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

    // Children and selected child
    public $children = [];
    public $selectedChildId = null;

    // Time range filter
    public $timeRange = 'last_3_months'; // Options: 'last_month', 'last_3_months', 'last_6_months', 'last_year', 'custom'
    public $customStartDate = null;
    public $customEndDate = null;

    // Subject filter
    public $selectedSubject = null;
    public $availableSubjects = [];

    // Progress data
    public $overallProgress = 0;
    public $attendanceRate = 0;
    public $assessmentScores = [];
    public $subjectProgress = [];
    public $recentSessions = [];
    public $recentAssessments = [];
    public $monthlySessionCounts = [];
    public $skillMastery = [];
    public $learningGoals = [];
    public $recommendedFocus = [];

    // Report type for PDF export
    public $reportType = 'comprehensive'; // 'comprehensive', 'academic', 'attendance', 'custom'

    // Chart data
    public $chartData = [
        'subjectsData' => [],
        'attendanceData' => [],
        'assessmentData' => [],
        'progressTrendData' => [],
        'skillsRadarData' => []
    ];

    // UI states
    public $activeSectionTab = 'overview'; // 'overview', 'subjects', 'assessments', 'attendance', 'goals'

    // Modal states
    public $showSessionDetailsModal = false;
    public $showAssessmentDetailsModal = false;
    public $selectedSession = null;
    public $selectedAssessment = null;

    // View mode for progress
    public $progressViewMode = 'chart'; // 'chart', 'table'

    public function mount()
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        if (!$this->parentProfile) {
            return redirect()->route('parents.profile-setup');
        }

        // Load children
        $this->loadChildren();

        // Set default selected child if there are children
        if (count($this->children) > 0) {
            $this->selectedChildId = $this->children[0]['id'];
        }

        // Set default dates for custom range
        $this->customStartDate = Carbon::now()->subMonths(3)->format('Y-m-d');
        $this->customEndDate = Carbon::now()->format('Y-m-d');

        // Load subjects and progress data
        $this->loadSubjects();
        $this->loadProgressData();
    }

    private function loadChildren()
    {
        $this->children = $this->parentProfile->children()
            ->with(['learningSessions', 'assessmentSubmissions', 'subjects'])
            ->get()
            ->toArray();
    }

    private function loadSubjects()
    {
        if ($this->selectedChildId) {
            $child = Children::with('subjects')->find($this->selectedChildId);
            if ($child) {
                $this->availableSubjects = $child->subjects->toArray();
            } else {
                $this->availableSubjects = Subject::all()->toArray();
            }
        } else {
            $this->availableSubjects = Subject::all()->toArray();
        }
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

    private function loadProgressData()
    {
        if (!$this->selectedChildId) {
            return;
        }

        $child = Children::with([
            'learningSessions' => function($query) {
                $dateRange = $this->getDateRange();
                $query->whereBetween('start_time', [$dateRange['start'], $dateRange['end']]);
                if ($this->selectedSubject) {
                    $query->where('subject_id', $this->selectedSubject);
                }
                $query->with(['subject', 'teacher']);
            },
            'assessmentSubmissions' => function($query) {
                $dateRange = $this->getDateRange();
                $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                if ($this->selectedSubject) {
                    $query->whereHas('assessment', function($q) {
                        $q->where('subject_id', $this->selectedSubject);
                    });
                }
                $query->with(['assessment', 'assessment.subject']);
            },
            'subjects'
        ])->find($this->selectedChildId);

        if (!$child) {
            return;
        }

        // Calculate overall progress (for demonstration purposes)
        $this->calculateOverallProgress($child);

        // Calculate attendance rate
        $this->calculateAttendanceRate($child);

        // Prepare assessment scores
        $this->prepareAssessmentScores($child);

        // Prepare subject progress
        $this->prepareSubjectProgress($child);

        // Get recent sessions
        $this->recentSessions = $child->learningSessions->sortByDesc('start_time')->take(5)->toArray();

        // Get recent assessments
        $this->recentAssessments = $child->assessmentSubmissions->sortByDesc('created_at')->take(5)->toArray();

        // Prepare monthly session counts
        $this->prepareMonthlySessionCounts($child);

        // Prepare skill mastery data (mock data for demonstration)
        $this->prepareSkillMasteryData();

        // Prepare learning goals (mock data for demonstration)
        $this->prepareLearningGoals();

        // Prepare recommended focus areas (mock data for demonstration)
        $this->prepareRecommendedFocus();

        // Prepare chart data
        $this->prepareChartData($child);
    }

    private function calculateOverallProgress($child)
    {
        // This would typically be a more complex calculation based on multiple factors
        // For demonstration, we'll use a combination of session attendance and assessment scores

        // Get number of sessions attended
        $totalSessions = $child->learningSessions->count();
        $attendedSessions = $child->learningSessions->where('attended', true)->count();

        // Get average assessment score
        $assessmentScores = $child->assessmentSubmissions->pluck('score')->filter();
        $avgScore = $assessmentScores->count() > 0 ? $assessmentScores->avg() : 0;

        // Calculate overall progress (50% sessions, 50% assessments)
        $sessionProgress = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;
        $assessmentProgress = $avgScore;

        $this->overallProgress = round(($sessionProgress * 0.5) + ($assessmentProgress * 0.5));

        // Ensure it's capped at 100%
        $this->overallProgress = min(100, max(0, $this->overallProgress));
    }

    private function calculateAttendanceRate($child)
    {
        $totalSessions = $child->learningSessions->count();
        $attendedSessions = $child->learningSessions->where('attended', true)->count();

        $this->attendanceRate = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100) : 0;
    }

    private function prepareAssessmentScores($child)
    {
        $assessments = $child->assessmentSubmissions;

        // Group by subject
        $bySubject = $assessments->groupBy(function($item) {
            return $item['assessment']['subject']['name'] ?? 'Unknown';
        });

        $this->assessmentScores = [];

        foreach ($bySubject as $subject => $items) {
            $scores = $items->pluck('score')->filter();
            $avgScore = $scores->count() > 0 ? round($scores->avg(), 1) : 0;

            $this->assessmentScores[] = [
                'subject' => $subject,
                'average_score' => $avgScore,
                'assessments_count' => $items->count(),
                'highest_score' => $scores->count() > 0 ? $scores->max() : 0,
                'lowest_score' => $scores->count() > 0 ? $scores->min() : 0,
            ];
        }
    }

    private function prepareSubjectProgress($child)
    {
        $subjects = $child->subjects;
        $this->subjectProgress = [];

        foreach ($subjects as $subject) {
            // Get sessions for this subject
            $sessions = $child->learningSessions->where('subject_id', $subject->id);
            $totalSessions = $sessions->count();
            $attendedSessions = $sessions->where('attended', true)->count();

            // Get assessments for this subject
            $assessments = $child->assessmentSubmissions->filter(function($item) use ($subject) {
                return ($item['assessment']['subject_id'] ?? null) == $subject->id;
            });

            $scores = $assessments->pluck('score')->filter();
            $avgScore = $scores->count() > 0 ? round($scores->avg(), 1) : 0;

            // Calculate progress for this subject
            $attendanceProgress = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;
            $assessmentProgress = $avgScore;

            // Overall subject progress (weighted average)
            $progress = round(($attendanceProgress * 0.4) + ($assessmentProgress * 0.6));

            $this->subjectProgress[] = [
                'id' => $subject->id,
                'name' => $subject->name,
                'progress' => min(100, max(0, $progress)),
                'sessions_count' => $totalSessions,
                'attended_sessions' => $attendedSessions,
                'assessments_count' => $assessments->count(),
                'average_score' => $avgScore,
            ];
        }
    }

    private function prepareMonthlySessionCounts($child)
    {
        $dateRange = $this->getDateRange();
        $startMonth = $dateRange['start']->startOfMonth();
        $endMonth = $dateRange['end']->startOfMonth();

        $sessionsByMonth = [];
        $currentMonth = $startMonth->copy();

        while ($currentMonth->lte($endMonth)) {
            $monthKey = $currentMonth->format('Y-m');
            $monthLabel = $currentMonth->format('M Y');

            $sessionsByMonth[$monthKey] = [
                'label' => $monthLabel,
                'total' => 0,
                'attended' => 0,
                'missed' => 0
            ];

            $currentMonth->addMonth();
        }

        // Count sessions by month
        foreach ($child->learningSessions as $session) {
            $sessionDate = Carbon::parse($session->start_time);
            $monthKey = $sessionDate->format('Y-m');

            if (isset($sessionsByMonth[$monthKey])) {
                $sessionsByMonth[$monthKey]['total']++;

                if ($session->attended) {
                    $sessionsByMonth[$monthKey]['attended']++;
                } else {
                    $sessionsByMonth[$monthKey]['missed']++;
                }
            }
        }

        $this->monthlySessionCounts = array_values($sessionsByMonth);
    }

    private function prepareSkillMasteryData()
    {
        // This would typically come from a more complex skill tracking system
        // For demonstration, we'll use mock data
        $this->skillMastery = [
            ['skill' => 'Critical Thinking', 'level' => rand(60, 95), 'subject' => 'General'],
            ['skill' => 'Problem Solving', 'level' => rand(65, 90), 'subject' => 'Mathematics'],
            ['skill' => 'Reading Comprehension', 'level' => rand(70, 95), 'subject' => 'English'],
            ['skill' => 'Scientific Method', 'level' => rand(60, 85), 'subject' => 'Science'],
            ['skill' => 'Essay Writing', 'level' => rand(55, 85), 'subject' => 'English'],
            ['skill' => 'Data Analysis', 'level' => rand(60, 90), 'subject' => 'Mathematics'],
            ['skill' => 'Historical Analysis', 'level' => rand(70, 90), 'subject' => 'History'],
            ['skill' => 'Creative Expression', 'level' => rand(75, 95), 'subject' => 'Art']
        ];
    }

    private function prepareLearningGoals()
    {
        // This would typically come from saved goals for the student
        // For demonstration, we'll use mock data
        $this->learningGoals = [
            [
                'id' => 1,
                'title' => 'Complete Algebra Fundamentals',
                'description' => 'Master basic algebraic concepts including equations, inequalities, and functions',
                'subject' => 'Mathematics',
                'progress' => rand(40, 90),
                'target_date' => Carbon::now()->addWeeks(rand(2, 8))->format('Y-m-d')
            ],
            [
                'id' => 2,
                'title' => 'Improve Essay Structure',
                'description' => 'Develop clear thesis statements and supporting paragraphs in essays',
                'subject' => 'English',
                'progress' => rand(30, 85),
                'target_date' => Carbon::now()->addWeeks(rand(1, 6))->format('Y-m-d')
            ],
            [
                'id' => 3,
                'title' => 'Science Project Completion',
                'description' => 'Complete the renewable energy science project with research and presentation',
                'subject' => 'Science',
                'progress' => rand(20, 70),
                'target_date' => Carbon::now()->addWeeks(rand(3, 10))->format('Y-m-d')
            ],
        ];
    }

    private function prepareRecommendedFocus()
    {
        // This would typically be generated based on assessment results and progress
        // For demonstration, we'll use mock data
        $this->recommendedFocus = [
            [
                'area' => 'Algebra Word Problems',
                'subject' => 'Mathematics',
                'reason' => 'Recent assessment shows opportunity for improvement in translating word problems to equations',
                'resources' => [
                    ['type' => 'video', 'title' => 'Word Problem Strategies'],
                    ['type' => 'worksheet', 'title' => 'Practice Set: Algebra Word Problems']
                ]
            ],
            [
                'area' => 'Reading Comprehension',
                'subject' => 'English',
                'reason' => 'Analysis of recent assessments shows potential growth in identifying main themes and supporting details',
                'resources' => [
                    ['type' => 'article', 'title' => 'Active Reading Techniques'],
                    ['type' => 'exercise', 'title' => 'Theme Identification Practice']
                ]
            ],
            [
                'area' => 'Scientific Hypothesis Formation',
                'subject' => 'Science',
                'reason' => 'Teacher feedback indicates opportunity to strengthen scientific hypothesis creation',
                'resources' => [
                    ['type' => 'video', 'title' => 'The Scientific Method Explained'],
                    ['type' => 'worksheet', 'title' => 'Hypothesis Writing Workshop']
                ]
            ]
        ];
    }

    private function prepareChartData($child)
    {
        // Prepare subjects data for radar chart
        $subjectsData = [];
        foreach ($this->subjectProgress as $subject) {
            $subjectsData[] = [
                'subject' => $subject['name'],
                'progress' => $subject['progress']
            ];
        }
        $this->chartData['subjectsData'] = $subjectsData;

        // Prepare attendance data for bar chart
        $attendanceData = [];
        foreach ($this->monthlySessionCounts as $month) {
            $attendanceData[] = [
                'month' => $month['label'],
                'attended' => $month['attended'],
                'missed' => $month['missed']
            ];
        }
        $this->chartData['attendanceData'] = $attendanceData;

        // Prepare assessment data for line chart
        $assessmentData = [];
        $assessments = $child->assessmentSubmissions->sortBy('created_at');
        foreach ($assessments as $assessment) {
            $date = Carbon::parse($assessment->created_at)->format('M d');
            $subject = $assessment->assessment->subject->name ?? 'Unknown';

            $assessmentData[] = [
                'date' => $date,
                'score' => $assessment->score,
                'subject' => $subject
            ];
        }
        $this->chartData['assessmentData'] = $assessmentData;

        // Prepare progress trend data
        // This would typically come from historical progress snapshots
        // For demonstration, we'll generate mock data
        $progressTrendData = [];
        $dateRange = $this->getDateRange();
        $startMonth = $dateRange['start']->startOfMonth();
        $endMonth = $dateRange['end']->startOfMonth();

        $currentMonth = $startMonth->copy();
        $baseProgress = rand(50, 70);

        while ($currentMonth->lte($endMonth)) {
            $monthLabel = $currentMonth->format('M Y');

            // Simulate progress increasing over time with some randomness
            $progress = min(100, $baseProgress + (($currentMonth->diffInMonths($startMonth) * 3) + rand(-5, 5)));

            $progressTrendData[] = [
                'month' => $monthLabel,
                'progress' => $progress
            ];

            $currentMonth->addMonth();
        }
        $this->chartData['progressTrendData'] = $progressTrendData;

        // Prepare skills radar data
        $skillsRadarData = [];
        foreach ($this->skillMastery as $skill) {
            $skillsRadarData[] = [
                'skill' => $skill['skill'],
                'level' => $skill['level']
            ];
        }
        $this->chartData['skillsRadarData'] = $skillsRadarData;
    }

    public function updatedSelectedChildId()
    {
        $this->loadSubjects();
        $this->loadProgressData();
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

    public function updatedActiveSectionTab()
    {
        // Any actions needed when changing tabs
    }

    public function showSessionDetails($sessionId)
    {
        if (!$this->selectedChildId) {
            return;
        }

        $child = Children::find($this->selectedChildId);

        if (!$child) {
            return;
        }

        $session = $child->learningSessions()
            ->with(['teacher', 'subject'])
            ->find($sessionId);

        if ($session) {
            $this->selectedSession = $session->toArray();
            $this->showSessionDetailsModal = true;
        }
    }

    public function showAssessmentDetails($assessmentId)
    {
        if (!$this->selectedChildId) {
            return;
        }

        $child = Children::find($this->selectedChildId);

        if (!$child) {
            return;
        }

        $assessment = $child->assessmentSubmissions()
            ->with(['assessment', 'assessment.subject'])
            ->find($assessmentId);

        if ($assessment) {
            $this->selectedAssessment = $assessment->toArray();
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
        // For demonstration purposes, we'll just show a response message
        session()->flash('message', 'Progress report download initiated. Your report will be ready shortly.');
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
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Learning Progress</h1>
                <p class="mt-1 text-base-content/70">Track and analyze your children's academic progress and achievements</p>
            </div>
            <button
                wire:click="downloadProgressReport"
                class="btn btn-primary"
            >
                <x-icon name="o-document-arrow-down" class="w-5 h-5 mr-2" />
                Download Report
            </button>
        </div>

        @if(session()->has('message'))
            <div class="mb-6 alert alert-success">
                <x-icon name="o-check-circle" class="w-6 h-6" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        <!-- No Children Warning -->
        @if(count($children) === 0)
            <div class="p-12 text-center shadow-lg bg-base-100 rounded-xl">
                <div class="flex flex-col items-center">
                    <x-icon name="o-user-plus" class="w-20 h-20 mb-4 text-base-content/20" />
                    <h3 class="text-xl font-bold">No children added yet</h3>
                    <p class="max-w-md mx-auto mt-2 text-base-content/70">
                        Add your children to start tracking their learning progress and achievements.
                    </p>
                    <a href="{{ route('parents.children.create') }}" class="mt-6 btn btn-primary">
                        <x-icon name="o-user-plus" class="w-5 h-5 mr-2" />
                        Add Your First Child
                    </a>
                </div>
            </div>
        @else
            <!-- Filters and Controls -->
            <div class="p-4 mb-6 shadow-lg bg-base-100 rounded-xl">
                <div class="flex flex-col justify-between gap-4 lg:flex-row">
                    <div class="flex flex-col gap-3 md:flex-row">
                        <!-- Select Child -->
                        <select
                            wire:model.live="selectedChildId"
                            class="select select-bordered"
                        >
                            @foreach($children as $child)
                                <option value="{{ $child['id'] }}">{{ $child['name'] }}</option>
                            @endforeach
                        </select>

                        <!-- Select Subject -->
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

                    <div class="flex flex-col gap-3 md:flex-row">
                        <!-- Time Range -->
                        <select
                            wire:model.live="timeRange"
                            class="select select-bordered"
                        >
                            @foreach($this->getTimeRangeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>

                        <!-- Custom Date Range (if selected) -->
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

                        <!-- Toggle View Mode -->
                        <div class="join">
                            <button
                                wire:click="$set('progressViewMode', 'chart')"
                                class="join-item btn {{ $progressViewMode === 'chart' ? 'btn-active' : '' }}"
                            >
                                <x-icon name="o-chart-bar" class="w-5 h-5" />
                            </button>
                            <button
                                wire:click="$set('progressViewMode', 'table')"
                                class="join-item btn {{ $progressViewMode === 'table' ? 'btn-active' : '' }}"
                            >
                                <x-icon name="o-table-cells" class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Tabs -->
            <div class="mb-6 tabs tabs-boxed">
                <a
                    wire:click="$set('activeSectionTab', 'overview')"
                    class="tab {{ $activeSectionTab === 'overview' ? 'tab-active' : '' }}"
                >
                    Overview
                </a>
                <a
                    wire:click="$set('activeSectionTab', 'subjects')"
                    class="tab {{ $activeSectionTab === 'subjects' ? 'tab-active' : '' }}"
                >
                    Subjects
                </a>
                <a
                    wire:click="$set('activeSectionTab', 'assessments')"
                    class="tab {{ $activeSectionTab === 'assessments' ? 'tab-active' : '' }}"
                >
                    Assessments
                </a>
                <a
                    wire:click="$set('activeSectionTab', 'attendance')"
                    class="tab {{ $activeSectionTab === 'attendance' ? 'tab-active' : '' }}"
                >
                    Attendance
                </a>
                <a
                    wire:click="$set('activeSectionTab', 'goals')"
                    class="tab {{ $activeSectionTab === 'goals' ? 'tab-active' : '' }}"
                >
                    Goals
                </a>
            </div>

            <!-- Overview Tab -->
            <div class="{{ $activeSectionTab === 'overview' ? 'block' : 'hidden' }}">
                <!-- Progress Summary Cards -->
                <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-3">
                    <!-- Overall Progress -->
                    <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold">Overall Progress</h3>
                            <div class="tooltip" data-tip="Combined measure of attendance, assessments, and skill mastery">
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

                    <!-- Assessment Average -->
                    <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold">Assessment Performance</h3>
                            <div class="tooltip" data-tip="Average score across all assessments">
                                <x-icon name="o-information-circle" class="w-5 h-5 text-info" />
                            </div>
                        </div>

                        <div class="flex flex-col items-center">
                            @php
                                $assessmentAvg = count($assessmentScores) > 0
                                    ? round(collect($assessmentScores)->pluck('average_score')->avg())
                                    : 0;
                                $grade = $this->getGradeFromScore($assessmentAvg);
                            @endphp

                            <div class="flex items-center justify-center w-32 h-32 rounded-full border-8 {{ $grade['class'] }} border-opacity-50">
                                <div class="text-center">
                                    <div class="text-2xl font-bold">{{ $assessmentAvg }}%</div>
                                    <div class="text-3xl font-bold {{ $grade['class'] }}">{{ $grade['grade'] }}</div>
                                </div>
                            </div>
                            <div class="mt-4 text-sm">
                                <span class="font-medium">Grade:</span>
                                <span class="{{ $grade['class'] }}">{{ $grade['grade'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress Charts Row -->
                <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
                    <!-- Subject Progress -->
                    <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                        <h3 class="mb-4 text-lg font-bold">Subject Progress</h3>

                        @if(count($subjectProgress) > 0)
                            <div class="space-y-4">
                                @foreach($subjectProgress as $subject)
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="font-medium">{{ $subject['name'] }}</span>
                                            <span>{{ $subject['progress'] }}%</span>
                                        </div>
                                        <div class="w-full h-3 overflow-hidden rounded-full bg-base-300">
                                            <div class="h-full {{ $this->getProgressColor($subject['progress']) }}" style="width: {{ $subject['progress'] }}%"></div>
                                        </div>
                                        <div class="flex justify-between mt-1 text-xs opacity-70">
                                            <span>Sessions: {{ $subject['sessions_count'] }}</span>
                                            <span>Avg. Score: {{ $subject['average_score'] }}%</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-6 text-center">
                                <p class="text-base-content/70">No subject data available</p>
                            </div>
                        @endif
                    </div>

                    <!-- Progress Trend -->
                    <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                        <h3 class="mb-4 text-lg font-bold">Progress Trend</h3>

                        @if(count($chartData['progressTrendData']) > 0)
                            <div class="h-64">
                                <!-- This would typically be a chart component -->
                                <div class="flex items-end h-full">
                                    @foreach($chartData['progressTrendData'] as $item)
                                        <div class="flex flex-col items-center flex-1">
                                            <div class="mb-1 text-xs">{{ $item['progress'] }}%</div>
                                            <div class="w-full max-w-[30px] bg-primary rounded-t" style="height: {{ $item['progress'] }}%;"></div>
                                            <div class="w-full mt-1 text-xs text-center truncate">{{ $item['month'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="py-6 text-center">
                                <p class="text-base-content/70">No trend data available</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Skill Mastery & Recommended Focus -->
                <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
                    <!-- Skill Mastery -->
                    <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                        <h3 class="mb-4 text-lg font-bold">Skill Mastery</h3>

                        @if(count($skillMastery) > 0)
                            <div class="space-y-4">
                                @foreach($skillMastery as $skill)
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <div>
                                                <span class="font-medium">{{ $skill['skill'] }}</span>
                                                <span class="ml-2 text-xs opacity-70">{{ $skill['subject'] }}</span>
                                            </div>
                                            <span>{{ $skill['level'] }}%</span>
                                        </div>
                                        <div class="w-full h-3 overflow-hidden rounded-full bg-base-300">
                                            <div class="h-full {{ $this->getProgressColor($skill['level']) }}" style="width: {{ $skill['level'] }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-6 text-center">
                                <p class="text-base-content/70">No skill mastery data available</p>
                            </div>
                        @endif
                    </div>

                    <!-- Recommended Focus Areas -->
                    <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                        <h3 class="mb-4 text-lg font-bold">Recommended Focus Areas</h3>

                        @if(count($recommendedFocus) > 0)
                            <div class="space-y-4">
                                @foreach($recommendedFocus as $focus)
                                    <div class="pb-1 pl-4 border-l-4 border-primary">
                                        <div class="font-medium">{{ $focus['area'] }}</div>
                                        <div class="text-sm opacity-70">{{ $focus['subject'] }}</div>
                                        <div class="mt-1 text-sm">{{ $focus['reason'] }}</div>

                                        @if(isset($focus['resources']) && count($focus['resources']) > 0)
                                            <div class="mt-2 text-xs">
                                                <span class="font-medium">Recommended Resources:</span>
                                                <ul class="mt-1 ml-4 space-y-1">
                                                    @foreach($focus['resources'] as $resource)
                                                        <li>
                                                            <span class="badge badge-sm badge-outline">{{ $resource['type'] }}</span>
                                                            {{ $resource['title'] }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-6 text-center">
                                <p class="text-base-content/70">No focus recommendations available</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Recent Sessions -->
                    <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold">Recent Sessions</h3>
                            <a href="{{ route('parents.sessions.index') }}" class="btn btn-sm btn-ghost">View All</a>
                        </div>

                        @if(count($recentSessions) > 0)
                            <div class="overflow-x-auto">
                                <table class="table w-full table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentSessions as $session)
                                            <tr>
                                                <td>{{ $this->getFormattedDate($session['start_time']) }}</td>
                                                <td>{{ $session['subject']['name'] ?? 'Unknown' }}</td>
                                                <td>
                                                    <div class="badge {{
                                                        $session['status'] === 'scheduled' ? 'badge-primary' :
                                                        ($session['status'] === 'completed' ? 'badge-success' :
                                                        'badge-error')
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
                                <p class="text-base-content/70">No recent sessions available</p>
                            </div>
                        @endif
                    </div>

                    <!-- Recent Assessments -->
                    <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold">Recent Assessments</h3>
                            <a href="{{ route('parents.assessments.index') }}" class="btn btn-sm btn-ghost">View All</a>
                        </div>

                        @if(count($recentAssessments) > 0)
                            <div class="overflow-x-auto">
                                <table class="table w-full table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Title</th>
                                            <th>Score</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentAssessments as $assessment)
                                            <tr>
                                                <td>{{ $this->getFormattedDate($assessment['created_at']) }}</td>
                                                <td>{{ $assessment['assessment']['title'] ?? 'Unknown' }}</td>
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
                        @else
                            <div class="py-6 text-center">
                                <p class="text-base-content/70">No recent assessments available</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Subjects Tab -->
            <div class="{{ $activeSectionTab === 'subjects' ? 'block' : 'hidden' }}">
                <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                    <h3 class="mb-6 text-xl font-bold">Subject Performance</h3>

                    @if(count($subjectProgress) > 0)
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                            @foreach($subjectProgress as $subject)
                                <div class="p-4 transition-shadow border rounded-lg hover:shadow-md">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-lg font-bold">{{ $subject['name'] }}</h4>
                                        <div class="badge badge-primary">{{ $subject['progress'] }}%</div>
                                    </div>

                                    <div class="w-full h-3 mb-4 overflow-hidden rounded-full bg-base-300">
                                        <div class="h-full {{ $this->getProgressColor($subject['progress']) }}" style="width: {{ $subject['progress'] }}%"></div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="p-2 text-center rounded bg-base-200">
                                            <div class="text-xs opacity-70">Sessions</div>
                                            <div class="font-medium">{{ $subject['sessions_count'] }}</div>
                                        </div>
                                        <div class="p-2 text-center rounded bg-base-200">
                                            <div class="text-xs opacity-70">Attendance</div>
                                            <div class="font-medium">
                                                @if($subject['sessions_count'] > 0)
                                                    {{ round(($subject['attended_sessions'] / $subject['sessions_count']) * 100) }}%
                                                @else
                                                    N/A
                                                @endif
                                            </div>
                                        </div>
                                        <div class="p-2 text-center rounded bg-base-200">
                                            <div class="text-xs opacity-70">Assessments</div>
                                            <div class="font-medium">{{ $subject['assessments_count'] }}</div>
                                        </div>
                                        <div class="p-2 text-center rounded bg-base-200">
                                            <div class="text-xs opacity-70">Avg. Score</div>
                                            <div class="font-medium">{{ $subject['average_score'] }}%</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No subject data available</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Assessments Tab -->
            <div class="{{ $activeSectionTab === 'assessments' ? 'block' : 'hidden' }}">
                <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold">Assessment Performance</h3>
                        <a href="{{ route('parents.assessments.index') }}" class="btn btn-outline btn-sm">
                            View All Assessments
                        </a>
                    </div>

                    <!-- Assessment Performance by Subject -->
                    @if(count($assessmentScores) > 0)
                        <div class="mb-6">
                            <h4 class="mb-3 font-medium">Performance by Subject</h4>
                            <div class="overflow-x-auto">
                                <table class="table w-full table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Assessments</th>
                                            <th>Avg. Score</th>
                                            <th>Highest</th>
                                            <th>Lowest</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($assessmentScores as $score)
                                            <tr>
                                                <td class="font-medium">{{ $score['subject'] }}</td>
                                                <td>{{ $score['assessments_count'] }}</td>
                                                <td>{{ $score['average_score'] }}%</td>
                                                <td>{{ $score['highest_score'] }}%</td>
                                                <td>{{ $score['lowest_score'] }}%</td>
                                                <td>
                                                    @php $grade = $this->getGradeFromScore($score['average_score']); @endphp
                                                    <div class="badge {{ $grade['class'] }}">{{ $grade['grade'] }}</div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Recent Assessment Results -->
                        <div>
                            <h4 class="mb-3 font-medium">Recent Assessment Results</h4>

                            @if(count($recentAssessments) > 0)
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
                                                    <td class="font-medium">{{ $assessment['assessment']['title'] ?? 'Unknown' }}</td>
                                                    <td>{{ $assessment['assessment']['subject']['name'] ?? 'Unknown' }}</td>
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
                                                            class="btn btn-outline btn-xs"
                                                        >
                                                            View Details
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="py-6 text-center">
                                    <p class="text-base-content/70">No recent assessment data available</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No assessment data available</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Attendance Tab -->
            <div class="{{ $activeSectionTab === 'attendance' ? 'block' : 'hidden' }}">
                <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold">Attendance Statistics</h3>
                        <a href="{{ route('parents.sessions.index') }}" class="btn btn-outline btn-sm">
                            View All Sessions
                        </a>
                    </div>

                    <!-- Attendance Summary -->
                    <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-3">
                        <div class="p-4 text-center rounded-lg bg-base-200">
                            <div class="text-3xl font-bold text-primary">{{ $attendanceRate }}%</div>
                            <div class="text-sm opacity-70">Overall Attendance Rate</div>
                        </div>

                        @php
                            $totalSessions = collect($monthlySessionCounts)->sum('total');
                            $attendedSessions = collect($monthlySessionCounts)->sum('attended');
                            $missedSessions = collect($monthlySessionCounts)->sum('missed');
                        @endphp

                        <div class="p-4 text-center rounded-lg bg-base-200">
                            <div class="text-3xl font-bold text-success">{{ $attendedSessions }}</div>
                            <div class="text-sm opacity-70">Sessions Attended</div>
                        </div>

                        <div class="p-4 text-center rounded-lg bg-base-200">
                            <div class="text-3xl font-bold text-error">{{ $missedSessions }}</div>
                            <div class="text-sm opacity-70">Sessions Missed</div>
                        </div>
                    </div>

                    <!-- Monthly Attendance Chart -->
                    <div class="mb-6">
                        <h4 class="mb-4 font-medium">Monthly Attendance</h4>

                        @if(count($monthlySessionCounts) > 0)
                            <div class="h-64">
                                <!-- This would typically be a chart component -->
                                <div class="flex items-end h-full">
                                    @foreach($monthlySessionCounts as $month)
                                        <div class="flex flex-col items-center flex-1">
                                            <div class="flex flex-col w-full max-w-[50px] gap-1">
                                                @if($month['missed'] > 0)
                                                    <div class="text-xs">{{ $month['missed'] }}</div>
                                                    <div class="w-full bg-error" style="height: {{ $month['missed'] * 10 }}px;"></div>
                                                @endif

                                                @if($month['attended'] > 0)
                                                    <div class="text-xs">{{ $month['attended'] }}</div>
                                                    <div class="w-full bg-success" style="height: {{ $month['attended'] * 10 }}px;"></div>
                                                @endif
                                            </div>
                                            <div class="w-full mt-2 text-xs text-center truncate">{{ $month['label'] }}</div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="flex justify-center gap-4 mt-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 bg-success"></div>
                                        <span class="text-xs">Attended</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 bg-error"></div>
                                        <span class="text-xs">Missed</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="py-6 text-center">
                                <p class="text-base-content/70">No attendance data available</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Goals Tab -->
            <div class="{{ $activeSectionTab === 'goals' ? 'block' : 'hidden' }}">
                <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold">Learning Goals</h3>
                        <button class="btn btn-primary btn-sm">
                            <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                            Add Goal
                        </button>
                    </div>

                    @if(count($learningGoals) > 0)
                        <div class="space-y-6">
                            @foreach($learningGoals as $goal)
                                <div class="p-4 border rounded-lg">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h4 class="font-bold">{{ $goal['title'] }}</h4>
                                            <div class="text-sm opacity-70">{{ $goal['subject'] }}</div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button class="btn btn-ghost btn-xs">
                                                <x-icon name="o-pencil-square" class="w-4 h-4" />
                                            </button>
                                            <button class="btn btn-ghost btn-xs text-error">
                                                <x-icon name="o-trash" class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    <p class="mt-2 text-sm">{{ $goal['description'] }}</p>

                                    <div class="mt-4">
                                        <div class="flex justify-between mb-1">
                                            <span class="text-sm">Progress: {{ $goal['progress'] }}%</span>
                                            <span class="text-xs">Target Date: {{ $this->getFormattedDate($goal['target_date']) }}</span>
                                        </div>
                                        <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                            <div class="h-full {{ $this->getProgressColor($goal['progress']) }}" style="width: {{ $goal['progress'] }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No learning goals set yet</p>
                            <button class="mt-4 btn btn-primary">
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Create Your First Goal
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
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
                        <h4 class="mb-2 font-semibold">Performance</h4>
                        @if($selectedSession['status'] === 'completed')
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm opacity-70">Score:</span>
                                    <span class="font-medium">{{ $selectedSession['performance_score'] ?? 'Not graded' }}</span>
                                </div>

                                <h4 class="mt-6 mb-2 font-semibold">Notes</h4>
                                <div class="p-3 bg-base-200 rounded-lg min-h-[100px]">
                                    {{ $selectedSession['notes'] ?? 'No notes provided for this session.' }}
                                </div>
                            </div>
                        @else
                            <div class="p-3 rounded-lg bg-base-200">
                                <p>Performance details will be available after the session is completed.</p>
                            </div>

                            <h4 class="mt-6 mb-2 font-semibold">Notes</h4>
                            <div class="p-3 bg-base-200 rounded-lg min-h-[80px]">
                                {{ $selectedSession['notes'] ?? 'No notes provided for this session.' }}
                            </div>
                        @endif
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
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Status:</span>
                                <div class="badge {{
                                    $selectedAssessment['status'] === 'graded' ? 'badge-success' : 'badge-info'
                                }}">
                                    {{ ucfirst($selectedAssessment['status'] ?? 'completed') }}
                                </div>
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
