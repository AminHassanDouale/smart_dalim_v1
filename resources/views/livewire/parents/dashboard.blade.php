<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\ParentProfile;
use App\Models\Children;
use App\Models\LearningSession;
use App\Models\Enrollment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $user;
    public $parentProfile;
    public $children = [];
    public $upcomingSessions = [];
    public $enrollments = [];

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
        $this->loadParentData();
        $this->loadStatsFromDatabase();
        $this->loadUpcomingSessionsFromDatabase();
        $this->loadEnrollments();
        $this->prepareChartData();
    }

    public function loadParentData()
    {
        $this->parentProfile = $this->user->parentProfile()->with(['children' => function($query) {
            $query->with(['teacher', 'subjects', 'learningSessions']);
        }])->first();

        // Fetch children if parent profile exists
        if ($this->parentProfile) {
            $this->children = $this->parentProfile->children;
        }
    }

    public function loadStatsFromDatabase()
    {
        if (!$this->parentProfile) {
            return;
        }

        $childrenIds = $this->children->pluck('id')->toArray();
        
        // Get actual statistics from database
        $totalSessions = LearningSession::whereIn('children_id', $childrenIds)->count();
        $upcomingSessions = LearningSession::whereIn('children_id', $childrenIds)
            ->where('start_time', '>', now())
            ->where('status', LearningSession::STATUS_SCHEDULED)
            ->count();
            
        $completedSessions = LearningSession::whereIn('children_id', $childrenIds)
            ->where('status', LearningSession::STATUS_COMPLETED)
            ->count();
        
        // Get pending homework count (simulated)
        $pendingHomework = rand(0, 5); // Replace with actual homework count when model exists
        
        // Calculate average progress
        $avgProgress = $totalSessions > 0 
            ? round(($completedSessions / $totalSessions) * 100) 
            : 0;
        
        // Update the stats array
        $this->stats = [
            'total_children' => $this->children->count(),
            'total_sessions' => $totalSessions,
            'upcoming_sessions' => $upcomingSessions,
            'completed_sessions' => $completedSessions,
            'pending_homework' => $pendingHomework,
            'average_progress' => $avgProgress
        ];
    }
    
    public function loadUpcomingSessionsFromDatabase()
    {
        if (!$this->parentProfile) {
            return;
        }

        $childrenIds = $this->children->pluck('id')->toArray();
        
        // Apply filters
        $query = LearningSession::whereIn('children_id', $childrenIds)
            ->with(['children', 'teacher', 'subject']);
            
        // Apply child filter if selected
        if (!empty($this->childFilter)) {
            $query->where('children_id', $this->childFilter);
        }
        
        // Apply date range filter
        switch ($this->dateRangeFilter) {
            case 'today':
                $query->whereDate('start_time', Carbon::today());
                break;
            case 'this_week':
                $query->whereBetween('start_time', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ]);
                break;
            case 'next_week':
                $query->whereBetween('start_time', [
                    Carbon::now()->addWeek()->startOfWeek(),
                    Carbon::now()->addWeek()->endOfWeek()
                ]);
                break;
            case 'upcoming':
            default:
                $query->where('start_time', '>', now());
                break;
        }
        
        // Apply status filter if selected
        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        } else {
            // Default to scheduled if no status selected
            $query->where('status', LearningSession::STATUS_SCHEDULED);
        }
        
        // Get the sessions
        $sessions = $query->orderBy('start_time')->limit(10)->get();
        
        // Format sessions for display
        $this->upcomingSessions = $sessions->map(function($session) {
            return [
                'id' => $session->id,
                'child_id' => $session->children_id,
                'child_name' => $session->children ? $session->children->name : 'Unknown',
                'subject' => $session->subject ? $session->subject->name : 'General',
                'teacher' => $session->teacher ? $session->teacher->name : 'Unassigned',
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'status' => $session->status,
                'location' => $session->location ?? 'Online',
                'notes' => $session->notes
            ];
        })->toArray();
    }
    
    public function loadEnrollments()
    {
        if (!$this->parentProfile) {
            return;
        }

        $childrenIds = $this->children->pluck('id')->toArray();
        
        // Get active enrollments for all children
        $this->enrollments = Enrollment::whereIn('student_id', $childrenIds)
            ->where('status', 'active')
            ->with(['course.subject', 'course.teacher'])
            ->get();
    }

    public function prepareChartData()
    {
        if (!$this->parentProfile) {
            return;
        }

        $childrenIds = $this->children->pluck('id')->toArray();
        
        // Sessions chart data - sessions per week (from actual data)
        $weeksData = [];
        
        for ($i = 0; $i < 4; $i++) {
            $startDate = Carbon::now()->subWeeks(3 - $i)->startOfWeek();
            $endDate = Carbon::now()->subWeeks(3 - $i)->endOfWeek();
            $weekLabel = $startDate->format('M d');
            
            $sessionsCount = LearningSession::whereIn('children_id', $childrenIds)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->count();
                
            $weeksData[] = [
                'name' => $weekLabel,
                'sessions' => $sessionsCount
            ];
        }
        
        $this->sessionsChartData = $weeksData;

        // Progress chart data - progress by subject
        // For now using child subjects, but ideally would calculate progress for each
        $subjects = [];
        foreach ($this->children as $child) {
            foreach ($child->subjects as $subject) {
                $subjects[$subject->id] = $subject->name;
            }
        }
        
        $progressData = [];
        foreach ($subjects as $id => $name) {
            // Ideally calculate real progress based on completed sessions or assessments
            // For now using random values as placeholders
            $progressData[] = [
                'subject' => $name,
                'progress' => rand(30, 95)
            ];
        }
        
        // If no subjects found, use default placeholders
        if (empty($progressData)) {
            $defaultSubjects = ['Mathematics', 'Science', 'English', 'History', 'Art'];
            foreach ($defaultSubjects as $subject) {
                $progressData[] = [
                    'subject' => $subject,
                    'progress' => rand(30, 95)
                ];
            }
        }
        
        $this->progressChartData = $progressData;
    }

    // Update filters and refresh data
    public function updatedChildFilter()
    {
        $this->loadUpcomingSessionsFromDatabase();
    }
    
    public function updatedDateRangeFilter()
    {
        $this->loadUpcomingSessionsFromDatabase();
    }
    
    public function updatedStatusFilter()
    {
        $this->loadUpcomingSessionsFromDatabase();
    }

    public function getRecentActivitiesProperty()
    {
        if (!$this->parentProfile) {
            return [];
        }
        
        $childrenIds = $this->children->pluck('id')->toArray();
        $activities = [];

        // Get recent sessions
        $recentSessions = LearningSession::whereIn('children_id', $childrenIds)
            ->with(['children', 'subject', 'teacher'])
            ->latest('updated_at')
            ->limit(4)
            ->get();
            
        foreach ($recentSessions as $session) {
            $childName = $session->children ? $session->children->name : 'Unknown';
            $teacherName = $session->teacher ? $session->teacher->name : 'Unknown';
            $subjectName = $session->subject ? $session->subject->name : 'General';
            
            if ($session->status === LearningSession::STATUS_COMPLETED) {
                $activities[] = [
                    'id' => $session->id,
                    'type' => 'session_completed',
                    'title' => 'Session Completed',
                    'description' => $subjectName . ' lesson with ' . $teacherName,
                    'child' => $childName,
                    'time' => Carbon::parse($session->updated_at)->format('M d, Y'),
                    'icon' => 'o-check-circle',
                    'color' => 'bg-green-100 text-green-600'
                ];
            } elseif ($session->status === LearningSession::STATUS_SCHEDULED) {
                $activities[] = [
                    'id' => $session->id,
                    'type' => 'session_scheduled',
                    'title' => 'Session Scheduled',
                    'description' => $subjectName . ' with ' . $teacherName,
                    'child' => $childName,
                    'time' => Carbon::parse($session->updated_at)->format('M d, Y'),
                    'icon' => 'o-calendar',
                    'color' => 'bg-purple-100 text-purple-600'
                ];
            }
        }
        
        // If we don't have enough real activities, add some placeholders
        if (count($activities) < 4) {
            $sampleActivities = [
                [
                    'id' => 'placeholder-1',
                    'type' => 'homework_assigned',
                    'title' => 'Homework Assigned',
                    'description' => 'Science project due next week',
                    'child' => $this->children->first()->name ?? 'Child',
                    'time' => Carbon::now()->subDays(2)->format('M d, Y'),
                    'icon' => 'o-document-text',
                    'color' => 'bg-blue-100 text-blue-600'
                ],
                [
                    'id' => 'placeholder-2',
                    'type' => 'assessment_result',
                    'title' => 'Assessment Result',
                    'description' => 'Mathematics quiz: 92%',
                    'child' => $this->children->first()->name ?? 'Child',
                    'time' => Carbon::now()->subDays(5)->format('M d, Y'),
                    'icon' => 'o-academic-cap',
                    'color' => 'bg-yellow-100 text-yellow-600'
                ]
            ];
            
            $activities = array_merge($activities, array_slice($sampleActivities, 0, 4 - count($activities)));
        }
        
        // Sort by time
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        return array_slice($activities, 0, 4);
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
        <!-- Welcome Banner with Auth Data -->
        <div class="mb-8 overflow-hidden text-white shadow-lg rounded-xl bg-gradient-to-r from-primary to-primary-focus">
            <div class="flex flex-col items-center md:flex-row">
                <div class="flex-1 p-6 md:p-8">
                    <h1 class="mb-2 text-3xl font-bold">Welcome back, {{ $user->name }}!</h1>
                    <p class="mb-1 text-white/90">
                        {{ Carbon::now()->format('l, F d, Y') }}
                    </p>
                    
                    <!-- Auth Data Section -->
                    <div class="p-3 mt-3 text-white rounded-lg bg-white/10">
                        <h3 class="mb-2 font-semibold">Your Account Details</h3>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                            <div>
                                <p class="flex items-center">
                                    <x-icon name="o-user" class="w-4 h-4 mr-2" />
                                    <span class="text-white/80">Email:</span>
                                    <span class="ml-2">{{ $user->email }}</span>
                                </p>
                                <p class="flex items-center">
                                    <x-icon name="o-identification" class="w-4 h-4 mr-2" />
                                    <span class="text-white/80">Role:</span>
                                    <span class="ml-2 capitalize">{{ $user->role }}</span>
                                </p>
                                <p class="flex items-center">
                                    <x-icon name="o-check-badge" class="w-4 h-4 mr-2" />
                                    <span class="text-white/80">Status:</span>
                                    <span class="ml-2">{{ $user->hasCompletedProfile() ? 'Profile Complete' : 'Profile Incomplete' }}</span>
                                </p>
                            </div>
                            <div>
                                @if($parentProfile)
                                    <p class="flex items-center">
                                        <x-icon name="o-phone" class="w-4 h-4 mr-2" />
                                        <span class="text-white/80">Phone:</span>
                                        <span class="ml-2">{{ $parentProfile->phone_number ?? 'Not set' }}</span>
                                    </p>
                                    <p class="flex items-center">
                                        <x-icon name="o-map-pin" class="w-4 h-4 mr-2" />
                                        <span class="text-white/80">Address:</span>
                                        <span class="ml-2">{{ $parentProfile->address ?? 'Not set' }}</span>
                                    </p>
                                    <p class="flex items-center">
                                        <x-icon name="o-user-group" class="w-4 h-4 mr-2" />
                                        <span class="text-white/80">Children:</span>
                                        <span class="ml-2">{{ $stats['total_children'] }}</span>
                                    </p>
                                @else
                                    <p class="text-white/80">Parent profile not created yet.</p>
                                @endif
                            </div>
                        </div>
                    </div>

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
                        <x-icon name="o-academic-cap" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Sessions Completed</div>
                    <div class="stat-value text-accent">{{ $stats['completed_sessions'] }}</div>
                    <div class="stat-desc">Learning progress</div>
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
                            @foreach($children as $child)
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
                                            <p class="text-sm opacity-70">{{ $child->school_name ?? 'School not set' }} - Grade {{ $child->grade ?? 'N/A' }}</p>
                                        </div>
                                        <a href="{{ route('parents.progress.child', $child->id) }}" class="btn btn-sm">View Progress</a>
                                    </div>

                                    <!-- Child's information -->
                                    <div class="mt-3 p-2 rounded-lg bg-base-300/50">
                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <p class="text-sm flex">
                                                    <span class="opacity-70">Age:</span>
                                                    <span class="ml-2 font-medium">{{ $child->age ?? 'Not set' }}</span>
                                                </p>
                                                <p class="text-sm flex">
                                                    <span class="opacity-70">Gender:</span>
                                                    <span class="ml-2 font-medium">{{ $child->gender ?? 'Not set' }}</span>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-sm flex">
                                                    <span class="opacity-70">Teacher:</span>
                                                    <span class="ml-2 font-medium">{{ $child->teacher ? $child->teacher->name : 'Not assigned' }}</span>
                                                </p>
                                                <p class="text-sm flex">
                                                    <span class="opacity-70">Subjects:</span>
                                                    <span class="ml-2 font-medium">{{ $child->subjects->count() }}</span>
                                                </p>
                                            </div>
                                        </div>
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

                                    <!-- Child's sessions -->
                                    <div class="flex items-center justify-between mt-3">
                                        <div class="badge badge-outline">
                                            <x-icon name="o-calendar" class="w-3 h-3 mr-1" />
                                            {{ $child->learningSessions()->where('status', 'scheduled')->count() }} upcoming
                                        </div>
                                        <div class="badge badge-outline">
                                            <x-icon name="o-check-circle" class="w-3 h-3 mr-1" />
                                            {{ $child->learningSessions()->where('status', 'completed')->count() }} completed
                                        </div>
                                        <div class="badge badge-outline">
                                            <x-icon name="o-academic-cap" class="w-3 h-3 mr-1" />
                                            {{ $child->assessments()->count() }} assessments
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
                <!-- Account Summary -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Account Summary</h2>
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-base-content/70">Account Created:</span>
                                <span class="font-medium">{{ Carbon::parse($user->created_at)->format('M d, Y') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-base-content/70">Last Login:</span>
                                <span class="font-medium">{{ Carbon::now()->format('M d, Y H:i') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-base-content/70">Profile Status:</span>
                                <span class="font-medium">
                                    @if($user->hasCompletedProfile())
                                        <span class="text-success">Complete</span>
                                    @else
                                        <span class="text-warning">Incomplete</span>
                                    @endif
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-base-content/70">Active Enrollments:</span>
                                <span class="font-medium">{{ $enrollments->count() }}</span>
                            </div>
                        </div>
                        <div class="divider"></div>
                        <div class="card-actions">
                            <a href="{{ route('parents.profile.edit', $user) }}" class="btn btn-outline btn-block btn-sm">
                                <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                                Edit Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Quick Actions</h2>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="{{ route('parents.sessions.requests') }}" class="btn btn-outline">
                                <x-icon name="o-eye" class="w-4 h-4 mr-2" />
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