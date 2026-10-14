<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirement extends Model
{
    protected $fillable = ['document_type_id', 'name', 'description', 'is_required'];

    protected $casts = ['is_required' => 'boolean'];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
