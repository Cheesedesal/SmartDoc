<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use App\Exceptions\RequestNotAllowedException;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Student-side transitions. Staff no longer change request status by hand;
 * everything else is driven by App\Services\RequestAutomation.
 *
 * Every method re-reads the row under a lock and checks ownership, so callers
 * cannot act on another student's request or on a stale status.
 */
class RequestLifecycle
{
    /** Pending -> Cancelled. Frees the student's slot. */
    public function cancel(DocumentRequest $request, User $student, ?string $reason = null): DocumentRequest
    {
        return $this->locked($request, $student, function (DocumentRequest $row) use ($reason) {
            if ($row->status !== RequestStatus::Pending) {
                throw RequestNotAllowedException::invalidState('cancelled');
            }

            $row->forceFill([
                'status' => RequestStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason !== null ? mb_substr($reason, 0, 255) : null,
            ])->save();

            return $row;
        });
    }

    /** Ready for Pickup -> Completed (the student confirms they received it). */
    public function confirmReceived(DocumentRequest $request, User $student): DocumentRequest
    {
        return $this->locked($request, $student, function (DocumentRequest $row) {
            if ($row->status !== RequestStatus::ReadyForPickup) {
                throw RequestNotAllowedException::invalidState('marked as received');
            }

            $row->forceFill([
                'status' => RequestStatus::Completed,
                'current_stage' => RequestStage::Released,
                'completed_at' => now(),
                'released_at' => now(),
                'stage_updated_at' => now(),
            ])->save();

            return $row;
        });
    }

    /** Completed -> archived. Frees the student's slot for a new request. */
    public function archive(DocumentRequest $request, User $student): DocumentRequest
    {
        return $this->locked($request, $student, function (DocumentRequest $row) {
            if ($row->status !== RequestStatus::Completed || $row->is_archived) {
                throw RequestNotAllowedException::invalidState('archived');
            }

            $row->forceFill(['is_archived' => true, 'archived_at' => now()])->save();

            return $row;
        });
    }

    /**
     * Replace flagged payment details and send the request back for re-checking.
     *
     * @param  array<string, mixed>  $data  payment_method, payment_reference, payment_amount, payment_paid_on
     */
    public function resubmitPayment(
        DocumentRequest $request,
        User $student,
        array $data,
        UploadedFile $proof,
    ): DocumentRequest {
        $disk = Storage::disk(config('smartdoc.uploads.disk'));
        $newPath = null;
        $oldPath = null;

        try {
            $result = $this->locked($request, $student, function (DocumentRequest $row) use ($data, $proof, $disk, &$newPath, &$oldPath) {
                if ($row->status !== RequestStatus::Pending || $row->payment_status !== PaymentStatus::Flagged) {
                    throw RequestNotAllowedException::invalidState('resubmitted for payment review');
                }

                $reference = trim((string) $data['payment_reference']);

                $taken = DocumentRequest::query()
                    ->where('payment_reference', $reference)
                    ->whereKeyNot($row->id)
                    ->exists();

                if ($taken) {
                    throw RequestNotAllowedException::duplicatePaymentReference();
                }

                $oldPath = $row->payment_proof_path;
                $newPath = $disk->putFile("payment-proofs/{$row->user_id}", $proof);

                $row->forceFill([
                    'payment_method' => $data['payment_method'],
                    'payment_reference' => $reference,
                    'payment_amount' => $data['payment_amount'],
                    'payment_paid_on' => $data['payment_paid_on'],
                    'payment_proof_path' => $newPath,
                    'payment_status' => PaymentStatus::Submitted,
                    'payment_metadata' => [
                        'proof_original_name' => $proof->getClientOriginalName(),
                        'proof_size_bytes' => $proof->getSize(),
                        'resubmitted_at' => now()->toIso8601String(),
                    ],
                    // Sends it back into the automation queue.
                    'ai_checked_at' => null,
                    'ai_remarks' => null,
                    'ai_confidence' => null,
                ])->save();

                return $row;
            });
        } catch (UniqueConstraintViolationException) {
            if ($newPath !== null) {
                $disk->delete($newPath);
            }

            throw RequestNotAllowedException::duplicatePaymentReference();
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                $disk->delete($newPath);
            }

            throw $e;
        }

        // Only remove the old proof once the new details are safely committed.
        if ($oldPath !== null && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }

        return $result;
    }

    /**
     * @template T
     *
     * @param  callable(DocumentRequest): T  $callback
     * @return T
     */
    private function locked(DocumentRequest $request, User $student, callable $callback)
    {
        return DB::transaction(function () use ($request, $student, $callback) {
            $row = DocumentRequest::query()->lockForUpdate()->find($request->id);

            if ($row === null || (int) $row->user_id !== (int) $student->id) {
                throw RequestNotAllowedException::invalidState('changed');
            }

            return $callback($row);
        });
    }
}
