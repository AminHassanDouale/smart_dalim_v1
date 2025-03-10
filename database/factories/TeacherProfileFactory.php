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
            'whatsapp' => $this->faker->phoneNumber(),
            'phone' => $this->faker->phoneNumber(),
            'fix_number' => $this->faker->optional(0.5)->phoneNumber(),
            'photo' => $this->faker->optional(0.7)->imageUrl(),
            'date_of_birth' => $this->faker->dateTimeBetween('-60 years', '-20 years'),
            'place_of_birth' => $this->faker->city(),
            'has_completed_profile' => $this->faker->boolean(90),
            'status' => $this->faker->randomElement([
                TeacherProfile::STATUS_SUBMITTED,
                TeacherProfile::STATUS_CHECKING,
                TeacherProfile::STATUS_VERIFIED,
            ]),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TeacherProfile::STATUS_VERIFIED,
            'has_completed_profile' => true,
        ]);
    }
}