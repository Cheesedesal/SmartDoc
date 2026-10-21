<?php

namespace App\Models;

use App\Models\Concerns\ArchivesRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use ArchivesRecords;

    protected $fillable = ['code', 'name', 'status'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive($query)
    {
        return $query->unarchived()->where('status', 'active')->orderBy('id');
    }
}
