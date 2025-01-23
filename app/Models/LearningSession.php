<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LearningSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'children_id',
        'subject_id',
        'start_time',
        'end_time',
        'status',
        'attended',
        'performance_score'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'attended' => 'boolean',
        'performance_score' => 'decimal:2'
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Children::class, 'children_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
