<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 week');
        $end = (clone $start)->modify('+6 hours');

        return [
            'day_of_week' => fake()->randomElement(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']),
            'start_time' => $start,
            'end_time' => $end,
            'room_number' => 'Room-' . fake()->numberBetween(100, 999),
            'student_id' => null,
        ];
    }
}
