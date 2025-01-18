<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Subject;

class SubjectFactory extends Factory
{
    /**
     * Track used subjects to prevent duplicates
     */
    private static array $usedSubjects = [];

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $subjects = [
            'Mathematics', 'Physics', 'Chemistry', 'Biology',
            'English', 'History', 'Geography', 'Computer Science'
        ];

        // Filter out already used subjects
        $availableSubjects = array_diff($subjects, self::$usedSubjects);

        // If all subjects are used, reset the tracking array
        if (empty($availableSubjects)) {
            self::$usedSubjects = [];
            $availableSubjects = $subjects;
        }

        // Get a random subject from remaining ones
        $name = fake()->randomElement($availableSubjects);

        // Track this subject as used
        self::$usedSubjects[] = $name;

        // Generate base slug
        $baseSlug = Str::slug($name);

        // Check if slug exists and append number if needed
        $slug = $baseSlug;
        $counter = 1;

        while (Subject::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => fake()->paragraph(),
        ];
    }
}
