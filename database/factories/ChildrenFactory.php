<?php

namespace Database\Factories;

use App\Models\ParentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChildrenFactory extends Factory
{
    protected $availableDays = [
        'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'
    ];

    protected function generateTimeSlot(): array
    {
        $startHour = fake()->numberBetween(8, 16);
        $startTime = sprintf("%02d:00", $startHour);
        $endTime = sprintf("%02d:00", $startHour + 2);
        return [$startTime, $endTime];
    }

    public function definition(): array
    {
        return [
            'parent_profile_id' => ParentProfile::factory(),
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'name' => fake()->name(),
            'age' => fake()->numberBetween(5, 17),
            'available_times' => collect($this->availableDays)
                ->random(3)
                ->map(fn($day) => [
                    'day' => $day,
                    'time' => $this->generateTimeSlot()
                ])
                ->values()
                ->all(),
        ];
    }

    public function withCustomSchedule(array $schedule): static
    {
        return $this->state(fn (array $attributes) => [
            'available_times' => $schedule
        ]);
    }

    public function withSpecificAge(int $age): static
    {
        return $this->state(fn (array $attributes) => [
            'age' => $age
        ]);
    }
}

