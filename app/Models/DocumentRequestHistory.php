<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequestHistory extends Model
{
    protected $fillable = [
        'document_request_id',
        'changed_by',
        'event',
        'from_status',
        'to_status',
        'from_stage',
        'to_stage',
        'remarks',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class, 'document_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
