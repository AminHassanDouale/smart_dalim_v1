<?php

namespace Database\Factories;

use App\Models\Children;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class LearningSessionFactory extends Factory
{
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('2024-01-01', '2025-05-05');
        $endTime = Carbon::parse($startTime)->addHours(2);

        return [
            'children_id' => Children::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => function (array $attributes) {
                $child = Children::find($attributes['children_id']);
                return User::factory()->teacher()->create()->id;
            },
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => fake()->randomElement(['scheduled', 'completed', 'cancelled']),
            'attended' => function (array $attributes) {
                return $attributes['status'] === 'completed' ?
                    fake()->boolean(80) : false;
            },
            'performance_score' => function (array $attributes) {
                return $attributes['status'] === 'completed' && $attributes['attended'] ?
                    fake()->randomFloat(2, 60, 100) : null;
            },
            'notes' => fake()->optional(0.7)->sentence()
        ];
    }

    public function past(): static
    {
        return $this->state(function () {
            $startTime = fake()->dateTimeBetween('2024-01-01', 'now');
            return [
                'start_time' => $startTime,
                'end_time' => Carbon::parse($startTime)->addHours(2),
                'status' => 'completed'
            ];
        });
    }

    public function upcoming(): static
    {
        return $this->state(function () {
            $startTime = fake()->dateTimeBetween('now', '2025-05-05');
            return [
                'start_time' => $startTime,
                'end_time' => Carbon::parse($startTime)->addHours(2),
                'status' => 'scheduled'
            ];
        });
    }
}
