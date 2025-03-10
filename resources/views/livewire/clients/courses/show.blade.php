<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public $user;
    public $clientProfile;
    public $course;
    public $courseId;

    // Tab state
    public $activeTab = 'overview';

    // UI states
    public $showEnrollModal = false;
    public $isInWishlist = false;

    // For review form
    public $showReviewForm = false;
    public $reviewRating = 5;
    public $reviewComment = '';

    public function mount($course)
    {
        $this->user = Auth::user();
        $this->clientProfile = $this->user->clientProfile;
        $this->courseId = $course;

        // In a real app, you'd fetch the course from the database
        // For now, using mock data
        $this->course = $this->getCourseData($course);

        // Determine if the user has wishlisted this course
        $this->isInWishlist = in_array($this->courseId, [3, 5]); // Mock data
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function toggleWishlist()
    {
        // In a real application, you'd update the database
        $this->isInWishlist = !$this->isInWishlist;

        $this->toast(
            type: $this->isInWishlist ? 'success' : 'info',
            title: $this->isInWishlist ? 'Added to wishlist' : 'Removed from wishlist',
            position: 'toast-bottom toast-end',
            icon: 'o-heart',
            css: $this->isInWishlist ? 'alert-success' : 'alert-info',
            timeout: 2000
        );
    }

    public function showEnrollmentModal()
    {
        $this->showEnrollModal = true;
    }

    public function closeEnrollmentModal()
    {
        $this->showEnrollModal = false;
    }

    public function enrollNow()
    {
        // In a real app, you'd create an enrollment record
        $this->closeEnrollmentModal();

        $this->toast(
            type: 'success',
            title: 'Successfully enrolled!',
            description: 'You have been enrolled in ' . $this->course['title'],
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000,
            redirectTo: route('clients.enrollments')
        );

        return $this->redirect(route('clients.enrollments'), navigate: true);
    }

    public function openReviewForm()
    {
        $this->showReviewForm = true;
    }

    public function closeReviewForm()
    {
        $this->showReviewForm = false;
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

        $this->closeReviewForm();
    }

    // Format date for display
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // Mock data for the course (in a real app, you would fetch from database)
    private function getCourseData($courseId)
    {
        $allCourses = [
            1 => [
                'id' => 1,
                'title' => 'Advanced Laravel Development',
                'slug' => 'advanced-laravel-development',
                'description' => 'Master Laravel framework with advanced techniques and best practices. This comprehensive course takes you beyond the basics to build robust, scalable applications with Laravel. You\'ll learn advanced Eloquent usage, custom authentication systems, caching strategies, and more. By the end of this course, you\'ll have the skills to architect and implement complex web applications with confidence.',
                'short_description' => 'Take your Laravel skills to the next level.',
                'price' => 129.99,
                'sale_price' => 89.99,
                'thumbnail' => 'course-laravel.jpg',
                'category' => 'development',
                'level' => 'advanced',
                'duration' => '10 weeks',
                'lessons' => 42,
                'students' => 1458,
                'rating' => 4.8,
                'reviews_count' => 326,
                'instructor' => 'Sarah Johnson',
                'instructor_title' => 'Senior Laravel Developer',
                'instructor_bio' => 'Sarah has over 10 years of experience in web development with a focus on PHP and Laravel. She has built enterprise-level applications and consulted for Fortune 500 companies.',
                'created_at' => '2024-02-15',
                'updated_at' => '2024-02-28',
                'is_featured' => true,
                'is_bestseller' => true,
                'skills' => ['Laravel', 'PHP', 'MySQL', 'API Development', 'TDD'],
                'prerequisites' => [
                    'Basic knowledge of PHP',
                    'Understanding of MVC architecture',
                    'Familiarity with basic Laravel concepts'
                ],
                'what_youll_learn' => [
                    'Build complex Laravel applications using best practices',
                    'Implement authentication and authorization',
                    'Develop RESTful APIs with Laravel',
                    'Master Eloquent ORM and database relationships',
                    'Implement testing strategies for Laravel apps',
                    'Create and use Laravel packages',
                    'Optimize Laravel applications for performance',
                    'Work with queues and background jobs'
                ],
                'course_includes' => [
                    '42 on-demand video lessons',
                    '15 coding exercises',
                    '5 real-world projects',
                    'Access on mobile and desktop',
                    'Certificate of completion',
                    'Lifetime access'
                ],
                'sections' => [
                    [
                        'title' => 'Getting Started',
                        'lessons' => [
                            'Course Overview',
                            'Setting Up Your Development Environment',
                            'Laravel Project Structure'
                        ]
                    ],
                    [
                        'title' => 'Advanced Eloquent ORM',
                        'lessons' => [
                            'Complex Relationships',
                            'Query Scopes and Macros',
                            'Custom Eloquent Collections',
                            'Events and Observers'
                        ]
                    ],
                    [
                        'title' => 'API Development',
                        'lessons' => [
                            'RESTful API Design Principles',
                            'API Authentication with Sanctum',
                            'API Resources and Transformers',
                            'API Versioning and Documentation'
                        ]
                    ],
                    [
                        'title' => 'Testing in Laravel',
                        'lessons' => [
                            'Unit and Feature Tests',
                            'HTTP Tests and Mocking',
                            'Database Testing',
                            'TDD Workflow'
                        ]
                    ]
                ],
                'reviews' => [
                    [
                        'user' => 'John D.',
                        'rating' => 5,
                        'date' => '2024-02-20',
                        'comment' => 'This course was exactly what I needed to level up my Laravel skills. The projects were challenging but doable, and I learned so much!'
                    ],
                    [
                        'user' => 'Alice K.',
                        'rating' => 4,
                        'date' => '2024-01-15',
                        'comment' => 'Great content and explanations. I especially enjoyed the sections on API development and testing.'
                    ],
                    [
                        'user' => 'Michael R.',
                        'rating' => 5,
                        'date' => '2023-12-10',
                        'comment' => 'Sarah is an excellent instructor. Her explanations are clear, and she provides real-world examples that make complex concepts easy to understand.'
                    ]
                ]
            ],
            2 => [
                'id' => 2,
                'title' => 'React and Redux Masterclass',
                'slug' => 'react-redux-masterclass',
                'description' => 'Comprehensive guide to building scalable applications with React and Redux. This in-depth course teaches you how to build complex, data-driven applications with React and Redux. You\'ll learn component design patterns, state management strategies, routing, and optimization techniques. By working through practical examples and real-world projects, you\'ll gain the skills to build professional-grade React applications.',
                'short_description' => 'Become a React expert.',
                'price' => 149.99,
                'sale_price' => null,
                'thumbnail' => 'course-react.jpg',
                'category' => 'development',
                'level' => 'intermediate',
                'duration' => '8 weeks',
                'lessons' => 38,
                'students' => 2145,
                'rating' => 4.7,
                'reviews_count' => 512,
                'instructor' => 'Michael Chen',
                'instructor_title' => 'Frontend Developer & Consultant',
                'instructor_bio' => 'Michael is a seasoned frontend developer with expertise in React and modern JavaScript. He has worked with startups and tech companies to build scalable frontend architectures.',
                'created_at' => '2024-01-10',
                'updated_at' => '2024-02-05',
                'is_featured' => false,
                'is_bestseller' => true,
                'skills' => ['React', 'Redux', 'JavaScript', 'Frontend Development', 'State Management'],
                'prerequisites' => [
                    'Basic JavaScript knowledge',
                    'Understanding of HTML and CSS',
                    'Familiarity with ES6+ syntax'
                ],
                'what_youll_learn' => [
                    'Build complex UIs with React components',
                    'Manage application state with Redux',
                    'Implement routing and navigation',
                    'Optimize React applications for performance',
                    'Test React components effectively',
                    'Create custom hooks and context providers',
                    'Implement authentication in React apps',
                    'Deploy React applications to production'
                ],
                'course_includes' => [
                    '38 on-demand video lessons',
                    '12 coding exercises',
                    '4 real-world projects',
                    'Access on mobile and desktop',
                    'Certificate of completion',
                    'Lifetime access'
                ],
                'sections' => [
                    [
                        'title' => 'React Fundamentals',
                        'lessons' => [
                            'Course Introduction',
                            'React Component Patterns',
                            'Hooks Deep Dive',
                            'Context API'
                        ]
                    ],
                    [
                        'title' => 'Redux State Management',
                        'lessons' => [
                            'Redux Core Concepts',
                            'Actions and Reducers',
                            'Redux Middleware',
                            'Redux Toolkit'
                        ]
                    ],
                    [
                        'title' => 'Advanced Patterns',
                        'lessons' => [
                            'Custom Hooks',
                            'Performance Optimization',
                            'Code Splitting',
                            'Error Boundaries'
                        ]
                    ],
                    [
                        'title' => 'Testing and Deployment',
                        'lessons' => [
                            'Testing with React Testing Library',
                            'Mocking APIs in Tests',
                            'Deployment Strategies',
                            'CI/CD for React Apps'
                        ]
                    ]
                ],
                'reviews' => [
                    [
                        'user' => 'Sarah M.',
                        'rating' => 5,
                        'date' => '2024-02-12',
                        'comment' => 'This course transformed how I approach React development. The Redux sections were particularly enlightening.'
                    ],
                    [
                        'user' => 'James W.',
                        'rating' => 4,
                        'date' => '2024-01-30',
                        'comment' => 'Great course overall. I would have liked more examples of integrating with backend APIs, but the React and Redux content was excellent.'
                    ],
                    [
                        'user' => 'Emily L.',
                        'rating' => 5,
                        'date' => '2023-12-15',
                        'comment' => 'Michael explains complex concepts in an easy-to-understand way. The projects were challenging but helped solidify my understanding.'
                    ]
                ]
            ],
            3 => [
                'id' => 3,
                'title' => 'UI/UX Design Fundamentals',
                'slug' => 'ui-ux-design-fundamentals',
                'description' => 'Learn the principles of effective UI/UX design and create stunning user interfaces. This course provides a comprehensive introduction to user interface and user experience design. You\'ll learn design theory, usability principles, and practical skills using industry-standard tools. Through hands-on projects, you\'ll develop your design thinking and create interfaces that are both beautiful and user-friendly.',
                'short_description' => 'Create beautiful, user-friendly designs.',
                'price' => 99.99,
                'sale_price' => 79.99,
                'thumbnail' => 'course-uiux.jpg',
                'category' => 'design',
                'level' => 'beginner',
                'duration' => '6 weeks',
                'lessons' => 28,
                'students' => 3250,
                'rating' => 4.9,
                'reviews_count' => 748,
                'instructor' => 'Emily Rodriguez',
                'instructor_title' => 'Senior UX Designer',
                'instructor_bio' => 'Emily has worked as a UX designer for top tech companies and agencies. Her design philosophy focuses on creating intuitive, accessible, and delightful user experiences.',
                'created_at' => '2023-11-25',
                'updated_at' => '2024-01-15',
                'is_featured' => true,
                'is_bestseller' => true,
                'skills' => ['UI Design', 'UX Research', 'Figma', 'Design Principles', 'Wireframing'],
                'prerequisites' => [
                    'No prior design experience required',
                    'Basic computer skills',
                    'Interest in visual design and user experience'
                ],
                'what_youll_learn' => [
                    'Create user-centered designs',
                    'Conduct effective user research',
                    'Design intuitive user interfaces',
                    'Create wireframes and prototypes',
                    'Test and iterate on designs',
                    'Apply design principles to real-world projects',
                    'Use Figma for UI/UX design',
                    'Present and explain your design decisions'
                ],
                'course_includes' => [
                    '28 on-demand video lessons',
                    '8 design exercises',
                    '3 portfolio-ready projects',
                    'Access on mobile and desktop',
                    'Certificate of completion',
                    'Lifetime access',
                    'Design resource downloads'
                ],
                'sections' => [
                    [
                        'title' => 'Design Fundamentals',
                        'lessons' => [
                            'Introduction to UI/UX',
                            'Design Principles',
                            'Color Theory',
                            'Typography Basics'
                        ]
                    ],
                    [
                        'title' => 'User Research',
                        'lessons' => [
                            'User Research Methods',
                            'Creating User Personas',
                            'User Journey Mapping',
                            'Usability Testing'
                        ]
                    ],
                    [
                        'title' => 'Design Tools',
                        'lessons' => [
                            'Introduction to Figma',
                            'Wireframing in Figma',
                            'Creating UI Components',
                            'Prototyping Interactions'
                        ]
                    ],
                    [
                        'title' => 'Design Projects',
                        'lessons' => [
                            'Mobile App Design Project',
                            'Website Redesign Project',
                            'Design System Creation',
                            'Portfolio Preparation'
                        ]
                    ]
                ],
                'reviews' => [
                    [
                        'user' => 'Daniel J.',
                        'rating' => 5,
                        'date' => '2024-02-15',
                        'comment' => 'This course completely changed how I think about design. Emily is an amazing instructor who makes complex concepts accessible.'
                    ],
                    [
                        'user' => 'Rebecca T.',
                        'rating' => 5,
                        'date' => '2024-01-20',
                        'comment' => 'Perfect for beginners! I had no design background, but now I feel confident creating user interfaces that look great and function well.'
                    ],
                    [
                        'user' => 'Mark P.',
                        'rating' => 4,
                        'date' => '2023-12-05',
                        'comment' => 'The projects in this course are excellent and help reinforce the concepts. Would have liked more on accessibility, but overall a great course.'
                    ]
                ]
            ]
        ];

        return isset($allCourses[$courseId]) ? $allCourses[$courseId] : $allCourses[1];
    }

    protected function toast(
        string $type,
        string $title,
        $description = '',
        string $position = 'toast-bottom toast-end',
        string $icon = '',
        string $css = '',
        $timeout = 3000,
        $redirectTo = null
    ) {
        $redirectPath = $redirectTo ? $redirectTo : '';

        $this->js("
            Toaster.{$type}('{$title}', {
                description: '{$description}',
                position: '{$position}',
                icon: '{$icon}',
                css: '{$css}',
                timeout: {$timeout},
                redirectTo: '{$redirectPath}'
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
                <li><a href="{{ route('clients.courses') }}">Courses</a></li>
                <li>{{ $course['title'] }}</li>
            </ul>
        </div>

        <!-- Course Header -->
        <div class="grid grid-cols-1 gap-6 mb-8 lg:grid-cols-3">
            <!-- Course Info -->
            <div class="lg:col-span-2">
                <h1 class="text-3xl font-bold">{{ $course['title'] }}</h1>
                <p class="mt-2 text-base-content/70">{{ $course['short_description'] }}</p>

                <div class="flex flex-wrap items-center gap-4 mt-4">
                    <div class="flex items-center gap-1">
                        <x-icon name="o-star" class="w-4 h-4 text-yellow-500 fill-yellow-500" />
                        <span>{{ $course['rating'] }} ({{ $course['reviews_count'] }} reviews)</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <x-icon name="o-users" class="w-4 h-4 opacity-70" />
                        <span>{{ number_format($course['students']) }} students</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <x-icon name="o-clock" class="w-4 h-4 opacity-70" />
                        <span>{{ $course['duration'] }}</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <x-icon name="o-academic-cap" class="w-4 h-4 opacity-70" />
                        <span>{{ $course['level'] }}</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <x-icon name="o-calendar" class="w-4 h-4 opacity-70" />
                        <span>Last updated {{ $this->formatDate($course['updated_at']) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-4">
                    <div class="flex items-center gap-2">
                        <div class="avatar placeholder">
                            <div class="w-8 h-8 rounded-full bg-neutral-focus text-neutral-content">
                                <span>{{ substr($course['instructor'], 0, 1) }}</span>
                            </div>
                        </div>
                        <div>
                            <span class="font-medium">{{ $course['instructor'] }}</span>
                            <div class="text-xs opacity-70">{{ $course['instructor_title'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollment Card -->
            <div class="lg:col-span-1">
                <div class="p-6 shadow-xl card bg-base-100">
                    <figure class="px-1 pt-1">
                        <img
                            src="{{ asset('images/' . $course['thumbnail']) }}"
                            alt="{{ $course['title'] }}"
                            class="object-cover w-full rounded-xl h-44"
                            onerror="this.src='https://placehold.co/600x400?text=Course+Image'"
                        />
                    </figure>
                    <div class="card-body">
                        <div class="text-center">
                            @if($course['sale_price'])
                                <div class="text-3xl font-bold">${{ $course['sale_price'] }}</div>
                                <div class="line-through text-base-content/50">${{ $course['price'] }}</div>
                                <div class="mt-1 text-sm text-success">
                                    {{ round((($course['price'] - $course['sale_price']) / $course['price']) * 100) }}% discount!
                                </div>
                            @else
                                <div class="text-3xl font-bold">${{ $course['price'] }}</div>
                            @endif
                        </div>

                        <div class="gap-2 card-actions">
                            <button
                                wire:click="showEnrollmentModal"
                                class="w-full mt-4 btn btn-primary"
                            >
                                Enroll Now
                            </button>

                            <button
                                wire:click="toggleWishlist"
                                class="w-full btn btn-outline"
                            >
                                @if($isInWishlist)
                                    <x-icon name="s-heart" class="w-4 h-4 mr-2 text-red-500" />
                                    Remove from Wishlist
                                @else
                                    <x-icon name="o-heart" class="w-4 h-4 mr-2" />
                                    Add to Wishlist
                                @endif
                            </button>
                        </div>

                        <div class="mt-4 text-sm">
                            <h4 class="font-medium">This course includes:</h4>
                            <ul class="mt-2 space-y-2">
                                @foreach($course['course_includes'] as $item)
                                    <li class="flex items-start gap-2">
                                        <x-icon name="o-check" class="w-5 h-5 mt-0.5 text-success" />
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
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
                    wire:click="setActiveTab('instructor')"
                    class="tab {{ $activeTab === 'instructor' ? 'tab-active' : '' }}"
                >
                    Instructor
                </a>
                <a
                    wire:click="setActiveTab('reviews')"
                    class="tab {{ $activeTab === 'reviews' ? 'tab-active' : '' }}"
                >
                    Reviews
                </a>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <div class="p-6 shadow-xl card bg-base-100">
                    <!-- Overview Tab -->
                    <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
                        <h2 class="text-xl font-bold">Course Description</h2>
                        <p class="mt-4">{{ $course['description'] }}</p>

                        <div class="divider"></div>

                        <h3 class="text-lg font-bold">What You'll Learn</h3>
                        <div class="grid grid-cols-1 gap-2 mt-4 md:grid-cols-2">
                            @foreach($course['what_youll_learn'] as $item)
                                <div class="flex items-start gap-2">
                                    <x-icon name="o-check" class="w-5 h-5 mt-0.5 text-success" />
                                    <span>{{ $item }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="divider"></div>

                        <h3 class="text-lg font-bold">Prerequisites</h3>
                        <ul class="mt-4 space-y-2">
                            @foreach($course['prerequisites'] as $item)
                                <li class="flex items-start gap-2">
                                    <x-icon name="o-arrow-right" class="w-5 h-5 mt-0.5 opacity-70" />
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="divider"></div>

                        <h3 class="text-lg font-bold">Skills You'll Gain</h3>
                        <div class="flex flex-wrap gap-2 mt-4">
                            @foreach($course['skills'] as $skill)
                                <div class="badge badge-outline">{{ $skill }}</div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Curriculum Tab -->
                    <div class="{{ $activeTab === 'curriculum' ? 'block' : 'hidden' }}">
                        <h2 class="text-xl font-bold">Course Curriculum</h2>
                        <p class="mt-2 text-base-content/70">{{ $course['lessons'] }} lessons • {{ $course['duration'] }}</p>

                        <div class="mt-6 space-y-4">
                            @foreach($course['sections'] as $index => $section)
                                <div class="border collapse collapse-arrow rounded-box border-base-300">
                                    <input type="checkbox" {{ $index === 0 ? 'checked' : '' }} />
                                    <div class="text-lg font-medium collapse-title">
                                        {{ $section['title'] }}
                                        <div class="text-sm font-normal opacity-70">{{ count($section['lessons']) }} lessons</div>
                                    </div>
                                    <div class="collapse-content">
                                        <ul class="space-y-2">
                                            @foreach($section['lessons'] as $lesson)
                                                <li class="flex items-center justify-between p-2 rounded-lg hover:bg-base-200">
                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="o-play-circle" class="w-5 h-5 opacity-70" />
                                                        <span>{{ $lesson }}</span>
                                                    </div>
                                                    <span class="text-xs opacity-50">Preview</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Instructor Tab -->
                    <div class="{{ $activeTab === 'instructor' ? 'block' : 'hidden' }}">
                        <h2 class="text-xl font-bold">About the Instructor</h2>

                        <div class="flex items-start gap-4 mt-6">
                            <div class="avatar placeholder">
                                <div class="w-20 h-20 rounded-full bg-neutral-focus text-neutral-content">
                                    <span class="text-3xl">{{ substr($course['instructor'], 0, 1) }}</span>
                                </div>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">{{ $course['instructor'] }}</h3>
                                <p class="text-base-content/70">{{ $course['instructor_title'] }}</p>

                                <div class="flex items-center gap-3 mt-2">
                                    <div class="flex items-center gap-1">
                                        <x-icon name="o-star" class="w-4 h-4 text-yellow-500" />
                                        <span>{{ $course['rating'] }} Instructor Rating</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <x-icon name="o-users" class="w-4 h-4 opacity-70" />
                                        <span>{{ number_format($course['students']) }} Students</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <x-icon name="o-users" class="w-4 h-4 opacity-70" />
                                        <span>{{ number_format($course['students']) }} Students</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <x-icon name="o-document-text" class="w-4 h-4 opacity-70" />
                                        <span>{{ $course['lessons'] }} Lessons</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <p>{{ $course['instructor_bio'] }}</p>
                        </div>
                    </div>

                    <!-- Reviews Tab -->
                    <div class="{{ $activeTab === 'reviews' ? 'block' : 'hidden' }}">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-bold">Student Reviews</h2>
                            <button wire:click="openReviewForm" class="btn btn-sm btn-outline">
                                <x-icon name="o-pencil" class="w-4 h-4 mr-1" />
                                Write a Review
                            </button>
                        </div>

                        <div class="p-4 mt-4 rounded-lg bg-base-200">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div class="text-center md:col-span-1">
                                    <div class="text-5xl font-bold">{{ $course['rating'] }}</div>
                                    <div class="flex items-center justify-center mt-2">
                                        @for($i = 1; $i <= 5; $i++)
                                            <x-icon
                                                name="{{ $i <= floor($course['rating']) ? 's-star' : 'o-star' }}"
                                                class="w-5 h-5 {{ $i <= floor($course['rating']) ? 'text-yellow-500 fill-yellow-500' : 'text-yellow-500' }}"
                                            />
                                        @endfor
                                    </div>
                                    <div class="mt-1 text-sm">{{ $course['reviews_count'] }} reviews</div>
                                </div>

                                <div class="md:col-span-3">
                                    <div class="space-y-2">
                                        @php
                                            $ratingCounts = [
                                                5 => rand(60, 80),
                                                4 => rand(15, 30),
                                                3 => rand(5, 10),
                                                2 => rand(1, 5),
                                                1 => rand(0, 2)
                                            ];

                                            // Ensure the total is 100%
                                            $total = array_sum($ratingCounts);
                                            foreach($ratingCounts as $rating => $count) {
                                                $ratingCounts[$rating] = round(($count / $total) * 100);
                                            }
                                        @endphp

                                        @for($i = 5; $i >= 1; $i--)
                                            <div class="flex items-center gap-2">
                                                <div class="w-12 text-sm text-right">{{ $i }} stars</div>
                                                <div class="flex-1 h-2 overflow-hidden rounded-full bg-base-300">
                                                    <div
                                                        class="h-full {{ $i >= 4 ? 'bg-success' : ($i >= 3 ? 'bg-info' : ($i >= 2 ? 'bg-warning' : 'bg-error')) }}"
                                                        style="width: {{ $ratingCounts[$i] }}%"
                                                    ></div>
                                                </div>
                                                <div class="w-8 text-xs text-right">{{ $ratingCounts[$i] }}%</div>
                                            </div>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            @foreach($course['reviews'] as $review)
                                <div class="p-4 rounded-lg bg-base-200">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <div class="font-medium">{{ $review['user'] }}</div>
                                            <div class="flex items-center mt-1">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <x-icon
                                                        name="{{ $i <= $review['rating'] ? 's-star' : 'o-star' }}"
                                                        class="w-4 h-4 {{ $i <= $review['rating'] ? 'text-yellow-500 fill-yellow-500' : 'text-yellow-500' }}"
                                                    />
                                                @endfor
                                            </div>
                                        </div>
                                        <div class="text-xs opacity-70">{{ $this->formatDate($review['date']) }}</div>
                                    </div>
                                    <p class="mt-2">{{ $review['comment'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Similar Courses -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Similar Courses</h3>

                        <div class="mt-4 space-y-4">
                            @foreach(array_filter(range(1, 3), fn($id) => $id != $course['id']) as $similarId)
                                @php
                                    $similarCourse = $this->getCourseData($similarId);
                                @endphp
                                <div class="flex gap-3">
                                    <img
                                        src="{{ asset('images/' . $similarCourse['thumbnail']) }}"
                                        alt="{{ $similarCourse['title'] }}"
                                        class="object-cover w-16 rounded-lg h-14"
                                        onerror="this.src='https://placehold.co/600x400?text=Course+Image'"
                                    />
                                    <div class="flex-1">
                                        <h4 class="font-medium">{{ $similarCourse['title'] }}</h4>
                                        <div class="flex items-center gap-1 mt-1">
                                            <x-icon name="o-star" class="w-3 h-3 text-yellow-500 fill-yellow-500" />
                                            <span class="text-xs">{{ $similarCourse['rating'] }} ({{ $similarCourse['reviews_count'] }})</span>
                                        </div>
                                        <div class="mt-1 text-sm font-medium">
                                            @if($similarCourse['sale_price'])
                                                <span>${{ $similarCourse['sale_price'] }}</span>
                                                <span class="ml-1 text-xs line-through opacity-50">${{ $similarCourse['price'] }}</span>
                                            @else
                                                <span>${{ $similarCourse['price'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrollment Modal -->
    <div class="modal {{ $showEnrollModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <button wire:click="closeEnrollmentModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            <h3 class="text-lg font-bold">Enroll in {{ $course['title'] }}</h3>

            <div class="p-4 mt-4 rounded-lg bg-base-200">
                <div class="flex items-center justify-between">
                    <span class="font-medium">Course Fee</span>
                    @if($course['sale_price'])
                        <div>
                            <span class="text-lg font-bold">${{ $course['sale_price'] }}</span>
                            <span class="ml-1 text-sm line-through opacity-50">${{ $course['price'] }}</span>
                        </div>
                    @else
                        <span class="text-lg font-bold">${{ $course['price'] }}</span>
                    @endif
                </div>

                <div class="my-2 divider"></div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span>Course Duration</span>
                        <span>{{ $course['duration'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Total Lessons</span>
                        <span>{{ $course['lessons'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Access</span>
                        <span>Lifetime</span>
                    </div>
                </div>
            </div>

            <div class="modal-action">
                <button wire:click="closeEnrollmentModal" class="btn">Cancel</button>
                <button wire:click="enrollNow" class="btn btn-primary">
                    Confirm Enrollment
                </button>
            </div>
        </div>
    </div>

    <!-- Review Form Modal -->
    <div class="modal {{ $showReviewForm ? 'modal-open' : '' }}">
        <div class="modal-box">
            <button wire:click="closeReviewForm" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            <h3 class="text-lg font-bold">Write a Review</h3>

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
                <button wire:click="closeReviewForm" class="btn">Cancel</button>
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
</div>
