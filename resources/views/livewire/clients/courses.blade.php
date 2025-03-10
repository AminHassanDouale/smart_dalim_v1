<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Course;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $clientProfile;

    // Filters
    public $search = '';
    public $selectedCategory = '';
    public $selectedLevel = '';
    public $sortBy = 'popularity'; // Default sort

    // Modal states
    public $showCourseDetailsModal = false;
    public $selectedCourse = null;

    // Wishlisting
    public $wishlistedCourses = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategory' => ['except' => ''],
        'selectedLevel' => ['except' => ''],
        'sortBy' => ['except' => 'popularity'],
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->clientProfile = $this->user->clientProfile;

        // In a real app, fetch wishlisted courses from database
        $this->wishlistedCourses = [3, 5]; // Example IDs
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSelectedCategory()
    {
        $this->resetPage();
    }

    public function updatingSelectedLevel()
    {
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function toggleWishlist($courseId)
    {
        // In a real application, you'd update the database
        if (in_array($courseId, $this->wishlistedCourses)) {
            $this->wishlistedCourses = array_diff($this->wishlistedCourses, [$courseId]);

            $this->toast(
                type: 'info',
                title: 'Removed from wishlist',
                position: 'toast-bottom toast-end',
                icon: 'o-heart',
                css: 'alert-info',
                timeout: 2000
            );
        } else {
            $this->wishlistedCourses[] = $courseId;

            $this->toast(
                type: 'success',
                title: 'Added to wishlist',
                position: 'toast-bottom toast-end',
                icon: 'o-heart',
                css: 'alert-success',
                timeout: 2000
            );
        }
    }

    public function showCourseDetails($courseId)
    {
        // Find the course in our mock data
        $this->selectedCourse = collect($this->getAllCourses())->firstWhere('id', $courseId);
        $this->showCourseDetailsModal = true;
    }

    public function closeCourseDetailsModal()
    {
        $this->showCourseDetailsModal = false;
        $this->selectedCourse = null;
    }

    public function enrollInCourse($courseId)
    {
        // In a real application, you'd create an enrollment record

        $this->toast(
            type: 'success',
            title: 'Successfully enrolled!',
            description: 'You have been enrolled in this course.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000,
            redirectTo: route('clients.enrollments')
        );

        return $this->redirect(route('clients.enrollments'), navigate: true);
    }

    // This would normally query your database
    public function getCoursesProperty()
    {
        $allCourses = collect($this->getAllCourses());

        // Apply filters
        if ($this->search) {
            $allCourses = $allCourses->filter(function($course) {
                return str_contains(strtolower($course['title']), strtolower($this->search)) ||
                       str_contains(strtolower($course['description']), strtolower($this->search));
            });
        }

        if ($this->selectedCategory) {
            $allCourses = $allCourses->filter(function($course) {
                return $course['category'] === $this->selectedCategory;
            });
        }

        if ($this->selectedLevel) {
            $allCourses = $allCourses->filter(function($course) {
                return $course['level'] === $this->selectedLevel;
            });
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'price_low':
                $allCourses = $allCourses->sortBy('price');
                break;
            case 'price_high':
                $allCourses = $allCourses->sortByDesc('price');
                break;
            case 'newest':
                $allCourses = $allCourses->sortByDesc('created_at');
                break;
            case 'highest_rated':
                $allCourses = $allCourses->sortByDesc('rating');
                break;
            default: // popularity
                $allCourses = $allCourses->sortByDesc('students');
                break;
        }

        return $allCourses->values()->all();
    }

    public function getCategoriesProperty()
    {
        // This would come from your database in a real app
        return [
            'development' => 'Web Development',
            'design' => 'Design',
            'marketing' => 'Digital Marketing',
            'business' => 'Business',
            'mobile' => 'Mobile Development',
            'data' => 'Data Science'
        ];
    }

    public function getLevelsProperty()
    {
        return [
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
            'all-levels' => 'All Levels'
        ];
    }

    // Mock data for courses (in a real app, you would fetch from database)
    private function getAllCourses()
    {
        return [
            [
                'id' => 1,
                'title' => 'Advanced Laravel Development',
                'slug' => 'advanced-laravel-development',
                'description' => 'Master Laravel framework with advanced techniques and best practices.',
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
                'created_at' => '2024-02-15',
                'is_featured' => true,
                'is_bestseller' => true,
                'skills' => ['Laravel', 'PHP', 'MySQL', 'API Development', 'TDD'],
                'what_youll_learn' => [
                    'Build complex Laravel applications using best practices',
                    'Implement authentication and authorization',
                    'Develop RESTful APIs with Laravel',
                    'Master Eloquent ORM and database relationships',
                    'Implement testing strategies for Laravel apps'
                ]
            ],
            [
                'id' => 2,
                'title' => 'React and Redux Masterclass',
                'slug' => 'react-redux-masterclass',
                'description' => 'Comprehensive guide to building scalable applications with React and Redux.',
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
                'created_at' => '2024-01-10',
                'is_featured' => false,
                'is_bestseller' => true,
                'skills' => ['React', 'Redux', 'JavaScript', 'Frontend Development'],
                'what_youll_learn' => [
                    'Build complex UIs with React components',
                    'Manage application state with Redux',
                    'Implement routing and navigation',
                    'Optimize React applications for performance',
                    'Test React components effectively'
                ]
            ],
            [
                'id' => 3,
                'title' => 'UI/UX Design Fundamentals',
                'slug' => 'ui-ux-design-fundamentals',
                'description' => 'Learn the principles of effective UI/UX design and create stunning user interfaces.',
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
                'created_at' => '2023-11-25',
                'is_featured' => true,
                'is_bestseller' => true,
                'skills' => ['UI Design', 'UX Research', 'Figma', 'Design Principles'],
                'what_youll_learn' => [
                    'Create user-centered designs',
                    'Conduct effective user research',
                    'Design intuitive user interfaces',
                    'Create wireframes and prototypes',
                    'Test and iterate on designs'
                ]
            ],
            [
                'id' => 4,
                'title' => 'Digital Marketing Strategy',
                'slug' => 'digital-marketing-strategy',
                'description' => 'Develop comprehensive digital marketing strategies to grow your business online.',
                'short_description' => 'Master digital marketing strategies.',
                'price' => 119.99,
                'sale_price' => null,
                'thumbnail' => 'course-marketing.jpg',
                'category' => 'marketing',
                'level' => 'intermediate',
                'duration' => '7 weeks',
                'lessons' => 35,
                'students' => 1876,
                'rating' => 4.6,
                'reviews_count' => 395,
                'instructor' => 'David Wilson',
                'instructor_title' => 'Digital Marketing Specialist',
                'created_at' => '2024-01-05',
                'is_featured' => false,
                'is_bestseller' => false,
                'skills' => ['SEO', 'Content Marketing', 'Social Media', 'Email Marketing'],
                'what_youll_learn' => [
                    'Create effective digital marketing campaigns',
                    'Optimize content for search engines',
                    'Develop social media marketing strategies',
                    'Analyze marketing data and make improvements',
                    'Build email marketing campaigns'
                ]
            ],
            [
                'id' => 5,
                'title' => 'Flutter App Development',
                'slug' => 'flutter-app-development',
                'description' => 'Build beautiful cross-platform mobile applications with Flutter and Dart.',
                'short_description' => 'Create cross-platform mobile apps.',
                'price' => 139.99,
                'sale_price' => 99.99,
                'thumbnail' => 'course-flutter.jpg',
                'category' => 'mobile',
                'level' => 'intermediate',
                'duration' => '9 weeks',
                'lessons' => 46,
                'students' => 2205,
                'rating' => 4.8,
                'reviews_count' => 537,
                'instructor' => 'Alex Johnson',
                'instructor_title' => 'Mobile Developer',
                'created_at' => '2023-12-08',
                'is_featured' => true,
                'is_bestseller' => false,
                'skills' => ['Flutter', 'Dart', 'Mobile Development', 'UI Design'],
                'what_youll_learn' => [
                    'Build beautiful user interfaces with Flutter',
                    'Implement state management in Flutter apps',
                    'Connect to APIs and handle data',
                    'Deploy apps to iOS and Android',
                    'Implement authentication and user management'
                ]
            ],
            [
                'id' => 6,
                'title' => 'Data Science with Python',
                'slug' => 'data-science-python',
                'description' => 'Learn data analysis, visualization, and machine learning with Python.',
                'short_description' => 'Analyze and visualize data with Python.',
                'price' => 159.99,
                'sale_price' => 129.99,
                'thumbnail' => 'course-datascience.jpg',
                'category' => 'data',
                'level' => 'all-levels',
                'duration' => '12 weeks',
                'lessons' => 58,
                'students' => 1975,
                'rating' => 4.7,
                'reviews_count' => 412,
                'instructor' => 'Lisa Chen',
                'instructor_title' => 'Data Scientist',
                'created_at' => '2023-10-15',
                'is_featured' => false,
                'is_bestseller' => true,
                'skills' => ['Python', 'Pandas', 'NumPy', 'Matplotlib', 'Machine Learning'],
                'what_youll_learn' => [
                    'Analyze data with Python libraries',
                    'Create compelling data visualizations',
                    'Build machine learning models',
                    'Clean and preprocess data effectively',
                    'Extract insights from large datasets'
                ]
            ],
            [
                'id' => 7,
                'title' => 'Business Analytics Fundamentals',
                'slug' => 'business-analytics-fundamentals',
                'description' => 'Learn how to analyze business data and make data-driven decisions.',
                'short_description' => 'Make better business decisions with data.',
                'price' => 109.99,
                'sale_price' => null,
                'thumbnail' => 'course-business.jpg',
                'category' => 'business',
                'level' => 'beginner',
                'duration' => '5 weeks',
                'lessons' => 24,
                'students' => 1250,
                'rating' => 4.5,
                'reviews_count' => 285,
                'instructor' => 'Robert Taylor',
                'instructor_title' => 'Business Analyst',
                'created_at' => '2024-02-01',
                'is_featured' => false,
                'is_bestseller' => false,
                'skills' => ['Data Analysis', 'Excel', 'Business Intelligence', 'Reporting'],
                'what_youll_learn' => [
                    'Analyze business data effectively',
                    'Create insightful reports and dashboards',
                    'Make data-driven business decisions',
                    'Identify key performance indicators',
                    'Present data findings to stakeholders'
                ]
            ],
            [
                'id' => 8,
                'title' => 'Graphic Design Masterclass',
                'slug' => 'graphic-design-masterclass',
                'description' => 'Learn graphic design principles and tools to create stunning visuals.',
                'short_description' => 'Create professional graphic designs.',
                'price' => 129.99,
                'sale_price' => 99.99,
                'thumbnail' => 'course-graphicdesign.jpg',
                'category' => 'design',
                'level' => 'all-levels',
                'duration' => '8 weeks',
                'lessons' => 40,
                'students' => 2780,
                'rating' => 4.8,
                'reviews_count' => 623,
                'instructor' => 'Jessica Park',
                'instructor_title' => 'Senior Graphic Designer',
                'created_at' => '2023-09-20',
                'is_featured' => true,
                'is_bestseller' => true,
                'skills' => ['Adobe Photoshop', 'Adobe Illustrator', 'Typography', 'Color Theory'],
                'what_youll_learn' => [
                    'Master essential graphic design principles',
                    'Create logos, branding, and marketing materials',
                    'Design for print and digital media',
                    'Work with typography effectively',
                    'Build a professional design portfolio'
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
        $action = null,
        $redirectTo = null
    ) {
        $actionJson = $action ? json_encode($action) : 'null';
        $redirectPath = $redirectTo ? $redirectTo : '';

        $this->js("
            Toaster.{$type}('{$title}', {
                description: '{$description}',
                position: '{$position}',
                icon: '{$icon}',
                css: '{$css}',
                timeout: {$timeout},
                action: {$actionJson},
                redirectTo: '{$redirectPath}'
            });
        ");
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h1 class="text-3xl font-bold">Explore Courses</h1>
                    <p class="mt-1 text-base-content/70">Discover our curated selection of professional courses</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('clients.enrollments') }}" class="btn btn-outline">
                        <x-icon name="o-academic-cap" class="w-4 h-4 mr-2" />
                        My Enrollments
                    </a>
                    <a href="{{ route('clients.wishlist') }}" class="btn btn-outline">
                        <x-icon name="o-heart" class="w-4 h-4 mr-2" />
                        Wishlist
                    </a>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="p-6 mb-8 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search courses..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- Category Filter -->
                <div>
                    <select wire:model.live="selectedCategory" class="w-full select select-bordered">
                        <option value="">All Categories</option>
                        @foreach($this->categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Level Filter -->
                <div>
                    <select wire:model.live="selectedLevel" class="w-full select select-bordered">
                        <option value="">All Levels</option>
                        @foreach($this->levels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <select wire:model.live="sortBy" class="w-full select select-bordered">
                        <option value="popularity">Most Popular</option>
                        <option value="newest">Newest</option>
                        <option value="highest_rated">Highest Rated</option>
                        <option value="price_low">Price: Low to High</option>
                        <option value="price_high">Price: High to Low</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Featured Courses Carousel (if any) -->
        @if(collect($this->courses)->contains('is_featured', true))
            <div class="mb-12">
                <h2 class="mb-6 text-2xl font-bold">Featured Courses</h2>

                <div class="p-1 carousel carousel-center rounded-box">
                    @foreach(collect($this->courses)->where('is_featured', true)->take(4) as $course)
                        <div class="w-full carousel-item md:w-1/2 lg:w-1/3">
                            <div class="m-2 transition-all shadow-lg card bg-base-100 hover:shadow-xl">
                                <figure class="px-4 pt-4">
                                    <img
                                        src="{{ asset('images/' . $course['thumbnail']) }}"
                                        alt="{{ $course['title'] }}"
                                        class="object-cover w-full rounded-xl h-44"
                                        onerror="this.src='https://placehold.co/600x400?text=Course+Image'"
                                    />
                                    @if($course['sale_price'])
                                        <div class="absolute top-6 right-6">
                                            <div class="px-2 py-1 text-xs text-white rounded-md bg-primary">
                                                SALE
                                            </div>
                                        </div>
                                    @endif
                                </figure>
                                <div class="card-body">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="badge badge-outline">{{ $this->categories[$course['category']] ?? $course['category'] }}</div>
                                        <div class="flex items-center gap-1">
                                            <x-icon name="o-star" class="w-4 h-4 text-yellow-500 fill-yellow-500" />
                                            <span class="text-sm">{{ $course['rating'] }} ({{ $course['reviews_count'] }})</span>
                                        </div>
                                    </div>
                                    <h3 class="card-title">{{ $course['title'] }}</h3>
                                    <p class="text-sm text-base-content/70">{{ $course['short_description'] }}</p>

                                    <div class="flex items-center justify-between mt-2">
                                        <div class="flex items-center gap-1">
                                            <div class="avatar placeholder">
                                                <div class="w-6 h-6 rounded-full bg-neutral-focus text-neutral-content">
                                                    <span>{{ substr($course['instructor'], 0, 1) }}</span>
                                                </div>
                                            </div>
                                            <span class="text-sm">{{ $course['instructor'] }}</span>
                                        </div>
                                        <div class="text-sm">{{ $course['lessons'] }} lessons</div>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between mt-4">
                                        <div class="font-bold">
                                            @if($course['sale_price'])
                                                <span class="text-lg">${{ $course['sale_price'] }}</span>
                                                <span class="ml-1 text-sm line-through opacity-50">${{ $course['price'] }}</span>
                                            @else
                                                <span class="text-lg">${{ $course['price'] }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button
                                                wire:click="toggleWishlist({{ $course['id'] }})"
                                                class="btn btn-circle btn-sm btn-ghost"
                                            >
                                                @if(in_array($course['id'], $wishlistedCourses))
                                                    <x-icon name="s-heart" class="w-5 h-5 text-red-500" />
                                                @else
                                                    <x-icon name="o-heart" class="w-5 h-5" />
                                                @endif
                                            </button>
                                            <button
                                                wire:click="showCourseDetails({{ $course['id'] }})"
                                                class="btn btn-primary btn-sm"
                                            >
                                                View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Course Grid -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold">All Courses</h2>
                <div class="flex items-center text-sm">
                    <span>Showing {{ count($this->courses) }} courses</span>
                </div>
            </div>

            @if(count($this->courses) > 0)
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->courses as $course)
                        <div class="transition-all shadow-lg card bg-base-100 hover:shadow-xl">
                            <figure class="px-4 pt-4">
                                <img
                                    src="{{ asset('images/' . $course['thumbnail']) }}"
                                    alt="{{ $course['title'] }}"
                                    class="object-cover w-full rounded-xl h-44"
                                    onerror="this.src='https://placehold.co/600x400?text=Course+Image'"
                                />
                                @if($course['sale_price'])
                                    <div class="absolute top-6 right-6">
                                        <div class="px-2 py-1 text-xs text-white bg-red-500 rounded-md">
                                            {{ round((($course['price'] - $course['sale_price']) / $course['price']) * 100) }}% OFF
                                        </div>
                                    </div>
                                @endif
                            </figure>
                            <div class="card-body">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="badge badge-outline">{{ $this->categories[$course['category']] ?? $course['category'] }}</div>
                                    <div class="badge {{ $this->levels[$course['level']] ? 'badge-primary badge-outline' : '' }}">
                                        {{ $this->levels[$course['level']] ?? ucfirst($course['level']) }}
                                    </div>
                                </div>
                                <h3 class="card-title">{{ $course['title'] }}</h3>
                                <p class="text-sm line-clamp-2 text-base-content/70">{{ $course['short_description'] }}</p>

                                <div class="flex items-center gap-3 mt-3">
                                    <div class="flex items-center gap-1">
                                        <x-icon name="o-clock" class="w-4 h-4 text-base-content/70" />
                                        <span class="text-sm">{{ $course['duration'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <x-icon name="o-academic-cap" class="w-4 h-4 text-base-content/70" />
                                        <span class="text-sm">{{ $course['lessons'] }} lessons</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <x-icon name="o-star" class="w-4 h-4 text-yellow-500 fill-yellow-500" />
                                        <span class="text-sm">{{ $course['rating'] }}</span>
                                    </div>
                                </div>

                                <div class="my-2 divider"></div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="avatar placeholder">
                                            <div class="w-8 h-8 rounded-full bg-neutral-focus text-neutral-content">
                                                <span>{{ substr($course['instructor'], 0, 1) }}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium">{{ $course['instructor'] }}</div>
                                            <div class="text-xs text-base-content/70">{{ $course['instructor_title'] }}</div>
                                        </div>
                                    </div>
                                    <div class="font-bold">
                                        @if($course['sale_price'])
                                            <span class="text-lg">${{ $course['sale_price'] }}</span>
                                            <span class="ml-1 text-sm line-through opacity-50">${{ $course['price'] }}</span>
                                        @else
                                            <span class="text-lg">${{ $course['price'] }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="justify-between mt-4 card-actions">
                                    <button
                                        wire:click="toggleWishlist({{ $course['id'] }})"
                                        class="btn btn-outline btn-sm"
                                    >
                                        @if(in_array($course['id'], $wishlistedCourses))
                                        <x-icon name="s-heart" class="w-4 h-4 mr-1 text-red-500" />
                                        Wishlisted
                                    @else
                                        <x-icon name="o-heart" class="w-4 h-4 mr-1" />
                                        Wishlist
                                    @endif
                                </button>
                                <button
                                    wire:click="showCourseDetails({{ $course['id'] }})"
                                    class="btn btn-primary btn-sm"
                                >
                                    View Details
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 text-center shadow-lg rounded-xl bg-base-100">
                <div class="flex flex-col items-center justify-center">
                    <x-icon name="o-magnifying-glass" class="w-16 h-16 mb-4 text-base-content/30" />
                    <h3 class="text-xl font-bold">No courses found</h3>
                    <p class="mt-2 text-base-content/70">Try adjusting your search or filters</p>
                    <button wire:click="$set('search', ''); $set('selectedCategory', ''); $set('selectedLevel', '');" class="mt-4 btn btn-outline">
                        Clear Filters
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- Best Seller Courses (if any) -->
    @if(collect($this->courses)->contains('is_bestseller', true))
        <div class="mb-12">
            <h2 class="mb-6 text-2xl font-bold">Bestseller Courses</h2>

            <div class="p-1 carousel carousel-center rounded-box">
                @foreach(collect($this->courses)->where('is_bestseller', true)->take(4) as $course)
                    <div class="w-full carousel-item md:w-1/2 lg:w-1/3">
                        <div class="relative m-2 transition-all shadow-lg card bg-base-100 hover:shadow-xl">
                            <div class="absolute top-4 left-4 badge badge-secondary">Bestseller</div>
                            <figure class="px-4 pt-4">
                                <img
                                    src="{{ asset('images/' . $course['thumbnail']) }}"
                                    alt="{{ $course['title'] }}"
                                    class="object-cover w-full rounded-xl h-44"
                                    onerror="this.src='https://placehold.co/600x400?text=Course+Image'"
                                />
                            </figure>
                            <div class="card-body">
                                <h3 class="card-title">{{ $course['title'] }}</h3>
                                <p class="text-sm text-base-content/70">{{ $course['short_description'] }}</p>

                                <div class="flex items-center gap-2 mt-2">
                                    <div class="avatar placeholder">
                                        <div class="w-6 h-6 rounded-full bg-neutral-focus text-neutral-content">
                                            <span>{{ substr($course['instructor'], 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <span class="text-sm">{{ $course['instructor'] }}</span>
                                    <div class="flex items-center gap-1 ml-auto">
                                        <x-icon name="o-users" class="w-4 h-4 text-base-content/70" />
                                        <span class="text-sm">{{ number_format($course['students']) }}</span>
                                    </div>
                                </div>

                                <div class="justify-end mt-4 card-actions">
                                    <button
                                        wire:click="showCourseDetails({{ $course['id'] }})"
                                        class="btn btn-primary btn-sm"
                                    >
                                        View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Why Choose Our Courses Section -->
    <div class="p-8 mb-12 shadow-xl rounded-xl bg-base-200">
        <h2 class="mb-6 text-2xl font-bold text-center">Why Choose Our Courses</h2>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
            <div class="flex flex-col items-center p-4 text-center">
                <div class="p-3 mb-4 rounded-full bg-primary bg-opacity-20">
                    <x-icon name="o-academic-cap" class="w-8 h-8 text-primary" />
                </div>
                <h3 class="mb-2 text-lg font-semibold">Expert Instructors</h3>
                <p class="text-sm text-base-content/70">Learn from industry professionals with years of experience</p>
            </div>

            <div class="flex flex-col items-center p-4 text-center">
                <div class="p-3 mb-4 rounded-full bg-secondary bg-opacity-20">
                    <x-icon name="o-clock" class="w-8 h-8 text-secondary" />
                </div>
                <h3 class="mb-2 text-lg font-semibold">Flexible Learning</h3>
                <p class="text-sm text-base-content/70">Study at your own pace, anywhere and anytime</p>
            </div>

            <div class="flex flex-col items-center p-4 text-center">
                <div class="p-3 mb-4 rounded-full bg-accent bg-opacity-20">
                    <x-icon name="o-document-text" class="w-8 h-8 text-accent" />
                </div>
                <h3 class="mb-2 text-lg font-semibold">Updated Content</h3>
                <p class="text-sm text-base-content/70">Access the latest materials and industry best practices</p>
            </div>

            <div class="flex flex-col items-center p-4 text-center">
                <div class="p-3 mb-4 rounded-full bg-success bg-opacity-20">
                    <x-icon name="o-check-badge" class="w-8 h-8 text-success" />
                </div>
                <h3 class="mb-2 text-lg font-semibold">Certification</h3>
                <p class="text-sm text-base-content/70">Receive a certificate upon successful course completion</p>
            </div>
        </div>
    </div>

    <!-- Testimonials Section -->
    <div class="mb-12">
        <h2 class="mb-6 text-2xl font-bold text-center">What Our Students Say</h2>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div class="p-6 shadow-lg rounded-xl bg-base-100">
                <div class="flex items-center gap-4 mb-4">
                    <div class="avatar placeholder">
                        <div class="w-12 rounded-full bg-neutral-focus text-neutral-content">
                            <span>JD</span>
                        </div>
                    </div>
                    <div>
                        <div class="font-medium">John Doe</div>
                        <div class="text-sm text-base-content/70">Web Developer</div>
                    </div>
                </div>
                <p class="italic text-base-content/80">"The Laravel course was exactly what I needed to advance my career. The instructor was knowledgeable and the content was comprehensive."</p>
                <div class="flex mt-4">
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                </div>
            </div>

            <div class="p-6 shadow-lg rounded-xl bg-base-100">
                <div class="flex items-center gap-4 mb-4">
                    <div class="avatar placeholder">
                        <div class="w-12 rounded-full bg-neutral-focus text-neutral-content">
                            <span>JS</span>
                        </div>
                    </div>
                    <div>
                        <div class="font-medium">Jane Smith</div>
                        <div class="text-sm text-base-content/70">UX Designer</div>
                    </div>
                </div>
                <p class="italic text-base-content/80">"The UI/UX Design course helped me transition to a new career. The practical projects were challenging and relevant to real-world scenarios."</p>
                <div class="flex mt-4">
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="o-star" class="w-5 h-5 text-yellow-500" />
                </div>
            </div>

            <div class="p-6 shadow-lg rounded-xl bg-base-100">
                <div class="flex items-center gap-4 mb-4">
                    <div class="avatar placeholder">
                        <div class="w-12 rounded-full bg-neutral-focus text-neutral-content">
                            <span>RT</span>
                        </div>
                    </div>
                    <div>
                        <div class="font-medium">Robert Thomas</div>
                        <div class="text-sm text-base-content/70">Marketing Manager</div>
                    </div>
                </div>
                <p class="italic text-base-content/80">"The Digital Marketing Strategy course exceeded my expectations. I was able to immediately apply what I learned to improve our company's online presence."</p>
                <div class="flex mt-4">
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                    <x-icon name="s-star" class="w-5 h-5 text-yellow-500" />
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Section -->
    <div class="p-8 mb-12 shadow-xl rounded-xl bg-base-100">
        <h2 class="mb-6 text-2xl font-bold text-center">Frequently Asked Questions</h2>

        <div class="w-full join join-vertical">
            <div class="border collapse collapse-arrow join-item border-base-300">
                <input type="radio" name="faq-accordion" checked />
                <div class="text-lg font-medium collapse-title">
                    How long do I have access to a course?
                </div>
                <div class="collapse-content">
                    <p>Once you enroll in a course, you have lifetime access to it. You can revisit the material whenever you need a refresher.</p>
                </div>
            </div>

            <div class="border collapse collapse-arrow join-item border-base-300">
                <input type="radio" name="faq-accordion" />
                <div class="text-lg font-medium collapse-title">
                    What payment methods do you accept?
                </div>
                <div class="collapse-content">
                    <p>We accept all major credit cards, PayPal, and bank transfers. If you need an alternative payment method, please contact our support team.</p>
                </div>
            </div>

            <div class="border collapse collapse-arrow join-item border-base-300">
                <input type="radio" name="faq-accordion" />
                <div class="text-lg font-medium collapse-title">
                    Can I get a refund if I'm not satisfied?
                </div>
                <div class="collapse-content">
                    <p>Yes, we offer a 30-day money-back guarantee. If you're not completely satisfied with your purchase, you can request a full refund within 30 days of enrollment.</p>
                </div>
            </div>

            <div class="border collapse collapse-arrow join-item border-base-300">
                <input type="radio" name="faq-accordion" />
                <div class="text-lg font-medium collapse-title">
                    Do I receive a certificate upon completion?
                </div>
                <div class="collapse-content">
                    <p>Yes, you will receive a certificate of completion for each course you successfully finish. These certificates can be added to your portfolio or LinkedIn profile.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Course Details Modal -->
<div class="modal {{ $showCourseDetailsModal ? 'modal-open' : '' }}">
    <div class="max-w-4xl modal-box">
        <button wire:click="closeCourseDetailsModal" class="absolute btn btn-sm btn-circle btn-ghost right-2 top-2">✕</button>

        @if($selectedCourse)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="md:col-span-2">
                    <h3 class="text-2xl font-bold">{{ $selectedCourse['title'] }}</h3>
                    <p class="mt-2 text-base-content/70">{{ $selectedCourse['description'] }}</p>

                    <div class="flex flex-wrap items-center gap-4 mt-4">
                        <div class="badge badge-outline">{{ $this->categories[$selectedCourse['category']] ?? ucfirst($selectedCourse['category']) }}</div>
                        <div class="badge badge-primary badge-outline">{{ $this->levels[$selectedCourse['level']] ?? ucfirst($selectedCourse['level']) }}</div>
                        <div class="flex items-center gap-1">
                            <x-icon name="o-clock" class="w-4 h-4" />
                            <span>{{ $selectedCourse['duration'] }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <x-icon name="o-users" class="w-4 h-4" />
                            <span>{{ number_format($selectedCourse['students']) }} students</span>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div>
                        <h4 class="mb-3 text-lg font-semibold">What You'll Learn</h4>
                        <ul class="space-y-2">
                            @foreach($selectedCourse['what_youll_learn'] as $item)
                                <li class="flex items-start gap-2">
                                    <x-icon name="o-check" class="w-5 h-5 mt-0.5 text-success" />
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="mt-6">
                        <h4 class="mb-3 text-lg font-semibold">Skills You'll Gain</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($selectedCourse['skills'] as $skill)
                                <div class="badge badge-outline">{{ $skill }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div>
                    <div class="shadow-lg card bg-base-200">
                        <figure class="px-5 pt-5">
                            <img
                                src="{{ asset('images/' . $selectedCourse['thumbnail']) }}"
                                alt="{{ $selectedCourse['title'] }}"
                                class="object-cover w-full rounded-xl"
                                onerror="this.src='https://placehold.co/600x400?text=Course+Image'"
                            />
                        </figure>
                        <div class="card-body">
                            <div class="text-center">
                                @if($selectedCourse['sale_price'])
                                    <div class="text-3xl font-bold">${{ $selectedCourse['sale_price'] }}</div>
                                    <div class="line-through text-base-content/50">${{ $selectedCourse['price'] }}</div>
                                    <div class="mt-1 text-sm text-success">
                                        {{ round((($selectedCourse['price'] - $selectedCourse['sale_price']) / $selectedCourse['price']) * 100) }}% discount!
                                    </div>
                                @else
                                    <div class="text-3xl font-bold">${{ $selectedCourse['price'] }}</div>
                                @endif
                            </div>

                            <div class="mt-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span>Lessons</span>
                                    <span class="font-medium">{{ $selectedCourse['lessons'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Duration</span>
                                    <span class="font-medium">{{ $selectedCourse['duration'] }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Level</span>
                                    <span class="font-medium">{{ $this->levels[$selectedCourse['level']] ?? ucfirst($selectedCourse['level']) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Released</span>
                                    <span class="font-medium">{{ \Carbon\Carbon::parse($selectedCourse['created_at'])->format('M Y') }}</span>
                                </div>
                            </div>

                            <div class="justify-center mt-6 space-y-2 card-actions">
                                <button
                                    wire:click="enrollInCourse({{ $selectedCourse['id'] }})"
                                    class="w-full btn btn-primary"
                                >
                                    Enroll Now
                                </button>
                                <button
                                    wire:click="toggleWishlist({{ $selectedCourse['id'] }})"
                                    class="w-full btn btn-outline"
                                >
                                    @if(in_array($selectedCourse['id'], $wishlistedCourses))
                                        <x-icon name="s-heart" class="w-4 h-4 mr-2 text-red-500" />
                                        Remove from Wishlist
                                    @else
                                        <x-icon name="o-heart" class="w-4 h-4 mr-2" />
                                        Add to Wishlist
                                    @endif
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 mt-4 shadow-lg rounded-xl bg-base-100">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="avatar placeholder">
                                <div class="w-12 h-12 rounded-full bg-neutral-focus text-neutral-content">
                                    <span>{{ substr($selectedCourse['instructor'], 0, 1) }}</span>
                                </div>
                            </div>
                            <div>
                                <div class="font-medium">{{ $selectedCourse['instructor'] }}</div>
                                <div class="text-sm text-base-content/70">{{ $selectedCourse['instructor_title'] }}</div>
                            </div>
                        </div>
                        <a href="#" class="w-full btn btn-sm btn-outline">View Instructor Profile</a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
</div>
