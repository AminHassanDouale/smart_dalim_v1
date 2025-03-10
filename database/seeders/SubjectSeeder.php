<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            [
                'name' => 'Mathematics',
                'description' => 'Study of numbers, quantities, and shapes',
            ],
            [
                'name' => 'Physics',
                'description' => 'Science of matter, energy, and their interactions',
            ],
            [
                'name' => 'Chemistry',
                'description' => 'Study of substances, their properties, and reactions',
            ],
            [
                'name' => 'Biology',
                'description' => 'Study of living organisms and their vital processes',
            ],
            [
                'name' => 'English Language',
                'description' => 'Study of English grammar, vocabulary, and communication',
            ],
            [
                'name' => 'History',
                'description' => 'Study of past events and human civilization',
            ],
            [
                'name' => 'Geography',
                'description' => 'Study of Earth and human interaction with environment',
            ],
            [
                'name' => 'Computer Science',
                'description' => 'Study of computers and computational systems',
            ],
            [
                'name' => 'Art',
                'description' => 'Study of various forms of creative expression',
            ],
            [
                'name' => 'Music',
                'description' => 'Study of musical theory and performance',
            ]
        ];

        foreach ($subjects as $subject) {
            Subject::create([
                'name' => $subject['name'],
                'slug' => Str::slug($subject['name']),
                'description' => $subject['description']
            ]);
        }

        $this->command->info('Subjects seeded successfully!');
    }
}
