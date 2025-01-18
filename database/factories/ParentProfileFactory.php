<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class ParentProfileFactory extends Factory
{
    protected $relationships = [
        'Spouse', 'Grandparent', 'Sibling', 'Aunt', 'Uncle', 'Friend', 'Neighbor'
    ];

    protected function generateEmergencyContact(): array
    {
        return [
            'name' => fake()->name(),
            'relationship' => Arr::random($this->relationships),
            'phone' => fake()->e164PhoneNumber(),
            'email' => fake()->optional(0.7)->safeEmail(),
            'is_primary' => fake()->boolean(20)
        ];
    }

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone_number' => fake()->e164PhoneNumber(),
            'address' => fake()->address(),
            'number_of_children' => fake()->numberBetween(1, 4),
            'additional_information' => fake()->paragraph(),
            'profile_photo_path' => null,
            'emergency_contacts' => json_encode([
                $this->generateEmergencyContact(),
                $this->generateEmergencyContact()
            ]),
            'has_completed_profile' => true,
        ];
    }

    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_completed_profile' => false,
            'additional_information' => null
        ]);
    }

    public function withEmergencyContacts(int $count): static
    {
        return $this->state(function () use ($count) {
            $contacts = array_map(
                fn() => $this->generateEmergencyContact(),
                range(1, $count)
            );
            return ['emergency_contacts' => json_encode($contacts)];
        });
    }
}
