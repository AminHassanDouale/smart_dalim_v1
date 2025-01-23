<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Subject;
use App\Models\Course;
use App\Models\LearningSession;
use App\Models\Children;
use App\Models\ParentProfile;
use App\Models\TeacherProfile;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Subjects
        $subjects = collect(['Mathematics', 'Physics', 'Chemistry', 'Biology', 'English'])
            ->map(fn ($name) => Subject::create([
                'name' => $name,
                'slug' => str($name)->slug(),
                'description' => fake()->sentence()
            ]));

        // Create Teachers
        $teachers = collect();
        for ($i = 0; $i < 10; $i++) {
            $teacher = User::factory()->teacher()->create();
            TeacherProfile::factory()->create(['user_id' => $teacher->id]);

            $teacher->refresh();
            $teacher->teacherProfile->subjects()->attach(
                $subjects->random(rand(2, 4))->pluck('id')->toArray()
            );

            $teachers->push($teacher);
        }

        // Create Parents with Children
        for ($i = 0; $i < 20; $i++) {
            $parent = User::factory()->parent()->create();

            $parentProfile = ParentProfile::factory()->create([
                'user_id' => $parent->id
            ]);

            Children::factory(rand(1, 3))->create([
                'parent_profile_id' => $parentProfile->id
            ]);
        }

        // Create Courses
        $teachers->each(function ($teacher) {
            $teacher->teacherProfile->subjects->each(function ($subject) use ($teacher) {
                Course::factory()->create([
                    'teacher_profile_id' => $teacher->teacherProfile->id,
                    'subject_id' => $subject->id
                ]);
            });
        });

        // Create Learning Sessions
        Children::all()->each(function ($child) use ($teachers) {
            $selectedTeachers = $teachers->random(3);
            $selectedTeachers->each(function ($teacher) use ($child) {
                $teacher->teacherProfile->subjects->each(function ($subject) use ($teacher, $child) {
                    LearningSession::factory(rand(3, 5))->past()->create([
                        'teacher_id' => $teacher->id,
                        'children_id' => $child->id,
                        'subject_id' => $subject->id
                    ]);

                    LearningSession::factory(rand(2, 4))->upcoming()->create([
                        'teacher_id' => $teacher->id,
                        'children_id' => $child->id,
                        'subject_id' => $subject->id
                    ]);
                });
            });
        });
    }
}
