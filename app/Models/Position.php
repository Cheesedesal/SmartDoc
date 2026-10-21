<?php

namespace App\Models;

use App\Models\Concerns\ArchivesRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use ArchivesRecords;

    protected $fillable = ['name', 'role', 'status'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
