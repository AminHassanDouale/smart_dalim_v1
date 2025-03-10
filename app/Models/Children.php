<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Children extends Model {
    use HasFactory;
    protected $table = 'childrens'; // Table name is specified

    protected $fillable = [
        'parent_profile_id',
        'teacher_id',
        'name',
        'age',
        'available_times',
        'gender',
        'school_name', // Add school_name to fillable
        'grade', // Add this

    ];

    protected $casts = [
        'age' => 'integer',
        'available_times' => 'array',
        'created_at',
        'updated_at',
        'last_session_at'
    ];

    public function parentProfile()
    {
        return $this->belongsTo(ParentProfile::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'children_subjects');
    }

    public function learningSessions() {
        return $this->hasMany(LearningSession::class, 'children_id');
    }

    public function assessments(): BelongsToMany
{
    return $this->belongsToMany(Assessment::class, 'assessment_children')
        ->withPivot(['status', 'start_time', 'end_time', 'score'])
        ->withTimestamps();
}

/**
 * Get assessment submissions for this child.
 */
public function assessmentSubmissions(): HasMany
{
    return $this->hasMany(AssessmentSubmission::class, 'children_id');
}
}