<?php

namespace App\Actions;

use App\Enums\ClaimingMethod;
use App\Enums\PaymentStatus;
use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use App\Exceptions\RequestNotAllowedException;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Creates a request for a student, enforcing the single-request rule.
 *
 * Input shape (already validated by the form request):
 *   $data  = [purpose?, requester_remarks?, payment_method?, payment_reference?,
 *             payment_amount?, payment_paid_on?]
 *   $files = [document_requirement_id => UploadedFile]
 */
class SubmitDocumentRequest
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function execute(
        User $student,
        DocumentType $type,
        array $data,
        array $files,
        ?UploadedFile $paymentProof = null,
    ): DocumentRequest {
        if (! $type->is_active) {
            throw RequestNotAllowedException::documentUnavailable();
        }

        $disk = Storage::disk(config('smartdoc.uploads.disk'));
        $stored = [];

        try {
            return DB::transaction(function () use ($student, $type, $data, $files, $paymentProof, $disk, &$stored) {
                // Serialise this student's submissions so two tabs can't both pass the check.
                User::query()->whereKey($student->id)->lockForUpdate()->first();

                if (DocumentRequest::query()->where('user_id', $student->id)->blocking()->exists()) {
                    throw RequestNotAllowedException::activeRequestExists();
                }

                $needsPayment = (float) $type->fee > 0;
                $reference = $needsPayment ? trim((string) ($data['payment_reference'] ?? '')) : null;

                if ($needsPayment
                    && DocumentRequest::query()->where('payment_reference', $reference)->exists()) {
                    throw RequestNotAllowedException::duplicatePaymentReference();
                }

                $proofPath = null;
                $metadata = null;

                if ($needsPayment && $paymentProof !== null) {
                    $proofPath = $disk->putFile("payment-proofs/{$student->id}", $paymentProof);
                    $stored[] = $proofPath;
                    $metadata = [
                        'proof_original_name' => $paymentProof->getClientOriginalName(),
                        'proof_size_bytes' => $paymentProof->getSize(),
                        'submitted_at' => now()->toIso8601String(),
                    ];
                }

                $request = DocumentRequest::create([
                    'user_id' => $student->id,
                    'document_type_id' => $type->id,
                    'purpose' => $data['purpose'] ?? null,
                    'requester_remarks' => $data['requester_remarks'] ?? null,
                    'claiming_method' => ClaimingMethod::PickUp,
                    'status' => RequestStatus::Pending,
                    'current_stage' => RequestStage::RequestReceived,
                    'payment_method' => $needsPayment ? ($data['payment_method'] ?? null) : null,
                    'payment_reference' => $reference,
                    'payment_amount' => $needsPayment ? ($data['payment_amount'] ?? 0) : 0,
                    'payment_status' => $needsPayment ? PaymentStatus::Submitted : PaymentStatus::NotRequired,
                    'payment_proof_path' => $proofPath,
                    'payment_paid_on' => $needsPayment ? ($data['payment_paid_on'] ?? null) : null,
                    'payment_metadata' => $metadata,
                ]);

                $request->forceFill([
                    'estimated_completion_date' => now()->addDays((int) $type->processing_days)->toDateString(),
                ])->save();

                foreach ($files as $requirementId => $file) {
                    $path = $disk->putFile("requirements/{$student->id}", $file);
                    $stored[] = $path;

                    $request->uploads()->create([
                        'document_requirement_id' => $requirementId,
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                    ]);
                }

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            $this->cleanup($disk, $stored);

            throw RequestNotAllowedException::duplicatePaymentReference();
        } catch (Throwable $e) {
            $this->cleanup($disk, $stored);

            throw $e;
        }
    }

    /** @param  array<int, string>  $paths */
    private function cleanup($disk, array $paths): void
    {
        foreach ($paths as $path) {
            $disk->delete($path);
        }
    }
}
