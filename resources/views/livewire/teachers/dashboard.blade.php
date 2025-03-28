<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Course;
use App\Models\LearningSession;
use App\Models\Children;
use App\Models\User;
use App\Models\Subject;
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
        // Get real stats from the database
        try {
            // Count unique children assigned to this teacher's sessions
            $totalStudents = Children::whereHas('learningSessions', function($query) {
                $query->where('teacher_id', $this->user->id);
            })->count();

            // Count all sessions for this teacher
            $totalSessions = LearningSession::where('teacher_id', $this->user->id)->count();

            // Count upcoming sessions
            $upcomingSessions = LearningSession::where('teacher_id', $this->user->id)
                ->where('start_time', '>', now())
                ->where('status', LearningSession::STATUS_SCHEDULED)
                ->count();

            // Count completed sessions
            $completedSessions = LearningSession::where('teacher_id', $this->user->id)
                ->where('status', LearningSession::STATUS_COMPLETED)
                ->count();

            // Count active courses
            $totalCourses = Course::where('teacher_profile_id', $this->teacherProfile->id)
                ->where('status', 'active')
                ->count();

            $this->stats = [
                'total_students' => $totalStudents,
                'total_sessions' => $totalSessions,
                'upcoming_sessions' => $upcomingSessions,
                'completed_sessions' => $completedSessions,
                'total_courses' => $totalCourses,
                'session_requests' => 0 // You can implement this if you have a session_requests feature
            ];
        } catch (\Exception $e) {
            // In case of error, leave default values as 0
            // You could add logging here if needed
        }
    }

    // Get upcoming sessions for the teacher (real data)
    public function getUpcomingSessionsProperty()
    {
        return LearningSession::with(['children', 'subject', 'course'])
            ->where('teacher_id', $this->user->id)
            ->where('start_time', '>', now())
            ->where('status', LearningSession::STATUS_SCHEDULED)
            ->orderBy('start_time')
            ->limit(5)
            ->get();
    }

    // Get active courses for the teacher (real data)
    public function getCoursesProperty()
    {
        return Course::where('teacher_profile_id', $this->teacherProfile->id)
            ->where('status', 'active')
            ->withCount('enrollments')
            ->limit(5)
            ->get();
    }

    // Get recent students for the teacher (real data)
    public function getRecentStudentsProperty()
    {
        return Children::whereHas('learningSessions', function($query) {
                $query->where('teacher_id', $this->user->id);
            })
            ->withCount('learningSessions')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
    }

    // Get recent activity for the teacher (real data from learning sessions)
    public function getRecentActivityProperty()
    {
        $recentSessions = LearningSession::with(['children', 'subject'])
            ->where('teacher_id', $this->user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        $activities = [];

        foreach($recentSessions as $session) {
            $activityType = '';
            $title = '';
            $description = '';
            $icon = '';
            $color = '';

            if($session->status === LearningSession::STATUS_COMPLETED) {
                $activityType = 'session_completed';
                $title = 'Session completed';
                $description = 'Completed session with ' . ($session->children->name ?? 'Student') .
                               ' on ' . ($session->subject->name ?? 'Subject');
                $icon = 'o-check-circle';
                $color = 'bg-success-100 text-success-600';
            } elseif($session->status === LearningSession::STATUS_SCHEDULED) {
                $activityType = 'session_scheduled';
                $title = 'New session scheduled';
                $description = 'Upcoming session with ' . ($session->children->name ?? 'Student') .
                               ' on ' . ($session->subject->name ?? 'Subject');
                $icon = 'o-calendar';
                $color = 'bg-primary-100 text-primary-600';
            } elseif($session->status === LearningSession::STATUS_CANCELLED) {
                $activityType = 'session_cancelled';
                $title = 'Session cancelled';
                $description = 'Cancelled session with ' . ($session->children->name ?? 'Student') .
                               ' on ' . ($session->subject->name ?? 'Subject');
                $icon = 'o-x-circle';
                $color = 'bg-error-100 text-error-600';
            }

            $activities[] = [
                'type' => $activityType,
                'title' => $title,
                'description' => $description,
                'date' => $session->updated_at->toDateTimeString(),
                'icon' => $icon,
                'color' => $color
            ];
        }

        return $activities;
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
                                                <td class="font-medium">
                                                    {{ $session->subject ? $session->subject->name : 'Untitled Session' }}
                                                </td>
                                                <td>{{ $session->children ? $session->children->name : 'Unknown Student' }}</td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <span>{{ $session->start_time ? $this->formatDate($session->start_time) : 'Date TBD' }}</span>
                                                        <span class="text-xs opacity-70">
                                                            {{ $session->start_time ? $session->start_time->format('h:i A') : '' }} -
                                                            {{ $session->end_time ? $session->end_time->format('h:i A') : '' }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="badge {{ $session->status === LearningSession::STATUS_SCHEDULED ? 'badge-success' : 'badge-warning' }}">
                                                        {{ ucfirst($session->status) }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex gap-1">
                                                        <a href="{{ route('teachers.sessions.show', $session->id) }}" class="btn btn-xs btn-outline">
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
                                                    <h3 class="text-lg font-semibold">{{ $course->name }}</h3>
                                                    <div class="mt-1 text-sm opacity-70">
                                                        <span>{{ $course->enrollments_count ?? 0 }} students enrolled</span>
                                                    </div>
                                                </div>
                                                <div class="badge {{ $course->status === 'active' ? 'badge-success' : 'badge-warning' }}">
                                                    {{ ucfirst($course->status) }}
                                                </div>
                                            </div>

                                            @php
                                                // Calculate course progress - this is a simplified example
                                                // You would replace this with real logic based on your app's design
                                                $progress = 0;
                                                if($course->start_date && $course->end_date) {
                                                    $totalDays = $course->start_date->diffInDays($course->end_date);
                                                    $elapsedDays = $course->start_date->diffInDays(now());
                                                    if($totalDays > 0) {
                                                        $progress = min(100, round(($elapsedDays / $totalDays) * 100));
                                                    }
                                                }
                                            @endphp

                                            <div class="mt-3">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-sm">Progress</span>
                                                    <span class="text-sm font-medium">{{ $progress }}%</span>
                                                </div>
                                                <div class="h-2 overflow-hidden rounded-full bg-base-300">
                                                    <div class="h-full {{ $progress > 75 ? 'bg-success' : ($progress > 40 ? 'bg-info' : 'bg-primary') }}" style="width: {{ $progress }}%"></div>
                                                </div>
                                            </div>
                                            <div class="justify-end mt-4 card-actions">
                                                <a href="{{ route('teachers.courses.show', $course->id) }}" class="btn btn-sm btn-ghost">View Details</a>
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
                            @if(count($this->recentStudents) > 0)
                                @foreach($this->recentStudents as $student)
                                    <div class="flex items-center gap-3">
                                        <div class="avatar placeholder">
                                            <div class="w-10 h-10 rounded-full bg-neutral-focus text-neutral-content">
                                                <span>{{ substr($student->name, 0, 1) }}</span>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="font-medium">{{ $student->name }}</div>
                                            <div class="text-xs opacity-70">
                                                {{ $student->learning_sessions_count ?? 0 }} sessions
                                            </div>
                                        </div>
                                        <div class="text-xs opacity-70">
                                            {{ $this->formatDate($student->updated_at) }}
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="p-4 text-center">
                                    <p class="text-base-content/70">No students found.</p>
                                </div>
                            @endif
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
                            @if(count($this->recentActivity) > 0)
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
                            @else
                                <div class="p-4 text-center">
                                    <p class="text-base-content/70">No recent activity.</p>
                                </div>
                            @endif
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
</div>
