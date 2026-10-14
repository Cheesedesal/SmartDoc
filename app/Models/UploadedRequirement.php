<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadedRequirement extends Model
{
    protected $fillable = [
        'document_request_id', 'document_requirement_id',
        'original_name', 'path', 'is_verified',
    ];

    protected $casts = ['is_verified' => 'boolean'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class, 'document_request_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class, 'document_requirement_id');
    }
}
