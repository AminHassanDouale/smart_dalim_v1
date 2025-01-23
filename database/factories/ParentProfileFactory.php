<?php

namespace Database\Factories;

use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParentProfileFactory extends Factory
{
    protected $model = ParentProfile::class;

    public function definition(): array
    {
        return [
            'phone_number' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'number_of_children' => fake()->numberBetween(1, 4),
            'additional_information' => fake()->paragraph(),
            'emergency_contacts' => [
                [
                    'name' => fake()->name(),
                    'relation' => 'Spouse',
                    'phone' => fake()->phoneNumber(),
                ],
            ],
            'has_completed_profile' => true,
        ];
    }
}
