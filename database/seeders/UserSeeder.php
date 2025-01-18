<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ParentProfile;
use App\Models\TeacherProfile;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
   /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create regular parent users
        for ($i = 1; $i <= 5; $i++) {
            $user = User::create([
                'name' => fake()->name(),
                'email' => "parent{$i}@example.com",
                'password' => Hash::make('password'),
                'role' => User::ROLE_PARENT,
                'username' => "parent{$i}",
                'email_verified_at' => now(),
            ]);

            ParentProfile::create([
                'user_id' => $user->id,
                'phone_number' => fake()->phoneNumber(),
                'address' => fake()->address(),
                'number_of_children' => fake()->numberBetween(1, 4),
                'additional_information' => fake()->paragraph(),
                'emergency_contacts' => [
                    [
                        'name' => fake()->name(),
                        'relationship' => fake()->randomElement(['Spouse', 'Grandparent', 'Sibling']),
                        'phone' => fake()->phoneNumber(),
                    ]
                ],
                'has_completed_profile' => true,
            ]);
        }

        // Create demo parent user
        $demoParent = User::create([
            'name' => 'Demo Parent',
            'email' => 'demoparent@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_PARENT,
            'username' => 'demoparent',
            'email_verified_at' => now(),
        ]);

        ParentProfile::create([
            'user_id' => $demoParent->id,
            'phone_number' => '1234567890',
            'address' => '123 Demo Street',
            'number_of_children' => 2,
            'additional_information' => 'Demo parent account for testing purposes',
            'emergency_contacts' => [
                [
                    'name' => 'Emergency Contact 1',
                    'relationship' => 'Spouse',
                    'phone' => '1234567890'
                ]
            ],
            'has_completed_profile' => true,
        ]);
    }
}
