<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Children extends Model
{
    use HasFactory;
    protected $fillable = [
        'parent_profile_id',
        'teacher_id',
        'name',
        'age',
        'available_times',
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
    public function learningSessions()
{
    return $this->hasMany(LearningSession::class, 'children_id');
}
}
