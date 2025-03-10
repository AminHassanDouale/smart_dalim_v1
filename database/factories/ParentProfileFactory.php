<?php

namespace Database\Factories;

use App\Models\ParentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParentProfileFactory extends Factory
{
    protected $model = ParentProfile::class;

    public function definition(): array
    {
        return [
            'phone_number' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'number_of_children' => $this->faker->numberBetween(1, 5),
            'additional_information' => $this->faker->optional(0.7)->paragraph(),
            'profile_photo_path' => $this->faker->optional(0.3)->imageUrl(),
            'emergency_contacts' => [
                [
                    'name' => $this->faker->name(),
                    'relationship' => $this->faker->randomElement(['Spouse', 'Grandparent', 'Sibling', 'Uncle', 'Aunt']),
                    'phone' => $this->faker->phoneNumber(),
                ],
                [
                    'name' => $this->faker->name(),
                    'relationship' => $this->faker->randomElement(['Spouse', 'Grandparent', 'Sibling', 'Uncle', 'Aunt']),
                    'phone' => $this->faker->phoneNumber(),
                ],
            ],
            'has_completed_profile' => $this->faker->boolean(80),
        ];
    }
}