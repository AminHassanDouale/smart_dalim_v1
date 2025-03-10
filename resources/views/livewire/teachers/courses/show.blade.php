<?php

namespace App\Livewire\Teachers\Courses;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $course;
    public $teacher;
    public $teacherProfile;

    // Tab management
    public $activeTab = 'overview';

    // Modals
    public $showPublishModal = false;
    public $showSessionsModal = false;
    public $showDeleteModal = false;
    public $showEnrollmentsModal = false;

    // Session form
    public $sessionDate;
    public $sessionStartTime;
    public $sessionEndTime;

    // Filter
    public $studentSearch = '';

    public function mount($course)
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        // In a real app, we would fetch this from the database
        $this->course = $this->getMockCourse($course);

        // Set default session values
        $this->sessionDate = Carbon::now()->addDays(1)->format('Y-m-d');
        $this->sessionStartTime = '09:00';
        $this->sessionEndTime = '11:00';

        // Set active tab from query parameter if available
        $this->activeTab = request()->has('tab') ? request()->get('tab') : 'overview';
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    // Publish/unpublish course
    public function toggleCourseStatus()
    {
        // In a real app, this would update the database
        $this->course['status'] = $this->course['status'] === 'active' ? 'draft' : 'active';

        $statusText = $this->course['status'] === 'active' ? 'published' : 'unpublished';

        $this->toast(
            type: 'success',
            title: 'Course ' . $statusText,
            description: 'The course has been ' . $statusText . ' successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->showPublishModal = false;
    }

    // Schedule new session
    public function scheduleSession()
    {
        // Basic validation
        $this->validate([
            'sessionDate' => 'required|date|after_or_equal:today',
            'sessionStartTime' => 'required',
            'sessionEndTime' => 'required|after:sessionStartTime'
        ]);

        // In a real app, this would create a new session in the database

        $this->toast(
            type: 'success',
            title: 'Session scheduled',
            description: 'New session has been scheduled successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->showSessionsModal = false;
    }

    // Delete course
    public function deleteCourse()
    {
        // In a real app, this would delete the course from the database

        $this->toast(
            type: 'success',
            title: 'Course deleted',
            description: 'The course has been deleted successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->showDeleteModal = false;

        // Redirect to courses index
        return redirect()->route('teachers.courses');
    }

    // Format date
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // Get status badge class
    public function getStatusBadgeClass($status)
    {
        switch ($status) {
            case 'active':
                return 'badge-success';
            case 'draft':
                return 'badge-warning';
            case 'inactive':
                return 'badge-error';
            default:
                return 'badge-info';
        }
    }

    // Get students for this course
    public function getStudentsProperty()
    {
        $students = collect($this->course['mock_students']);

        // Apply search filter
        if ($this->studentSearch) {
            $search = strtolower($this->studentSearch);
            $students = $students->filter(function($student) use ($search) {
                return str_contains(strtolower($student['name']), $search);
            });
        }

        return $students->values()->all();
    }

    // Get upcoming sessions for this course
    public function getUpcomingSessionsProperty()
    {
        // In a real app, this would be fetched from the database
        return collect($this->course['mock_sessions'])
            ->filter(function($session) {
                return Carbon::parse($session['date'])->isFuture();
            })
            ->sortBy('date')
            ->values()
            ->all();
    }

    // Get past sessions for this course
    public function getPastSessionsProperty()
    {
        // In a real app, this would be fetched from the database
        return collect($this->course['mock_sessions'])
            ->filter(function($session) {
                return Carbon::parse($session['date'])->isPast();
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    // Get course completion percentage
    public function getCompletionPercentageProperty()
    {
        $startDate = Carbon::parse($this->course['start_date']);
        $endDate = Carbon::parse($this->course['end_date']);
        $now = Carbon::now();

        if ($now->lt($startDate)) {
            return 0;
        }

        if ($now->gt($endDate)) {
            return 100;
        }

        $totalDays = $startDate->diffInDays($endDate);
        $passedDays = $startDate->diffInDays($now);

        return round(($passedDays / $totalDays) * 100);
    }

    // Mock course data (would be fetched from database in a real app)
    private function getMockCourse($id)
    {
        return [
            'id' => $id,
            'name' => 'Advanced Laravel Development',
            'slug' => 'advanced-laravel-development',
            'description' => 'Master advanced Laravel concepts including Middleware, Service Containers, and more. This comprehensive course dives deep into Laravel\'s architecture and advanced features to help you build robust, scalable applications.',
            'level' => 'advanced',
            'subject_id' => 1,
            'subject_name' => 'Laravel Development',
            'teacher_profile_id' => $this->teacherProfile->id ?? 1,
            'price' => 299.99,
            'status' => 'active',
            'students_count' => 12,
            'max_students' => 20,
            'duration' => 8,
            'start_date' => Carbon::now()->subWeeks(2)->format('Y-m-d'),
            'end_date' => Carbon::now()->addWeeks(6)->format('Y-m-d'),
            'created_at' => Carbon::now()->subDays(30)->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->subDays(5)->format('Y-m-d H:i:s'),
            'cover_image' => null,
            'completed_percentage' => 25,
            'curriculum' => [
                [
                    'title' => 'Module 1: Advanced Routing',
                    'description' => 'Learn advanced routing techniques including route model binding, route caching, and route groups.',
                    'completed' => true
                ],
                [
                    'title' => 'Module 2: Service Containers and IoC',
                    'description' => 'Deep dive into dependency injection, service providers, and the inversion of control pattern.',
                    'completed' => true
                ],
                [
                    'title' => 'Module 3: Custom Middleware',
                    'description' => 'Create and implement custom middleware for request filtering, authentication, and more.',
                    'completed' => false
                ],
                [
                    'title' => 'Module 4: Advanced Eloquent',
                    'description' => 'Master advanced Eloquent features like polymorphic relationships, query scopes, and custom casts.',
                    'completed' => false
                ],
                [
                    'title' => 'Module 5: Final Project',
                    'description' => 'Apply all learned concepts to build a complete application with advanced Laravel features.',
                    'completed' => false
                ]
            ],
            'learning_outcomes' => [
                'Build complex Laravel applications',
                'Implement custom service providers',
                'Optimize database queries',
                'Create reusable packages',
                'Deploy Laravel applications to production environments',
                'Implement advanced authentication systems'
            ],
            'prerequisites' => [
                'Basic Laravel knowledge',
                'PHP proficiency',
                'Understanding of MVC architecture',
                'Familiarity with Composer and package management'
            ],
            'mock_students' => [
                [
                    'id' => 1,
                    'name' => 'Alex Johnson',
                    'email' => 'alex.johnson@example.com',
                    'enrolled_date' => Carbon::now()->subDays(25)->format('Y-m-d'),
                    'progress' => 30,
                    'avatar' => null
                ],
                [
                    'id' => 2,
                    'name' => 'Emma Smith',
                    'email' => 'emma.smith@example.com',
                    'enrolled_date' => Carbon::now()->subDays(22)->format('Y-m-d'),
                    'progress' => 35,
                    'avatar' => null
                ],
                [
                    'id' => 3,
                    'name' => 'Michael Brown',
                    'email' => 'michael.brown@example.com',
                    'enrolled_date' => Carbon::now()->subDays(20)->format('Y-m-d'),
                    'progress' => 25,
                    'avatar' => null
                ],
                [
                    'id' => 4,
                    'name' => 'Sophia Garcia',
                    'email' => 'sophia.garcia@example.com',
                    'enrolled_date' => Carbon::now()->subDays(18)->format('Y-m-d'),
                    'progress' => 40,
                    'avatar' => null
                ],
                [
                    'id' => 5,
                    'name' => 'William Martinez',
                    'email' => 'william.martinez@example.com',
                    'enrolled_date' => Carbon::now()->subDays(15)->format('Y-m-d'),
                    'progress' => 20,
                    'avatar' => null
                ],
                [
                    'id' => 6,
                    'name' => 'Olivia Wilson',
                    'email' => 'olivia.wilson@example.com',
                    'enrolled_date' => Carbon::now()->subDays(12)->format('Y-m-d'),
                    'progress' => 15,
                    'avatar' => null
                ],
                [
                    'id' => 7,
                    'name' => 'James Anderson',
                    'email' => 'james.anderson@example.com',
                    'enrolled_date' => Carbon::now()->subDays(10)->format('Y-m-d'),
                    'progress' => 10,
                    'avatar' => null
                ],
                [
                    'id' => 8,
                    'name' => 'Charlotte Thomas',
                    'email' => 'charlotte.thomas@example.com',
                    'enrolled_date' => Carbon::now()->subDays(8)->format('Y-m-d'),
                    'progress' => 5,
                    'avatar' => null
                ]
            ],
            'mock_sessions' => [
                [
                    'id' => 1,
                    'title' => 'Introduction to Advanced Laravel Concepts',
                    'date' => Carbon::now()->subDays(10)->format('Y-m-d'),
                    'start_time' => '10:00:00',
                    'end_time' => '12:00:00',
                    'status' => 'completed',
                    'attendance_count' => 10,
                    'recording_url' => 'https://example.com/recording/1'
                ],
                [
                    'id' => 2,
                    'title' => 'Deep Dive into Service Containers',
                    'date' => Carbon::now()->subDays(3)->format('Y-m-d'),
                    'start_time' => '14:00:00',
                    'end_time' => '16:00:00',
                    'status' => 'completed',
                    'attendance_count' => 9,
                    'recording_url' => 'https://example.com/recording/2'
                ],
                [
                    'id' => 3,
                    'title' => 'Custom Middleware Workshop',
                    'date' => Carbon::now()->addDays(4)->format('Y-m-d'),
                    'start_time' => '10:00:00',
                    'end_time' => '12:00:00',
                    'status' => 'scheduled',
                    'attendance_count' => null,
                    'recording_url' => null
                ],
                [
                    'id' => 4,
                    'title' => 'Advanced Eloquent Relationships',
                    'date' => Carbon::now()->addDays(11)->format('Y-m-d'),
                    'start_time' => '14:00:00',
                    'end_time' => '16:00:00',
                    'status' => 'scheduled',
                    'attendance_count' => null,
                    'recording_url' => null
                ],
                [
                    'id' => 5,
                    'title' => 'Final Project Preparation',
                    'date' => Carbon::now()->addDays(18)->format('Y-m-d'),
                    'start_time' => '10:00:00',
                    'end_time' => '12:00:00',
                    'status' => 'scheduled',
                    'attendance_count' => null,
                    'recording_url' => null
                ]
            ]
        ];
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
        <!-- Header Section with Course Cover Image (if available) -->
        <div class="relative mb-8 overflow-hidden rounded-xl bg-gradient-to-r from-primary/80 to-secondary/80">
            <div class="absolute inset-0 opacity-20 bg-pattern-grid"></div>

            <div class="relative z-10 flex flex-col items-start justify-between gap-6 p-8 md:flex-row md:items-center">
                <div class="max-w-3xl">
                    <div class="flex items-center gap-2 mb-2">
                        <a href="{{ route('teachers.courses') }}" class="text-white/80 hover:text-white">
                            <x-icon name="o-arrow-left" class="w-5 h-5" />
                        </a>
                        <span class="text-white/80">{{ $course['subject_name'] }}</span>
                    </div>

                    <h1 class="text-3xl font-bold text-white md:text-4xl">{{ $course['name'] }}</h1>

                    <div class="flex flex-wrap items-center gap-3 mt-3">
                        <div class="badge badge-lg {{ $this->getStatusBadgeClass($course['status']) }}">
                            {{ ucfirst($course['status']) }}
                        </div>

                        <div class="text-white badge badge-lg bg-white/20">
                            {{ ucfirst($course['level']) }} Level
                        </div>

                        <div class="text-white badge badge-lg bg-white/20">
                            {{ $course['duration'] }} Weeks
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if($course['status'] === 'active')
                        <button
                            wire:click="$set('showPublishModal', true)"
                            class="text-white border-white btn btn-outline hover:bg-white hover:text-primary"
                        >
                            <x-icon name="o-eye-slash" class="w-4 h-4 mr-2" />
                            Unpublish
                        </button>
                    @else
                        <button
                            wire:click="$set('showPublishModal', true)"
                            class="text-white border-white btn btn-outline hover:bg-white hover:text-primary"
                        >
                            <x-icon name="o-eye" class="w-4 h-4 mr-2" />
                            Publish
                        </button>
                    @endif

                    <a
                        href="{{ route('teachers.courses.edit', $course['id']) }}"
                        class="text-white border-white btn btn-outline hover:bg-white hover:text-primary"
                    >
                        <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                        Edit
                    </a>

                    <button
                        wire:click="$set('showDeleteModal', true)"
                        class="text-white border-white btn btn-outline hover:bg-white hover:text-error"
                    >
                        <x-icon name="o-trash" class="w-4 h-4 mr-2" />
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Course Statistics Cards -->
        <div class="grid grid-cols-1 gap-6 mb-8 sm:grid-cols-2 md:grid-cols-4">
            <div class="shadow-lg stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <x-icon name="o-users" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Enrollment</div>
                    <div class="stat-value text-primary">{{ $course['students_count'] }}/{{ $course['max_students'] }}</div>
                    <div class="stat-desc">{{ round(($course['students_count'] / $course['max_students']) * 100) }}% of capacity</div>
                </div>
            </div>

            <div class="shadow-lg stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <x-icon name="o-academic-cap" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Modules</div>
                    <div class="stat-value text-secondary">{{ count($course['curriculum']) }}</div>
                    <div class="stat-desc">
                        {{ count(array_filter($course['curriculum'], fn($m) => $m['completed'])) }} completed
                    </div>
                </div>
            </div>

            <div class="shadow-lg stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-info">
                        <x-icon name="o-video-camera" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Sessions</div>
                    <div class="stat-value text-info">{{ count($course['mock_sessions']) }}</div>
                    <div class="stat-desc">{{ count($this->upcomingSessions) }} upcoming</div>
                </div>
            </div>

            <div class="shadow-lg stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-success">
                        <div class="text-success radial-progress" style="--value:{{ $this->completionPercentage }};">
                            {{ $this->completionPercentage }}%
                        </div>
                    </div>
                    <div class="stat-title">Completion</div>
                    <div class="stat-value text-success">{{ $this->completionPercentage }}%</div>
                    <div class="stat-desc">{{ $this->formatDate($course['start_date']) }} - {{ $this->formatDate($course['end_date']) }}</div>
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
                wire:click.prevent="setActiveTab('curriculum')"
                class="tab {{ $activeTab === 'curriculum' ? 'tab-active' : '' }}"
            >
                Curriculum
            </a>
            <a
                wire:click.prevent="setActiveTab('students')"
                class="tab {{ $activeTab === 'students' ? 'tab-active' : '' }}"
            >
                Students ({{ $course['students_count'] }})
            </a>
            <a
                wire:click.prevent="setActiveTab('sessions')"
                class="tab {{ $activeTab === 'sessions' ? 'tab-active' : '' }}"
            >
                Sessions ({{ count($course['mock_sessions']) }})
            </a>
        </div>

        <!-- Tab Content -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <!-- Overview Tab -->
                <div class="{{ $activeTab === 'overview' ? '' : 'hidden' }}">
                    <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                        <!-- Course Description -->
                        <div class="md:col-span-2">
                            <h2 class="mb-4 text-2xl font-bold">Course Description</h2>
                            <div class="prose max-w-none">
                                <p>{{ $course['description'] }}</p>
                            </div>

                            <!-- Course Timeline -->
                            <h3 class="mt-8 mb-4 text-xl font-bold">Course Timeline</h3>
                            <div class="flex items-center justify-between mb-2">
                                <span>Progress</span>
                                <span>{{ $this->completionPercentage }}%</span>
                            </div>
                            <div class="w-full h-4 mb-4 rounded-full bg-base-200">
                                <div
                                    class="h-4 rounded-full bg-primary"
                                    style="width: {{ $this->completionPercentage }}%"
                                ></div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h4 class="text-sm font-medium text-base-content/70">Start Date</h4>
                                    <p class="text-lg">{{ $this->formatDate($course['start_date']) }}</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-base-content/70">End Date</h4>
                                    <p class="text-lg">{{ $this->formatDate($course['end_date']) }}</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-base-content/70">Duration</h4>
                                    <p class="text-lg">{{ $course['duration'] }} weeks</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-base-content/70">Price</h4>
                                    <p class="text-lg">${{ number_format($course['price'], 2) }}</p>
                                </div>
                            </div>

                            <!-- Next Session -->
                            @if(count($this->upcomingSessions) > 0)
                                <div class="p-4 mt-8 rounded-lg bg-primary/10">
                                    <h3 class="flex items-center text-lg font-semibold">
                                        <x-icon name="o-calendar" class="w-5 h-5 mr-2 text-primary" />
                                        Next Session
                                    </h3>
                                    <div class="mt-2">
                                        <p class="font-medium">{{ $this->upcomingSessions[0]['title'] }}</p>
                                        <p class="text-sm text-base-content/70">
                                            {{ $this->formatDate($this->upcomingSessions[0]['date']) }} &bull;
                                            {{ \Carbon\Carbon::parse($this->upcomingSessions[0]['start_time'])->format('h:i A') }} -
                                            {{ \Carbon\Carbon::parse($this->upcomingSessions[0]['end_time'])->format('h:i A') }}
                                        </p>
                                    </div>
                                    <div class="flex justify-end mt-2">
                                        <button onclick="window.location.href='#sessions-tab'" wire:click="setActiveTab('sessions')" class="btn btn-sm btn-primary">View All Sessions</button>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Sidebar -->
                        <div>
                            <!-- Quick Stats -->
                            <div class="p-6 mb-6 rounded-lg bg-base-200">
                                <h3 class="mb-4 text-lg font-bold">Quick Stats</h3>
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-base-content/70">Enrollment</span>
                                        <span class="font-semibold">{{ $course['students_count'] }}/{{ $course['max_students'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-base-content/70">Modules</span>
                                        <span class="font-semibold">{{ count($course['curriculum']) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-base-content/70">Sessions</span>
                                        <span class="font-semibold">{{ count($course['mock_sessions']) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-base-content/70">Created</span>
                                        <span class="font-semibold">{{ $this->formatDate($course['created_at']) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-base-content/70">Status</span>
                                        <div class="badge {{ $this->getStatusBadgeClass($course['status']) }}">
                                            {{ ucfirst($course['status']) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Learning Outcomes -->
                            <div class="p-6 mb-6 rounded-lg bg-base-200">
                                <h3 class="mb-4 text-lg font-bold">Learning Outcomes</h3>
                                <ul class="space-y-2">
                                    @foreach($course['learning_outcomes'] as $outcome)
                                        <li class="flex items-start gap-2">
                                            <x-icon name="o-check-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-success" />
                                            <span>{{ $outcome }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <!-- Prerequisites -->
                            <div class="p-6 rounded-lg bg-base-200">
                                <h3 class="mb-4 text-lg font-bold">Prerequisites</h3>
                                <ul class="space-y-2">
                                    @foreach($course['prerequisites'] as $prerequisite)
                                        <li class="flex items-start gap-2">
                                            <x-icon name="o-information-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-info" />
                                            <span>{{ $prerequisite }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Curriculum Tab -->
                <div class="{{ $activeTab === 'curriculum' ? '' : 'hidden' }}">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold">Course Curriculum</h2>
                        <a
                            href="{{ route('teachers.courses.edit', $course['id']) }}?tab=curriculum"
                            class="btn btn-outline"
                        >
                            <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                            Edit Curriculum
                        </a>
                    </div>

                    <div>
                        <div class="space-y-6">
                            @foreach($course['curriculum'] as $index => $module)
                                <div class="p-6 border rounded-lg {{ $module['completed'] ? 'border-success/30 bg-success/5' : 'border-base-300' }}">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                @if($module['completed'])
                                                    <div class="p-1 rounded-full bg-success text-success-content">
                                                        <x-icon name="o-check" class="w-4 h-4" />
                                                    </div>
                                                @else
                                                    <div class="p-1 rounded-full bg-base-300">
                                                        <span class="flex items-center justify-center w-4 h-4 text-xs">{{ $index + 1 }}</span>
                                                    </div>
                                                @endif
                                                <h3 class="text-lg font-semibold">{{ $module['title'] }}</h3>
                                            </div>
                                            <p class="mt-2 text-base-content/70">{{ $module['description'] }}</p>
                                        </div>
                                        <div class="badge {{ $module['completed'] ? 'badge-success' : 'badge-ghost' }}">
                                            {{ $module['completed'] ? 'Completed' : 'Upcoming' }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Students Tab -->
                    <div class="{{ $activeTab === 'students' ? '' : 'hidden' }}" id="students-tab">
                        <div class="flex flex-col items-start justify-between gap-4 mb-6 sm:flex-row sm:items-center">
                            <h2 class="text-2xl font-bold">Enrolled Students</h2>

                            <div class="flex flex-col w-full gap-4 sm:flex-row sm:w-auto">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                                    </div>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="studentSearch"
                                        placeholder="Search students..."
                                        class="w-full pl-10 sm:w-64 input input-bordered"
                                    >
                                </div>

                                <button
                                    wire:click="$set('showEnrollmentsModal', true)"
                                    class="btn btn-primary"
                                >
                                    <x-icon name="o-user-plus" class="w-4 h-4 mr-2" />
                                    Manage Enrollments
                                </button>
                            </div>
                        </div>

                        @if(count($this->students) > 0)
                            <div class="overflow-x-auto">
                                <table class="table table-zebra">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Enrollment Date</th>
                                            <th>Progress</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->students as $student)
                                            <tr>
                                                <td>
                                                    <div class="flex items-center gap-3">
                                                        <div class="avatar placeholder">
                                                            <div class="w-10 h-10 rounded-full bg-neutral-focus text-neutral-content">
                                                                <span>{{ substr($student['name'], 0, 1) }}</span>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="font-bold">{{ $student['name'] }}</div>
                                                            <div class="text-sm opacity-70">{{ $student['email'] }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>{{ $this->formatDate($student['enrolled_date']) }}</td>
                                                <td>
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-full h-2 rounded-full bg-base-300 max-w-32">
                                                            <div
                                                                class="h-2 rounded-full bg-primary"
                                                                style="width: {{ $student['progress'] }}%"
                                                            ></div>
                                                        </div>
                                                        <span>{{ $student['progress'] }}%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex gap-2">
                                                        <a
                                                            href="{{ route('teachers.students.show', $student['id']) }}"
                                                            class="btn btn-sm btn-ghost"
                                                        >
                                                            <x-icon name="o-eye" class="w-4 h-4" />
                                                        </a>
                                                        <button class="btn btn-sm btn-ghost">
                                                            <x-icon name="o-envelope" class="w-4 h-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-6 text-center bg-base-200 rounded-xl">
                                <x-icon name="o-users" class="w-12 h-12 mx-auto mb-4 text-base-content/30" />
                                <h3 class="text-lg font-semibold">No students found</h3>
                                <p class="mt-1 text-base-content/70">
                                    @if($studentSearch)
                                        No students match your search query
                                    @else
                                        No students are enrolled in this course yet
                                    @endif
                                </p>
                                @if($studentSearch)
                                    <button
                                        wire:click="$set('studentSearch', '')"
                                        class="mt-4 btn btn-outline"
                                    >
                                        Clear Search
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Sessions Tab -->
                    <div class="{{ $activeTab === 'sessions' ? '' : 'hidden' }}" id="sessions-tab">
                        <div class="flex flex-col items-start justify-between gap-4 mb-6 sm:flex-row sm:items-center">
                            <h2 class="text-2xl font-bold">Course Sessions</h2>

                            <button
                                wire:click="$set('showSessionsModal', true)"
                                class="btn btn-primary"
                            >
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Schedule New Session
                            </button>
                        </div>

                        <!-- Upcoming Sessions -->
                        @if(count($this->upcomingSessions) > 0)
                            <h3 class="mb-4 text-xl font-bold">Upcoming Sessions</h3>
                            <div class="mb-8 space-y-4">
                                @foreach($this->upcomingSessions as $session)
                                    <div class="p-6 border rounded-lg shadow-sm border-primary/20 bg-primary/5">
                                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <h4 class="text-lg font-semibold">{{ $session['title'] }}</h4>
                                                <div class="flex flex-wrap items-center gap-3 mt-2">
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
                                                    <div class="badge badge-info">{{ ucfirst($session['status']) }}</div>
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <a
                                                    href="{{ route('teachers.sessions.show', $session['id']) }}"
                                                    class="btn btn-sm btn-outline"
                                                >
                                                    View Details
                                                </a>
                                                <button class="btn btn-sm btn-primary">
                                                    <x-icon name="o-video-camera" class="w-4 h-4 mr-1" />
                                                    Start Session
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Past Sessions -->
                        @if(count($this->pastSessions) > 0)
                            <h3 class="mb-4 text-xl font-bold">Past Sessions</h3>
                            <div class="space-y-4">
                                @foreach($this->pastSessions as $session)
                                    <div class="p-6 border rounded-lg shadow-sm border-base-300">
                                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <h4 class="text-lg font-semibold">{{ $session['title'] }}</h4>
                                                <div class="flex flex-wrap items-center gap-3 mt-2">
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
                                                    <div class="badge badge-success">{{ ucfirst($session['status']) }}</div>
                                                </div>
                                                <div class="mt-2 text-sm text-base-content/70">
                                                    <span>{{ $session['attendance_count'] }} attendees</span>
                                                    @if($session['recording_url'])
                                                        <span class="mx-2">•</span>
                                                        <a href="{{ $session['recording_url'] }}" class="link link-primary">Recording available</a>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex gap-2">
                                                <a
                                                    href="{{ route('teachers.sessions.show', $session['id']) }}"
                                                    class="btn btn-sm btn-outline"
                                                >
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if(count($this->upcomingSessions) === 0 && count($this->pastSessions) === 0)
                            <div class="p-6 text-center bg-base-200 rounded-xl">
                                <x-icon name="o-calendar" class="w-12 h-12 mx-auto mb-4 text-base-content/30" />
                                <h3 class="text-lg font-semibold">No sessions scheduled</h3>
                                <p class="mt-1 text-base-content/70">
                                    Schedule your first session for this course
                                </p>
                                <button
                                    wire:click="$set('showSessionsModal', true)"
                                    class="mt-4 btn btn-primary"
                                >
                                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                    Schedule Session
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Publish/Unpublish Modal -->
        <div class="modal {{ $showPublishModal ? 'modal-open' : '' }}">
            <div class="modal-box">
                <h3 class="text-lg font-bold">{{ $course['status'] === 'active' ? 'Unpublish' : 'Publish' }} Course</h3>
                <p class="py-4">
                    Are you sure you want to {{ $course['status'] === 'active' ? 'unpublish' : 'publish' }} this course?
                    @if($course['status'] !== 'active')
                        Publishing will make it visible to students.
                    @else
                        Unpublishing will hide it from students.
                    @endif
                </p>
                <div class="modal-action">
                    <button
                        wire:click="$set('showPublishModal', false)"
                        class="btn btn-outline"
                    >
                        Cancel
                    </button>
                    <button
                        wire:click="toggleCourseStatus"
                        class="btn {{ $course['status'] === 'active' ? 'btn-warning' : 'btn-success' }}"
                    >
                        {{ $course['status'] === 'active' ? 'Unpublish' : 'Publish' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Course Modal -->
        <div class="modal {{ $showDeleteModal ? 'modal-open' : '' }}">
            <div class="modal-box">
                <h3 class="text-lg font-bold">Delete Course</h3>
                <p class="py-4">
                    Are you sure you want to delete this course? This action cannot be undone and will remove all associated data.
                </p>
                <div class="modal-action">
                    <button
                        wire:click="$set('showDeleteModal', false)"
                        class="btn btn-outline"
                    >
                        Cancel
                    </button>
                    <button
                        wire:click="deleteCourse"
                        class="btn btn-error"
                    >
                        Delete Course
                    </button>
                </div>
            </div>
        </div>

        <!-- Schedule Session Modal -->
        <div class="modal {{ $showSessionsModal ? 'modal-open' : '' }}">
            <div class="modal-box">
                <h3 class="text-lg font-bold">Schedule New Session</h3>
                <form wire:submit.prevent="scheduleSession">
                    <div class="p-4 space-y-4">
                        <!-- Session Date -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Date</span>
                            </label>
                            <input
                                type="date"
                                wire:model="sessionDate"
                                class="input input-bordered"
                                min="{{ date('Y-m-d') }}"
                            />
                            @error('sessionDate') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Session Time -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Start Time</span>
                                </label>
                                <input
                                    type="time"
                                    wire:model="sessionStartTime"
                                    class="input input-bordered"
                                />
                                @error('sessionStartTime') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">End Time</span>
                                </label>
                                <input
                                    type="time"
                                    wire:model="sessionEndTime"
                                    class="input input-bordered"
                                />
                                @error('sessionEndTime') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="modal-action">
                        <button
                            type="button"
                            wire:click="$set('showSessionsModal', false)"
                            class="btn btn-outline"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
