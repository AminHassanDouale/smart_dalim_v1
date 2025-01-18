<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The valid roles as defined in the database ENUM
     */
    protected array $validRoles = ['parent', 'teacher'];

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'username' => $this->generateUniqueUsername($name),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => fake()->randomElement($this->validRoles),
        ];
    }

    /**
     * Generate a unique username based on the user's name.
     */
    protected function generateUniqueUsername(string $name): string
    {
        $baseUsername = Str::lower(str_replace(' ', '.', $name));
        $baseUsername = preg_replace('/[^a-z0-9.]/', '', $baseUsername);
        return $baseUsername . random_int(100, 999);
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

    /**
     * Configure the model as a parent user.
     */
    public function parent(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'parent',
        ]);
    }

    /**
     * Configure the model as a teacher user.
     */
    public function teacher(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'teacher',
        ]);
    }
}
