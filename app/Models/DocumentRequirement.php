<?php

namespace App\Models;

use App\Models\Concerns\ArchivesRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentRequirement extends Model
{
    use ArchivesRecords;

    protected $fillable = ['document_type_id', 'name', 'description', 'is_required'];

    protected $casts = ['is_required' => 'boolean'];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(UploadedRequirement::class);
    }
}
