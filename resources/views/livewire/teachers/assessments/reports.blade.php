<?php
// resources/views/livewire/teachers/assessments/reports.blade.php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    // User and profile
    public $teacher;
    public $teacherProfile;

    // Filters
    public $timeframeFilter = 'all';
    public $courseFilter = '';
    public $subjectFilter = '';
    public $typeFilter = '';
    public $participantFilter = '';

    // Visualization options
    public $chartType = 'performance';
    public $showAllAssessments = false;

    // Data
    public $courses = [];
    public $subjects = [];
    public $assessmentTypes = [];

    // Cached data (for performance)
    public $assessments = [];
    public $recentSubmissions = [];
    public $topPerformers = [];
    public $needsAttention = [];
    public $assessmentStats = [];
    public $performanceOverview = [];
    public $subjectPerformance = [];

    // Excel Export fields
    public $exportReportTitle = '';
    public $exportIncludeParticipants = true;
    public $exportIncludeQuestions = true;
    public $exportIncludeAnswers = false;
    public $showExportModal = false;

    protected $queryString = [
        'timeframeFilter' => ['except' => 'all'],
        'courseFilter' => ['except' => ''],
        'subjectFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'participantFilter' => ['except' => ''],
        'chartType' => ['except' => 'performance'],
    ];

    public function mount()
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        // Load lists for filters
        $this->courses = Course::where('teacher_profile_id', $this->teacherProfile->id)
            ->orWhere('status', 'active')
            ->orderBy('title')
            ->get();

        $this->subjects = Subject::orderBy('name')->get();
        $this->assessmentTypes = Assessment::$types ?? [
            'quiz' => 'Quiz',
            'test' => 'Test',
            'exam' => 'Exam',
            'assignment' => 'Assignment',
            'project' => 'Project',
            'essay' => 'Essay',
            'presentation' => 'Presentation',
            'other' => 'Other',
        ];

        // Load data
        $this->loadReportData();
    }

    public function loadReportData()
    {
        // Get teacher assessments with submissions
        $query = Assessment::query()
            ->where('teacher_profile_id', $this->teacherProfile->id)
            ->with([
                'submissions' => function($q) {
                    $q->whereIn('status', ['completed', 'graded'])
                      ->with(['children', 'client'])
                      ->orderBy('end_time', 'desc');
                },
                'questions',
                'course',
                'subject'
            ]);

        // Apply filters
        if ($this->timeframeFilter !== 'all') {
            // Get the start date based on timeframe
            $startDate = match($this->timeframeFilter) {
                'last7days' => now()->subDays(7),
                'last30days' => now()->subDays(30),
                'last3months' => now()->subMonths(3),
                'last6months' => now()->subMonths(6),
                'thisyear' => now()->startOfYear(),
                default => null
            };

            if ($startDate) {
                $query->whereHas('submissions', function($q) use ($startDate) {
                    $q->where('end_time', '>=', $startDate);
                });
            }
        }

        if ($this->courseFilter) {
            $query->where('course_id', $this->courseFilter);
        }

        if ($this->subjectFilter) {
            $query->where('subject_id', $this->subjectFilter);
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->participantFilter) {
            if ($this->participantFilter === 'children') {
                $query->whereHas('submissions', function($q) {
                    $q->whereNotNull('children_id');
                });
            } else if ($this->participantFilter === 'clients') {
                $query->whereHas('submissions', function($q) {
                    $q->whereNotNull('client_profile_id');
                });
            }
        }

        // Limit to just a few assessments for performance overview
        $assessments = $query->orderBy('created_at', 'desc')->get();
        $this->assessments = $assessments;

        // Generate statistics
        $this->calculateStatistics($assessments);
    }

    public function calculateStatistics($assessments)
    {
        // 1. Recent submissions
        $this->recentSubmissions = $assessments
            ->flatMap->submissions
            ->sortByDesc('end_time')
            ->take(5);

        // 2. Assessment stats
        $totalAssessments = $assessments->count();
        $totalSubmissions = $assessments->flatMap->submissions->count();
        $completedSubmissions = $assessments->flatMap->submissions->whereIn('status', ['completed', 'graded'])->count();
        $averageScore = $assessments->flatMap->submissions->whereIn('status', ['completed', 'graded'])->avg('score');
        $averageQuestions = $assessments->avg(function($assessment) {
            return $assessment->questions->count();
        });

        $this->assessmentStats = [
            'total_assessments' => $totalAssessments,
            'total_submissions' => $totalSubmissions,
            'completion_rate' => $totalSubmissions > 0 ? round(($completedSubmissions / $totalSubmissions) * 100, 1) : 0,
            'average_score' => round($averageScore, 1),
            'average_questions' => round($averageQuestions),
        ];

        // 3. Performance overview (for chart)
        $performanceData = [];

        foreach ($assessments as $assessment) {
            // Skip assessments with no submissions
            if ($assessment->submissions->whereIn('status', ['completed', 'graded'])->isEmpty()) {
                continue;
            }

            $totalPoints = $assessment->total_points ?: 100;
            $averageScore = $assessment->submissions->whereIn('status', ['completed', 'graded'])->avg('score');
            $percentageScore = $totalPoints > 0 ? ($averageScore / $totalPoints) * 100 : 0;

            $performanceData[] = [
                'id' => $assessment->id,
                'title' => Str::limit($assessment->title, 30),
                'average_score' => round($percentageScore, 1),
                'submissions_count' => $assessment->submissions->whereIn('status', ['completed', 'graded'])->count(),
                'passing_rate' => $assessment->passing_points
                    ? round($assessment->submissions->whereIn('status', ['completed', 'graded'])
                        ->filter(fn($s) => $s->score >= $assessment->passing_points)
                        ->count() / max(1, $assessment->submissions->whereIn('status', ['completed', 'graded'])->count()) * 100, 1)
                    : null,
                'type' => $assessment->type,
            ];
        }

        // Sort by average score
        $performanceData = collect($performanceData)->sortByDesc('average_score')->values()->all();
        $this->performanceOverview = array_slice($performanceData, 0, $this->showAllAssessments ? count($performanceData) : 10);

        // 4. Subject performance
        $subjectPerformance = [];
        $subjectSubmissions = [];

        foreach ($assessments as $assessment) {
            $subjectId = $assessment->subject_id;
            $subjectName = $assessment->subject ? $assessment->subject->name : 'No Subject';

            if (!isset($subjectPerformance[$subjectId])) {
                $subjectPerformance[$subjectId] = [
                    'name' => $subjectName,
                    'total_score' => 0,
                    'total_submissions' => 0,
                ];
            }

            $completedSubmissions = $assessment->submissions->whereIn('status', ['completed', 'graded']);
            $totalScore = $completedSubmissions->sum('score');
            $submissionCount = $completedSubmissions->count();

            $subjectPerformance[$subjectId]['total_score'] += $totalScore;
            $subjectPerformance[$subjectId]['total_submissions'] += $submissionCount;
        }

        // Calculate averages
        foreach ($subjectPerformance as $id => $data) {
            if ($data['total_submissions'] > 0) {
                $subjectPerformance[$id]['average_score'] = round($data['total_score'] / $data['total_submissions'], 1);
            } else {
                $subjectPerformance[$id]['average_score'] = 0;
            }
        }

        $this->subjectPerformance = collect($subjectPerformance)->sortByDesc('average_score')->values()->all();

        // 5. Top performers
        $participants = collect();

        foreach ($assessments as $assessment) {
            foreach ($assessment->submissions->whereIn('status', ['completed', 'graded']) as $submission) {
                $participantId = $submission->children_id ?? ('client_' . $submission->client_profile_id);
                $participantName = $submission->children_id
                    ? ($submission->children->name ?? 'Unknown')
                    : ($submission->client->company_name ?? $submission->client->user->name ?? 'Unknown');
                $participantType = $submission->children_id ? 'student' : 'client';

                if (!$participants->has($participantId)) {
                    $participants[$participantId] = [
                        'id' => $participantId,
                        'name' => $participantName,
                        'type' => $participantType,
                        'total_score' => 0,
                        'total_possible' => 0,
                        'submissions_count' => 0,
                    ];
                }

                $participants[$participantId]['total_score'] += $submission->score ?? 0;
                $participants[$participantId]['total_possible'] += $assessment->total_points;
                $participants[$participantId]['submissions_count'] += 1;
            }
        }

        // Calculate percentage scores and sort
        $participants = $participants->map(function($participant) {
            $participant['average_percentage'] = $participant['total_possible'] > 0
                ? round(($participant['total_score'] / $participant['total_possible']) * 100, 1)
                : 0;
            return $participant;
        })->sortByDesc('average_percentage');

        $this->topPerformers = $participants->take(5)->values()->all();

        // 6. Participants needing attention (lowest scores)
        $this->needsAttention = $participants
            ->filter(fn($p) => $p['submissions_count'] > 0)
            ->sortBy('average_percentage')
            ->take(5)
            ->values()
            ->all();
    }

    // Update handlers for filters
    public function updatedTimeframeFilter() { $this->loadReportData(); }
    public function updatedCourseFilter() { $this->loadReportData(); }
    public function updatedSubjectFilter() { $this->loadReportData(); }
    public function updatedTypeFilter() { $this->loadReportData(); }
    public function updatedParticipantFilter() { $this->loadReportData(); }
    public function updatedShowAllAssessments() { $this->loadReportData(); }

    // Toggle showing all assessments in performance chart
    public function toggleShowAllAssessments()
    {
        $this->showAllAssessments = !$this->showAllAssessments;
        $this->loadReportData();
    }

    // Export handlers
    public function showExportModal()
    {
        $this->showExportModal = true;
        $this->exportReportTitle = 'Assessment Performance Report - ' . now()->format('Y-m-d');
    }

    public function exportReport()
    {
        // This would normally connect to a real export process
        // For now, just display a success message

        $this->showExportModal = false;

        $this->js("
            Toaster.success('Report export started', {
                description: 'Your report will be ready for download shortly.',
                position: 'toast-bottom toast-end',
                icon: 'o-arrow-down-tray',
                css: 'alert-success',
                timeout: 3000
            });
        ");

        // In a real app, you'd initiate a job or direct download here
    }

    public function clearFilters()
    {
        $this->reset([
            'timeframeFilter',
            'courseFilter',
            'subjectFilter',
            'typeFilter',
            'participantFilter'
        ]);

        $this->loadReportData();
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col justify-between gap-4 mb-6 md:flex-row md:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('teachers.assessments.index') }}" class="btn btn-circle btn-sm btn-ghost">
                        <x-icon name="o-arrow-left" class="w-5 h-5" />
                    </a>
                    <h1 class="text-3xl font-bold">Assessment Reports</h1>
                </div>
                <p class="mt-1 text-base-content/70">Analytics and performance insights for your assessments</p>
            </div>

            <div class="flex gap-2">
                <button
                    wire:click="showExportModal"
                    class="btn btn-outline"
                >
                    <x-icon name="o-arrow-down-tray" class="w-5 h-5 mr-2" />
                    Export Report
                </button>

                <a href="{{ route('teachers.assessments.create') }}" class="btn btn-primary">
                    <x-icon name="o-plus" class="w-5 h-5 mr-2" />
                    Create Assessment
                </a>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="p-4 mb-6 shadow-lg bg-base-100 rounded-xl">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <!-- Timeframe Filter -->
                <div>
                    <label class="label">
                        <span class="label-text">Timeframe</span>
                    </label>
                    <select wire:model.live="timeframeFilter" class="w-full select select-bordered">
                        <option value="all">All Time</option>
                        <option value="last7days">Last 7 Days</option>
                        <option value="last30days">Last 30 Days</option>
                        <option value="last3months">Last 3 Months</option>
                        <option value="last6months">Last 6 Months</option>
                        <option value="thisyear">This Year</option>
                    </select>
                </div>

                <!-- Course Filter -->
                <div>
                    <label class="label">
                        <span class="label-text">Course</span>
                    </label>
                    <select wire:model.live="courseFilter" class="w-full select select-bordered">
                        <option value="">All Courses</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->title }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Subject Filter -->
                <div>
                    <label class="label">
                        <span class="label-text">Subject</span>
                    </label>
                    <select wire:model.live="subjectFilter" class="w-full select select-bordered">
                        <option value="">All Subjects</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Assessment Type Filter -->
                <div>
                    <label class="label">
                        <span class="label-text">Assessment Type</span>
                    </label>
                    <select wire:model.live="typeFilter" class="w-full select select-bordered">
                        <option value="">All Types</option>
                        @foreach($assessmentTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Participant Type Filter -->
                <div>
                    <label class="label">
                        <span class="label-text">Participant Type</span>
                    </label>
                    <select wire:model.live="participantFilter" class="w-full select select-bordered">
                        <option value="">All Participants</option>
                        <option value="children">Students Only</option>
                        <option value="clients">Clients Only</option>
                    </select>
                </div>
            </div>

            <!-- Clear Filters Button (if any filters applied) -->
            @if($timeframeFilter !== 'all' || $courseFilter || $subjectFilter || $typeFilter || $participantFilter)
                <div class="flex justify-end mt-3">
                    <button wire:click="clearFilters" class="btn btn-sm btn-ghost">
                        <x-icon name="o-x-mark" class="w-4 h-4 mr-1" />
                        Clear Filters
                    </button>
                </div>
            @endif
        </div>

        <!-- Overview Stats Cards -->
        <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-5">
            <div class="flex items-center gap-4 p-6 shadow-lg bg-base-100 rounded-xl">
                <div class="p-3 rounded-full bg-primary/20">
                    <x-icon name="o-document-text" class="w-8 h-8 text-primary" />
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $assessmentStats['total_assessments'] }}</div>
                    <div class="text-sm opacity-70">Assessments</div>
                </div>
            </div>

            <div class="flex items-center gap-4 p-6 shadow-lg bg-base-100 rounded-xl">
                <div class="p-3 rounded-full bg-secondary/20">
                    <x-icon name="o-clipboard-document-check" class="w-8 h-8 text-secondary" />
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $assessmentStats['total_submissions'] }}</div>
                    <div class="text-sm opacity-70">Submissions</div>
                </div>
            </div>

            <div class="flex items-center gap-4 p-6 shadow-lg bg-base-100 rounded-xl">
                <div class="p-3 rounded-full bg-accent/20">
                    <x-icon name="o-check-circle" class="w-8 h-8 text-accent" />
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $assessmentStats['completion_rate'] }}%</div>
                    <div class="text-sm opacity-70">Completion Rate</div>
                </div>
            </div>

            <div class="flex items-center gap-4 p-6 shadow-lg bg-base-100 rounded-xl">
                <div class="p-3 rounded-full bg-success/20">
                    <x-icon name="o-trophy" class="w-8 h-8 text-success" />
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $assessmentStats['average_score'] }}</div>
                    <div class="text-sm opacity-70">Avg. Score</div>
                </div>
            </div>

            <div class="flex items-center gap-4 p-6 shadow-lg bg-base-100 rounded-xl">
                <div class="p-3 rounded-full bg-info/20">
                    <x-icon name="o-question-mark-circle" class="w-8 h-8 text-info" />
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $assessmentStats['average_questions'] }}</div>
                    <div class="text-sm opacity-70">Avg. Questions</div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column (Performance Charts) -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Assessment Performance Chart -->
                <div class="p-6 shadow-xl bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold">Assessment Performance</h2>
                        <div class="flex gap-2">
                            <div class="btn-group">
                                <button class="btn btn-sm {{ $chartType === 'performance' ? 'btn-active' : '' }}" wire:click="$set('chartType', 'performance')">
                                    <x-icon name="o-chart-bar" class="w-4 h-4 mr-1" />
                                    Performance
                                </button>
                                <button class="btn btn-sm {{ $chartType === 'completion' ? 'btn-active' : '' }}" wire:click="$set('chartType', 'completion')">
                                    <x-icon name="o-check-circle" class="w-4 h-4 mr-1" />
                                    Completion
                                </button>
                            </div>

                            @if(count($performanceOverview) >= 10)
                                <button class="btn btn-sm btn-outline" wire:click="toggleShowAllAssessments">
                                    {{ $showAllAssessments ? 'Show Less' : 'Show All' }}
                                </button>
                            @endif
                        </div>
                    </div>

                    @if(count($performanceOverview) > 0)
                        <div class="h-96">
                            @if($chartType === 'performance')
                                <!-- Horizontal Bar Chart for Average Scores -->
                                <div class="w-full h-full pr-4 overflow-y-auto">
                                    <div class="space-y-3 min-h-[600px]">
                                        @foreach($performanceOverview as $assessment)
                                            <div>
                                                <div class="flex items-center justify-between mb-1">
                                                    <div class="tooltip" data-tip="{{ $assessment['title'] }}">
                                                        <span class="inline-block max-w-xs text-sm truncate">{{ $assessment['title'] }}</span>
                                                    </div>
                                                    <span class="text-sm font-medium">{{ $assessment['average_score'] }}%</span>
                                                </div>
                                                <div class="w-full h-4 rounded-full bg-base-300">
                                                    <div class="h-full rounded-full {{
                                                        $assessment['average_score'] >= 80 ? 'bg-success' :
                                                        ($assessment['average_score'] >= 60 ? 'bg-warning' : 'bg-error')
                                                    }}" style="width: {{ $assessment['average_score'] }}%"></div>
                                                </div>
                                                <div class="flex justify-between mt-1 text-xs opacity-70">
                                                    <span>{{ $assessment['submissions_count'] }} submissions</span>
                                                    @if($assessment['passing_rate'] !== null)
                                                        <span>{{ $assessment['passing_rate'] }}% passing rate</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <!-- Horizontal Bar Chart for Completion Rates -->
                                <div class="w-full h-full pr-4 overflow-y-auto">
                                    <div class="space-y-3 min-h-[600px]">
                                        @foreach($performanceOverview as $assessment)
                                            <div>
                                                <div class="flex items-center justify-between mb-1">
                                                    <div class="tooltip" data-tip="{{ $assessment['title'] }}">
                                                        <span class="inline-block max-w-xs text-sm truncate">{{ $assessment['title'] }}</span>
                                                    </div>
                                                    <span class="text-sm font-medium">{{ $assessment['submissions_count'] }} submissions</span>
                                                </div>
                                                @if($assessment['passing_rate'] !== null)
                                                    <div class="w-full h-4 rounded-full bg-base-300">
                                                        <div class="h-full rounded-full {{
                                                            $assessment['passing_rate'] >= 80 ? 'bg-success' :
                                                            ($assessment['passing_rate'] >= 60 ? 'bg-warning' : 'bg-error')
                                                        }}" style="width: {{ $assessment['passing_rate'] }}%"></div>
                                                    </div>
                                                    <div class="flex justify-between mt-1 text-xs opacity-70">
                                                        <span>Passing rate</span>
                                                        <span>{{ $assessment['passing_rate'] }}%</span>
                                                    </div>
                                                @else
                                                    <div class="w-full h-4 rounded-full bg-base-300">
                                                        <div class="h-full rounded-full bg-info" style="width: 100%"></div>
                                                    </div>
                                                    <div class="flex justify-between mt-1 text-xs opacity-70">
                                                        <span>No passing score set</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center rounded-lg h-80 bg-base-200">
                            <x-icon name="o-chart-bar" class="w-16 h-16 text-base-content/20" />
                            <h3 class="mt-4 text-lg font-medium">No performance data available</h3>
                            <p class="mt-1 text-base-content/70">
                                Create assessments and collect submissions to see performance analytics.
                            </p>
                        </div>
                    @endif
                </div>

                <!-- Subject Performance Chart -->
                <div class="p-6 shadow-xl bg-base-100 rounded-xl">
                    <h2 class="mb-6 text-xl font-bold">Subject Performance</h2>

                    @if(count($subjectPerformance) > 0)
                        <div class="space-y-5">
                            @foreach($subjectPerformance as $subject)
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium">{{ $subject['name'] }}</span>
                                        <span class="text-sm">{{ $subject['average_score'] }} avg. score</span>
                                    </div>
                                    <div class="w-full h-3 rounded-full bg-base-300">
                                        <div class="h-full rounded-full bg-primary" style="width: {{ min(100, $subject['average_score']) }}%"></div>
                                    </div>
                                    <div class="mt-1 text-xs text-base-content/70">
                                        {{ $subject['total_submissions'] }} submissions
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-40 rounded-lg bg-base-200">
                            <p class="text-base-content/70">No subject performance data available.</p>
                        </div>
                    @endif
                </div>

                <!-- Recent Submissions -->
                <div class="p-6 shadow-xl bg-base-100 rounded-xl">
                    <h2 class="mb-6 text-xl font-bold">Recent Submissions</h2>

                    @if(count($recentSubmissions) > 0)
                        <div class="overflow-x-auto">
                            <table class="table w-full table-zebra">
                                <thead>
                                    <tr>
                                        <th>Assessment</th>
                                        <th>Participant</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentSubmissions as $submission)
                                        <tr>
                                            <td>
                                                <div class="font-medium">{{ Str::limit($submission->assessment->title, 30) }}</div>
                                                <div class="text-xs opacity-60">{{ $assessmentTypes[$submission->assessment->type] ?? 'Assessment' }}</div>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-2">
                                                    <div class="avatar placeholder">
                                                        <div class="w-8 rounded-full bg-neutral-focus text-neutral-content">
                                                            <span>{{ substr($submission->getParticipantNameAttribute(), 0, 1) }}</span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium">{{ $submission->getParticipantNameAttribute() }}</div>
                                                        <div class="text-xs opacity-60">
                                                            {{ $submission->children_id ? 'Student' : 'Client' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-1">
                                                    <div class="radial-progress text-primary" style="--value:{{ min(100, round(($submission->score / $submission->assessment->total_points) * 100)) }}; --size: 2rem;">
                                                        <span class="text-xs">{{ round(($submission->score / $submission->assessment->total_points) * 100) }}%</span>
                                                    </div>
                                                    <div class="text-sm">
                                                        {{ $submission->score }}/{{ $submission->assessment->total_points }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-sm">{{ $submission->end_time->format('M d, Y') }}</div>
                                                <div class="text-xs opacity-60">{{ $submission->end_time->format('h:i A') }}</div>
                                            </td>
                                            <td>
                                                <a href="{{ route('teachers.assessment-submissions.show', $submission->id) }}" class="btn btn-ghost btn-sm">
                                                    <x-icon name="o-eye" class="w-4 h-4" />
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-40 rounded-lg bg-base-200">
                            <p class="text-base-content/70">No recent submissions available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right Column (Participant Stats) -->
            <div class="space-y-6">
                <!-- Top Performers Card -->
                <div class="p-6 shadow-xl bg-base-100 rounded-xl">
                    <h2 class="mb-6 text-xl font-bold">Top Performers</h2>

                    @if(count($topPerformers) > 0)
                        <div class="space-y-4">
                            @foreach($topPerformers as $index => $performer)
                                <div class="flex items-center gap-4 p-3 rounded-lg {{ $index === 0 ? 'bg-warning/20' : 'bg-base-200' }}">
                                    <div class="avatar placeholder">
                                        <div class="w-12 h-12 rounded-full bg-neutral-focus text-neutral-content">
                                            <span class="text-lg">{{ substr($performer['name'], 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $performer['name'] }}</div>
                                        <div class="text-xs">{{ $performer['type'] === 'student' ? 'Student' : 'Client' }}</div>
                                        <div class="mt-1 text-xs opacity-70">{{ $performer['submissions_count'] }} assessments completed</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold">{{ $performer['average_percentage'] }}%</div>
                                        <div class="text-xs opacity-70">average score</div>
                                    </div>
                                    @if($index === 0)
                                        <div class="absolute -top-2 -right-2">
                                            <div class="p-1 rounded-full bg-warning text-warning-content">
                                                <x-icon name="o-trophy" class="w-5 h-5" />
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-40 rounded-lg bg-base-200">
                            <p class="text-base-content/70">No performance data available yet.</p>
                        </div>
                    @endif
                </div>

                <!-- Needs Attention Card -->
                <div class="p-6 shadow-xl bg-base-100 rounded-xl">
                    <h2 class="mb-6 text-xl font-bold">Needs Attention</h2>

                    @if(count($needsAttention) > 0)
                        <div class="space-y-4">
                            @foreach($needsAttention as $performer)
                                <div class="flex items-center gap-4 p-3 rounded-lg bg-base-200">
                                    <div class="avatar placeholder">
                                        <div class="w-12 h-12 rounded-full bg-neutral-focus text-neutral-content">
                                            <span class="text-lg">{{ substr($performer['name'], 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $performer['name'] }}</div>
                                        <div class="text-xs">{{ $performer['type'] === 'student' ? 'Student' : 'Client' }}</div>
                                        <div class="mt-1 text-xs opacity-70">{{ $performer['submissions_count'] }} assessments completed</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold {{ $performer['average_percentage'] < 60 ? 'text-error' : 'text-warning' }}">
                                            {{ $performer['average_percentage'] }}%
                                        </div>
                                        <div class="text-xs opacity-70">average score</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <a href="{{ route('teachers.participants.index') }}" class="w-full btn btn-outline">
                                <x-icon name="o-user-group" class="w-4 h-4 mr-2" />
                                View All Participants
                            </a>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-40 rounded-lg bg-base-200">
                            <p class="text-base-content/70">No performance data available yet.</p>
                        </div>
                    @endif
                </div>

                <!-- Assessment Type Distribution -->
                <div class="p-6 shadow-xl bg-base-100 rounded-xl">
                    <h2 class="mb-6 text-xl font-bold">Assessment Types</h2>

                    @php
                        $typeDistribution = collect($assessments)->groupBy('type')->map->count();
                        $totalAssessments = $typeDistribution->sum();
                    @endphp

                    @if($totalAssessments > 0)
                        <div class="space-y-3">
                            @foreach($typeDistribution as $type => $count)
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm">{{ $assessmentTypes[$type] ?? ucfirst($type) }}</span>
                                        <span class="text-sm">{{ $count }}</span>
                                    </div>
                                    <div class="w-full h-2 rounded-full bg-base-300">
                                        <div class="h-full rounded-full {{ match($type) {
                                            'quiz' => 'bg-primary',
                                            'test' => 'bg-secondary',
                                            'exam' => 'bg-accent',
                                            'assignment' => 'bg-info',
                                            default => 'bg-neutral'
                                        } }}" style="width: {{ ($count / $totalAssessments) * 100 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center h-40 rounded-lg bg-base-200">
                            <p class="text-base-content/70">No assessment type data available.</p>
                        </div>
                    @endif
                </div>

                <!-- Quick Actions Card -->
                <div class="p-6 shadow-xl bg-base-100 rounded-xl">
                    <h2 class="mb-4 text-xl font-bold">Quick Actions</h2>

                    <div class="space-y-2">
                        <a href="{{ route('teachers.assessments.create') }}" class="w-full btn btn-primary">
                            <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                            Create New Assessment
                        </a>

                        <a href="{{ route('teachers.assessments.question-bank') }}" class="w-full btn btn-outline">
                            <x-icon name="o-collection" class="w-4 h-4 mr-2" />
                            Question Bank
                        </a>

                        <button wire:click="showExportModal" class="w-full btn btn-outline">
                            <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                            Export Reports
                        </button>

                        <a href="{{ route('teachers.assessments.index') }}" class="w-full btn btn-outline">
                            <x-icon name="o-document-text" class="w-4 h-4 mr-2" />
                            Manage Assessments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal {{ $showExportModal ? 'modal-open' : '' }}">
        <div class="max-w-md modal-box">
            <h3 class="text-lg font-bold">Export Assessment Report</h3>
            <p class="py-4">Configure your export options:</p>

            <div class="w-full form-control">
                <label class="label">
                    <span class="label-text">Report Title</span>
                </label>
                <input
                    type="text"
                    wire:model="exportReportTitle"
                    placeholder="Enter report title"
                    class="w-full input input-bordered"
                />
            </div>

            <div class="mt-4 form-control">
                <label class="justify-start gap-2 cursor-pointer label">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-primary"
                        wire:model="exportIncludeParticipants"
                    />
                    <span class="label-text">Include participant details</span>
                </label>
            </div>

            <div class="form-control">
                <label class="justify-start gap-2 cursor-pointer label">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-primary"
                        wire:model="exportIncludeQuestions"
                    />
                    <span class="label-text">Include question analysis</span>
                </label>
            </div>

            <div class="form-control">
                <label class="justify-start gap-2 cursor-pointer label">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-primary"
                        wire:model="exportIncludeAnswers"
                    />
                    <span class="label-text">Include individual answers</span>
                </label>
            </div>

            <div class="modal-action">
                <button wire:click="$set('showExportModal', false)" class="btn">Cancel</button>
                <button wire:click="exportReport" class="btn btn-primary">
                    <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                    Export
                </button>
            </div>
        </div>
    </div>
</div>
