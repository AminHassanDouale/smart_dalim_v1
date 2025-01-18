<?php

namespace App\Livewire;

use App\Models\LearningSession;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $selectedMonth;
    public ?string $selectedSubject = null;
    public Collection $subjects;
    public array $timetable = [];
    public array $attendanceStats = [];
    public array $monthlyAttendance = [];
    public User $teacher;

    public function mount(User $teacher): void
    {
        $this->teacher = $teacher;
        $this->selectedMonth = now()->format('Y-m');

        // Get subjects through learning sessions for the specific teacher
        $this->subjects = Subject::whereIn('id', function($query) {
            $query->select('subject_id')
                  ->from('learning_sessions')
                  ->where('teacher_id', $this->teacher->id)
                  ->distinct();
        })->get();

        if ($this->subjects->isNotEmpty()) {
            $this->selectedSubject = $this->subjects->first()->id;
        }

        $this->loadTimetableData();
    }

    public function updatedSelectedMonth(): void
    {
        $this->loadTimetableData();
    }

    public function updatedSelectedSubject(): void
    {
        $this->loadTimetableData();
    }

    protected function loadTimetableData(): void
    {
        $startDate = Carbon::createFromFormat('Y-m', $this->selectedMonth)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Load sessions for the month
        $sessions = LearningSession::query()
            ->where('teacher_id', $this->teacher->id)
            ->when($this->selectedSubject, function($query) {
                $query->where('subject_id', $this->selectedSubject);
            })
            ->whereBetween('start_time', [$startDate, $endDate])
            ->with(['subject', 'child'])
            ->orderBy('start_time')
            ->get()
            ->groupBy(function($session) {
                $date = Carbon::parse($session->start_time);
                return [
                    'date' => $date->format('Y-m-d'),
                    'formatted_date' => $date->format('l, F j, Y')
                ];
            });

        // Format timetable data
        $this->timetable = $sessions->map(function($daySessions) {
            return $daySessions->map(function($session) {
                return [
                    'id' => $session->id,
                    'start_time' => Carbon::parse($session->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($session->end_time)->format('H:i'),
                    'subject' => $session->subject->name,
                    'student' => $session->child->name,
                    'status' => $session->status,
                    'attended' => $session->attended,
                    'performance_score' => $session->performance_score
                ];
            });
        })->toArray();

        // Calculate attendance statistics
        $this->calculateAttendanceStats();
    }

    protected function calculateAttendanceStats(): void
    {
        $startDate = Carbon::createFromFormat('Y-m', $this->selectedMonth)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get attendance stats
        $stats = LearningSession::query()
            ->where('teacher_id', $this->teacher->id)
            ->when($this->selectedSubject, function($query) {
                $query->where('subject_id', $this->selectedSubject);
            })
            ->whereBetween('start_time', [$startDate, $endDate])
            ->select([
                'children_id',
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_sessions'),
                DB::raw('AVG(performance_score) as avg_performance')
            ])
            ->groupBy('children_id')
            ->with('child')
            ->get();

        // Format attendance stats
        $this->attendanceStats = $stats->map(function($stat) {
            return [
                'student_name' => $stat->child->name,
                'total_sessions' => $stat->total_sessions,
                'attended_sessions' => $stat->attended_sessions,
                'attendance_rate' => round(($stat->attended_sessions / $stat->total_sessions) * 100, 1),
                'avg_performance' => round($stat->avg_performance ?? 0, 1)
            ];
        })->toArray();

        // Calculate monthly totals
        $this->monthlyAttendance = [
            'total_sessions' => $stats->sum('total_sessions'),
            'attended_sessions' => $stats->sum('attended_sessions'),
            'attendance_rate' => $stats->sum('total_sessions') > 0
                ? round(($stats->sum('attended_sessions') / $stats->sum('total_sessions')) * 100, 1)
                : 0,
            'avg_performance' => round($stats->avg('avg_performance') ?? 0, 1)
        ];
    }
    public function redirectToClass(): void
    {
        $this->redirect("/teachers/" . auth()->id() . "/class");
    }

    #[Computed]
    public function months(): array
    {
        $months = collect();
        // Show 6 months back and 2 months forward
        for ($i = -6; $i <= 2; $i++) {
            $date = now()->addMonths($i)->startOfMonth();
            $months->push([
                'id' => $date->format('Y-m'),
                'name' => $date->format('F Y')
            ]);
        }
        return $months->toArray();
    }
}; ?>

