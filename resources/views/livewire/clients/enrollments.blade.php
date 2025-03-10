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
    public $progressFilter = '';
    public $searchQuery = '';
    public $sortBy = 'last_accessed';

    // Tab state
    public $activeTab = 'active';

    // Modal states
    public $showCertificateModal = false;
    public $selectedEnrollment = null;

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'progressFilter' => ['except' => ''],
        'searchQuery' => ['except' => ''],
        'sortBy' => ['except' => 'last_accessed'],
        'activeTab' => ['except' => 'active'],
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

    public function updatingProgressFilter()
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

    public function viewCertificate($enrollmentId)
    {
        // Find the enrollment in our mock data
        $this->selectedEnrollment = collect($this->getAllEnrollments())->firstWhere('id', $enrollmentId);
        $this->showCertificateModal = true;
    }

    public function closeCertificateModal()
    {
        $this->showCertificateModal = false;
        $this->selectedEnrollment = null;
    }

    public function continueCourse($enrollmentId)
    {
        // In a real app, this would redirect to the course learning page
        // Just showing a toast for demo
        $enrollment = collect($this->getAllEnrollments())->firstWhere('id', $enrollmentId);

        $this->toast(
            type: 'info',
            title: 'Continuing course...',
            description: 'Redirecting to ' . $enrollment['course_title'],
            position: 'toast-bottom toast-end',
            icon: 'o-academic-cap',
            css: 'alert-info',
            timeout: 2000
        );
    }

    public function downloadCertificate($enrollmentId)
    {
        // In a real app, this would generate and download a certificate
        $this->toast(
            type: 'success',
            title: 'Certificate downloaded',
            description: 'Your certificate has been downloaded successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-document-arrow-down',
            css: 'alert-success',
            timeout: 3000
        );
    }

    // Get filtered enrollments
    public function getEnrollmentsProperty()
    {
        $enrollments = collect($this->getAllEnrollments());

        // First, filter by tab
        if ($this->activeTab === 'active') {
            $enrollments = $enrollments->filter(function($enrollment) {
                return $enrollment['status'] === 'in_progress' || $enrollment['status'] === 'paused';
            });
        } elseif ($this->activeTab === 'completed') {
            $enrollments = $enrollments->filter(function($enrollment) {
                return $enrollment['status'] === 'completed';
            });
        } elseif ($this->activeTab === 'archived') {
            $enrollments = $enrollments->filter(function($enrollment) {
                return $enrollment['status'] === 'archived';
            });
        }

        // Apply additional filters
        if ($this->statusFilter) {
            $enrollments = $enrollments->filter(function($enrollment) {
                return $enrollment['status'] === $this->statusFilter;
            });
        }

        if ($this->progressFilter) {
            $enrollments = $enrollments->filter(function($enrollment) {
                if ($this->progressFilter === 'not_started') {
                    return $enrollment['progress'] === 0;
                } elseif ($this->progressFilter === 'in_progress') {
                    return $enrollment['progress'] > 0 && $enrollment['progress'] < 100;
                } elseif ($this->progressFilter === 'completed') {
                    return $enrollment['progress'] === 100;
                }
                return true;
            });
        }

        if ($this->searchQuery) {
            $query = strtolower($this->searchQuery);
            $enrollments = $enrollments->filter(function($enrollment) use ($query) {
                return str_contains(strtolower($enrollment['course_title']), $query) ||
                       str_contains(strtolower($enrollment['instructor']), $query);
            });
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'title':
                $enrollments = $enrollments->sortBy('course_title');
                break;
            case 'progress':
                $enrollments = $enrollments->sortByDesc('progress');
                break;
            case 'enrollment_date':
                $enrollments = $enrollments->sortByDesc('enrollment_date');
                break;
            default: // last_accessed
                $enrollments = $enrollments->sortByDesc('last_accessed');
                break;
        }

        return $enrollments->values()->all();
    }

    // Get stats for the dashboard
    public function getStatsProperty()
    {
        $allEnrollments = collect($this->getAllEnrollments());

        return [
            'total' => $allEnrollments->count(),
            'active' => $allEnrollments->where('status', 'in_progress')->count(),
            'completed' => $allEnrollments->where('status', 'completed')->count(),
            'average_progress' => $allEnrollments->avg('progress'),
            'certificates' => $allEnrollments->where('has_certificate', true)->count()
        ];
    }

    // Get top enrollments for featured section
    public function getTopEnrollmentsProperty()
    {
        return collect($this->getAllEnrollments())
            ->where('status', 'in_progress')
            ->sortByDesc('progress')
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

    // Mock data for enrollments (in a real app, you would fetch from database)
    private function getAllEnrollments()
    {
        return [
            [
                'id' => 1,
                'course_title' => 'Advanced Laravel Development',
                'course_slug' => 'advanced-laravel-development',
                'thumbnail' => 'course-laravel.jpg',
                'instructor' => 'Sarah Johnson',
                'progress' => 35,
                'status' => 'in_progress',
                'enrollment_date' => '2024-01-15',
                'last_accessed' => '2024-03-01 14:20:00',
                'expiry_date' => '2025-01-15',
                'certificate_date' => null,
                'has_certificate' => false,
                'current_lesson' => 'API Development with Laravel',
                'total_lessons' => 42,
                'completed_lessons' => 15,
                'category' => 'development',
                'level' => 'advanced'
            ],
            [
                'id' => 2,
                'course_title' => 'React and Redux Masterclass',
                'course_slug' => 'react-redux-masterclass',
                'thumbnail' => 'course-react.jpg',
                'instructor' => 'Michael Chen',
                'progress' => 68,
                'status' => 'in_progress',
                'enrollment_date' => '2023-11-10',
                'last_accessed' => '2024-02-28 09:15:00',
                'expiry_date' => '2024-11-10',
                'certificate_date' => null,
                'has_certificate' => false,
                'current_lesson' => 'Advanced Redux Middleware',
                'total_lessons' => 38,
                'completed_lessons' => 26,
                'category' => 'development',
                'level' => 'intermediate'
            ],
            [
                'id' => 3,
                'course_title' => 'UI/UX Design Fundamentals',
                'course_slug' => 'ui-ux-design-fundamentals',
                'thumbnail' => 'course-uiux.jpg',
                'instructor' => 'Emily Rodriguez',
                'progress' => 100,
                'status' => 'completed',
                'enrollment_date' => '2023-09-05',
                'last_accessed' => '2023-10-25 16:45:00',
                'expiry_date' => '2024-09-05',
                'certificate_date' => '2023-10-25',
                'has_certificate' => true,
                'current_lesson' => 'Course Completed',
                'total_lessons' => 28,
                'completed_lessons' => 28,
                'category' => 'design',
                'level' => 'beginner'
            ],
            [
                'id' => 4,
                'course_title' => 'Digital Marketing Strategy',
                'course_slug' => 'digital-marketing-strategy',
                'thumbnail' => 'course-marketing.jpg',
                'instructor' => 'David Wilson',
                'progress' => 45,
                'status' => 'paused',
                'enrollment_date' => '2023-12-12',
                'last_accessed' => '2024-01-15 11:30:00',
                'expiry_date' => '2024-12-12',
                'certificate_date' => null,
                'has_certificate' => false,
                'current_lesson' => 'Social Media Marketing Plan',
                'total_lessons' => 35,
                'completed_lessons' => 16,
                'category' => 'marketing',
                'level' => 'intermediate'
            ],
            [
                'id' => 5,
                'course_title' => 'Flutter App Development',
                'course_slug' => 'flutter-app-development',
                'thumbnail' => 'course-flutter.jpg',
                'instructor' => 'Alex Johnson',
                'progress' => 10,
                'status' => 'in_progress',
                'enrollment_date' => '2024-02-20',
                'last_accessed' => '2024-02-25 13:10:00',
                'expiry_date' => '2025-02-20',
                'certificate_date' => null,
                'has_certificate' => false,
                'current_lesson' => 'Flutter Widgets and Layouts',
                'total_lessons' => 46,
                'completed_lessons' => 5,
                'category' => 'mobile',
                'level' => 'intermediate'
            ],
            [
                'id' => 6,
                'course_title' => 'Data Science with Python',
                'course_slug' => 'data-science-python',
                'thumbnail' => 'course-datascience.jpg',
                'instructor' => 'Lisa Chen',
                'progress' => 100,
                'status' => 'completed',
                'enrollment_date' => '2023-08-10',
                'last_accessed' => '2023-11-30 10:20:00',
                'expiry_date' => '2024-08-10',
                'certificate_date' => '2023-11-30',
                'has_certificate' => true,
                'current_lesson' => 'Course Completed',
                'total_lessons' => 58,
                'completed_lessons' => 58,
                'category' => 'data',
                'level' => 'all-levels'
            ],
            [
                'id' => 7,
                'course_title' => 'Business Analytics Fundamentals',
                'course_slug' => 'business-analytics-fundamentals',
                'thumbnail' => 'course-business.jpg',
                'instructor' => 'Robert Taylor',
                'progress' => 100,
                'status' => 'archived',
                'enrollment_date' => '2023-05-05',
                'last_accessed' => '2023-07-15 09:45:00',
                'expiry_date' => '2024-05-05',
                'certificate_date' => '2023-07-15',
                'has_certificate' => true,
                'current_lesson' => 'Course Archived',
                'total_lessons' => 24,
                'completed_lessons' => 24,
                'category' => 'business',
                'level' => 'beginner'
            ],
            [
                'id' => 8,
                'course_title' => 'Graphic Design Masterclass',
                'course_slug' => 'graphic-design-masterclass',
                'thumbnail' => 'course-graphicdesign.jpg',
                'instructor' => 'Jessica Park',
                'progress' => 75,
                'status' => 'in_progress',
                'enrollment_date' => '2023-10-10',
                'last_accessed' => '2024-02-20 15:30:00',
                'expiry_date' => '2024-10-10',
                'certificate_date' => null,
                'has_certificate' => false,
                'current_lesson' => 'Advanced Typography Techniques',
                'total_lessons' => 40,
                'completed_lessons' => 30,
                'category' => 'design',
                'level' => 'all-levels'
            ],
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
                <h1 class="text-3xl font-bold">My Learning</h1>
                <p class="mt-1 text-base-content/70">Track your courses and progress</p>
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

        <!-- Learning Dashboard Stats -->
        <div class="p-4 mb-8 shadow-lg rounded-xl bg-base-100 sm:p-6">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['total'] }}</div>
                    <div class="text-xs opacity-70">Total Enrollments</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['active'] }}</div>
                    <div class="text-xs opacity-70">Active Courses</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['completed'] }}</div>
                    <div class="text-xs opacity-70">Completed</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ round($this->stats['average_progress']) }}%</div>
                    <div class="text-xs opacity-70">Avg. Progress</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['certificates'] }}</div>
                    <div class="text-xs opacity-70">Certificates</div>
                </div>
            </div>
        </div>

        <!-- Top Courses Section -->
        @if(count($this->topEnrollments) > 0)
            <div class="mb-8">
                <h2 class="mb-6 text-xl font-bold">Continue Learning</h2>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->topEnrollments as $enrollment)
                        <div class="h-full shadow-lg card bg-base-100">
                            <figure class="px-4 pt-4">
                                <img
                                    src="{{ asset('images/' . $enrollment['thumbnail']) }}"
                                    alt="{{ $enrollment['course_title'] }}"
                                    class="object-cover w-full rounded-xl h-44"
                                    onerror="this.src='https://placehold.co/600x400?text=Course+Image'"
                                />
                            </figure>
                            <div class="flex-grow card-body">
                                <h3 class="card-title">{{ $enrollment['course_title'] }}</h3>
                                <div class="flex items-center gap-2 mt-2">
                                    <div class="avatar placeholder">
                                        <div class="w-6 h-6 rounded-full bg-neutral-focus text-neutral-content">
                                            <span>{{ substr($enrollment['instructor'], 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <span class="text-sm">{{ $enrollment['instructor'] }}</span>
                                </div>

                                <div class="mt-3">
                                    <p class="mb-1 text-sm">
                                        <span class="font-medium">Current lesson:</span> {{ $enrollment['current_lesson'] }}
                                    </p>
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <span class="text-sm">Progress</span>
                                        <span class="text-sm font-medium">{{ $enrollment['progress'] }}%</span>
                                    </div>
                                    <div class="w-full h-2 mb-1 overflow-hidden rounded-full bg-base-300">
                                        <div class="h-full {{ $enrollment['progress'] > 75 ? 'bg-success' : ($enrollment['progress'] > 40 ? 'bg-info' : 'bg-primary') }}" style="width: {{ $enrollment['progress'] }}%"></div>
                                    </div>
                                    <p class="text-xs text-right text-base-content/70">
                                        {{ $enrollment['completed_lessons'] }}/{{ $enrollment['total_lessons'] }} lessons completed
                                    </p>
                                </div>

                                <div class="justify-center mt-4 card-actions">
                                    <button
                                        wire:click="continueCourse({{ $enrollment['id'] }})"
                                        class="w-full btn btn-primary"
                                    >
                                        Continue Learning
                                    </button>
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
                    wire:click="setActiveTab('active')"
                    class="tab {{ $activeTab === 'active' ? 'tab-active' : '' }}"
                >
                    Active Courses
                </a>
                <a
                    wire:click="setActiveTab('completed')"
                    class="tab {{ $activeTab === 'completed' ? 'tab-active' : '' }}"
                >
                    Completed
                </a>
                <a
                    wire:click="setActiveTab('archived')"
                    class="tab {{ $activeTab === 'archived' ? 'tab-active' : '' }}"
                >
                    Archived
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
                            placeholder="Search courses..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <select wire:model.live="statusFilter" class="w-full select select-bordered">
                        <option value="">All Statuses</option>
                        <option value="in_progress">In Progress</option>
                        <option value="paused">Paused</option>
                        <option value="completed">Completed</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <!-- Progress Filter -->
                <div>
                    <select wire:model.live="progressFilter" class="w-full select select-bordered">
                        <option value="">All Progress</option>
                        <option value="not_started">Not Started (0%)</option>
                        <option value="in_progress">In Progress (1-99%)</option>
                        <option value="completed">Completed (100%)</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <select wire:model.live="sortBy" class="w-full select select-bordered">
                        <option value="last_accessed">Recently Accessed</option>
                        <option value="enrollment_date">Enrollment Date</option>
                        <option value="progress">Progress</option>
                        <option value="title">Title (A-Z)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Enrollments List -->
        <div class="shadow-xl rounded-xl bg-base-100">
            @if(count($this->enrollments) > 0)
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 gap-6">
                        @foreach($this->enrollments as $enrollment)
                            <div class="overflow-hidden transition-all border rounded-lg shadow-sm hover:shadow-md border-base-200">
                                <div class="grid grid-cols-1 lg:grid-cols-4">
                                    <!-- Course Thumbnail & Basic Info -->
                                    <div class="lg:col-span-1">
                                        <div class="relative h-full">
                                            <img
                                                src="{{ asset('images/' . $enrollment['thumbnail']) }}"
                                                alt="{{ $enrollment['course_title'] }}"
                                                class="object-cover w-full h-full lg:h-44"
                                                onerror="this.src='https://placehold.co/600x400?text=Course+Image'"
                                            />
                                            <div class="absolute top-3 right-3">
                                                <div class="px-2 py-1 text-xs text-white rounded-md {{
                                                    $enrollment['status'] === 'in_progress' ? 'bg-info' :
                                                    ($enrollment['status'] === 'completed' ? 'bg-success' :
                                                    ($enrollment['status'] === 'paused' ? 'bg-warning' : 'bg-neutral'))
                                                }}">
                                                    {{ ucfirst(str_replace('_', ' ', $enrollment['status'])) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Course Details -->
                                    <div class="p-5 lg:col-span-3">
                                        <div class="flex flex-col h-full">
                                            <div class="mb-4">
                                                <h3 class="text-lg font-bold">{{ $enrollment['course_title'] }}</h3>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <div class="avatar placeholder">
                                                        <div class="w-5 h-5 rounded-full bg-neutral-focus text-neutral-content">
                                                            <span class="text-xs">{{ substr($enrollment['instructor'], 0, 1) }}</span>
                                                        </div>
                                                    </div>
                                                    <span class="text-sm">{{ $enrollment['instructor'] }}</span>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                                <div class="flex-1 space-y-2 md:col-span-2">
                                                    <!-- Progress Bar -->
                                                    <div>
                                                        <div class="flex items-center justify-between gap-2 mb-1">
                                                            <span class="text-sm">Progress</span>
                                                            <span class="text-sm font-medium">{{ $enrollment['progress'] }}%</span>
                                                        </div>
                                                        <div class="w-full h-2 mb-1 overflow-hidden rounded-full bg-base-300">
                                                            <div class="h-full {{ $enrollment['progress'] > 75 ? 'bg-success' : ($enrollment['progress'] > 40 ? 'bg-info' : 'bg-primary') }}" style="width: {{ $enrollment['progress'] }}%"></div>
                                                        </div>
                                                        <p class="text-xs text-base-content/70">
                                                            {{ $enrollment['completed_lessons'] }}/{{ $enrollment['total_lessons'] }} lessons completed
                                                        </p>
                                                    </div>

                                                    <!-- Current Lesson -->
                                                    <div>
                                                        <p class="text-sm">
                                                            <span class="font-medium">Current lesson:</span> {{ $enrollment['current_lesson'] }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="flex-1">
                                                    <div class="space-y-1">
                                                        <div class="flex justify-between">
                                                            <span class="text-xs opacity-70">Enrolled:</span>
                                                            <span class="text-xs">{{ $this->formatDate($enrollment['enrollment_date']) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-xs opacity-70">Last accessed:</span>
                                                            <span class="text-xs">{{ $this->getRelativeTime($enrollment['last_accessed']) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-xs opacity-70">Access until:</span>
                                                            <span class="text-xs">{{ $this->formatDate($enrollment['expiry_date']) }}</span>
                                                        </div>
                                                        @if($enrollment['certificate_date'])
                                                        <div class="flex justify-between">
                                                            <span class="text-xs opacity-70">Certificate:</span>
                                                            <span class="text-xs">{{ $this->formatDate($enrollment['certificate_date']) }}</span>
                                                        </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Fixed section with the syntax error -->
                                            <div class="flex flex-wrap gap-2 mt-4 md:justify-end">
                                                @if($enrollment['status'] === 'completed' && $enrollment['has_certificate'])
                                                    <button
                                                        wire:click="viewCertificate({{ $enrollment['id'] }})"
                                                        class="btn btn-outline btn-sm"
                                                    >
                                                        <x-icon name="o-academic-cap" class="w-4 h-4 mr-1" />
                                                        View Certificate
                                                    </button>
                                                @endif

                                                @if($enrollment['status'] !== 'archived')
                                                    <a
                                                        href="{{ route('clients.enrollments.show', $enrollment['id']) }}"
                                                        class="btn btn-outline btn-sm"
                                                    >
                                                        <x-icon name="o-document-text" class="w-4 h-4 mr-1" />
                                                        Details
                                                    </a>

                                                    @if($enrollment['status'] === 'in_progress' || $enrollment['status'] === 'paused')
                                                        <button
                                                            wire:click="continueCourse({{ $enrollment['id'] }})"
                                                            class="btn btn-primary btn-sm"
                                                        >
                                                            <x-icon name="o-play" class="w-4 h-4 mr-1" />
                                                            {{ $enrollment['progress'] > 0 ? 'Continue' : 'Start' }}
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
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
                        <x-icon name="o-book-open" class="w-16 h-16 mb-4 text-base-content/30" />
                        <h3 class="text-xl font-bold">No courses found</h3>
                        <p class="mt-2 text-base-content/70">
                            @if($searchQuery || $statusFilter || $progressFilter)
                                Try adjusting your search or filters
                            @else
                                You are not enrolled in any courses yet
                            @endif
                        </p>

                        @if($searchQuery || $statusFilter || $progressFilter)
                            <button
                                wire:click="$set('searchQuery', ''); $set('statusFilter', ''); $set('progressFilter', '');"
                                class="mt-4 btn btn-outline"
                            >
                                Clear Filters
                            </button>
                        @else
                            <a href="{{ route('clients.courses') }}" class="mt-4 btn btn-primary">
                                Browse Courses
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Certificate Modal -->
    <div class="modal {{ $showCertificateModal ? 'modal-open' : '' }}">
        <div class="max-w-3xl modal-box">
            <button wire:click="closeCertificateModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            @if($selectedEnrollment)
                <div class="relative p-8 text-center border-8 border-primary/20 bg-base-100">
                    <!-- Certificate Header -->
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold text-primary">Certificate of Completion</h3>
                        <p class="mt-2 text-base-content/70">This certifies that</p>
                    </div>

                    <!-- Student Name -->
                    <div class="mb-6">
                        <h2 class="text-3xl font-bold">{{ $user->name }}</h2>
                    </div>

                    <!-- Course Details -->
                    <div class="mb-6">
                        <p class="text-base-content/70">has successfully completed the course</p>
                        <h4 class="mt-2 text-xl font-bold">{{ $selectedEnrollment['course_title'] }}</h4>
                        <p class="mt-1 text-base-content/70">with a score of 100%</p>
                    </div>

                    <!-- Date and Instructor -->
                    <div class="mb-8">
                        <p class="text-base-content/70">on {{ $this->formatDate($selectedEnrollment['certificate_date']) }}</p>
                        <p class="mt-4 text-base-content/70">Instructor: {{ $selectedEnrollment['instructor'] }}</p>
                    </div>

                    <!-- Certificate ID -->
                    <div class="text-xs text-base-content/50">
                        <p>Certificate ID: CERT-{{ $selectedEnrollment['id'] }}-{{ str_replace(['-', ' '], '', $user->name) }}</p>
                    </div>

                    <!-- Watermark -->
                    <div class="absolute transform -translate-x-1/2 -translate-y-1/2 pointer-events-none select-none top-1/2 left-1/2 opacity-5">
                        <x-icon name="o-academic-cap" class="w-48 h-48" />
                    </div>
                </div>

                <div class="modal-action">
                    <button wire:click="downloadCertificate({{ $selectedEnrollment['id'] }})" class="btn btn-primary">
                        <x-icon name="o-arrow-down-tray" class="w-5 h-5 mr-2" />
                        Download Certificate
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
