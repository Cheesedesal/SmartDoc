<?php

namespace App\Models;

use App\Enums\AnnouncementStatus;
use App\Enums\Audience;
use App\Enums\Role;
use App\Models\Concerns\ArchivesRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use ArchivesRecords;

    protected $fillable = ['title', 'message', 'audience', 'status', 'published_at', 'expires_at', 'created_by'];

    protected $casts = [
        'audience' => Audience::class,
        'status' => AnnouncementStatus::class,
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Port of legacy getRecentAnnouncements(). */
    public function scopeVisibleTo($query, Role $role)
    {
        return $query
            ->unarchived()
            ->whereIn('audience', [$role->value, Audience::All->value])
            ->where('status', AnnouncementStatus::Published->value)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->startOfDay()))
            ->latest('published_at');
    }
}
