<?php

namespace App\Livewire\Teachers\Students;

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Children;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningSession;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public $student;
    public $teacher;
    public $teacherProfile;

    // Tab management
    public $activeTab = 'overview';

    // Session and enrollment collections
    public $sessions = [];
    public $enrollments = [];

    // Message modal
    public $showMessageModal = false;
    public $messageSubject = '';
    public $messageContent = '';

    // Analytics data
    public $monthlyAttendanceData = [];
    public $progressData = [];
    public $performanceData = [];

    // Filter states
    public $sessionStatusFilter = '';
    public $sessionDateFilter = '';

    public function mount($student)
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        // In a real app, we would fetch the actual student data
        $this->student = $this->getMockStudent($student);

        // Load related data
        $this->loadSessions();
        $this->loadEnrollments();
        $this->generateAnalyticsData();

        // Set active tab from query parameter if available
        $this->activeTab = request()->has('tab') ? request()->get('tab') : 'overview';
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function loadSessions()
    {
        // In a real app, this would fetch from the database
        $this->sessions = $this->getMockSessionsForStudent($this->student['id']);
    }

    public function loadEnrollments()
    {
        // In a real app, this would fetch from the database
        $this->enrollments = $this->getMockEnrollmentsForStudent($this->student['id']);
    }

    public function generateAnalyticsData()
    {
        // Generate monthly attendance data for chart
        $this->generateMonthlyAttendanceData();

        // Generate progress data for chart
        $this->generateProgressData();

        // Generate performance data for chart
        $this->generatePerformanceData();
    }

    public function generateMonthlyAttendanceData()
    {
        // Generate data for the last 6 months
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthName = $month->format('M');
            $monthSessions = collect($this->sessions)->filter(function($session) use ($month) {
                $sessionDate = Carbon::parse($session['date']);
                return $sessionDate->month === $month->month && $sessionDate->year === $month->year;
            });

            $totalSessions = $monthSessions->count();
            $attendedSessions = $monthSessions->filter(function($session) {
                return $session['attended'] === true;
            })->count();

            $rate = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100) : 0;

            $data[] = [
                'month' => $monthName,
                'rate' => $rate,
                'total' => $totalSessions,
                'attended' => $attendedSessions
            ];
        }

        $this->monthlyAttendanceData = $data;
    }

    public function generateProgressData()
    {
        // Use the enrollments to create progress data
        $data = [];

        foreach ($this->enrollments as $enrollment) {
            $data[] = [
                'course' => $enrollment['course_name'],
                'progress' => $enrollment['progress'],
                'status' => $enrollment['status']
            ];
        }

        $this->progressData = $data;
    }

    public function generatePerformanceData()
    {
        // Create performance data from sessions
        $courseSessions = collect($this->sessions)
            ->filter(function($session) {
                return $session['performance_score'] !== null;
            })
            ->groupBy('course_name');

        $data = [];

        foreach ($courseSessions as $courseName => $sessions) {
            $avgScore = $sessions->avg('performance_score');

            $data[] = [
                'course' => $courseName,
                'score' => round($avgScore, 1),
                'sessions' => $sessions->count()
            ];
        }

        $this->performanceData = $data;
    }

    public function getSessionsProperty()
    {
        $sessions = collect($this->sessions);

        // Apply status filter
        if (!empty($this->sessionStatusFilter)) {
            $sessions = $sessions->filter(function($session) {
                return $session['status'] === $this->sessionStatusFilter;
            });
        }

        // Apply date filter
        if (!empty($this->sessionDateFilter)) {
            $sessions = $sessions->filter(function($session) {
                $sessionDate = Carbon::parse($session['date']);

                switch ($this->sessionDateFilter) {
                    case 'past':
                        return $sessionDate->isPast();
                    case 'future':
                        return $sessionDate->isFuture();
                    case 'this_week':
                        return $sessionDate->isCurrentWeek();
                    case 'this_month':
                        return $sessionDate->isCurrentMonth();
                    default:
                        return true;
                }
            });
        }

        // Sort sessions by date descending
        return $sessions->sortByDesc('date')->values()->all();
    }

    public function getStudentStatsProperty()
    {
        $sessions = collect($this->sessions);
        $enrollments = collect($this->enrollments);

        // Total sessions
        $totalSessions = $sessions->count();

        // Attendance rate
        $attendedSessions = $sessions->filter(function($session) {
            return $session['attended'] === true;
        })->count();

        $attendanceRate = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100) : 0;

        // Completed courses
        $completedCourses = $enrollments->filter(function($enrollment) {
            return $enrollment['status'] === 'completed';
        })->count();

        // Avg performance score
        $performanceSessions = $sessions->filter(function($session) {
            return $session['performance_score'] !== null;
        });

        $avgPerformance = $performanceSessions->count() > 0
            ? round($performanceSessions->avg('performance_score'), 1)
            : 0;

        // Recent improvement
        $recentImprovement = $this->calculateRecentImprovement();

        return [
            'total_sessions' => $totalSessions,
            'attendance_rate' => $attendanceRate,
            'completed_courses' => $completedCourses,
            'avg_performance' => $avgPerformance,
            'recent_improvement' => $recentImprovement,
            'active_enrollments' => $enrollments->where('status', 'active')->count()
        ];
    }

    public function calculateRecentImprovement()
    {
        // Calculate if there's improvement in the last 3 sessions compared to previous sessions
        $sessions = collect($this->sessions)
            ->filter(function($session) {
                return $session['performance_score'] !== null;
            })
            ->sortByDesc('date')
            ->values();

        if ($sessions->count() < 4) {
            return 0; // Not enough data
        }

        // Get the average of the last 3 sessions
        $recentAvg = $sessions->take(3)->avg('performance_score');

        // Get the average of the previous 3 sessions
        $previousAvg = $sessions->slice(3, 3)->avg('performance_score');

        if ($previousAvg == 0) {
            return 0;
        }

        return round((($recentAvg - $previousAvg) / $previousAvg) * 100);
    }

    public function getUpcomingSessionsProperty()
    {
        return collect($this->sessions)
            ->filter(function($session) {
                return Carbon::parse($session['date'])->isFuture();
            })
            ->sortBy('date')
            ->take(3)
            ->values()
            ->all();
    }

    public function openMessageModal()
    {
        $this->messageSubject = 'Message to ' . $this->student['name'];
        $this->messageContent = '';
        $this->showMessageModal = true;
    }

    public function closeMessageModal()
    {
        $this->showMessageModal = false;
        $this->messageSubject = '';
        $this->messageContent = '';
    }

    public function sendMessage()
    {
        // Validate the form
        $this->validate([
            'messageSubject' => 'required|min:3|max:100',
            'messageContent' => 'required|min:10|max:1000',
        ]);

        // In a real app, this would send the message to the student

        // Show success message
        $this->toast(
            type: 'success',
            title: 'Message sent',
            description: 'Your message has been sent to ' . $this->student['name'],
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeMessageModal();
    }

    // Format date for display
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // Get relative time
    public function getRelativeTime($date)
    {
        return Carbon::parse($date)->diffForHumans();
    }

    // Get status badge class
    public function getStatusBadgeClass($status)
    {
        switch ($status) {
            case 'active':
                return 'badge-success';
            case 'inactive':
                return 'badge-error';
            case 'completed':
                return 'badge-success';
            case 'scheduled':
                return 'badge-info';
            case 'cancelled':
                return 'badge-warning';
            default:
                return 'badge-info';
        }
    }

    // Mock data - would be fetched from database in real app
    private function getMockStudent($id)
    {
        return [
            'id' => $id,
            'name' => 'Alex Johnson',
            'email' => 'alex.johnson@example.com',
            'avatar' => null,
            'status' => 'active',
            'enrolled_at' => Carbon::now()->subDays(60)->format('Y-m-d'),
            'last_session_at' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'total_sessions' => 15,
            'attendance_rate' => 90,
            'progress' => 65,
            'parent_name' => 'Robert Johnson',
            'parent_email' => 'robert.johnson@example.com',
            'parent_phone' => '+1 (555) 123-4567',
            'age' => 16,
            'grade' => '10th Grade',
            'school' => 'Lincoln High School',
            'interests' => ['Programming', 'Web Development', 'Mathematics'],
            'learning_style' => 'Visual',
            'notes' => 'Alex is a dedicated student who shows great potential in programming. He grasps concepts quickly but sometimes needs help with practical applications.'
        ];
    }

    private function getMockSessionsForStudent($studentId)
    {
        // Generate some mock sessions for the student
        $sessions = [];
        $count = 20;

        // List of course names
        $courseNames = [
            'Advanced Laravel Development',
            'React and Redux Masterclass',
            'UI/UX Design Fundamentals',
            'Mobile Development with Flutter'
        ];

        // Create past sessions
        for ($i = 0; $i < 15; $i++) {
            $courseName = $courseNames[array_rand($courseNames)];
            $date = Carbon::now()->subDays(($i + 1) * 5)->format('Y-m-d');
            $performanceScore = rand(2, 5); // Random score between 2-5

            $sessions[] = [
                'id' => $i + 1,
                'title' => "$courseName - Session #" . ($i + 1),
                'course_id' => array_search($courseName, $courseNames) + 1,
                'course_name' => $courseName,
                'date' => $date,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'status' => 'completed',
                'attended' => rand(0, 10) > 1, // 90% attendance rate
                'performance_score' => $performanceScore,
                'notes' => "Student demonstrated " . ($performanceScore >= 4 ? "excellent" : "good") . " understanding of the concepts.",
                'topics_covered' => $this->getTopicsForCourse($courseName)
            ];
        }

        // Create future sessions
        for ($i = 0; $i < 5; $i++) {
            $courseName = $courseNames[array_rand($courseNames)];
            $date = Carbon::now()->addDays(($i + 1) * 4)->format('Y-m-d');

            $sessions[] = [
                'id' => $i + 16,
                'title' => "$courseName - Session #" . ($i + 16),
                'course_id' => array_search($courseName, $courseNames) + 1,
                'course_name' => $courseName,
                'date' => $date,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'status' => 'scheduled',
                'attended' => null,
                'performance_score' => null,
                'notes' => null,
                'topics_covered' => $this->getTopicsForCourse($courseName)
            ];
        }

        return $sessions;
    }

    private function getTopicsForCourse($courseName)
    {
        $topics = [
            'Advanced Laravel Development' => ['Middleware', 'Service Containers', 'Eloquent Relationships', 'API Development'],
            'React and Redux Masterclass' => ['Hooks', 'Redux State Management', 'Context API', 'React Router'],
            'UI/UX Design Fundamentals' => ['Color Theory', 'Typography', 'User Research', 'Wireframing'],
            'Mobile Development with Flutter' => ['Dart Basics', 'Flutter Widgets', 'State Management', 'Navigation']
        ];

        return $topics[$courseName] ?? ['General Topics'];
    }

    private function getMockEnrollmentsForStudent($studentId)
    {
        $enrollments = [
            [
                'id' => 1,
                'student_id' => $studentId,
                'course_id' => 1,
                'course_name' => 'Advanced Laravel Development',
                'enrollment_date' => Carbon::now()->subDays(60)->format('Y-m-d'),
                'status' => 'active',
                'progress' => 65,
                'completion_date' => null,
                'teacher_name' => 'Dr. Jane Smith',
                'description' => 'Master advanced Laravel concepts including Middleware, Service Containers, and more.',
                'start_date' => Carbon::now()->subDays(60)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
            ],
            [
                'id' => 2,
                'student_id' => $studentId,
                'course_id' => 3,
                'course_name' => 'UI/UX Design Fundamentals',
                'enrollment_date' => Carbon::now()->subDays(45)->format('Y-m-d'),
                'status' => 'active',
                'progress' => 40,
                'completion_date' => null,
                'teacher_name' => 'Prof. Michael Chen',
                'description' => 'Learn the principles of effective UI/UX design and implement them in real projects.',
                'start_date' => Carbon::now()->subDays(45)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(45)->format('Y-m-d'),
            ],
            [
                'id' => 3,
                'student_id' => $studentId,
                'course_id' => 2,
                'course_name' => 'React and Redux Masterclass',
                'enrollment_date' => Carbon::now()->subDays(90)->format('Y-m-d'),
                'status' => 'completed',
                'progress' => 100,
                'completion_date' => Carbon::now()->subDays(15)->format('Y-m-d'),
                'teacher_name' => 'Sarah Johnson',
                'description' => 'Comprehensive guide to building scalable applications with React and Redux.',
                'start_date' => Carbon::now()->subDays(90)->format('Y-m-d'),
                'end_date' => Carbon::now()->subDays(15)->format('Y-m-d'),
            ]
        ];

        return $enrollments;
    }

    // Toast notification helper function
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
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('teachers.students.index') }}" class="btn btn-sm btn-ghost">
                    <x-icon name="o-arrow-left" class="w-4 h-4" />
                    Back to Students
                </a>
            </div>

            <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div class="flex items-center gap-4">
                    <div class="avatar placeholder">
                        <div class="w-16 h-16 rounded-full bg-neutral-focus text-neutral-content">
                            <span class="text-xl">{{ substr($student['name'], 0, 1) }}</span>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold">{{ $student['name'] }}</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-base-content/70">{{ $student['email'] }}</span>
                            <div class="badge {{ $this->getStatusBadgeClass($student['status']) }}">
                                {{ ucfirst($student['status']) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button
                        wire:click="openMessageModal"
                        class="btn btn-primary"
                    >
                        <x-icon name="o-envelope" class="w-4 h-4 mr-2" />
                        Message Student
                    </button>
                </div>
            </div>
        </div>

        <!-- Key Stats Cards -->
        <div class="grid grid-cols-1 gap-4 mb-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
            <div class="p-4 shadow-md rounded-box bg-base-100">
                <div class="text-xs font-medium text-base-content/70">Sessions</div>
                <div class="text-2xl font-bold">{{ $this->studentStats['total_sessions'] }}</div>
            </div>

            <div class="p-4 shadow-md rounded-box bg-base-100">
                <div class="text-xs font-medium text-base-content/70">Attendance</div>
                <div class="text-2xl font-bold">{{ $this->studentStats['attendance_rate'] }}%</div>
            </div>

            <div class="p-4 shadow-md rounded-box bg-base-100">
                <div class="text-xs font-medium text-base-content/70">Active Courses</div>
                <div class="text-2xl font-bold">{{ $this->studentStats['active_enrollments'] }}</div>
            </div>

            <div class="p-4 shadow-md rounded-box bg-base-100">
                <div class="text-xs font-medium text-base-content/70">Completed</div>
                <div class="text-2xl font-bold">{{ $this->studentStats['completed_courses'] }}</div>
            </div>

            <div class="p-4 shadow-md rounded-box bg-base-100">
                <div class="text-xs font-medium text-base-content/70">Performance</div>
                <div class="text-2xl font-bold">{{ $this->studentStats['avg_performance'] }}/5</div>
            </div>

            <div class="p-4 shadow-md rounded-box bg-base-100">
                <div class="text-xs font-medium text-base-content/70">Improvement</div>
                <div class="text-2xl font-bold {{ $this->studentStats['recent_improvement'] >= 0 ? 'text-success' : 'text-error' }}">
                    {{ $this->studentStats['recent_improvement'] >= 0 ? '+' : '' }}{{ $this->studentStats['recent_improvement'] }}%
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="mb-6 tabs tabs-boxed">
            <a
                wire:click.prevent="setActiveTab('overview')"
                class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}"
            >
                Overview
            </a>
            <a
                wire:click.prevent="setActiveTab('sessions')"
                class="tab {{ $activeTab === 'sessions' ? 'tab-active' : '' }}"
            >
                Sessions
            </a>
            <a
                wire:click.prevent="setActiveTab('enrollments')"
                class="tab {{ $activeTab === 'enrollments' ? 'tab-active' : '' }}"
            >
                Courses
            </a>
            <a
                wire:click.prevent="setActiveTab('analytics')"
                class="tab {{ $activeTab === 'analytics' ? 'tab-active' : '' }}"
            >
                Analytics
            </a>
            <a
                wire:click.prevent="setActiveTab('details')"
                class="tab {{ $activeTab === 'details' ? 'tab-active' : '' }}"
            >
                Details
            </a>
        </div>

        <!-- Tab Content -->
        <div class="min-h-[60vh]">
            <!-- Overview Tab -->
            <div class="{{ $activeTab === 'overview' ? '' : 'hidden' }}">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Student Information -->
                    <div class="lg:col-span-1">
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="card-title">Student Information</h2>

                                <div class="mt-4 space-y-4">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <div class="text-xs font-medium text-base-content/70">Age</div>
                                            <div>{{ $student['age'] }} years</div>
                                        </div>
                                        <div>
                                            <div class="text-xs font-medium text-base-content/70">Grade</div>
                                            <div>{{ $student['grade'] }}</div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-base-content/70">School</div>
                                        <div>{{ $student['school'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-base-content/70">Enrolled Since</div>
                                        <div>{{ $this->formatDate($student['enrolled_at']) }}</div>
                                        <div class="text-xs text-base-content/60">{{ $this->getRelativeTime($student['enrolled_at']) }}</div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-base-content/70">Last Session</div>
                                        <div>{{ $this->formatDate($student['last_session_at']) }}</div>
                                        <div class="text-xs text-base-content/60">{{ $this->getRelativeTime($student['last_session_at']) }}</div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-base-content/70">Interests</div>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($student['interests'] as $interest)
                                                <div class="badge badge-outline">{{ $interest }}</div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs font-medium text-base-content/70">Learning Style</div>
                                        <div>{{ $student['learning_style'] }}</div>
                                    </div>

                                    <div class="pt-2">
                                        <div class="text-xs font-medium text-base-content/70">Parent/Guardian</div>
                                        <div class="font-medium">{{ $student['parent_name'] }}</div>
                                        <div class="text-sm">{{ $student['parent_email'] }}</div>
                                        <div class="text-sm">{{ $student['parent_phone'] }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Teacher Notes -->
                        <div class="mt-6 shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="card-title">Notes</h2>
                                <p class="mt-2">{{ $student['notes'] }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Overview Content -->
                    <div class="lg:col-span-2">
                        <!-- Upcoming Sessions -->
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <div class="flex items-center justify-between">
                                    <h2 class="card-title">Upcoming Sessions</h2>
                                    <a href="#" wire:click.prevent="setActiveTab('sessions')" class="btn btn-sm btn-ghost">View All</a>
                                </div>

                                @if(count($this->upcomingSessions) > 0)
                                    <div class="mt-4 space-y-4">
                                        @foreach($this->upcomingSessions as $session)
                                            <div class="p-4 border rounded-lg border-base-300">
                                                <div class="flex flex-wrap items-start justify-between gap-2">
                                                    <div>
                                                        <h3 class="font-semibold">{{ $session['title'] }}</h3>
                                                        <div class="mt-1 text-sm">
                                                            <span class="font-medium">{{ $this->formatDate($session['date']) }}</span> •
                                                            {{ \Carbon\Carbon::parse($session['start_time'])->format('h:i A') }} -
                                                            {{ \Carbon\Carbon::parse($session['end_time'])->format('h:i A') }}
                                                        </div>
                                                        <div class="mt-1 text-sm text-base-content/70">
                                                            Topics: {{ implode(', ', $session['topics_covered']) }}
                                                        </div>
                                                    </div>
                                                    <div class="badge {{ $this->getStatusBadgeClass($session['status']) }}">
                                                        {{ ucfirst($session['status']) }}
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="p-6 mt-4 text-center bg-base-200 rounded-xl">
                                        <p>No upcoming sessions scheduled.</p>
                                        <button class="mt-2 btn btn-sm btn-outline">Schedule Session</button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Enrolled Courses -->
                        <div class="mt-6 shadow-xl card bg-base-100">
                            <div class="card-body">
                                <div class="flex items-center justify-between">
                                    <h2 class="card-title">Current Enrollments</h2>
                                    <a href="#" wire:click.prevent="setActiveTab('enrollments')" class="btn btn-sm btn-ghost">View All</a>
                                </div>

                                <div class="mt-4 space-y-4">
                                    @foreach($enrollments as $enrollment)
                                        @if($enrollment['status'] === 'active')
                                            <div class="p-4 border rounded-lg border-base-300">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <h3 class="font-semibold">{{ $enrollment['course_name'] }}</h3>
                                                        <p class="mt-1 text-sm text-base-content/70">{{ $enrollment['description'] }}</p>
                                                        <div class="mt-2 text-sm">
                                                            <span class="font-medium">Enrolled:</span> {{ $this->formatDate($enrollment['enrollment_date']) }}
                                                        </div>
                                                    </div>
                                                    <div class="badge {{ $this->getStatusBadgeClass($enrollment['status']) }}">
                                                        {{ ucfirst($enrollment['status']) }}
                                                    </div>
                                                </div>

                                                <div class="mt-4">
                                                    <div class="flex items-center justify-between mb-1 text-sm">
                                                        <span>Progress</span>
                                                        <span>{{ $enrollment['progress'] }}%</span>
                                                    </div>
                                                    <progress class="w-full progress progress-primary" value="{{ $enrollment['progress'] }}" max="100"></progress>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Performance Overview -->
                        <div class="mt-6 shadow-xl card bg-base-100">
                            <div class="card-body">
                                <div class="flex items-center justify-between">
                                    <h2 class="card-title">Performance Overview</h2>
                                    <a href="#" wire:click.prevent="setActiveTab('analytics')" class="btn btn-sm btn-ghost">View Analytics</a>
                                </div>

                                <div class="grid grid-cols-1 gap-6 mt-4 lg:grid-cols-2">
                                    <div>
                                        <h3 class="text-lg font-medium">Attendance Rate</h3>
                                        <div class="flex items-center mt-2">
                                            <div class="radial-progress text-primary" style="--value:{{ $this->studentStats['attendance_rate'] }}; --size:5rem;">
                                                {{ $this->studentStats['attendance_rate'] }}%
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm">
                                                    <span class="font-medium">{{ $this->studentStats['attendance_rate'] }}% attendance</span> over
                                                    {{ $this->studentStats['total_sessions'] }} sessions
                                                </div>
                                                @if($this->studentStats['attendance_rate'] >= 90)
                                                    <div class="text-sm text-success">Excellent attendance record</div>
                                                @elseif($this->studentStats['attendance_rate'] >= 75)
                                                    <div class="text-sm text-success">Good attendance record</div>
                                                @else
                                                    <div class="text-sm text-warning">Attendance needs improvement</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 class="text-lg font-medium">Recent Performance</h3>
                                        <div class="flex items-center mt-2">
                                            <div class="radial-progress text-secondary" style="--value:{{ $this->studentStats['avg_performance'] * 20 }}; --size:5rem;">
                                                {{ $this->studentStats['avg_performance'] }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm">
                                                    <span class="font-medium">{{ $this->studentStats['avg_performance'] }}/5 average score</span>
                                                </div>
                                                <div class="text-sm {{ $this->studentStats['recent_improvement'] >= 0 ? 'text-success' : 'text-error' }}">
                                                    {{ $this->studentStats['recent_improvement'] >= 0 ? '+' : '' }}{{ $this->studentStats['recent_improvement'] }}% improvement
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sessions Tab -->
            <div class="{{ $activeTab === 'sessions' ? '' : 'hidden' }}">
                <div class="mb-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="card-title">Sessions</h2>

                            <div class="flex flex-wrap gap-2">
                                <!-- Status Filter -->
                                <select wire:model.live="sessionStatusFilter" class="select select-bordered select-sm">
                                    <option value="">All Statuses</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>

                                <!-- Date Filter -->
                                <select wire:model.live="sessionDateFilter" class="select select-bordered select-sm">
                                    <option value="">All Dates</option>
                                    <option value="future">Upcoming</option>
                                    <option value="past">Past</option>
                                    <option value="this_week">This Week</option>
                                    <option value="this_month">This Month</option>
                                </select>

                                <button class="btn btn-primary btn-sm">
                                    <x-icon name="o-plus" class="w-4 h-4 mr-1" />
                                    Schedule Session
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($this->sessions) > 0)
                    <div class="space-y-4">
                        @foreach($this->sessions as $session)
                            <div class="shadow-lg card bg-base-100">
                                <div class="card-body">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <h3 class="text-lg font-semibold">{{ $session['title'] }}</h3>
                                                <div class="badge {{ $this->getStatusBadgeClass($session['status']) }}">
                                                    {{ ucfirst($session['status']) }}
                                                </div>
                                                @if($session['attended'] === true)
                                                    <div class="badge badge-success">Attended</div>
                                                @elseif($session['attended'] === false)
                                                    <div class="badge badge-error">Absent</div>
                                                @endif
                                            </div>

                                            <div class="mt-2">
                                                <div class="flex flex-wrap gap-x-6 gap-y-2">
                                                    <div class="flex items-center gap-1">
                                                        <x-icon name="o-calendar" class="w-4 h-4 text-base-content/70" />
                                                        <span>{{ $this->formatDate($session['date']) }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-1">
                                                        <x-icon name="o-clock" class="w-4 h-4 text-base-content/70" />
                                                        <span>
                                                            {{ \Carbon\Carbon::parse($session['start_time'])->format('h:i A') }} -
                                                            {{ \Carbon\Carbon::parse($session['end_time'])->format('h:i A') }}
                                                        </span>
                                                    </div>
                                                    @if($session['performance_score'])
                                                        <div class="flex items-center gap-1">
                                                            <x-icon name="o-chart-bar" class="w-4 h-4 text-base-content/70" />
                                                            <span>Score: {{ $session['performance_score'] }}/5</span>
                                                        </div>
                                                    @endif
                                                </div>

                                                @if(count($session['topics_covered']) > 0)
                                                    <div class="mt-3">
                                                        <div class="text-sm font-medium">Topics Covered:</div>
                                                        <div class="flex flex-wrap gap-1 mt-1">
                                                            @foreach($session['topics_covered'] as $topic)
                                                                <div class="badge badge-outline">{{ $topic }}</div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                @if($session['notes'])
                                                    <div class="mt-3">
                                                        <div class="text-sm font-medium">Session Notes:</div>
                                                        <p class="mt-1 text-sm">{{ $session['notes'] }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        @if($session['status'] === 'scheduled')
                                            <div class="flex flex-wrap gap-2">
                                                <button class="btn btn-sm btn-outline">
                                                    <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                                    Edit
                                                </button>
                                                <button class="btn btn-sm btn-error btn-outline">
                                                    <x-icon name="o-x-mark" class="w-4 h-4 mr-1" />
                                                    Cancel
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center bg-base-200 rounded-xl">
                        <x-icon name="o-calendar" class="w-12 h-12 mx-auto mb-4 text-base-content/30" />
                        <h3 class="text-lg font-semibold">No sessions found</h3>
                        <p class="mt-1 text-base-content/70">
                            No sessions match the current filters.
                        </p>
                        @if($sessionStatusFilter || $sessionDateFilter)
                            <button
                                wire:click="$set('sessionStatusFilter', ''); $set('sessionDateFilter', '');"
                                class="mt-4 btn btn-outline"
                            >
                                Clear Filters
                            </button>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Enrollments Tab -->
            <div class="{{ $activeTab === 'enrollments' ? '' : 'hidden' }}">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    @foreach($enrollments as $enrollment)
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <div class="flex items-start justify-between">
                                    <h3 class="text-lg font-semibold">{{ $enrollment['course_name'] }}</h3>
                                    <div class="badge {{ $this->getStatusBadgeClass($enrollment['status']) }}">
                                        {{ ucfirst($enrollment['status']) }}
                                    </div>
                                </div>

                                <p class="mt-2 text-sm">{{ $enrollment['description'] }}</p>

                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <div class="text-xs font-medium text-base-content/70">Enrolled</div>
                                        <div>{{ $this->formatDate($enrollment['enrollment_date']) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-base-content/70">Teacher</div>
                                        <div>{{ $enrollment['teacher_name'] }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-base-content/70">Start Date</div>
                                        <div>{{ $this->formatDate($enrollment['start_date']) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-base-content/70">End Date</div>
                                        <div>{{ $this->formatDate($enrollment['end_date']) }}</div>
                                    </div>
                                </div>

                                <div class="divider"></div>

                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span>Progress</span>
                                        <span>{{ $enrollment['progress'] }}%</span>
                                    </div>
                                    <progress class="w-full progress progress-primary" value="{{ $enrollment['progress'] }}" max="100"></progress>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Analytics Tab -->
            <div class="{{ $activeTab === 'analytics' ? '' : 'hidden' }}">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Attendance Chart -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Monthly Attendance</h2>

                            <div class="overflow-x-auto">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Sessions</th>
                                            <th>Attended</th>
                                            <th>Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($monthlyAttendanceData as $month)
                                            <tr>
                                                <td>{{ $month['month'] }}</td>
                                                <td>{{ $month['total'] }}</td>
                                                <td>{{ $month['attended'] }}</td>
                                                <td>
                                                    <div class="flex items-center gap-2">
                                                        <progress class="w-16 progress" value="{{ $month['rate'] }}" max="100"></progress>
                                                        <span>{{ $month['rate'] }}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Performance by Course Chart -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Performance by Course</h2>

                            <div class="overflow-x-auto">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Sessions</th>
                                            <th>Avg. Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($performanceData as $course)
                                            <tr>
                                                <td>{{ $course['course'] }}</td>
                                                <td>{{ $course['sessions'] }}</td>
                                                <td>
                                                    <div class="flex items-center gap-2">
                                                        <div class="rating rating-xs rating-half">
                                                            @for($i = 1; $i <= 5; $i++)
                                                                @if($course['score'] >= $i)
                                                                    <input type="radio" name="rating-{{ $loop->index }}" class="bg-yellow-400 rating-hidden" disabled />
                                                                    <input type="radio" name="rating-{{ $loop->index }}" class="bg-yellow-400 mask mask-star-2 mask-half-1" disabled checked />
                                                                    <input type="radio" name="rating-{{ $loop->index }}" class="bg-yellow-400 mask mask-star-2 mask-half-2" disabled checked />
                                                                @elseif($course['score'] > $i - 0.5)
                                                                    <input type="radio" name="rating-{{ $loop->index }}" class="bg-yellow-400 rating-hidden" disabled />
                                                                    <input type="radio" name="rating-{{ $loop->index }}" class="bg-yellow-400 mask mask-star-2 mask-half-1" disabled checked />
                                                                    <input type="radio" name="rating-{{ $loop->index }}" class="mask mask-star-2 mask-half-2 bg-base-300" disabled />
                                                                @else
                                                                    <input type="radio" name="rating-{{ $loop->index }}" class="bg-yellow-400 rating-hidden" disabled />
                                                                    <input type="radio" name="rating-{{ $loop->index }}" class="mask mask-star-2 mask-half-1 bg-base-300" disabled />
                                                                    <input type="radio" name="rating-{{ $loop->index }}" class="mask mask-star-2 mask-half-2 bg-base-300" disabled />
                                                                @endif
                                                            @endfor
                                                        </div>
                                                        <span>{{ $course['score'] }}/5</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Course Progress Chart -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Course Progress</h2>

                            <div class="mt-4 space-y-6">
                                @foreach($progressData as $course)
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span>{{ $course['course'] }}</span>
                                            <span class="text-sm">{{ $course['progress'] }}%</span>
                                        </div>
                                        <div class="w-full h-3 rounded-full bg-base-200">
                                            <div
                                                class="h-3 rounded-full {{ $course['status'] === 'completed' ? 'bg-success' : 'bg-primary' }}"
                                                style="width: {{ $course['progress'] }}%"
                                            ></div>
                                        </div>
                                        <div class="mt-1 text-xs text-right">
                                            {{ $course['status'] === 'completed' ? 'Completed' : 'In Progress' }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Overall Performance Card -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Overall Performance</h2>

                            <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                                <div class="flex flex-col items-center">
                                    <div class="radial-progress text-primary" style="--value:{{ $this->studentStats['attendance_rate'] }}; --size:8rem; --thickness: 0.8rem;">
                                        <span class="text-2xl">{{ $this->studentStats['attendance_rate'] }}%</span>
                                    </div>
                                    <span class="mt-2 font-medium">Attendance Rate</span>
                                </div>

                                <div class="flex flex-col items-center">
                                    <div class="radial-progress text-secondary" style="--value:{{ $this->studentStats['avg_performance'] * 20 }}; --size:8rem; --thickness: 0.8rem;">
                                        <span class="text-2xl">{{ $this->studentStats['avg_performance'] }}/5</span>
                                    </div>
                                    <span class="mt-2 font-medium">Performance Score</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Details Tab -->
            <div class="{{ $activeTab === 'details' ? '' : 'hidden' }}">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-6 text-xl font-bold">Student Details</h2>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <h3 class="mb-4 text-lg font-medium">Personal Information</h3>

                                <div class="space-y-4">
                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Full Name</div>
                                        <div class="text-lg">{{ $student['name'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Email Address</div>
                                        <div>{{ $student['email'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Age</div>
                                        <div>{{ $student['age'] }} years</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Grade</div>
                                        <div>{{ $student['grade'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">School</div>
                                        <div>{{ $student['school'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Learning Style</div>
                                        <div>{{ $student['learning_style'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Interests</div>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($student['interests'] as $interest)
                                                <div class="badge badge-outline">{{ $interest }}</div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="mb-4 text-lg font-medium">Parent/Guardian Information</h3>

                                <div class="space-y-4">
                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Parent/Guardian Name</div>
                                        <div class="text-lg">{{ $student['parent_name'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Email Address</div>
                                        <div>{{ $student['parent_email'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Phone Number</div>
                                        <div>{{ $student['parent_phone'] }}</div>
                                    </div>
                                </div>

                                <h3 class="mt-8 mb-4 text-lg font-medium">Enrollment Information</h3>

                                <div class="space-y-4">
                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Enrolled Since</div>
                                        <div>{{ $this->formatDate($student['enrolled_at']) }}</div>
                                        <div class="text-sm text-base-content/60">{{ $this->getRelativeTime($student['enrolled_at']) }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Status</div>
                                        <div class="badge {{ $this->getStatusBadgeClass($student['status']) }}">
                                            {{ ucfirst($student['status']) }}
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Total Sessions</div>
                                        <div>{{ $this->studentStats['total_sessions'] }}</div>
                                    </div>

                                    <div>
                                        <div class="text-sm font-medium text-base-content/70">Active Courses</div>
                                        <div>{{ $this->studentStats['active_enrollments'] }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    @if($showMessageModal)
        <div class="modal modal-open">
            <div class="modal-box">
                <button wire:click="closeMessageModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

                <h3 class="text-lg font-bold">{{ $messageSubject }}</h3>

                <form wire:submit.prevent="sendMessage" class="mt-4">
                    <div class="space-y-4">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Subject</span>
                            </label>
                            <input
                                type="text"
                                wire:model="messageSubject"
                                class="input input-bordered @error('messageSubject') input-error @enderror"
                                placeholder="Message subject"
                            />
                            @error('messageSubject') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Message</span>
                            </label>
                            <textarea
                                wire:model="messageContent"
                                class="h-32 textarea textarea-bordered @error('messageContent') textarea-error @enderror"
                                placeholder="Type your message here..."
                            ></textarea>
                            @error('messageContent') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="modal-action">
                        <button
                            type="button"
                            wire:click="closeMessageModal"
                            class="btn btn-ghost"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <x-icon name="o-paper-airplane" class="w-4 h-4 mr-2" />
                            Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
