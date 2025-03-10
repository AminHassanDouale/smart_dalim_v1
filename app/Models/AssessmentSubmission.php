<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSubmission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessment_id',
        'children_id',
        'client_profile_id',
        'start_time',
        'end_time',
        'score',
        'status',
        'answers',
        'feedback',
        'graded_by',
        'graded_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'graded_at' => 'datetime',
        'score' => 'integer',
        'answers' => 'json',
        'feedback' => 'json',
    ];

    /**
     * Submission statuses.
     *
     * @var array
     */
    public static $statuses = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'graded' => 'Graded',
        'late' => 'Late',
    ];

    /**
     * Get the assessment that owns the submission.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Get the child that owns the submission.
     */
    public function children(): BelongsTo
    {
        return $this->belongsTo(Children::class, 'children_id');
    }

    /**
     * Get the client that owns the submission.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class, 'client_profile_id');
    }

    /**
     * Get the user that graded the submission.
     */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /**
     * Check if the submission is from a child.
     */
    public function isFromChild(): bool
    {
        return !is_null($this->children_id);
    }

    /**
     * Check if the submission is from a client.
     */
    public function isFromClient(): bool
    {
        return !is_null($this->client_profile_id);
    }

    /**
     * Get the participant name (from either child or client).
     */
    public function getParticipantNameAttribute(): string
    {
        if ($this->isFromChild() && $this->children) {
            return $this->children->name;
        }

        if ($this->isFromClient() && $this->client) {
            return $this->client->company_name ?: $this->client->user->name;
        }

        return 'Unknown';
    }

    /**
     * Check if the submission is completed.
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'graded']);
    }

    /**
     * Check if the submission is graded.
     */
    public function isGraded(): bool
    {
        return $this->status === 'graded';
    }

    /**
     * Check if the submission is late.
     */
    public function isLate(): bool
    {
        return $this->status === 'late' ||
            ($this->end_time && $this->assessment->due_date && $this->end_time->gt($this->assessment->due_date));
    }

    /**
     * Get the duration of the submission.
     */
    public function getDurationAttribute(): int
    {
        if (!$this->start_time || !$this->end_time) {
            return 0;
        }

        return $this->start_time->diffInMinutes($this->end_time);
    }
}
