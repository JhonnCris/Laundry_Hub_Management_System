<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The staff record (attendance, "handled by") is linked by email, so keep the two in step
     * and never let the link drift when a user edits their own profile.
     */
    protected static function booted(): void
    {
        static::updating(function (User $user) {
            if ($user->isDirty(['email', 'name'])) {
                Staff::query()
                    ->where('email', $user->getOriginal('email'))
                    ->update(['email' => $user->email, 'name' => $user->name]);
            }
        });
    }

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return ($this->role ?? 'staff') === 'admin';
    }

    public function isStaff(): bool
    {
        return ! $this->isAdmin();
    }

    /** Linked operations staff row (by email). */
    public function staffRecord(): ?Staff
    {
        return Staff::query()->where('email', $this->email)->first();
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
