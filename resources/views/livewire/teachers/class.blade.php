<?php

namespace App\Livewire;

use App\Models\LearningSession;
use App\Models\User;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public User $teacher;
    public Collection $students;
    public Collection $subjects;
    public array $studentStats = [];
    public array $attendanceChart = [];
    public array $subjectScores = [];
    public array $subjectChanges = [];
    public ?string $selectedPeriod = 'week';
    public ?string $sortBy = 'attendance';
    public ?string $scoreSort = 'negative';
    public ?string $changeSort = 'high';
    public ?string $selectedSubject = null;
    public ?string $studentSort = 'score_high';

    public function mount(User $teacher): void
    {
        $this->teacher = $teacher;
        $this->subjects = Subject::whereIn('id', function($query) {
            $query->select('subject_id')
                  ->from('learning_sessions')
                  ->where('teacher_id', $this->teacher->id)
                  ->distinct();
        })->get();

        $this->loadAllData();
    }

    public function loadAllData(): void
    {
        $this->loadClassData();
        $this->loadAnalyticsData();
    }

    public function loadClassData(): void
    {
        $this->loadStudentStats();
        $this->loadAttendanceChart();
    }

    protected function loadAnalyticsData(): void
    {
        $this->loadSubjectScores();
        $this->loadSubjectChanges();
    }

    protected function loadStudentStats(): void
    {
        $startDate = match($this->selectedPeriod) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subMonths(3),
            default => now()->subWeek()
        };

        $stats = LearningSession::query()
            ->where('teacher_id', $this->teacher->id)
            ->when($this->selectedSubject, function($query) {
                if ($this->selectedSubject !== 'all') {
                    $query->where('subject_id', $this->selectedSubject);
                }
            })
            ->where('start_time', '>=', $startDate)
            ->select([
                'children_id',
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_sessions'),
                DB::raw('AVG(performance_score) as avg_score')
            ])
            ->groupBy('children_id')
            ->with('children')
            ->get();

        $this->studentStats = $stats->map(function($stat) {
            $attendanceRate = $stat->total_sessions > 0
                ? round(($stat->attended_sessions / $stat->total_sessions) * 100)
                : 0;

            return [
                'id' => $stat->children_id,
                'name' => $stat->children->name,
                'attendance_rate' => $attendanceRate,
                'score' => round($stat->avg_score ?? 0),
                'total_sessions' => $stat->total_sessions,
                'attended_sessions' => $stat->attended_sessions,
            ];
        })->when($this->studentSort === 'score_high', fn($collection) => $collection->sortByDesc('score'))
          ->when($this->studentSort === 'score_low', fn($collection) => $collection->sortBy('score'))
          ->when($this->studentSort === 'attendance_high', fn($collection) => $collection->sortByDesc('attendance_rate'))
          ->when($this->studentSort === 'attendance_low', fn($collection) => $collection->sortBy('attendance_rate'))
          ->when($this->studentSort === 'name', fn($collection) => $collection->sortBy('name'))
          ->values()
          ->toArray();
    }

    public function updatedStudentSort(): void
    {
        $this->loadStudentStats();
    }

    protected function loadAttendanceChart(): void
    {
        $startDate = now()->subDays(7)->startOfDay();
        $dates = collect();

        for ($i = 0; $i < 8; $i++) {
            $dates->push($startDate->copy()->addDays($i)->format('Y-m-d'));
        }

        $attendanceData = LearningSession::query()
            ->where('teacher_id', $this->teacher->id)
            ->when($this->selectedSubject, function($query) {
                $query->where('subject_id', $this->selectedSubject);
            })
            ->whereBetween('start_time', [$startDate, now()])
            ->select([
                DB::raw('DATE(start_time) as date'),
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_sessions')
            ])
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $this->attendanceChart = $dates->map(function($date) use ($attendanceData) {
            $data = $attendanceData->get($date);
            return [
                'date' => Carbon::parse($date)->format('d'),
                'day' => Carbon::parse($date)->format('D'),
                'attendance' => $data
                    ? round(($data->attended_sessions / $data->total_sessions) * 100, 1)
                    : 0
            ];
        })->toArray();
    }

    protected function loadSubjectScores(): void
    {
        $startDate = match($this->selectedPeriod) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subMonths(3),
            default => now()->subWeek()
        };

        $stats = LearningSession::query()
            ->where('teacher_id', $this->teacher->id)
            ->when($this->selectedSubject, function($query) {
                if ($this->selectedSubject !== 'all') {
                    $query->where('subject_id', $this->selectedSubject);
                }
            })
            ->where('start_time', '>=', $startDate)
            ->select([
                'children_id',
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('SUM(CASE WHEN attended = 1 THEN 1 ELSE 0 END) as attended_sessions'),
                DB::raw('AVG(performance_score) as avg_score')
            ])
            ->groupBy('children_id')
            ->with('children')
            ->get();

        $this->studentStats = $stats->map(function($stat) {
            $attendanceRate = $stat->total_sessions > 0
                ? round(($stat->attended_sessions / $stat->total_sessions) * 100)
                : 0;

            return [
                'id' => $stat->children_id,
                'name' => $stat->children->name,
                'attendance_rate' => $attendanceRate,
                'score' => round($stat->avg_score ?? 0),
                'total_sessions' => $stat->total_sessions,
                'attended_sessions' => $stat->attended_sessions,
            ];
        })->when($this->studentSort === 'score_high', fn($collection) => $collection->sortByDesc('score'))
          ->when($this->studentSort === 'score_low', fn($collection) => $collection->sortBy('score'))
          ->when($this->studentSort === 'attendance_high', fn($collection) => $collection->sortByDesc('attendance_rate'))
          ->when($this->studentSort === 'attendance_low', fn($collection) => $collection->sortBy('attendance_rate'))
          ->when($this->studentSort === 'name', fn($collection) => $collection->sortBy('name'))
          ->values()
          ->toArray();
    }

    protected function loadSubjectChanges(): void
    {
        $lastMonth = now()->subMonth()->startOfDay();

        $changes = LearningSession::query()
            ->select([
                'subjects.name as subject',
                DB::raw("AVG(CASE WHEN start_time < '{$lastMonth}' THEN performance_score END) as previous_score"),
                DB::raw("AVG(CASE WHEN start_time >= '{$lastMonth}' THEN performance_score END) as current_score")
            ])
            ->join('subjects', 'subjects.id', '=', 'learning_sessions.subject_id')
            ->where('learning_sessions.teacher_id', $this->teacher->id)
            ->when($this->selectedSubject, function($query) {
                $query->where('subject_id', $this->selectedSubject);
            })
            ->where('start_time', '>=', $lastMonth->copy()->subMonth())
            ->groupBy('subjects.id', 'subjects.name')
            ->get();

        $this->subjectChanges = $changes->map(function ($change) {
            $previousScore = round($change->previous_score ?? 0);
            $currentScore = round($change->current_score ?? 0);
            $percentageChange = $currentScore - $previousScore;

            return [
                'subject' => $change->subject,
                'from' => $previousScore,
                'to' => $currentScore,
                'change' => $percentageChange
            ];
        })->when($this->changeSort === 'high',
            fn($collection) => $collection->sortByDesc('change'),
            fn($collection) => $collection->sortBy('change')
        )->values()->toArray();
    }

    public function updatedSelectedPeriod(): void
    {
        $this->loadAllData();
    }

    public function updatedSortBy(): void
    {
        $this->loadStudentStats();
    }

    public function updatedScoreSort(): void
    {
        $this->loadSubjectScores();
    }

    public function updatedChangeSort(): void
    {
        $this->loadSubjectChanges();
    }

    public function updatedSelectedSubject(): void
    {
        $this->loadAllData();
    }
}; ?>

