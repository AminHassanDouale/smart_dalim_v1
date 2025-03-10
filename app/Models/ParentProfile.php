<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ParentProfile extends Model
{
    use HasFactory;  // This is crucial for factory functionality

    protected $fillable = [
        'user_id',
        'phone_number',
        'address',
        'number_of_children',
        'additional_information',
        'profile_photo_path',
        'emergency_contacts',
        'has_completed_profile',
    ];

    protected $casts = [
        'emergency_contacts' => 'array',
        'has_completed_profile' => 'boolean',
        'number_of_children' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function children()
{
    return $this->hasMany(Children::class);
}
}