<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\ClientProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientProfile>
 */
class ClientProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = collect(['consulting', 'development', 'training', 'support'])
            ->random(rand(1, 4))
            ->values()
            ->toArray();

        return [
            'company_name' => fake()->company(),
            'whatsapp' => fake()->phoneNumber(),
            'phone' => fake()->phoneNumber(),
            'website' => fake()->url(),
            'position' => fake()->jobTitle(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'industry' => fake()->randomElement(['Technology', 'Education', 'Healthcare', 'Finance', 'Manufacturing']),
            'company_size' => fake()->randomElement(['1-10', '11-50', '51-200', '201-500', '501+']),
            'preferred_services' => $services,
            'preferred_contact_method' => fake()->randomElement(['email', 'phone', 'whatsapp']),
            'notes' => fake()->paragraph(),
            'has_completed_profile' => fake()->boolean(80),
            'status' => fake()->randomElement([
                ClientProfile::STATUS_PENDING,
                ClientProfile::STATUS_APPROVED,
                ClientProfile::STATUS_REJECTED,
            ]),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure()
    {
        return $this->afterCreating(function (ClientProfile $clientProfile) {
            // Additional setup after creation if needed
        });
    }

    /**
     * Indicate the profile is pending.
     */
    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ClientProfile::STATUS_PENDING,
            ];
        });
    }

    /**
     * Indicate the profile is approved.
     */
    public function approved()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ClientProfile::STATUS_APPROVED,
                'has_completed_profile' => true,
            ];
        });
    }

    /**
     * Indicate the profile is rejected.
     */
    public function rejected()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ClientProfile::STATUS_REJECTED,
                'has_completed_profile' => true,
            ];
        });
    }
}
