<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'school_id', 'firstname', 'surname', 'email', 'department_id',
        'course', 'year', 'section', 'position', 'password', 'role', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
            'password' => 'hashed',
            'role' => Role::class,
            'status' => UserStatus::class,
        ];

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

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }
}
