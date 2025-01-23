<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // default password for testing
            'remember_token' => Str::random(10),
            'username' => fake()->unique()->userName(),
        ];
    }

    /**
     * State for parent users.
     */
    public function parent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'role' => User::ROLE_PARENT,
            ];
        });
    }

    /**
     * State for teacher users.
     */
    public function teacher(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'role' => User::ROLE_TEACHER,
            ];
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
