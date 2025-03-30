<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ParentProfile;
use App\Models\TeacherProfile;
use App\Models\Subject;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create regular parent users

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'), // Better to use an environment variable in production
            'role' => User::ROLE_ADMIN,
            'username' => 'admin', // Add the username field
            'email_verified_at' => now(),
        ]);
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

        // Create regular teacher users
        for ($i = 1; $i <= 5; $i++) {
            $user = User::create([
                'name' => fake()->name(),
                'email' => "teacher{$i}@example.com",
                'password' => Hash::make('password'),
                'role' => User::ROLE_TEACHER,
                'username' => "teacher{$i}",
                'email_verified_at' => now(), // This ensures the account is verified
            ]);

            // Create teacher profile with empty fields
            // This is based on your request to make all attributes empty and take data from teacher data
            $teacherProfile = TeacherProfile::create([
                'user_id' => $user->id,
                'whatsapp' => '', // Empty as requested
                'phone' => '', // Empty as requested
                'place_of_birth' => '', // Empty as requested
                'has_completed_profile' => true,
                'status' => 'submitted' // Based on your error message, this seems to be the status format
            ]);

            // Get random subject IDs (assuming you have subjects already seeded)
            $subjectIds = Subject::inRandomOrder()->limit(fake()->numberBetween(1, 3))->pluck('id');

            // Attach subjects to teacher if there are any
            if ($subjectIds->count() > 0) {
                $teacherProfile->subjects()->attach($subjectIds);
            }
        }

        // Create a demo teacher account
        $demoTeacher = User::create([
            'name' => 'Demo Teacher',
            'email' => 'demoteacher@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_TEACHER,
            'username' => 'demoteacher',
            'email_verified_at' => now(),
        ]);

        $demoTeacherProfile = TeacherProfile::create([
            'user_id' => $demoTeacher->id,
            'whatsapp' => '', // Empty as requested
            'phone' => '', // Empty as requested
            'place_of_birth' => '', // Empty as requested
            'has_completed_profile' => true,
            'status' => 'approved' // This demo teacher is fully approved
        ]);

        // Attach subjects to demo teacher
        $demoSubjectIds = Subject::inRandomOrder()->limit(3)->pluck('id');
        if ($demoSubjectIds->count() > 0) {
            $demoTeacherProfile->subjects()->attach($demoSubjectIds);
        }
    }
}