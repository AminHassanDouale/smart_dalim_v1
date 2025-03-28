<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class LearningSession extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'teacher_id',
        'children_id',
        'subject_id',
        'course_id',
        'start_time',
        'end_time',
        'status',
        'attended',
        'performance_score',
        'location',
        'notes'
    ];
    
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'attended' => 'boolean',
        'performance_score' => 'decimal:2'
    ];
    
    protected $indexes = ['teacher_id', 'children_id', 'subject_id', 'course_id'];
    
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
    
    public function children(): BelongsTo
    {
        return $this->belongsTo(Children::class, 'children_id');
    }
    
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
    
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
    
    // Add scopes for common queries
    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', now())
                     ->where('status', self::STATUS_SCHEDULED);
    }
    
    public function scopePast($query)
    {
        return $query->where('start_time', '<', now());
    }
    
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
    
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('children_id', $studentId);
    }
    
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_time', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay()
        ]);
    }
    
    public function scopeBySubject($query, $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }
    
    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }
    
    // Helper methods
    public function markAsCompleted($attended = true, $notes = null)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'attended' => $attended,
            'notes' => $notes ?? $this->notes
        ]);
    }
    
    public function markAsCancelled($notes = null)
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'notes' => $notes ?? $this->notes
        ]);
    }
    
    public function reschedule($newStartTime, $newEndTime = null)
    {
        $start = Carbon::parse($newStartTime);
        $end = $newEndTime ? Carbon::parse($newEndTime) : $start->copy()->addHour();
        
        $this->update([
            'start_time' => $start,
            'end_time' => $end,
            'status' => self::STATUS_SCHEDULED
        ]);
    }
    
    public function getDurationInMinutes()
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    /**
     * Get the attendances for the learning session.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}