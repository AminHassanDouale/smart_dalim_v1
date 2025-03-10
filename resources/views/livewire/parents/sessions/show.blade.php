<?php

use Livewire\Volt\Component;
use App\Models\LearningSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public $session;
    public $relatedSessions = [];
    public $nextSession = null;
    public $previousSession = null;

    // Feedback and ratings
    public $showFeedbackModal = false;
    public $feedbackContent = '';
    public $teacherRating = 0;
    public $overallRating = 0;
    public $understandingRating = 0;
    public $engagementRating = 0;

    // Notes
    public $showAddNoteModal = false;
    public $noteContent = '';
    public $notes = [];

    // Materials and resources
    public $materials = [];

    // Assignments
    public $assignments = [];

    public function mount($session)
    {
        // Load the session with relationships
        $this->session = LearningSession::with([
            'teacher',
            'children',
            'subject',
            'course'
        ])->findOrFail($session);

        // Security check - ensure the parent has access to this session
        $parent = Auth::user()->parentProfile;
        abort_if(!$parent || !$parent->children()->where('id', $this->session->children_id)->exists(), 403);

        // Load related sessions (same subject, same child)
        $this->loadRelatedSessions();

        // Load next and previous sessions
        $this->loadNextPreviousSessions();

        // Load mock feedback, notes, materials, and assignments
        $this->loadMockData();
    }

    private function loadRelatedSessions()
    {
        // Get other sessions for the same child with the same subject
        $this->relatedSessions = LearningSession::where('children_id', $this->session->children_id)
            ->where('subject_id', $this->session->subject_id)
            ->where('id', '!=', $this->session->id)
            ->orderBy('start_time', 'desc')
            ->take(5)
            ->get();
    }

    private function loadNextPreviousSessions()
    {
        // Get the next scheduled session for this child
        $this->nextSession = LearningSession::where('children_id', $this->session->children_id)
            ->where('start_time', '>', $this->session->start_time)
            ->orderBy('start_time', 'asc')
            ->first();

        // Get the previous session for this child
        $this->previousSession = LearningSession::where('children_id', $this->session->children_id)
            ->where('start_time', '<', $this->session->start_time)
            ->orderBy('start_time', 'desc')
            ->first();
    }

    private function loadMockData()
    {
        // Mock feedback data
        if ($this->session->status === 'completed') {
            $this->feedbackContent = 'Your child showed good understanding of the core concepts today. They participated actively and completed all assigned tasks. We focused on problem-solving strategies and critical thinking. For next session, please review the practice worksheets provided.';
            $this->teacherRating = rand(4, 5);
            $this->overallRating = rand(4, 5);
            $this->understandingRating = rand(3, 5);
            $this->engagementRating = rand(3, 5);
        }

        // Mock notes
        $this->notes = [
            [
                'id' => 1,
                'content' => 'Child showed improvement in understanding complex problems.',
                'created_at' => Carbon::parse($this->session->start_time)->subHours(2),
                'author' => 'Teacher',
            ],
            [
                'id' => 2,
                'content' => 'Needs more practice with the concepts covered today.',
                'created_at' => Carbon::parse($this->session->start_time),
                'author' => 'Teacher',
            ]
        ];

        // Mock materials
        $this->materials = [
            [
                'id' => 1,
                'title' => 'Lesson Worksheet',
                'type' => 'pdf',
                'size' => '420 KB',
                'uploaded_at' => Carbon::parse($this->session->start_time),
            ],
            [
                'id' => 2,
                'title' => 'Practice Problems',
                'type' => 'pdf',
                'size' => '320 KB',
                'uploaded_at' => Carbon::parse($this->session->start_time),
            ],
            [
                'id' => 3,
                'title' => 'Visual Aids',
                'type' => 'pptx',
                'size' => '2.4 MB',
                'uploaded_at' => Carbon::parse($this->session->start_time),
            ]
        ];

        // Mock assignments
        if ($this->session->status === 'completed') {
            $this->assignments = [
                [
                    'id' => 1,
                    'title' => 'Practice Worksheet',
                    'due_date' => Carbon::parse($this->session->end_time)->addDays(3),
                    'status' => 'pending',
                    'description' => 'Complete all problems on pages 1-2 of the practice worksheet.'
                ],
                [
                    'id' => 2,
                    'title' => 'Reading Assignment',
                    'due_date' => Carbon::parse($this->session->end_time)->addDays(5),
                    'status' => 'pending',
                    'description' => 'Read chapter 3 and prepare notes for discussion in the next session.'
                ]
            ];
        }
    }

    public function openFeedbackModal()
    {
        $this->showFeedbackModal = true;
    }

    public function closeFeedbackModal()
    {
        $this->showFeedbackModal = false;
    }

    public function submitFeedback()
    {
        // Validate feedback
        $this->validate([
            'feedbackContent' => 'required|min:10',
            'teacherRating' => 'required|integer|min:1|max:5',
            'overallRating' => 'required|integer|min:1|max:5',
            'understandingRating' => 'required|integer|min:1|max:5',
            'engagementRating' => 'required|integer|min:1|max:5',
        ]);

        // In a real app, save feedback to database
        // For demo, just show a toast
        $this->toast(
            type: 'success',
            title: 'Feedback Submitted',
            description: 'Thank you for providing your feedback!',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeFeedbackModal();
    }

    public function openAddNoteModal()
    {
        $this->showAddNoteModal = true;
        $this->noteContent = '';
    }

    public function closeAddNoteModal()
    {
        $this->showAddNoteModal = false;
    }

    public function addNote()
    {
        // Validate note
        $this->validate([
            'noteContent' => 'required|min:5',
        ]);

        // In a real app, save note to database
        // For demo, add to local array
        $this->notes[] = [
            'id' => count($this->notes) + 1,
            'content' => $this->noteContent,
            'created_at' => Carbon::now(),
            'author' => 'Parent',
        ];

        $this->toast(
            type: 'success',
            title: 'Note Added',
            description: 'Your note has been added successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeAddNoteModal();
    }

    public function rescheduleSession()
    {
        // In a real app, show reschedule form or redirect
        // For demo, just show a toast
        $this->toast(
            type: 'info',
            title: 'Reschedule Session',
            description: 'You would be redirected to the rescheduling form.',
            position: 'toast-bottom toast-end',
            icon: 'o-calendar',
            css: 'alert-info',
            timeout: 3000
        );
    }

    public function confirmCancelSession()
    {
        $this->toast(
            type: 'warning',
            title: 'Confirm Cancellation',
            description: 'Are you sure you want to cancel this session? This cannot be undone.',
            position: 'toast-bottom toast-end',
            icon: 'o-exclamation-triangle',
            css: 'alert-warning',
            timeout: false,
            action: [
                'label' => 'Yes, Cancel',
                'onClick' => '$wire.cancelSession()'
            ]
        );
    }

    public function cancelSession()
    {
        // In a real app, update session status
        // For demo, update local model
        $this->session->status = 'cancelled';

        $this->toast(
            type: 'success',
            title: 'Session Cancelled',
            description: 'The session has been successfully cancelled.',
            position: 'toast-bottom toast-end',
            icon: 'o-x-circle',
            css: 'alert-success',
            timeout: 3000
        );
    }

    public function markAssignmentComplete($assignmentId)
    {
        // Update assignment status in the local array
        foreach ($this->assignments as $key => $assignment) {
            if ($assignment['id'] === $assignmentId) {
                $this->assignments[$key]['status'] = 'completed';
                break;
            }
        }

        $this->toast(
            type: 'success',
            title: 'Assignment Completed',
            description: 'The assignment has been marked as completed.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );
    }

    public function contactTeacher()
    {
        // In a real app, open message composer or redirect
        // For demo, just show a toast
        $this->toast(
            type: 'info',
            title: 'Contact Teacher',
            description: 'You would be redirected to the messaging interface.',
            position: 'toast-bottom toast-end',
            icon: 'o-chat-bubble-left-right',
            css: 'alert-info',
            timeout: 3000
        );
    }

    public function downloadMaterial($materialId)
    {
        // In a real app, trigger download
        // For demo, just show a toast
        $material = collect($this->materials)->firstWhere('id', $materialId);

        $this->toast(
            type: 'success',
            title: 'Download Started',
            description: 'Downloading ' . $material['title'] . '...',
            position: 'toast-bottom toast-end',
            icon: 'o-arrow-down-tray',
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

    public function formatTime($dateTime)
    {
        return Carbon::parse($dateTime)->format('g:i A');
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

    public function getSessionStatusColor()
    {
        switch ($this->session->status) {
            case 'scheduled':
                return 'bg-primary text-primary-content';
            case 'completed':
                return 'bg-success text-success-content';
            case 'cancelled':
                return 'bg-error text-error-content';
            default:
                return 'bg-info text-info-content';
        }
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

<div x-data="{
    activeTab: 'overview',
    setActiveTab(tab) {
        this.activeTab = tab;
    }
}" class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Back Navigation -->
        <div class="mb-6">
            <a href="{{ route('sessions.index') }}" class="flex items-center gap-1 btn btn-ghost btn-sm">
                <x-icon name="o-arrow-left" class="w-4 h-4" />
                Back to Sessions
            </a>
        </div>

        <!-- Session Hero Banner -->
        <div class="mb-8 overflow-hidden shadow-lg rounded-xl {{ $this->getSessionStatusColor() }}">
            <div class="relative p-8">
                <!-- Abstract background pattern -->
                <div class="absolute inset-0 opacity-10">
                    <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                        <defs>
                            <pattern id="pattern" width="100" height="100" patternUnits="userSpaceOnUse">
                                <circle cx="50" cy="50" r="40" fill="none" stroke="currentColor" stroke-width="2" />
                                <circle cx="0" cy="0" r="20" fill="none" stroke="currentColor" stroke-width="2" />
                                <circle cx="100" cy="100" r="20" fill="none" stroke="currentColor" stroke-width="2" />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#pattern)" />
                    </svg>
                </div>

                <div class="relative flex flex-col gap-8 md:flex-row md:items-center">
                    <div class="flex-1">
                        <div class="inline-block px-3 py-1 mb-3 text-sm font-medium border rounded-full border-opacity-20 border-primary-content">
                            {{ ucfirst($session->status) }} Session
                        </div>
                        <h1 class="text-3xl font-bold">{{ $session->subject->name }} Session</h1>
                        <div class="mt-2 text-xl">with {{ $session->teacher->name }}</div>

                        <div class="flex flex-wrap mt-4 gap-x-6 gap-y-2">
                            <div class="flex items-center gap-2">
                                <x-icon name="o-calendar" class="w-5 h-5 opacity-80" />
                                <span>{{ $this->formatDate($session->start_time) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-icon name="o-clock" class="w-5 h-5 opacity-80" />
                                <span>{{ $this->formatTime($session->start_time) }} - {{ $this->formatTime($session->end_time) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-icon name="o-map-pin" class="w-5 h-5 opacity-80" />
                                <span>{{ $session->location ?? 'Online' }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-6">
                            @if($session->status === 'scheduled')
                                <button wire:click="rescheduleSession" class="btn">
                                    <x-icon name="o-arrow-path" class="w-4 h-4 mr-1" />
                                    Reschedule
                                </button>
                                <button wire:click="confirmCancelSession" class="text-white bg-opacity-20 bg-error/20 hover:bg-error/30 btn">
                                    <x-icon name="o-x-circle" class="w-4 h-4 mr-1" />
                                    Cancel Session
                                </button>
                            @elseif($session->status === 'completed')
                                <button wire:click="openFeedbackModal" class="btn">
                                    <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 mr-1" />
                                    {{ $feedbackContent ? 'View Feedback' : 'Add Feedback' }}
                                </button>
                            @endif
                            <button wire:click="contactTeacher" class="border-opacity-20 border-primary-content btn">
                                <x-icon name="o-envelope" class="w-4 h-4 mr-1" />
                                Contact Teacher
                            </button>
                        </div>
                    </div>

                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center w-32 h-32 mx-auto rounded-full select-none bg-opacity-20 bg-primary-content">
                            @if($session->status === 'completed' && $session->performance_score !== null)
                                <!-- Performance Score -->
                                <div class="flex flex-col items-center justify-center">
                                    <div class="text-4xl font-bold">{{ number_format($session->performance_score, 1) }}</div>
                                    <div class="text-sm font-medium">Performance</div>
                                </div>
                            @elseif($session->status === 'scheduled')
                                <!-- Countdown or Time -->
                                <div class="flex flex-col items-center justify-center">
                                    @if(Carbon::parse($session->start_time)->isFuture())
                                        @php
                                            $diff = Carbon::now()->diff(Carbon::parse($session->start_time));
                                            $daysDiff = Carbon::now()->diffInDays(Carbon::parse($session->start_time));
                                        @endphp

                                        @if($daysDiff > 0)
                                            <div class="text-4xl font-bold">{{ $daysDiff }}</div>
                                            <div class="text-sm font-medium">{{ $daysDiff === 1 ? 'Day' : 'Days' }}</div>
                                        @else
                                            <div class="text-4xl font-bold">{{ $diff->format('%h') }}</div>
                                            <div class="text-sm font-medium">{{ $diff->format('%h') === '1' ? 'Hour' : 'Hours' }}</div>
                                        @endif
                                    @else
                                        <x-icon name="o-clock" class="w-16 h-16" />
                                    @endif
                                </div>
                            @elseif($session->status === 'cancelled')
                                <!-- Cancelled Icon -->
                                <x-icon name="o-x-circle" class="w-16 h-16" />
                            @else
                                <!-- Subject Icon -->
                                <x-icon name="o-academic-cap" class="w-16 h-16" />
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="mb-6 tabs tabs-boxed">
            <a
                @click="setActiveTab('overview')"
                :class="{ 'tab-active': activeTab === 'overview' }"
                class="tab"
            >
                Overview
            </a>
            <a
                @click="setActiveTab('materials')"
                :class="{ 'tab-active': activeTab === 'materials' }"
                class="tab"
            >
                Materials
            </a>
            <a
                @click="setActiveTab('assignments')"
                :class="{ 'tab-active': activeTab === 'assignments' }"
                class="tab {{
                    count($assignments) > 0 ? 'indicator-item' : ''
                }}"
            >
                Assignments
                @if(count($assignments) > 0)
                    <span class="badge badge-sm">{{ count($assignments) }}</span>
                @endif
            </a>
            <a
                @click="setActiveTab('notes')"
                :class="{ 'tab-active': activeTab === 'notes' }"
                class="tab"
            >
                Notes
            </a>
            <a
                @click="setActiveTab('progress')"
                :class="{ 'tab-active': activeTab === 'progress' }"
                class="tab"
            >
                Progress
            </a>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
            <!-- Left Column (Main Content) -->
            <div class="md:col-span-2">
                <!-- Overview Tab -->
                <div x-show="activeTab === 'overview'">
                    <div class="space-y-8">
                        <!-- Session Details Card -->
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="card-title">Session Details</h2>

                                <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-2">
                                    <div>
                                        <div class="mb-3">
                                            <div class="text-sm font-medium opacity-70">Subject</div>
                                            <div class="text-lg">{{ $session->subject->name }}</div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="text-sm font-medium opacity-70">Teacher</div>
                                            <div class="text-lg">{{ $session->teacher->name }}</div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="text-sm font-medium opacity-70">Child</div>
                                            <div class="text-lg">{{ $session->children->name }}</div>
                                        </div>

                                        @if($session->course)
                                            <div class="mb-3">
                                                <div class="text-sm font-medium opacity-70">Course</div>
                                                <div class="text-lg">{{ $session->course->name }}</div>
                                            </div>
                                        @endif
                                    </div>

                                    <div>
                                        <div class="mb-3">
                                            <div class="text-sm font-medium opacity-70">Date and Time</div>
                                            <div class="text-lg">{{ $this->formatDateTime($session->start_time) }}</div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="text-sm font-medium opacity-70">Duration</div>
                                            <div class="text-lg">{{ $this->formatDuration($session->start_time, $session->end_time) }}</div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="text-sm font-medium opacity-70">Location</div>
                                            <div class="text-lg">{{ $session->location ?? 'Online' }}</div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="text-sm font-medium opacity-70">Status</div>
                                            <div class="text-lg">
                                                <div class="badge {{
                                                    $session->status === 'completed' ? 'badge-success' :
                                                    ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                                }}">
                                                    {{ ucfirst($session->status) }}
                                                </div>

                                                @if($session->status === 'completed')
                                                    <div class="badge {{ $session->attended ? 'badge-success' : 'badge-error' }} ml-2">
                                                        {{ $session->attended ? 'Attended' : 'Absent' }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Teacher Feedback Card -->
                        @if($session->status === 'completed')
                            <div class="shadow-xl card bg-base-100">
                                <div class="card-body">
                                    <h2 class="card-title">Teacher's Feedback</h2>

                                    @if($feedbackContent)
                                        <div class="p-4 mt-4 bg-base-200 rounded-box">
                                            <p>{{ $feedbackContent }}</p>
                                        </div>

                                        <div class="grid grid-cols-1 gap-6 mt-6 md:grid-cols-2">
                                            <div>
                                                <div class="font-medium">Performance Areas</div>
                                                <div class="mt-2 space-y-3">
                                                    <div>
                                                        <div class="flex items-center justify-between mb-1">
                                                            <span class="text-sm">Overall Performance</span>
                                                            <span class="text-sm font-medium">{{ $overallRating }}/5</span>
                                                        </div>
                                                        <div class="w-full h-2 rounded-full bg-base-300">
                                                            <div class="h-full rounded-full bg-primary" style="width: {{ ($overallRating / 5) * 100 }}%"></div>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <div class="flex items-center justify-between mb-1">
                                                            <span class="text-sm">Understanding</span>
                                                            <span class="text-sm font-medium">{{ $understandingRating }}/5</span>
                                                        </div>
                                                        <div class="w-full h-2 rounded-full bg-base-300">
                                                            <div class="h-full rounded-full bg-info" style="width: {{ ($understandingRating / 5) * 100 }}%"></div>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <div class="flex items-center justify-between mb-1">
                                                            <span class="text-sm">Engagement</span>
                                                            <span class="text-sm font-medium">{{ $engagementRating }}/5</span>
                                                        </div>
                                                        <div class="w-full h-2 rounded-full bg-base-300">
                                                            <div class="h-full rounded-full bg-success" style="width: {{ ($engagementRating / 5) * 100 }}%"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div>
                                                @if($session->performance_score !== null)
                                                    <div class="font-medium">Session Score</div>
                                                    <div class="flex items-center mt-2">
                                                        <div class="radial-progress {{
                                                            $session->performance_score >= 8 ? 'text-success' :
                                                            ($session->performance_score >= 6 ? 'text-warning' : 'text-error')
                                                        }}" style="--value:{{ $session->performance_score * 10 }}; --size:4rem; --thickness: 0.5rem;">
                                                            {{ number_format($session->performance_score, 1) }}
                                                        </div>

                                                        <div class="ml-4">
                                                            <div class="font-medium">Performance Score</div>
                                                            <div class="text-sm opacity-70">Out of 10 points</div>

                                                            <div class="mt-2">
                                                                <span class="{{
                                                                    $session->performance_score >= 8 ? 'text-success' :
                                                                    ($session->performance_score >= 6 ? 'text-warning' : 'text-error')
                                                                }}">
                                                                    {{
                                                                        $session->performance_score >= 8 ? 'Excellent' :
                                                                        ($session->performance_score >= 6 ? 'Good' : 'Needs Improvement')
                                                                    }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="p-6 mt-2 text-center bg-base-200 rounded-box">
                                            <p>No feedback has been provided for this session yet.</p>
                                            <button wire:click="openFeedbackModal" class="mt-3 btn btn-primary btn-sm">
                                                Add Feedback
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Session Agenda Card (if available) -->
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="card-title">Session Agenda</h2>

                                <div class="p-4 mt-4 space-y-4 bg-base-200 rounded-box">
                                    <div class="flex gap-3">
                                        <div class="flex-shrink-0 mt-1">
                                            <div class="flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full bg-primary text-primary-content">1</div>
                                        </div>
                                        <div>
                                            <div class="font-medium">Introduction and Review</div>
                                            <p class="text-sm mt-0.5">Review of previous concepts and introduction to new material.</p>
                                        </div>
                                    </div>

                                    <div class="flex gap-3">
                                        <div class="flex-shrink-0 mt-1">
                                            <div class="flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full bg-primary text-primary-content">2</div>
                                        </div>
                                        <div>
                                            <div class="font-medium">Core Lesson Content</div>
                                            <p class="text-sm mt-0.5">Presentation and explanation of new concepts.</p>
                                        </div>
                                    </div>

                                    <div class="flex gap-3">
                                        <div class="flex-shrink-0 mt-1">
                                            <div class="flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full bg-primary text-primary-content">3</div>
                                        </div>
                                        <div>
                                            <div class="font-medium">Practice and Application</div>
                                            <p class="text-sm mt-0.5">Hands-on activities and problem-solving exercises.</p>
                                        </div>
                                    </div>

                                    <div class="flex gap-3">
                                        <div class="flex-shrink-0 mt-1">
                                            <div class="flex items-center justify-center w-6 h-6 text-sm font-bold rounded-full bg-primary text-primary-content">4</div>
                                        </div>
                                        <div>
                                            <div class="font-medium">Assessment and Conclusion</div>
                                            <p class="text-sm mt-0.5">Quick assessment of understanding and summary of key points.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Learning Objectives Card -->
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="card-title">Learning Objectives</h2>

                                <div class="mt-4 space-y-3">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-check-circle" class="flex-shrink-0 w-5 h-5 text-success" />
                                        <span>Understand core concepts related to {{ $session->subject->name }}</span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-check-circle" class="flex-shrink-0 w-5 h-5 text-success" />
                                        <span>Apply problem-solving strategies to practical examples</span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-check-circle" class="flex-shrink-0 w-5 h-5 text-success" />
                                        <span>Develop critical thinking skills through analysis</span>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-check-circle" class="flex-shrink-0 w-5 h-5 text-success" />
                                        <span>Build confidence in subject matter mastery</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Materials Tab -->
                <div x-show="activeTab === 'materials'" x-cloak>
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Session Materials</h2>

                            @if(count($materials) > 0)
                                <div class="overflow-x-auto">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Size</th>
                                                <th>Uploaded</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($materials as $material)
                                                <tr>
                                                    <td class="font-medium">{{ $material['title'] }}</td>
                                                    <td>
                                                        <div class="flex items-center gap-1">
                                                            <x-icon name="{{
                                                                $material['type'] === 'pdf' ? 'o-document' :
                                                                ($material['type'] === 'pptx' ? 'o-presentation-chart-bar' : 'o-document-text')
                                                            }}" class="w-4 h-4" />
                                                            <span>{{ strtoupper($material['type']) }}</span>
                                                        </div>
                                                    </td>
                                                    <td>{{ $material['size'] }}</td>
                                                    <td>{{ $this->formatDate($material['uploaded_at']) }}</td>
                                                    <td>
                                                        <button wire:click="downloadMaterial({{ $material['id'] }})" class="btn btn-sm btn-ghost">
                                                            <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                                            Download
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="p-6 mt-2 text-center bg-base-200 rounded-box">
                                    <p>No materials have been provided for this session yet.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Recommended Resources</h2>

                            <div class="mt-4 space-y-4">
                                <div class="p-3 bg-base-200 rounded-box">
                                    <div class="flex items-start gap-3">
                                        <x-icon name="o-book-open" class="flex-shrink-0 w-6 h-6 mt-1 text-primary" />
                                        <div>
                                            <div class="font-medium">Online Practice Site</div>
                                            <p class="mt-1 text-sm">Interactive website with additional practice problems and explanations.</p>
                                            <a href="#" class="inline-flex items-center gap-1 mt-2 text-sm font-medium text-primary">
                                                <span>Visit Website</span>
                                                <x-icon name="o-arrow-top-right-on-square" class="w-3 h-3" />
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-3 bg-base-200 rounded-box">
                                    <div class="flex items-start gap-3">
                                        <x-icon name="o-video-camera" class="flex-shrink-0 w-6 h-6 mt-1 text-primary" />
                                        <div>
                                            <div class="font-medium">Supplementary Video Tutorials</div>
                                            <p class="mt-1 text-sm">Video explanations of key concepts covered in this session.</p>
                                            <a href="#" class="inline-flex items-center gap-1 mt-2 text-sm font-medium text-primary">
                                                <span>Watch Videos</span>
                                                <x-icon name="o-arrow-top-right-on-square" class="w-3 h-3" />
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignments Tab -->
                <div x-show="activeTab === 'assignments'" x-cloak>
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Session Assignments</h2>

                            @if(count($assignments) > 0)
                                <div class="mt-4 space-y-4">
                                    @foreach($assignments as $assignment)
                                        <div class="p-4 border rounded-box border-base-300">
                                            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <h3 class="text-lg font-medium">{{ $assignment['title'] }}</h3>
                                                        <div class="badge {{ $assignment['status'] === 'completed' ? 'badge-success' : 'badge-warning' }}">
                                                            {{ ucfirst($assignment['status']) }}
                                                        </div>
                                                    </div>
                                                    <p class="mt-1 text-sm">{{ $assignment['description'] }}</p>
                                                </div>

                                                <div class="mt-3 md:mt-0 md:ml-4">
                                                    <div class="flex flex-col items-end">
                                                        <div class="text-sm">
                                                            Due: <span class="font-medium">{{ $this->formatDate($assignment['due_date']) }}</span>
                                                        </div>
                                                        <div class="text-sm {{ Carbon::parse($assignment['due_date'])->isPast() ? 'text-error' : '' }}">
                                                            {{ Carbon::parse($assignment['due_date'])->diffForHumans() }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            @if($assignment['status'] !== 'completed')
                                                <div class="flex justify-end mt-3">
                                                    <button wire:click="markAssignmentComplete({{ $assignment['id'] }})" class="btn btn-sm btn-primary">
                                                        Mark as Completed
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-6 mt-2 text-center bg-base-200 rounded-box">
                                    <p>No assignments have been assigned for this session.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Notes Tab -->
                <div x-show="activeTab === 'notes'" x-cloak>
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <div class="flex items-center justify-between">
                                <h2 class="card-title">Session Notes</h2>
                                <button wire:click="openAddNoteModal" class="btn btn-primary btn-sm">
                                    <x-icon name="o-plus" class="w-4 h-4 mr-1" />
                                    Add Note
                                </button>
                            </div>

                            @if(count($notes) > 0)
                                <div class="mt-4 space-y-4">
                                    @foreach($notes as $note)
                                        <div class="p-4 bg-base-200 rounded-box">
                                            <div class="flex justify-between">
                                                <div class="font-medium">{{ $note['author'] }} Note</div>
                                                <div class="text-sm opacity-70">{{ Carbon::parse($note['created_at'])->diffForHumans() }}</div>
                                            </div>
                                            <p class="mt-2">{{ $note['content'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-6 mt-2 text-center bg-base-200 rounded-box">
                                    <p>No notes have been added for this session yet.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Progress Tab -->
                <div x-show="activeTab === 'progress'" x-cloak>
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Progress Tracking</h2>

                            <div class="p-4 mt-4 border rounded-lg border-base-300">
                                <h3 class="text-lg font-medium">Subject Progress</h3>
                                <p class="mt-1 text-sm">Progress in {{ $session->subject->name }} over recent sessions.</p>

                                <div class="h-64 mt-4" id="subjectProgressChart"></div>

                                <script>
                                    document.addEventListener('livewire:initialized', () => {
                                        // Sample data for the chart
                                        const data = [
                                            { x: '1st Session', y: 6.5 },
                                            { x: '2nd Session', y: 7.2 },
                                            { x: '3rd Session', y: 6.8 },
                                            { x: '4th Session', y: 7.5 },
                                            { x: 'Current', y: {{ $session->performance_score ?? 'null' }} }
                                        ];

                                        const options = {
                                            chart: {
                                                type: 'line',
                                                height: 250,
                                                toolbar: {
                                                    show: false
                                                }
                                            },
                                            series: [{
                                                name: 'Performance Score',
                                                data: data.filter(item => item.y !== null).map(item => item.y)
                                            }],
                                            xaxis: {
                                                categories: data.filter(item => item.y !== null).map(item => item.x),
                                            },
                                            yaxis: {
                                                min: 0,
                                                max: 10,
                                                title: {
                                                    text: 'Score (out of 10)'
                                                }
                                            },
                                            colors: ['#6419E6'],
                                            stroke: {
                                                curve: 'smooth',
                                                width: 3
                                            },
                                            markers: {
                                                size: 5
                                            }
                                        };

                                        if (document.getElementById('subjectProgressChart')) {
                                            const chart = new ApexCharts(document.getElementById('subjectProgressChart'), options);
                                            chart.render();
                                        }
                                    });
                                </script>
                            </div>

                            <div class="grid grid-cols-1 gap-6 mt-6 md:grid-cols-2">
                                <div class="p-4 border rounded-lg border-base-300">
                                    <h3 class="text-lg font-medium">Skill Mastery</h3>

                                    <div class="mt-4 space-y-4">
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm">Concept Understanding</span>
                                                <span class="text-sm font-medium">75%</span>
                                            </div>
                                            <div class="w-full h-2 rounded-full bg-base-300">
                                                <div class="h-full rounded-full bg-primary" style="width: 75%"></div>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm">Problem Solving</span>
                                                <span class="text-sm font-medium">82%</span>
                                            </div>
                                            <div class="w-full h-2 rounded-full bg-base-300">
                                                <div class="h-full rounded-full bg-info" style="width: 82%"></div>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm">Critical Thinking</span>
                                                <span class="text-sm font-medium">68%</span>
                                            </div>
                                            <div class="w-full h-2 rounded-full bg-base-300">
                                                <div class="h-full rounded-full bg-warning" style="width: 68%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-4 border rounded-lg border-base-300">
                                    <h3 class="text-lg font-medium">Learning Achievements</h3>

                                    <div class="mt-4 space-y-2">
                                        <div class="flex items-center gap-2">
                                            <x-icon name="o-check-badge" class="w-5 h-5 text-success" />
                                            <span>Completed {{ $session->subject->name }} fundamentals</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <x-icon name="o-check-badge" class="w-5 h-5 text-success" />
                                            <span>Mastered basic problem-solving techniques</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <x-icon name="o-check-badge" class="w-5 h-5 text-success" />
                                            <span>Completed 3 major assignments successfully</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <x-icon name="o-academic-cap" class="w-5 h-5 text-primary" />
                                            <span>In progress: Advanced application skills</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div>
                <!-- Navigation Section -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Session Navigation</h2>

                        <div class="p-4 mt-4 text-center bg-base-200 rounded-box">
                            <div class="font-medium">Current Session</div>
                            <div class="mt-1">{{ $this->formatDate($session->start_time) }}</div>
                            <div class="badge {{
                                $session->status === 'completed' ? 'badge-success' :
                                ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                            }} mt-2">
                                {{ ucfirst($session->status) }}
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-4">
                            @if($previousSession)
                                <a href="{{ route('sessions.show', $previousSession->id) }}" class="text-center btn btn-outline">
                                    <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                    Previous
                                </a>
                            @else
                                <button disabled class="text-center btn btn-outline">
                                    <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                    Previous
                                </button>
                            @endif

                            @if($nextSession)
                            <a href="{{ route('sessions.show', $nextSession->id) }}" class="text-center btn btn-outline">
                                Next
                                <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                            </a>
                        @else
                            <button disabled class="text-center btn btn-outline">
                                Next
                                <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                            </button>
                        @endif
                    </div>

                    <a href="{{ route('sessions.index') }}" class="mt-4 btn btn-ghost btn-block btn-sm">
                        View All Sessions
                    </a>
                </div>
            </div>

            <!-- Teacher Information Card -->
            <div class="mt-6 shadow-xl card bg-base-100">
                <div class="card-body">
                    <h2 class="card-title">Teacher Information</h2>

                    <div class="flex flex-col items-center mt-4">
                        <div class="avatar placeholder">
                            <div class="w-24 rounded-full bg-neutral-focus text-neutral-content">
                                <span class="text-3xl">{{ substr($session->teacher->name, 0, 1) }}</span>
                            </div>
                        </div>

                        <div class="mt-4 text-center">
                            <div class="text-xl font-bold">{{ $session->teacher->name }}</div>
                            <div class="text-sm opacity-70">{{ $session->subject->name }} Teacher</div>

                            @if($teacherRating)
                                <div class="flex items-center justify-center gap-1 mt-2">
                                    <div class="rating rating-sm">
                                        @for($i = 1; $i <= 5; $i++)
                                            <input
                                                type="radio"
                                                name="teacher-rating"
                                                class="bg-orange-400 mask mask-star-2"
                                                {{ $i <= $teacherRating ? 'checked' : '' }}
                                                disabled
                                            />
                                        @endfor
                                    </div>
                                    <span class="text-sm">({{ $teacherRating }}/5)</span>
                                </div>
                            @endif
                        </div>

                        <div class="w-full mt-4 space-y-2">
                            <button wire:click="contactTeacher" class="flex items-center justify-center w-full gap-2 btn btn-outline btn-sm">
                                <x-icon name="o-envelope" class="w-4 h-4" />
                                Contact Teacher
                            </button>

                            <a href="#" class="flex items-center justify-center w-full gap-2 btn btn-ghost btn-sm">
                                <x-icon name="o-identification" class="w-4 h-4" />
                                View Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Sessions Card -->
            <div class="mt-6 shadow-xl card bg-base-100">
                <div class="card-body">
                    <h2 class="card-title">Related Sessions</h2>

                    @if($relatedSessions->isEmpty())
                        <div class="p-4 mt-2 text-center bg-base-200 rounded-box">
                            <p>No related sessions found.</p>
                        </div>
                    @else
                        <div class="mt-4 space-y-3">
                            @foreach($relatedSessions as $relatedSession)
                                <a href="{{ route('sessions.show', $relatedSession->id) }}" class="block p-3 transition-colors rounded-box hover:bg-base-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="font-medium">{{ $this->formatDate($relatedSession->start_time) }}</div>
                                            <div class="text-sm opacity-70">{{ $this->formatTime($relatedSession->start_time) }}</div>
                                        </div>
                                        <div class="badge {{
                                            $relatedSession->status === 'completed' ? 'badge-success' :
                                            ($relatedSession->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                        }}">
                                            {{ ucfirst($relatedSession->status) }}
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal {{ $showFeedbackModal ? 'modal-open' : '' }}">
    <div class="modal-box">
        <button wire:click="closeFeedbackModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

        <h3 class="text-lg font-bold">Session Feedback</h3>

        <form wire:submit.prevent="submitFeedback" class="py-4">
            <div class="mb-4">
                <label class="block mb-2 font-medium">Your Feedback</label>
                <textarea
                    wire:model="feedbackContent"
                    rows="4"
                    class="w-full textarea textarea-bordered"
                    placeholder="Share your thoughts about this session..."
                    {{ $feedbackContent && $session->status === 'completed' ? 'readonly' : '' }}
                ></textarea>
                @error('feedbackContent') <span class="text-sm text-error">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block mb-2 font-medium">Teacher Rating</label>
                <div class="rating">
                    @for($i = 1; $i <= 5; $i++)
                        <input
                            type="radio"
                            wire:model="teacherRating"
                            value="{{ $i }}"
                            name="teacher-rating"
                            class="bg-orange-400 mask mask-star-2"
                            {{ $teacherRating && $session->status === 'completed' ? 'disabled' : '' }}
                        />
                    @endfor
                </div>
                @error('teacherRating') <span class="text-sm text-error">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-3">
                <div>
                    <label class="block mb-2 text-sm font-medium">Overall Rating</label>
                    <div class="rating rating-sm">
                        @for($i = 1; $i <= 5; $i++)
                            <input
                                type="radio"
                                wire:model="overallRating"
                                value="{{ $i }}"
                                name="overall-rating"
                                class="bg-orange-400 mask mask-star-2"
                                {{ $overallRating && $session->status === 'completed' ? 'disabled' : '' }}
                            />
                        @endfor
                    </div>
                    @error('overallRating') <span class="text-sm text-error">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium">Understanding</label>
                    <div class="rating rating-sm">
                        @for($i = 1; $i <= 5; $i++)
                            <input
                                type="radio"
                                wire:model="understandingRating"
                                value="{{ $i }}"
                                name="understanding-rating"
                                class="bg-orange-400 mask mask-star-2"
                                {{ $understandingRating && $session->status === 'completed' ? 'disabled' : '' }}
                            />
                        @endfor
                    </div>
                    @error('understandingRating') <span class="text-sm text-error">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium">Engagement</label>
                    <div class="rating rating-sm">
                        @for($i = 1; $i <= 5; $i++)
                            <input
                                type="radio"
                                wire:model="engagementRating"
                                value="{{ $i }}"
                                name="engagement-rating"
                                class="bg-orange-400 mask mask-star-2"
                                {{ $engagementRating && $session->status === 'completed' ? 'disabled' : '' }}
                            />
                        @endfor
                    </div>
                    @error('engagementRating') <span class="text-sm text-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="modal-action">
                <button type="button" wire:click="closeFeedbackModal" class="btn">Cancel</button>
                @if(!$feedbackContent || $session->status !== 'completed')
                    <button type="submit" class="btn btn-primary">Submit Feedback</button>
                @else
                    <button type="button" wire:click="closeFeedbackModal" class="btn btn-primary">Close</button>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal {{ $showAddNoteModal ? 'modal-open' : '' }}">
    <div class="modal-box">
        <button wire:click="closeAddNoteModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

        <h3 class="text-lg font-bold">Add Session Note</h3>

        <form wire:submit.prevent="addNote" class="py-4">
            <div class="mb-4">
                <label class="block mb-2 font-medium">Note Content</label>
                <textarea
                    wire:model="noteContent"
                    rows="4"
                    class="w-full textarea textarea-bordered"
                    placeholder="Add your note about this session..."
                ></textarea>
                @error('noteContent') <span class="text-sm text-error">{{ $message }}</span> @enderror
            </div>

            <div class="modal-action">
                <button type="button" wire:click="closeAddNoteModal" class="btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Note</button>
            </div>
        </form>
    </div>
</div>
</div>
