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
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable implements CanResetPassword {
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Role constants
     */
    public const ROLE_PARENT = 'parent';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_CLIENT = 'client'; // Add client role
    public const ROLE_ADMIN = 'admin'; // Add admin role


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
     * Get the client profile associated with the user.
     */
    public function clientProfile(): HasOne
    {
        return $this->hasOne(ClientProfile::class);
    }

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

        if ($this->role === self::ROLE_CLIENT) {
            return 'clients.dashboard';
        }

        return 'login';
    }

    /**
     * Get the profile setup route for the user based on their role
     */
    public function getProfileSetupRoute(): string
    {
        if ($this->role === self::ROLE_PARENT) {
            return 'parents.profile-setup.steps';
        }

        if ($this->role === self::ROLE_TEACHER) {
            return 'teachers.profile-setup';
        }

        if ($this->role === self::ROLE_CLIENT) {
            return 'clients.profile-setup';
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

        if ($this->role === self::ROLE_CLIENT) {
            return $this->clientProfile?->has_completed_profile;
        }

        return false;
    }

    public function subjects(): BelongsToMany {
        return $this->belongsToMany(Subject::class, 'user_subjects');
    }
    /**
 * Get all children associated with the user (through parent profile).
 */
public function children()
{
    return $this->hasManyThrough(
        Children::class,
        ParentProfile::class,
        'user_id',     // Foreign key on parent_profiles table
        'parent_profile_id', // Foreign key on children table
        'id',         // Local key on users table
        'id'          // Local key on parent_profiles table
    );
}
public function assignedHomework()
{
    return $this->hasMany(Homework::class, 'teacher_id');
}

  /**
     * Get user's custom notifications
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

      /**
     * Get the support tickets for the user.
     */
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get the invoices for the user.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the support messages for the user.
     */
    public function supportMessages()
    {
        return $this->hasMany(SupportMessage::class);
    }
}
