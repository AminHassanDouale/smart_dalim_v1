<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\LearningSession;
use App\Models\Children;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $children = [];
    public $selectedChild = null;
    public $selectedSubject = null;
    public $dateRange = 'month';
    public $startDate = null;
    public $endDate = null;
    public $reportType = 'progress';

    // Filter and sort states
    public $searchQuery = '';
    public $sortBy = 'date';
    public $sortDir = 'desc';

    protected $queryString = [
        'selectedChild' => ['except' => null],
        'selectedSubject' => ['except' => null],
        'dateRange' => ['except' => 'month'],
        'reportType' => ['except' => 'progress'],
        'searchQuery' => ['except' => ''],
    ];

    public function mount()
    {
        $this->user = Auth::user();

        // Get all children for this parent
        $this->children = $this->user->parentProfile->children()->get();

        // Set default selected child if available
        if ($this->children->count() > 0 && !$this->selectedChild) {
            $this->selectedChild = $this->children->first()->id;
        }

        // Set date range based on selection
        $this->updateDateRange();
    }

    public function updatedDateRange()
    {
        $this->updateDateRange();
    }

    public function updatedSelectedChild()
    {
        $this->resetPage();
    }

    public function updatedSelectedSubject()
    {
        $this->resetPage();
    }

    public function updatedReportType()
    {
        $this->resetPage();
    }

    public function updateDateRange()
    {
        $today = Carbon::today();

        switch ($this->dateRange) {
            case 'week':
                $this->startDate = $today->copy()->startOfWeek()->format('Y-m-d');
                $this->endDate = $today->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->startDate = $today->copy()->startOfMonth()->format('Y-m-d');
                $this->endDate = $today->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'quarter':
                $this->startDate = $today->copy()->startOfQuarter()->format('Y-m-d');
                $this->endDate = $today->copy()->endOfQuarter()->format('Y-m-d');
                break;
            case 'year':
                $this->startDate = $today->copy()->startOfYear()->format('Y-m-d');
                $this->endDate = $today->copy()->endOfYear()->format('Y-m-d');
                break;
            case 'custom':
                // Custom date range is set by the user, so don't update it
                break;
            default:
                $this->startDate = $today->copy()->startOfMonth()->format('Y-m-d');
                $this->endDate = $today->copy()->endOfMonth()->format('Y-m-d');
        }
    }

    public function setCustomDateRange($start, $end)
    {
        $this->dateRange = 'custom';
        $this->startDate = $start;
        $this->endDate = $end;
        $this->resetPage();
    }

    public function getSelectedChildDataProperty()
    {
        if (!$this->selectedChild) {
            return null;
        }

        return $this->children->firstWhere('id', $this->selectedChild);
    }

    public function getSubjectsProperty()
    {
        if (!$this->selectedChild) {
            return collect();
        }

        return Children::find($this->selectedChild)->subjects;
    }

    public function getLearningSessionsProperty()
    {
        if (!$this->selectedChild) {
            return collect();
        }

        $query = LearningSession::forStudent($this->selectedChild)
            ->with(['teacher', 'subject', 'course'])
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ]);

        if ($this->selectedSubject) {
            $query->where('subject_id', $this->selectedSubject);
        }

        if ($this->searchQuery) {
            $query->where(function($q) {
                $q->whereHas('teacher', function($q) {
                    $q->where('name', 'like', '%' . $this->searchQuery . '%');
                })
                ->orWhereHas('subject', function($q) {
                    $q->where('name', 'like', '%' . $this->searchQuery . '%');
                })
                ->orWhereHas('course', function($q) {
                    $q->where('name', 'like', '%' . $this->searchQuery . '%');
                })
                ->orWhere('notes', 'like', '%' . $this->searchQuery . '%');
            });
        }

        return $query->orderBy('start_time', $this->sortDir)->paginate(10);
    }

    public function getAttendanceStatsProperty()
    {
        if (!$this->selectedChild) {
            return [
                'total' => 0,
                'attended' => 0,
                'missed' => 0,
                'attendance_rate' => 0,
            ];
        }

        $sessions = LearningSession::forStudent($this->selectedChild)
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ])
            ->where('status', LearningSession::STATUS_COMPLETED);

        if ($this->selectedSubject) {
            $sessions->where('subject_id', $this->selectedSubject);
        }

        $total = $sessions->count();
        $attended = $sessions->where('attended', true)->count();

        return [
            'total' => $total,
            'attended' => $attended,
            'missed' => $total - $attended,
            'attendance_rate' => $total > 0 ? round(($attended / $total) * 100) : 0,
        ];
    }

    public function getPerformanceDataProperty()
    {
        if (!$this->selectedChild) {
            return collect();
        }

        $query = LearningSession::forStudent($this->selectedChild)
            ->where('status', LearningSession::STATUS_COMPLETED)
            ->whereNotNull('performance_score')
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ]);

        if ($this->selectedSubject) {
            $query->where('subject_id', $this->selectedSubject);
        }

        $sessions = $query->with(['subject'])->get();

        if ($sessions->isEmpty()) {
            return collect();
        }

        // Group by subject and calculate average scores
        $bySubject = $sessions->groupBy('subject_id')
            ->map(function($group) {
                $subject = $group->first()->subject;
                return [
                    'subject_id' => $subject->id,
                    'subject_name' => $subject->name,
                    'average_score' => $group->avg('performance_score'),
                    'sessions_count' => $group->count(),
                    'highest_score' => $group->max('performance_score'),
                    'lowest_score' => $group->min('performance_score'),
                ];
            })
            ->values();

        return $bySubject;
    }

    public function getProgressTrendProperty()
    {
        if (!$this->selectedChild) {
            return collect();
        }

        $query = LearningSession::forStudent($this->selectedChild)
            ->where('status', LearningSession::STATUS_COMPLETED)
            ->whereNotNull('performance_score')
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ]);

        if ($this->selectedSubject) {
            $query->where('subject_id', $this->selectedSubject);
        }

        $sessions = $query->orderBy('start_time')->get();

        // Format for trend chart
        return $sessions->map(function($session) {
            return [
                'date' => $session->start_time->format('Y-m-d'),
                'score' => $session->performance_score,
                'subject' => $session->subject->name,
            ];
        });
    }

    public function downloadReport()
    {
        // In a real application, this would generate a PDF or Excel report
        $this->toast(
            type: 'success',
            title: 'Report download started',
            description: 'Your report is being generated and will download shortly.',
            position: 'toast-bottom toast-end',
            icon: 'o-document-arrow-down',
            css: 'alert-success',
            timeout: 3000
        );
    }

    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    public function formatDateTime($dateTime)
    {
        return Carbon::parse($dateTime)->format('M d, Y g:i A');
    }

    public function formatDuration($startTime, $endTime)
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $durationMinutes = $start->diffInMinutes($end);

        $hours = floor($durationMinutes / 60);
        $minutes = $durationMinutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    protected function toast(
        string $type,
        string $title,
        $description = '',
        string $position = 'toast-bottom toast-end',
        string $icon = '',
        string $css = '',
        $timeout = 3000,
        $action = null
    ) {
        $actionJson = $action ? json_encode($action) : 'null';

        $this->js("
            Toaster.{$type}('{$title}', {
                description: '{$description}',
                position: '{$position}',
                icon: '{$icon}',
                css: '{$css}',
                timeout: {$timeout},
                action: {$actionJson}
            });
        ");
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Learning Reports</h1>
                <p class="mt-1 text-base-content/70">Track and analyze your child's educational progress</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="downloadReport" class="btn btn-primary">
                    <x-icon name="o-document-arrow-down" class="w-4 h-4 mr-2" />
                    Download Report
                </button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="p-6 mb-8 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                <!-- Child Select -->
                <div>
                    <label for="childSelect" class="block mb-2 text-sm font-medium">Select Child</label>
                    <select
                        id="childSelect"
                        wire:model.live="selectedChild"
                        class="w-full select select-bordered"
                    >
                        @if($children->isEmpty())
                            <option value="">No children found</option>
                        @else
                            @foreach($children as $child)
                                <option value="{{ $child->id }}">{{ $child->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Subject Select -->
                <div>
                    <label for="subjectSelect" class="block mb-2 text-sm font-medium">Subject (Optional)</label>
                    <select
                        id="subjectSelect"
                        wire:model.live="selectedSubject"
                        class="w-full select select-bordered"
                    >
                        <option value="">All Subjects</option>
                        @foreach($this->subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Range -->
                <div>
                    <label for="dateRangeSelect" class="block mb-2 text-sm font-medium">Date Range</label>
                    <select
                        id="dateRangeSelect"
                        wire:model.live="dateRange"
                        class="w-full select select-bordered"
                    >
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="quarter">This Quarter</option>
                        <option value="year">This Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>

                <!-- Report Type -->
                <div>
                    <label for="reportTypeSelect" class="block mb-2 text-sm font-medium">Report Type</label>
                    <select
                        id="reportTypeSelect"
                        wire:model.live="reportType"
                        class="w-full select select-bordered"
                    >
                        <option value="progress">Progress Report</option>
                        <option value="attendance">Attendance Report</option>
                        <option value="performance">Performance Analysis</option>
                        <option value="sessions">Session History</option>
                    </select>
                </div>
            </div>

            @if($dateRange === 'custom')
                <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-2">
                    <div>
                        <label for="startDate" class="block mb-2 text-sm font-medium">Start Date</label>
                        <input
                            type="date"
                            id="startDate"
                            wire:model.live="startDate"
                            class="w-full input input-bordered"
                        />
                    </div>
                    <div>
                        <label for="endDate" class="block mb-2 text-sm font-medium">End Date</label>
                        <input
                            type="date"
                            id="endDate"
                            wire:model.live="endDate"
                            class="w-full input input-bordered"
                        />
                    </div>
                </div>
            @endif

            <div class="p-2 mt-4 text-sm bg-base-200 rounded-box">
                <span class="font-medium">Current Range:</span> {{ $this->formatDate($startDate) }} to {{ $this->formatDate($endDate) }}
            </div>
        </div>

        @if(!$selectedChild)
            <!-- No Child Selected Message -->
            <div class="p-12 text-center shadow-lg rounded-xl bg-base-100">
                <div class="flex flex-col items-center justify-center">
                    <x-icon name="o-user" class="w-16 h-16 mb-4 text-base-content/30" />
                    <h3 class="text-xl font-bold">No Child Selected</h3>
                    <p class="mt-2 text-base-content/70">
                        Please select a child to view their learning reports
                    </p>
                </div>
            </div>
        @elseif($this->selectedChildData)
            <!-- Main Report Content -->
            <div class="mb-8">
                <!-- Child Info Card -->
                <div class="p-6 mb-6 shadow-lg rounded-xl bg-base-100">
                    <div class="flex flex-col items-center gap-4 md:flex-row md:items-start">
                        <div class="avatar placeholder">
                            <div class="w-20 rounded-full bg-neutral-focus text-neutral-content">
                                <span class="text-xl">{{ substr($this->selectedChildData->name, 0, 1) }}</span>
                            </div>
                        </div>
                        <div class="text-center md:text-left">
                            <h2 class="text-2xl font-bold">{{ $this->selectedChildData->name }}</h2>
                            <p class="text-base-content/70">
                                Age: {{ $this->selectedChildData->age }} | Grade: {{ $this->selectedChildData->grade }}
                            </p>
                            <div class="mt-2">
                                <span class="font-medium">School:</span> {{ $this->selectedChildData->school_name }}
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 ml-auto">
                            @foreach($this->subjects as $subject)
                                <div class="badge badge-outline">{{ $subject->name }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Progress Report -->
                @if($reportType === 'progress')
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Progress Report for {{ $this->selectedChildData->name }}</h2>

                            <!-- Overall Stats -->
                            <div class="grid grid-cols-1 gap-4 my-4 md:grid-cols-4">
                                <div class="p-4 shadow stats bg-base-200">
                                    <div class="stat">
                                        <div class="stat-title">Total Sessions</div>
                                        <div class="stat-value">{{ $this->attendanceStats['total'] }}</div>
                                        <div class="stat-desc">During this period</div>
                                    </div>
                                </div>

                                <div class="p-4 shadow stats bg-base-200">
                                    <div class="stat">
                                        <div class="stat-title">Attendance Rate</div>
                                        <div class="stat-value">{{ $this->attendanceStats['attendance_rate'] }}%</div>
                                        <div class="stat-desc">{{ $this->attendanceStats['attended'] }} sessions attended</div>
                                    </div>
                                </div>

                                <div class="p-4 shadow stats bg-base-200">
                                    <div class="stat">
                                        <div class="stat-title">Average Performance</div>
                                        <div class="stat-value">
                                            {{ $this->performanceData->isNotEmpty()
                                                ? number_format($this->performanceData->avg('average_score'), 1) . '/10'
                                                : 'N/A'
                                            }}
                                        </div>
                                        <div class="stat-desc">Across all subjects</div>
                                    </div>
                                </div>

                                <div class="p-4 shadow stats bg-base-200">
                                    <div class="stat">
                                        <div class="stat-title">Best Subject</div>
                                        <div class="text-sm stat-value">
                                            {{ $this->performanceData->isNotEmpty()
                                                ? $this->performanceData->sortByDesc('average_score')->first()['subject_name']
                                                : 'N/A'
                                            }}
                                        </div>
                                        <div class="stat-desc">
                                            {{ $this->performanceData->isNotEmpty()
                                                ? number_format($this->performanceData->sortByDesc('average_score')->first()['average_score'], 1) . '/10'
                                                : ''
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Performance By Subject -->
                            <div class="mb-6">
                                <h3 class="mb-4 text-lg font-semibold">Performance By Subject</h3>

                                @if($this->performanceData->isEmpty())
                                    <div class="p-6 text-center bg-base-200 rounded-box">
                                        <p>No performance data available for the selected period.</p>
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Average Score</th>
                                                    <th>Sessions</th>
                                                    <th>Highest Score</th>
                                                    <th>Trend</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($this->performanceData as $data)
                                                <tr>
                                                    <td class="font-medium">{{ $data['subject_name'] }}</td>
                                                    <td>
                                                        <div class="flex items-center gap-2">
                                                            <span>{{ number_format($data['average_score'], 1) }}/10</span>
                                                            <div class="radial-progress text-primary" style="--value:{{ $data['average_score'] * 10 }}; --size:2rem;">
                                                                {{ number_format($data['average_score'] * 10) }}%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>{{ $data['sessions_count'] }}</td>
                                                    <td>{{ number_format($data['highest_score'], 1) }}/10</td>
                                                    <td>
                                                        <!-- Simplified trend indicator -->
                                                        @if($this->progressTrend->where('subject', $data['subject_name'])->count() > 1)
                                                            @php
                                                                $subjectTrend = $this->progressTrend->where('subject', $data['subject_name']);
                                                                $first = $subjectTrend->first()['score'];
                                                                $last = $subjectTrend->last()['score'];
                                                                $diff = $last - $first;
                                                            @endphp

                                                            @if($diff > 0.5)
                                                                <div class="flex items-center text-success">
                                                                    <x-icon name="o-arrow-trending-up" class="w-5 h-5 mr-1" />
                                                                    <span>Improving</span>
                                                                </div>
                                                            @elseif($diff < -0.5)
                                                                <div class="flex items-center text-error">
                                                                    <x-icon name="o-arrow-trending-down" class="w-5 h-5 mr-1" />
                                                                    <span>Declining</span>
                                                                </div>
                                                            @else
                                                                <div class="flex items-center text-info">
                                                                    <x-icon name="o-minus" class="w-5 h-5 mr-1" />
                                                                    <span>Steady</span>
                                                                </div>
                                                            @endif
                                                        @else
                                                            <span class="text-base-content/50">Insufficient data</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>

                            <!-- Recent Sessions -->
                            <div>
                                <h3 class="mb-4 text-lg font-semibold">Recent Learning Sessions</h3>

                                @if($this->learningSessions->isEmpty())
                                    <div class="p-6 text-center bg-base-200 rounded-box">
                                        <p>No learning sessions available for the selected period.</p>
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>Subject</th>
                                                    <th>Teacher</th>
                                                    <th>Duration</th>
                                                    <th>Performance</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($this->learningSessions as $session)
                                                <tr>
                                                    <td>{{ $this->formatDateTime($session->start_time) }}</td>
                                                    <td>{{ $session->subject->name }}</td>
                                                    <td>{{ $session->teacher->name }}</td>
                                                    <td>{{ $this->formatDuration($session->start_time, $session->end_time) }}</td>
                                                    <td>
                                                        @if($session->performance_score !== null)
                                                            <div class="flex items-center gap-2">
                                                                <span>{{ number_format($session->performance_score, 1) }}/10</span>
                                                                <div class="radial-progress {{
                                                                    $session->performance_score >= 8 ? 'text-success' :
                                                                    ($session->performance_score >= 6 ? 'text-warning' : 'text-error')
                                                                }}" style="--value:{{ $session->performance_score * 10 }}; --size:1.5rem;">
                                                                </div>
                                                            </div>
                                                        @else
                                                            <span class="text-base-content/50">N/A</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="badge {{
                                                            $session->status === 'completed' ? 'badge-success' :
                                                            ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                                        }}">
                                                            {{ ucfirst($session->status) }}
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4">
                                        {{ $this->learningSessions->links() }}
                                    </div>
                                @endif
                            </div>

                            <!-- Recommendations Section -->
                            <div class="p-6 mt-6 bg-base-200 rounded-box">
                                <h3 class="mb-2 text-lg font-semibold">Recommendations</h3>
                                <div class="space-y-3">
                                    @if($this->performanceData->isEmpty())
                                        <p>No performance data available for generating recommendations.</p>
                                    @else
                                        @php
                                            $lowestPerformingSubject = $this->performanceData->sortBy('average_score')->first();
                                        @endphp

                                        @if($lowestPerformingSubject['average_score'] < 7)
                                            <div class="flex items-start gap-2">
                                                <x-icon name="o-academic-cap" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                                <p>Consider additional support in <strong>{{ $lowestPerformingSubject['subject_name'] }}</strong> to improve performance.</p>
                                            </div>
                                        @endif

                                        @if($this->attendanceStats['attendance_rate'] < 80)
                                            <div class="flex items-start gap-2">
                                                <x-icon name="o-clock" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                                <p>Work on improving attendance rate (currently {{ $this->attendanceStats['attendance_rate'] }}%) for better learning continuity.</p>
                                            </div>
                                        @endif

                                        <!-- Additional recommendations based on data -->
                                        <div class="flex items-start gap-2">
                                            <x-icon name="o-light-bulb" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                            <p>Regular practice and revision will help consolidate learning across all subjects.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Attendance Report -->
                @if($reportType === 'attendance')
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Attendance Report for {{ $this->selectedChildData->name }}</h2>

                            <!-- Attendance Overview -->
                            <div class="grid grid-cols-1 gap-4 my-4 md:grid-cols-3">
                                <div class="p-4 text-center shadow stats bg-base-200">
                                    <div class="stat">
                                        <div class="stat-title">Sessions Attended</div>
                                        <div class="stat-value text-success">{{ $this->attendanceStats['attended'] }}</div>
                                        <div class="stat-desc">Successfully completed</div>
                                    </div>
                                </div>

                                <div class="p-4 text-center shadow stats bg-base-200">
                                    <div class="stat">
                                        <div class="stat-title">Attendance Rate</div>
                                        <div class="stat-value {{ $this->attendanceStats['attendance_rate'] >= 90 ? 'text-success' : ($this->attendanceStats['attendance_rate'] >= 75 ? 'text-warning' : 'text-error') }}">
                                            {{ $this->attendanceStats['attendance_rate'] }}%
                                        </div>
                                        <div class="stat-desc">{{ $this->attendanceStats['missed'] }} sessions missed</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance Details -->
                            <div class="mb-6">
                                <h3 class="mb-4 text-lg font-semibold">Attendance Details</h3>

                                @if($this->learningSessions->isEmpty())
                                    <div class="p-6 text-center bg-base-200 rounded-box">
                                        <p>No learning sessions available for the selected period.</p>
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>Subject</th>
                                                    <th>Teacher</th>
                                                    <th>Attendance</th>
                                                    <th>Status</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($this->learningSessions as $session)
                                                <tr>
                                                    <td>{{ $this->formatDateTime($session->start_time) }}</td>
                                                    <td>{{ $session->subject->name }}</td>
                                                    <td>{{ $session->teacher->name }}</td>
                                                    <td>
                                                        @if($session->status === 'completed')
                                                            <div class="badge {{ $session->attended ? 'badge-success' : 'badge-error' }}">
                                                                {{ $session->attended ? 'Present' : 'Absent' }}
                                                            </div>
                                                        @else
                                                            <div class="badge badge-ghost">N/A</div>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="badge {{
                                                            $session->status === 'completed' ? 'badge-success' :
                                                            ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                                        }}">
                                                            {{ ucfirst($session->status) }}
                                                        </div>
                                                    </td>
                                                    <td class="max-w-xs truncate">{{ $session->notes ?? 'No notes' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4">
                                        {{ $this->learningSessions->links() }}
                                    </div>
                                @endif
                            </div>

                            <!-- Attendance Insights -->
                            <div class="p-6 mt-4 bg-base-200 rounded-box">
                                <h3 class="mb-2 text-lg font-semibold">Attendance Insights</h3>
                                <div class="space-y-3">
                                    @if($this->attendanceStats['total'] > 0)
                                        @if($this->attendanceStats['attendance_rate'] >= 90)
                                            <div class="flex items-start gap-2">
                                                <x-icon name="o-check-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-success" />
                                                <p>Excellent attendance rate of {{ $this->attendanceStats['attendance_rate'] }}%. Keep up the good work!</p>
                                            </div>
                                        @elseif($this->attendanceStats['attendance_rate'] >= 75)
                                            <div class="flex items-start gap-2">
                                                <x-icon name="o-exclamation-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-warning" />
                                                <p>Good attendance at {{ $this->attendanceStats['attendance_rate'] }}%, but there's room for improvement.</p>
                                            </div>
                                        @else
                                            <div class="flex items-start gap-2">
                                                <x-icon name="o-exclamation-triangle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-error" />
                                                <p>Attendance rate of {{ $this->attendanceStats['attendance_rate'] }}% needs significant improvement to ensure learning continuity.</p>
                                            </div>
                                        @endif

                                        @if($this->attendanceStats['missed'] > 0)
                                            <div class="flex items-start gap-2">
                                                <x-icon name="o-calendar" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                                <p>{{ $this->attendanceStats['missed'] }} missed sessions during this period. Please ensure make-up lessons are scheduled if needed.</p>
                                            </div>
                                        @endif
                                    @else
                                        <p>No attendance data available for analysis during the selected period.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Performance Analysis -->
                @if($reportType === 'performance')
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Performance Analysis for {{ $this->selectedChildData->name }}</h2>

                            <!-- Performance Overview -->
                            <div class="grid grid-cols-1 gap-4 my-4 md:grid-cols-3">
                                <div class="p-4 shadow stats bg-base-200">
                                    <div class="stat">
                                        <div class="stat-title">Average Performance</div>
                                        <div class="stat-value">
                                            {{ $this->performanceData->isNotEmpty()
                                                ? number_format($this->performanceData->avg('average_score'), 1) . '/10'
                                                : 'N/A'
                                            }}
                                        </div>
                                        <div class="stat-desc">Across all subjects</div>
                                    </div>
                                </div>

                                <div class="p-4 shadow stats bg-base-200">
                                    <div class="stat">
                                        <div class="stat-title">Strongest Subject</div>
                                        <div class="text-sm stat-value text-success">
                                            {{ $this->performanceData->isNotEmpty()
                                                ? $this->performanceData->sortByDesc('average_score')->first()['subject_name']
                                                : 'N/A'
                                            }}
                                        </div>
                                        <div class="stat-desc">
                                            {{ $this->performanceData->isNotEmpty()
                                                ? number_format($this->performanceData->sortByDesc('average_score')->first()['average_score'], 1) . '/10'
                                                : ''
                                            }}
                                        </div>
                                    </div>
                                </div>

                                <div class="p-4 shadow stats bg-base-200">
                                    <div class="stat">
                                        <div class="stat-title">Needs Improvement</div>
                                        <div class="text-sm stat-value text-warning">
                                            {{ $this->performanceData->isNotEmpty()
                                                ? $this->performanceData->sortBy('average_score')->first()['subject_name']
                                                : 'N/A'
                                            }}
                                        </div>
                                        <div class="stat-desc">
                                            {{ $this->performanceData->isNotEmpty()
                                                ? number_format($this->performanceData->sortBy('average_score')->first()['average_score'], 1) . '/10'
                                                : ''
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Performance Details -->
                            <div class="mb-6">
                                <h3 class="mb-4 text-lg font-semibold">Performance By Subject</h3>

                                @if($this->performanceData->isEmpty())
                                    <div class="p-6 text-center bg-base-200 rounded-box">
                                        <p>No performance data available for the selected period.</p>
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Average Score</th>
                                                    <th>Highest Score</th>
                                                    <th>Lowest Score</th>
                                                    <th>Sessions</th>
                                                    <th>Performance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($this->performanceData as $data)
                                                <tr>
                                                    <td class="font-medium">{{ $data['subject_name'] }}</td>
                                                    <td>{{ number_format($data['average_score'], 1) }}/10</td>
                                                    <td>{{ number_format($data['highest_score'], 1) }}/10</td>
                                                    <td>{{ number_format($data['lowest_score'], 1) }}/10</td>
                                                    <td>{{ $data['sessions_count'] }}</td>
                                                    <td>
                                                        <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                                            <div class="h-full {{
                                                                $data['average_score'] >= 8 ? 'bg-success' :
                                                                ($data['average_score'] >= 6 ? 'bg-info' : 'bg-error')
                                                            }}" style="width: {{ $data['average_score'] * 10 }}%"></div>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>

                            <!-- Session Performance -->
                            <div class="mb-6">
                                <h3 class="mb-4 text-lg font-semibold">Recent Session Performance</h3>

                                @if($this->learningSessions->isEmpty())
                                    <div class="p-6 text-center bg-base-200 rounded-box">
                                        <p>No learning sessions available for the selected period.</p>
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>Subject</th>
                                                    <th>Teacher</th>
                                                    <th>Performance Score</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($this->learningSessions->where('status', 'completed') as $session)
                                                <tr>
                                                    <td>{{ $this->formatDateTime($session->start_time) }}</td>
                                                    <td>{{ $session->subject->name }}</td>
                                                    <td>{{ $session->teacher->name }}</td>
                                                    <td>
                                                        @if($session->performance_score !== null)
                                                            <div class="flex items-center gap-2">
                                                                <span>{{ number_format($session->performance_score, 1) }}/10</span>
                                                                <div class="radial-progress {{
                                                                    $session->performance_score >= 8 ? 'text-success' :
                                                                    ($session->performance_score >= 6 ? 'text-warning' : 'text-error')
                                                                }}" style="--value:{{ $session->performance_score * 10 }}; --size:1.8rem;">
                                                                    {{ round($session->performance_score * 10) }}%
                                                                </div>
                                                            </div>
                                                        @else
                                                            <span class="text-base-content/50">N/A</span>
                                                        @endif
                                                    </td>
                                                    <td class="max-w-xs truncate">{{ $session->notes ?? 'No notes' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4">
                                        {{ $this->learningSessions->links() }}
                                    </div>
                                @endif
                            </div>

                            <!-- Performance Insights -->
                            <div class="p-6 mt-4 bg-base-200 rounded-box">
                                <h3 class="mb-2 text-lg font-semibold">Performance Insights</h3>
                                <div class="space-y-3">
                                    @if($this->performanceData->isNotEmpty())
                                        @php
                                            $avgPerformance = $this->performanceData->avg('average_score');
                                            $bestSubject = $this->performanceData->sortByDesc('average_score')->first();
                                            $weakestSubject = $this->performanceData->sortBy('average_score')->first();
                                        @endphp

                                        <div class="flex items-start gap-2">
                                            <x-icon name="o-chart-bar" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                            <p>Overall average performance: <strong>{{ number_format($avgPerformance, 1) }}/10</strong> across all subjects.</p>
                                        </div>

                                        <div class="flex items-start gap-2">
                                            <x-icon name="o-star" class="flex-shrink-0 w-5 h-5 mt-0.5 text-success" />
                                            <p>Strongest performance in <strong>{{ $bestSubject['subject_name'] }}</strong> with an average score of {{ number_format($bestSubject['average_score'], 1) }}/10.</p>
                                        </div>

                                        @if($weakestSubject['average_score'] < 7)
                                            <div class="flex items-start gap-2">
                                                <x-icon name="o-academic-cap" class="flex-shrink-0 w-5 h-5 mt-0.5 text-warning" />
                                                <p>Additional support recommended for <strong>{{ $weakestSubject['subject_name'] }}</strong> where the average score is {{ number_format($weakestSubject['average_score'], 1) }}/10.</p>
                                            </div>
                                        @endif
                                    @else
                                        <p>No performance data available for analysis during the selected period.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Session History -->
                @if($reportType === 'sessions')
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Session History for {{ $this->selectedChildData->name }}</h2>

                            <!-- Search & Filters for Sessions -->
                            <div class="flex flex-col gap-4 mb-4 md:flex-row">
                                <div class="flex-1">
                                    <div class="relative w-full">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                                        </div>
                                        <input
                                            type="text"
                                            wire:model.live.debounce.300ms="searchQuery"
                                            placeholder="Search by teacher, subject, or notes..."
                                            class="w-full pl-10 input input-bordered"
                                        >
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        wire:click="$set('sortDir', '{{ $sortDir === 'desc' ? 'asc' : 'desc' }}')"
                                        class="btn btn-outline btn-sm"
                                    >
                                        <x-icon name="{{ $sortDir === 'desc' ? 'o-arrow-down' : 'o-arrow-up' }}" class="w-4 h-4 mr-1" />
                                        {{ $sortDir === 'desc' ? 'Newest First' : 'Oldest First' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Sessions List -->
                            @if($this->learningSessions->isEmpty())
                                <div class="p-6 text-center bg-base-200 rounded-box">
                                    <p>No learning sessions available for the selected period.</p>
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Subject</th>
                                                <th>Teacher</th>
                                                <th>Duration</th>
                                                <th>Status</th>
                                                <th>Performance</th>
                                                <th>Location</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($this->learningSessions as $session)
                                            <tr>
                                                <td>{{ $this->formatDateTime($session->start_time) }}</td>
                                                <td>{{ $session->subject->name }}</td>
                                                <td>{{ $session->teacher->name }}</td>
                                                <td>{{ $this->formatDuration($session->start_time, $session->end_time) }}</td>
                                                <td>
                                                    <div class="badge {{
                                                        $session->status === 'completed' ? ($session->attended ? 'badge-success' : 'badge-error') :
                                                        ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                                    }}">
                                                        {{ ucfirst($session->status) }}
                                                        {{ $session->status === 'completed' && !$session->attended ? ' (Absent)' : '' }}
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($session->performance_score !== null)
                                                        <div class="flex items-center gap-1">
                                                            {{ number_format($session->performance_score, 1) }}/10
                                                        </div>
                                                    @else
                                                        <span class="text-base-content/50">N/A</span>
                                                    @endif
                                                </td>
                                                <td>{{ $session->location ?? 'Not specified' }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-4">
                                    {{ $this->learningSessions->links() }}
                                </div>
                            @endif

                            <!-- Session Notes -->
                            <div class="p-6 mt-6 bg-base-200 rounded-box">
                                <h3 class="mb-4 text-lg font-semibold">Session Notes</h3>

                                @if($this->learningSessions->where('notes', '!=', null)->count() > 0)
                                    <div class="space-y-4">
                                        @foreach($this->learningSessions->where('notes', '!=', null)->take(5) as $session)
                                            <div class="p-4 bg-base-100 rounded-box">
                                                <div class="flex justify-between mb-2">
                                                    <div class="font-medium">{{ $session->subject->name }} with {{ $session->teacher->name }}</div>
                                                    <div class="text-sm opacity-70">{{ $this->formatDateTime($session->start_time) }}</div>
                                                </div>
                                                <p>{{ $session->notes }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p>No session notes available for the selected period.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
                                    <div class="stat">
                                        <div class="stat-title">Total Sessions</div>
                                        <div class="stat-value">{{ $this->attendanceStats['total'] }}</div>
                                        <div class="stat-desc">Scheduled during this period</div>
                                    </div>
                                </div>

                                <div class="p-4 text-center shadow stats bg-base-200">
                                    <div class="p-4 text-center shadow stats bg-base-200">
                                        <div class="stat">
                                            <div class="stat-title">Sessions Attended</div>
                                            <div class="stat-value text-success">{{ $this->attendanceStats['attended'] }}</div>
                                            <div class="stat-desc">Successfully completed</div>
                                        </div>
                                    </div>

                                    <div class="p-4 text-center shadow stats bg-base-200">
                                        <div class="stat">
                                            <div class="stat-title">Attendance Rate</div>
                                            <div class="stat-value {{ $this->attendanceStats['attendance_rate'] >= 90 ? 'text-success' : ($this->attendanceStats['attendance_rate'] >= 75 ? 'text-warning' : 'text-error') }}">
                                                {{ $this->attendanceStats['attendance_rate'] }}%
                                            </div>
                                            <div class="stat-desc">{{ $this->attendanceStats['missed'] }} sessions missed</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attendance Details -->
                                <div class="mb-6">
                                    <h3 class="mb-4 text-lg font-semibold">Attendance Details</h3>

                                    @if($this->learningSessions->isEmpty())
                                        <div class="p-6 text-center bg-base-200 rounded-box">
                                            <p>No learning sessions available for the selected period.</p>
                                        </div>
                                    @else
                                        <div class="overflow-x-auto">
                                            <table class="table table-zebra">
                                                <thead>
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>Subject</th>
                                                        <th>Teacher</th>
                                                        <th>Attendance</th>
                                                        <th>Status</th>
                                                        <th>Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($this->learningSessions as $session)
                                                    <tr>
                                                        <td>{{ $this->formatDateTime($session->start_time) }}</td>
                                                        <td>{{ $session->subject->name }}</td>
                                                        <td>{{ $session->teacher->name }}</td>
                                                        <td>
                                                            @if($session->status === 'completed')
                                                                <div class="badge {{ $session->attended ? 'badge-success' : 'badge-error' }}">
                                                                    {{ $session->attended ? 'Present' : 'Absent' }}
                                                                </div>
                                                            @else
                                                                <div class="badge badge-ghost">N/A</div>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div class="badge {{
                                                                $session->status === 'completed' ? 'badge-success' :
                                                                ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                                            }}">
                                                                {{ ucfirst($session->status) }}
                                                            </div>
                                                        </td>
                                                        <td class="max-w-xs truncate">{{ $session->notes ?? 'No notes' }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="mt-4">
                                            {{ $this->learningSessions->links() }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Attendance Insights -->
                                <div class="p-6 mt-4 bg-base-200 rounded-box">
                                    <h3 class="mb-2 text-lg font-semibold">Attendance Insights</h3>
                                    <div class="space-y-3">
                                        @if($this->attendanceStats['total'] > 0)
                                            @if($this->attendanceStats['attendance_rate'] >= 90)
                                                <div class="flex items-start gap-2">
                                                    <x-icon name="o-check-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-success" />
                                                    <p>Excellent attendance rate of {{ $this->attendanceStats['attendance_rate'] }}%. Keep up the good work!</p>
                                                </div>
                                            @elseif($this->attendanceStats['attendance_rate'] >= 75)
                                                <div class="flex items-start gap-2">
                                                    <x-icon name="o-exclamation-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-warning" />
                                                    <p>Good attendance at {{ $this->attendanceStats['attendance_rate'] }}%, but there's room for improvement.</p>
                                                </div>
                                            @else
                                                <div class="flex items-start gap-2">
                                                    <x-icon name="o-exclamation-triangle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-error" />
                                                    <p>Attendance rate of {{ $this->attendanceStats['attendance_rate'] }}% needs significant improvement to ensure learning continuity.</p>
                                                </div>
                                            @endif

                                            @if($this->attendanceStats['missed'] > 0)
                                                <div class="flex items-start gap-2">
                                                    <x-icon name="o-calendar" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                                    <p>{{ $this->attendanceStats['missed'] }} missed sessions during this period. Please ensure make-up lessons are scheduled if needed.</p>
                                                </div>
                                            @endif
                                        @else
                                            <p>No attendance data available for analysis during the selected period.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Performance Analysis -->
                    @if($reportType === 'performance')
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="card-title">Performance Analysis for {{ $this->selectedChildData->name }}</h2>

                                <!-- Performance Overview -->
                                <div class="grid grid-cols-1 gap-4 my-4 md:grid-cols-3">
                                    <div class="p-4 shadow stats bg-base-200">
                                        <div class="stat">
                                            <div class="stat-title">Average Performance</div>
                                            <div class="stat-value">
                                                {{ $this->performanceData->isNotEmpty()
                                                    ? number_format($this->performanceData->avg('average_score'), 1) . '/10'
                                                    : 'N/A'
                                                }}
                                            </div>
                                            <div class="stat-desc">Across all subjects</div>
                                        </div>
                                    </div>

                                    <div class="p-4 shadow stats bg-base-200">
                                        <div class="stat">
                                            <div class="stat-title">Strongest Subject</div>
                                            <div class="text-sm stat-value text-success">
                                                {{ $this->performanceData->isNotEmpty()
                                                    ? $this->performanceData->sortByDesc('average_score')->first()['subject_name']
                                                    : 'N/A'
                                                }}
                                            </div>
                                            <div class="stat-desc">
                                                {{ $this->performanceData->isNotEmpty()
                                                    ? number_format($this->performanceData->sortByDesc('average_score')->first()['average_score'], 1) . '/10'
                                                    : ''
                                                }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-4 shadow stats bg-base-200">
                                        <div class="stat">
                                            <div class="stat-title">Needs Improvement</div>
                                            <div class="text-sm stat-value text-warning">
                                                {{ $this->performanceData->isNotEmpty()
                                                    ? $this->performanceData->sortBy('average_score')->first()['subject_name']
                                                    : 'N/A'
                                                }}
                                            </div>
                                            <div class="stat-desc">
                                                {{ $this->performanceData->isNotEmpty()
                                                    ? number_format($this->performanceData->sortBy('average_score')->first()['average_score'], 1) . '/10'
                                                    : ''
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Performance Details -->
                                <div class="mb-6">
                                    <h3 class="mb-4 text-lg font-semibold">Performance By Subject</h3>

                                    @if($this->performanceData->isEmpty())
                                        <div class="p-6 text-center bg-base-200 rounded-box">
                                            <p>No performance data available for the selected period.</p>
                                        </div>
                                    @else
                                        <div class="overflow-x-auto">
                                            <table class="table table-zebra">
                                                <thead>
                                                    <tr>
                                                        <th>Subject</th>
                                                        <th>Average Score</th>
                                                        <th>Highest Score</th>
                                                        <th>Lowest Score</th>
                                                        <th>Sessions</th>
                                                        <th>Performance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($this->performanceData as $data)
                                                    <tr>
                                                        <td class="font-medium">{{ $data['subject_name'] }}</td>
                                                        <td>{{ number_format($data['average_score'], 1) }}/10</td>
                                                        <td>{{ number_format($data['highest_score'], 1) }}/10</td>
                                                        <td>{{ number_format($data['lowest_score'], 1) }}/10</td>
                                                        <td>{{ $data['sessions_count'] }}</td>
                                                        <td>
                                                            <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                                                <div class="h-full {{
                                                                    $data['average_score'] >= 8 ? 'bg-success' :
                                                                    ($data['average_score'] >= 6 ? 'bg-info' : 'bg-error')
                                                                }}" style="width: {{ $data['average_score'] * 10 }}%"></div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </div>

                                <!-- Session Performance -->
                                <div class="mb-6">
                                    <h3 class="mb-4 text-lg font-semibold">Recent Session Performance</h3>

                                    @if($this->learningSessions->isEmpty())
                                        <div class="p-6 text-center bg-base-200 rounded-box">
                                            <p>No learning sessions available for the selected period.</p>
                                        </div>
                                    @else
                                        <div class="overflow-x-auto">
                                            <table class="table table-zebra">
                                                <thead>
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>Subject</th>
                                                        <th>Teacher</th>
                                                        <th>Performance Score</th>
                                                        <th>Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($this->learningSessions->where('status', 'completed') as $session)
                                                    <tr>
                                                        <td>{{ $this->formatDateTime($session->start_time) }}</td>
                                                        <td>{{ $session->subject->name }}</td>
                                                        <td>{{ $session->teacher->name }}</td>
                                                        <td>
                                                            @if($session->performance_score !== null)
                                                                <div class="flex items-center gap-2">
                                                                    <span>{{ number_format($session->performance_score, 1) }}/10</span>
                                                                    <div class="radial-progress {{
                                                                        $session->performance_score >= 8 ? 'text-success' :
                                                                        ($session->performance_score >= 6 ? 'text-warning' : 'text-error')
                                                                    }}" style="--value:{{ $session->performance_score * 10 }}; --size:1.8rem;">
                                                                        {{ round($session->performance_score * 10) }}%
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <span class="text-base-content/50">N/A</span>
                                                            @endif
                                                        </td>
                                                        <td class="max-w-xs truncate">{{ $session->notes ?? 'No notes' }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="mt-4">
                                            {{ $this->learningSessions->links() }}
                                        </div>
                                    @endif
                                </div>

                                <!-- Performance Insights -->
                                <div class="p-6 mt-4 bg-base-200 rounded-box">
                                    <h3 class="mb-2 text-lg font-semibold">Performance Insights</h3>
                                    <div class="space-y-3">
                                        @if($this->performanceData->isNotEmpty())
                                            @php
                                                $avgPerformance = $this->performanceData->avg('average_score');
                                                $bestSubject = $this->performanceData->sortByDesc('average_score')->first();
                                                $weakestSubject = $this->performanceData->sortBy('average_score')->first();
                                            @endphp

                                            <div class="flex items-start gap-2">
                                                <x-icon name="o-chart-bar" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                                <p>Overall average performance: <strong>{{ number_format($avgPerformance, 1) }}/10</strong> across all subjects.</p>
                                            </div>

                                            <div class="flex items-start gap-2">
                                                <x-icon name="o-star" class="flex-shrink-0 w-5 h-5 mt-0.5 text-success" />
                                                <p>Strongest performance in <strong>{{ $bestSubject['subject_name'] }}</strong> with an average score of {{ number_format($bestSubject['average_score'], 1) }}/10.</p>
                                            </div>

                                            @if($weakestSubject['average_score'] < 7)
                                                <div class="flex items-start gap-2">
                                                    <x-icon name="o-academic-cap" class="flex-shrink-0 w-5 h-5 mt-0.5 text-warning" />
                                                    <p>Additional support recommended for <strong>{{ $weakestSubject['subject_name'] }}</strong> where the average score is {{ number_format($weakestSubject['average_score'], 1) }}/10.</p>
                                                </div>
                                            @endif
                                        @else
                                            <p>No performance data available for analysis during the selected period.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Session History -->
                    @if($reportType === 'sessions')
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="card-title">Session History for {{ $this->selectedChildData->name }}</h2>

                                <!-- Search & Filters for Sessions -->
                                <div class="flex flex-col gap-4 mb-4 md:flex-row">
                                    <div class="flex-1">
                                        <div class="relative w-full">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                                            </div>
                                            <input
                                                type="text"
                                                wire:model.live.debounce.300ms="searchQuery"
                                                placeholder="Search by teacher, subject, or notes..."
                                                class="w-full pl-10 input input-bordered"
                                            >
                                        </div>
                                    </div>

                                    <div class="flex gap-2">
                                        <button
                                            wire:click="$set('sortDir', '{{ $sortDir === 'desc' ? 'asc' : 'desc' }}')"
                                            class="btn btn-outline btn-sm"
                                        >
                                            <x-icon name="{{ $sortDir === 'desc' ? 'o-arrow-down' : 'o-arrow-up' }}" class="w-4 h-4 mr-1" />
                                            {{ $sortDir === 'desc' ? 'Newest First' : 'Oldest First' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Sessions List -->
                                @if($this->learningSessions->isEmpty())
                                    <div class="p-6 text-center bg-base-200 rounded-box">
                                        <p>No learning sessions available for the selected period.</p>
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>Subject</th>
                                                    <th>Teacher</th>
                                                    <th>Duration</th>
                                                    <th>Status</th>
                                                    <th>Performance</th>
                                                    <th>Location</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($this->learningSessions as $session)
                                                <tr>
                                                    <td>{{ $this->formatDateTime($session->start_time) }}</td>
                                                    <td>{{ $session->subject->name }}</td>
                                                    <td>{{ $session->teacher->name }}</td>
                                                    <td>{{ $this->formatDuration($session->start_time, $session->end_time) }}</td>
                                                    <td>
                                                        <div class="badge {{
                                                            $session->status === 'completed' ? ($session->attended ? 'badge-success' : 'badge-error') :
                                                            ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                                        }}">
                                                            {{ ucfirst($session->status) }}
                                                            {{ $session->status === 'completed' && !$session->attended ? ' (Absent)' : '' }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($session->performance_score !== null)
                                                            <div class="flex items-center gap-1">
                                                                {{ number_format($session->performance_score, 1) }}/10
                                                            </div>
                                                        @else
                                                            <span class="text-base-content/50">N/A</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $session->location ?? 'Not specified' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4">
                                        {{ $this->learningSessions->links() }}
                                    </div>
                                @endif

                                <!-- Session Notes -->
                                <div class="p-6 mt-6 bg-base-200 rounded-box">
                                    <h3 class="mb-4 text-lg font-semibold">Session Notes</h3>

                                    @if($this->learningSessions->where('notes', '!=', null)->count() > 0)
                                        <div class="space-y-4">
                                            @foreach($this->learningSessions->where('notes', '!=', null)->take(5) as $session)
                                                <div class="p-4 bg-base-100 rounded-box">
                                                    <div class="flex justify-between mb-2">
                                                        <div class="font-medium">{{ $session->subject->name }} with {{ $session->teacher->name }}</div>
                                                        <div class="text-sm opacity-70">{{ $this->formatDateTime($session->start_time) }}</div>
                                                    </div>
                                                    <p>{{ $session->notes }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p>No session notes available for the selected period.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

