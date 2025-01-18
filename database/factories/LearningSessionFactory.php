<?php

namespace Database\Factories;

use App\Models\Children;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class LearningSessionFactory extends Factory
{
    public function definition(): array
    {
        // Set date range from December 1, 2024 to June 1, 2025
        $startTime = fake()->dateTimeBetween('2024-12-01', '2025-06-01');
        $endTime = Carbon::parse($startTime)->addHour();

        return [
            'children_id' => Children::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => function (array $attributes) {
                return Children::find($attributes['children_id'])->teacher_id;
            },
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => fake()->randomElement(['scheduled', 'completed', 'cancelled']),
            'attended' => function (array $attributes) {
                // Only completed sessions can be attended
                return $attributes['status'] === 'completed' ?
                    fake()->boolean(80) : false;
            },
            'performance_score' => function (array $attributes) {
                // Only attended sessions have performance scores
                return $attributes['status'] === 'completed' && $attributes['attended'] ?
                    fake()->numberBetween(60, 100) : null;
            },
            'notes' => fake()->optional(0.7)->sentence()
        ];
    }

    // State method for past sessions (December 2024 - Now)
    public function past(): static
    {
        return $this->state(function () {
            $startTime = fake()->dateTimeBetween('2024-12-01', 'now');
            return [
                'start_time' => $startTime,
                'end_time' => Carbon::parse($startTime)->addHour(),
                'status' => 'completed'
            ];
        });
    }

    // State method for future sessions (Now - June 2025)
    public function upcoming(): static
    {
        return $this->state(function () {
            $startTime = fake()->dateTimeBetween('now', '2025-06-01');
            return [
                'start_time' => $startTime,
                'end_time' => Carbon::parse($startTime)->addHour(),
                'status' => 'scheduled'
            ];
        });
    }
}
