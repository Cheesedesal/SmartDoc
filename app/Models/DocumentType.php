<?php

namespace App\Models;

use App\Models\Concerns\ArchivesRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use ArchivesRecords;

    protected $fillable = ['name', 'description', 'processing_days', 'fee', 'is_active'];

    protected $casts = [
        'processing_days' => 'integer',
        'fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function requirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->unarchived()->where('is_active', true)->orderBy('name');
    }
}