<div>
    <x-header title="Timetable & Attendance for {{ $teacher->name }}" separator>
        <x-slot:actions>
            <x-select
            :options="$this->months"
            wire:model.live="selectedMonth"
            class="w-48"
        />
            <x-select
                :options="$subjects->map(fn($subject) => ['id' => $subject->id, 'name' => $subject->name])->toArray()"
                wire:model.live="selectedSubject"
                class="w-40 ml-2"
            />
            <x-button
            wire:click="redirectToClass"
            class="ml-2"
        >
            <x-icon name="o-academic-cap" class="w-5 h-5 mr-2" />
            View Class
        </x-button>
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 mt-6">
        <!-- Monthly Stats Card -->
        <x-card title="Monthly Overview">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="p-4 rounded-lg bg-blue-50">
                    <p class="text-sm text-blue-600">Total Sessions</p>
                    <p class="text-2xl font-semibold">{{ $monthlyAttendance['total_sessions'] ?? 0 }}</p>
                </div>

                <div class="p-4 rounded-lg bg-green-50">
                    <p class="text-sm text-green-600">Attended Sessions</p>
                    <p class="text-2xl font-semibold">{{ $monthlyAttendance['attended_sessions'] ?? 0 }}</p>
                </div>

                <div class="p-4 rounded-lg bg-purple-50">
                    <p class="text-sm text-purple-600">Attendance Rate</p>
                    <p class="text-2xl font-semibold">{{ $monthlyAttendance['attendance_rate'] ?? 0 }}%</p>
                </div>

                <div class="p-4 rounded-lg bg-yellow-50">
                    <p class="text-sm text-yellow-600">Avg Performance</p>
                    <p class="text-2xl font-semibold">{{ $monthlyAttendance['avg_performance'] ?? 0 }}%</p>
                </div>
            </div>
        </x-card>

        <!-- Timetable -->
        <x-card title="Monthly Timetable">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    @forelse($timetable as $date => $sessions)
                        <tbody class="divide-y divide-gray-200">
                            <tr class="bg-gray-50">
                                <th colspan="6" class="px-4 py-2 text-left">
                                    {{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }}                          </tr>
                            @foreach($sessions as $session)
                                <tr>
                                    <td class="px-4 py-2 text-sm">{{ $session['start_time'] }} - {{ $session['end_time'] }}</td>
                                    <td class="px-4 py-2">{{ $session['subject'] }}</td>
                                    <td class="px-4 py-2">{{ $session['student'] }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{
                                            $session['status'] === 'scheduled' ? 'bg-blue-100 text-blue-800' :
                                            ($session['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                            'bg-gray-100 text-gray-800')
                                        }}">
                                            {{ ucfirst($session['status']) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{
                                            $session['attended'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                        }}">
                                            {{ $session['attended'] ? 'Present' : 'Absent' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        @if($session['performance_score'])
                                            <span class="px-2 py-1 text-xs font-medium text-yellow-800 bg-yellow-100 rounded-full">
                                                Score: {{ $session['performance_score'] }}%
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                No sessions scheduled for this month
                            </td>
                        </tr>
                    @endforelse
                </table>
            </div>
        </x-card>

        <!-- Student Attendance Stats -->
        <x-card title="Student Attendance Summary">
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left">Student</th>
                            <th class="px-4 py-2 text-center">Total Sessions</th>
                            <th class="px-4 py-2 text-center">Attended</th>
                            <th class="px-4 py-2 text-center">Attendance Rate</th>
                            <th class="px-4 py-2 text-center">Avg Performance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($attendanceStats as $stat)
                            <tr>
                                <td class="px-4 py-2">{{ $stat['student_name'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $stat['total_sessions'] }}</td>
                                <td class="px-4 py-2 text-center">{{ $stat['attended_sessions'] }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="px-2 py-1 text-xs font-medium {{
                                        $stat['attendance_rate'] >= 80 ? 'bg-green-100 text-green-800' :
                                        ($stat['attendance_rate'] >= 60 ? 'bg-yellow-100 text-yellow-800' :
                                        'bg-red-100 text-red-800')
                                    }} rounded-full">
                                        {{ $stat['attendance_rate'] }}%
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-center">{{ $stat['avg_performance'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    No attendance data available
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</div>
