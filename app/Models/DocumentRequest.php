<?php

namespace App\Models;

use App\Enums\ClaimingMethod;
use App\Enums\PaymentStatus;
use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentRequest extends Model
{
    protected $fillable = [
        'user_id', 'document_type_id', 'purpose', 'claiming_method',
        'requester_remarks', 'staff_remarks', 'status', 'current_stage',
        'payment_method', 'payment_reference', 'payment_amount', 'payment_status',
        'payment_proof_path', 'payment_paid_on', 'payment_metadata',
    ];

    protected $casts = [
        'status' => RequestStatus::class,
        'current_stage' => RequestStage::class,
        'claiming_method' => ClaimingMethod::class,
        'payment_status' => PaymentStatus::class,
        'payment_amount' => 'decimal:2',
        'payment_paid_on' => 'date',
        'payment_verified_at' => 'datetime',
        'payment_metadata' => 'array',
        'estimated_completion_date' => 'date',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'released_at' => 'datetime',
        'ai_checked_at' => 'datetime',
        'stage_updated_at' => 'datetime',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /** "REQ-007" style reference, as shown in the legacy reports. */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => 'REQ-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT));
    }

    /** Progress-bar value derived from status and stage. */
    protected function progress(): Attribute
    {
        return Attribute::get(fn () => match ($this->status) {
            RequestStatus::Pending, RequestStatus::Rejected, RequestStatus::Cancelled => 0,
            RequestStatus::ReadyForPickup, RequestStatus::Completed => 100,
            default => ($this->current_stage ?? RequestStage::RequestReceived)->progress(),
        });
    }

    /** Requests that occupy a student's single slot: ongoing, or completed but not archived. */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query
            ->whereIn('status', RequestStatus::blockingValues())
            ->where('is_archived', false);
    }

    /** Finished requests shown in the read-only history: Completed, plus legacy Rejected rows. */
    public function scopeRecords(Builder $query): Builder
    {
        return $query->whereIn('status', RequestStatus::recordValues());
    }

    /** Requests still being worked on (the AI / staff-monitor queue). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', RequestStatus::openValues());
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_processed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(UploadedRequirement::class);
    }

    public function aiLogs(): HasMany
    {
        return $this->hasMany(RequestAiLog::class)->latest('id');
    }

    /**
     * Stage timeline built only from persisted data: the current status/stage decide each step's
     * state, and a step's time comes from the automation log (or the dedicated timestamp columns).
     * Nothing is invented: a step with no recorded time shows no time.
     *
     * @return array<int, array{stage: string, state: string, at: ?\Illuminate\Support\Carbon}>
     */
    public function timeline(): array
    {
        $stages = [
            RequestStage::RequestReceived, RequestStage::ForVerification, RequestStage::ForPreparation,
            RequestStage::ForFinalization, RequestStage::ReadyForRelease, RequestStage::Released,
        ];

        $stopped = in_array($this->status, [RequestStatus::Cancelled, RequestStatus::Rejected], true);
        $completed = $this->status === RequestStatus::Completed;
        $current = array_search($this->current_stage ?? RequestStage::RequestReceived, $stages, true);
        $current = $current === false ? 0 : $current;
        $logs = $this->aiLogs->sortBy('id');

        $steps = [];

        foreach ($stages as $i => $stage) {
            $state = match (true) {
                $stopped => $i === 0 ? 'done' : 'upcoming',
                $completed => 'done',
                $i < $current => 'done',
                $i === $current => 'current',
                default => 'upcoming',
            };

            $at = null;

            if ($state !== 'upcoming') {
                $at = match ($stage) {
                    RequestStage::RequestReceived => $this->created_at,
                    RequestStage::Released => $this->released_at ?? $this->completed_at,
                    default => $logs->firstWhere('to_stage', $stage->value)?->created_at
                        ?? match ($stage) {
                            RequestStage::ForVerification => $this->approved_at,
                            RequestStage::ReadyForRelease => $this->completed_at,
                            default => null,
                        },
                };
            }

            $steps[] = ['stage' => $stage->value, 'state' => $state, 'at' => $at];
        }

        return $steps;
    }
}
