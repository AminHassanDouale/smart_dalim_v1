<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition()
    {
        $startDate = fake()->dateTimeBetween('2024-01-01', '2024-12-31');
        $endDate = fake()->dateTimeBetween($startDate, '2025-05-05');

        return [
            'name' => fake()->unique()->sentence(3),
            'slug' => fn ($attr) => Str::slug($attr['name']),
            'description' => fake()->paragraph(),
            'level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
            'duration' => fake()->numberBetween(4, 16) . ' weeks',
            'price' => fake()->numberBetween(100, 1000),
            'status' => 'active',
            'curriculum' => [
                'Week 1' => fake()->sentences(3),
                'Week 2' => fake()->sentences(3),
            ],
            'prerequisites' => [fake()->sentence(), fake()->sentence()],
            'learning_outcomes' => [fake()->sentence(), fake()->sentence()],
            'max_students' => fake()->numberBetween(5, 20),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Course $course) {
            // Create 2-3 schedules per course
            $daysOfWeek = ['Monday', 'Wednesday', 'Friday'];
            foreach ($daysOfWeek as $day) {
                Schedule::factory()->create([
                    'course_id' => $course->id,
                    'day_of_week' => $day,
                    'start_time' => fake()->dateTimeBetween('09:00', '15:00'),
                    'end_time' => fake()->dateTimeBetween('16:00', '20:00'),
                    'room_number' => fake()->bothify('Room-###'),
                ]);
            }
        });
    }
}
