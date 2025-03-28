<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Homework extends Model
{
    use HasFactory;

    protected $table = 'homework';

    protected $fillable = [
        'title',
        'description',
        'child_id',
        'subject_id',
        'teacher_id',
        'due_date',
        'is_completed',
        'completed_at',
        'max_score',
        'achieved_score',
        'teacher_feedback',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Children::class, 'child_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HomeworkAttachment::class);
    }
}