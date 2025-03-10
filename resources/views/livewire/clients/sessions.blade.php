<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $clientProfile;

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
        $this->clientProfile = $this->user->clientProfile;
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
        $this->showSessionDetailsModal = true;
    }

    public function closeSessionDetailsModal()
    {
        $this->showSessionDetailsModal = false;
        $this->selectedSession = null;
    }

    public function joinSession($sessionId)
    {
        // In a real app, this would redirect to the session room
        $session = collect($this->getAllSessions())->firstWhere('id', $sessionId);

        $this->toast(
            type: 'info',
            title: 'Joining session...',
            description: 'Preparing to join session with ' . $session['teacher_name'],
            position: 'toast-bottom toast-end',
            icon: 'o-video-camera',
            css: 'alert-info',
            timeout: 2000
        );
    }

    public function rescheduleSession($sessionId)
    {
        // In a real app, this would open a reschedule form
        $this->toast(
            type: 'info',
            title: 'Reschedule request initiated',
            description: 'Please select a new time for your session.',
            position: 'toast-bottom toast-end',
            icon: 'o-calendar',
            css: 'alert-info',
            timeout: 3000
        );
    }

    public function cancelSession($sessionId)
    {
        // In a real app, this would handle the cancellation logic
        $this->toast(
            type: 'warning',
            title: 'Session cancelled',
            description: 'Your session has been cancelled successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-x-circle',
            css: 'alert-warning',
            timeout: 3000
        );
    }

    public function provideFeedback($sessionId)
    {
        // In a real app, this would open a feedback form
        $this->toast(
            type: 'success',
            title: 'Feedback submitted',
            description: 'Thank you for your feedback!',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );
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
                       str_contains(strtolower($session['teacher_name']), $query) ||
                       str_contains(strtolower($session['course_title']), $query);
            });
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'title':
                $sessions = $sessions->sortBy('title');
                break;
            case 'teacher':
                $sessions = $sessions->sortBy('teacher_name');
                break;
            case 'course':
                $sessions = $sessions->sortBy('course_title');
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
            'total_hours' => $allSessions->where('status', 'completed')->sum('duration_hours')
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
                'title' => 'Laravel Advanced Techniques',
                'course_title' => 'Advanced Laravel Development',
                'course_id' => 1,
                'teacher_id' => 101,
                'teacher_name' => 'Sarah Johnson',
                'teacher_avatar' => null,
                'teacher_title' => 'Senior Laravel Developer',
                'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
                'time' => '10:00:00',
                'end_time' => '12:00:00',
                'duration_hours' => 2,
                'status' => 'confirmed',
                'location' => 'online',
                'meeting_link' => 'https://meet.example.com/session/123456',
                'notes' => 'Please prepare questions about Laravel service containers and middleware.',
                'feedback_submitted' => false,
                'materials' => [
                    ['name' => 'Laravel Advanced PDF', 'type' => 'pdf', 'url' => '#'],
                    ['name' => 'Code Samples', 'type' => 'zip', 'url' => '#']
                ],
                'recording' => null
            ],
            [
                'id' => 2,
                'title' => 'React Components & Hooks',
                'course_title' => 'React and Redux Masterclass',
                'course_id' => 2,
                'teacher_id' => 102,
                'teacher_name' => 'Michael Chen',
                'teacher_avatar' => null,
                'teacher_title' => 'Frontend Developer & Consultant',
                'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'time' => '14:00:00',
                'end_time' => '16:00:00',
                'duration_hours' => 2,
                'status' => 'scheduled',
                'location' => 'online',
                'meeting_link' => 'https://meet.example.com/session/789012',
                'notes' => 'We will cover React hooks and custom component creation.',
                'feedback_submitted' => false,
                'materials' => [
                    ['name' => 'React Hooks Cheatsheet', 'type' => 'pdf', 'url' => '#']
                ],
                'recording' => null
            ],
            [
                'id' => 3,
                'title' => 'UI Design Principles Review',
                'course_title' => 'UI/UX Design Fundamentals',
                'course_id' => 3,
                'teacher_id' => 103,
                'teacher_name' => 'Emily Rodriguez',
                'teacher_avatar' => null,
                'teacher_title' => 'Senior UX Designer',
                'date' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'time' => '11:00:00',
                'end_time' => '13:00:00',
                'duration_hours' => 2,
                'status' => 'completed',
                'location' => 'online',
                'meeting_link' => 'https://meet.example.com/session/345678',
                'notes' => 'Session focused on reviewing UI design principles and student projects.',
                'feedback_submitted' => true,
                'feedback_rating' => 5,
                'materials' => [
                    ['name' => 'Design Principles PDF', 'type' => 'pdf', 'url' => '#'],
                    ['name' => 'Project Templates', 'type' => 'zip', 'url' => '#']
                ],
                'recording' => [
                    'url' => '#',
                    'duration' => '1:58:23',
                    'size' => '350MB'
                ]
            ],
            [
                'id' => 4,
                'title' => 'Social Media Marketing Strategy',
                'course_title' => 'Digital Marketing Strategy',
                'course_id' => 4,
                'teacher_id' => 104,
                'teacher_name' => 'David Wilson',
                'teacher_avatar' => null,
                'teacher_title' => 'Digital Marketing Specialist',
                'date' => Carbon::now()->format('Y-m-d'),
                'time' => '15:30:00',
                'end_time' => '17:00:00',
                'duration_hours' => 1.5,
                'status' => 'confirmed',
                'location' => 'online',
                'meeting_link' => 'https://meet.example.com/session/901234',
                'notes' => 'We will discuss effective social media strategies for different platforms.',
                'feedback_submitted' => false,
                'materials' => [
                    ['name' => 'Social Media Strategy Template', 'type' => 'docx', 'url' => '#'],
                    ['name' => 'Marketing Calendar', 'type' => 'xlsx', 'url' => '#']
                ],
                'recording' => null
            ],
            [
                'id' => 5,
                'title' => 'Mobile App UI Development with Flutter',
                'course_title' => 'Flutter App Development',
                'course_id' => 5,
                'teacher_id' => 105,
                'teacher_name' => 'Alex Johnson',
                'teacher_avatar' => null,
                'teacher_title' => 'Mobile Developer',
                'date' => Carbon::now()->addDays(7)->format('Y-m-d'),
                'time' => '13:00:00',
                'end_time' => '15:00:00',
                'duration_hours' => 2,
                'status' => 'scheduled',
                'location' => 'online',
                'meeting_link' => 'https://meet.example.com/session/567890',
                'notes' => 'Please prepare Flutter development environment before the session.',
                'feedback_submitted' => false,
                'materials' => [
                    ['name' => 'Flutter Setup Guide', 'type' => 'pdf', 'url' => '#']
                ],
                'recording' => null
            ],
            [
                'id' => 6,
                'title' => 'Data Visualization with Python',
                'course_title' => 'Data Science with Python',
                'course_id' => 6,
                'teacher_id' => 106,
                'teacher_name' => 'Lisa Chen',
                'teacher_avatar' => null,
                'teacher_title' => 'Data Scientist',
                'date' => Carbon::now()->subDays(20)->format('Y-m-d'),
                'time' => '10:00:00',
                'end_time' => '12:30:00',
                'duration_hours' => 2.5,
                'status' => 'completed',
                'location' => 'online',
                'meeting_link' => 'https://meet.example.com/session/123789',
                'notes' => 'Session on using matplotlib, seaborn, and plotly for data visualization.',
                'feedback_submitted' => true,
                'feedback_rating' => 4,
                'materials' => [
                    ['name' => 'Python Visualization Notebook', 'type' => 'ipynb', 'url' => '#'],
                    ['name' => 'Sample Datasets', 'type' => 'zip', 'url' => '#']
                ],
                'recording' => [
                    'url' => '#',
                    'duration' => '2:25:10',
                    'size' => '420MB'
                ]
            ],
            [
                'id' => 7,
                'title' => 'Business Analytics Workshop',
                'course_title' => 'Business Analytics Fundamentals',
                'course_id' => 7,
                'teacher_id' => 107,
                'teacher_name' => 'Robert Taylor',
                'teacher_avatar' => null,
                'teacher_title' => 'Business Analyst',
                'date' => Carbon::now()->subDays(15)->format('Y-m-d'),
                'time' => '09:00:00',
                'end_time' => '12:00:00',
                'duration_hours' => 3,
                'status' => 'cancelled',
                'location' => 'online',
                'meeting_link' => null,
                'notes' => 'Session cancelled due to instructor illness. Will be rescheduled soon.',
                'feedback_submitted' => false,
                'materials' => [],
                'recording' => null
            ],
            [
                'id' => 8,
                'title' => 'Advanced Design Techniques',
                'course_title' => 'Graphic Design Masterclass',
                'course_id' => 8,
                'teacher_id' => 108,
                'teacher_name' => 'Jessica Park',
                'teacher_avatar' => null,
                'teacher_title' => 'Senior Graphic Designer',
                'date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'time' => '14:00:00',
                'end_time' => '16:30:00',
                'duration_hours' => 2.5,
                'status' => 'completed',
                'location' => 'online',
                'meeting_link' => 'https://meet.example.com/session/456123',
                'notes' => 'Session covered advanced graphic design techniques and tools.',
                'feedback_submitted' => false,
                'materials' => [
                    ['name' => 'Design Assets', 'type' => 'zip', 'url' => '#'],
                    ['name' => 'Tutorial PDF', 'type' => 'pdf', 'url' => '#']
                ],
                'recording' => [
                    'url' => '#',
                    'duration' => '2:28:45',
                    'size' => '410MB'
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
                <h1 class="text-3xl font-bold">My Learning Sessions</h1>
                <p class="mt-1 text-base-content/70">Manage your scheduled and completed learning sessions</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('clients.courses') }}" class="btn btn-outline">
                    <x-icon name="o-book-open" class="w-4 h-4 mr-2" />
                    Browse Courses
                </a>
                <a href="{{ route('clients.session-requests') }}" class="btn btn-primary">
                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                    Schedule Session
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
                                        <p class="text-sm text-base-content/70">{{ $session['course_title'] }}</p>
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
                                            <span>{{ substr($session['teacher_name'], 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $session['teacher_name'] }}</div>
                                        <div class="text-xs text-base-content/70">{{ $session['teacher_title'] }}</div>
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
                                            {{ Carbon::parse($session['time'])->format('h:i A') }} -
                                            {{ Carbon::parse($session['end_time'])->format('h:i A') }}
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
                                            wire:click="joinSession({{ $session['id'] }})"
                                            class="btn btn-primary"
                                        >
                                            <x-icon name="o-play" class="w-4 h-4 mr-2" />
                                            Join Now
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
                        <option value="teacher">Teacher (A-Z)</option>
                        <option value="course">Course (A-Z)</option>
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
                                                    <p class="text-sm text-base-content/70">{{ $session['course_title'] }}</p>
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
                                                    <!-- Teacher Info -->
                                                    <div class="flex items-center gap-2">
                                                        <div class="avatar placeholder">
                                                            <div class="w-8 h-8 rounded-full bg-neutral-focus text-neutral-content">
                                                                <span>{{ substr($session['teacher_name'], 0, 1) }}</span>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-medium">{{ $session['teacher_name'] }}</div>
                                                            <div class="text-xs text-base-content/70">{{ $session['teacher_title'] }}</div>
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
                                                            {{ Carbon::parse($session['time'])->format('h:i A') }} -
                                                            {{ Carbon::parse($session['end_time'])->format('h:i A') }}
                                                            <span class="ml-1 text-xs text-base-content/70">({{ $session['duration_hours'] }} hours)</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex-1">
                                                    <!-- Session Details -->
                                                    <div class="space-y-3">
                                                        <div class="flex items-center gap-2">
                                                            <x-icon name="o-video-camera" class="w-5 h-5 text-base-content/70" />
                                                            <span class="text-sm">{{ ucfirst($session['location']) }} Session</span>
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

                                                        @if($session['recording'])
                                                            <div class="flex items-center gap-2">
                                                                <x-icon name="o-film" class="w-5 h-5 text-base-content/70" />
                                                                <div>
                                                                    <span class="text-sm">Recording available</span>
                                                                    <span class="ml-2 text-xs text-base-content/70">({{ $session['recording']['duration'] }})</span>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Session Actions -->
                                            <div class="flex flex-wrap gap-2 mt-4 md:justify-end">
                                                @if($session['status'] === 'completed')
                                                    @if($session['recording'])
                                                        <a href="{{ $session['recording']['url'] }}" class="btn btn-outline btn-sm">
                                                            <x-icon name="o-play" class="w-4 h-4 mr-1" />
                                                            Watch Recording
                                                        </a>
                                                    @endif

                                                    @if(!$session['feedback_submitted'])
                                                        <button
                                                            wire:click="provideFeedback({{ $session['id'] }})"
                                                            class="btn btn-outline btn-sm"
                                                        >
                                                            <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 mr-1" />
                                                            Leave Feedback
                                                        </button>
                                                    @endif
                                                @endif

                                                @if($session['status'] === 'scheduled' || $session['status'] === 'confirmed')
                                                    @if($this->isJoinable($session['date'], $session['time']))
                                                        <button
                                                            wire:click="joinSession({{ $session['id'] }})"
                                                            class="btn btn-primary btn-sm"
                                                        >
                                                            <x-icon name="o-play" class="w-4 h-4 mr-1" />
                                                            Join Now
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

                                                    @if($session['feedback_submitted'])
                                                        <div class="mt-3">
                                                            <p class="text-sm">Your rating:</p>
                                                            <div class="flex items-center justify-center mt-1">
                                                                @for($i = 1; $i <= 5; $i++)
                                                                    <x-icon
                                                                        name="{{ $i <= $session['feedback_rating'] ? 's-star' : 'o-star' }}"
                                                                        class="w-5 h-5 {{ $i <= $session['feedback_rating'] ? 'text-yellow-500 fill-yellow-500' : 'text-base-content/30' }}"
                                                                    />
                                                                @endfor
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

                                            @if($session['notes'])
                                                <div class="mt-4 divider"></div>
                                                <div class="mt-2">
                                                    <div class="text-sm font-medium">Notes:</div>
                                                    <p class="mt-1 text-xs text-base-content/70">{{ $session['notes'] }}</p>
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
                                You don't have any {{ $activeTab }} sessions yet
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
                            <a href="{{ route('clients.session-requests') }}" class="mt-4 btn btn-primary">
                                Schedule a Session
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
                            <p class="mt-1 text-base-content/70">{{ $selectedSession['course_title'] }}</p>
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
                                            {{ Carbon::parse($selectedSession['time'])->format('h:i A') }} -
                                            {{ Carbon::parse($selectedSession['end_time'])->format('h:i A') }}
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
                                        <span class="ml-1 text-sm">{{ ucfirst($selectedSession['location']) }}</span>
                                        @if($selectedSession['meeting_link'] && ($selectedSession['status'] === 'scheduled' || $selectedSession['status'] === 'confirmed'))
                                            <a href="{{ $selectedSession['meeting_link'] }}" class="ml-2 text-xs link link-primary">Meeting Link</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Teacher Information -->
                        <div class="p-4 mt-4 rounded-lg bg-base-200">
                            <h4 class="mb-3 text-lg font-semibold">Teacher</h4>

                            <div class="flex items-center gap-3">
                                <div class="avatar placeholder">
                                    <div class="w-12 h-12 rounded-full bg-neutral-focus text-neutral-content">
                                        <span>{{ substr($selectedSession['teacher_name'], 0, 1) }}</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="font-medium">{{ $selectedSession['teacher_name'] }}</div>
                                    <div class="text-sm text-base-content/70">{{ $selectedSession['teacher_title'] }}</div>
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
                                                        ($material['type'] === 'docx' ? 'o-document' :
                                                        ($material['type'] === 'xlsx' ? 'o-table-cells' : 'o-document')))
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

                    <!-- Right Column: Notes, Recording, Actions -->
                    <div>
                        <!-- Notes -->
                        <div class="p-4 rounded-lg bg-base-200">
                            <h4 class="mb-3 text-lg font-semibold">Session Notes</h4>

                            @if($selectedSession['notes'])
                                <p class="text-sm">{{ $selectedSession['notes'] }}</p>
                            @else
                                <p class="text-sm text-base-content/70">No notes available for this session.</p>
                            @endif
                        </div>

                        <!-- Recording (if available) -->
                        @if($selectedSession['recording'])
                            <div class="p-4 mt-4 rounded-lg bg-base-200">
                                <h4 class="mb-3 text-lg font-semibold">Recording</h4>

                                <div class="p-3 border rounded-md border-base-300">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-sm font-medium">Session Recording</div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-base-content/70">Duration: {{ $selectedSession['recording']['duration'] }}</span>
                                                <span class="text-xs text-base-content/70">Size: {{ $selectedSession['recording']['size'] }}</span>
                                            </div>
                                        </div>
                                        <a href="{{ $selectedSession['recording']['url'] }}" class="btn btn-sm btn-primary">
                                            <x-icon name="o-play" class="w-4 h-4 mr-1" />
                                            Watch
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Feedback (if submitted) -->
                        @if($selectedSession['feedback_submitted'])
                            <div class="p-4 mt-4 rounded-lg bg-base-200">
                                <h4 class="mb-3 text-lg font-semibold">Your Feedback</h4>

                                <div class="text-center">
                                    <div class="flex items-center justify-center gap-1 mb-2">
                                        @for($i = 1; $i <= 5; $i++)
                                            <x-icon
                                                name="{{ $i <= $selectedSession['feedback_rating'] ? 's-star' : 'o-star' }}"
                                                class="w-6 h-6 {{ $i <= $selectedSession['feedback_rating'] ? 'text-yellow-500 fill-yellow-500' : 'text-base-content/30' }}"
                                            />
                                        @endfor
                                    </div>
                                    <p class="text-sm">You rated this session {{ $selectedSession['feedback_rating'] }}/5</p>
                                </div>
                            </div>
                        @endif

                        <!-- Actions -->
                        <div class="mt-6">
                            <div class="space-y-3">
                                @if($selectedSession['status'] === 'scheduled' || $selectedSession['status'] === 'confirmed')
                                    @if($this->isJoinable($selectedSession['date'], $selectedSession['time']))
                                        <button
                                            wire:click="joinSession({{ $selectedSession['id'] }})"
                                            class="w-full btn btn-primary"
                                        >
                                            <x-icon name="o-play" class="w-4 h-4 mr-2" />
                                            Join Session
                                        </button>
                                    @endif

                                    <button
                                        wire:click="rescheduleSession({{ $selectedSession['id'] }})"
                                        class="w-full btn btn-outline"
                                    >
                                        <x-icon name="o-calendar" class="w-4 h-4 mr-2" />
                                        Reschedule Session
                                    </button>

                                    <button
                                        wire:click="cancelSession({{ $selectedSession['id'] }})"
                                        class="w-full btn btn-outline btn-error"
                                    >
                                        <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                                        Cancel Session
                                    </button>
                                @elseif($selectedSession['status'] === 'completed' && !$selectedSession['feedback_submitted'])
                                    <button
                                        wire:click="provideFeedback({{ $selectedSession['id'] }})"
                                        class="w-full btn btn-primary"
                                    >
                                        <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 mr-2" />
                                        Provide Feedback
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