<div>
    <!-- Page Header -->
    <x-header title="Class Dashboard" separator>
        <x-slot:actions>
            <x-select
                :options="[
                    ['id' => 'week', 'name' => 'Last Week'],
                    ['id' => 'month', 'name' => 'Last Month'],
                    ['id' => 'quarter', 'name' => 'Last Quarter']
                ]"
                wire:model.live="selectedPeriod"
                class="w-40"
            />

            <x-select
                :options="collect([['id' => 'all', 'name' => 'All Classes']])->concat(
                    $subjects->map(fn($subject) => ['id' => $subject->id, 'name' => $subject->name])
                )->toArray()"
                wire:model.live="selectedSubject"
                class="w-40 ml-2"
            />
        </x-slot:actions>
    </x-header>

    <!-- Main Content -->
    <div class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Students Card -->
            <x-stat-card
                title="Total Students"
                :value="count($studentStats)"
                icon="s-users"
                trend="0"
            />

            <!-- Average Attendance Card -->
            <x-stat-card
                title="Average Attendance"
                :value="number_format(collect($studentStats)->avg('attendance_rate'), 2) . '%'"
                icon="s-chart-bar"
                :trend="collect($attendanceChart)->last()['attendance'] - collect($attendanceChart)->first()['attendance']"
            />

            <!-- Average Score Card -->
            <x-stat-card
                title="Average Score"
                :value="number_format(collect($studentStats)->avg('score'), 2) . '%'"
                icon="c-academic-cap"
                trend="0"
            />

            <!-- Total Sessions Card -->
            <x-stat-card
                title="Total Sessions"
                :value="collect($studentStats)->sum('total_sessions')"
                icon="s-calendar-date-range"
                trend="0"
            />
        </div>

        <!-- Attendance Section -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Student List -->
            <x-card title="My Class">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">Sort by:</span>
                        <x-select
                            :options="[
                                ['id' => 'score_high', 'name' => 'Score (High to Low)'],
                                ['id' => 'score_low', 'name' => 'Score (Low to High)'],
                                ['id' => 'attendance_high', 'name' => 'Attendance (High to Low)'],
                                ['id' => 'attendance_low', 'name' => 'Attendance (Low to High)'],
                                ['id' => 'name', 'name' => 'Name']
                            ]"
                            wire:model.live="studentSort"
                            class="w-48"
                        />
                    </div>
                </div>

                <div class="space-y-4">
                    @forelse($studentStats as $student)
                        <div class="flex items-center justify-between p-4 bg-white border rounded-lg hover:bg-gray-50">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0 w-10 h-10 bg-gray-200 rounded-full"></div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $student['name'] }}</h4>
                                    <p class="text-sm text-gray-500">{{ $student['attended_sessions'] }}/{{ $student['total_sessions'] }} sessions</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <div @class([
                                    'px-3 py-1 text-sm font-medium rounded-full',
                                    'bg-green-100 text-green-800' => $student['score'] >= 80,
                                    'bg-yellow-100 text-yellow-800' => $student['score'] >= 60 && $student['score'] < 80,
                                    'bg-red-100 text-red-800' => $student['score'] < 60
                                ])>
                                    Score: {{ $student['score'] }}%
                                </div>
                                <div @class([
                                    'px-3 py-1 text-sm font-medium rounded-full',
                                    'bg-green-100 text-green-800' => $student['attendance_rate'] >= 80,
                                    'bg-yellow-100 text-yellow-800' => $student['attendance_rate'] >= 60 && $student['attendance_rate'] < 80,
                                    'bg-red-100 text-red-800' => $student['attendance_rate'] < 60
                                ])>
                                    Attendance: {{ $student['attendance_rate'] }}%
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-gray-500">
                            No students found
                        </div>
                    @endforelse
                </div>
            </x-card>

            <!-- Attendance Chart -->
            <x-card title="Most Changed">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">Sort by:</span>
                        <x-select
                            :options="[
                                ['id' => 'high', 'name' => 'high to low'],
                                ['id' => 'low', 'name' => 'low to high']
                            ]"
                            wire:model.live="changeSort"
                            class="w-32"
                        />
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach($subjectChanges as $change)
                        <div class="flex items-center justify-between p-4 bg-white border rounded-lg hover:bg-gray-50">
                            <span class="font-medium">{{ $change['subject'] }}</span>
                            <div class="flex items-center gap-4">
                                <span class="text-gray-600">{{ $change['from'] }}% → {{ $change['to'] }}%</span>
                                <div @class([
                                    'flex items-center gap-1',
                                    'text-green-500' => $change['change'] > 0,
                                    'text-red-500' => $change['change'] < 0,
                                    'text-gray-500' => $change['change'] === 0
                                ])>
                                    @if($change['change'] > 0)
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    @elseif($change['change'] < 0)
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                                        </svg>
                                    @endif
                                    <span>{{ abs($change['change']) }}%</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>

        </div>



            <!-- Subject Changes -->
            <x-card title="Attendance Overview">
                <div class="h-80">
                    <livewire:attendance-chart :chart-data="$attendanceChart" />
                </div>
            </x-card>
        </div>
    </div>
</div>
