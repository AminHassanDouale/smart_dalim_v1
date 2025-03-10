<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentQuestion extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assessment_id',
        'question',
        'type',
        'options',
        'correct_answer',
        'points',
        'order',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'json',
        'metadata' => 'json',
        'points' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Question types.
     *
     * @var array
     */
    public static $types = [
        'multiple_choice' => 'Multiple Choice',
        'multiple_answer' => 'Multiple Answer',
        'true_false' => 'True/False',
        'short_answer' => 'Short Answer',
        'essay' => 'Essay',
        'fill_blank' => 'Fill in the Blank',
        'matching' => 'Matching',
        'numeric' => 'Numeric',
        'ordering' => 'Ordering',
    ];

    /**
     * Get the assessment that owns the question.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * Check if the question is multiple choice.
     */
    public function isMultipleChoice(): bool
    {
        return $this->type === 'multiple_choice';
    }

    /**
     * Check if the question is true/false.
     */
    public function isTrueFalse(): bool
    {
        return $this->type === 'true_false';
    }

    /**
     * Check if the question needs manual grading.
     */
    public function needsManualGrading(): bool
    {
        return in_array($this->type, ['essay', 'short_answer']);
    }
}