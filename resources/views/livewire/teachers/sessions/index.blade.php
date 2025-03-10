<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\LearningSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $teacherProfile;

    // Filter and sort states
    public $statusFilter = '';
    public $dateFilter = '';
    public $searchQuery = '';
    public $sortBy = 'date';

    // Tab state
    public $activeTab = 'upcoming';

    // Modal states
    public $showSessionDetailsModal = false;
    public $selectedSession = null;

    // Notes input
    public $sessionNotes = '';

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'dateFilter' => ['except' => ''],
        'searchQuery' => ['except' => ''],
        'sortBy' => ['except' => 'date'],
        'activeTab' => ['except' => 'upcoming'],
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->teacherProfile = $this->user->teacherProfile;
    }

    public function updatingSearchQuery()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingDateFilter()
    {
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function viewSessionDetails($sessionId)
    {
        // Find the session in our mock data
        $this->selectedSession = collect($this->getAllSessions())->firstWhere('id', $sessionId);
        $this->sessionNotes = $this->selectedSession['teacher_notes'] ?? '';
        $this->showSessionDetailsModal = true;
    }

    public function closeSessionDetailsModal()
    {
        $this->showSessionDetailsModal = false;
        $this->selectedSession = null;
        $this->sessionNotes = '';
    }

    public function startSession($sessionId)
    {
        // In a real app, this would redirect to a virtual classroom
        $session = collect($this->getAllSessions())->firstWhere('id', $sessionId);

        $this->toast(
            type: 'info',
            title: 'Starting session...',
            description: 'Preparing virtual classroom for session with ' . $session['student_name'],
            position: 'toast-bottom toast-end',
            icon: 'o-video-camera',
            css: 'alert-info',
            timeout: 2000
        );
    }

    public function cancelSession($sessionId)
    {
        // In a real app, this would update the database
        $this->toast(
            type: 'warning',
            title: 'Session cancelled',
            description: 'The session has been cancelled successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-x-circle',
            css: 'alert-warning',
            timeout: 3000
        );
    }

    public function rescheduleSession($sessionId)
    {
        // In a real app, this would open a reschedule form
        $this->toast(
            type: 'info',
            title: 'Reschedule request initiated',
            description: 'Please select a new time for the session.',
            position: 'toast-bottom toast-end',
            icon: 'o-calendar',
            css: 'alert-info',
            timeout: 3000
        );
    }

    public function saveSessionNotes($sessionId)
    {
        // In a real app, this would save notes to the database
        $this->toast(
            type: 'success',
            title: 'Notes saved',
            description: 'Session notes have been saved successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeSessionDetailsModal();
    }

    public function markAttendance($sessionId, $attended)
    {
        // In a real app, this would update the database
        $this->toast(
            type: 'success',
            title: 'Attendance updated',
            description: 'Attendance has been marked ' . ($attended ? 'present' : 'absent'),
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        // If we're in the modal, close it
        if ($this->showSessionDetailsModal) {
            $this->closeSessionDetailsModal();
        }
    }

    public function completeSession($sessionId)
    {
        // In a real app, this would update the database
        $this->toast(
            type: 'success',
            title: 'Session completed',
            description: 'The session has been marked as completed.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        // If we're in the modal, close it
        if ($this->showSessionDetailsModal) {
            $this->closeSessionDetailsModal();
        }
    }

    // Get filtered sessions
    public function getSessionsProperty()
    {
        $sessions = collect($this->getAllSessions());

        // First, filter by tab
        if ($this->activeTab === 'upcoming') {
            $sessions = $sessions->filter(function($session) {
                return $session['status'] === 'scheduled' || $session['status'] === 'confirmed';
            });
        } elseif ($this->activeTab === 'completed') {
            $sessions = $sessions->filter(function($session) {
                return $session['status'] === 'completed';
            });
        } elseif ($this->activeTab === 'cancelled') {
            $sessions = $sessions->filter(function($session) {
                return $session['status'] === 'cancelled';
            });
        }

        // Apply additional filters
        if ($this->statusFilter) {
            $sessions = $sessions->filter(function($session) {
                return $session['status'] === $this->statusFilter;
            });
        }

        if ($this->dateFilter) {
            $sessions = $sessions->filter(function($session) {
                if ($this->dateFilter === 'today') {
                    return Carbon::parse($session['date'])->isToday();
                } elseif ($this->dateFilter === 'this_week') {
                    return Carbon::parse($session['date'])->isCurrentWeek();
                } elseif ($this->dateFilter === 'this_month') {
                    return Carbon::parse($session['date'])->isCurrentMonth();
                }
                return true;
            });
        }

        if ($this->searchQuery) {
            $query = strtolower($this->searchQuery);
            $sessions = $sessions->filter(function($session) use ($query) {
                return str_contains(strtolower($session['title']), $query) ||
                       str_contains(strtolower($session['student_name']), $query) ||
                       str_contains(strtolower($session['subject_name']), $query);
            });
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'title':
                $sessions = $sessions->sortBy('title');
                break;
            case 'student':
                $sessions = $sessions->sortBy('student_name');
                break;
            case 'subject':
                $sessions = $sessions->sortBy('subject_name');
                break;
            default: // date
                $sessions = $sessions->sortBy('date');
                break;
        }

        return $sessions->values()->all();
    }

    // Get stats for the dashboard
    public function getStatsProperty()
    {
        $allSessions = collect($this->getAllSessions());

        return [
            'total' => $allSessions->count(),
            'upcoming' => $allSessions->whereIn('status', ['scheduled', 'confirmed'])->count(),
            'completed' => $allSessions->where('status', 'completed')->count(),
            'cancelled' => $allSessions->where('status', 'cancelled')->count(),
            'total_hours' => $allSessions->sum('duration_hours')
        ];
    }

    // Get upcoming sessions for featured section
    public function getUpcomingSessionsProperty()
    {
        return collect($this->getAllSessions())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->sortBy('date')
            ->take(3)
            ->values()
            ->all();
    }

    // For formatting dates
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // Method to get relative time
    public function getRelativeTime($date)
    {
        return Carbon::parse($date)->diffForHumans();
    }

    // Method to check if a session is today
    public function isToday($date)
    {
        return Carbon::parse($date)->isToday();
    }

    // Method to check if a session is joinable (within 15 minutes of start time)
    public function isJoinable($date, $time)
    {
        $sessionDateTime = Carbon::parse("$date $time");
        $now = Carbon::now();

        // Session is joinable if it's within 15 minutes before or 2 hours after start time
        return $now->between(
            $sessionDateTime->copy()->subMinutes(15),
            $sessionDateTime->copy()->addHours(2)
        );
    }

    // Mock data for sessions (in a real app, you would fetch from database)
    private function getAllSessions()
    {
        return [
            [
                'id' => 1,
                'title' => 'Advanced Laravel Middleware',
                'subject_id' => 1,
                'subject_name' => 'Laravel Development',
                'student_id' => 101,
                'student_name' => 'Alex Johnson',
                'student_avatar' => null,
                'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
                'time' => '10:00:00',
                'end_time' => '12:00:00',
                'duration_hours' => 2,
                'status' => 'confirmed',
                'attended' => null,
                'performance_score' => null,
                'teacher_notes' => 'Prepare examples of custom middleware implementation',
                'materials' => [
                    ['name' => 'Laravel Middleware Guide', 'type' => 'pdf', 'url' => '#'],
                    ['name' => 'Code Samples', 'type' => 'zip', 'url' => '#']
                ]
            ],
            [
                'id' => 2,
                'title' => 'React Component Lifecycle',
                'subject_id' => 2,
                'subject_name' => 'React Development',
                'student_id' => 102,
                'student_name' => 'Emma Smith',
                'student_avatar' => null,
                'date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'time' => '14:00:00',
                'end_time' => '15:30:00',
                'duration_hours' => 1.5,
                'status' => 'scheduled',
                'attended' => null,
                'performance_score' => null,
                'teacher_notes' => 'Focus on hooks vs class components',
                'materials' => [
                    ['name' => 'React Hooks Cheatsheet', 'type' => 'pdf', 'url' => '#']
                ]
            ],
            [
                'id' => 3,
                'title' => 'UI Design Principles',
                'subject_id' => 3,
                'subject_name' => 'UI/UX Design',
                'student_id' => 103,
                'student_name' => 'John Davis',
                'student_avatar' => null,
                'date' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'time' => '11:00:00',
                'end_time' => '13:00:00',
                'duration_hours' => 2,
                'status' => 'completed',
                'attended' => true,
                'performance_score' => 4.5,
                'teacher_notes' => 'Student showed good progress with color theory application',
                'materials' => [
                    ['name' => 'Design Principles PDF', 'type' => 'pdf', 'url' => '#'],
                    ['name' => 'UI Templates', 'type' => 'zip', 'url' => '#']
                ]
            ],
            [
                'id' => 4,
                'title' => 'Laravel Model Relationships',
                'subject_id' => 1,
                'subject_name' => 'Laravel Development',
                'student_id' => 101,
                'student_name' => 'Alex Johnson',
                'student_avatar' => null,
                'date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'time' => '15:30:00',
                'end_time' => '17:00:00',
                'duration_hours' => 1.5,
                'status' => 'completed',
                'attended' => true,
                'performance_score' => 4.0,
                'teacher_notes' => 'Covered many-to-many relationships and polymorphic relations',
                'materials' => [
                    ['name' => 'Laravel Relationships', 'type' => 'pdf', 'url' => '#'],
                    ['name' => 'Model Examples', 'type' => 'zip', 'url' => '#']
                ]
            ],
            [
                'id' => 5,
                'title' => 'Flutter State Management',
                'subject_id' => 4,
                'subject_name' => 'Mobile Development',
                'student_id' => 104,
                'student_name' => 'Sophia Williams',
                'student_avatar' => null,
                'date' => Carbon::now()->format('Y-m-d'),
                'time' => '13:00:00',
                'end_time' => '15:00:00',
                'duration_hours' => 2,
                'status' => 'confirmed',
                'attended' => null,
                'performance_score' => null,
                'teacher_notes' => 'Focus on Provider pattern and state management options',
                'materials' => [
                    ['name' => 'Flutter State Management Guide', 'type' => 'pdf', 'url' => '#']
                ]
            ],
            [
                'id' => 6,
                'title' => 'Data Structures in Python',
                'subject_id' => 5,
                'subject_name' => 'Python Programming',
                'student_id' => 105,
                'student_name' => 'Daniel Brown',
                'student_avatar' => null,
                'date' => Carbon::now()->subDays(20)->format('Y-m-d'),
                'time' => '10:00:00',
                'end_time' => '12:30:00',
                'duration_hours' => 2.5,
                'status' => 'cancelled',
                'attended' => null,
                'performance_score' => null,
                'teacher_notes' => 'Session cancelled due to student illness',
                'materials' => []
            ],
            [
                'id' => 7,
                'title' => 'Advanced CSS Layouts',
                'subject_id' => 6,
                'subject_name' => 'Frontend Development',
                'student_id' => 106,
                'student_name' => 'Olivia Wilson',
                'student_avatar' => null,
                'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'time' => '09:00:00',
                'end_time' => '11:00:00',
                'duration_hours' => 2,
                'status' => 'scheduled',
                'attended' => null,
                'performance_score' => null,
                'teacher_notes' => 'Prepare CSS Grid and Flexbox examples',
                'materials' => [
                    ['name' => 'CSS Layout Cheatsheet', 'type' => 'pdf', 'url' => '#']
                ]
            ],
            [
                'id' => 8,
                'title' => 'JavaScript ES6 Features',
                'subject_id' => 6,
                'subject_name' => 'Frontend Development',
                'student_id' => 103,
                'student_name' => 'John Davis',
                'student_avatar' => null,
                'date' => Carbon::now()->addDays(4)->format('Y-m-d'),
                'time' => '14:00:00',
                'end_time' => '16:00:00',
                'duration_hours' => 2,
                'status' => 'scheduled',
                'attended' => null,
                'performance_score' => null,
                'teacher_notes' => 'Focus on arrow functions, destructuring, and async/await',
                'materials' => [
                    ['name' => 'ES6 Cheatsheet', 'type' => 'pdf', 'url' => '#'],
                    ['name' => 'Code Examples', 'type' => 'js', 'url' => '#']
                ]
            ]
        ];
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
                <h1 class="text-3xl font-bold">My Teaching Sessions</h1>
                <p class="mt-1 text-base-content/70">Manage your scheduled and completed teaching sessions</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('teachers.timetable') }}" class="btn btn-outline">
                    <x-icon name="o-calendar" class="w-4 h-4 mr-2" />
                    My Timetable
                </a>
                <a href="{{ route('teachers.session-requests') }}" class="btn btn-primary">
                    <x-icon name="o-inbox" class="w-4 h-4 mr-2" />
                    Session Requests
                </a>
            </div>
        </div>

        <!-- Sessions Dashboard Stats -->
        <div class="p-4 mb-8 shadow-lg rounded-xl bg-base-100 sm:p-6">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['total'] }}</div>
                    <div class="text-xs opacity-70">Total Sessions</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['upcoming'] }}</div>
                    <div class="text-xs opacity-70">Upcoming</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['completed'] }}</div>
                    <div class="text-xs opacity-70">Completed</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['cancelled'] }}</div>
                    <div class="text-xs opacity-70">Cancelled</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['total_hours'] }}</div>
                    <div class="text-xs opacity-70">Total Hours</div>
                </div>
            </div>
        </div>

        <!-- Upcoming Sessions Section -->
        @if(count($this->upcomingSessions) > 0)
            <div class="mb-8">
                <h2 class="mb-6 text-xl font-bold">Next Sessions</h2>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    @foreach($this->upcomingSessions as $session)
                        <div class="h-full shadow-lg card bg-base-100">
                            <div class="p-6">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ $session['title'] }}</h3>
                                        <p class="text-sm text-base-content/70">{{ $session['subject_name'] }}</p>
                                    </div>
                                    <div class="badge {{
                                        $session['status'] === 'confirmed' ? 'badge-success' :
                                        ($session['status'] === 'scheduled' ? 'badge-info' : 'badge-warning')
                                    }}">
                                        {{ ucfirst($session['status']) }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 mt-4">
                                    <div class="avatar placeholder">
                                        <div class="w-10 h-10 rounded-full bg-neutral-focus text-neutral-content">
                                            <span>{{ substr($session['student_name'], 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $session['student_name'] }}</div>
                                        <div class="text-xs text-base-content/70">Student</div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 mt-4">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-calendar" class="w-5 h-5 text-base-content/70" />
                                        <span class="text-sm">{{ $this->formatDate($session['date']) }}</span>
                                        @if($this->isToday($session['date']))
                                            <span class="badge badge-sm badge-primary">Today</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-clock" class="w-5 h-5 text-base-content/70" />
                                        <span class="text-sm">
                                            {{ \Carbon\Carbon::parse($session['time'])->format('h:i A') }} -
                                            {{ \Carbon\Carbon::parse($session['end_time'])->format('h:i A') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-video-camera" class="w-5 h-5 text-base-content/70" />
                                        <span class="text-sm">Online Session</span>
                                    </div>
                                </div>

                                <div class="justify-end mt-6 card-actions">
                                    @if($this->isJoinable($session['date'], $session['time']))
                                        <button
                                            wire:click="startSession({{ $session['id'] }})"
                                            class="btn btn-primary"
                                        >
                                            <x-icon name="o-play" class="w-4 h-4 mr-2" />
                                            Start Now
                                        </button>
                                    @else
                                        <button
                                            wire:click="viewSessionDetails({{ $session['id'] }})"
                                            class="btn btn-outline"
                                        >
                                            View Details
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Tabs & Search/Filter Section -->
        <div class="mb-4">
            <div class="tabs tabs-boxed">
                <a
                    wire:click="setActiveTab('upcoming')"
                    class="tab {{ $activeTab === 'upcoming' ? 'tab-active' : '' }}"
                >
                    Upcoming
                </a>
                <a
                    wire:click="setActiveTab('completed')"
                    class="tab {{ $activeTab === 'completed' ? 'tab-active' : '' }}"
                >
                    Completed
                </a>
                <a
                    wire:click="setActiveTab('cancelled')"
                    class="tab {{ $activeTab === 'cancelled' ? 'tab-active' : '' }}"
                >
                    Cancelled
                </a>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="p-4 mb-6 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <!-- Search -->
                <div class="lg:col-span-1">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="searchQuery"
                            placeholder="Search sessions..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <select wire:model.live="statusFilter" class="w-full select select-bordered">
                        <option value="">All Statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Date Filter -->
                <div>
                    <select wire:model.live="dateFilter" class="w-full select select-bordered">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month">This Month</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <select wire:model.live="sortBy" class="w-full select select-bordered">
                        <option value="date">Date (Newest First)</option>
                        <option value="title">Title (A-Z)</option>
                        <option value="student">Student (A-Z)</option>
                        <option value="subject">Subject (A-Z)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Sessions List -->
        <div class="shadow-xl rounded-xl bg-base-100">
            @if(count($this->sessions) > 0)
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 gap-6">
                        @foreach($this->sessions as $session)
                            <div class="overflow-hidden transition-all border rounded-lg shadow-sm hover:shadow-md border-base-200">
                                <div class="grid grid-cols-1 lg:grid-cols-4">
                                    <!-- Left Section with Basic Info -->
                                    <div class="p-5 lg:col-span-3">
                                        <div class="flex flex-col h-full">
                                            <div class="flex items-start justify-between mb-3">
                                                <div>
                                                    <h3 class="text-lg font-bold">{{ $session['title'] }}</h3>
                                                    <p class="text-sm text-base-content/70">{{ $session['subject_name'] }}</p>
                                                </div>
                                                <div class="badge {{
                                                    $session['status'] === 'confirmed' ? 'badge-success' :
                                                    ($session['status'] === 'scheduled' ? 'badge-info' :
                                                    ($session['status'] === 'completed' ? 'badge-success' : 'badge-warning'))
                                                }}">
                                                    {{ ucfirst($session['status']) }}
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div class="flex-1 space-y-3">
                                                    <!-- Student Info -->
                                                    <div class="flex items-center gap-2">
                                                        <div class="avatar placeholder">
                                                            <div class="w-8 h-8 rounded-full bg-neutral-focus text-neutral-content">
                                                                <span>{{ substr($session['student_name'], 0, 1) }}</span>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-medium">{{ $session['student_name'] }}</div>
                                                            <div class="text-xs text-base-content/70">Student</div>
                                                        </div>
                                                    </div>

                                                    <!-- Date & Time -->
                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="o-calendar" class="w-5 h-5 text-base-content/70" />
                                                        <div>
                                                            <div class="text-sm">{{ $this->formatDate($session['date']) }}</div>
                                                            @if($this->isToday($session['date']))
                                                                <div class="badge badge-sm badge-primary">Today</div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="o-clock" class="w-5 h-5 text-base-content/70" />
                                                        <div class="text-sm">
                                                            {{ \Carbon\Carbon::parse($session['time'])->format('h:i A') }} -
                                                            {{ \Carbon\Carbon::parse($session['end_time'])->format('h:i A') }}
                                                            <span class="ml-1 text-xs text-base-content/70">({{ $session['duration_hours'] }} hours)</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex-1 space-y-3">
                                                    <!-- Session Details -->
                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="o-video-camera" class="w-5 h-5 text-base-content/70" />
                                                        <span class="text-sm">Online Session</span>
                                                    </div>

                                                    @if($session['materials'] && count($session['materials']) > 0)
                                                        <div class="flex items-start gap-2">
                                                            <x-icon name="o-document-text" class="w-5 h-5 mt-0.5 text-base-content/70" />
                                                            <div>
                                                                <span class="text-sm">Materials:</span>
                                                                <div class="flex flex-wrap gap-1 mt-1">
                                                                    @foreach($session['materials'] as $material)
                                                                        <a
                                                                            href="{{ $material['url'] }}"
                                                                            class="flex items-center p-1 text-xs rounded-md bg-base-200 hover:bg-base-300"
                                                                        >
                                                                            <span>{{ $material['name'] }}</span>
                                                                        </a>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if($session['teacher_notes'])
                                                        <div class="flex items-start gap-2">
                                                            <x-icon name="o-pencil-square" class="w-5 h-5 mt-0.5 text-base-content/70" />
                                                            <div>
                                                                <span class="text-sm">Notes:</span>
                                                                <p class="text-xs line-clamp-1">{{ $session['teacher_notes'] }}</p>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Session Actions -->
                                            <div class="flex flex-wrap gap-2 mt-4 md:justify-end">
                                                @if($session['status'] === 'completed')
                                                    <button
                                                        wire:click="viewSessionDetails({{ $session['id'] }})"
                                                        class="btn btn-outline btn-sm"
                                                    >
                                                        <x-icon name="o-document-text" class="w-4 h-4 mr-1" />
                                                        View Notes
                                                    </button>
                                                @endif

                                                @if($session['status'] === 'scheduled' || $session['status'] === 'confirmed')
                                                    @if($this->isJoinable($session['date'], $session['time']))
                                                        <button
                                                            wire:click="startSession({{ $session['id'] }})"
                                                            class="btn btn-primary btn-sm"
                                                        >
                                                            <x-icon name="o-play" class="w-4 h-4 mr-1" />
                                                            Start Now
                                                        </button>
                                                    @endif

                                                    <button
                                                        wire:click="rescheduleSession({{ $session['id'] }})"
                                                        class="btn btn-outline btn-sm"
                                                    >
                                                        <x-icon name="o-calendar" class="w-4 h-4 mr-1" />
                                                        Reschedule
                                                    </button>

                                                    <button
                                                        wire:click="cancelSession({{ $session['id'] }})"
                                                        class="btn btn-outline btn-sm btn-error"
                                                    >
                                                        <x-icon name="o-x-mark" class="w-4 h-4 mr-1" />
                                                        Cancel
                                                    </button>
                                                @endif

                                                <button
                                                    wire:click="viewSessionDetails({{ $session['id'] }})"
                                                    class="btn btn-outline btn-sm"
                                                >
                                                    <x-icon name="o-information-circle" class="w-4 h-4 mr-1" />
                                                    Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Section with Status -->
                                    <div class="p-5 border-t lg:border-t-0 lg:border-l border-base-200">
                                        <div class="flex flex-col h-full">
                                            @if($session['status'] === 'completed')
                                                <div class="text-center">
                                                    <div class="inline-flex items-center justify-center w-16 h-16 mb-3 rounded-full bg-success bg-opacity-20">
                                                        <x-icon name="o-check-circle" class="w-8 h-8 text-success" />
                                                    </div>
                                                    <h4 class="text-lg font-medium">Completed</h4>
                                                    <p class="mt-1 text-sm text-base-content/70">{{ $this->getRelativeTime($session['date']) }}</p>

                                                    @if(isset($session['attended']))
                                                        <div class="mt-3">
                                                            <p class="text-sm">Student attendance:</p>
                                                            <div class="mt-1 badge {{ $session['attended'] ? 'badge-success' : 'badge-error' }}">
                                                                {{ $session['attended'] ? 'Present' : 'Absent' }}
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if(isset($session['performance_score']))
                                                        <div class="mt-3">
                                                            <p class="text-sm">Performance score:</p>
                                                            <div class="mt-1 text-lg font-semibold">
                                                                {{ $session['performance_score'] }}/5
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @elseif($session['status'] === 'cancelled')
                                                <div class="text-center">
                                                    <div class="inline-flex items-center justify-center w-16 h-16 mb-3 rounded-full bg-error bg-opacity-20">
                                                        <x-icon name="o-x-circle" class="w-8 h-8 text-error" />
                                                    </div>
                                                    <h4 class="text-lg font-medium">Cancelled</h4>
                                                    <p class="mt-1 text-sm text-base-content/70">{{ $this->getRelativeTime($session['date']) }}</p>
                                                </div>
                                            @elseif($session['status'] === 'confirmed')
                                                <div class="text-center">
                                                    <div class="inline-flex items-center justify-center w-16 h-16 mb-3 rounded-full bg-success bg-opacity-20">
                                                        <x-icon name="o-check-circle" class="w-8 h-8 text-success" />
                                                    </div>
                                                    <h4 class="text-lg font-medium">Confirmed</h4>
                                                    <p class="mt-1 text-sm text-base-content/70">{{ $this->getRelativeTime($session['date']) }}</p>

                                                    @if($this->isToday($session['date']))
                                                        <div class="mt-2 badge badge-primary">Today</div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="text-center">
                                                    <div class="inline-flex items-center justify-center w-16 h-16 mb-3 rounded-full bg-info bg-opacity-20">
                                                        <x-icon name="o-clock" class="w-8 h-8 text-info" />
                                                    </div>
                                                    <h4 class="text-lg font-medium">Scheduled</h4>
                                                    <p class="mt-1 text-sm text-base-content/70">{{ $this->getRelativeTime($session['date']) }}</p>
                                                </div>
                                            @endif

                                            @if($session['teacher_notes'])
                                                <div class="mt-4 divider"></div>
                                                <div class="mt-2">
                                                    <div class="text-sm font-medium">Notes:</div>
                                                    <p class="mt-1 text-xs text-base-content/70 line-clamp-3">{{ $session['teacher_notes'] }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                        <x-icon name="o-calendar" class="w-16 h-16 mb-4 text-base-content/30" />
                        <h3 class="text-xl font-bold">No sessions found</h3>
                        <p class="mt-2 text-base-content/70">
                            @if($searchQuery || $statusFilter || $dateFilter)
                                Try adjusting your search or filters
                            @else
                                You don t have any {{ $activeTab }} sessions yet
                            @endif
                        </p>

                        @if($searchQuery || $statusFilter || $dateFilter)
                            <button
                                wire:click="$set('searchQuery', ''); $set('statusFilter', ''); $set('dateFilter', '');"
                                class="mt-4 btn btn-outline"
                            >
                                Clear Filters
                            </button>
                        @else
                            <a href="{{ route('teachers.timetable') }}" class="mt-4 btn btn-primary">
                                View Timetable
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Session Details Modal -->
    <div class="modal {{ $showSessionDetailsModal ? 'modal-open' : '' }}">
        <div class="max-w-3xl modal-box">
            <button wire:click="closeSessionDetailsModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            @if($selectedSession)
                <div class="mb-6">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-2xl font-bold">{{ $selectedSession['title'] }}</h3>
                            <p class="mt-1 text-base-content/70">{{ $selectedSession['subject_name'] }}</p>
                        </div>
                        <div class="badge {{
                            $selectedSession['status'] === 'confirmed' ? 'badge-success' :
                            ($selectedSession['status'] === 'scheduled' ? 'badge-info' :
                            ($selectedSession['status'] === 'completed' ? 'badge-success' : 'badge-warning'))
                        }}">
                            {{ ucfirst($selectedSession['status']) }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Left Column: Session Details -->
                    <div>
                        <div class="p-4 rounded-lg bg-base-200">
                            <h4 class="mb-3 text-lg font-semibold">Session Details</h4>

                            <div class="space-y-3">
                                <!-- Date and Time -->
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-calendar" class="w-5 h-5 text-base-content/70" />
                                    <div>
                                        <span class="text-sm font-medium">Date:</span>
                                        <span class="ml-1 text-sm">{{ $this->formatDate($selectedSession['date']) }}</span>
                                        @if($this->isToday($selectedSession['date']))
                                            <span class="ml-1 badge badge-sm badge-primary">Today</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-icon name="o-clock" class="w-5 h-5 text-base-content/70" />
                                    <div>
                                        <span class="text-sm font-medium">Time:</span>
                                        <span class="ml-1 text-sm">
                                            {{ \Carbon\Carbon::parse($selectedSession['time'])->format('h:i A') }} -
                                            {{ \Carbon\Carbon::parse($selectedSession['end_time'])->format('h:i A') }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-icon name="o-eye" class="w-5 h-5 text-base-content/70" />
                                    <div>
                                        <span class="text-sm font-medium">Duration:</span>
                                        <span class="ml-1 text-sm">{{ $selectedSession['duration_hours'] }} hours</span>
                                    </div>
                                </div>

                                <!-- Location -->
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-video-camera" class="w-5 h-5 text-base-content/70" />
                                    <div>
                                        <span class="text-sm font-medium">Location:</span>
                                        <span class="ml-1 text-sm">Online Session</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Student Information -->
                        <div class="p-4 mt-4 rounded-lg bg-base-200">
                            <h4 class="mb-3 text-lg font-semibold">Student</h4>

                            <div class="flex items-center gap-3">
                                <div class="avatar placeholder">
                                    <div class="w-12 h-12 rounded-full bg-neutral-focus text-neutral-content">
                                        <span>{{ substr($selectedSession['student_name'], 0, 1) }}</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="font-medium">{{ $selectedSession['student_name'] }}</div>
                                    <div class="text-sm text-base-content/70">Student ID: {{ $selectedSession['student_id'] }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Materials -->
                        @if($selectedSession['materials'] && count($selectedSession['materials']) > 0)
                            <div class="p-4 mt-4 rounded-lg bg-base-200">
                                <h4 class="mb-3 text-lg font-semibold">Materials</h4>

                                <div class="space-y-2">
                                    @foreach($selectedSession['materials'] as $material)
                                        <a
                                            href="{{ $material['url'] }}"
                                            class="flex items-center justify-between p-2 transition-colors rounded-md hover:bg-base-300"
                                        >
                                            <div class="flex items-center gap-2">
                                                <x-icon
                                                    name="{{
                                                        $material['type'] === 'pdf' ? 'o-document-text' :
                                                        ($material['type'] === 'zip' ? 'o-archive-box' :
                                                        ($material['type'] === 'js' ? 'o-code-bracket' : 'o-document'))
                                                    }}"
                                                    class="w-5 h-5 text-base-content/70"
                                                />
                                                <span>{{ $material['name'] }}</span>
                                            </div>
                                            <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Right Column: Notes, Actions -->
                    <div>
                        <!-- Session Notes -->
                        <div class="p-4 rounded-lg bg-base-200">
                            <h4 class="mb-3 text-lg font-semibold">Session Notes</h4>

                            @if($selectedSession['status'] === 'completed')
                                <p class="mb-3 text-sm">{{ $sessionNotes }}</p>
                            @else
                                <textarea
                                    wire:model="sessionNotes"
                                    placeholder="Add your session notes here..."
                                    class="w-full h-32 resize-none textarea textarea-bordered"
                                ></textarea>
                                <button
                                    wire:click="saveSessionNotes({{ $selectedSession['id'] }})"
                                    class="mt-2 btn btn-sm btn-primary"
                                >
                                    Save Notes
                                </button>
                            @endif
                        </div>

                        <!-- Attendance (if session is today or completed) -->
                        @if($this->isToday($selectedSession['date']) || $selectedSession['status'] === 'completed')
                            <div class="p-4 mt-4 rounded-lg bg-base-200">
                                <h4 class="mb-3 text-lg font-semibold">Attendance</h4>

                                @if($selectedSession['attended'] === null)
                                    <div class="flex gap-2">
                                        <button
                                            wire:click="markAttendance({{ $selectedSession['id'] }}, true)"
                                            class="flex-1 btn btn-sm btn-success"
                                        >
                                            Mark Present
                                        </button>
                                        <button
                                            wire:click="markAttendance({{ $selectedSession['id'] }}, false)"
                                            class="flex-1 btn btn-sm btn-error"
                                        >
                                            Mark Absent
                                        </button>
                                    </div>
                                @else
                                    <div class="p-2 text-center">
                                        <div class="badge {{ $selectedSession['attended'] ? 'badge-success' : 'badge-error' }}">
                                            {{ $selectedSession['attended'] ? 'Present' : 'Absent' }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Session Actions -->
                        <div class="p-4 mt-4 rounded-lg bg-base-200">
                            <h4 class="mb-3 text-lg font-semibold">Actions</h4>

                            <div class="space-y-2">
                                @if($selectedSession['status'] === 'scheduled' || $selectedSession['status'] === 'confirmed')
                                    @if($this->isJoinable($selectedSession['date'], $selectedSession['time']))
                                        <button
                                            wire:click="startSession({{ $selectedSession['id'] }})"
                                            class="w-full btn btn-primary"
                                        >
                                            <x-icon name="o-play" class="w-4 h-4 mr-2" />
                                            Start Session
                                        </button>
                                    @endif

                                    <button
                                        wire:click="rescheduleSession({{ $selectedSession['id'] }})"
                                        class="w-full btn btn-outline"
                                    >
                                        <x-icon name="o-calendar" class="w-4 h-4 mr-2" />
                                        Reschedule
                                    </button>

                                    <button
                                        wire:click="cancelSession({{ $selectedSession['id'] }})"
                                        class="w-full btn btn-outline btn-error"
                                    >
                                        <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                                        Cancel Session
                                    </button>
                                @endif

                                @if($selectedSession['status'] === 'confirmed' && $this->isToday($selectedSession['date']))
                                    <button
                                        wire:click="completeSession({{ $selectedSession['id'] }})"
                                        class="w-full btn btn-success"
                                    >
                                        <x-icon name="o-check" class="w-4 h-4 mr-2" />
                                        Complete Session
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
