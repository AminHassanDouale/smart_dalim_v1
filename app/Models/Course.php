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
       'end_date'
   ];
   protected $casts = [
       'curriculum' => 'array',
       'prerequisites' => 'array',
       'learning_outcomes' => 'array',
       'price' => 'decimal:2',
       'start_date' => 'datetime',
       'end_date' => 'datetime'
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
}
