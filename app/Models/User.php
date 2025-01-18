<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements CanResetPassword
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Role constants
     */
    public const ROLE_PARENT = 'parent';
    public const ROLE_TEACHER = 'teacher';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'username'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Get the parent profile associated with the user.
     */
    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    /**
     * Get the teacher profile associated with the user.
     */
    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    /**
     * Check if user has completed their profile
     */


    /**
     * Get the dashboard route for the user based on their role
     */
    public function getDashboardRoute(): string
    {
        if ($this->role === self::ROLE_PARENT) {
            return 'parents.dashboard';
        }

        if ($this->role === self::ROLE_TEACHER) {
            return 'teachers.dashboard';
        }

        return 'login';
    }

    /**
     * Get the profile setup route for the user based on their role
     */
    public function getProfileSetupRoute(): string
    {
        if ($this->role === self::ROLE_PARENT) {
            return 'parents.profile-setup.steps'; // Updated to match new component structure
        }

        if ($this->role === self::ROLE_TEACHER) {
            return 'teachers.profile-setup';
        }

        return 'login';
    }

    public function hasCompletedProfile(): bool
    {
        if ($this->role === self::ROLE_PARENT) {
            return $this->parentProfile?->has_completed_profile && $this->parentProfile->children()->exists();
        }

        if ($this->role === self::ROLE_TEACHER) {
            return $this->teacherProfile()->exists();
        }

        return false;
    }
public function subjects(): BelongsToMany
{
    return $this->belongsToMany(Subject::class, 'user_subjects');
}

}
