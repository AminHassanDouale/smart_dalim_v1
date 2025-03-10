<?php

use Livewire\Volt\Component;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSubmission;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Material;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public Assessment $assessment;

    // Tabs management
    public $activeTab = 'overview';

    // Dashboard stats
    public $stats = [
        'total_questions' => 0,
        'total_participants' => 0,
        'total_submissions' => 0,
        'completed_submissions' => 0,
        'average_score' => 0,
        'passing_rate' => 0,
        'time_spent_avg' => 0
    ];

    // Data collections
    public $questions = [];
    public $submissions = [];
    public $materials = [];
    public $participants = [
        'children' => [],
        'clients' => []
    ];

    // Chart data
    public $scoreDistribution = [];
    public $questionPerformance = [];
    public $participantPerformance = [];
    public $timeCompletion = [];

    // Modals
    public $showDeleteModal = false;
    public $showStatusModal = false;
    public $showShareModal = false;
    public $showNotificationModal = false;
    public $sharingOptions = [
        'email' => true,
        'link' => false,
        'qr' => false
    ];
    public $notificationMessage = '';

    // Publication settings
    public $publishStartDate = null;
    public $publishEndDate = null;

    public function mount(Assessment $assessment)
    {
        $this->assessment = $assessment;

        // Check if user has access to this assessment
        if($assessment->teacher_profile_id !== Auth::user()->teacherProfile->id) {
            return redirect()->route('teachers.assessments')
                ->with('error', 'You do not have permission to view this assessment.');
        }

        $this->loadAssessmentData();
        $this->calculateStats();
        $this->prepareChartData();

        // Set initial dates if available
        $this->publishStartDate = $assessment->start_date ? $assessment->start_date->format('Y-m-d\TH:i') : null;
        $this->publishEndDate = $assessment->due_date ? $assessment->due_date->format('Y-m-d\TH:i') : null;
    }

    private function loadAssessmentData()
    {
        // Load questions
        $this->questions = $this->assessment->questions()->orderBy('order')->get();

        // Load submissions with participants
        $this->submissions = $this->assessment->submissions()
            ->with(['children', 'client', 'gradedBy'])
            ->get();

        // Load materials
        $this->materials = $this->assessment->materials;

        // Get unique participants
        $this->participants['children'] = $this->submissions
            ->where('children_id', '!=', null)
            ->pluck('children')
            ->unique('id')
            ->filter();

        $this->participants['clients'] = $this->submissions
            ->where('client_profile_id', '!=', null)
            ->pluck('client')
            ->unique('id')
            ->filter();
    }

    private function calculateStats()
    {
        $this->stats['total_questions'] = $this->questions->count();
        $this->stats['total_participants'] = $this->participants['children']->count() + $this->participants['clients']->count();
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

                        // For automatically graded questions
                        if (!$question->needsManualGrading()) {
                            $answer = $submission->answers[$question->id];
                            $isCorrect = false;

                            if ($question->type == 'multiple_choice' && $answer == $question->correct_answer) {
                                $isCorrect = true;
                            } elseif ($question->type == 'true_false' && $answer == $question->correct_answer) {
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
                    'text' => strlen($question->question) > 40 ? substr(strip_tags($question->question), 0, 40) . '...' : strip_tags($question->question),
                    'attempted' => $attemptedCount,
                    'correct' => $correctCount,
                    'percentage' => $attemptedCount > 0 ? round(($correctCount / $attemptedCount) * 100, 1) : 0
                ];
            }

            $this->questionPerformance = $questionPerformance;
        }

        // Prepare participant performance data
        $participantPerformance = [];

        $completedSubmissions = $this->submissions->whereIn('status', ['completed', 'graded']);
        foreach ($completedSubmissions as $submission) {
            $participantName = $submission->isFromChild()
                ? ($submission->children->name ?? 'Unknown Student')
                : ($submission->client->user->name ?? 'Unknown Client');

            $scorePercentage = $this->assessment->total_points > 0
                ? round(($submission->score / $this->assessment->total_points) * 100, 1)
                : 0;

            $participantPerformance[] = [
                'name' => $participantName,
                'type' => $submission->isFromChild() ? 'student' : 'client',
                'score' => $submission->score,
                'percentage' => $scorePercentage,
                'status' => $submission->status,
                'date' => $submission->end_time ? $submission->end_time->format('M d, Y') : 'N/A',
            ];
        }

        $this->participantPerformance = collect($participantPerformance)
            ->sortByDesc('percentage')
            ->values()
            ->take(10)
            ->toArray();

        // Time completion data
        $timeData = $completedSubmissions
            ->filter(function($s) { return $s->start_time && $s->end_time; })
            ->map(function($s) {
                return [
                    'name' => $s->isFromChild()
                        ? ($s->children->name ?? 'Unknown Student')
                        : ($s->client->user->name ?? 'Unknown Client'),
                    'minutes' => $s->start_time->diffInMinutes($s->end_time),
                    'date' => $s->end_time->format('M d')
                ];
            })->values();

        $this->timeCompletion = $timeData->sortBy('minutes')->values()->take(10)->toArray();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function confirmDelete()
    {
        $this->showDeleteModal = true;
    }

    public function deleteAssessment()
    {
        try {
            $this->assessment->delete();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Assessment deleted successfully!'
            ]);

            return redirect()->route('teachers.assessments');
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error deleting assessment: ' . $e->getMessage()
            ]);
        }
    }

    public function togglePublishStatus()
    {
        $this->assessment->is_published = !$this->assessment->is_published;

        if ($this->assessment->is_published) {
            $this->assessment->status = 'published';
            $message = 'Assessment published successfully!';
        } else {
            $this->assessment->status = 'draft';
            $message = 'Assessment unpublished. It is now a draft.';
        }

        $this->assessment->save();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message
        ]);
    }

    public function showStatusSettings()
    {
        $this->showStatusModal = true;
    }

    public function updatePublishSettings()
    {
        $this->validate([
            'publishStartDate' => 'nullable|date',
            'publishEndDate' => 'nullable|date|after_or_equal:publishStartDate',
        ]);

        try {
            $this->assessment->start_date = $this->publishStartDate;
            $this->assessment->due_date = $this->publishEndDate;
            $this->assessment->is_published = true;
            $this->assessment->status = 'published';
            $this->assessment->save();

            $this->showStatusModal = false;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Assessment publish settings updated successfully!'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error updating publish settings: ' . $e->getMessage()
            ]);
        }
    }

    public function showSharingOptions()
    {
        $this->showShareModal = true;
    }

    public function sendNotification()
    {
        $this->validate([
            'notificationMessage' => 'required|min:10',
        ]);

        // In a real application, you would send notifications to participants here

        $this->showNotificationModal = false;
        $this->notificationMessage = '';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Notification sent to participants successfully!'
        ]);
    }

    public function duplicateAssessment()
    {
        try {
            $newAssessment = $this->assessment->replicate();
            $newAssessment->title = 'Copy of ' . $this->assessment->title;
            $newAssessment->is_published = false;
            $newAssessment->status = 'draft';
            $newAssessment->save();

            // Duplicate questions
            foreach ($this->assessment->questions as $question) {
                $newQuestion = $question->replicate();
                $newQuestion->assessment_id = $newAssessment->id;
                $newQuestion->save();
            }

            // Copy material relations
            if ($this->assessment->materials) {
                $newAssessment->materials()->attach($this->assessment->materials->pluck('id'));
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Assessment duplicated successfully!'
            ]);

            return redirect()->route('teachers.assessments.show', $newAssessment->id);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error duplicating assessment: ' . $e->getMessage()
            ]);
        }
    }

    public function downloadReport()
    {
        // In a real application, you would generate and download a report here

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Report generation started. It will download shortly.'
        ]);
    }

    protected function toast($type, $message)
    {
        $this->dispatch('notify', [
            'type' => $type,
            'message' => $message
        ]);
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section with Assessment Info -->
        <div class="flex flex-col justify-between mb-6 space-y-4 md:items-center md:flex-row md:space-y-0">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('teachers.assessments') }}" class="btn btn-circle btn-sm btn-ghost">
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
                <p class="mt-1 text-base-content/70">{{ $assessment->type ? ucfirst($assessment->type) : 'Assessment' }} · {{ $questions->count() }} questions · Created {{ $assessment->created_at->format('M d, Y') }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if(!$assessment->is_published)
                    <button wire:click="togglePublishStatus" class="btn btn-primary">
                        <x-icon name="o-paper-airplane" class="w-5 h-5 mr-1" />
                        Publish
                    </button>
                @else
                    <button wire:click="togglePublishStatus" class="btn btn-outline">
                        <x-icon name="o-eye-slash" class="w-5 h-5 mr-1" />
                        Unpublish
                    </button>
                @endif

                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-outline">
                        <x-icon name="o-cog-6-tooth" class="w-5 h-5 mr-1" />
                        Actions
                        <x-icon name="o-chevron-down" class="w-4 h-4 ml-1" />
                    </div>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                        <li>
                            <a href="{{ route('teachers.assessments.create') }}">
                                <x-icon name="o-pencil-square" class="w-4 h-4" />
                                Edit Assessment
                            </a>
                        </li>
                        <li>
                            <button wire:click="duplicateAssessment">
                                <x-icon name="o-document-duplicate" class="w-4 h-4" />
                                Duplicate
                            </button>
                        </li>
                        <li>
                            <button wire:click="showSharingOptions">
                                <x-icon name="o-share" class="w-4 h-4" />
                                Share
                            </button>
                        </li>
                        <li>
                            <button wire:click="showStatusSettings">
                                <x-icon name="o-clock" class="w-4 h-4" />
                                Schedule
                            </button>
                        </li>
                        <hr class="my-1">
                        <li>
                            <a href="#" class="text-error" wire:click.prevent="confirmDelete">
                                <x-icon name="o-trash" class="w-4 h-4" />
                                Delete
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-4">
            <div class="flex items-center gap-4 p-4 bg-white shadow-md rounded-xl">
                <div class="p-3 rounded-full bg-primary/20">
                    <x-icon name="o-user-group" class="w-6 h-6 text-primary" />
                </div>
                <div>
                    <div class="text-xl font-bold">{{ $stats['total_participants'] }}</div>
                    <div class="text-sm opacity-70">Participants</div>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 bg-white shadow-md rounded-xl">
                <div class="p-3 rounded-full bg-secondary/20">
                    <x-icon name="o-clipboard-document-check" class="w-6 h-6 text-secondary" />
                </div>
                <div>
                    <div class="text-xl font-bold">{{ $stats['completed_submissions'] }}/{{ $stats['total_submissions'] }}</div>
                    <div class="text-sm opacity-70">Submissions</div>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 bg-white shadow-md rounded-xl">
                <div class="p-3 rounded-full bg-success/20">
                    <x-icon name="o-trophy" class="w-6 h-6 text-success" />
                </div>
                <div>
                    <div class="text-xl font-bold">{{ $stats['average_score'] }}/{{ $assessment->total_points }}</div>
                    <div class="text-sm opacity-70">Avg. Score</div>
                </div>
            </div>

            <div class="flex items-center gap-4 p-4 bg-white shadow-md rounded-xl">
                <div class="p-3 rounded-full bg-accent/20">
                    <x-icon name="o-chart-bar" class="w-6 h-6 text-accent" />
                </div>
                <div>
                    <div class="text-xl font-bold">{{ $stats['passing_rate'] }}%</div>
                    <div class="text-sm opacity-70">Passing Rate</div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex flex-wrap gap-2 p-1 mb-6 tabs tabs-boxed bg-base-100">
            <button
                wire:click="setActiveTab('overview')"
                class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}"
            >
                <x-icon name="o-squares-2x2" class="w-4 h-4 mr-2" />
                Overview
            </button>
            <button
                wire:click="setActiveTab('questions')"
                class="tab {{ $activeTab === 'questions' ? 'tab-active' : '' }}"
            >
                <x-icon name="o-question-mark-circle" class="w-4 h-4 mr-2" />
                Questions
            </button>
            <button
                wire:click="setActiveTab('submissions')"
                class="tab {{ $activeTab === 'submissions' ? 'tab-active' : '' }}"
            >
                <x-icon name="o-clipboard-document-check" class="w-4 h-4 mr-2" />
                Submissions
            </button>
            <button
                wire:click="setActiveTab('analytics')"
                class="tab {{ $activeTab === 'analytics' ? 'tab-active' : '' }}"
            >
                <x-icon name="o-chart-bar-square" class="w-4 h-4 mr-2" />
                Analytics
            </button>
            <button
                wire:click="setActiveTab('settings')"
                class="tab {{ $activeTab === 'settings' ? 'tab-active' : '' }}"
            >
                <x-icon name="o-cog-6-tooth" class="w-4 h-4 mr-2" />
                Settings
            </button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Overview Tab -->
            <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Left Column (2/3 width on large screens) -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Assessment Summary Card -->
                        <div class="p-6 bg-white shadow-md rounded-xl">
                            <h2 class="mb-4 text-xl font-bold">Assessment Summary</h2>
                            <div class="prose max-w-none">
                                <p>{{ $assessment->description ?? 'No description provided.' }}</p>

                                @if($assessment->instructions)
                                    <h3 class="mt-4">Instructions</h3>
                                    <p>{{ $assessment->instructions }}</p>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 gap-6 mt-6 md:grid-cols-2">
                                <div>
                                    <h3 class="mb-2 font-medium">Key Information</h3>
                                    <ul class="space-y-2">
                                        <li class="flex justify-between">
                                            <span class="text-base-content/70">Type:</span>
                                            <span class="font-medium">{{ ucfirst($assessment->type ?? 'Not specified') }}</span>
                                        </li>
                                        <li class="flex justify-between">
                                            <span class="text-base-content/70">Total Points:</span>
                                            <span class="font-medium">{{ $assessment->total_points }}</span>
                                        </li>
                                        <li class="flex justify-between">
                                            <span class="text-base-content/70">Passing Score:</span>
                                            <span class="font-medium">{{ $assessment->passing_points ?? 'Not set' }}</span>
                                        </li>
                                        <li class="flex justify-between">
                                            <span class="text-base-content/70">Time Limit:</span>
                                            <span class="font-medium">{{ $assessment->time_limit ? $assessment->time_limit . ' minutes' : 'No limit' }}</span>
                                        </li>
                                    </ul>
                                </div>
                                <div>
                                    <h3 class="mb-2 font-medium">Schedule</h3>
                                    <ul class="space-y-2">
                                        <li class="flex justify-between">
                                            <span class="text-base-content/70">Start Date:</span>
                                            <span class="font-medium">{{ $assessment->start_date ? $assessment->start_date->format('M d, Y g:i A') : 'Not set' }}</span>
                                        </li>
                                        <li class="flex justify-between">
                                            <span class="text-base-content/70">Due Date:</span>
                                            <span class="font-medium">{{ $assessment->due_date ? $assessment->due_date->format('M d, Y g:i A') : 'No deadline' }}</span>
                                        </li>
                                        <li class="flex justify-between">
                                            <span class="text-base-content/70">Status:</span>
                                            <span class="font-medium {{ $assessment->is_published ? 'text-success' : 'text-warning' }}">
                                                {{ $assessment->is_published ? 'Published' : 'Draft' }}
                                            </span>
                                        </li>
                                        <li class="flex justify-between">
                                            <span class="text-base-content/70">Created:</span>
                                            <span class="font-medium">{{ $assessment->created_at->format('M d, Y') }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-6">
                                <a href="{{ route('teachers.assessments.create') }}" class="btn btn-outline">
                                    <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                                    Edit Details
                                </a>

                                <button wire:click="showSharingOptions" class="btn btn-outline">
                                    <x-icon name="o-share" class="w-4 h-4 mr-2" />
                                    Share
                                </button>
                            </div>
                        </div>

                        <!-- Quick Stats Card -->
                        <div class="p-6 bg-white shadow-md rounded-xl">
                            <h2 class="mb-4 text-xl font-bold">Performance Overview</h2>

                            @if($stats['completed_submissions'] > 0)
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <!-- Score Distribution -->
                                    <div>
                                        <h3 class="mb-3 font-medium">Score Distribution</h3>
                                        <div class="space-y-3">
                                            @foreach($scoreDistribution as $range => $count)
                                                <div>
                                                    <div class="flex justify-between mb-1">
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
                                    </div>

                                    <!-- Completion Stats -->
                                    <div>
                                        <h3 class="mb-3 font-medium">Submission Stats</h3>

                                        <div class="flex flex-col gap-4">
                                            <!-- Completion Progress -->
                                            <div>
                                                <div class="flex justify-between mb-1">
                                                    <span class="text-sm">Completion Rate</span>
                                                    <span class="text-sm">{{ $stats['completed_submissions'] }}/{{ $stats['total_submissions'] }}</span>
                                                </div>
                                                <div class="w-full h-3 rounded-full bg-base-300">
                                                    @php
                                                        $completionPercentage = $stats['total_submissions'] > 0 ?
                                                            ($stats['completed_submissions'] / $stats['total_submissions']) * 100 : 0;
                                                    @endphp
                                                    <div class="h-full rounded-full bg-primary" style="width: {{ $completionPercentage }}%"></div>
                                                </div>
                                            </div>

                                            <!-- Average Time -->
<div class="p-3 rounded-lg bg-base-200">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium">Average Completion Time</span>
        <span class="text-sm font-bold">{{ $stats['time_spent_avg'] }} minutes</span>
    </div>
</div>

<!-- Passing Rate -->
@if($assessment->passing_points)
    <div class="p-3 mt-3 rounded-lg bg-base-200">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium">Passing Rate</span>
            <div class="flex items-center">
                <div class="radial-progress text-{{ $stats['passing_rate'] >= 70 ? 'success' : ($stats['passing_rate'] >= 50 ? 'warning' : 'error') }}" style="--value:{{ $stats['passing_rate'] }}; --size:2.5rem; --thickness: 2px;">
                    <span class="text-xs">{{ $stats['passing_rate'] }}%</span>
                </div>
            </div>
        </div>
    </div>
@endif

</div>
</div>

@else
    <div class="flex flex-col items-center justify-center p-8 rounded-lg bg-base-200">
        <x-icon name="o-chart-bar" class="w-14 h-14 text-base-content/20" />
        <h3 class="mt-4 text-lg font-medium">No performance data yet</h3>
        <p class="mt-2 text-sm text-center text-base-content/70">
            Once students start submitting their assessments, performance data will appear here.
        </p>
        <button wire:click="showSharingOptions" class="mt-4 btn btn-primary btn-sm">
            <x-icon name="o-share" class="w-4 h-4 mr-2" />
            Share with Students
        </button>
    </div>
@endif
</div>

<!-- Top Performers Card -->
<div class="p-6 bg-white shadow-md rounded-xl">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold">Top Performers</h2>
        <button wire:click="setActiveTab('analytics')" class="btn btn-ghost btn-sm">
            See All
            <x-icon name="o-arrow-right" class="w-4 h-4 ml-1" />
        </button>
    </div>

    @if(count($participantPerformance) > 0)
        <div class="space-y-3">
            @foreach(array_slice($participantPerformance, 0, 5) as $index => $performer)
                <div class="flex items-center p-3 {{ $index === 0 ? 'bg-amber-50 border border-amber-200' : 'bg-base-200' }} rounded-lg">
                    <div class="avatar placeholder">
                        <div class="w-10 h-10 rounded-full bg-neutral text-neutral-content">
                            <span class="text-lg">{{ substr($performer['name'], 0, 1) }}</span>
                        </div>
                    </div>
                    <div class="flex-1 ml-3">
                        <div class="flex items-center">
                            <span class="font-medium">{{ $performer['name'] }}</span>
                            @if($index === 0)
                                <div class="gap-1 ml-2 badge badge-warning">
                                    <x-icon name="o-trophy" class="w-3 h-3" />
                                    Top
                                </div>
                            @endif
                        </div>
                        <span class="text-xs text-base-content/70">{{ ucfirst($performer['type']) }} · {{ $performer['date'] }}</span>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold">{{ $performer['percentage'] }}%</div>
                        <div class="text-xs text-base-content/70">{{ $performer['score'] }}/{{ $assessment->total_points }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if(count($participantPerformance) > 5)
            <button wire:click="setActiveTab('analytics')" class="w-full mt-3 btn btn-sm btn-outline">
                View All Results
            </button>
        @endif
    @else
        <div class="flex flex-col items-center justify-center p-6 rounded-lg bg-base-200">
            <p class="text-base-content/70">No submissions yet</p>
        </div>
    @endif
</div>
</div>

<!-- Right Column (1/3 width) -->
<div class="space-y-6">
    <!-- Material List -->
    <div class="p-6 bg-white shadow-md rounded-xl">
        <h2 class="mb-4 text-xl font-bold">Materials</h2>

        @if(count($materials) > 0)
            <div class="space-y-3">
                @foreach($materials as $material)
                    <div class="flex items-center p-3 transition-colors border rounded-lg hover:bg-base-100">
                        <div class="p-2 mr-3 rounded-lg bg-primary/10">
                            <x-icon name="{{
                                $material->type === 'pdf' ? 'o-document-text' :
                                ($material->type === 'video' ? 'o-film' :
                                ($material->type === 'audio' ? 'o-musical-note' : 'o-document'))
                            }}" class="w-5 h-5 text-primary" />
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">{{ $material->title }}</div>
                            <div class="text-xs opacity-70">{{ ucfirst($material->type) }}</div>
                        </div>
                        <a href="#" class="btn btn-ghost btn-sm">
                            <x-icon name="o-eye" class="w-4 h-4" />
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-6 text-center rounded-lg bg-base-200">
                <p class="text-base-content/70">No materials attached</p>
                <a href="{{ route('teachers.assessments.create') }}" class="mt-2 btn btn-sm btn-outline">
                    <x-icon name="o-plus" class="w-4 h-4 mr-1" />
                    Add Materials
                </a>
            </div>
        @endif
    </div>

    <!-- Quick Actions Card -->
    <div class="p-6 bg-white shadow-md rounded-xl">
        <h2 class="mb-4 text-xl font-bold">Quick Actions</h2>

        <div class="space-y-3">
            <!-- Edit Questions -->
            <a href="#" class="w-full btn btn-outline">
                <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                Edit Questions
            </a>

            <!-- Assign to Students -->
            <button wire:click="showSharingOptions" class="w-full btn btn-outline">
                <x-icon name="o-user-plus" class="w-4 h-4 mr-2" />
                Assign to Students
            </button>

            <!-- Preview Assessment -->
            <a href="#" class="w-full btn btn-outline">
                <x-icon name="o-eye" class="w-4 h-4 mr-2" />
                Preview Assessment
            </a>

            <!-- Download Reports -->
            <button wire:click="downloadReport" class="w-full btn btn-outline">
                <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                Download Reports
            </button>
        </div>
    </div>

    <!-- Participants List -->
    <div class="p-6 bg-white shadow-md rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">Participants</h2>
            <button wire:click="setActiveTab('submissions')" class="btn btn-ghost btn-sm">
                See All
                <x-icon name="o-arrow-right" class="w-4 h-4 ml-1" />
            </button>
        </div>

        <div class="flex gap-2 mb-4">
            <div class="flex-1 p-3 text-center rounded-lg bg-base-200">
                <div class="text-xl font-bold">{{ $participants['children']->count() }}</div>
                <div class="text-sm opacity-70">Students</div>
            </div>
            <div class="flex-1 p-3 text-center rounded-lg bg-base-200">
                <div class="text-xl font-bold">{{ $participants['clients']->count() }}</div>
                <div class="text-sm opacity-70">Clients</div>
            </div>
        </div>

        @if($participants['children']->count() > 0 || $participants['clients']->count() > 0)
            <div class="pr-2 space-y-2 overflow-y-auto max-h-60">
                @foreach($participants['children'] as $child)
                    <div class="flex items-center p-2 rounded-lg bg-base-200">
                        <div class="avatar placeholder">
                            <div class="w-8 h-8 rounded-full bg-neutral text-neutral-content">
                                <span>{{ substr($child->name ?? 'S', 0, 1) }}</span>
                            </div>
                        </div>
                        <div class="ml-2">
                            <div class="font-medium">{{ $child->name ?? 'Unknown Student' }}</div>
                            <div class="text-xs opacity-70">Student</div>
                        </div>
                    </div>
                @endforeach

                @foreach($participants['clients'] as $client)
                    <div class="flex items-center p-2 rounded-lg bg-base-200">
                        <div class="avatar placeholder">
                            <div class="w-8 h-8 rounded-full bg-secondary text-secondary-content">
                                <span>{{ substr($client->user->name ?? 'C', 0, 1) }}</span>
                            </div>
                        </div>
                        <div class="ml-2">
                            <div class="font-medium">{{ $client->user->name ?? 'Unknown Client' }}</div>
                            <div class="text-xs opacity-70">Client</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <button wire:click="showNotificationModal = true" class="w-full mt-4 btn btn-outline">
                <x-icon name="o-bell" class="w-4 h-4 mr-2" />
                Notify Participants
            </button>
        @else
            <div class="p-4 text-center rounded-lg bg-base-200">
                <p class="text-base-content/70">No participants yet</p>
                <button wire:click="showSharingOptions" class="mt-2 btn btn-sm btn-outline">
                    <x-icon name="o-user-plus" class="w-4 h-4 mr-1" />
                    Add Participants
                </button>
            </div>
        @endif
    </div>
