<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use App\Models\DocumentRequest;
use App\Models\RequestAiLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Replaces manual staff status updates.
 *
 *  Pending (not yet checked)  --verify-->  In Progress / For Verification
 *                                      \-> stays Pending, held with remarks (nothing is rejected)
 *  In Progress                --clock-->   For Preparation -> For Finalization -> Ready for Pickup
 *
 * The student still confirms receipt (Ready for Pickup -> Completed) elsewhere.
 */
class RequestAutomation
{
    public function __construct(private readonly RequestEvaluator $evaluator)
    {
    }

    /** Run one pass. Returns how many requests changed. */
    public function run(): int
    {
        return $this->verifyPending() + $this->advanceInProgress();
    }

    /**
     * Cheap page-load trigger: runs at most once per tick window, across all users.
     */
    public function runIfDue(): int
    {
        $tick = (int) config('smartdoc.automation.tick_seconds');

        if ($tick <= 0 || ! Cache::add('smartdoc.automation.tick', true, $tick)) {
            return 0;
        }

        return $this->run();
    }

    public function verifyPending(): int
    {
        $changed = 0;

        DocumentRequest::query()
            ->where('status', RequestStatus::Pending->value)
            ->whereNull('ai_checked_at')
            ->where('is_archived', false)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$changed) {
                $changed += $this->verify($id) ? 1 : 0;
            });

        return $changed;
    }

    public function advanceInProgress(): int
    {
        $changed = 0;

        DocumentRequest::query()
            ->where('status', RequestStatus::InProgress->value)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$changed) {
                $changed += $this->advance($id) ? 1 : 0;
            });

        return $changed;
    }

    private function verify(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $request = DocumentRequest::with(['documentType.requirements', 'uploads'])
                ->lockForUpdate()
                ->find($id);

            // Re-check inside the lock so two workers can't both process it.
            if ($request === null
                || $request->status !== RequestStatus::Pending
                || $request->ai_checked_at !== null) {
                return false;
            }

            $result = $this->evaluator->evaluate($request);
            $feeApplies = (float) $request->documentType->fee > 0;

            if ($result['passed']) {
                $request->forceFill([
                    'status' => RequestStatus::InProgress,
                    'current_stage' => RequestStage::ForVerification,
                    'stage_updated_at' => now(),
                    'approved_at' => now(),
                    'ai_checked_at' => now(),
                    'ai_confidence' => $result['confidence'],
                    'ai_remarks' => 'All requirements and payment details passed automatic verification.',
                    'payment_status' => $feeApplies ? PaymentStatus::Paid : PaymentStatus::NotRequired,
                    'payment_verified_at' => $feeApplies ? now() : null,
                ])->save();

                $request->uploads()->update(['is_verified' => true]);

                $this->log($request, 'verified', RequestStage::RequestReceived->value,
                    RequestStage::ForVerification->value, $result['confidence'], 'Passed automatic verification.');

                return true;
            }

            $request->forceFill([
                'ai_checked_at' => now(),
                'ai_confidence' => $result['confidence'],
                'ai_remarks' => implode("\n", $result['issues']),
                'payment_status' => $feeApplies && ! $result['payment_ok']
                    ? PaymentStatus::Flagged
                    : $request->payment_status,
            ])->save();

            $this->log($request, 'held', null, null, $result['confidence'], implode(' | ', $result['issues']));

            return true;
        });
    }

    private function advance(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $request = DocumentRequest::with('documentType')->lockForUpdate()->find($id);

            if ($request === null || $request->status !== RequestStatus::InProgress) {
                return false;
            }

            $next = $request->current_stage?->next();

            if ($next === null) {
                return false;
            }

            $since = $request->stage_updated_at ?? $request->approved_at ?? $request->updated_at;

            if ($since === null || $since->copy()->addSeconds($this->stageSeconds($request))->isFuture()) {
                return false;
            }

            $from = $request->current_stage->value;
            $ready = $next === RequestStage::ReadyForRelease;

            $request->forceFill([
                'current_stage' => $next,
                'stage_updated_at' => now(),
                'status' => $ready ? RequestStatus::ReadyForPickup : RequestStatus::InProgress,
            ])->save();

            $this->log($request, $ready ? 'ready_for_pickup' : 'stage_advanced', $from, $next->value, null,
                $ready ? 'Document is ready for pick-up.' : "Moved to {$next->value}.");

            return true;
        });
    }

    /** processing_days are split evenly over the three working stages. */
    public function stageSeconds(DocumentRequest $request): int
    {
        $perStage = (int) round(((int) $request->documentType->processing_days * 86400) / 3);

        return max((int) config('smartdoc.automation.min_stage_seconds'), $perStage);
    }

    private function log(
        DocumentRequest $request,
        string $action,
        ?string $from,
        ?string $to,
        ?int $confidence,
        ?string $details,
    ): void {
        RequestAiLog::create([
            'document_request_id' => $request->id,
            'action' => $action,
            'from_stage' => $from,
            'to_stage' => $to,
            'confidence' => $confidence,
            'details' => $details,
        ]);
    }
}
