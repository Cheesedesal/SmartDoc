<?php

namespace App\Models;

use App\Enums\ChatbotFaqStatus;
use App\Models\Concerns\ArchivesRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotFaq extends Model
{
    use ArchivesRecords;

    protected $fillable = [
        'intent',
        'category',
        'canonical_question',
        'question_examples',
        'answer',
        'locale',
        'status',
        'is_active',
        'is_suggested',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'question_examples' => 'array',
        'status' => ChatbotFaqStatus::class,
        'is_active' => 'boolean',
        'is_suggested' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->unarchived()
            ->where('status', ChatbotFaqStatus::Approved->value)
            ->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
