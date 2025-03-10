<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Material extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'teacher_profile_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'external_url',
        'type',
        'is_public',
        'is_featured',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'file_size' => 'integer',
    ];

    /**
     * Get the teacher profile that owns the material.
     */
    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    /**
     * Get the subjects related to this material.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'material_subject');
    }

    /**
     * Get the courses related to this material.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_material')
            ->withPivot('order')
            ->withTimestamps();
    }

    /**
     * Get the URL to the file.
     */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path) {
            return Storage::url($this->file_path);
        }

        return null;
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Get the icon for the file type.
     */
    public function getFileIconAttribute(): string
    {
        $type = $this->type;

        switch ($type) {
            case 'document':
                return 'o-document-text';
            case 'pdf':
                return 'o-document';
            case 'spreadsheet':
                return 'o-table-cells';
            case 'presentation':
                return 'o-presentation-chart-bar';
            case 'video':
                return 'o-film';
            case 'audio':
                return 'o-musical-note';
            case 'image':
                return 'o-photo';
            case 'link':
                return 'o-link';
            case 'archive':
                return 'o-archive-box';
            default:
                return 'o-document';
        }
    }

    /**
     * Scope a query to only include public materials.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope a query to only include featured materials.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include materials of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include materials for a specific subject.
     */
    public function scopeForSubject($query, $subjectId)
    {
        return $query->whereHas('subjects', function ($query) use ($subjectId) {
            $query->where('subjects.id', $subjectId);
        });
    }

    /**
     * Scope a query to only include materials for a specific course.
     */
    public function scopeForCourse($query, $courseId)
    {
        return $query->whereHas('courses', function ($query) use ($courseId) {
            $query->where('courses.id', $courseId);
        });
    }
}