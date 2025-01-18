<?php

namespace App\Livewire;

use App\Models\LearningSession;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public ?string $period = '-30 days';
    public ?string $selectedSubject = null;
    public array $performanceChart = [
        'type' => 'line',
        'options' => [
            'backgroundColor' => '#dfd7f7',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false
                    ]
                ],
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'ticks' => [
                        'callback' => 'function(value) { return value + "%"; }'
                    ]
                ]
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom'
                ]
            ],
        ],
        'data' => [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Class Completion Rate',
                    'data' => [],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => '0.1',
                    'fill' => true
                ],
                [
                    'label' => 'Average Student Performance',
                    'data' => [],
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => '0.1',
                    'fill' => true
                ]
            ]
        ]
    ];
    public array $teacherStats = [];
    public array $upcomingSessions = [];
    public Collection $subjects;

    public function mount(): void
    {
        // Get subjects through learning sessions
        $this->subjects = Subject::whereIn('id', function($query) {
            $query->select('subject_id')
                  ->from('learning_sessions')
                  ->where('teacher_id', auth()->id())
                  ->distinct();
        })->get();

        if ($this->subjects->isNotEmpty()) {
            $this->selectedSubject = $this->subjects->first()->id;
            $this->loadDashboardData();
        }
    }

    public function updatedPeriod($value): void
    {
        $this->period = $value;
        $this->loadDashboardData();
    }

    public function updatedSelectedSubject($value): void
    {
        $this->selectedSubject = $value;
        $this->loadDashboardData();
    }

    #[Computed]
    public function loadDashboardData(): void
    {
        $this->loadChartData();
        $this->loadTeacherStats();
        $this->loadUpcomingSessions();
    }

    protected function loadChartData(): void
    {
        $startDate = Carbon::parse($this->period)->startOfDay();
        $teacherId = auth()->id();

        $data = Cache::remember("teacher_chart_data_{$teacherId}_{$this->period}", 300, function () use ($startDate, $teacherId) {
            return LearningSession::query()
                ->where('teacher_id', $teacherId)
                ->when($this->selectedSubject, function($query) {
                    $query->where('subject_id', $this->selectedSubject);
                })
                ->where('created_at', '>=', $startDate)
                ->select([
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total_sessions'),
                    DB::raw('SUM(CASE WHEN status = "completed" AND attended = 1 THEN 1 ELSE 0 END) as completed_sessions'),
                    DB::raw('AVG(performance_score) as avg_score')
                ])
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        });

        Arr::set($this->performanceChart, 'data.labels',
            $data->pluck('date')->map(fn($date) => Carbon::parse($date)->format('M d')));

        Arr::set($this->performanceChart, 'data.datasets.0.data',
            $data->map(fn($item) => $item->total_sessions > 0
                ? round(($item->completed_sessions / $item->total_sessions) * 100, 1)
                : 0));

        Arr::set($this->performanceChart, 'data.datasets.1.data',
            $data->pluck('avg_score')->map(fn($score) => round($score ?? 0, 1)));
    }

    protected function loadTeacherStats(): void
    {
        $startDate = Carbon::parse($this->period)->startOfDay();
        $teacherId = auth()->id();

        $data = Cache::remember("teacher_stats_{$teacherId}_{$this->period}", 300, function () use ($startDate, $teacherId) {
            $query = LearningSession::query()
                ->where('teacher_id', $teacherId)
                ->where('created_at', '>=', $startDate)
                ->when($this->selectedSubject, function($query) {
                    $query->where('subject_id', $this->selectedSubject);
                });

            $completedSessions = clone $query;

            return [
                'total_sessions' => $query->count(),
                'completed_sessions' => $completedSessions
                    ->where('status', 'completed')
                    ->where('attended', true)
                    ->count(),
                'total_students' => $query->distinct('children_id')->count(),
                'avg_performance' => $query->where('performance_score', '>', 0)->avg('performance_score'),
                'total_hours' => $query->sum(DB::raw('TIMESTAMPDIFF(HOUR, start_time, end_time)'))
            ];
        });

        $this->teacherStats = [
            'total_sessions' => $data['total_sessions'],
            'completion_rate' => $data['total_sessions'] > 0
                ? round(($data['completed_sessions'] / $data['total_sessions']) * 100)
                : 0,
            'total_students' => $data['total_students'],
            'avg_performance' => round($data['avg_performance'] ?? 0),
            'teaching_hours' => round($data['total_hours'] ?? 0)
        ];
    }

    protected function loadUpcomingSessions(): void
    {
        $teacherId = auth()->id();

        $this->upcomingSessions = Cache::remember(
            "teacher_upcoming_sessions_{$teacherId}",
            300,
            function () use ($teacherId) {
                return LearningSession::query()
                    ->where('teacher_id', $teacherId)
                    ->where('start_time', '>', now())
                    ->where('status', 'scheduled')
                    ->with(['subject', 'child'])
                    ->orderBy('start_time')
                    ->take(5)
                    ->get()
                    ->map(fn($session) => [
                        'id' => $session->id,
                        'subject' => $session->subject->name,
                        'student' => $session->child->name,
                        'start_time' => Carbon::parse($session->start_time)->format('M d, Y H:i'),
                        'duration' => Carbon::parse($session->start_time)
                            ->diffInHours(Carbon::parse($session->end_time)),
                        'status' => $session->status
                    ])
                    ->toArray();
            }
        );
    }

    #[Computed]
    public function calendarEvents(): array
    {
        return LearningSession::query()
            ->where('teacher_id', auth()->id())
            ->when($this->selectedSubject, function($query) {
                $query->where('subject_id', $this->selectedSubject);
            })
            ->whereDate('start_time', '>=', now()->subMonths(1))
            ->whereDate('start_time', '<=', now()->addMonths(3))
            ->with(['subject', 'child'])
            ->get()
            ->map(function ($session) {
                $startTime = Carbon::parse($session->start_time);
                $endTime = Carbon::parse($session->end_time);

                $cssClass = match(true) {
                    $startTime->isFuture() => '!bg-blue-200',
                    $session->attended && $session->status === 'completed' => '!bg-green-200',
                    !$session->attended || $session->status === 'cancelled' => '!bg-red-200',
                    default => '!bg-gray-200'
                };

                return [
                    'label' => $session->subject->name,
                    'description' => "with {$session->child->name}",
                    'css' => $cssClass,
                    'range' => [
                        $startTime->format('Y-m-d H:i:s'),
                        $endTime->format('Y-m-d H:i:s')
                    ]
                ];
            })
            ->toArray();
    }
    public function redirectToTimetable(): void
{
    $this->redirect("/teachers/" . auth()->id() . "/timetable");
}


    public function with(): array
    {
        return [
            'periods' => [
                ['id' => '-7 days', 'name' => 'Last 7 days'],
                ['id' => '-30 days', 'name' => 'Last 30 days'],
                ['id' => '-90 days', 'name' => 'Last 90 days'],
            ],
        ];
    }
}?>

