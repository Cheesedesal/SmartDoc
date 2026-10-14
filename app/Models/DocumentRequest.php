<?php

namespace App\Models;

use App\Enums\ClaimingMethod;
use App\Enums\RequestStage;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentRequest extends Model
{
    protected $fillable = [
        'user_id', 'document_type_id', 'purpose', 'claiming_method',
        'requester_remarks', 'staff_remarks', 'status', 'current_stage',
    ];

    protected $casts = [
            'status' => RequestStatus::class,
            'current_stage' => RequestStage::class,
            'claiming_method' => ClaimingMethod::class,
            'estimated_completion_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'released_at' => 'datetime',
        ];

    /** "REQ-007" style reference, as shown in the legacy reports. */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => 'REQ-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT));
    }

    /** Port of the legacy progress-bar logic in RMFunctions. */
    protected function progress(): Attribute
    {
        return Attribute::get(fn () => match ($this->status) {
            RequestStatus::Pending, RequestStatus::Rejected => 0,
            RequestStatus::ReadyForPickup, RequestStatus::Completed => 100,
            default => $this->current_stage->progress(),
        });
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

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(UploadedRequirement::class);
    }
}
