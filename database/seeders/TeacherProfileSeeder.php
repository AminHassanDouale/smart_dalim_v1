<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use App\Models\TeacherProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;


class TeacherProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if subjects exist
        $subjects = Subject::all();

        if ($subjects->isEmpty()) {
            $this->command->error('No subjects found. Please seed subjects first.');
            return;
        }

        $teachers = [
            [
                'name' => 'John Smith',
                'email' => 'john.smith@example.com',
                'username' => 'johnsmith',
                'profile' => [
                    'whatsapp' => '+1234567890',
                    'phone' => '+1234567890',
                    'fix_number' => '+1234567890',
                    'date_of_birth' => '1985-06-15',
                    'place_of_birth' => 'New York',
                    'photo' => 'default-teacher.jpg'
                ]
            ],
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@example.com',
                'username' => 'sarahjohnson',
                'profile' => [
                    'whatsapp' => '+1234567891',
                    'phone' => '+1234567891',
                    'fix_number' => '+1234567891',
                    'date_of_birth' => '1988-03-22',
                    'place_of_birth' => 'Los Angeles',
                    'photo' => 'default-teacher.jpg'
                ]
            ],
            [
                'name' => 'Michael Brown',
                'email' => 'michael.brown@example.com',
                'username' => 'michaelbrown',
                'profile' => [
                    'whatsapp' => '+1234567892',
                    'phone' => '+1234567892',
                    'fix_number' => '+1234567892',
                    'date_of_birth' => '1982-09-10',
                    'place_of_birth' => 'Chicago',
                    'photo' => 'default-teacher.jpg'
                ]
            ],
            [
                'name' => 'Emily Davis',
                'email' => 'emily.davis@example.com',
                'username' => 'emilydavis',
                'profile' => [
                    'whatsapp' => '+1234567893',
                    'phone' => '+1234567893',
                    'fix_number' => '+1234567893',
                    'date_of_birth' => '1990-12-05',
                    'place_of_birth' => 'Boston',
                    'photo' => 'default-teacher.jpg'
                ]
            ],
            [
                'name' => 'David Wilson',
                'email' => 'david.wilson@example.com',
                'username' => 'davidwilson',
                'profile' => [
                    'whatsapp' => '+1234567894',
                    'phone' => '+1234567894',
                    'fix_number' => '+1234567894',
                    'date_of_birth' => '1987-07-30',
                    'place_of_birth' => 'San Francisco',
                    'photo' => 'default-teacher.jpg'
                ]
            ]
        ];

        foreach ($teachers as $teacherData) {
            // Create user
            $user = User::create([
                'name' => $teacherData['name'],
                'email' => $teacherData['email'],
                'username' => $teacherData['username'],
                'password' => Hash::make('password'), // Default password
                'role' => User::ROLE_TEACHER,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]);

            // Create teacher profile
            TeacherProfile::create([
                'user_id' => $user->id,
                'whatsapp' => $teacherData['profile']['whatsapp'],
                'phone' => $teacherData['profile']['phone'],
                'fix_number' => $teacherData['profile']['fix_number'],
                'photo' => $teacherData['profile']['photo'],
                'date_of_birth' => $teacherData['profile']['date_of_birth'],
                'place_of_birth' => $teacherData['profile']['place_of_birth']
            ]);

            // Assign random subjects to teacher (2-4 subjects per teacher)
            $randomSubjects = $subjects->random(rand(2, 4));
            $user->subjects()->attach($randomSubjects->pluck('id'));
        }

        $this->command->info('Teachers seeded successfully!');
    }
}
