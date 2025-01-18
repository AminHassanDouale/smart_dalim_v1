<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Children;
use App\Models\Subject;
use App\Models\User;
use App\Models\LearningSession;
use Carbon\Carbon;

class LearningSessionSeeder extends Seeder
{
    public function run()
    {
        // Get all children
        $children = Children::with('subjects', 'teacher')->get();

        foreach ($children as $child) {
            // Get child's subjects
            $subjects = $child->subjects;
            $teacher = $child->teacher;

            // Generate 10 sample learning sessions
            for ($i = 0; $i < 10; $i++) {
                // Randomly select a subject for this session
                $subject = $subjects->random();

                // Create learning sessions for next 2 months
                $startTime = now()
                    ->addDays(rand(0, 60))
                    ->setHour(rand(8, 20))
                    ->setMinute(rand(0, 1) * 30);

                LearningSession::create([
                    'children_id' => $child->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher->id,
                    'start_time' => $startTime,
                    'end_time' => $startTime->copy()->addHour(),
                    'status' => $startTime->isPast() ? 'completed' : 'scheduled',
                    'attended' => $startTime->isPast(),
                    'performance_score' => $startTime->isPast() ? rand(60, 100) : null,
                    'notes' => $startTime->isPast() ? 'Session completed' : null
                ]);
            }
        }
    }
}
