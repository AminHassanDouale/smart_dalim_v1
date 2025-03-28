<?php

namespace App\Livewire\Parents\Progress;

use Livewire\Volt\Component;
use App\Models\Children;
use App\Models\LearningSession;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    // User and profile data
    public $user;
    public $parentProfile;

    // Child data
    public $child;
    public $childId;

    // Time range filter
    public $timeRange = 'last_3_months'; // Options: 'last_month', 'last_3_months', 'last_6_months', 'last_year', 'custom'
    public $customStartDate = null;
    public $customEndDate = null;

    // Subject filter
    public $selectedSubject = null;
    public $availableSubjects = [];

    // Active section tabs
    public $activeTab = 'overview'; // 'overview', 'subjects', 'skills', 'attendance', 'performance'
    public $activeSubTab = null;

    // Progress data
    public $overallProgress = 0;
    public $attendanceRate = 0;
    public $assessmentScores = [];
    public $subjectProgress = [];
    public $progressTrend = [];
    public $sessionHistory = [];
    public $upcomingSessions = [];
    public $recentAssessments = [];

    // Modal states
    public $showSessionDetailsModal = false;
    public $showAssessmentDetailsModal = false;

    // Selected item for modals
    public $selectedSession = null;
    public $selectedAssessment = null;

    public function mount($child)
    {
        $this->childId = $child;
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        if (!$this->parentProfile) {
            return redirect()->route('parents.profile-setup');
        }

        // Load child data with relationships
        $this->loadChild();

        // Set date range defaults
        $this->customStartDate = Carbon::now()->subMonths(3)->format('Y-m-d');
        $this->customEndDate = Carbon::now()->format('Y-m-d');

        // Load data
        $this->loadSubjects();
        $this->loadProgressData();
    }

    private function loadChild()
    {
        $this->child = Children::where('id', $this->childId)
            ->where('parent_profile_id', $this->parentProfile->id)
            ->with(['subjects', 'learningSessions.subject', 'learningSessions.teacher',
                   'assessmentSubmissions.assessment.subject', 'teacher'])
            ->firstOrFail();
    }

    private function loadSubjects()
    {
        $this->availableSubjects = $this->child->subjects->toArray();
    }

    private function loadProgressData()
    {
        // Filter data based on date range
        $dateRange = $this->getDateRange();
    
        // Get learning sessions within date range
        $sessions = $this->child->learningSessions->filter(function($session) use ($dateRange) {
            $sessionDate = Carbon::parse($session->start_time);
            $inDateRange = $sessionDate->between($dateRange['start'], $dateRange['end']);
    
            if ($this->selectedSubject && $inDateRange) {
                return $session->subject_id == $this->selectedSubject;
            }
    
            return $inDateRange;
        });
    
        // Get assessment submissions within date range
        $assessments = $this->child->assessmentSubmissions->filter(function($assessment) use ($dateRange) {
            $assessmentDate = Carbon::parse($assessment->created_at);
            $inDateRange = $assessmentDate->between($dateRange['start'], $dateRange['end']);
    
            if ($this->selectedSubject && $inDateRange && isset($assessment->assessment->subject_id)) {
                return $assessment->assessment->subject_id == $this->selectedSubject;
            }
    
            return $inDateRange;
        });
    
        // Process real data
        $this->calculateOverallProgress($sessions, $assessments);
        $this->calculateAttendanceRate($sessions);
        $this->prepareAssessmentScores($assessments);
        $this->prepareSubjectProgress($sessions, $assessments);
        $this->prepareSessionHistory($sessions);
        $this->prepareUpcomingSessions();
        $this->prepareRecentAssessments($assessments);
        $this->prepareProgressTrend($sessions, $assessments);
    }

    private function getDateRange()
    {
        $endDate = Carbon::now();

        switch ($this->timeRange) {
            case 'last_month':
                $startDate = Carbon::now()->subMonth();
                break;
            case 'last_3_months':
                $startDate = Carbon::now()->subMonths(3);
                break;
            case 'last_6_months':
                $startDate = Carbon::now()->subMonths(6);
                break;
            case 'last_year':
                $startDate = Carbon::now()->subYear();
                break;
            case 'custom':
                $startDate = Carbon::parse($this->customStartDate);
                $endDate = Carbon::parse($this->customEndDate);
                break;
            default:
                $startDate = Carbon::now()->subMonths(3);
        }

        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }

    private function calculateOverallProgress($sessions, $assessments)
    {
        // Calculate progress based on sessions attended and assessment scores
        $totalSessions = $sessions->count();
        $attendedSessions = $sessions->where('attended', true)->count();

        $sessionProgress = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

        $assessmentScores = $assessments->pluck('score')->filter();
        $assessmentProgress = $assessmentScores->count() > 0 ? $assessmentScores->avg() : 0;

        // Weight: 40% attendance, 60% assessment scores
        $this->overallProgress = ($sessionProgress * 0.4) + ($assessmentProgress * 0.6);
        $this->overallProgress = round(min(100, max(0, $this->overallProgress)));
    }

    private function calculateAttendanceRate($sessions)
    {
        $totalSessions = $sessions->count();
        $attendedSessions = $sessions->where('attended', true)->count();

        $this->attendanceRate = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100) : 0;
    }

    private function prepareAssessmentScores($assessments)
    {
        // Group assessments by subject
        $bySubject = $assessments->groupBy(function($assessment) {
            return $assessment->assessment->subject->name ?? 'Unknown';
        });

        $this->assessmentScores = [];

        foreach ($bySubject as $subject => $items) {
            $scores = $items->pluck('score')->filter();
            $avgScore = $scores->count() > 0 ? round($scores->avg(), 1) : 0;

            $this->assessmentScores[] = [
                'subject' => $subject,
                'average_score' => $avgScore,
                'count' => $items->count(),
                'highest' => $scores->count() > 0 ? $scores->max() : 0,
                'lowest' => $scores->count() > 0 ? $scores->min() : 0,
                'grade' => $this->getGradeFromScore($avgScore)['grade'],
                'grade_class' => $this->getGradeFromScore($avgScore)['class']
            ];
        }

        // Sort by average score (descending)
        usort($this->assessmentScores, function($a, $b) {
            return $b['average_score'] <=> $a['average_score'];
        });
    }

    private function prepareSubjectProgress($sessions, $assessments)
    {
        $this->subjectProgress = [];

        foreach ($this->child->subjects as $subject) {
            // Filter sessions for this subject
            $subjectSessions = $sessions->filter(function($session) use ($subject) {
                return $session->subject_id == $subject->id;
            });

            // Filter assessments for this subject
            $subjectAssessments = $assessments->filter(function($assessment) use ($subject) {
                return isset($assessment->assessment->subject_id) &&
                       $assessment->assessment->subject_id == $subject->id;
            });

            // Calculate metrics
            $totalSessions = $subjectSessions->count();
            $attendedSessions = $subjectSessions->where('attended', true)->count();
            $attendanceRate = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

            $scores = $subjectAssessments->pluck('score')->filter();
            $avgScore = $scores->count() > 0 ? $scores->avg() : 0;

            // Calculate overall progress for this subject
            $progress = ($attendanceRate * 0.4) + ($avgScore * 0.6);
            $progress = round(min(100, max(0, $progress)));

            $this->subjectProgress[] = [
                'id' => $subject->id,
                'name' => $subject->name,
                'progress' => $progress,
                'total_sessions' => $totalSessions,
                'attended_sessions' => $attendedSessions,
                'attendance_rate' => round($attendanceRate),
                'assessments_count' => $subjectAssessments->count(),
                'average_score' => round($avgScore, 1),
                'grade' => $this->getGradeFromScore($avgScore)['grade'],
                'grade_class' => $this->getGradeFromScore($avgScore)['class'],
                'progress_class' => $this->getProgressColor($progress)
            ];
        }

        // Sort by progress (descending)
        usort($this->subjectProgress, function($a, $b) {
            return $b['progress'] <=> $a['progress'];
        });
    }

    private function prepareProgressTrend($sessions, $assessments)
    {
        // Generate monthly progress data
        $dateRange = $this->getDateRange();
        $startMonth = $dateRange['start']->startOfMonth();
        $endMonth = $dateRange['end']->startOfMonth();

        $monthlyData = [];
        $currentMonth = $startMonth->copy();

        while ($currentMonth->lte($endMonth)) {
            $monthKey = $currentMonth->format('Y-m');
            $monthLabel = $currentMonth->format('M Y');

            $monthSessions = $sessions->filter(function($session) use ($currentMonth) {
                $sessionDate = Carbon::parse($session->start_time);
                return $sessionDate->month == $currentMonth->month &&
                       $sessionDate->year == $currentMonth->year;
            });

            $monthAssessments = $assessments->filter(function($assessment) use ($currentMonth) {
                $assessmentDate = Carbon::parse($assessment->created_at);
                return $assessmentDate->month == $currentMonth->month &&
                       $assessmentDate->year == $currentMonth->year;
            });

            // Calculate monthly progress
            $totalSessions = $monthSessions->count();
            $attendedSessions = $monthSessions->where('attended', true)->count();
            $attendanceRate = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

            $scores = $monthAssessments->pluck('score')->filter();
            $avgScore = $scores->count() > 0 ? $scores->avg() : 0;

            // Weight: 40% attendance, 60% assessment scores
            $progress = ($attendanceRate * 0.4) + ($avgScore * 0.6);
            $progress = round(min(100, max(0, $progress)));

            // If no sessions or assessments, use last month's progress or a default
            if ($totalSessions == 0 && $scores->count() == 0) {
                $progress = isset($monthlyData[$currentMonth->copy()->subMonth()->format('Y-m')])
                          ? $monthlyData[$currentMonth->copy()->subMonth()->format('Y-m')]['progress']
                          : 0;
            }

            $monthlyData[$monthKey] = [
                'month' => $monthLabel,
                'progress' => $progress,
                'attendance_rate' => round($attendanceRate),
                'average_score' => round($avgScore),
                'total_sessions' => $totalSessions,
                'total_assessments' => $scores->count()
            ];

            $currentMonth->addMonth();
        }

        $this->progressTrend = array_values($monthlyData);
    }

    private function prepareSessionHistory($sessions)
    {
        $this->sessionHistory = $sessions->sortByDesc('start_time')->values()->toArray();
    }

    private function prepareUpcomingSessions()
    {
        // Get upcoming sessions for this child
        $this->upcomingSessions = $this->child->learningSessions()
            ->where('start_time', '>', Carbon::now())
            ->where('status', 'scheduled')
            ->with(['subject', 'teacher'])
            ->orderBy('start_time', 'asc')
            ->take(5)
            ->get()
            ->toArray();
    }

    private function prepareRecentAssessments($assessments)
    {
        $this->recentAssessments = $assessments->sortByDesc('created_at')->take(5)->values()->toArray();
    }

    public function updatedTimeRange()
    {
        $this->loadProgressData();
    }

    public function updatedCustomStartDate()
    {
        if ($this->timeRange === 'custom') {
            $this->loadProgressData();
        }
    }

    public function updatedCustomEndDate()
    {
        if ($this->timeRange === 'custom') {
            $this->loadProgressData();
        }
    }

    public function updatedSelectedSubject()
    {
        $this->loadProgressData();
    }

    public function updatedActiveTab()
    {
        // Reset sub-tab when main tab changes
        $this->activeSubTab = null;
    }

    public function showSessionDetails($sessionId)
    {
        $session = collect($this->sessionHistory)->firstWhere('id', $sessionId);
        if ($session) {
            $this->selectedSession = $session;
            $this->showSessionDetailsModal = true;
        }
    }

    public function showAssessmentDetails($assessmentId)
    {
        $assessment = collect($this->recentAssessments)->firstWhere('id', $assessmentId);
        if ($assessment) {
            $this->selectedAssessment = $assessment;
            $this->showAssessmentDetailsModal = true;
        }
    }

    public function closeSessionDetailsModal()
    {
        $this->showSessionDetailsModal = false;
        $this->selectedSession = null;
    }

    public function closeAssessmentDetailsModal()
    {
        $this->showAssessmentDetailsModal = false;
        $this->selectedAssessment = null;
    }

    public function downloadProgressReport()
    {
        // In a real application, this would generate and download a PDF report
        session()->flash('message', 'Progress report download started. Your report will be ready shortly.');
    }

    public function getProgressColor($progress)
    {
        if ($progress >= 80) {
            return 'bg-success';
        } elseif ($progress >= 60) {
            return 'bg-info';
        } elseif ($progress >= 40) {
            return 'bg-warning';
        } else {
            return 'bg-error';
        }
    }

    public function getGradeFromScore($score)
    {
        if ($score >= 90) {
            return ['grade' => 'A', 'class' => 'text-success'];
        } elseif ($score >= 80) {
            return ['grade' => 'B', 'class' => 'text-success'];
        } elseif ($score >= 70) {
            return ['grade' => 'C', 'class' => 'text-warning'];
        } elseif ($score >= 60) {
            return ['grade' => 'D', 'class' => 'text-warning'];
        } else {
            return ['grade' => 'F', 'class' => 'text-error'];
        }
    }

    public function getFormattedDate($date)
    {
        return Carbon::parse($date)->format('M j, Y');
    }

    public function getFormattedTime($time)
    {
        return Carbon::parse($time)->format('g:i A');
    }

    public function getTimeRangeOptions()
    {
        return [
            'last_month' => 'Last Month',
            'last_3_months' => 'Last 3 Months',
            'last_6_months' => 'Last 6 Months',
            'last_year' => 'Last Year',
            'custom' => 'Custom Range'
        ];
    }
};?>
<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 lg:flex-row">
            <div class="flex flex-col gap-4 md:flex-row md:items-center">
                <div class="avatar">
                    <div class="w-16 h-16 rounded-full bg-primary">
                        @if($child['photo'])
                            <img src="{{ Storage::url($child['photo']) }}" alt="{{ $child['name'] }}" />
                        @else
                            <div class="flex items-center justify-center w-full h-full text-2xl font-bold text-primary-content">
                                {{ substr($child['name'], 0, 1) }}
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('parents.children.index') }}" class="text-sm hover:underline">
                            <x-icon name="o-arrow-left" class="inline w-4 h-4" />
                            Back to Children
                        </a>
                    </div>
                    <h1 class="text-3xl font-bold">{{ $child['name'] }}'s Progress Dashboard</h1>
                    <p class="text-base-content/70">Comprehensive view of learning progress and achievements</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <div class="dropdown dropdown-end">
                    <button class="btn btn-outline">
                        <x-icon name="o-document-arrow-down" class="w-5 h-5 mr-2" />
                        Export
                    </button>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                        <li>
                            <button wire:click="downloadProgressReport">
                                <x-icon name="o-document-text" class="w-4 h-4" />
                                Full Report (PDF)
                            </button>
                        </li>
                    </ul>
                </div>

                <a href="{{ route('parents.children.edit', $child['id']) }}" class="btn btn-ghost btn-sm">
                    <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                    Edit Profile
                </a>

                <a href="{{ route('parents.sessions.requests', ['child_id' => $child['id']]) }}" class="btn btn-primary">
                    <x-icon name="o-eye" class="w-4 h-4 mr-2" />
                    Schedule Session
                </a>
            </div>
        </div>

        @if(session()->has('message'))
            <div class="mb-6 alert alert-success">
                <x-icon name="o-check-circle" class="w-6 h-6" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        <!-- Filters and Controls -->
        <div class="p-4 mb-6 shadow-lg bg-base-100 rounded-xl">
            <div class="flex flex-col justify-between gap-4 lg:flex-row">
                <div class="flex flex-col gap-3 md:flex-row">
                    <select
                        wire:model.live="timeRange"
                        class="select select-bordered"
                    >
                        @foreach($this->getTimeRangeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    @if($timeRange === 'custom')
                        <div class="flex items-center gap-2">
                            <input
                                type="date"
                                wire:model.live="customStartDate"
                                class="input input-bordered"
                            />
                            <span>to</span>
                            <input
                                type="date"
                                wire:model.live="customEndDate"
                                class="input input-bordered"
                            />
                        </div>
                    @endif
                </div>

                <div class="flex flex-col gap-3 md:flex-row">
                    <select
                        wire:model.live="selectedSubject"
                        class="select select-bordered"
                    >
                        <option value="">All Subjects</option>
                        @foreach($availableSubjects as $subject)
                            <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Main Tabs -->
        <div class="mb-6 tabs tabs-boxed">
            <a
                wire:click="$set('activeTab', 'overview')"
                class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}"
            >
                Overview
            </a>
            <a
                wire:click="$set('activeTab', 'subjects')"
                class="tab {{ $activeTab === 'subjects' ? 'tab-active' : '' }}"
            >
                Subjects
            </a>
            <a
                wire:click="$set('activeTab', 'attendance')"
                class="tab {{ $activeTab === 'attendance' ? 'tab-active' : '' }}"
            >
                Attendance
            </a>
            <a
                wire:click="$set('activeTab', 'performance')"
                class="tab {{ $activeTab === 'performance' ? 'tab-active' : '' }}"
            >
                Performance
            </a>
        </div>

        <!-- Overview Tab -->
        <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
            <!-- Progress Summary Cards -->
            <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Overall Progress -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold">Overall Progress</h3>
                        <div class="tooltip" data-tip="Combined measure of attendance and assessments">
                            <x-icon name="o-information-circle" class="w-5 h-5 text-info" />
                        </div>
                    </div>

                    <div class="flex flex-col items-center">
                        <div class="radial-progress text-primary" style="--value:{{ $overallProgress }}; --size:8rem; --thickness: 0.8rem;">
                            <span class="text-2xl font-bold">{{ $overallProgress }}%</span>
                        </div>
                        <div class="mt-4 text-sm">
                            <span class="font-medium">Status:</span>
                            @if($overallProgress >= 80)
                                <span class="text-success">Excellent</span>
                            @elseif($overallProgress >= 60)
                                <span class="text-info">Good</span>
                            @elseif($overallProgress >= 40)
                                <span class="text-warning">Needs Improvement</span>
                            @else
                                <span class="text-error">Requires Attention</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Attendance Rate -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold">Attendance Rate</h3>
                        <div class="tooltip" data-tip="Percentage of scheduled sessions attended">
                            <x-icon name="o-information-circle" class="w-5 h-5 text-info" />
                        </div>
                    </div>

                    <div class="flex flex-col items-center">
                        <div class="radial-progress text-secondary" style="--value:{{ $attendanceRate }}; --size:8rem; --thickness: 0.8rem;">
                            <span class="text-2xl font-bold">{{ $attendanceRate }}%</span>
                        </div>
                        <div class="mt-4 text-sm">
                            <span class="font-medium">Rate:</span>
                            @if($attendanceRate >= 90)
                                <span class="text-success">Excellent</span>
                            @elseif($attendanceRate >= 75)
                                <span class="text-info">Good</span>
                            @elseif($attendanceRate >= 60)
                                <span class="text-warning">Needs Improvement</span>
                            @else
                                <span class="text-error">Requires Attention</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <h3 class="mb-4 text-lg font-bold">Recent Activity</h3>

                    <div class="space-y-3">
                        @if(count($upcomingSessions) > 0)
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-full bg-primary/20">
                                    <x-icon name="o-calendar" class="w-4 h-4 text-primary" />
                                </div>
                                <div class="text-sm">
                                    <span class="font-medium">Next Session:</span>
                                    <div class="text-xs">
                                        {{ $this->getFormattedDate($upcomingSessions[0]['start_time']) }}
                                        ({{ $upcomingSessions[0]['subject']['name'] ?? 'Unknown Subject' }})
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(count($recentAssessments) > 0)
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-full bg-secondary/20">
                                    <x-icon name="o-clipboard-document-check" class="w-4 h-4 text-secondary" />
                                </div>
                                <div class="text-sm">
                                    <span class="font-medium">Latest Assessment:</span>
                                    <div class="text-xs">
                                        {{ $this->getFormattedDate($recentAssessments[0]['created_at']) }}
                                        ({{ $recentAssessments[0]['score'] }}%)
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(count($progressTrend) > 0)
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-full bg-success/20">
                                    <x-icon name="o-chart-bar" class="w-4 h-4 text-success" />
                                </div>
                                <div class="text-sm">
                                    <span class="font-medium">Progress Trend:</span>
                                    @php
                                        $lastMonth = $progressTrend[count($progressTrend) - 1];
                                        $previousMonth = count($progressTrend) >= 2 ? $progressTrend[count($progressTrend) - 2] : null;
                                        $change = $previousMonth ? $lastMonth['progress'] - $previousMonth['progress'] : 0;
                                    @endphp
                                    <div class="text-xs {{ $change >= 0 ? 'text-success' : 'text-error' }}">
                                        {{ $change >= 0 ? '+' : '' }}{{ $change }}% from previous month
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <a href="{{ route('parents.children.show', $child['id']) }}" class="mt-4 btn btn-ghost btn-sm btn-block">
                        View Child Profile
                    </a>
                </div>
            </div>

            <!-- Progress Over Time Chart -->
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-lg font-bold">Progress Over Time</h3>

                @if(count($progressTrend) > 0)
                    <div class="h-64">
                        <!-- This would typically be a chart component -->
                        <div class="flex items-end h-full">
                            @foreach($progressTrend as $item)
                                <div class="flex flex-col items-center flex-1">
                                    <div class="mb-1 text-xs">{{ $item['progress'] }}%</div>
                                    <div class="w-full max-w-[30px] bg-primary rounded-t" style="height: {{ $item['progress'] }}%;"></div>
                                    <div class="w-full mt-2 text-xs text-center truncate">{{ $item['month'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No progress data available for the selected time period</p>
                    </div>
                @endif
            </div>

            <!-- Subject Progress -->
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold">Subject Progress</h3>
                    <button
                        wire:click="$set('activeTab', 'subjects')"
                        class="btn btn-ghost btn-sm"
                    >
                        View All Subjects
                    </button>
                </div>

                @if(count($subjectProgress) > 0)
                    <div class="space-y-4">
                        @foreach(array_slice($subjectProgress, 0, 4) as $subject)
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="font-medium">{{ $subject['name'] }}</span>
                                    <div class="flex items-center gap-2">
                                        <span>{{ $subject['progress'] }}%</span>
                                        <span class="badge {{ $subject['grade_class'] }}">{{ $subject['grade'] }}</span>
                                    </div>
                                </div>
                                <div class="w-full h-3 overflow-hidden rounded-full bg-base-300">
                                    <div class="h-full {{ $subject['progress_class'] }}" style="width: {{ $subject['progress'] }}%"></div>
                                </div>
                                <div class="flex justify-between mt-1 text-xs opacity-70">
                                    <span>Sessions: {{ $subject['total_sessions'] }}</span>
                                    <span>Attendance: {{ $subject['attendance_rate'] }}%</span>
                                    <span>Avg. Score: {{ $subject['average_score'] }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No subject data available for the selected time period</p>
                    </div>
                @endif
            </div>

            <!-- Recent Sessions -->
            <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold">Recent Sessions</h3>
                    <a href="{{ route('parents.sessions.index', ['child_id' => $child['id']]) }}" class="btn btn-ghost btn-sm">View All</a>
                </div>

                @if(count($sessionHistory) > 0)
                    <div class="overflow-x-auto">
                        <table class="table w-full table-zebra">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(array_slice($sessionHistory, 0, 5) as $session)
                                    <tr>
                                        <td>{{ $this->getFormattedDate($session['start_time']) }}</td>
                                        <td>{{ $session['subject']['name'] ?? 'Unknown' }}</td>
                                        <td>
                                            <div class="badge {{
                                                $session['status'] === 'completed' ? 'badge-success' :
                                                ($session['status'] === 'scheduled' ? 'badge-info' : 'badge-error')
                                            }}">
                                                {{ ucfirst($session['status']) }}
                                            </div>
                                        </td>
                                        <td>
                                            <button
                                                wire:click="showSessionDetails({{ $session['id'] }})"
                                                class="btn btn-ghost btn-xs"
                                            >
                                                <x-icon name="o-eye" class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No session data available for the selected time period</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Subjects Tab -->
        <div class="{{ $activeTab === 'subjects' ? 'block' : 'hidden' }}">
            <!-- Subjects Grid -->
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-xl font-bold">Subject Performance</h3>

                @if(count($subjectProgress) > 0)
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($subjectProgress as $subject)
                            <div class="p-6 transition-shadow border rounded-xl hover:shadow-lg">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-bold">{{ $subject['name'] }}</h4>
                                    <div class="badge {{ $subject['grade_class'] }}">{{ $subject['grade'] }}</div>
                                </div>

                                <div class="mb-4 text-center">
                                    <div class="mx-auto radial-progress text-primary" style="--value:{{ $subject['progress'] }}; --size:7rem; --thickness: 0.7rem;">
                                        <span class="text-xl font-bold">{{ $subject['progress'] }}%</span>
                                    </div>
                                </div>

                                <div class="mb-4 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm opacity-70">Sessions Attended:</span>
                                        <span class="font-medium">{{ $subject['attended_sessions'] }}/{{ $subject['total_sessions'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm opacity-70">Attendance Rate:</span>
                                        <span class="font-medium">{{ $subject['attendance_rate'] }}%</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm opacity-70">Assessments:</span>
                                        <span class="font-medium">{{ $subject['assessments_count'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm opacity-70">Average Score:</span>
                                        <span class="font-medium">{{ $subject['average_score'] }}%</span>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        wire:click="$set('selectedSubject', {{ $subject['id'] }})"
                                        class="btn btn-outline btn-sm"
                                    >
                                        Filter by Subject
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No subject data available for the selected time period</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Attendance Tab -->
        <div class="{{ $activeTab === 'attendance' ? 'block' : 'hidden' }}">
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-xl font-bold">Attendance Overview</h3>

                <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2">
                    <div class="shadow stats">
                        <div class="stat">
                            <div class="stat-title">Attendance Rate</div>
                            <div class="stat-value text-primary">{{ $attendanceRate }}%</div>
                            <div class="stat-desc">Overall attendance for selected period</div>
                        </div>
                    </div>

                    <div class="shadow stats">
                        <div class="stat">
                            <div class="stat-title">Sessions Attended</div>
                            <div class="stat-value text-success">
                                @php
                                    $totalSessions = count($sessionHistory);
                                    $attendedSessions = collect($sessionHistory)->where('attended', true)->count();
                                @endphp
                                {{ $attendedSessions }}/{{ $totalSessions }}
                            </div>
                            <div class="stat-desc">Sessions in selected period</div>
                        </div>
                    </div>
                </div>

                <!-- Session History -->
                <div>
                    <h4 class="mb-4 font-medium">Session History</h4>

                    @if(count($sessionHistory) > 0)
                        <div class="overflow-x-auto">
                            <table class="table w-full table-zebra">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Status</th>
                                        <th>Attended</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sessionHistory as $session)
                                        <tr>
                                            <td>
                                                <div>{{ $this->getFormattedDate($session['start_time']) }}</div>
                                                <div class="text-xs opacity-70">
                                                    {{ $this->getFormattedTime($session['start_time']) }} -
                                                    {{ $this->getFormattedTime($session['end_time']) }}
                                                </div>
                                            </td>
                                            <td>{{ $session['subject']['name'] ?? 'Unknown' }}</td>
                                            <td>{{ $session['teacher']['name'] ?? 'Unknown' }}</td>
                                            <td>
                                                <div class="badge {{
                                                    $session['status'] === 'completed' ? 'badge-success' :
                                                    ($session['status'] === 'scheduled' ? 'badge-info' : 'badge-error')
                                                }}">
                                                    {{ ucfirst($session['status']) }}
                                                </div>
                                            </td>
                                            <td>
                                                @if($session['status'] === 'completed')
                                                    <div class="{{ $session['attended'] ? 'text-success' : 'text-error' }}">
                                                        {{ $session['attended'] ? 'Yes' : 'No' }}
                                                    </div>
                                                @else
                                                    <div class="opacity-50">N/A</div>
                                                @endif
                                            </td>
                                            <td>
                                                <button
                                                    wire:click="showSessionDetails({{ $session['id'] }})"
                                                    class="btn btn-ghost btn-xs"
                                                >
                                                    <x-icon name="o-eye" class="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No session data available for the selected time period</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Performance Tab -->
        <div class="{{ $activeTab === 'performance' ? 'block' : 'hidden' }}">
            <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-xl font-bold">Assessment Performance</h3>

                @if(count($recentAssessments) > 0)
                    <!-- Recent Assessments Table -->
                    <div>
                        <h4 class="mb-4 font-medium">Recent Assessments</h4>

                        <div class="overflow-x-auto">
                            <table class="table w-full">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Title</th>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentAssessments as $assessment)
                                        <tr>
                                            <td>{{ $this->getFormattedDate($assessment['created_at']) }}</td>
                                            <td>{{ $assessment['assessment']['title'] ?? 'Unknown Assessment' }}</td>
                                            <td>{{ $assessment['assessment']['subject']['name'] ?? 'Unknown Subject' }}</td>
                                            <td>
                                                @php $grade = $this->getGradeFromScore($assessment['score']); @endphp
                                                <div class="flex items-center gap-2">
                                                    <span>{{ $assessment['score'] }}%</span>
                                                    <span class="badge {{ $grade['class'] }}">{{ $grade['grade'] }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <button
                                                    wire:click="showAssessmentDetails({{ $assessment['id'] }})"
                                                    class="btn btn-ghost btn-xs"
                                                >
                                                    <x-icon name="o-eye" class="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No assessment data available for the selected time period</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Session Details Modal -->
    <div class="modal {{ $showSessionDetailsModal ? 'modal-open' : '' }}">
        <div class="max-w-2xl modal-box">
            @if($selectedSession)
                <div class="flex items-start justify-between">
                    <h3 class="text-lg font-bold">Session Details</h3>
                    <button wire:click="closeSessionDetailsModal" class="btn btn-sm btn-circle">
                        <x-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="divider"></div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <h4 class="mb-2 font-semibold">Session Information</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Subject:</span>
                                <span class="font-medium">{{ $selectedSession['subject']['name'] ?? 'Unknown Subject' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Date:</span>
                                <span>{{ $this->getFormattedDate($selectedSession['start_time']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Time:</span>
                                <span>{{ $this->getFormattedTime($selectedSession['start_time']) }} - {{ $this->getFormattedTime($selectedSession['end_time']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Teacher:</span>
                                <span>{{ $selectedSession['teacher']['name'] ?? 'Unknown Teacher' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Status:</span>
                                <div class="badge {{
                                    $selectedSession['status'] === 'scheduled' ? 'badge-primary' :
                                    ($selectedSession['status'] === 'completed' ? 'badge-success' : 'badge-error')
                                }}">
                                    {{ ucfirst($selectedSession['status']) }}
                                </div>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Attended:</span>
                                <span class="{{ $selectedSession['attended'] ? 'text-success' : 'text-error' }}">
                                    {{ $selectedSession['attended'] ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 font-semibold">Notes</h4>
                        <div class="p-3 rounded-lg bg-base-200">
                            {{ $selectedSession['notes'] ?? 'No notes provided for this session.' }}
                        </div>
                    </div>
                </div>

                <div class="modal-action">
                    <button wire:click="closeSessionDetailsModal" class="btn">Close</button>
                </div>
            @endif
        </div>
        <div class="modal-backdrop" wire:click="closeSessionDetailsModal"></div>
    </div>

    <!-- Assessment Details Modal -->
    <div class="modal {{ $showAssessmentDetailsModal ? 'modal-open' : '' }}">
        <div class="max-w-2xl modal-box">
            @if($selectedAssessment)
                <div class="flex items-start justify-between">
                    <h3 class="text-lg font-bold">Assessment Details</h3>
                    <button wire:click="closeAssessmentDetailsModal" class="btn btn-sm btn-circle">
                        <x-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="divider"></div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <h4 class="mb-2 font-semibold">Assessment Information</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Title:</span>
                                <span class="font-medium">{{ $selectedAssessment['assessment']['title'] ?? 'Unknown Assessment' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Subject:</span>
                                <span>{{ $selectedAssessment['assessment']['subject']['name'] ?? 'Unknown Subject' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Date:</span>
                                <span>{{ $this->getFormattedDate($selectedAssessment['created_at']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Type:</span>
                                <span>{{ ucfirst($selectedAssessment['assessment']['type'] ?? 'Unknown') }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 font-semibold">Results</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Score:</span>
                                <span class="font-medium">{{ $selectedAssessment['score'] }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Grade:</span>
                                @php $grade = $this->getGradeFromScore($selectedAssessment['score']); @endphp
                                <span class="font-medium {{ $grade['class'] }}">{{ $grade['grade'] }}</span>
                            </div>
                        </div>

                        <h4 class="mt-6 mb-2 font-semibold">Feedback</h4>
                        <div class="p-3 bg-base-200 rounded-lg min-h-[80px]">
                            {{ $selectedAssessment['feedback'] ?? 'No feedback provided for this assessment.' }}
                        </div>
                    </div>
                </div>

                <div class="modal-action">
                    <button wire:click="closeAssessmentDetailsModal" class="btn">Close</button>
                </div>
            @endif
        </div>
        <div class="modal-backdrop" wire:click="closeAssessmentDetailsModal"></div>
    </div>
</div>