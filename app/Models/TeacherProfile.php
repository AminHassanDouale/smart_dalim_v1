<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TeacherProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'whatsapp',
        'phone',
        'fix_number',
        'photo',
        'date_of_birth',
        'place_of_birth',
        'has_completed_profile',
        'status'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'has_completed_profile' => 'boolean',
    ];

    const STATUS_SUBMITTED = 'submitted';
    const STATUS_CHECKING = 'checking';
    const STATUS_VERIFIED = 'verified';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->morphMany(File::class, 'model');
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_CHECKING,
            self::STATUS_VERIFIED
        ];
    }
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_teacher', 'teacher_profile_id', 'subject_id');
    }
}
