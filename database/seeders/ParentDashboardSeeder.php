<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ParentProfile;
use App\Models\Children;
use App\Models\Subject;
use App\Models\LearningSession;
use Illuminate\Database\Seeder;

class ParentDashboardSeeder extends Seeder
{
    public function run(): void
    {
        // First, clear existing subjects if needed
        // Subject::truncate();

        // Create base subjects with specific names
        $subjects = collect([
            'Mathematics',
            'English',
            'Science',
            'History',
            'Geography',
            'Art',
            'Music',
            'Physical Education'
        ])->map(function ($name) {
            return Subject::factory()->create([
                'name' => $name,
                'slug' => str($name)->slug()
            ]);
        });

        // Rest of the seeder...
        $teachers = User::factory(10)
            ->teacher()
            ->create()
            ->each(function ($teacher) use ($subjects) {
                // Attach random subjects to each teacher
                $teacher->subjects()->attach(
                    $subjects->random(3)->pluck('id')->toArray()
                );
            });

        // Continue with parent creation...
        User::factory(50)
            ->parent()
            ->has(
                ParentProfile::factory()
                    ->has(
                        Children::factory(fake()->numberBetween(1, 3))
                            ->hasAttached($subjects->random(3))
                            ->has(
                                LearningSession::factory(20)
                                    ->state(function (array $attributes, Children $child) use ($subjects, $teachers) {
                                        return [
                                            'subject_id' => $subjects->random()->id,
                                            'teacher_id' => $teachers->random()->id,
                                            'created_at' => now()->subDays(rand(1, 180))
                                        ];
                                    })
                            )
                    )
            )
            ->create();

        // Create incomplete profiles
        User::factory(5)
            ->parent()
            ->has(ParentProfile::factory()->incomplete())
            ->create();
    }
}
