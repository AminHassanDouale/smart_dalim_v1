<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'type',
        'teacher_profile_id',
        'course_id',
        'subject_id',
        'total_points',
        'passing_points',
        'due_date',
        'start_date',
        'time_limit',
        'is_published',
        'settings',
        'instructions',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_published' => 'boolean',
        'total_points' => 'integer',
        'passing_points' => 'integer',
        'time_limit' => 'integer',
        'due_date' => 'datetime',
        'start_date' => 'datetime',
        'settings' => 'json',
    ];

    /**
     * The assessment types.
     *
     * @var array
     */
    public static $types = [
        'quiz' => 'Quiz',
        'test' => 'Test',
        'exam' => 'Exam',
        'assignment' => 'Assignment',
        'project' => 'Project',
        'essay' => 'Essay',
        'presentation' => 'Presentation',
        'other' => 'Other',
    ];

    /**
     * Assessment statuses.
     *
     * @var array
     */
    public static $statuses = [
        'draft' => 'Draft',
        'published' => 'Published',
        'active' => 'Active',
        'ended' => 'Ended',
        'archived' => 'Archived',
    ];

    /**
     * Get the teacher profile that owns the assessment.
     */
    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    /**
     * Get the course that the assessment belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the subject that the assessment belongs to.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the questions for this assessment.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class);
    }

    /**
     * Get the submissions for this assessment.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssessmentSubmission::class);
    }

    /**
     * Get the materials associated with this assessment.
     */
    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'assessment_material');
    }

    /**
     * Get the children assigned to this assessment.
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Children::class, 'assessment_children')
            ->withPivot(['status', 'start_time', 'end_time', 'score'])
            ->withTimestamps();
    }

    /**
     * Get the clients assigned to this assessment.
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(ClientProfile::class, 'assessment_client')
            ->withPivot(['status', 'start_time', 'end_time', 'score'])
            ->withTimestamps();
    }

    /**
     * Get all participants (children and clients) for this assessment.
     * This is a convenience method to get all participants.
     */
    public function getAllParticipantsCount(): int
    {
        return $this->children()->count() + $this->clients()->count();
    }

    /**
     * Check if the assessment is published.
     */
    public function isPublished(): bool
    {
        return $this->is_published;
    }

    /**
     * Check if the assessment has started.
     */
    public function hasStarted(): bool
    {
        return $this->start_date && now()->gte($this->start_date);
    }

    /**
     * Check if the assessment has ended.
     */
    public function hasEnded(): bool
    {
        return $this->due_date && now()->gte($this->due_date);
    }

    /**
     * Get the status of the assessment.
     */
    public function getStatus(): string
    {
        if (!$this->is_published) {
            return 'draft';
        }

        if ($this->hasEnded()) {
            return 'ended';
        }

        if ($this->hasStarted()) {
            return 'active';
        }

        return 'published';
    }

    /**
     * Determine the progress percentage for this assessment.
     */
    public function getProgressAttribute(): int
    {
        // Get counts from both children and clients
        $totalChildrenSubmissions = $this->children()->count();
        $totalClientSubmissions = $this->clients()->count();
        $totalParticipants = $totalChildrenSubmissions + $totalClientSubmissions;

        if ($totalParticipants === 0) {
            return 0;
        }

        $completedChildrenSubmissions = $this->children()
            ->wherePivot('status', 'completed')
            ->count();

        $completedClientSubmissions = $this->clients()
            ->wherePivot('status', 'completed')
            ->count();

        $totalCompleted = $completedChildrenSubmissions + $completedClientSubmissions;

        return round(($totalCompleted / $totalParticipants) * 100);
    }

    /**
     * Get the average score for this assessment.
     */
    public function getAverageScoreAttribute(): float
    {
        $completedChildrenSubmissions = $this->children()
            ->wherePivot('status', 'completed')
            ->get();

        $completedClientSubmissions = $this->clients()
            ->wherePivot('status', 'completed')
            ->get();

        $allCompletedSubmissions = $completedChildrenSubmissions->concat($completedClientSubmissions);

        if ($allCompletedSubmissions->isEmpty()) {
            return 0;
        }

        $totalScore = $completedChildrenSubmissions->sum(fn ($child) => $child->pivot->score)
            + $completedClientSubmissions->sum(fn ($client) => $client->pivot->score);

        return round($totalScore / $allCompletedSubmissions->count(), 1);
    }

    /**
     * Get the formatted time limit.
     */
    public function getFormattedTimeLimitAttribute(): string
    {
        if (!$this->time_limit) {
            return 'No time limit';
        }

        $hours = floor($this->time_limit / 60);
        $minutes = $this->time_limit % 60;

        if ($hours > 0) {
            return "{$hours}h " . ($minutes > 0 ? "{$minutes}m" : "");
        }

        return "{$minutes} minutes";
    }

}