</div>
</div>
</div>

<!-- Questions Tab -->
<div class="{{ $activeTab === 'questions' ? 'block' : 'hidden' }}">
    <div class="overflow-hidden bg-white shadow-md rounded-xl">
        <div class="flex items-center justify-between p-4 border-b">
            <h2 class="text-xl font-bold">Assessment Questions ({{ $questions->count() }})</h2>
            <a href="#" class="btn btn-primary">
                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                Add Question
            </a>
        </div>

        @if($questions->count() > 0)
            <div class="p-4">
                <div class="space-y-4">
                    @foreach($questions as $index => $question)
                        <div class="p-4 bg-base-200 rounded-xl">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center">
                                    <div class="flex items-center justify-center w-8 h-8 font-bold text-white rounded-full bg-primary">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="ml-3">
                                        <div class="font-medium">{{ strip_tags($question->question) }}</div>
                                        <div class="text-xs opacity-70">
                                            {{ ucfirst($question->type ?? 'question') }} ·
                                            {{ $question->points ?? 1 }} {{ Str::plural('point', $question->points ?? 1) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-1">
                                    <button class="btn btn-ghost btn-sm">
                                        <x-icon name="o-eye" class="w-4 h-4" />
                                    </button>
                                    <button class="btn btn-ghost btn-sm">
                                        <x-icon name="o-pencil-square" class="w-4 h-4" />
                                    </button>
                                    <button class="btn btn-ghost btn-sm text-error">
                                        <x-icon name="o-trash" class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            @if($question->type === 'multiple_choice' && !empty($question->options))
                                <div class="mt-3 pl-11">
                                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                        @foreach($question->options as $option)
                                            <div class="flex items-center p-2 bg-white rounded-lg {{ $option == $question->correct_answer ? 'border border-success' : '' }}">
                                                <div class="w-5 h-5 flex items-center justify-center rounded-full mr-2 {{ $option == $question->correct_answer ? 'bg-success text-white' : 'bg-base-300' }}">
                                                    {{ $option == $question->correct_answer ? '✓' : '' }}
                                                </div>
                                                <span>{{ $option }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="p-8 text-center">
                <x-icon name="o-question-mark-circle" class="w-16 h-16 mx-auto text-base-content/20" />
                <h3 class="mt-4 text-lg font-medium">No questions yet</h3>
                <p class="mt-2 text-base-content/70">
                    Start by adding some questions to your assessment.
                </p>
                <a href="#" class="mt-4 btn btn-primary">
                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                    Add First Question
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Submissions Tab -->
<div class="{{ $activeTab === 'submissions' ? 'block' : 'hidden' }}">
    <!-- Submissions content similar to your review.blade.php file -->
    <div class="overflow-hidden bg-white shadow-md rounded-xl">
        <div class="p-4 border-b">
            <h2 class="text-xl font-bold">Assessment Submissions</h2>
            <p class="mt-1 text-sm text-base-content/70">View and grade student submissions</p>
        </div>

        @if($submissions->count() > 0)
            <div class="overflow-x-auto">
                <table class="table w-full table-zebra">
                    <thead>
                        <tr>
                            <th>Participant</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Time Spent</th>
                            <th>Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($submissions as $submission)
                            <tr>
                                <td>
                                    <div class="flex items-center">
                                        <div class="avatar placeholder">
                                            <div class="w-8 h-8 rounded-full bg-neutral-focus text-neutral-content">
                                                <span>{{ substr($submission->isFromChild() ?
                                                    ($submission->children->name ?? 'S') :
                                                    ($submission->client->user->name ?? 'C'), 0, 1) }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-2">
                                            <div class="font-medium">{{
                                                $submission->isFromChild() ?
                                                ($submission->children->name ?? 'Unknown Student') :
                                                ($submission->client->user->name ?? 'Unknown Client')
                                            }}</div>
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
                                </td>
                                <td>
                                    @if($submission->end_time)
                                        <div class="text-sm">{{ $submission->end_time->format('M d, Y') }}</div>
                                        <div class="text-xs opacity-60">{{ $submission->end_time->format('h:i A') }}</div>
                                    @else
                                        <span class="opacity-60">Not submitted</span>
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
                                        <div class="flex items-center">
                                            <div class="radial-progress text-{{
                                                $assessment->passing_points && $submission->score >= $assessment->passing_points ? 'success' :
                                                ($assessment->passing_points ? 'error' : 'primary') }}"
                                                style="--value:{{ min(100, round(($submission->score / $assessment->total_points) * 100)) }}; --size: 2rem; --thickness: 3px;">
                                                <span class="text-xs">{{ round(($submission->score / $assessment->total_points) * 100) }}%</span>
                                            </div>
                                            <div class="ml-2 font-medium">
                                                {{ $submission->score }}/{{ $assessment->total_points }}
                                            </div>
                                        </div>
                                    @else
                                        <span class="opacity-60">Not scored</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex gap-1">
                                        <a href="#" class="btn btn-ghost btn-sm">
                                            <x-icon name="o-eye" class="w-4 h-4" />
                                        </a>

                                        @if($submission->status === 'completed')
                                            <a href="#" class="btn btn-success btn-sm">
                                                <x-icon name="o-check" class="w-4 h-4 mr-1" />
                                                Grade
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center">
                <x-icon name="o-clipboard-document-check" class="w-16 h-16 mx-auto text-base-content/20" />
                <h3 class="mt-4 text-lg font-medium">No submissions yet</h3>
                <p class="mt-2 text-base-content/70">
                    Once students complete the assessment, their submissions will appear here.
                </p>
                <button wire:click="showSharingOptions" class="mt-4 btn btn-primary">
                    <x-icon name="o-share" class="w-4 h-4 mr-2" />
                    Share Assessment
                </button>
            </div>
        @endif
    </div>
</div>

<!-- Analytics Tab -->
<div class="{{ $activeTab === 'analytics' ? 'block' : 'hidden' }}">
    <!-- Analytics content -->
    <div class="space-y-6">
        <!-- Score Distribution Chart -->
        <div class="p-6 bg-white shadow-md rounded-xl">
            <h2 class="mb-4 text-xl font-bold">Score Distribution</h2>

            @if(array_sum($scoreDistribution) > 0)
                <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                    <!-- Bar Chart -->
                    <div>
                        <div class="space-y-3">
                            @foreach($scoreDistribution as $range => $count)
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm">{{ $range }}%</span>
                                        <span class="text-sm">{{ $count }} {{ Str::plural('submission', $count) }}</span>
                                    </div>
                                    <div class="w-full h-6 overflow-hidden rounded-lg bg-base-300">
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
                                        <div class="h-full {{ $colors[$range] }} flex items-center px-3" style="width: {{ $percentage }}%">
                                            @if($percentage > 20)
                                                <span class="text-xs font-bold text-white">{{ $count }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Stats Summary -->
                    <div class="flex flex-col justify-center">
                        <div class="shadow stats">
                            <div class="stat">
                                <div class="stat-title">Total Submissions</div>
                                <div class="stat-value">{{ $stats['completed_submissions'] }}</div>
                                <div class="stat-desc">Out of {{ $stats['total_submissions'] }} assigned</div>
                            </div>

                            <div class="stat">
                                <div class="stat-title">Average Score</div>
                                <div class="stat-value">{{ $stats['average_score'] }}</div>
                                <div class="stat-desc">Out of {{ $assessment->total_points }}</div>
                            </div>

                            @if($assessment->passing_points)
                                <div class="stat">
                                    <div class="stat-title">Passing Rate</div>
                                    <div class="stat-value text-{{ $stats['passing_rate'] >= 70 ? 'success' : ($stats['passing_rate'] >= 50 ? 'warning' : 'error') }}">{{ $stats['passing_rate'] }}%</div>
                                    <div class="stat-desc">Threshold: {{ $assessment->passing_points }} points</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center p-8 rounded-lg bg-base-200">
                    <x-icon name="o-chart-bar" class="w-14 h-14 text-base-content/20" />
                    <h3 class="mt-4 text-lg font-medium">No data available</h3>
                    <p class="mt-2 text-center text-base-content/70">
                        Once students start submitting their assessments, score distribution will appear here.
                    </p>
                </div>
            @endif
        </div>

        <!-- Question Analysis -->
        <div class="p-6 bg-white shadow-md rounded-xl">
            <h2 class="mb-4 text-xl font-bold">Question Analysis</h2>

            @if(count($questionPerformance) > 0)
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th class="text-center">Success Rate</th>
                                <th class="text-center">Difficulty</th>
                                <th class="text-center">Attempts</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($questionPerformance as $question)
                                <tr>
                                    <td class="max-w-md">
                                        <div class="font-medium">{{ $question['text'] }}</div>
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-center">
                                            <div class="radial-progress text-{{
                                                $question['percentage'] >= 70 ? 'success' :
                                                ($question['percentage'] >= 40 ? 'warning' : 'error') }}"
                                                style="--value:{{ $question['percentage'] }}; --size: 2.5rem; --thickness: 3px;">
                                                <span class="text-xs">{{ $question['percentage'] }}%</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="badge {{
                                            $question['percentage'] >= 70 ? 'badge-success' :
                                            ($question['percentage'] >= 40 ? 'badge-warning' : 'badge-error') }}">
                                            {{
                                                $question['percentage'] >= 70 ? 'Easy' :
                                                ($question['percentage'] >= 40 ? 'Medium' : 'Hard')
                                            }}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div>{{ $question['attempted'] }}</div>
                                        <div class="text-xs opacity-70">{{ $question['correct'] }} correct</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center p-8 rounded-lg bg-base-200">
                    <x-icon name="o-question-mark-circle" class="w-14 h-14 text-base-content/20" />
                    <h3 class="mt-4 text-lg font-medium">No question data yet</h3>
                    <p class="mt-2 text-center text-base-content/70">
                        Question performance analysis will be available after students submit their answers.
                    </p>
                </div>
            @endif
        </div>

        <!-- Participant Performance -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Top Performers -->
            <div class="p-6 bg-white shadow-md rounded-xl">
                <h2 class="mb-4 text-xl font-bold">Top Performers</h2>

                @if(count($participantPerformance) > 0)
                    <div class="space-y-3">
                        @foreach($participantPerformance as $index => $performer)
                            <div class="flex items-center p-3 {{ $index === 0 ? 'bg-amber-50 border border-amber-200' : 'bg-base-200' }} rounded-lg">
                                <div class="avatar placeholder">
                                    <div class="w-10 h-10 rounded-full bg-neutral text-neutral-content">
                                        <span class="text-lg">{{ substr($performer['name'], 0, 1) }}</span>
                                    </div>
                                </div>
                                <div class="flex-1 ml-3">
                                    <div class="flex items-center">
                                        <span class="font-medium">{{ $performer['name'] }}</span>
                                        @if($index === 0)
                                            <div class="gap-1 ml-2 badge badge-warning">
                                                <x-icon name="o-trophy" class="w-3 h-3" />
                                                Top
                                            </div>
                                        @endif
                                    </div>
                                    <span class="text-xs text-base-content/70">{{ ucfirst($performer['type']) }} · {{ $performer['date'] }}</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold">{{ $performer['percentage'] }}%</div>
                                    <div class="text-xs text-base-content/70">{{ $performer['score'] }}/{{ $assessment->total_points }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @else
                    <div class="flex flex-col items-center justify-center p-8 rounded-lg bg-base-200">
                        <p class="text-base-content/70">No submissions yet</p>
                    </div>
                @endif
            </div>

            <!-- Fastest Completions -->
            <div class="p-6 bg-white shadow-md rounded-xl">
                <h2 class="mb-4 text-xl font-bold">Fastest Completions</h2>

                @if(count($timeCompletion) > 0)
                    <div class="space-y-3">
                        @foreach($timeCompletion as $index => $completion)
                            <div class="flex items-center p-3 {{ $index === 0 ? 'bg-blue-50 border border-blue-200' : 'bg-base-200' }} rounded-lg">
                                <div class="avatar placeholder">
                                    <div class="w-10 h-10 rounded-full bg-info text-info-content">
                                        <span class="text-lg">{{ substr($completion['name'], 0, 1) }}</span>
                                    </div>
                                </div>
                                <div class="flex-1 ml-3">
                                    <div class="font-medium">{{ $completion['name'] }}</div>
                                    <span class="text-xs text-base-content/70">{{ $completion['date'] }}</span>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold">{{ $completion['minutes'] }} min</div>
                                    <div class="text-xs text-base-content/70">completion time</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center p-8 rounded-lg bg-base-200">
                        <p class="text-base-content/70">No completions yet</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Export Options -->
        <div class="flex justify-end">
            <button wire:click="downloadReport" class="btn btn-primary">
                <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                Export Full Report
            </button>
        </div>
    </div>
</div>

<!-- Settings Tab -->
<div class="{{ $activeTab === 'settings' ? 'block' : 'hidden' }}">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <!-- Assessment Settings -->
        <div class="p-6 bg-white shadow-md rounded-xl">
            <h2 class="mb-4 text-xl font-bold">Assessment Settings</h2>

            <div class="space-y-4">
                <div>
                    <h3 class="mb-2 font-medium">Basic Settings</h3>
                    <div class="p-4 space-y-3 rounded-lg bg-base-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">Shuffle Questions</div>
                                <div class="text-xs text-base-content/70">Random order for each student</div>
                            </div>
                            <input type="checkbox" class="toggle toggle-primary" disabled
                                {{ isset($assessment->settings['shuffle_questions']) && $assessment->settings['shuffle_questions'] ? 'checked' : '' }} />
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">Show Correct Answers</div>
                                <div class="text-xs text-base-content/70">After submission</div>
                            </div>
                            <input type="checkbox" class="toggle toggle-primary" disabled
                                {{ isset($assessment->settings['show_correct_answers']) && $assessment->settings['show_correct_answers'] ? 'checked' : '' }} />
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">Allow Retakes</div>
                                <div class="text-xs text-base-content/70">Multiple attempts</div>
                            </div>
                            <input type="checkbox" class="toggle toggle-primary" disabled
                                {{ isset($assessment->settings['allow_retakes']) && $assessment->settings['allow_retakes'] ? 'checked' : '' }} />
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-medium">Timing & Points</h3>
                    <div class="p-4 space-y-3 rounded-lg bg-base-200">
                        <div class="flex justify-between">
                            <span class="text-base-content/70">Time Limit:</span>
                            <span class="font-medium">{{ $assessment->time_limit ? $assessment->time_limit . ' minutes' : 'No limit' }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-base-content/70">Total Points:</span>
                            <span class="font-medium">{{ $assessment->total_points }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-base-content/70">Passing Score:</span>
                            <span class="font-medium">{{ $assessment->passing_points ?? 'Not set' }}</span>
                        </div>
                    </div>
                </div>

                <a href="{{ route('teachers.assessments.create') }}" class="w-full btn btn-outline">
                    <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                    Edit Settings
                </a>
            </div>
        </div>

        <!-- Publication Settings -->
        <div class="p-6 bg-white shadow-md rounded-xl">
            <h2 class="mb-4 text-xl font-bold">Publication Settings</h2>

            <div class="space-y-4">
                <div>
                    <h3 class="mb-2 font-medium">Availability</h3>
                    <div class="p-4 space-y-3 rounded-lg bg-base-200">
                        <div class="flex justify-between">
                            <span class="text-base-content/70">Status:</span>
                            <span class="font-medium {{ $assessment->is_published ? 'text-success' : 'text-warning' }}">
                                {{ $assessment->is_published ? 'Published' : 'Draft' }}
                            </span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-base-content/70">Start Date:</span>
                            <span class="font-medium">{{ $assessment->start_date ? $assessment->start_date->format('M d, Y g:i A') : 'Not set' }}</span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-base-content/70">Due Date:</span>
                            <span class="font-medium">{{ $assessment->due_date ? $assessment->due_date->format('M d, Y g:i A') : 'No deadline' }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-medium">Visibility</h3>
                    <div class="p-4 space-y-3 rounded-lg bg-base-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">Visible to Students</div>
                                <div class="text-xs text-base-content/70">{{ $assessment->is_published ? 'Students can see this assessment' : 'Hidden from students' }}</div>
                            </div>
                            <input type="checkbox" class="toggle toggle-primary" disabled {{ $assessment->is_published ? 'checked' : '' }} />
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button wire:click="showStatusSettings" class="flex-1 btn btn-outline">
                        <x-icon name="o-clock" class="w-4 h-4 mr-2" />
                        Schedule
                    </button>

                    <button wire:click="togglePublishStatus" class="btn {{ $assessment->is_published ? 'btn-warning' : 'btn-success' }} flex-1">
                        @if($assessment->is_published)
                            <x-icon name="o-eye-slash" class="w-4 h-4 mr-2" />
                            Unpublish
                        @else
                            <x-icon name="o-paper-airplane" class="w-4 h-4 mr-2" />
                            Publish
                        @endif
                    </button>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="p-6 bg-white border-t-4 shadow-md md:col-span-2 rounded-xl border-error">
            <h2 class="mb-4 text-xl font-bold">Danger Zone</h2>

            <div class="p-4 rounded-lg bg-base-200">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium">Delete Assessment</div>
                        <div class="text-xs text-base-content/70">This action cannot be undone. All data will be permanently lost.</div>
                    </div>
                    <button wire:click="confirmDelete" class="btn btn-error">
                        <x-icon name="o-trash" class="w-4 h-4 mr-2" />
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
</div>

<!-- Modals -->
<!-- Delete Confirmation Modal -->
<div class="modal {{ $showDeleteModal ? 'modal-open' : '' }}">
    <div class="modal-box">
        <h3 class="text-lg font-bold text-error">Delete Assessment?</h3>
        <p class="py-4">Are you sure you want to delete this assessment? This action cannot be undone and will remove all associated questions and submissions.</p>
        <div class="modal-action">
            <button wire:click="$set('showDeleteModal', false)" class="btn">Cancel</button>
            <button wire:click="deleteAssessment" class="btn btn-error">Delete Permanently</button>
        </div>
    </div>
</div>

<!-- Publish Schedule Modal -->
<div class="modal {{ $showStatusModal ? 'modal-open' : '' }}">
    <div class="modal-box">
        <h3 class="text-lg font-bold">Schedule Assessment</h3>
        <p class="py-2">Set when this assessment will be available to students.</p>

        <div class="mt-4 space-y-4">
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Start Date & Time</span>
                </label>
                <input type="datetime-local" wire:model="publishStartDate" class="input input-bordered" />
            </div>

            <div class="form-control">
                <label class="label">
                    <span class="label-text">Due Date & Time (Optional)</span>
                </label>
                <input type="datetime-local" wire:model="publishEndDate" class="input input-bordered" />
            </div>
        </div>

        <div class="modal-action">
            <button wire:click="$set('showStatusModal', false)" class="btn">Cancel</button>
            <button wire:click="updatePublishSettings" class="btn btn-primary">
                <x-icon name="o-calendar" class="w-4 h-4 mr-2" />
                Schedule & Publish
            </button>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal {{ $showShareModal ? 'modal-open' : '' }}">
    <div class="modal-box">
        <h3 class="text-lg font-bold">Share Assessment</h3>
        <p class="py-2">Choose how you want to share this assessment with students.</p>

        <div class="mt-4 space-y-4">
            <div class="p-4 rounded-lg bg-base-200">
                <div class="tabs tabs-boxed bg-base-300">
                    <a class="tab {{ $sharingOptions['email'] ? 'tab-active' : '' }}" wire:click="$set('sharingOptions.email', true); $set('sharingOptions.link', false); $set('sharingOptions.qr', false);">Email</a>
                    <a class="tab {{ $sharingOptions['link'] ? 'tab-active' : '' }}" wire:click="$set('sharingOptions.email', false); $set('sharingOptions.link', true); $set('sharingOptions.qr', false);">Link</a>
                    <a class="tab {{ $sharingOptions['qr'] ? 'tab-active' : '' }}" wire:click="$set('sharingOptions.email', false); $set('sharingOptions.link', false); $set('sharingOptions.qr', true);">QR Code</a>
                </div>

                <div class="mt-4">
                    @if($sharingOptions['email'])
                        <div>
                            <p class="mb-2 text-sm">Send assessment directly to participants via email</p>
                            <button class="w-full btn btn-primary" wire:click="$set('showNotificationModal', true); $set('showShareModal', false);">
                                <x-icon name="o-envelope" class="w-4 h-4 mr-2" />
                                Compose Email
                            </button>
                        </div>
                    @elseif($sharingOptions['link'])
                        <div>
                            <p class="mb-2 text-sm">Share a direct link to the assessment</p>
                            <div class="flex">
                                <input type="text" readonly value="https://example.com/assessments/{{ $assessment->id }}/take" class="w-full input input-bordered" />
                                <button class="ml-2 btn btn-square btn-outline">
                                    <x-icon name="o-clipboard" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @elseif($sharingOptions['qr'])
                        <div class="flex flex-col items-center">
                            <p class="mb-2 text-sm">Scan this QR code to access the assessment</p>
                            <div class="p-4 bg-white rounded-lg">
                                <!-- Placeholder for QR code -->
                                <div class="flex items-center justify-center w-48 h-48 bg-base-300">
                                    <x-icon name="o-qr-code" class="w-32 h-32 text-base-content/20" />
                                </div>
                            </div>
                            <button class="mt-2 btn btn-outline">
                                <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                                Download QR Code
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <h4 class="mb-2 font-medium">Advanced Options</h4>
                <div class="form-control">
                    <label class="justify-start cursor-pointer label">
                        <input type="checkbox" class="checkbox checkbox-primary" />
                        <span class="ml-2 label-text">Allow guest access (no login required)</span>
                    </label>
                </div>
                <div class="form-control">
                    <label class="justify-start cursor-pointer label">
                        <input type="checkbox" class="checkbox checkbox-primary" />
                        <span class="ml-2 label-text">Require access code</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="modal-action">
            <button wire:click="$set('showShareModal', false)" class="btn">Close</button>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div class="modal {{ $showNotificationModal ? 'modal-open' : '' }}">
    <div class="modal-box">
        <h3 class="text-lg font-bold">Notify Participants</h3>
        <p class="py-2">Send a notification to all participants about this assessment.</p>

        <div class="mt-4 space-y-4">
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Message</span>
                </label>
                <textarea wire:model="notificationMessage" class="h-32 textarea textarea-bordered" placeholder="Write your message to participants here..."></textarea>
                @error('notificationMessage') <span class="text-sm text-error">{{ $message }}</span> @enderror
            </div>

            <div class="p-3 rounded-lg bg-base-200">
                <div class="font-medium">Recipients</div>
                <div class="mt-1 text-sm">{{ $stats['total_participants'] }} participant(s) will receive this notification.</div>
            </div>
        </div>

        <div class="modal-action">
            <button wire:click="$set('showNotificationModal', false)" class="btn">Cancel</button>
            <button wire:click="sendNotification" class="btn btn-primary">
                <x-icon name="o-paper-airplane" class="w-4 h-4 mr-2" />
                Send Notification
            </button>
        </div>
    </div>
</div>
