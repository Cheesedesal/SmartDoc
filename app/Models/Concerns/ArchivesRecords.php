<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait ArchivesRecords
{
    public function initializeArchivesRecords(): void
    {
        $this->casts['archived_at'] = 'datetime';
    }

    public function scopeUnarchived(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('archived_at'));
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull($this->qualifyColumn('archived_at'));
    }

    public function archive(): void
    {
        $this->forceFill(['archived_at' => Carbon::now()])->save();
    }

    public function restoreArchive(): void
    {
        $this->forceFill(['archived_at' => null])->save();
    }
}
