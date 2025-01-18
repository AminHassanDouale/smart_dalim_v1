<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'children_id',
        'subject_id',
        'teacher_id',
        'start_time',
        'end_time',
        'status',
        'attended',
        'performance_score',
        'notes'
    ];

    protected $attributes = [
        'status' => 'scheduled',
        'attended' => false,
        'performance_score' => null
    ];
    public function child(): BelongsTo
    {
        return $this->belongsTo(Children::class, 'children_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
    public function scopeActive($query) {
        return $query->whereHas('learningSessions', function($q) {
            $q->where('status', 'active');
        });
    }
}
