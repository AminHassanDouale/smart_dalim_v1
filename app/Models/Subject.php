<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(TeacherProfile::class, 'subject_teacher', 'subject_id', 'teacher_profile_id');
    }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_subjects');
    }
    public function learningSessions()
    {
        return $this->hasMany(LearningSession::class);
    }


}
