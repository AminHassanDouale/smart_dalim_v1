<?php
// resources/views/livewire/teachers/assessments/review.blade.php

use Livewire\Volt\Component;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSubmission;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public Assessment $assessment;
    public $questions = [];
    public $submissions = [];
    public $materials = [];
    public $participants = [];

    // Stats
    public $stats = [
        'total_submissions' => 0,
        'completed_submissions' => 0,
        'average_score' => 0,
        'passing_rate' => 0,
        'time_spent_avg' => 0
    ];

    // Chart data
    public $scoreDistribution = [];
    public $questionPerformance = [];

    // Filters
    public $statusFilter = 'all';
    public $participantFilter = 'all';
    public $searchQuery = '';

    public function mount(Assessment $assessment)
    {
        $this->assessment = $assessment;

        // Check if user has access to this assessment
        if($assessment->teacher_profile_id !== Auth::user()->teacherProfile->id) {
            return redirect()->route('teachers.assessments.index')
                ->with('error', 'You do not have permission to review this assessment.');
        }

        $this->loadData();
        $this->prepareChartData();
    }

    public function loadData()
    {
        // Load questions with ordering by question order
        $this->questions = $this->assessment->questions()->orderBy('order')->get();

        // Load materials
        $this->materials = $this->assessment->materials;

        // Load submissions with participants
        $submissions = $this->assessment->submissions()
            ->with(['children', 'client', 'gradedBy'])
            ->get();

        // Apply filters
        if ($this->statusFilter !== 'all') {
            $submissions = $submissions->filter(function($submission) {
                return $submission->status === $this->statusFilter;
            });
        }

        if ($this->searchQuery) {
            $search = strtolower($this->searchQuery);
            $submissions = $submissions->filter(function($submission) use ($search) {
                $participantName = $submission->getParticipantNameAttribute();
                return str_contains(strtolower($participantName), $search);
            });
        }

        if ($this->participantFilter === 'children') {
            $submissions = $submissions->filter(function($submission) {
                return $submission->isFromChild();
            });
        } elseif ($this->participantFilter === 'clients') {
            $submissions = $submissions->filter(function($submission) {
                return $submission->isFromClient();
            });
        }

        $this->submissions = $submissions;

        // Get unique participants
        $children = $submissions->where('children_id', '!=', null)
            ->pluck('children')->unique('id');

        $clients = $submissions->where('client_profile_id', '!=', null)
            ->pluck('client')->unique('id');

        $this->participants = [
            'children' => $children,
            'clients' => $clients
        ];

        // Calculate stats
        $this->calculateStats();
    }

    private function calculateStats()
    {
        $this->stats['total_submissions'] = $this->submissions->count();
        $this->stats['completed_submissions'] = $this->submissions->whereIn('status', ['completed', 'graded'])->count();

        $completedSubmissions = $this->submissions->whereIn('status', ['completed', 'graded']);

        if ($completedSubmissions->count() > 0) {
            $this->stats['average_score'] = round($completedSubmissions->avg('score'), 1);

            if ($this->assessment->passing_points) {
                $passedCount = $completedSubmissions->where('score', '>=', $this->assessment->passing_points)->count();
                $this->stats['passing_rate'] = $completedSubmissions->count() > 0
                    ? round(($passedCount / $completedSubmissions->count()) * 100, 1)
                    : 0;
            }

            // Calculate average time spent
            $timesSpent = $completedSubmissions->map(function($submission) {
                if ($submission->start_time && $submission->end_time) {
                    return $submission->start_time->diffInMinutes($submission->end_time);
                }
                return null;
            })->filter()->values();

            $this->stats['time_spent_avg'] = $timesSpent->count() > 0
                ? round($timesSpent->avg(), 0)
                : 0;
        }
    }

    private function prepareChartData()
    {
        // Prepare score distribution data
        $scores = $this->submissions->whereIn('status', ['completed', 'graded'])->pluck('score');

        if ($scores->count() > 0) {
            $ranges = [
                '0-20' => 0,
                '21-40' => 0,
                '41-60' => 0,
                '61-80' => 0,
                '81-100' => 0
            ];

            foreach ($scores as $score) {
                $percentage = ($score / $this->assessment->total_points) * 100;

                if ($percentage <= 20) {
                    $ranges['0-20']++;
                } elseif ($percentage <= 40) {
                    $ranges['21-40']++;
                } elseif ($percentage <= 60) {
                    $ranges['41-60']++;
                } elseif ($percentage <= 80) {
                    $ranges['61-80']++;
                } else {
                    $ranges['81-100']++;
                }
            }

            $this->scoreDistribution = $ranges;
        }

        // Prepare question performance data
        if ($this->questions->count() > 0 && $this->submissions->count() > 0) {
            $questionPerformance = [];

            foreach ($this->questions as $question) {
                $correctCount = 0;
                $attemptedCount = 0;

                foreach ($this->submissions as $submission) {
                    if ($submission->answers && isset($submission->answers[$question->id])) {
                        $attemptedCount++;

                        if (!$question->needsManualGrading()) {
                            // For automatically graded questions
                            $answer = $submission->answers[$question->id];
                            $isCorrect = false;

                            if ($question->isMultipleChoice() && $answer == $question->correct_answer) {
                                $isCorrect = true;
                            } elseif ($question->isTrueFalse() && $answer == $question->correct_answer) {
                                $isCorrect = true;
                            }

                            if ($isCorrect) {
                                $correctCount++;
                            }
                        }
                    }
                }

                $questionPerformance[] = [
                    'id' => $question->id,
                    'text' => strlen($question->question) > 40 ? substr($question->question, 0, 40) . '...' : $question->question,
                    'attempted' => $attemptedCount,
                    'correct' => $correctCount,
                    'percentage' => $attemptedCount > 0 ? round(($correctCount / $attemptedCount) * 100, 1) : 0
                ];
            }

            $this->questionPerformance = $questionPerformance;
        }
    }

    public function updatedStatusFilter()
    {
        $this->loadData();
        $this->prepareChartData();
    }

    public function updatedParticipantFilter()
    {
        $this->loadData();
        $this->prepareChartData();
    }

    public function updatedSearchQuery()
    {
        $this->loadData();
        $this->prepareChartData();
    }

    public function publishAssessment()
    {
        $this->assessment->is_published = true;
        $this->assessment->status = 'published';
        $this->assessment->save();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Assessment published successfully!'
        ]);
    }

    public function unpublishAssessment()
    {
        $this->assessment->is_published = false;
        $this->assessment->status = 'draft';
        $this->assessment->save();

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Assessment unpublished and set to draft status.'
        ]);
    }

    public function archiveAssessment()
    {
        $this->assessment->status = 'archived';
        $this->assessment->save();

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Assessment archived successfully.'
        ]);

        return redirect()->route('teachers.assessments.index');
    }

    public function downloadResults()
    {
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Generating results export. This may take a moment...'
        ]);

        // In a real app, you would generate CSV/Excel here
        // For now, just simulate with a notification

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Results downloaded successfully!'
        ]);
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section with Assessment Info -->
        <div class="flex flex-col justify-between mb-6 space-y-4 md:items-center md:flex-row md:space-y-0">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('teachers.assessments.index') }}" class="btn btn-circle btn-sm btn-ghost">
                        <x-icon name="o-arrow-left" class="w-5 h-5" />
                    </a>
                    <h1 class="text-3xl font-bold">{{ $assessment->title }}</h1>
                    <div class="badge {{
                        $assessment->status === 'draft' ? 'badge-warning' :
                        ($assessment->status === 'published' ? 'badge-success' :
                        ($assessment->status === 'archived' ? 'badge-neutral' : 'badge-info'))
                    }}">
                        {{ ucfirst($assessment->status) }}
                    </div>
                </div>
                <p class="mt-1 text-base-content/70">{{ $assessment->type ? $assessment::$types[$assessment->type] : 'Assessment' }} Review Dashboard</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-outline">
                        <x-icon name="o-cog-6-tooth" class="w-5 h-5 mr-1" />
                        Actions
                        <x-icon name="o-chevron-down" class="w-4 h-4 ml-1" />
                    </div>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                        <li>
                            <a href="{{ route('teachers.assessments.edit', $assessment) }}">
                                <x-icon name="o-pencil-square" class="w-4 h-4" />
                                Edit Assessment
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('teachers.assessments.questions.index', $assessment) }}">
                                <x-icon name="o-question-mark-circle" class="w-4 h-4" />
                                Manage Questions
                            </a>
                        </li>
                        <li>
                            <a href="#" wire:click.prevent="downloadResults">
                                <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                Export Results
                            </a>
                        </li>
                        <hr class="my-1">
                        @if($assessment->is_published)
                            <li>
                                <a href="#" wire:click.prevent="unpublishAssessment" class="text-warning">
                                    <x-icon name="o-eye-slash" class="w-4 h-4" />
                                    Unpublish
                                </a>
                            </li>
                        @else
                            <li>
                                <a href="#" wire:click.prevent="publishAssessment" class="text-success">
                                    <x-icon name="o-eye" class="w-4 h-4" />
                                    Publish
                                </a>
                            </li>
                        @endif
                        <li>
                            <a href="#" wire:click.prevent="$dispatch('openModal', { component: 'confirm-modal', arguments: { title: 'Archive Assessment?', message: 'Are you sure you want to archive this assessment? This will make it inaccessible to students.', onConfirm: 'archiveAssessment' } })" class="text-error">
                                <x-icon name="o-archive-box" class="w-4 h-4" />
                                Archive
                            </a>
                        </li>
                    </ul>
                </div>

                <a href="{{ route('teachers.assessments.duplicate', $assessment) }}" class="btn btn-outline">
                    <x-icon name="o-document-duplicate" class="w-5 h-5 mr-1" />
                    Duplicate
                </a>

                <a href="{{ route('teachers.assessments.preview', $assessment) }}" class="btn btn-primary">
                    <x-icon name="o-eye" class="w-5 h-5 mr-1" />
                    Preview
                </a>
            </div>
        </div>

        <!-- Assessment Info Cards -->
        <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2 lg:grid-cols-4">
            <div class="p-6 shadow-md card bg-base-100">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-full bg-primary/20">
                        <x-icon name="o-document-text" class="w-6 h-6 text-primary" />
                    </div>
                    <div>
                        <div class="font-medium opacity-70">Total Questions</div>
                        <div class="text-2xl font-bold">{{ $questions->count() }}</div>
                    </div>
                </div>
            </div>

            <div class="p-6 shadow-md card bg-base-100">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-full bg-secondary/20">
                        <x-icon name="o-user-group" class="w-6 h-6 text-secondary" />
                    </div>
                    <div>
                        <div class="font-medium opacity-70">Participants</div>
                        <div class="text-2xl font-bold">{{ $participants['children']->count() + $participants['clients']->count() }}</div>
                    </div>
                </div>
            </div>

            <div class="p-6 shadow-md card bg-base-100">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-full bg-success/20">
                        <x-icon name="o-check-circle" class="w-6 h-6 text-success" />
                    </div>
                    <div>
                        <div class="font-medium opacity-70">Average Score</div>
                        <div class="text-2xl font-bold">{{ $stats['average_score'] }}/{{ $assessment->total_points }}</div>
                    </div>
                </div>
            </div>

            <div class="p-6 shadow-md card bg-base-100">
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-full bg-info/20">
                        <x-icon name="o-clock" class="w-6 h-6 text-info" />
                    </div>
                    <div>
                        <div class="font-medium opacity-70">Avg. Time to Complete</div>
                        <div class="text-2xl font-bold">{{ $stats['time_spent_avg'] }} min</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column (2/3 width on large screens) -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Performance Analysis -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="text-xl font-bold card-title">Performance Analysis</h2>

                        <!-- Score Distribution -->
                        <div class="p-4 mt-4 rounded-lg bg-base-200">
                            <h3 class="text-lg font-semibold">Score Distribution</h3>

                            @if(array_sum($scoreDistribution) > 0)
                                <div class="mt-4 space-y-3">
                                    @foreach($scoreDistribution as $range => $count)
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm">{{ $range }}%</span>
                                                <span class="text-sm">{{ $count }} {{ Str::plural('submission', $count) }}</span>
                                            </div>
                                            <div class="w-full h-3 rounded-full bg-base-300">
                                                @php
                                                    $percentage = array_sum($scoreDistribution) > 0 ? ($count / array_sum($scoreDistribution)) * 100 : 0;
                                                    $colors = [
                                                        '0-20' => 'bg-error',
                                                        '21-40' => 'bg-warning',
                                                        '41-60' => 'bg-warning',
                                                        '61-80' => 'bg-success',
                                                        '81-100' => 'bg-success'
                                                    ];
                                                @endphp
                                                <div class="h-full {{ $colors[$range] }} rounded-full" style="width: {{ $percentage }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-6 mt-2 text-center">
                                    <p class="text-base-content/70">No submission data available yet.</p>
                                </div>
                            @endif

                            <!-- Passing Rate -->
                            @if($assessment->passing_points)
                                <div class="flex items-center justify-between p-3 mt-4 border rounded-lg bg-base-100">
                                    <div>
                                        <span class="text-sm opacity-70">Passing Score: {{ $assessment->passing_points }}/{{ $assessment->total_points }}</span>
                                        <div class="text-lg font-bold">Passing Rate: {{ $stats['passing_rate'] }}%</div>
                                    </div>
                                    <div class="radial-progress {{ $stats['passing_rate'] >= 70 ? 'text-success' : ($stats['passing_rate'] >= 50 ? 'text-warning' : 'text-error') }}" style="--value:{{ $stats['passing_rate'] }}; --size:3.5rem">
                                        {{ $stats['passing_rate'] }}%
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Question Performance -->
                        <div class="p-4 mt-4 rounded-lg bg-base-200">
                            <h3 class="text-lg font-semibold">Question Performance</h3>

                            @if(count($questionPerformance) > 0)
                                <div class="mt-4 overflow-x-auto">
                                    <table class="table w-full table-zebra">
                                        <thead>
                                            <tr>
                                                <th>Question</th>
                                                <th class="text-center">Attempts</th>
                                                <th class="text-center">Correct</th>
                                                <th class="text-center">Success Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($questionPerformance as $question)
                                                <tr>
                                                    <td class="max-w-xs">{{ $question['text'] }}</td>
                                                    <td class="text-center">{{ $question['attempted'] }}</td>
                                                    <td class="text-center">{{ $question['correct'] }}</td>
                                                    <td>
                                                        <div class="flex items-center justify-center">
                                                            <div class="w-16 h-2 mr-2 rounded-full bg-base-300">
                                                                <div class="h-full {{ $question['percentage'] >= 70 ? 'bg-success' : ($question['percentage'] >= 40 ? 'bg-warning' : 'bg-error') }} rounded-full" style="width: {{ $question['percentage'] }}%"></div>
                                                            </div>
                                                            <span>{{ $question['percentage'] }}%</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="p-6 mt-2 text-center">
                                    <p class="text-base-content/70">No question performance data available yet.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Submissions List -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold card-title">Submissions</h2>
                            <div class="badge badge-primary">{{ $submissions->count() }} {{ Str::plural('submission', $submissions->count()) }}</div>
                        </div>

                        <!-- Search and Filters -->
                        <div class="grid grid-cols-1 gap-4 p-4 mt-4 rounded-lg bg-base-200 md:grid-cols-3">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Search</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="searchQuery"
                                    placeholder="Search by name..."
                                    class="w-full input input-bordered"
                                />
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Status</span>
                                </label>
                                <select wire:model.live="statusFilter" class="w-full select select-bordered">
                                    <option value="all">All Statuses</option>
                                    <option value="not_started">Not Started</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="graded">Graded</option>
                                </select>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Participant Type</span>
                                </label>
                                <select wire:model.live="participantFilter" class="w-full select select-bordered">
                                    <option value="all">All Types</option>
                                    <option value="children">Children</option>
                                    <option value="clients">Clients</option>
                                </select>
                            </div>
                        </div>

                        <!-- Submissions Table -->
                        <div class="mt-4 overflow-x-auto">
                            @if($submissions->count() > 0)
                                <table class="table w-full table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Participant</th>
                                            <th>Status</th>
                                            <th>Start Time</th>
                                            <th>Duration</th>
                                            <th>Score</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($submissions as $submission)
                                            <tr class="{{ $submission->isLate() ? 'bg-warning/10' : '' }}">
                                                <td>
                                                    <div class="flex items-center gap-2">
                                                        <div class="avatar placeholder">
                                                            <div class="w-8 h-8 rounded-full bg-neutral-focus text-neutral-content">
                                                                <span>{{ substr($submission->getParticipantNameAttribute(), 0, 1) }}</span>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium">{{ $submission->getParticipantNameAttribute() }}</div>
                                                            <div class="text-xs opacity-60">
                                                                {{ $submission->isFromChild() ? 'Student' : 'Client' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="badge {{
                                                        $submission->status === 'not_started' ? 'badge-ghost' :
                                                        ($submission->status === 'in_progress' ? 'badge-info' :
                                                        ($submission->status === 'completed' ? 'badge-success' :
                                                        ($submission->status === 'graded' ? 'badge-primary' : 'badge-warning')))
                                                    }}">
                                                        {{ str_replace('_', ' ', ucfirst($submission->status)) }}
                                                    </div>
                                                    @if($submission->isLate())
                                                        <div class="badge badge-warning">Late</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($submission->start_time)
                                                        <div class="text-sm">{{ $submission->start_time->format('M d, Y') }}</div>
                                                        <div class="text-xs opacity-60">{{ $submission->start_time->format('h:i A') }}</div>
                                                    @else
                                                        <span class="opacity-60">Not started</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($submission->start_time && $submission->end_time)
                                                        <span>{{ $submission->start_time->diffInMinutes($submission->end_time) }} min</span>
                                                    @else
                                                        <span class="opacity-60">--</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($submission->score !== null)
                                                        <div class="flex items-center gap-2">
                                                            <div class="font-medium">{{ $submission->score }}/{{ $assessment->total_points }}</div>
                                                            <div class="font-medium {{
                                                                $assessment->passing_points && $submission->score >= $assessment->passing_points ? 'text-success' :
                                                                ($assessment->passing_points ? 'text-error' : '')
                                                            }}">
                                                                ({{ round(($submission->score / $assessment->total_points) * 100) }}%)
                                                            </div>
                                                        </div>
                                                    @else
                                                        <span class="opacity-60">Not scored</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="flex gap-1">
                                                        <a href="{{ route('teachers.assessments.submissions.show', $submission) }}" class="btn btn-sm btn-outline">
                                                            <x-icon name="o-eye" class="w-4 h-4" />
                                                        </a>
                                                        @if($submission->isCompleted() && !$submission->isGraded())
                                                            <a href="{{ route('teachers.assessments.submissions.grade', $submission) }}" class="btn btn-sm btn-primary">
                                                                <x-icon name="o-pencil" class="w-4 h-4" />
                                                                Grade
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <div class="p-8 text-center rounded-lg bg-base-200">
                                    <div class="flex flex-col items-center">
                                        <x-icon name="o-document-magnifying-glass" class="w-16 h-16 text-base-content/30" />
                                        <h3 class="mt-4 text-lg font-bold">No submissions found</h3>
                                        <p class="mt-1 text-base-content/70">
                                            @if($searchQuery || $statusFilter !== 'all' || $participantFilter !== 'all')
                                                Try adjusting your search or filters
                                            @else
                                                There are no submissions for this assessment yet
                                            @endif
                                        </p>

                                        @if($searchQuery || $statusFilter !== 'all' || $participantFilter !== 'all')
                                        <button
                                        wire:click="$set('searchQuery', ''); $set('statusFilter', 'all'); $set('participantFilter', 'all');"
                                        class="mt-4 btn btn-outline btn-sm"
                                    >
                                        Clear Filters
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column (1/3 width) -->
    <div class="space-y-6">
        <!-- Assessment Details Card -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="text-xl font-bold card-title">Assessment Details</h2>

                <div class="mt-4 space-y-4">
                    <div class="flex justify-between">
                        <span class="font-medium">Type:</span>
                        <span>{{ $assessment->type ? $assessment::$types[$assessment->type] : 'Not specified' }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="font-medium">Total Points:</span>
                        <span>{{ $assessment->total_points }}</span>
                    </div>

                    @if($assessment->passing_points)
                        <div class="flex justify-between">
                            <span class="font-medium">Passing Score:</span>
                            <span>{{ $assessment->passing_points }} ({{ round(($assessment->passing_points / $assessment->total_points) * 100) }}%)</span>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="font-medium">Time Limit:</span>
                        <span>{{ $assessment->time_limit ? $assessment->getFormattedTimeLimitAttribute() : 'No limit' }}</span>
                    </div>

                    @if($assessment->course)
                        <div class="flex justify-between">
                            <span class="font-medium">Course:</span>
                            <span>{{ $assessment->course->title }}</span>
                        </div>
                    @endif

                    @if($assessment->subject)
                        <div class="flex justify-between">
                            <span class="font-medium">Subject:</span>
                            <span>{{ $assessment->subject->name }}</span>
                        </div>
                    @endif

                    @if($assessment->start_date)
                        <div class="flex justify-between">
                            <span class="font-medium">Starts:</span>
                            <span>{{ $assessment->start_date->format('M d, Y g:i A') }}</span>
                        </div>
                    @endif

                    @if($assessment->due_date)
                        <div class="flex justify-between">
                            <span class="font-medium">Due:</span>
                            <span class="{{ $assessment->hasEnded() ? 'text-error' : '' }}">
                                {{ $assessment->due_date->format('M d, Y g:i A') }}
                            </span>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="font-medium">Status:</span>
                        <span class="{{
                            $assessment->status === 'draft' ? 'text-warning' :
                            ($assessment->status === 'published' ? 'text-success' :
                            ($assessment->status === 'archived' ? 'text-base-content/70' : 'text-info'))
                        }}">
                            {{ ucfirst($assessment->status) }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="font-medium">Created:</span>
                        <span>{{ $assessment->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Materials Card -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="text-xl font-bold card-title">Assessment Materials</h2>

                @if($materials->count() > 0)
                    <div class="mt-4 space-y-2">
                        @foreach($materials as $material)
                            <div class="flex items-center p-3 transition-colors border rounded-lg hover:bg-base-200">
                                <div class="flex items-center flex-grow gap-3">
                                    <div class="p-2 rounded-lg bg-primary/20">
                                        <x-icon name="{{
                                            $material->type === 'pdf' ? 'o-document-text' :
                                            ($material->type === 'video' ? 'o-film' :
                                            ($material->type === 'audio' ? 'o-musical-note' :
                                            ($material->type === 'presentation' ? 'o-presentation-chart-bar' : 'o-document')))
                                        }}" class="w-5 h-5 text-primary" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $material->title }}</div>
                                        <div class="text-xs opacity-70">{{ ucfirst($material->type) }}</div>
                                    </div>
                                </div>
                                <a href="{{ route('teachers.materials.show', $material) }}" class="btn btn-sm btn-ghost">
                                    <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4" />
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 mt-2 text-center rounded-lg bg-base-200">
                        <p class="text-base-content/70">No materials attached to this assessment.</p>
                        <a href="{{ route('teachers.assessments.edit', $assessment) }}" class="mt-2 btn btn-sm btn-outline">
                            Add Materials
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Participants Summary Card -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="text-xl font-bold card-title">Participants Summary</h2>

                <div class="mt-4">
                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="font-medium">Completion Status</div>
                        <div class="flex items-center justify-between mt-2">
                            <span>Progress</span>
                            <span>{{ $stats['completed_submissions'] }}/{{ $stats['total_submissions'] }}</span>
                        </div>
                        <div class="w-full h-2 mt-1 overflow-hidden rounded-full bg-base-300">
                            @php
                                $completionPercentage = $stats['total_submissions'] > 0 ?
                                    ($stats['completed_submissions'] / $stats['total_submissions']) * 100 : 0;
                            @endphp
                            <div class="h-full bg-success" style="width: {{ $completionPercentage }}%"></div>
                        </div>
                    </div>

                    <!-- Participant Groups -->
                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <div class="p-4 text-center rounded-lg bg-base-200">
                            <div class="text-2xl font-bold">{{ $participants['children']->count() }}</div>
                            <div class="text-sm">Students</div>
                        </div>

                        <div class="p-4 text-center rounded-lg bg-base-200">
                            <div class="text-2xl font-bold">{{ $participants['clients']->count() }}</div>
                            <div class="text-sm">Clients</div>
                        </div>
                    </div>

                    <!-- Manage Participants -->
                    <div class="mt-4">
                        <a href="{{ route('teachers.assessments.participants', $assessment) }}" class="w-full btn btn-outline">
                            <x-icon name="o-user-group" class="w-4 h-4 mr-2" />
                            Manage Participants
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="text-xl font-bold card-title">Quick Actions</h2>

                <div class="grid grid-cols-1 gap-2 mt-4">
                    <a href="{{ route('teachers.assessments.questions.index', $assessment) }}" class="btn btn-outline">
                        <x-icon name="o-question-mark-circle" class="w-4 h-4 mr-2" />
                        Manage Questions
                    </a>

                    <a href="#" wire:click.prevent="downloadResults" class="btn btn-outline">
                        <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                        Export Results
                    </a>

                    <a href="{{ route('teachers.assessments.duplicate', $assessment) }}" class="btn btn-outline">
                        <x-icon name="o-document-duplicate" class="w-4 h-4 mr-2" />
                        Duplicate Assessment
                    </a>

                    @if(!$assessment->is_published)
                        <button wire:click="publishAssessment" class="btn btn-success">
                            <x-icon name="o-rocket-launch" class="w-4 h-4 mr-2" />
                            Publish Assessment
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
