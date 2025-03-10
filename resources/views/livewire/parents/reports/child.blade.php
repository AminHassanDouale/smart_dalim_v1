<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Children;
use App\Models\LearningSession;
use App\Models\Subject;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Collection;

new class extends Component {
    public $child;
    public $dateRange = 'month';
    public $startDate = null;
    public $endDate = null;
    public $selectedSubject = null;
    public $activeTab = 'overview';

    // For charts data
    public $performanceData = [];
    public $attendanceData = [];
    public $strengthsWeaknessesData = [];
    public $progressTrendData = [];

    // For modal
    public $showSessionDetailModal = false;
    public $selectedSession = null;

    // For comparison with siblings
    public $siblings = [];
    public $showComparison = false;

    protected $queryString = [
        'dateRange' => ['except' => 'month'],
        'selectedSubject' => ['except' => null],
        'activeTab' => ['except' => 'overview'],
    ];

    public function mount($child)
    {
        $this->child = Children::where('id', $child)
            ->where('parent_profile_id', Auth::user()->parentProfile->id)
            ->with(['subjects', 'learningSessions'])
            ->firstOrFail();

        // Set date range based on selection
        $this->updateDateRange();

        // Get siblings for comparison
        $this->siblings = Children::where('parent_profile_id', Auth::user()->parentProfile->id)
            ->where('id', '!=', $this->child->id)
            ->get();

        // Prepare chart data
        $this->prepareChartData();
    }

    public function updatedDateRange()
    {
        $this->updateDateRange();
        $this->prepareChartData();
    }

    public function updatedSelectedSubject()
    {
        $this->prepareChartData();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
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
            case 'all':
                // Get the earliest session date
                $firstSession = LearningSession::where('children_id', $this->child->id)
                    ->orderBy('start_time', 'asc')
                    ->first();

                if ($firstSession) {
                    $this->startDate = Carbon::parse($firstSession->start_time)->format('Y-m-d');
                } else {
                    $this->startDate = Carbon::now()->subMonths(3)->format('Y-m-d');
                }
                $this->endDate = Carbon::now()->format('Y-m-d');
                break;
            case 'custom':
                // Custom date range is set by the user, so don't update it
                break;
            default:
                $this->startDate = $today->copy()->startOfMonth()->format('Y-m-d');
                $this->endDate = $today->copy()->endOfMonth()->format('Y-m-d');
        }
    }

    public function prepareChartData()
    {
        // Performance by subject data
        $this->preparePerformanceData();

        // Attendance data
        $this->prepareAttendanceData();

        // Strengths and weaknesses data
        $this->prepareStrengthsWeaknessesData();

        // Progress trend data
        $this->prepareProgressTrendData();
    }

    private function preparePerformanceData()
    {
        $query = LearningSession::where('children_id', $this->child->id)
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
            ->values()
            ->toArray();

        $this->performanceData = $bySubject;
    }

    private function prepareAttendanceData()
    {
        $sessions = LearningSession::where('children_id', $this->child->id)
            ->where('status', LearningSession::STATUS_COMPLETED)
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ]);

        if ($this->selectedSubject) {
            $sessions->where('subject_id', $this->selectedSubject);
        }

        $sessions = $sessions->get();

        // Group by month for attendance trend
        $byMonth = $sessions->groupBy(function($session) {
            return Carbon::parse($session->start_time)->format('Y-m');
        })->map(function($group, $month) {
            $total = $group->count();
            $attended = $group->where('attended', true)->count();

            return [
                'month' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'total' => $total,
                'attended' => $attended,
                'missed' => $total - $attended,
                'attendance_rate' => $total > 0 ? round(($attended / $total) * 100) : 0,
            ];
        })
        ->values()
        ->toArray();

        $this->attendanceData = $byMonth;
    }

    private function prepareStrengthsWeaknessesData()
    {
        // Get assessment submissions to analyze strengths and weaknesses
        $assessments = AssessmentSubmission::where('children_id', $this->child->id)
            ->whereBetween('created_at', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ])
            ->with(['assessment.subject'])
            ->get();

        if ($assessments->isEmpty()) {
            $this->strengthsWeaknessesData = [];
            return;
        }

        // Group by skill areas (using mock data for demonstration)
        // In a real app, you'd analyze assessment answers to determine strengths/weaknesses
        $areas = [
            'problem_solving' => ['score' => rand(60, 95), 'level' => ''],
            'critical_thinking' => ['score' => rand(60, 95), 'level' => ''],
            'creativity' => ['score' => rand(60, 95), 'level' => ''],
            'communication' => ['score' => rand(60, 95), 'level' => ''],
            'collaboration' => ['score' => rand(60, 95), 'level' => ''],
            'memorization' => ['score' => rand(60, 95), 'level' => ''],
        ];

        // Determine levels
        foreach ($areas as $key => $area) {
            if ($area['score'] >= 85) {
                $areas[$key]['level'] = 'Excellent';
            } elseif ($area['score'] >= 70) {
                $areas[$key]['level'] = 'Good';
            } elseif ($area['score'] >= 50) {
                $areas[$key]['level'] = 'Average';
            } else {
                $areas[$key]['level'] = 'Needs Improvement';
            }
        }

        $this->strengthsWeaknessesData = $areas;
    }

    private function prepareProgressTrendData()
    {
        $query = LearningSession::where('children_id', $this->child->id)
            ->where('status', LearningSession::STATUS_COMPLETED)
            ->whereNotNull('performance_score')
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ])
            ->orderBy('start_time');

        if ($this->selectedSubject) {
            $query->where('subject_id', $this->selectedSubject);
        }

        $sessions = $query->with(['subject'])->get();

        if ($sessions->isEmpty()) {
            $this->progressTrendData = [];
            return;
        }

        // Group by week or month depending on date range
        $groupBy = $this->dateRange === 'week' || $this->dateRange === 'month'
            ? 'Y-m-d' // Daily for shorter ranges
            : 'Y-W';  // Weekly for longer ranges

        $trend = $sessions->groupBy(function($session) use ($groupBy) {
            return Carbon::parse($session->start_time)->format($groupBy);
        });

        // If grouped by subject
        if (!$this->selectedSubject) {
            // Multiple subjects, group by subject and time period
            $trendData = [];

            foreach ($trend as $period => $periodSessions) {
                $bySubject = $periodSessions->groupBy('subject_id');

                foreach ($bySubject as $subjectId => $subjectSessions) {
                    $subject = $subjectSessions->first()->subject;
                    $avgScore = $subjectSessions->avg('performance_score');

                    $trendData[] = [
                        'period' => $groupBy === 'Y-m-d'
                            ? Carbon::createFromFormat('Y-m-d', $period)->format('M d')
                            : 'Week ' . substr($period, 5),
                        'subject' => $subject->name,
                        'score' => round($avgScore, 1),
                    ];
                }
            }

            $this->progressTrendData = $trendData;
        } else {
            // Single subject, just track progress over time
            $trendData = $trend->map(function($periodSessions, $period) use ($groupBy) {
                return [
                    'period' => $groupBy === 'Y-m-d'
                        ? Carbon::createFromFormat('Y-m-d', $period)->format('M d')
                        : 'Week ' . substr($period, 5),
                    'score' => round($periodSessions->avg('performance_score'), 1),
                ];
            })->values()->toArray();

            $this->progressTrendData = $trendData;
        }
    }

    public function viewSessionDetail($sessionId)
    {
        $this->selectedSession = LearningSession::with(['teacher', 'subject', 'course'])
            ->findOrFail($sessionId);
        $this->showSessionDetailModal = true;
    }

    public function closeSessionDetailModal()
    {
        $this->showSessionDetailModal = false;
        $this->selectedSession = null;
    }

    public function toggleComparison()
    {
        $this->showComparison = !$this->showComparison;
    }

    public function getAssessmentsProperty()
    {
        $query = AssessmentSubmission::where('children_id', $this->child->id)
            ->whereBetween('created_at', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ])
            ->with(['assessment.subject', 'assessment.teacherProfile.user']);

        if ($this->selectedSubject) {
            $query->whereHas('assessment', function($q) {
                $q->where('subject_id', $this->selectedSubject);
            });
        }

        return $query->latest()->paginate(5);
    }

    public function getRecentSessionsProperty()
    {
        $query = LearningSession::where('children_id', $this->child->id)
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ])
            ->with(['teacher', 'subject']);

        if ($this->selectedSubject) {
            $query->where('subject_id', $this->selectedSubject);
        }

        return $query->latest('start_time')->paginate(10);
    }

    public function getAttendanceStatsProperty()
    {
        $sessions = LearningSession::where('children_id', $this->child->id)
            ->where('status', LearningSession::STATUS_COMPLETED)
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ]);

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

    public function getPerformanceStatsProperty()
    {
        $sessions = LearningSession::where('children_id', $this->child->id)
            ->where('status', LearningSession::STATUS_COMPLETED)
            ->whereNotNull('performance_score')
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ]);

        if ($this->selectedSubject) {
            $sessions->where('subject_id', $this->selectedSubject);
        }

        $sessions = $sessions->get();

        if ($sessions->isEmpty()) {
            return [
                'average' => 0,
                'highest' => 0,
                'lowest' => 0,
                'trend' => 'steady',
                'total_sessions' => 0,
            ];
        }

        // Get first and last session to determine trend
        $sortedSessions = $sessions->sortBy('start_time');
        $firstSession = $sortedSessions->first();
        $lastSession = $sortedSessions->last();

        $trend = 'steady';
        if ($sessions->count() > 1) {
            $diff = $lastSession->performance_score - $firstSession->performance_score;
            if ($diff > 0.5) {
                $trend = 'improving';
            } elseif ($diff < -0.5) {
                $trend = 'declining';
            }
        }

        return [
            'average' => round($sessions->avg('performance_score'), 1),
            'highest' => round($sessions->max('performance_score'), 1),
            'lowest' => round($sessions->min('performance_score'), 1),
            'trend' => $trend,
            'total_sessions' => $sessions->count(),
        ];
    }

    public function generatePdf()
    {
        // In a real application, this would generate a PDF report
        $this->toast(
            type: 'success',
            title: 'PDF Report Generated',
            description: 'Your detailed report for ' . $this->child->name . ' has been generated and is ready to download.',
            position: 'toast-bottom toast-end',
            icon: 'o-document-arrow-down',
            css: 'alert-success',
            timeout: 3000
        );
    }

    public function shareReport()
    {
        // In a real application, this would share the report with the teacher
        $this->toast(
            type: 'success',
            title: 'Report Shared Successfully',
            description: 'The report has been shared with ' . $this->child->name . '\'s teachers.',
            position: 'toast-bottom toast-end',
            icon: 'o-share',
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

<div class="p-6 space-y-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header with Child Info -->
        <div class="p-8 mb-8 overflow-hidden rounded-xl bg-gradient-to-r from-primary to-primary-focus">
            <div class="flex flex-col items-start gap-6 md:flex-row md:items-center">
                <div class="avatar placeholder">
                    <div class="w-24 h-24 rounded-full bg-primary-content text-primary-focus">
                        <span class="text-3xl">{{ substr($child->name, 0, 1) }}</span>
                    </div>
                </div>

                <div class="text-primary-content">
                    <h1 class="mb-2 text-3xl font-bold">{{ $child->name }}'s Learning Report</h1>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <div class="flex items-center gap-2">
                            <x-icon name="o-academic-cap" class="w-5 h-5 text-primary-content/80" />
                            <span>Age: {{ $child->age }} years</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-building-library" class="w-5 h-5 text-primary-content/80" />
                            <span>Grade: {{ $child->grade }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="o-building-office" class="w-5 h-5 text-primary-content/80" />
                            <span>School: {{ $child->school_name }}</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-4">
                        @foreach($child->subjects as $subject)
                            <div class="badge bg-primary-content/20 text-primary-content border-primary-content/30">{{ $subject->name }}</div>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 ml-auto">
                    <button wire:click="generatePdf" class="text-primary-content bg-primary-content/20 hover:bg-primary-content/30 border-primary-content/30 btn btn-sm">
                        <x-icon name="o-document-arrow-down" class="w-4 h-4 mr-1" />
                        Download PDF
                    </button>

                    <button wire:click="shareReport" class="text-primary-content bg-primary-content/20 hover:bg-primary-content/30 border-primary-content/30 btn btn-sm">
                        <x-icon name="o-share" class="w-4 h-4 mr-1" />
                        Share Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Report Filter Controls -->
        <div class="p-6 mb-8 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
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
                        <option value="all">All Time</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>

                <!-- Subject Filter -->
                <div>
                    <label for="subjectSelect" class="block mb-2 text-sm font-medium">Subject (Optional)</label>
                    <select
                        id="subjectSelect"
                        wire:model.live="selectedSubject"
                        class="w-full select select-bordered"
                    >
                        <option value="">All Subjects</option>
                        @foreach($child->subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Display -->
                <div class="flex items-end">
                    <div class="p-2 text-sm bg-base-200 rounded-box">
                        <span class="font-medium">Report Period:</span> {{ $this->formatDate($startDate) }} to {{ $this->formatDate($endDate) }}
                    </div>
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
        </div>

        <!-- Tab Navigation -->
        <div class="mb-6 tabs tabs-boxed">
            <a
                wire:click="setActiveTab('overview')"
                class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}"
            >
                Overview
            </a>
            <a
                wire:click="setActiveTab('performance')"
                class="tab {{ $activeTab === 'performance' ? 'tab-active' : '' }}"
            >
                Performance
            </a>
            <a
                wire:click="setActiveTab('attendance')"
                class="tab {{ $activeTab === 'attendance' ? 'tab-active' : '' }}"
            >
                Attendance
            </a>
            <a
                wire:click="setActiveTab('sessions')"
                class="tab {{ $activeTab === 'sessions' ? 'tab-active' : '' }}"
            >
                Sessions
            </a>
            <a
                wire:click="setActiveTab('assessments')"
                class="tab {{ $activeTab === 'assessments' ? 'tab-active' : '' }}"
            >
                Assessments
            </a>
        </div>

        <!-- Overview Tab -->
        @if($activeTab === 'overview')
            <div class="space-y-8">
                <!-- Key Metrics -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <div class="shadow-lg stats bg-base-100">
                        <div class="stat">
                            <div class="stat-figure text-primary">
                                <div class="avatar">
                                    <div class="w-16 p-2 rounded-full bg-primary/10">
                                        <x-icon name="o-star" class="w-full h-full text-primary" />
                                    </div>
                                </div>
                            </div>
                            <div class="stat-title">Performance Score</div>
                            <div class="stat-value text-primary">{{ $this->performanceStats['average'] }}/10</div>
                            <div class="stat-desc">
                                @if($this->performanceStats['trend'] === 'improving')
                                    <span class="flex items-center gap-1 text-success">
                                        <x-icon name="o-arrow-trending-up" class="w-4 h-4" />
                                        Improving
                                    </span>
                                @elseif($this->performanceStats['trend'] === 'declining')
                                    <span class="flex items-center gap-1 text-error">
                                        <x-icon name="o-arrow-trending-down" class="w-4 h-4" />
                                        Declining
                                    </span>
                                @else
                                    <span class="flex items-center gap-1 text-info">
                                        <x-icon name="o-arrow-right" class="w-4 h-4" />
                                        Steady
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="shadow-lg stats bg-base-100">
                        <div class="stat">
                            <div class="stat-figure text-secondary">
                                <div class="avatar">
                                    <div class="w-16 p-2 rounded-full bg-secondary/10">
                                        <x-icon name="o-clock" class="w-full h-full text-secondary" />
                                    </div>
                                </div>
                            </div>
                            <div class="stat-title">Attendance Rate</div>
                            <div class="stat-value text-secondary">{{ $this->attendanceStats['attendance_rate'] }}%</div>
                            <div class="stat-desc">{{ $this->attendanceStats['attended'] }}/{{ $this->attendanceStats['total'] }} sessions</div>
                        </div>
                    </div>

                    <div class="shadow-lg stats bg-base-100">
                        <div class="stat">
                            <div class="stat-figure text-accent">
                                <div class="avatar">
                                    <div class="w-16 p-2 rounded-full bg-accent/10">
                                        <x-icon name="o-book-open" class="w-full h-full text-accent" />
                                    </div>
                                </div>
                            </div>
                            <div class="stat-title">Total Sessions</div>
                            <div class="stat-value text-accent">{{ $this->recentSessions->total() }}</div>
                            <div class="stat-desc">During this period</div>
                        </div>
                    </div>

                    <div class="shadow-lg stats bg-base-100">
                        <div class="stat">
                            <div class="stat-figure text-success">
                                <div class="avatar">
                                    <div class="w-16 p-2 rounded-full bg-success/10">
                                        <x-icon name="o-clipboard-document-check" class="w-full h-full text-success" />
                                    </div>
                                </div>
                            </div>
                            <div class="stat-title">Assessments</div>
                            <div class="stat-value text-success">{{ $this->assessments->total() }}</div>
                            <div class="stat-desc">Completed</div>
                        </div>
                    </div>
                </div>

                <!-- Performance Chart -->
                <div class="p-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Performance By Subject</h2>

                        @if(empty($performanceData))
                            <div class="p-6 text-center bg-base-200 rounded-box">
                                <p>No performance data available for the selected period.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <!-- Visualization -->
                                <div>
                                    <div id="performanceChart" class="w-full h-64"></div>

                                    <script>
                                        document.addEventListener('livewire:initialized', () => {
                                            const data = @json($performanceData);

                                            const options = {
                                                chart: {
                                                    type: 'bar',
                                                    height: 250,
                                                },
                                                series: [{
                                                    name: 'Average Score',
                                                    data: data.map(item => item.average_score)
                                                }],
                                                xaxis: {
                                                    categories: data.map(item => item.subject_name),
                                                },
                                                colors: ['#6419E6'],
                                                plotOptions: {
                                                    bar: {
                                                        borderRadius: 4,
                                                        dataLabels: {
                                                            position: 'top',
                                                        },
                                                    }
                                                },
                                                dataLabels: {
                                                    enabled: true,
                                                    formatter: function (val) {
                                                        return val.toFixed(1) + '/10';
                                                    },
                                                    offsetY: -20,
                                                    style: {
                                                        colors: ['#304758']
                                                    }
                                                },
                                                yaxis: {
                                                    min: 0,
                                                    max: 10
                                                },
                                            };

                                            const chart = new ApexCharts(document.querySelector("#performanceChart"), options);
                                            chart.render();

                                            Livewire.on('refreshCharts', () => {
                                                chart.updateOptions({
                                                    series: [{
                                                        data: @json($performanceData).map(item => item.average_score)
                                                    }],
                                                    xaxis: {
                                                        categories: @json($performanceData).map(item => item.subject_name),
                                                    }
                                                });
                                            });
                                        });
                                    </script>
                                </div>

                                <!-- Data Table -->
                                <div>
                                    <div class="overflow-x-auto">
                                        <table class="table table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Avg. Score</th>
                                                    <th>Sessions</th>
                                                    <th>Highest</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($performanceData as $data)
                                                    <tr>
                                                        <td class="font-medium">{{ $data['subject_name'] }}</td>
                                                        <td>
                                                            <div class="flex items-center gap-2">
                                                                <span>{{ number_format($data['average_score'], 1) }}/10</span>
                                                                <div class="radial-progress {{
                                                                    $data['average_score'] >= 8 ? 'text-success' :
                                                                    ($data['average_score'] >= 6 ? 'text-info' : 'text-error')
                                                                }}" style="--value:{{ $data['average_score'] * 10 }}; --size:1.5rem;"></div>
                                                            </div>
                                                        </td>
                                                        <td>{{ $data['sessions_count'] }}</td>
                                                        <td>{{ number_format($data['highest_score'], 1) }}/10</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Progress Trend -->
                <div class="p-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Progress Trend</h2>

                        @if(empty($progressTrendData))
                            <div class="p-6 text-center bg-base-200 rounded-box">
                                <p>No trend data available for the selected period.</p>
                            </div>
                        @else
                            <div class="h-64" id="progressTrendChart"></div>

                            <script>
                                document.addEventListener('livewire:initialized', () => {
                                    const data = @json($progressTrendData);

                                    // Transform data for ApexCharts
                                    let series = [];

                                    // Check if we have multiple subjects or single subject
                                    if (data.length > 0 && data[0].hasOwnProperty('subject')) {
                                        // Multiple subjects
                                        const subjects = [...new Set(data.map(item => item.subject))];

                                        subjects.forEach(subject => {
                                            const subjectData = data.filter(item => item.subject === subject)
                                                .map(item => ({
                                                    x: item.period,
                                                    y: item.score
                                                }));

                                            series.push({
                                                name: subject,
                                                data: subjectData
                                            });
                                        });
                                    } else {
                                        // Single subject
                                        series = [{
                                            name: 'Performance Score',
                                            data: data.map(item => ({
                                                x: item.period,
                                                y: item.score
                                            }))
                                        }];
                                    }

                                    const options = {
                                        chart: {
                                            type: 'line',
                                            height: 250,
                                            animations: {
                                                enabled: true
                                            },
                                            toolbar: {
                                                show: false
                                            }
                                        },
                                        series: series,
                                        colors: ['#6419E6', '#65C3C8', '#F87272', '#36D399', '#FBBD23'],
                                        xaxis: {
                                            type: 'category'
                                        },
                                        yaxis: {
                                            min: 0,
                                            max: 10,
                                            title: {
                                                text: 'Score (out of 10)'
                                            }
                                        },
                                        dataLabels: {
                                            enabled: false
                                        },
                                        stroke: {
                                            curve: 'smooth',
                                            width: 3
                                        },
                                        markers: {
                                            size: 5
                                        }
                                    };

                                    const chart = new ApexCharts(document.querySelector("#progressTrendChart"), options);
                                    chart.render();

                                    Livewire.on('refreshCharts', () => {
                                        chart.updateOptions({
                                            series: series
                                        });
                                    });
                                });
                            </script>

                            <div class="p-4 mt-4 text-sm rounded-lg bg-base-200">
                                <div class="flex items-start gap-2">
                                    <x-icon name="o-light-bulb" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                    <div>
                                        <p class="font-medium">Key Insight:</p>
                                        <p>
                                            @if(!$selectedSubject && count($performanceData) > 1)
                                                {{ $child->name }} shows strongest performance in {{ collect($performanceData)->sortByDesc('average_score')->first()['subject_name'] }}
                                                and may benefit from additional support in {{ collect($performanceData)->sortBy('average_score')->first()['subject_name'] }}.
                                            @elseif($progressTrendData)
                                                @php
                                                    $firstPoint = $progressTrendData[0]['score'] ?? 0;
                                                    $lastPoint = $progressTrendData[count($progressTrendData) - 1]['score'] ?? 0;
                                                    $diff = $lastPoint - $firstPoint;
                                                @endphp

                                                @if($diff > 0.5)
                                                    {{ $child->name }} is showing steady improvement with an overall gain of {{ number_format(abs($diff), 1) }} points.
                                                @elseif($diff < -0.5)
                                                    {{ $child->name }}'s performance has decreased by {{ number_format(abs($diff), 1) }} points. Consider scheduling a consultation with the teacher.
                                                @else
                                                    {{ $child->name }}'s performance has remained relatively steady over this period.
                                                @endif
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Recent Sessions & Assessments -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Recent Sessions -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="card-title">Recent Sessions</h2>
                                <button wire:click="setActiveTab('sessions')" class="btn btn-sm btn-ghost">
                                    View All
                                </button>
                            </div>

                            @if($this->recentSessions->isEmpty())
                                <div class="p-6 text-center bg-base-200 rounded-box">
                                    <p>No sessions available for the selected period.</p>
                                </div>
                            @else
                                <div class="space-y-3">
                                    @foreach($this->recentSessions->take(3) as $session)
                                        <div class="p-3 bg-base-200 rounded-box">
                                            <div class="flex justify-between mb-1">
                                                <span class="font-medium">{{ $session->subject->name }}</span>
                                                <span class="text-sm">{{ $this->formatDate($session->start_time) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm">with {{ $session->teacher->name }}</span>
                                                <div class="badge {{
                                                    $session->status === 'completed' ? ($session->attended ? 'badge-success' : 'badge-error') :
                                                    ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                                }}">
                                                    {{ ucfirst($session->status) }}
                                                </div>
                                            </div>

                                            @if($session->performance_score !== null)
                                                <div class="flex items-center justify-between mt-2">
                                                    <span class="text-sm">Performance Score:</span>
                                                    <div class="flex items-center gap-1">
                                                        <span>{{ number_format($session->performance_score, 1) }}/10</span>
                                                        <div class="radial-progress {{
                                                            $session->performance_score >= 8 ? 'text-success' :
                                                            ($session->performance_score >= 6 ? 'text-info' : 'text-error')
                                                        }}" style="--value:{{ $session->performance_score * 10 }}; --size:1.5rem;">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="flex justify-center mt-2">
                                    <button wire:click="setActiveTab('sessions')" class="btn btn-sm btn-ghost btn-outline">
                                        See All Sessions
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Recent Assessments -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="card-title">Recent Assessments</h2>
                                <button wire:click="setActiveTab('assessments')" class="btn btn-sm btn-ghost">
                                    View All
                                </button>
                            </div>

                            @if($this->assessments->isEmpty())
                                <div class="p-6 text-center bg-base-200 rounded-box">
                                    <p>No assessments available for the selected period.</p>
                                </div>
                            @else
                                <div class="space-y-3">
                                    @foreach($this->assessments->take(3) as $assessment)
                                        <div class="p-3 bg-base-200 rounded-box">
                                            <div class="flex justify-between mb-1">
                                                <span class="font-medium">{{ $assessment->assessment->title }}</span>
                                                <span class="text-sm">{{ $this->formatDate($assessment->created_at) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm">{{ $assessment->assessment->subject->name }}</span>
                                                <div class="badge {{
                                                    $assessment->status === 'graded' ? 'badge-success' :
                                                    ($assessment->status === 'completed' ? 'badge-info' : 'badge-warning')
                                                }}">
                                                    {{ ucfirst($assessment->status) }}
                                                </div>
                                            </div>

                                            @if($assessment->score !== null)
                                                <div class="flex items-center justify-between mt-2">
                                                    <span class="text-sm">Score:</span>
                                                    <div class="flex items-center gap-1">
                                                        <span>{{ $assessment->score }}/{{ $assessment->assessment->total_points }}</span>
                                                        <div class="radial-progress {{
                                                            $assessment->score / $assessment->assessment->total_points >= 0.8 ? 'text-success' :
                                                            ($assessment->score / $assessment->assessment->total_points >= 0.6 ? 'text-info' : 'text-error')
                                                        }}" style="--value:{{ ($assessment->score / $assessment->assessment->total_points) * 100 }}; --size:1.5rem;">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="flex justify-center mt-2">
                                    <button wire:click="setActiveTab('assessments')" class="btn btn-sm btn-ghost btn-outline">
                                        See All Assessments
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Strengths & Areas for Improvement -->
                <div class="p-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Strengths & Areas for Improvement</h2>

                        @if(empty($strengthsWeaknessesData))
                            <div class="p-6 text-center bg-base-200 rounded-box">
                                <p>Not enough data available to assess strengths and areas for improvement.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <!-- Visualization -->
                                <div>
                                    <div id="skillsRadarChart" class="mx-auto" style="height: 300px;"></div>

                                    <script>
                                        document.addEventListener('livewire:initialized', () => {
                                            const data = @json($strengthsWeaknessesData);

                                            const skills = Object.keys(data);
                                            const scores = skills.map(skill => data[skill].score);

                                            const options = {
                                                chart: {
                                                    height: 300,
                                                    type: 'radar',
                                                },
                                                series: [{
                                                    name: 'Skill Level',
                                                    data: scores
                                                }],
                                                labels: skills.map(skill => skill.replace('_', ' ')).map(
                                                    s => s.charAt(0).toUpperCase() + s.slice(1)
                                                ),
                                                plotOptions: {
                                                    radar: {
                                                        polygons: {
                                                            strokeColors: '#e8e8e8',
                                                            fill: {
                                                                colors: ['#f8f8f8', '#fff']
                                                            }
                                                        }
                                                    }
                                                },
                                                colors: ['#6419E6'],
                                                markers: {
                                                    size: 5,
                                                    colors: ['#6419E6'],
                                                    strokeWidth: 2,
                                                },
                                                tooltip: {
                                                    y: {
                                                        formatter: function(val) {
                                                            return val + '%';
                                                        }
                                                    }
                                                },
                                                yaxis: {
                                                    tickAmount: 5,
                                                    min: 0,
                                                    max: 100
                                                }
                                            };

                                            const chart = new ApexCharts(document.querySelector("#skillsRadarChart"), options);
                                            chart.render();

                                            Livewire.on('refreshCharts', () => {
                                                const newData = @json($strengthsWeaknessesData);
                                                const newSkills = Object.keys(newData);
                                                const newScores = newSkills.map(skill => newData[skill].score);

                                                chart.updateOptions({
                                                    labels: newSkills.map(skill => skill.replace('_', ' ')).map(
                                                        s => s.charAt(0).toUpperCase() + s.slice(1)
                                                    ),
                                                    series: [{
                                                        data: newScores
                                                    }]
                                                });
                                            });
                                        });
                                    </script>
                                </div>

                                <!-- Skills Analysis -->
                                <div>
                                    <div class="mb-4">
                                        <h3 class="mb-2 text-lg font-medium">Key Strengths</h3>
                                        <ul class="space-y-2">
                                            @php
                                                $strengths = collect($strengthsWeaknessesData)
                                                    ->sortByDesc('score')
                                                    ->take(3);
                                            @endphp

                                            @foreach($strengths as $skill => $data)
                                                <li class="flex items-start gap-2">
                                                    <x-icon name="o-check-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-success" />
                                                    <div>
                                                        <div class="font-medium">{{ ucfirst(str_replace('_', ' ', $skill)) }}</div>
                                                        <div class="text-sm">
                                                            {{ $data['level'] }} ({{ $data['score'] }}%)
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>

                                    <div>
                                        <h3 class="mb-2 text-lg font-medium">Areas for Improvement</h3>
                                        <ul class="space-y-2">
                                            @php
                                                $weaknesses = collect($strengthsWeaknessesData)
                                                    ->sortBy('score')
                                                    ->take(3);
                                            @endphp

                                            @foreach($weaknesses as $skill => $data)
                                                <li class="flex items-start gap-2">
                                                    <x-icon name="o-academic-cap" class="flex-shrink-0 w-5 h-5 mt-0.5 text-warning" />
                                                    <div>
                                                        <div class="font-medium">{{ ucfirst(str_replace('_', ' ', $skill)) }}</div>
                                                        <div class="text-sm">
                                                            {{ $data['level'] }} ({{ $data['score'] }}%)
                                                        </div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>

                                    <div class="p-4 mt-4 text-sm rounded-lg bg-base-200">
                                        <div class="flex items-start gap-2">
                                            <x-icon name="o-light-bulb" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                            <div>
                                                <p class="font-medium">Development Focus:</p>
                                                <p>
                                                    @if (!empty($weaknesses))
                                                        We recommend focusing on developing {{ $child->name }}'s {{ collect($weaknesses)->keys()->map(fn($s) => str_replace('_', ' ', $s))->join(', ', ' and ') }} skills through targeted exercises and activities.
                                                    @else
                                                        Continue to encourage {{ $child->name }}'s overall academic development with a balanced approach to all skill areas.
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Teacher Feedback & Recommendations -->
                <div class="p-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Teacher Feedback & Recommendations</h2>

                        <div class="p-4 mb-4 border rounded-lg bg-base-200 border-base-300">
                            <div class="flex gap-4">
                                <div class="avatar placeholder">
                                    <div class="w-16 rounded-full bg-neutral-focus text-neutral-content">
                                        <span>TF</span>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="text-lg font-medium">Recent Feedback</h3>
                                    <p class="mt-2">
                                        {{ $child->name }} has been making steady progress in all subjects.
                                        {{ !empty($performanceData) ? 'Particularly in ' . collect($performanceData)->sortByDesc('average_score')->first()['subject_name'] . ', where ' . ($child->gender === 'male' ? 'he' : 'she') . ' demonstrates a strong understanding of core concepts.' : '' }}
                                        We recommend regular practice at home to reinforce classroom learning.
                                        Daily reading and practice with math problems will help build confidence and skills.
                                    </p>
                                    <div class="mt-2 text-sm text-right text-base-content/70">
                                        - Teaching Staff
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="p-4 border rounded-lg border-base-300">
                                <h3 class="flex items-center gap-2 mb-3 text-lg font-medium">
                                    <x-icon name="o-fire" class="w-5 h-5 text-primary" />
                                    Strengths to Cultivate
                                </h3>
                                <ul class="space-y-2">
                                    @if (!empty($performanceData))
                                        <li class="flex items-start gap-2">
                                            <x-icon name="o-check" class="flex-shrink-0 w-5 h-5 mt-0.5 text-success" />
                                            <span>Strong understanding of {{ collect($performanceData)->sortByDesc('average_score')->first()['subject_name'] }} concepts</span>
                                        </li>
                                    @endif
                                    <li class="flex items-start gap-2">
                                        <x-icon name="o-check" class="flex-shrink-0 w-5 h-5 mt-0.5 text-success" />
                                        <span>Consistent participation in class activities</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <x-icon name="o-check" class="flex-shrink-0 w-5 h-5 mt-0.5 text-success" />
                                        <span>Shows curiosity and asks thoughtful questions</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="p-4 border rounded-lg border-base-300">
                                <h3 class="flex items-center gap-2 mb-3 text-lg font-medium">
                                    <x-icon name="o-academic-cap" class="w-5 h-5 text-primary" />
                                    Areas for Home Support
                                </h3>
                                <ul class="space-y-2">
                                    @if (!empty($performanceData))
                                        <li class="flex items-start gap-2">
                                            <x-icon name="o-arrow-right" class="flex-shrink-0 w-5 h-5 mt-0.5 text-info" />
                                            <span>Additional practice in {{ collect($performanceData)->sortBy('average_score')->first()['subject_name'] }}</span>
                                        </li>
                                    @endif
                                    <li class="flex items-start gap-2">
                                        <x-icon name="o-arrow-right" class="flex-shrink-0 w-5 h-5 mt-0.5 text-info" />
                                        <span>Reading for 20 minutes daily</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <x-icon name="o-arrow-right" class="flex-shrink-0 w-5 h-5 mt-0.5 text-info" />
                                        <span>Review of homework and classroom materials</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="flex justify-center mt-4">
                            <button class="btn btn-primary">
                                <x-icon name="o-chat-bubble-left-right" class="w-5 h-5 mr-2" />
                                Schedule Parent-Teacher Conference
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sibling Comparison (if applicable) -->
                @if(count($siblings) > 0)
                    <div class="p-6 shadow-xl card bg-base-100">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="card-title">Sibling Comparison</h2>
                                <button wire:click="toggleComparison" class="btn btn-sm btn-outline">
                                    {{ $showComparison ? 'Hide' : 'Show' }} Comparison
                                </button>
                            </div>

                            @if($showComparison)
                                <div class="p-6 rounded-lg bg-base-200">
                                    <p class="mb-4">
                                        Comparing academic performance between siblings can provide insights into learning patterns within the family,
                                        but should be approached carefully. Each child has unique strengths, interests, and learning styles.
                                    </p>

                                    <div class="overflow-x-auto">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Child</th>
                                                    <th>Age/Grade</th>
                                                    <th>Avg. Performance</th>
                                                    <th>Attendance</th>
                                                    <th>Top Subject</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Current child -->
                                                <tr class="bg-primary/10">
                                                    <td class="font-medium">{{ $child->name }}</td>
                                                    <td>{{ $child->age }} / Grade {{ $child->grade }}</td>
                                                    <td>{{ $this->performanceStats['average'] }}/10</td>
                                                    <td>{{ $this->attendanceStats['attendance_rate'] }}%</td>
                                                    <td>
                                                        @if(!empty($performanceData))
                                                            {{ collect($performanceData)->sortByDesc('average_score')->first()['subject_name'] }}
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>
                                                </tr>

                                                <!-- Siblings (mock data for demo) -->
                                                @foreach($siblings as $sibling)
                                                    <tr>
                                                        <td>{{ $sibling->name }}</td>
                                                        <td>{{ $sibling->age }} / Grade {{ $sibling->grade }}</td>
                                                        <td>{{ number_format(rand(6, 9) + rand(0, 10) / 10, 1) }}/10</td>
                                                        <td>{{ rand(85, 98) }}%</td>
                                                        <td>
                                                            {{ ['Mathematics', 'Science', 'History', 'Language Arts', 'Art', 'Music'][rand(0, 5)] }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="p-4 mt-4 text-sm rounded-lg bg-base-100">
                                        <div class="flex items-start gap-2">
                                            <x-icon name="o-information-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-info" />
                                            <div>
                                                <p class="font-medium">Note on Sibling Comparisons:</p>
                                                <p>
                                                    Each child develops at their own pace and has unique strengths and challenges.
                                                    This comparison is provided for informational purposes only and should not be
                                                    used to create unrealistic expectations or undue pressure on any child.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="p-6 text-center bg-base-200 rounded-box">
                                    <p>Click "Show Comparison" to view performance data across siblings.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Performance Tab -->
        @if($activeTab === 'performance')
            <div class="space-y-8">
                <!-- Performance Summary Card -->
                <div class="p-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Performance Summary</h2>

                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                            <div class="p-4 shadow stats bg-base-200">
                                <div class="stat">
                                    <div class="stat-title">Average Score</div>
                                    <div class="stat-value text-primary">{{ $this->performanceStats['average'] }}/10</div>
                                    <div class="stat-desc">Across {{ $this->performanceStats['total_sessions'] }} sessions</div>
                                </div>
                            </div>

                            <div class="p-4 shadow stats bg-base-200">
                                <div class="stat">
                                    <div class="stat-title">Highest Score</div>
                                    <div class="stat-value text-success">{{ $this->performanceStats['highest'] }}/10</div>
                                    <div class="stat-desc">Personal best</div>
                                </div>
                            </div>

                            <div class="p-4 shadow stats bg-base-200">
                                <div class="stat">
                                    <div class="stat-title">Lowest Score</div>
                                    <div class="stat-value text-error">{{ $this->performanceStats['lowest'] }}/10</div>
                                    <div class="stat-desc">Area for improvement</div>
                                </div>
                            </div>

                            <div class="p-4 shadow stats bg-base-200">
                                <div class="stat">
                                    <div class="stat-title">Performance Trend</div>
                                    <div class="flex items-center gap-2 stat-value">
                                        @if($this->performanceStats['trend'] === 'improving')
                                            <x-icon name="o-arrow-trending-up" class="w-6 h-6 text-success" />
                                            <span class="text-success">Improving</span>
                                        @elseif($this->performanceStats['trend'] === 'declining')
                                            <x-icon name="o-arrow-trending-down" class="w-6 h-6 text-error" />
                                            <span class="text-error">Declining</span>
                                        @else
                                            <x-icon name="o-minus" class="w-6 h-6 text-info" />
                                            <span class="text-info">Steady</span>
                                        @endif
                                    </div>
                                    <div class="stat-desc">Overall direction</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Charts -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Subject Performance -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Performance By Subject</h2>

                            @if(empty($performanceData))
                                <div class="p-6 text-center bg-base-200 rounded-box">
                                    <p>No performance data available for the selected period.</p>
                                </div>
                            @else
                                <div id="performanceBarChart" style="height: 300px;"></div>

                                <script>
                                    document.addEventListener('livewire:initialized', () => {
                                        const data = @json($performanceData);

                                        const options = {
                                            chart: {
                                                type: 'bar',
                                                height: 300,
                                            },
                                            series: [{
                                                name: 'Average Score',
                                                data: data.map(item => item.average_score)
                                            }],
                                            xaxis: {
                                                categories: data.map(item => item.subject_name),
                                            },
                                            colors: ['#6419E6'],
                                            plotOptions: {
                                                bar: {
                                                    borderRadius: 4,
                                                    dataLabels: {
                                                        position: 'top',
                                                    },
                                                }
                                            },
                                            dataLabels: {
                                                enabled: true,
                                                formatter: function (val) {
                                                    return val.toFixed(1) + '/10';
                                                },
                                                offsetY: -20,
                                                style: {
                                                    colors: ['#304758']
                                                }
                                            },
                                            yaxis: {
                                                min: 0,
                                                max: 10,
                                                title: {
                                                    text: 'Average Score (out of 10)'
                                                }
                                            },
                                        };

                                        const chart = new ApexCharts(document.querySelector("#performanceBarChart"), options);
                                        chart.render();

                                        Livewire.on('refreshCharts', () => {
                                            chart.updateOptions({
                                                series: [{
                                                    data: @json($performanceData).map(item => item.average_score)
                                                }],
                                                xaxis: {
                                                    categories: @json($performanceData).map(item => item.subject_name),
                                                }
                                            });
                                        });
                                    });
                                </script>
                            @endif
                        </div>
                    </div>

                    <!-- Progress Trend -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Performance Trend</h2>

                            @if(empty($progressTrendData))
                                <div class="p-6 text-center bg-base-200 rounded-box">
                                    <p>No trend data available for the selected period.</p>
                                </div>
                            @else
                                <div id="progressLineChart" style="height: 300px;"></div>

                                <script>
                                    document.addEventListener('livewire:initialized', () => {
                                        const data = @json($progressTrendData);

                                        // Transform data for ApexCharts
                                        let series = [];

                                        // Check if we have multiple subjects or single subject
                                        if (data.length > 0 && data[0].hasOwnProperty('subject')) {
                                            // Multiple subjects
                                            const subjects = [...new Set(data.map(item => item.subject))];

                                            subjects.forEach(subject => {
                                                const subjectData = data.filter(item => item.subject === subject)
                                                    .map(item => ({
                                                        x: item.period,
                                                        y: item.score
                                                    }));

                                                series.push({
                                                    name: subject,
                                                    data: subjectData
                                                });
                                            });
                                        } else {
                                            // Single subject
                                            series = [{
                                                name: 'Performance Score',
                                                data: data.map(item => ({
                                                    x: item.period,
                                                    y: item.score
                                                }))
                                            }];
                                        }

                                        const options = {
                                            chart: {
                                                type: 'line',
                                                height: 300,
                                                animations: {
                                                    enabled: true
                                                },
                                                toolbar: {
                                                    show: true
                                                }
                                            },
                                            series: series,
                                            colors: ['#6419E6', '#65C3C8', '#F87272', '#36D399', '#FBBD23'],
                                            xaxis: {
                                                type: 'category'
                                            },
                                            yaxis: {
                                                min: 0,
                                                max: 10,
                                                title: {
                                                    text: 'Score (out of 10)'
                                                }
                                            },
                                            dataLabels: {
                                                enabled: false
                                            },
                                            stroke: {
                                                curve: 'smooth',
                                                width: 3
                                            },
                                            markers: {
                                                size: 5
                                            },
                                            tooltip: {
                                                shared: false
                                            },
                                            legend: {
                                                position: 'top'
                                            }
                                        };

                                        const chart = new ApexCharts(document.querySelector("#progressLineChart"), options);
                                        chart.render();

                                        Livewire.on('refreshCharts', () => {
                                            chart.updateOptions({
                                                series: series
                                            });
                                        });
                                    });
                                </script>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Performance Details Table -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Detailed Performance</h2>

                        @if($this->recentSessions->isEmpty())
                            <div class="p-6 text-center bg-base-200 rounded-box">
                                <p>No session data available for the selected period.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="table table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Subject</th>
                                            <th>Teacher</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->recentSessions as $session)
                                            @if($session->status === 'completed' && $session->attended)
                                                <tr>
                                                    <td>{{ $this->formatDate($session->start_time) }}</td>
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
                                                    <td>
                                                        <div class="badge badge-success">
                                                            Completed
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <button
                                                            wire:click="viewSessionDetail({{ $session->id }})"
                                                            class="btn btn-sm btn-ghost btn-circle"
                                                        >
                                                            <x-icon name="o-eye" class="w-5 h-5" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">
                                {{ $this->recentSessions->links() }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Skills Assessment -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Skills Assessment</h2>

                        @if(empty($strengthsWeaknessesData))
                            <div class="p-6 text-center bg-base-200 rounded-box">
                                <p>Not enough data available to assess skills.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Skill Area</th>
                                            <th>Proficiency Level</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($strengthsWeaknessesData as $skill => $data)
                                            <tr>
                                                <td class="font-medium">{{ ucfirst(str_replace('_', ' ', $skill)) }}</td>
                                                <td>{{ $data['level'] }}</td>
                                                <td>
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                                            <div class="h-full {{
                                                                $data['score'] >= 85 ? 'bg-success' :
                                                                ($data['score'] >= 70 ? 'bg-info' :
                                                                ($data['score'] >= 50 ? 'bg-warning' : 'bg-error'))
                                                            }}" style="width: {{ $data['score'] }}%"></div>
                                                        </div>
                                                        <span>{{ $data['score'] }}%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="badge {{
                                                        $data['score'] >= 85 ? 'badge-success' :
                                                        ($data['score'] >= 70 ? 'badge-info' :
                                                        ($data['score'] >= 50 ? 'badge-warning' : 'badge-error'))
                                                    }}">
                                                        {{
                                                            $data['score'] >= 85 ? 'Excellent' :
                                                            ($data['score'] >= 70 ? 'Good' :
                                                            ($data['score'] >= 50 ? 'Average' : 'Needs Improvement'))
                                                        }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="p-4 mt-4 text-sm rounded-lg bg-base-200">
                                <div class="flex items-start gap-2">
                                    <x-icon name="o-light-bulb" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                    <div>
                                        <p class="font-medium">Recommendations:</p>
                                        <ul class="mt-2 space-y-1 list-disc list-inside">
                                            @foreach(collect($strengthsWeaknessesData)->sortBy('score')->take(2) as $skill => $data)
                                                <li>
                                                    Focus on improving {{ strtolower(str_replace('_', ' ', $skill)) }}
                                                    skills through targeted practice and activities.
                                                </li>
                                            @endforeach
                                            <li>
                                                Continue to nurture strengths in
                                                {{ collect($strengthsWeaknessesData)->sortByDesc('score')->first()['score'] >= 85 ?
                                                    strtolower(str_replace('_', ' ', collect($strengthsWeaknessesData)->sortByDesc('score')->keys()->first())) :
                                                    'all areas'
                                                }}.
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Other tabs (attendance, sessions, assessments) would be implemented similarly -->

        <!-- Session Detail Modal -->
        <div class="modal {{ $showSessionDetailModal ? 'modal-open' : '' }}">
            <div class="modal-box">
                <button wire:click="closeSessionDetailModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

                @if($selectedSession)
                    <h3 class="text-lg font-bold">Session Details</h3>

                    <div class="py-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <div class="mb-2">
                                    <span class="font-bold">Subject:</span>
                                    <span>{{ $selectedSession->subject->name }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-bold">Teacher:</span>
                                    <span>{{ $selectedSession->teacher->name }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-bold">Date:</span>
                                    <span>{{ $this->formatDate($selectedSession->start_time) }}</span>
                                </div>
                            </div>

                            <div>
                                <div class="mb-2">
                                    <span class="font-bold">Time:</span>
                                    <span>{{ Carbon::parse($selectedSession->start_time)->format('g:i A') }} - {{ Carbon::parse($selectedSession->end_time)->format('g:i A') }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-bold">Duration:</span>
                                    <span>{{ $this->formatDuration($selectedSession->start_time, $selectedSession->end_time) }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-bold">Status:</span>
                                    <div class="badge {{
                                        $selectedSession->status === 'completed' ? 'badge-success' :
                                        ($selectedSession->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                    }}">
                                        {{ ucfirst($selectedSession->status) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($selectedSession->performance_score !== null)
                            <div class="mt-4">
                                <div class="font-bold">Performance Score:</div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xl">{{ number_format($selectedSession->performance_score, 1) }}/10</span>
                                    <div class="radial-progress {{
                                        $selectedSession->performance_score >= 8 ? 'text-success' :
                                        ($selectedSession->performance_score >= 6 ? 'text-warning' : 'text-error')
                                    }}" style="--value:{{ $selectedSession->performance_score * 10 }}; --size:3rem;">
                                        {{ round($selectedSession->performance_score * 10) }}%
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($selectedSession->notes)
                            <div class="mt-4">
                                <div class="font-bold">Teacher Notes:</div>
                                <div class="p-3 mt-1 bg-base-200 rounded-box">
                                    {{ $selectedSession->notes }}
                                </div>
                            </div>
                        @endif

                        <div class="p-4 mt-4 text-sm rounded-lg bg-base-200">
                            <div class="flex items-start gap-2">
                                <x-icon name="o-light-bulb" class="flex-shrink-0 w-5 h-5 mt-0.5 text-primary" />
                                <div>
                                    <p class="font-medium">Follow-up Recommendations:</p>
                                    <ul class="mt-2 space-y-1 list-disc list-inside">
                                        <li>
                                            Review the material covered in this session with your child.
                                        </li>
                                        <li>
                                            Practice related concepts through homework and additional exercises.
                                        </li>
                                        <li>
                                            Discuss any questions or challenges with the teacher in the next session.
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-action">
                        <button wire:click="closeSessionDetailModal" class="btn">Close</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
