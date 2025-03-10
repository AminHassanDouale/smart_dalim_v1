<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\TeacherProfile;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all teacher profiles and subjects to reference
        $teacherProfiles = TeacherProfile::all();
        $subjects = Subject::all();

        if ($teacherProfiles->isEmpty()) {
            $this->command->error('No teacher profiles found. Please seed teacher profiles first.');
            return;
        }

        if ($subjects->isEmpty()) {
            $this->command->error('No subjects found. Please seed subjects first.');
            return;
        }

        $courses = [
            [
                'name' => 'Basic Mathematics',
                'description' => 'Fundamental mathematics course covering arithmetic, basic algebra, and geometry.',
                'level' => 'Beginner',
                'duration' => '3 months',
                'price' => 299.99,
                'status' => 'active',
                'curriculum' => [
                    'Week 1-2: Arithmetic Operations',
                    'Week 3-4: Introduction to Algebra',
                    'Week 5-6: Basic Geometry',
                    'Week 7-8: Problem Solving',
                    'Week 9-12: Practice and Applications'
                ],
                'prerequisites' => [
                    'Basic number knowledge',
                    'Understanding of basic mathematical operations'
                ],
                'learning_outcomes' => [
                    'Master basic arithmetic operations',
                    'Solve simple algebraic equations',
                    'Understand geometric principles',
                    'Apply mathematics to real-world problems'
                ],
                'max_students' => 20
            ],
            [
                'name' => 'Advanced English Grammar',
                'description' => 'Comprehensive English grammar course for intermediate to advanced learners.',
                'level' => 'Advanced',
                'duration' => '4 months',
                'price' => 399.99,
                'status' => 'active',
                'curriculum' => [
                    'Week 1-3: Advanced Verb Tenses',
                    'Week 4-6: Complex Sentence Structures',
                    'Week 7-9: Academic Writing',
                    'Week 10-12: Professional Communication',
                    'Week 13-16: Practice and Applications'
                ],
                'prerequisites' => [
                    'Intermediate English proficiency',
                    'Basic grammar understanding'
                ],
                'learning_outcomes' => [
                    'Master advanced grammar concepts',
                    'Write complex sentences correctly',
                    'Improve academic writing skills',
                    'Enhance professional communication'
                ],
                'max_students' => 15
            ],
            [
                'name' => 'Introduction to Physics',
                'description' => 'Basic physics concepts and principles for beginners.',
                'level' => 'Intermediate',
                'duration' => '6 months',
                'price' => 499.99,
                'status' => 'draft',
                'curriculum' => [
                    'Month 1: Mechanics',
                    'Month 2: Thermodynamics',
                    'Month 3: Waves and Sound',
                    'Month 4: Light and Optics',
                    'Month 5: Electricity and Magnetism',
                    'Month 6: Modern Physics'
                ],
                'prerequisites' => [
                    'Basic mathematics knowledge',
                    'Understanding of scientific notation',
                    'Algebra fundamentals'
                ],
                'learning_outcomes' => [
                    'Understand basic physics principles',
                    'Solve physics problems',
                    'Conduct basic experiments',
                    'Apply physics concepts to real world'
                ],
                'max_students' => 25
            ]
        ];

        foreach ($courses as $courseData) {
            Course::create([
                'name' => $courseData['name'],
                'slug' => Str::slug($courseData['name']),
                'description' => $courseData['description'],
                'level' => $courseData['level'],
                'duration' => $courseData['duration'],
                'price' => $courseData['price'],
                'status' => $courseData['status'],
                'teacher_profile_id' => $teacherProfiles->random()->id,
                'subject_id' => $subjects->random()->id,
                'curriculum' => $courseData['curriculum'],
                'prerequisites' => $courseData['prerequisites'],
                'learning_outcomes' => $courseData['learning_outcomes'],
                'max_students' => $courseData['max_students'],
                'start_date' => now()->addDays(rand(5, 30)),
                'end_date' => now()->addMonths(rand(3, 6))
            ]);
        }

        $this->command->info('Courses seeded successfully!');
    }
}
