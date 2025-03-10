<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $clientProfile;

    // Wishlisted courses
    public $wishlistedCourses = [];

    public function mount()
    {
        $this->user = Auth::user();
        $this->clientProfile = $this->user->clientProfile;

        // In a real app, fetch wishlisted courses from database
        // For now, using mock data
        $this->wishlistedCourses = [3, 5]; // Example IDs
    }

    public function removeFromWishlist($courseId)
    {
        // In a real application, you'd update the database
        $this->wishlistedCourses = array_diff($this->wishlistedCourses, [$courseId]);

        $this->toast(
            type: 'info',
            title: 'Removed from wishlist',
            position: 'toast-bottom toast-end',
            icon: 'o-heart',
            css: 'alert-info',
            timeout: 2000
        );
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

    // Get wishlisted courses property
    public function getWishlistedCoursesDataProperty()
    {
        return collect($this->getAllCourses())
            ->filter(function($course) {
                return in_array($course['id'], $this->wishlistedCourses);
            })
            ->values()
            ->all();
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
                'created_at' => '2024-02-15'
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
                'created_at' => '2024-01-10'
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
                'created_at' => '2023-11-25'
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
                'created_at' => '2024-01-05'
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
                'created_at' => '2023-12-08'
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
                'created_at' => '2023-10-15'
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
                'created_at' => '2024-02-01'
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
                'created_at' => '2023-09-20'
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
                    <h1 class="text-3xl font-bold">My Wishlist</h1>
                    <p class="mt-1 text-base-content/70">Courses you've saved for later</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('clients.courses') }}" class="btn btn-outline">
                        <x-icon name="o-book-open" class="w-4 h-4 mr-2" />
                        Browse Courses
                    </a>
                    <a href="{{ route('clients.enrollments') }}" class="btn btn-outline">
                        <x-icon name="o-academic-cap" class="w-4 h-4 mr-2" />
                        My Enrollments
                    </a>
                </div>
            </div>
        </div>

        <!-- Wishlist Content -->
        @if(count($this->wishlistedCoursesData) > 0)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach($this->wishlistedCoursesData as $course)
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

                            <div class="divider my-2"></div>

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
                                    wire:click="removeFromWishlist({{ $course['id'] }})"
                                    class="btn btn-outline btn-sm"
                                >
                                    <x-icon name="o-trash" class="w-4 h-4 mr-1" />
                                    Remove
                                </button>
                                <button
                                    wire:click="enrollInCourse({{ $course['id'] }})"
                                    class="btn btn-primary btn-sm"
                                >
                                    Enroll Now
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-12 text-center shadow-lg rounded-xl bg-base-100">
                <div class="flex flex-col items-center justify-center">
                    <x-icon name="o-heart" class="w-16 h-16 mb-4 text-base-content/30" />
                    <h3 class="text-xl font-bold">Your wishlist is empty</h3>
                    <p class="mt-2 text-base-content/70">Save courses you're interested in to view them later</p>
                    <a href="{{ route('clients.courses') }}" class="mt-4 btn btn-primary">
                        Browse Courses
                    </a>
                </div>
            </div>
        @endif

        <!-- Recommended Courses Section (Optional - for an empty wishlist) -->
        @if(count($this->wishlistedCoursesData) == 0)
            <div class="mt-12">
                <h2 class="mb-6 text-2xl font-bold">Recommended Courses</h2>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach(collect($this->getAllCourses())->sortByDesc('students')->take(3) as $course)
                        <div class="transition-all shadow-lg card bg-base-100 hover:shadow-xl">
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

                                <div class="flex items-center gap-1 mt-2">
                                    <x-icon name="o-star" class="w-4 h-4 text-yellow-500 fill-yellow-500" />
                                    <span class="text-sm">{{ $course['rating'] }} ({{ $course['reviews_count'] }} reviews)</span>
                                </div>

                                <div class="font-bold mt-4">
                                    @if($course['sale_price'])
                                        <span class="text-lg">${{ $course['sale_price'] }}</span>
                                        <span class="ml-1 text-sm line-through opacity-50">${{ $course['price'] }}</span>
                                    @else
                                        <span class="text-lg">${{ $course['price'] }}</span>
                                    @endif
                                </div>

                                <div class="justify-end mt-2 card-actions">
                                    <a href="{{ route('clients.courses') }}" class="btn btn-sm btn-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
