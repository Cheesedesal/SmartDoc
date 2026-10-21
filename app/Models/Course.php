<?php

namespace App\Models;

use App\Models\Concerns\ArchivesRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use ArchivesRecords;

    protected $fillable = ['code', 'name', 'status'];

    public function years(): HasMany
    {
        return $this->hasMany(StudentYear::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
