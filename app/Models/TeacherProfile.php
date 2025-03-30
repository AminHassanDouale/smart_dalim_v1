<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TeacherProfile extends Model
{
    use HasFactory;

    /**
     * Status constants
     */
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CHECKING = 'checking';
    public const STATUS_VERIFIED = 'verified';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'whatsapp',
        'phone',
        'fix_number',
        'photo',
        'date_of_birth',
        'place_of_birth',
        'has_completed_profile',
        'status',
        'bio',
        'education',
        'experience',
        'specialization'
    ];

    /**
     * Default values for required fields
     *
     * @var array
     */
    protected $attributes = [
        'whatsapp' => '',
        'phone' => '',
        'place_of_birth' => '',
        'has_completed_profile' => false,
        'status' => 'submitted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'has_completed_profile' => 'boolean',
        'education' => 'json',
        'experience' => 'json',
    ];

    /**
     * Get the user that owns the teacher profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the files for the teacher profile.
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    /**
     * Get the subjects associated with the teacher.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher', 'teacher_profile_id', 'subject_id');
    }

    /**
     * Get the courses created by the teacher.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_profile_id');
    }

    /**
     * Get the materials created by the teacher.
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    /**
     * Get the assessments created by the teacher.
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Get the teacher's students.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Children::class, 'teacher_id', 'user_id');
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_CHECKING,
            self::STATUS_VERIFIED
        ];
    }

    /**
     * Check if the profile is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Check if the profile is being checked
     */
    public function isChecking(): bool
    {
        return $this->status === self::STATUS_CHECKING;
    }

    /**
     * Check if the profile is verified
     */
    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    /**
     * Get upcoming learning sessions for this teacher.
     */
    public function learningSessions(): HasMany
    {
        return $this->hasMany(LearningSession::class, 'teacher_id', 'user_id');
    }
}
