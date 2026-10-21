<?php

namespace App\Models;

use App\Models\Concerns\ArchivesRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use ArchivesRecords;

    protected $fillable = ['student_year_id', 'name', 'status'];

    public function studentYear(): BelongsTo
    {
        return $this->belongsTo(StudentYear::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
