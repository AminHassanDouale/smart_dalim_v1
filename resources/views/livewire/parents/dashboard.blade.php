<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\ParentProfile;
use App\Models\Children;
use App\Models\LearningSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $user;
    public $parentProfile;
    public $children = [];
    public $upcomingSessions = [];

    // Dashboard statistics
    public $stats = [
        'total_children' => 0,
        'total_sessions' => 0,
        'upcoming_sessions' => 0,
        'completed_sessions' => 0,
        'pending_homework' => 0,
        'average_progress' => 0
    ];

    // Filter states for upcoming sessions
    public $childFilter = '';
    public $dateRangeFilter = 'upcoming';
    public $statusFilter = '';

    // Chart data
    public $sessionsChartData = [];
    public $progressChartData = [];

    public function mount()
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        // Fetch children if parent profile exists
        if ($this->parentProfile) {
            $this->children = $this->parentProfile->children;
            $this->loadStats();
            $this->loadUpcomingSessions();
            $this->prepareChartData();
        }
    }

    public function loadStats()
    {
        // In a real app, you would calculate these from database queries
        $this->stats = [
            'total_children' => $this->children->count(),
            'total_sessions' => rand(10, 50),
            'upcoming_sessions' => rand(2, 8),
            'completed_sessions' => rand(8, 40),
            'pending_homework' => rand(0, 5),
            'average_progress' => rand(65, 95)
        ];
    }

    public function loadUpcomingSessions()
    {
        // In a real app, you would fetch this from the database
        // For now, we'll generate mock data
        $this->upcomingSessions = $this->getMockSessions();
    }

    private function getMockSessions()
    {
        $sessions = [];
        $subjects = ['Mathematics', 'Science', 'English Literature', 'History', 'Programming'];
        $statuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];

        foreach ($this->children as $index => $child) {
            // Generate 2-3 sessions per child
            $count = rand(2, 3);

            for ($i = 0; $i < $count; $i++) {
                $startDate = Carbon::now()->addDays(rand(1, 14))->setHour(rand(9, 17))->setMinute(0)->setSecond(0);
                $endDate = (clone $startDate)->addHours(rand(1, 2));

                $sessions[] = [
                    'id' => count($sessions) + 1,
                    'child_id' => $child->id,
                    'child_name' => $child->name,
                    'subject' => $subjects[array_rand($subjects)],
                    'teacher' => $this->getRandomTeacherName(),
                    'start_time' => $startDate,
                    'end_time' => $endDate,
                    'status' => $statuses[array_rand($statuses)],
                    'location' => 'Online',
                    'notes' => rand(0, 1) ? 'Preparation required' : ''
                ];
            }
        }

        // Sort by start date
        usort($sessions, function ($a, $b) {
            return $a['start_time']->timestamp - $b['start_time']->timestamp;
        });

        return $sessions;
    }

    private function getRandomTeacherName()
    {
        $teachers = [
            'Sarah Johnson',
            'Michael Chen',
            'Emily Rodriguez',
            'David Wilson',
            'Alex Johnson',
            'Lisa Chen'
        ];

        return $teachers[array_rand($teachers)];
    }

    public function prepareChartData()
    {
        // Sessions chart data - sessions per week
        $weeksData = [];

        for ($i = 0; $i < 4; $i++) {
            $weekLabel = Carbon::now()->subWeeks(3 - $i)->startOfWeek()->format('M d');
            $weeksData[] = [
                'name' => $weekLabel,
                'sessions' => rand(2, 8)
            ];
        }

        $this->sessionsChartData = $weeksData;

        // Progress chart data - progress by subject
        $subjects = ['Mathematics', 'Science', 'English', 'History', 'Art'];
        $progressData = [];

        foreach ($subjects as $subject) {
            $progressData[] = [
                'subject' => $subject,
                'progress' => rand(30, 95)
            ];
        }

        $this->progressChartData = $progressData;
    }

    public function getRecentActivitiesProperty()
    {
        // Mock recent activities
        return [
            [
                'id' => 1,
                'type' => 'session_completed',
                'title' => 'Session Completed',
                'description' => 'Mathematics lesson with Sarah Johnson',
                'child' => $this->children->first()->name ?? 'Child',
                'time' => Carbon::now()->subDays(1)->format('M d, Y'),
                'icon' => 'o-check-circle',
                'color' => 'bg-green-100 text-green-600'
            ],
            [
                'id' => 2,
                'type' => 'homework_assigned',
                'title' => 'Homework Assigned',
                'description' => 'Science project due next week',
                'child' => $this->children->first()->name ?? 'Child',
                'time' => Carbon::now()->subDays(2)->format('M d, Y'),
                'icon' => 'o-document-text',
                'color' => 'bg-blue-100 text-blue-600'
            ],
            [
                'id' => 3,
                'type' => 'session_scheduled',
                'title' => 'Session Scheduled',
                'description' => 'English Literature with Michael Chen',
                'child' => $this->children->first()->name ?? 'Child',
                'time' => Carbon::now()->subDays(3)->format('M d, Y'),
                'icon' => 'o-calendar',
                'color' => 'bg-purple-100 text-purple-600'
            ],
            [
                'id' => 4,
                'type' => 'assessment_result',
                'title' => 'Assessment Result',
                'description' => 'Mathematics quiz: 92%',
                'child' => $this->children->first()->name ?? 'Child',
                'time' => Carbon::now()->subDays(5)->format('M d, Y'),
                'icon' => 'o-academic-cap',
                'color' => 'bg-yellow-100 text-yellow-600'
            ]
        ];
    }

    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    public function formatTime($date)
    {
        return Carbon::parse($date)->format('h:i A');
    }

    public function getSessionStatusClass($status)
    {
        return match($status) {
            'scheduled' => 'badge-info',
            'in_progress' => 'badge-warning',
            'completed' => 'badge-success',
            'cancelled' => 'badge-error',
            default => 'badge-neutral'
        };
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Welcome Banner -->
        <div class="mb-8 overflow-hidden text-white shadow-lg rounded-xl bg-gradient-to-r from-primary to-primary-focus">
            <div class="flex flex-col items-center md:flex-row">
                <div class="flex-1 p-6 md:p-8">
                    <h1 class="mb-2 text-3xl font-bold">Welcome back, {{ $user->name }}!</h1>
                    <p class="mb-4 text-white/80">
                        {{ Carbon::now()->format('l, F d, Y') }}
                    </p>

                    <div class="flex flex-wrap gap-2 mt-4">
                        <a href="{{ route('parents.profile') }}" class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30">
                            <x-icon name="o-user" class="w-4 h-4 mr-1" />
                            View Profile
                        </a>
                        <a href="{{ route('parents.calendar') }}" class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30">
                            <x-icon name="o-calendar" class="w-4 h-4 mr-1" />
                            Calendar
                        </a>
                    </div>
                </div>
                <div class="hidden p-6 md:block">
                    <img src="{{ asset('images/parent-dashboard-illustration.svg') }}" alt="Dashboard" class="h-32" onerror="this.src='https://via.placeholder.com/150'">
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-2 lg:grid-cols-3">
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <x-icon name="o-user-group" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Children</div>
                    <div class="stat-value text-primary">{{ $stats['total_children'] }}</div>
                    <div class="stat-desc">Registered students</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <x-icon name="o-calendar" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Upcoming Sessions</div>
                    <div class="stat-value text-secondary">{{ $stats['upcoming_sessions'] }}</div>
                    <div class="stat-desc">In the next 2 weeks</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-accent">
                        <x-icon name="o-document-text" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Pending Homework</div>
                    <div class="stat-value text-accent">{{ $stats['pending_homework'] }}</div>
                    <div class="stat-desc">Assignments to complete</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column (Main Content) -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Upcoming Sessions -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Upcoming Sessions</h2>
                            <a href="{{ route('parents.schedule.index') }}" class="btn btn-sm btn-outline">View All</a>
                        </div>

                        <!-- Filters -->
                        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-3">
                            @if(count($children) > 0)
                            <select class="w-full select select-bordered" wire:model.live="childFilter">
                                <option value="">All Children</option>
                                @foreach($children as $child)
                                <option value="{{ $child->id }}">{{ $child->name }}</option>
                                @endforeach
                            </select>
                            @endif

                            <select class="w-full select select-bordered" wire:model.live="dateRangeFilter">
                                <option value="upcoming">Upcoming</option>
                                <option value="today">Today</option>
                                <option value="this_week">This Week</option>
                                <option value="next_week">Next Week</option>
                            </select>

                            <select class="w-full select select-bordered" wire:model.live="statusFilter">
                                <option value="">All Statuses</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        @if(count($upcomingSessions) > 0)
                        <div class="overflow-x-auto">
                            <table class="table w-full table-zebra">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Subject</th>
                                        <th>Child</th>
                                        <th>Teacher</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($upcomingSessions as $session)
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $formatDate($session['start_time']) }}</div>
                                            <div class="text-xs opacity-70">{{ $formatTime($session['start_time']) }} - {{ $formatTime($session['end_time']) }}</div>
                                        </td>
                                        <td>{{ $session['subject'] }}</td>
                                        <td>{{ $session['child_name'] }}</td>
                                        <td>{{ $session['teacher'] }}</td>
                                        <td>
                                            <div class="badge {{ $getSessionStatusClass($session['status']) }}">
                                                {{ ucfirst($session['status']) }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="dropdown dropdown-end">
                                                <div tabindex="0" role="button" class="btn btn-ghost btn-xs">
                                                    <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                                </div>
                                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                    <li><a href="{{ route('parents.sessions.show', $session['id']) }}">View Details</a></li>
                                                    <li><a>Open Virtual Classroom</a></li>
                                                    <li><a>Reschedule</a></li>
                                                    <li><a class="text-error">Cancel Session</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="py-8 text-center">
                            <x-icon name="o-calendar" class="w-12 h-12 mx-auto mb-3 text-base-content/30" />
                            <h3 class="text-lg font-medium">No upcoming sessions</h3>
                            <p class="mt-1 text-base-content/70">Schedule a new session to get started</p>
                            <a href="{{ route('parents.sessions.requests') }}" class="mt-4 btn btn-primary">Schedule Session</a>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Children Progress -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Children Progress</h2>
                            <a href="{{ route('parents.progress.index') }}" class="btn btn-sm btn-outline">View Details</a>
                        </div>

                        @if(count($children) > 0)
                        <div class="grid grid-cols-1 gap-4">
                            @foreach($children as $index => $child)
                            <div class="shadow-sm card bg-base-200">
                                <div class="p-4 card-body">
                                    <div class="flex items-center gap-4">
                                        <div class="avatar placeholder">
                                            <div class="w-12 rounded-full bg-neutral-focus text-neutral-content">
                                                <span>{{ substr($child->name, 0, 1) }}</span>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-bold">{{ $child->name }}</h3>
                                            <p class="text-sm opacity-70">{{ $child->school_name }} - Grade {{ $child->grade }}</p>
                                        </div>
                                        <a href="{{ route('parents.progress.child', $child->id) }}" class="btn btn-sm">View Progress</a>
                                    </div>

                                    <!-- Subject Progress Bars -->
                                    <div class="mt-4 space-y-3">
                                        @foreach(array_slice($progressChartData, 0, 3) as $subject)
                                        <div>
                                            <div class="flex justify-between mb-1">
                                                <span class="text-sm">{{ $subject['subject'] }}</span>
                                                <span class="text-sm font-medium">{{ $subject['progress'] }}%</span>
                                            </div>
                                            <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                                <div
                                                    class="h-full {{ $subject['progress'] > 75 ? 'bg-success' : ($subject['progress'] > 40 ? 'bg-info' : 'bg-primary') }}"
                                                    style="width: {{ $subject['progress'] }}%">
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>

                                    <!-- Recent achievements or notifications -->
                                    <div class="flex items-center justify-between mt-3">
                                        <div class="badge badge-outline">
                                            <x-icon name="o-calendar" class="w-3 h-3 mr-1" />
                                            {{ rand(1, 5) }} upcoming sessions
                                        </div>
                                        <div class="badge badge-outline">
                                            <x-icon name="o-document-text" class="w-3 h-3 mr-1" />
                                            {{ rand(0, 3) }} pending homework
                                        </div>
                                        <div class="badge badge-outline">
                                            <x-icon name="o-academic-cap" class="w-3 h-3 mr-1" />
                                            {{ rand(85, 98) }}% attendance
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="py-8 text-center">
                            <x-icon name="o-user-plus" class="w-12 h-12 mx-auto mb-3 text-base-content/30" />
                            <h3 class="text-lg font-medium">No children registered</h3>
                            <p class="mt-1 text-base-content/70">Add your children to track their progress</p>
                            <a href="{{ route('parents.children.create') }}" class="mt-4 btn btn-primary">Add Child</a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column (Sidebar) -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Quick Actions</h2>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="{{ route('parents.sessions.requests') }}" class="btn btn-outline">
                                <x-icon name="o-calendar-plus" class="w-4 h-4 mr-2" />
                                New Session
                            </a>
                            <a href="{{ route('parents.children.create') }}" class="btn btn-outline">
                                <x-icon name="o-user-plus" class="w-4 h-4 mr-2" />
                                Add Child
                            </a>
                            <a href="{{ route('parents.materials.index') }}" class="btn btn-outline">
                                <x-icon name="o-book-open" class="w-4 h-4 mr-2" />
                                Materials
                            </a>
                            <a href="{{ route('parents.messages.index') }}" class="btn btn-outline">
                                <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 mr-2" />
                                Messages
                            </a>
                            <a href="{{ route('parents.reports.index') }}" class="btn btn-outline">
                                <x-icon name="o-document-chart-bar" class="w-4 h-4 mr-2" />
                                Reports
                            </a>
                            <a href="{{ route('parents.support.index') }}" class="btn btn-outline">
                                <x-icon name="o-lifebuoy" class="w-4 h-4 mr-2" />
                                Support
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Recent Activity</h2>
                        <div class="space-y-4">
                            @foreach($this->recentActivities as $activity)
                                <div class="flex gap-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $activity['color'] }}">
                                            <x-icon name="{{ $activity['icon'] }}" class="w-5 h-5" />
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $activity['title'] }}</div>
                                        <div class="text-sm opacity-70">{{ $activity['description'] }}</div>
                                        <div class="flex items-center justify-between mt-1">
                                            <span class="text-xs badge badge-ghost">{{ $activity['child'] }}</span>
                                            <span class="text-xs opacity-50">{{ $activity['time'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                @if(!$loop->last)
                                    <div class="h-4 ml-5 border-l-2 border-dashed border-base-300"></div>
                                @endif
                            @endforeach
                        </div>
                        <div class="justify-center mt-4 card-actions">
                            <a href="#" class="btn btn-ghost btn-sm">View All Activity</a>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Payments -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-2 card-title">Upcoming Payments</h2>
                        @if(true)
                        <div class="mt-4 space-y-4">
                            <div class="p-3 rounded-lg bg-base-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">Monthly Tuition</div>
                                        <div class="text-sm opacity-70">Due in 5 days</div>
                                    </div>
                                    <div class="text-lg font-bold">$250.00</div>
                                </div>
                                <div class="flex justify-end mt-2">
                                    <a href="{{ route('parents.billing.index') }}" class="btn btn-sm btn-primary">Pay Now</a>
                                </div>
                            </div>

                            <div class="p-3 rounded-lg bg-base-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">Materials Fee</div>
                                        <div class="text-sm opacity-70">Due in 12 days</div>
                                    </div>
                                    <div class="text-lg font-bold">$45.00</div>
                                </div>
                                <div class="flex justify-end mt-2">
                                    <a href="{{ route('parents.billing.index') }}" class="btn btn-sm btn-primary">Pay Now</a>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="py-4 text-center">
                            <x-icon name="o-credit-card" class="w-10 h-10 mx-auto mb-2 text-base-content/30" />
                            <p class="text-base-content/70">No upcoming payments</p>
                        </div>
                        @endif
                        <div class="justify-end mt-2 card-actions">
                            <a href="{{ route('parents.payments.index') }}" class="btn btn-sm btn-ghost">Payment History</a>
                        </div>
                    </div>
                </div>

                <!-- Session Stats -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Learning Progress</h2>

                        <!-- Sessions Per Week -->
                        <div>
                            <h3 class="mb-2 text-sm font-medium">Sessions Per Week</h3>
                            <div class="flex items-end justify-between w-full h-32 gap-1">
                                @foreach($sessionsChartData as $week)
                                <div class="flex flex-col items-center">
                                    <div class="w-12 transition-all rounded-t bg-primary" style="height: {{ $week['sessions'] * 10 }}px;"></div>
                                    <div class="mt-1 text-xs">{{ $week['name'] }}</div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="my-2 divider"></div>

                        <!-- Overall Stats -->
                        <div class="shadow stats bg-base-200">
                            <div class="stat">
                                <div class="stat-title">Attendance</div>
                                <div class="stat-value text-success">{{ $stats['average_progress'] }}%</div>
                                <div class="stat-desc">{{ $stats['completed_sessions'] }} sessions completed</div>
                            </div>
                        </div>

                        <div class="justify-center mt-4 card-actions">
                            <a href="{{ route('parents.reports.index') }}" class="btn btn-outline btn-sm">
                                <x-icon name="o-chart-bar" class="w-4 h-4 mr-2" />
                                View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
