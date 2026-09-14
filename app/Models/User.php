<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function createdSeatingPlans(): HasMany
    {
        return $this->hasMany(SeatingPlan::class, 'created_by');
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_user')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * @return \Illuminate\Support\Collection<int, School>
     */
    public function accessibleSchools()
    {
        if ($this->isSuperAdmin()) {
            return School::where('is_active', true)->orderBy('name')->get();
        }

        return $this->schools()->where('schools.is_active', true)->orderBy('name')->get();
    }
}
