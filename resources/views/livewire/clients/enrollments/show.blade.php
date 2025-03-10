<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public $user;
    public $clientProfile;
    public $enrollment;
    public $enrollmentId;

    // Tab state
    public $activeTab = 'overview';

    // UI states
    public $showReviewModal = false;
    public $showCertificateModal = false;
    public $showCompletionModal = false;

    // For review form
    public $reviewRating = 5;
    public $reviewComment = '';

    // For note taking
    public $notes = [];
    public $currentNote = '';

    public function mount($enrollment)
    {
        $this->user = Auth::user();
        $this->clientProfile = $this->user->clientProfile;
        $this->enrollmentId = $enrollment;

        // In a real app, you'd fetch the enrollment from the database
        // For now, using mock data
        $this->enrollment = $this->getEnrollmentData($enrollment);

        // Prefill mock notes
        $this->notes = [
            [
                'id' => 1,
                'content' => 'Remember to review the section on middleware and how it relates to request lifecycle.',
                'created_at' => Carbon::now()->subDays(2)->toDateTimeString(),
                'lesson' => 'API Authentication with Sanctum'
            ],
            [
                'id' => 2,
                'content' => 'The presenter pattern is really useful for transforming data between models and API responses.',
                'created_at' => Carbon::now()->subDays(4)->toDateTimeString(),
                'lesson' => 'API Resources and Transformers'
            ]
        ];
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function continueLearning()
    {
        // In a real app, this would redirect to the course player
        $this->toast(
            type: 'info',
            title: 'Continuing course...',
            description: 'Redirecting to ' . $this->enrollment['current_lesson'],
            position: 'toast-bottom toast-end',
            icon: 'o-play',
            css: 'alert-info',
            timeout: 2000
        );
    }

    public function markLessonComplete($lessonIndex)
    {
        // In a real app, you'd update the database
        // Just showing a toast for demo
        $this->toast(
            type: 'success',
            title: 'Lesson marked as complete',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 2000
        );
    }

    public function openReviewModal()
    {
        $this->showReviewModal = true;
    }

    public function closeReviewModal()
    {
        $this->showReviewModal = false;
        $this->resetReviewForm();
    }

    public function resetReviewForm()
    {
        $this->reviewRating = 5;
        $this->reviewComment = '';
    }

    public function submitReview()
    {
        // In a real app, you'd save the review to the database
        $this->toast(
            type: 'success',
            title: 'Review submitted',
            description: 'Thank you for your feedback!',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeReviewModal();
    }

    public function viewCertificate()
    {
        if ($this->enrollment['progress'] < 100) {
            $this->toast(
                type: 'warning',
                title: 'Course not completed',
                description: 'You need to complete the course to get a certificate.',
                position: 'toast-bottom toast-end',
                icon: 'o-exclamation-triangle',
                css: 'alert-warning',
                timeout: 3000
            );
            return;
        }

        $this->showCertificateModal = true;
    }

    public function closeCertificateModal()
    {
        $this->showCertificateModal = false;
    }

    public function downloadCertificate()
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

    public function openCompletionModal()
    {
        $this->showCompletionModal = true;
    }

    public function closeCompletionModal()
    {
        $this->showCompletionModal = false;
    }

    public function markCourseComplete()
    {
        // In a real app, you'd update the database
        $this->enrollment['progress'] = 100;
        $this->enrollment['status'] = 'completed';
        $this->enrollment['certificate_date'] = Carbon::now()->format('Y-m-d');
        $this->enrollment['has_certificate'] = true;

        $this->toast(
            type: 'success',
            title: 'Course completed!',
            description: 'Congratulations on completing the course!',
            position: 'toast-bottom toast-end',
            icon: 'o-party-popper',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeCompletionModal();
    }

    public function addNote()
    {
        if (empty(trim($this->currentNote))) {
            return;
        }

        // In a real app, you'd save to the database
        $this->notes[] = [
            'id' => count($this->notes) + 1,
            'content' => $this->currentNote,
            'created_at' => Carbon::now()->toDateTimeString(),
            'lesson' => $this->enrollment['current_lesson']
        ];

        $this->currentNote = '';

        $this->toast(
            type: 'success',
            title: 'Note saved',
            position: 'toast-bottom toast-end',
            icon: 'o-document-text',
            css: 'alert-success',
            timeout: 2000
        );
    }

    public function deleteNote($noteId)
    {
        // In a real app, you'd delete from the database
        $this->notes = array_filter($this->notes, function($note) use ($noteId) {
            return $note['id'] !== $noteId;
        });

        $this->toast(
            type: 'info',
            title: 'Note deleted',
            position: 'toast-bottom toast-end',
            icon: 'o-trash',
            css: 'alert-info',
            timeout: 2000
        );
    }

    // Format date for display
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // Get relative time for display
    public function getRelativeTime($date)
    {
        return Carbon::parse($date)->diffForHumans();
    }

    // Mock data for the enrollment (in a real app, you would fetch from database)
    private function getEnrollmentData($enrollmentId)
    {
        $allEnrollments = [
            1 => [
                'id' => 1,
                'course_id' => 1,
                'course_title' => 'Advanced Laravel Development',
                'course_slug' => 'advanced-laravel-development',
                'thumbnail' => 'course-laravel.jpg',
                'instructor' => 'Sarah Johnson',
                'instructor_title' => 'Senior Laravel Developer',
                'progress' => 35,
                'status' => 'in_progress',
                'enrollment_date' => '2024-01-15',
                'last_accessed' => '2024-03-01 14:20:00',
                'expiry_date' => '2025-01-15',
                'certificate_date' => null,
                'has_certificate' => false,
                'current_lesson' => 'API Development with Laravel',
                'current_section' => 'API Development',
                'current_lesson_index' => 2,
                'current_section_index' => 2,
                'total_lessons' => 42,
                'completed_lessons' => 15,
                'category' => 'development',
                'level' => 'advanced',
                'description' => 'Master Laravel framework with advanced techniques and best practices. This comprehensive course takes you beyond the basics to build robust, scalable applications with Laravel.',
                'sections' => [
                    [
                        'title' => 'Getting Started',
                        'lessons' => [
                            ['title' => 'Course Overview', 'duration' => '8:45', 'is_completed' => true],
                            ['title' => 'Setting Up Your Development Environment', 'duration' => '15:20', 'is_completed' => true],
                            ['title' => 'Laravel Project Structure', 'duration' => '12:10', 'is_completed' => true]
                        ]
                    ],
                    [
                        'title' => 'Advanced Eloquent ORM',
                        'lessons' => [
                            ['title' => 'Complex Relationships', 'duration' => '18:30', 'is_completed' => true],
                            ['title' => 'Query Scopes and Macros', 'duration' => '14:15', 'is_completed' => true],
                            ['title' => 'Custom Eloquent Collections', 'duration' => '16:40', 'is_completed' => true],
                            ['title' => 'Events and Observers', 'duration' => '20:05', 'is_completed' => true]
                        ]
                    ],
                    [
                        'title' => 'API Development',
                        'lessons' => [
                            ['title' => 'RESTful API Design Principles', 'duration' => '22:15', 'is_completed' => true],
                            ['title' => 'API Authentication with Sanctum', 'duration' => '25:30', 'is_completed' => true],
                            ['title' => 'API Resources and Transformers', 'duration' => '19:45', 'is_completed' => false],
                            ['title' => 'API Versioning and Documentation', 'duration' => '23:10', 'is_completed' => false]
                        ]
                    ],
                    [
                        'title' => 'Testing in Laravel',
                        'lessons' => [
                            ['title' => 'Unit and Feature Tests', 'duration' => '24:20', 'is_completed' => false],
                            ['title' => 'HTTP Tests and Mocking', 'duration' => '18:35', 'is_completed' => false],
                            ['title' => 'Database Testing', 'duration' => '20:55', 'is_completed' => false],
                            ['title' => 'TDD Workflow', 'duration' => '26:15', 'is_completed' => false]
                        ]
                    ]
                ],
                'reviews' => []
            ],
            2 => [
                'id' => 2,
                'course_id' => 2,
                'course_title' => 'React and Redux Masterclass',
                'course_slug' => 'react-redux-masterclass',
                'thumbnail' => 'course-react.jpg',
                'instructor' => 'Michael Chen',
                'instructor_title' => 'Frontend Developer & Consultant',
                'progress' => 68,
                'status' => 'in_progress',
                'enrollment_date' => '2023-11-10',
                'last_accessed' => '2024-02-28 09:15:00',
                'expiry_date' => '2024-11-10',
                'certificate_date' => null,
                'has_certificate' => false,
                'current_lesson' => 'Advanced Redux Middleware',
                'current_section' => 'Redux State Management',
                'current_lesson_index' => 2,
                'current_section_index' => 1,
                'total_lessons' => 38,
                'completed_lessons' => 26,
                'category' => 'development',
                'level' => 'intermediate',
                'description' => 'Comprehensive guide to building scalable applications with React and Redux. This in-depth course teaches you how to build complex, data-driven applications.',
                'sections' => [
                    [
                        'title' => 'React Fundamentals',
                        'lessons' => [
                            ['title' => 'Course Introduction', 'duration' => '10:15', 'is_completed' => true],
                            ['title' => 'React Component Patterns', 'duration' => '18:40', 'is_completed' => true],
                            ['title' => 'Hooks Deep Dive', 'duration' => '22:30', 'is_completed' => true],
                            ['title' => 'Context API', 'duration' => '16:45', 'is_completed' => true]
                        ]
                    ],
                    [
                        'title' => 'Redux State Management',
                        'lessons' => [
                            ['title' => 'Redux Core Concepts', 'duration' => '20:10', 'is_completed' => true],
                            ['title' => 'Actions and Reducers', 'duration' => '24:35', 'is_completed' => true],
                            ['title' => 'Advanced Redux Middleware', 'duration' => '28:20', 'is_completed' => false],
                            ['title' => 'Redux Toolkit', 'duration' => '26:15', 'is_completed' => false]
                        ]
                    ]
                ],
                'reviews' => []
            ],
            3 => [
                'id' => 3,
                'course_id' => 3,
                'course_title' => 'UI/UX Design Fundamentals',
                'course_slug' => 'ui-ux-design-fundamentals',
                'thumbnail' => 'course-uiux.jpg',
                'instructor' => 'Emily Rodriguez',
                'instructor_title' => 'Senior UX Designer',
                'progress' => 100,
                'status' => 'completed',
                'enrollment_date' => '2023-09-05',
                'last_accessed' => '2023-10-25 16:45:00',
                'expiry_date' => '2024-09-05',
                'certificate_date' => '2023-10-25',
                'has_certificate' => true,
                'current_lesson' => 'Course Completed',
                'current_section' => 'Course Completed',
                'current_lesson_index' => null,
                'current_section_index' => null,
                'total_lessons' => 28,
                'completed_lessons' => 28,
                'category' => 'design',
                'level' => 'beginner',
                'description' => 'Learn the principles of effective UI/UX design and create stunning user interfaces. This course provides a comprehensive introduction to user interface and user experience design.',
                'sections' => [
                    [
                        'title' => 'Design Fundamentals',
                        'lessons' => [
                            ['title' => 'Introduction to UI/UX', 'duration' => '12:20', 'is_completed' => true],
                            ['title' => 'Design Principles', 'duration' => '16:45', 'is_completed' => true],
                            ['title' => 'Color Theory', 'duration' => '14:30', 'is_completed' => true],
                            ['title' => 'Typography Basics', 'duration' => '15:20', 'is_completed' => true]
                        ]
                    ],
                    [
                        'title' => 'User Research',
                        'lessons' => [
                            ['title' => 'User Research Methods', 'duration' => '22:15', 'is_completed' => true],
                            ['title' => 'Creating User Personas', 'duration' => '18:40', 'is_completed' => true],
                            ['title' => 'User Journey Mapping', 'duration' => '24:10', 'is_completed' => true],
                            ['title' => 'Usability Testing', 'duration' => '28:35', 'is_completed' => true]
                        ]
                    ]
                ],
                'reviews' => [
                    [
                        'rating' => 5,
                        'comment' => 'This course completely changed how I think about design. Emily is an amazing instructor who makes complex concepts accessible.',
                        'date' => '2023-10-26'
                    ]
                ]
            ]
        ];

        return isset($allEnrollments[$enrollmentId]) ? $allEnrollments[$enrollmentId] : $allEnrollments[1];
    }

    protected function toast(
        string $type,
        string $title,
        $description = '',
        string $position = 'toast-bottom toast-end',
        string $icon = '',
        string $css = '',
        $timeout = 3000
    ) {
        $this->js("
            Toaster.{$type}('{$title}', {
                description: '{$description}',
                position: '{$position}',
                icon: '{$icon}',
                css: '{$css}',
                timeout: {$timeout}
            });
        ");
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Breadcrumbs -->
        <div class="mb-6 text-sm breadcrumbs">
            <ul>
                <li><a href="{{ route('clients.dashboard') }}">Dashboard</a></li>
                <li><a href="{{ route('clients.enrollments') }}">My Learning</a></li>
                <li>{{ $enrollment['course_title'] }}</li>
            </ul>
        </div>

        <!-- Enrollment Header -->
        <div class="grid grid-cols-1 gap-6 mb-8 lg:grid-cols-3">
            <!-- Course Info -->
            <div class="lg:col-span-2">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <h1 class="text-3xl font-bold">{{ $enrollment['course_title'] }}</h1>

                    <div class="flex items-center gap-2 mt-2 md:mt-0">
                        <div class="badge {{
                            $enrollment['status'] === 'in_progress' ? 'badge-info' :
                            ($enrollment['status'] === 'completed' ? 'badge-success' :
                            ($enrollment['status'] === 'paused' ? 'badge-warning' : 'badge-neutral'))
                        }}">
                            {{ ucfirst(str_replace('_', ' ', $enrollment['status'])) }}
                        </div>

                        @if($enrollment['has_certificate'])
                            <div class="gap-1 badge badge-success">
                                <x-icon name="o-academic-cap" class="w-3 h-3" />
                                Certified
                            </div>
                        @endif
                    </div>
                </div>

                <p class="mt-2 text-base-content/70">{{ $enrollment['description'] }}</p>

                <div class="flex items-center gap-3 mt-4">
                    <div class="flex items-center gap-2">
                        <div class="avatar placeholder">
                            <div class="w-8 h-8 rounded-full bg-neutral-focus text-neutral-content">
                                <span>{{ substr($enrollment['instructor'], 0, 1) }}</span>
                            </div>
                        </div>
                        <div>
                            <span class="font-medium">{{ $enrollment['instructor'] }}</span>
                            <div class="text-xs opacity-70">{{ $enrollment['instructor_title'] }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-6 md:grid-cols-4">
                    <div class="p-3 text-center rounded-lg bg-base-200">
                        <div class="text-sm opacity-70">Enrolled On</div>
                        <div class="font-medium">{{ $this->formatDate($enrollment['enrollment_date']) }}</div>
                    </div>

                    <div class="p-3 text-center rounded-lg bg-base-200">
                        <div class="text-sm opacity-70">Last Accessed</div>
                        <div class="font-medium">{{ $this->getRelativeTime($enrollment['last_accessed']) }}</div>
                    </div>

                    <div class="p-3 text-center rounded-lg bg-base-200">
                        <div class="text-sm opacity-70">Access Until</div>
                        <div class="font-medium">{{ $this->formatDate($enrollment['expiry_date']) }}</div>
                    </div>

                    <div class="p-3 text-center rounded-lg bg-base-200">
                        <div class="text-sm opacity-70">Lessons Completed</div>
                        <div class="font-medium">{{ $enrollment['completed_lessons'] }}/{{ $enrollment['total_lessons'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Progress Card -->
            <div class="lg:col-span-1">
                <div class="p-6 shadow-xl card bg-base-100">
                    <div class="flex flex-col items-center">
                        <!-- Circular Progress Indicator -->
                        <div class="relative">
                            <div class="text-center">
                                <svg class="w-32 h-32" viewBox="0 0 100 100">
                                    <!-- Background Circle -->
                                    <circle
                                        cx="50"
                                        cy="50"
                                        r="45"
                                        fill="none"
                                        stroke-width="8"
                                        class="stroke-base-300"
                                    />

                                    <!-- Progress Circle -->
                                    @php
                                        $circumference = 2 * 3.14159 * 45;
                                        $offset = $circumference - ($enrollment['progress'] / 100) * $circumference;
                                    @endphp

                                    <circle
                                        cx="50"
                                        cy="50"
                                        r="45"
                                        fill="none"
                                        stroke-width="8"
                                        stroke-dasharray="{{ $circumference }}"
                                        stroke-dashoffset="{{ $offset }}"
                                        stroke-linecap="round"
                                        class="{{ $enrollment['progress'] > 75 ? 'stroke-success' : ($enrollment['progress'] > 40 ? 'stroke-info' : 'stroke-primary') }}"
                                        transform="rotate(-90 50 50)"
                                    />
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-3xl font-bold">{{ $enrollment['progress'] }}%</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 text-center">
                            <h3 class="text-lg font-bold">Course Progress</h3>
                            <p class="mt-1 text-sm">{{ $enrollment['completed_lessons'] }} of {{ $enrollment['total_lessons'] }} lessons completed</p>
                        </div>

                        @if($enrollment['status'] !== 'completed')
                            <div class="justify-center w-full mt-6 space-y-2 card-actions">
                                <button
                                    wire:click="continueLearning"
                                    class="w-full btn btn-primary"
                                >
                                    <x-icon name="o-play" class="w-4 h-4 mr-2" />
                                    Continue Learning
                                </button>

                                <button
                                    wire:click="openCompletionModal"
                                    class="w-full btn btn-outline"
                                >
                                    <x-icon name="o-check-badge" class="w-4 h-4 mr-2" />
                                    Mark as Completed
                                </button>
                            </div>
                        @else
                            <div class="justify-center w-full mt-6 card-actions">
                                <button
                                    wire:click="viewCertificate"
                                    class="w-full btn btn-primary"
                                >
                                    <x-icon name="o-academic-cap" class="w-4 h-4 mr-2" />
                                    View Certificate
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Tabs -->
        <div class="mb-4">
            <div class="tabs tabs-boxed">
                <a
                    wire:click="setActiveTab('overview')"
                    class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}"
                >
                    Overview
                </a>
                <a
                    wire:click="setActiveTab('curriculum')"
                    class="tab {{ $activeTab === 'curriculum' ? 'tab-active' : '' }}"
                >
                    Curriculum
                </a>
                <a
                    wire:click="setActiveTab('notes')"
                    class="tab {{ $activeTab === 'notes' ? 'tab-active' : '' }}"
                >
                    My Notes
                </a>
                @if($enrollment['status'] === 'completed')
                <a
                    wire:click="setActiveTab('certificate')"
                    class="tab {{ $activeTab === 'certificate' ? 'tab-active' : '' }}"
                >
                    Certificate
                </a>
                @endif
            </div>
        </div>

        <!-- Tab Content -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <!-- Overview Tab -->
                <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <h2 class="text-xl font-bold">Current Progress</h2>

                            @if($enrollment['status'] !== 'completed')
                                <div class="p-4 mt-4 rounded-lg bg-base-200">
                                    <h3 class="text-lg font-medium">Next Up: {{ $enrollment['current_lesson'] }}</h3>
                                    <p class="mt-1 text-sm">Continue where you left off in the {{ $enrollment['current_section'] }} section.</p>

                                    <div class="mt-4">
                                        <button
                                            wire:click="continueLearning"
                                            class="btn btn-primary btn-sm"
                                        >
                                            <x-icon name="o-play" class="w-4 h-4 mr-1" />
                                            Continue
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <h3 class="text-lg font-medium">Your Learning Journey</h3>

                                    <div class="w-full mt-4 overflow-hidden rounded-full bg-base-300">
                                        <div class="h-2 {{ $enrollment['progress'] > 75 ? 'bg-success' : ($enrollment['progress'] > 40 ? 'bg-info' : 'bg-primary') }}" style="width: {{ $enrollment['progress'] }}%"></div>
                                    </div>

                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-xs opacity-70">Started</span>
                                        <span class="text-xs opacity-70">In Progress</span>
                                        <span class="text-xs opacity-70">Completed</span>
                                    </div>
                                </div>
                            @else
                                <div class="p-4 mt-4 rounded-lg bg-success bg-opacity-10">
                                    <div class="flex items-center gap-3">
                                        <x-icon name="o-check-circle" class="w-8 h-8 text-success" />
                                        <div>
                                            <h3 class="text-lg font-medium">Course Completed!</h3>
                                            <p class="mt-1 text-sm">You ve successfully completed this course on {{ $this->formatDate($enrollment['certificate_date']) }}.</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-3 mt-4 md:grid-cols-2">
                                        <button
                                            wire:click="viewCertificate"
                                            class="btn btn-primary btn-sm"
                                        >
                                            <x-icon name="o-academic-cap" class="w-4 h-4 mr-1" />
                                            View Certificate
                                        </button>

                                        <button
                                            wire:click="openReviewModal"
                                            class="btn btn-outline btn-sm"
                                        >
                                            <x-icon name="o-pencil" class="w-4 h-4 mr-1" />
                                            @if(count($enrollment['reviews']) > 0)
                                                Edit Review
                                            @else
                                                Write a Review
                                            @endif
                                        </button>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-6">
                                <h3 class="text-lg font-medium">Recent Activity</h3>

                                <div class="mt-4 overflow-x-auto">
                                    <table class="table table-zebra">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Activity</th>
                                                <th>Time Spent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
<td>{{ $this->formatDate(Carbon::now()->subDays(1)) }}</td>
                                                <td>Completed lesson: API Authentication with Sanctum</td>
                                                <td>25 minutes</td>
                                            </tr>
                                            <tr>
                                                <td>{{ $this->formatDate(Carbon::now()->subDays(3)) }}</td>
                                                <td>Started lesson: API Resources and Transformers</td>
                                                <td>10 minutes</td>
                                            </tr>
                                            <tr>
                                                <td>{{ $this->formatDate(Carbon::now()->subDays(5)) }}</td>
                                                <td>Completed lesson: RESTful API Design Principles</td>
                                                <td>22 minutes</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="lg:col-span-1">
                            <div class="p-4 rounded-lg bg-base-200">
                                <h3 class="text-lg font-medium">Course Details</h3>

                                <div class="mt-4 space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm opacity-70">Category</span>
                                        <span class="text-sm font-medium">{{ ucfirst($enrollment['category']) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm opacity-70">Level</span>
                                        <span class="text-sm font-medium">{{ ucfirst($enrollment['level']) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm opacity-70">Total Lessons</span>
                                        <span class="text-sm font-medium">{{ $enrollment['total_lessons'] }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm opacity-70">Status</span>
                                        <span class="text-sm font-medium">{{ ucfirst($enrollment['status']) }}</span>
                                    </div>
                                </div>

                                <div class="my-2 divider"></div>

                                <h4 class="font-medium">Learning Resources</h4>
                                <div class="mt-2 space-y-2">
                                    <a href="#" class="flex items-center gap-2 p-2 transition-colors rounded-lg hover:bg-base-300">
                                        <x-icon name="o-document-text" class="w-5 h-5 opacity-70" />
                                        <span class="text-sm">Course Materials</span>
                                    </a>
                                    <a href="#" class="flex items-center gap-2 p-2 transition-colors rounded-lg hover:bg-base-300">
                                        <x-icon name="o-arrow-down-on-square" class="w-5 h-5 opacity-70" />
                                        <span class="text-sm">Downloadable Resources</span>
                                    </a>
                                    <a href="#" class="flex items-center gap-2 p-2 transition-colors rounded-lg hover:bg-base-300">
                                        <x-icon name="o-chat-bubble-left-right" class="w-5 h-5 opacity-70" />
                                        <span class="text-sm">Discussion Forum</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Curriculum Tab -->
                <div class="{{ $activeTab === 'curriculum' ? 'block' : 'hidden' }}">
                    <h2 class="text-xl font-bold">Course Curriculum</h2>
                    <p class="mt-2 text-base-content/70">{{ $enrollment['total_lessons'] }} lessons • {{ $enrollment['completed_lessons'] }} completed</p>

                    <div class="mt-6 space-y-4">
                        @foreach($enrollment['sections'] as $sectionIndex => $section)
                            <div class="border collapse collapse-arrow rounded-box border-base-300">
                                <input type="checkbox" {{ $sectionIndex === $enrollment['current_section_index'] ? 'checked' : '' }} />
                                <div class="text-lg font-medium collapse-title">
                                    {{ $section['title'] }}
                                    <div class="text-sm font-normal opacity-70">
                                        {{ count(array_filter($section['lessons'], function($lesson) { return $lesson['is_completed']; })) }}/{{ count($section['lessons']) }} lessons completed
                                    </div>
                                </div>
                                <div class="collapse-content">
                                    <ul class="space-y-2">
                                        @foreach($section['lessons'] as $lessonIndex => $lesson)
                                            <li class="flex items-center justify-between p-2 rounded-lg {{ $sectionIndex === $enrollment['current_section_index'] && $lessonIndex === $enrollment['current_lesson_index'] ? 'bg-primary bg-opacity-10' : 'hover:bg-base-200' }}">
                                                <div class="flex items-center gap-2">
                                                    @if($lesson['is_completed'])
                                                        <x-icon name="o-check-circle" class="w-5 h-5 text-success" />
                                                    @else
                                                        <x-icon name="o-play-circle" class="w-5 h-5 opacity-70" />
                                                    @endif
                                                    <span>{{ $lesson['title'] }}</span>
                                                    @if($sectionIndex === $enrollment['current_section_index'] && $lessonIndex === $enrollment['current_lesson_index'])
                                                        <span class="ml-2 badge badge-sm badge-primary">Current</span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs opacity-70">{{ $lesson['duration'] }}</span>
                                                    @if(!$lesson['is_completed'])
                                                        <button
                                                            wire:click="markLessonComplete('{{ $sectionIndex }}-{{ $lessonIndex }}')"
                                                            class="btn btn-ghost btn-xs"
                                                        >
                                                            Mark Complete
                                                        </button>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Notes Tab -->
                <div class="{{ $activeTab === 'notes' ? 'block' : 'hidden' }}">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-bold">My Notes</h2>
                        <span class="badge badge-neutral">{{ count($notes) }} notes</span>
                    </div>

                    <div class="p-4 mt-6 rounded-lg bg-base-200">
                        <h3 class="font-medium">Add a Note</h3>
                        <div class="flex items-start gap-2 mt-2">
                            <textarea
                                wire:model="currentNote"
                                class="w-full h-24 textarea textarea-bordered"
                                placeholder="Take notes on your current lesson..."
                            ></textarea>
                        </div>
                        <div class="mt-2 text-right">
                            <button
                                wire:click="addNote"
                                class="btn btn-primary btn-sm"
                                @if(empty(trim($currentNote))) disabled @endif
                            >
                                Save Note
                            </button>
                        </div>
                    </div>

                    <div class="mt-6">
                        @if(count($notes) > 0)
                            <div class="space-y-4">
                                @foreach($notes as $note)
                                    <div class="p-4 rounded-lg bg-base-200">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-2">
                                                <x-icon name="o-document-text" class="w-5 h-5 opacity-70" />
                                                <span class="font-medium">{{ $note['lesson'] }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs opacity-70">{{ $this->getRelativeTime($note['created_at']) }}</span>
                                                <button
                                                    wire:click="deleteNote({{ $note['id'] }})"
                                                    class="btn btn-ghost btn-xs"
                                                >
                                                    <x-icon name="o-trash" class="w-4 h-4 text-error" />
                                                </button>
                                            </div>
                                        </div>
                                        <p class="mt-2">{{ $note['content'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-8 text-center rounded-lg bg-base-200">
                                <x-icon name="o-document-text" class="w-12 h-12 mx-auto mb-4 opacity-30" />
                                <h3 class="text-lg font-medium">No notes yet</h3>
                                <p class="mt-2 text-sm opacity-70">Start taking notes to help you remember key concepts.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Certificate Tab -->
                <div class="{{ $activeTab === 'certificate' ? 'block' : 'hidden' }}">
                    @if($enrollment['has_certificate'])
                        <div class="text-center">
                            <h2 class="text-xl font-bold">Your Certificate</h2>
                            <p class="mt-2 opacity-70">Congratulations on completing this course!</p>

                            <div class="relative max-w-3xl p-8 mx-auto mt-8 text-center border-8 border-primary/20 bg-base-100">
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
                                    <h4 class="mt-2 text-xl font-bold">{{ $enrollment['course_title'] }}</h4>
                                    <p class="mt-1 text-base-content/70">with a score of 100%</p>
                                </div>

                                <!-- Date and Instructor -->
                                <div class="mb-8">
                                    <p class="text-base-content/70">on {{ $this->formatDate($enrollment['certificate_date']) }}</p>
                                    <p class="mt-4 text-base-content/70">Instructor: {{ $enrollment['instructor'] }}</p>
                                </div>

                                <!-- Certificate ID -->
                                <div class="text-xs text-base-content/50">
                                    <p>Certificate ID: CERT-{{ $enrollment['id'] }}-{{ str_replace(['-', ' '], '', $user->name) }}</p>
                                </div>

                                <!-- Watermark -->
                                <div class="absolute transform -translate-x-1/2 -translate-y-1/2 pointer-events-none select-none top-1/2 left-1/2 opacity-5">
                                    <x-icon name="o-academic-cap" class="w-48 h-48" />
                                </div>
                            </div>

                            <div class="mt-8">
                                <button
                                    wire:click="downloadCertificate"
                                    class="btn btn-primary"
                                >
                                    <x-icon name="o-arrow-down-tray" class="w-5 h-5 mr-2" />
                                    Download Certificate
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="p-8 text-center">
                            <x-icon name="o-academic-cap" class="w-16 h-16 mx-auto mb-4 opacity-30" />
                            <h3 class="text-lg font-medium">Certificate Not Available</h3>
                            <p class="mt-2 opacity-70">You need to complete the course to receive your certificate.</p>

                            <div class="w-full max-w-md mx-auto mt-6 overflow-hidden rounded-full bg-base-300">
                                <div class="h-2 {{ $enrollment['progress'] > 75 ? 'bg-success' : ($enrollment['progress'] > 40 ? 'bg-info' : 'bg-primary') }}" style="width: {{ $enrollment['progress'] }}%"></div>
                            </div>
                            <p class="mt-2 text-sm">{{ $enrollment['progress'] }}% complete</p>

                            <button
                                wire:click="continueLearning"
                                class="mt-6 btn btn-primary"
                            >
                                <x-icon name="o-play" class="w-4 h-4 mr-2" />
                                Continue Learning
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Course Completion Modal -->
    <div class="modal {{ $showCompletionModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <button wire:click="closeCompletionModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            <h3 class="text-lg font-bold">Mark Course as Completed</h3>

            <div class="p-4 mt-4 rounded-lg bg-base-200">
                <p>Are you sure you want to mark this course as completed?</p>
                <p class="mt-2 text-sm opacity-70">This will set your progress to 100% and generate a completion certificate.</p>
            </div>

            <div class="modal-action">
                <button wire:click="closeCompletionModal" class="btn">Cancel</button>
                <button wire:click="markCourseComplete" class="btn btn-primary">
                    <x-icon name="o-check-circle" class="w-5 h-5 mr-2" />
                    Confirm Completion
                </button>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal {{ $showReviewModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <button wire:click="closeReviewModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            <h3 class="text-lg font-bold">
                @if(count($enrollment['reviews']) > 0)
                    Edit Your Review
                @else
                    Write a Review
                @endif
            </h3>

            <div class="mt-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Your Rating</span>
                    </label>
                    <div class="flex items-center gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <button
                                wire:click="$set('reviewRating', {{ $i }})"
                                type="button"
                                class="p-1 btn btn-ghost btn-sm"
                            >
                                <x-icon
                                    name="{{ $i <= $reviewRating ? 's-star' : 'o-star' }}"
                                    class="w-8 h-8 {{ $i <= $reviewRating ? 'text-yellow-500 fill-yellow-500' : 'text-yellow-500' }}"
                                />
                            </button>
                        @endfor
                    </div>
                </div>

                <div class="mt-4 form-control">
                    <label class="label">
                        <span class="label-text">Your Review</span>
                    </label>
                    <textarea
                        wire:model="reviewComment"
                        class="h-32 textarea textarea-bordered"
                        placeholder="Tell us what you think about this course..."
                    ></textarea>
                </div>
            </div>

            <div class="modal-action">
                <button wire:click="closeReviewModal" class="btn">Cancel</button>
                <button
                    wire:click="submitReview"
                    class="btn btn-primary"
                    @if(empty(trim($reviewComment))) disabled @endif
                >
                    Submit Review
                </button>
            </div>
        </div>
    </div>

    <!-- Certificate Modal -->
    <div class="modal {{ $showCertificateModal ? 'modal-open' : '' }}">
        <div class="max-w-3xl modal-box">
            <button wire:click="closeCertificateModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

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
                    <h4 class="mt-2 text-xl font-bold">{{ $enrollment['course_title'] }}</h4>
                    <p class="mt-1 text-base-content/70">with a score of 100%</p>
                </div>

                <!-- Date and Instructor -->
                <div class="mb-8">
                    <p class="text-base-content/70">on {{ $this->formatDate($enrollment['certificate_date']) }}</p>
                    <p class="mt-4 text-base-content/70">Instructor: {{ $enrollment['instructor'] }}</p>
                </div>

                <!-- Certificate ID -->
                <div class="text-xs text-base-content/50">
                    <p>Certificate ID: CERT-{{ $enrollment['id'] }}-{{ str_replace(['-', ' '], '', $user->name) }}</p>
                </div>

                <!-- Watermark -->
                <div class="absolute transform -translate-x-1/2 -translate-y-1/2 pointer-events-none select-none top-1/2 left-1/2 opacity-5">
                    <x-icon name="o-academic-cap" class="w-48 h-48" />
                </div>
            </div>

            <div class="modal-action">
                <button wire:click="downloadCertificate" class="btn btn-primary">
                    <x-icon name="o-arrow-down-tray" class="w-5 h-5 mr-2" />
                    Download Certificate
                </button>
            </div>
        </div>
    </div>
</div>
