<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Concerns\ArchivesRecords;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use ArchivesRecords, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'school_id', 'firstname', 'surname', 'email', 'department_id', 'office_id',
        'position_id', 'course_id', 'student_year_id', 'section_id',
        'course', 'year', 'section', 'position', 'password', 'role', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'role' => Role::class,
        'status' => UserStatus::class,
        'inactive_since' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->status === UserStatus::Deactivated && $user->inactive_since === null) {
                $user->inactive_since = now();
            }
        });

        static::updating(function (User $user) {
            if ($user->isDirty('status')) {
                $user->inactive_since = $user->status === UserStatus::Deactivated ? now() : null;
            }
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => "{$this->firstname} {$this->surname}");
    }

    public function hasRole(Role|string ...$roles): bool
    {
        $current = $this->role instanceof Role ? $this->role->value : $this->role;

        foreach ($roles as $role) {
            if ($current === ($role instanceof Role ? $role->value : $role)) {
                return true;
            }
        }

        return false;
    }

    public function isActivated(): bool
    {
        return $this->status === UserStatus::Activated;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function positionRecord(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function courseRecord(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function yearRecord(): BelongsTo
    {
        return $this->belongsTo(StudentYear::class, 'student_year_id');
    }

    public function sectionRecord(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function chatbotMessages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class);
    }
}
