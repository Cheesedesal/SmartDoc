<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestAiLog extends Model
{
    protected $fillable = [
        'document_request_id', 'action', 'from_stage', 'to_stage', 'confidence', 'details',
    ];

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }
}
