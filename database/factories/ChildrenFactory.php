<?php

namespace Database\Factories;

use App\Models\Children;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChildrenFactory extends Factory
{
    protected $model = Children::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'gender' => fake()->randomElement(['male', 'female']),
            'school_name' => fake()->company(),
            'grade' => fake()->randomElement(['1st', '2nd', '3rd', '4th', '5th', '6th']),
            'available_times' => [
                ['day' => 'Monday', 'time' => ['12:00', '14:00']],
                ['day' => 'Wednesday', 'time' => ['12:00', '14:00']],
                ['day' => 'Friday', 'time' => ['12:00', '14:00']]
            ],
            'last_session_at' => fake()->dateTimeBetween('-6 months', '+1 month'),
        ];
    }
}
