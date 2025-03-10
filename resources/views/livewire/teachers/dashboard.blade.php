<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Course;
use App\Models\LearningSession;
use App\Models\Children;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $teacherProfile;
    public $stats = [
        'total_students' => 0,
        'total_sessions' => 0,
        'upcoming_sessions' => 0,
        'completed_sessions' => 0,
        'total_courses' => 0,
        'session_requests' => 0
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->teacherProfile = $this->user->teacherProfile;

        $this->calculateStats();
    }

    protected function calculateStats()
    {
        // In a real app, these would fetch from database
        // For demonstration, we'll use placeholder values
        $this->stats = [
            'total_students' => rand(5, 20),
            'total_sessions' => rand(20, 50),
            'upcoming_sessions' => rand(3, 8),
            'completed_sessions' => rand(15, 40),
            'total_courses' => rand(2, 5),
            'session_requests' => rand(0, 3)
        ];
    }

    // Get upcoming sessions for the teacher
    public function getUpcomingSessionsProperty()
    {
        // In a real app, you would fetch from the database
        // For now, returning mock data
        return [
            [
                'id' => 1,
                'title' => 'Advanced Laravel Middleware',
                'student_name' => 'Alex Johnson',
                'student_id' => 201,
                'date' => Carbon::now()->addDays(1)->format('Y-m-d'),
                'time' => '10:00:00',
                'end_time' => '12:00:00',
                'status' => 'confirmed'
            ],
            [
                'id' => 2,
                'title' => 'React Component Lifecycle',
                'student_name' => 'Emma Smith',
                'student_id' => 202,
                'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
                'time' => '14:00:00',
                'end_time' => '15:30:00',
                'status' => 'confirmed'
            ],
            [
                'id' => 3,
                'title' => 'UI Design Principles',
                'student_name' => 'John Davis',
                'student_id' => 203,
                'date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'time' => '09:00:00',
                'end_time' => '10:30:00',
                'status' => 'pending'
            ],
        ];
    }

    // Get active courses for the teacher
    public function getCoursesProperty()
    {
        // In a real app, you would fetch from the database
        // For now, returning mock data
        return [
            [
                'id' => 1,
                'name' => 'Advanced Laravel Development',
                'students' => 12,
                'progress' => 45,
                'status' => 'active'
            ],
            [
                'id' => 2,
                'name' => 'React and Redux Masterclass',
                'students' => 8,
                'progress' => 30,
                'status' => 'active'
            ],
        ];
    }

    // Get recent students for the teacher
    public function getRecentStudentsProperty()
    {
        // In a real app, you would fetch from the database
        // For now, returning mock data
        return [
            [
                'id' => 201,
                'name' => 'Alex Johnson',
                'courses' => ['Advanced Laravel Development'],
                'last_session' => Carbon::now()->subDays(2)->format('Y-m-d')
            ],
            [
                'id' => 202,
                'name' => 'Emma Smith',
                'courses' => ['React and Redux Masterclass'],
                'last_session' => Carbon::now()->subDays(3)->format('Y-m-d')
            ],
            [
                'id' => 203,
                'name' => 'John Davis',
                'courses' => ['UI/UX Design Fundamentals'],
                'last_session' => Carbon::now()->subDays(5)->format('Y-m-d')
            ],
            [
                'id' => 204,
                'name' => 'Sophia Rodriguez',
                'courses' => ['Advanced Laravel Development', 'React and Redux Masterclass'],
                'last_session' => Carbon::now()->subDays(7)->format('Y-m-d')
            ],
        ];
    }

    // Get recent activity for the teacher
    public function getRecentActivityProperty()
    {
        // In a real app, you would fetch from the database
        // For now, returning mock data
        return [
            [
                'type' => 'session_completed',
                'title' => 'Session completed',
                'description' => 'Completed session with Alex Johnson on Laravel Middleware',
                'date' => Carbon::now()->subDays(1)->toDateTimeString(),
                'icon' => 'o-check-circle',
                'color' => 'bg-success-100 text-success-600'
            ],
            [
                'type' => 'course_updated',
                'title' => 'Course updated',
                'description' => 'Updated curriculum for Advanced Laravel Development',
                'date' => Carbon::now()->subDays(2)->toDateTimeString(),
                'icon' => 'o-document-text',
                'color' => 'bg-info-100 text-info-600'
            ],
            [
                'type' => 'session_scheduled',
                'title' => 'New session scheduled',
                'description' => 'Upcoming session with Emma Smith on React Components',
                'date' => Carbon::now()->subDays(3)->toDateTimeString(),
                'icon' => 'o-calendar',
                'color' => 'bg-primary-100 text-primary-600'
            ],
            [
                'type' => 'material_added',
                'title' => 'Material added',
                'description' => 'Added new learning materials to React and Redux Masterclass',
                'date' => Carbon::now()->subDays(4)->toDateTimeString(),
                'icon' => 'o-document-plus',
                'color' => 'bg-secondary-100 text-secondary-600'
            ],
        ];
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
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Welcome Banner -->
        <div class="mb-8 overflow-hidden text-white shadow-lg bg-gradient-to-r from-primary to-primary-focus rounded-xl">
            <div class="flex flex-col items-center md:flex-row">
                <div class="flex-1 p-6 md:p-8">
                    <h1 class="mb-2 text-3xl font-bold">Welcome back, {{ $user->name }}!</h1>
                    <p class="mb-4 text-white/80">
                        Teacher Dashboard
                    </p>

                    <div class="flex flex-wrap gap-2 mt-4">
                        <a href="{{ route('teachers.profile') }}" class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30">
                            <x-icon name="o-user" class="w-4 h-4 mr-1" />
                            View Profile
                        </a>
                        <a href="{{ route('teachers.sessions') }}" class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30">
                            <x-icon name="o-calendar" class="w-4 h-4 mr-1" />
                            My Sessions
                        </a>
                    </div>
                </div>
                <div class="hidden p-6 md:block">
                    <img src="{{ asset('images/dashboard-illustration.svg') }}" alt="Dashboard" class="h-32">
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-2 lg:grid-cols-3">
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <x-icon name="o-users" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Students</div>
                    <div class="stat-value text-primary">{{ $stats['total_students'] }}</div>
                    <div class="stat-desc">Active students</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <x-icon name="o-video-camera" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Sessions</div>
                    <div class="stat-value text-secondary">{{ $stats['total_sessions'] }}</div>
                    <div class="stat-desc">{{ $stats['upcoming_sessions'] }} upcoming</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-info">
                        <x-icon name="o-academic-cap" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Courses</div>
                    <div class="stat-value text-info">{{ $stats['total_courses'] }}</div>
                    <div class="stat-desc">Active courses</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column (Upcoming Sessions & Courses) -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Upcoming Sessions -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Upcoming Sessions</h2>
                            <a href="{{ route('teachers.sessions') }}" class="btn btn-sm btn-outline">View All</a>
                        </div>

                        @if(count($this->upcomingSessions) > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Session</th>
                                            <th>Student</th>
                                            <th>Date & Time</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->upcomingSessions as $session)
                                            <tr>
                                                <td class="font-medium">{{ $session['title'] }}</td>
                                                <td>{{ $session['student_name'] }}</td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <span>{{ $this->formatDate($session['date']) }}</span>
                                                        <span class="text-xs opacity-70">
                                                            {{ \Carbon\Carbon::parse($session['time'])->format('h:i A') }} -
                                                            {{ \Carbon\Carbon::parse($session['end_time'])->format('h:i A') }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="badge {{ $session['status'] === 'confirmed' ? 'badge-success' : 'badge-warning' }}">
                                                        {{ ucfirst($session['status']) }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex gap-1">
                                                        <a href="{{ route('teachers.sessions.show', $session['id']) }}" class="btn btn-xs btn-outline">
                                                            Details
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-4 text-center">
                                <p class="text-base-content/70">No upcoming sessions scheduled.</p>
                                <a href="{{ route('teachers.timetable') }}" class="mt-2 btn btn-sm btn-outline">
                                    View Timetable
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Active Courses -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Active Courses</h2>
                            <a href="{{ route('teachers.courses') }}" class="btn btn-sm btn-outline">View All</a>
                        </div>

                        @if(count($this->courses) > 0)
                            <div class="space-y-4">
                                @foreach($this->courses as $course)
                                    <div class="shadow-sm card bg-base-200">
                                        <div class="p-4 card-body">
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <h3 class="text-lg font-semibold">{{ $course['name'] }}</h3>
                                                    <div class="mt-1 text-sm opacity-70">
                                                        <span>{{ $course['students'] }} students enrolled</span>
                                                    </div>
                                                </div>
                                                <div class="badge {{ $course['status'] === 'active' ? 'badge-success' : 'badge-warning' }}">
                                                    {{ ucfirst($course['status']) }}
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-sm">Progress</span>
                                                    <span class="text-sm font-medium">{{ $course['progress'] }}%</span>
                                                </div>
                                                <div class="h-2 overflow-hidden rounded-full bg-base-300">
                                                    <div class="h-full {{ $course['progress'] > 75 ? 'bg-success' : ($course['progress'] > 40 ? 'bg-info' : 'bg-primary') }}" style="width: {{ $course['progress'] }}%"></div>
                                                </div>
                                            </div>
                                            <div class="justify-end mt-4 card-actions">
                                                <a href="{{ route('teachers.courses.show', $course['id']) }}" class="btn btn-sm btn-ghost">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-4 text-center">
                                <p class="text-base-content/70">No active courses found.</p>
                                <a href="{{ route('teachers.courses.create') }}" class="mt-2 btn btn-sm btn-primary">
                                    Create Course
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Profile Card -->
                @if($teacherProfile)
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex flex-col items-center text-center">
                            @if($teacherProfile->photo)
                                <div class="mb-4 avatar">
                                    <div class="w-24 h-24 rounded-xl">
                                        <img src="{{ Storage::url($teacherProfile->photo) }}" alt="{{ $user->name }}" />
                                    </div>
                                </div>
                            @else
                                <div class="mb-4 avatar placeholder">
                                    <div class="w-24 h-24 bg-neutral-focus text-neutral-content rounded-xl">
                                        <span class="text-3xl">{{ substr($user->name, 0, 1) }}</span>
                                    </div>
                                </div>
                            @endif

                            <h3 class="text-xl font-bold">{{ $user->name }}</h3>
                            <p class="text-sm opacity-70">{{ $teacherProfile->subjects->pluck('name')->join(', ') ?: 'Teacher' }}</p>

                            <div class="mt-2 badge badge-outline">{{ $teacherProfile->status ?: 'New Teacher' }}</div>

                            <div class="divider"></div>

                            <div class="w-full space-y-2">
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-user" class="w-4 h-4 opacity-70" />
                                    <span>{{ $user->name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-envelope" class="w-4 h-4 opacity-70" />
                                    <span>{{ $user->email }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-phone" class="w-4 h-4 opacity-70" />
                                    <span>{{ $teacherProfile->phone ?: 'Not provided' }}</span>
                                </div>
                            </div>

                            <div class="justify-center w-full mt-4 card-actions">
                                <a href="{{ route('teachers.profile') }}" class="btn btn-primary btn-sm">View Profile</a>
                                <a href="{{ route('teachers.profile.edit', $user) }}" class="btn btn-outline btn-sm">Edit</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Recent Students -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Recent Students</h2>
                        <div class="space-y-3">
                            @foreach($this->recentStudents as $student)
                                <div class="flex items-center gap-3">
                                    <div class="avatar placeholder">
                                        <div class="w-10 h-10 rounded-full bg-neutral-focus text-neutral-content">
                                            <span>{{ substr($student['name'], 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $student['name'] }}</div>
                                        <div class="text-xs opacity-70">
                                            {{ count($student['courses']) }} course(s): {{ implode(', ', $student['courses']) }}
                                        </div>
                                    </div>
                                    <div class="text-xs opacity-70">
                                        {{ $this->formatDate($student['last_session']) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="justify-center mt-4 card-actions">
                            <a href="{{ route('teachers.students.index') }}" class="btn btn-ghost btn-sm">View All Students</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Recent Activity</h2>
                        <div class="space-y-4">
                            @foreach($this->recentActivity as $activity)
                                <div class="flex gap-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $activity['color'] }}">
                                            <x-icon name="{{ $activity['icon'] }}" class="w-5 h-5" />
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $activity['title'] }}</div>
                                        <div class="text-sm opacity-70">{{ $activity['description'] }}</div>
                                        <div class="mt-1 text-xs opacity-50">{{ $this->formatDate($activity['date']) }}</div>
                                    </div>
                                </div>
                                @if(!$loop->last)
                                    <div class="h-4 ml-5 border-l-2 border-dashed border-base-300"></div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Quick Actions</h2>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="{{ route('teachers.sessions') }}" class="btn btn-outline">
                                <x-icon name="o-video-camera" class="w-4 h-4 mr-2" />
                                Sessions
                            </a>
                            <a href="{{ route('teachers.timetable') }}" class="btn btn-outline">
                                <x-icon name="o-calendar" class="w-4 h-4 mr-2" />
                                Timetable
                            </a>
                            <a href="{{ route('teachers.courses') }}" class="btn btn-outline">
                                <x-icon name="o-academic-cap" class="w-4 h-4 mr-2" />
                                Courses
                            </a>
                            <a href="{{ route('teachers.students.index') }}" class="btn btn-outline">
                                <x-icon name="o-users" class="w-4 h-4 mr-2" />
                                Students
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</d
