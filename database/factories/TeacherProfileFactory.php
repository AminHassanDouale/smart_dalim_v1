<?php

namespace Database\Factories;

use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherProfileFactory extends Factory
{
    protected $model = TeacherProfile::class;

    public function definition(): array
    {
        return [
            'whatsapp' => fake()->phoneNumber(),
            'phone' => fake()->phoneNumber(),
            'fix_number' => fake()->phoneNumber(),
            'photo' => 'default-teacher.jpg',
            'date_of_birth' => fake()->date(),
            'place_of_birth' => fake()->city(),
            'has_completed_profile' => true,
            'status' => TeacherProfile::STATUS_VERIFIED,
        ];
    }
}