<div>
    <x-header title="Teacher Dashboard" separator>
        <x-slot:actions>
            <x-select
                :options="$periods"
                wire:model.live="period"
                class="w-40"
            />

            <x-select
                :options="$subjects->map(fn($subject) => ['id' => $subject->id, 'name' => $subject->name])->toArray()"
                wire:model.live="selectedSubject"
                class="w-40 ml-2"
            />
            <!-- Added Timetable Button -->
            <x-button
            wire:click="redirectToTimetable"
            class="ml-2"
        >
            <x-icon name="o-calendar" class="w-5 h-5 mr-2" />
            View Timetable
        </x-button>
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-4">
        <!-- Teacher Stats -->
        <x-card class="col-span-4 lg:col-span-1">
            <div class="space-y-6">
                <!-- Total Sessions -->
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <x-icon name="o-academic-cap" class="w-6 h-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Total Sessions</p>
                        <p class="text-xl font-semibold">{{ $teacherStats['total_sessions'] ?? 0 }}</p>
                    </div>
                </div>

                <!-- Completion Rate -->
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <x-icon name="o-check-circle" class="w-6 h-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Completion Rate</p>
                        <p class="text-xl font-semibold">{{ $teacherStats['completion_rate'] ?? 0 }}%</p>
                    </div>
                </div>

                <!-- Teaching Hours -->
                <div class="flex items-center">
                    <div class="p-3 bg-indigo-100 rounded-full">
                        <x-icon name="o-clock" class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Teaching Hours</p>
                        <p class="text-xl font-semibold">{{ $teacherStats['teaching_hours'] ?? 0 }}</p>
                    </div>
                </div>

                <!-- Total Students -->
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <x-icon name="o-users" class="w-6 h-6 text-purple-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Total Students</p>
                        <p class="text-xl font-semibold">{{ $teacherStats['total_students'] ?? 0 }}</p>
                    </div>
                </div>

                <!-- Average Performance -->
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <x-icon name="o-star" class="w-6 h-6 text-yellow-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Avg. Performance</p>
                        <p class="text-xl font-semibold">{{ $teacherStats['avg_performance'] ?? 0 }}%</p>
                    </div>
                </div>

                <!-- Earnings -->
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-emerald-100">
                        <x-icon name="o-currency-dollar" class="w-6 h-6 text-emerald-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Earnings</p>
                        <p class="text-xl font-semibold">${{ $teacherStats['earnings'] ?? '0.00' }}</p>
                    </div>
                </div>
            </div>
        </x-card>

        <!-- Performance Chart -->
        <div class="col-span-4 lg:col-span-3">
            <x-card title="Performance & Completion Rates" separator shadow>
                <x-chart wire:model="performanceChart" class="h-64" />
            </x-card>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-3">
        <!-- Upcoming Sessions -->
        <x-card title="Upcoming Sessions" class="col-span-4 lg:col-span-1">
            <div class="space-y-4">
                @forelse($upcomingSessions as $session)
                    <div class="p-4 bg-white border rounded-lg shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-medium text-gray-900">{{ $session['subject'] }}</h4>
                            <span class="px-2.5 py-0.5 text-xs font-medium rounded-full {{
                                $session['status'] === 'scheduled' ? 'bg-blue-100 text-blue-800' :
                                ($session['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                'bg-gray-100 text-gray-800')
                            }}">
                                {{ ucfirst($session['status']) }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            <p class="flex items-center text-sm text-gray-600">
                                <x-icon name="o-user" class="w-4 h-4 mr-2" />
                                {{ $session['student'] }}
                            </p>
                            <p class="flex items-center text-sm text-gray-600">
                                <x-icon name="o-clock" class="w-4 h-4 mr-2" />
                                {{ $session['start_time'] }}
                            </p>
                            <p class="flex items-center text-sm text-gray-600">
                                <x-icon name="s-arrow-left-on-rectangle" class="w-4 h-4 mr-2" />
                                {{ $session['duration'] }} hour(s)
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-gray-500">
                        No upcoming sessions
                    </div>
                @endforelse
            </div>
        </x-card>

        <!-- Calendar Section -->
        <div class="col-span-4 lg:col-span-2">
            <x-card title="Teaching Schedule Calendar">
                <div class="flex justify-between mb-4">
                    <div class="flex gap-4 text-sm">
                        <span class="flex items-center">
                            <span class="w-3 h-3 mr-1 bg-green-200 rounded"></span>
                            Completed
                        </span>
                        <span class="flex items-center">
                            <span class="w-3 h-3 mr-1 bg-red-200 rounded"></span>
                            Cancelled
                        </span>
                        <span class="flex items-center">
                            <span class="w-3 h-3 mr-1 bg-blue-200 rounded"></span>
                            Upcoming
                        </span>
                    </div>
                </div>

                <x-calendar
                    :events="$this->calendarEvents()"
                    months="3"
                    wire:key="calendar-{{$selectedSubject}}-{{$period}}"
                />
            </x-card>
        </div>
    </div>
</div>
