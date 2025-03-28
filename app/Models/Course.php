<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Course extends Model
{
   use HasFactory;
   protected $fillable = [
    'name',
    'slug',
    'description',
    'level',
    'duration',
    'price',
    'status', // active, inactive, draft
    'teacher_profile_id',
    'subject_id',
    'curriculum',
    'prerequisites',
    'learning_outcomes',
    'max_students',
    'start_date',
    'end_date',
    'cover_image' // Added cover_image to fillable
];

protected $casts = [
    'curriculum' => 'array',
    'prerequisites' => 'array',
    'learning_outcomes' => 'array',
    'price' => 'decimal:2',
    'start_date' => 'date', // Changed to date to match the component
    'end_date' => 'date', // Changed to date to match the component
    'duration' => 'integer' // Added explicit cast for duration
];
   public function teacher(): BelongsTo
   {
       return $this->belongsTo(TeacherProfile::class, 'teacher_profile_id');
   }
   public function subject(): BelongsTo
   {
       return $this->belongsTo(Subject::class);
   }
   public function schedules(): HasMany
   {
       return $this->hasMany(Schedule::class);
   }
   public function enrollments(): HasMany
   {
       return $this->hasMany(Enrollment::class);
   }
   public function scopeActive($query)
   {
       return $query->where('status', 'active');
   }

   // Add relationship to learning sessions
   public function learningSessions()
   {
       return $this->hasMany(LearningSession::class);
   }
   
}