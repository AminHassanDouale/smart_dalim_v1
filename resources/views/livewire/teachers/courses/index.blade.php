<?php

namespace App\Livewire\Teachers\Courses;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $teacherProfile;

    // Filter and sort states
    public $statusFilter = '';
    public $subjectFilter = '';
    public $searchQuery = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';

    // Filter options
    public $subjects = [];

    // Modal states
    public $showDeleteModal = false;
    public $courseToDelete = null;

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'subjectFilter' => ['except' => ''],
        'searchQuery' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->teacherProfile = $this->user->teacherProfile;

        // Load subjects for filter dropdown
        $this->loadSubjects();
    }

    public function loadSubjects()
    {
        // Get all subjects that the teacher is associated with
        if ($this->teacherProfile) {
            $this->subjects = $this->teacherProfile->subjects->toArray();
        }
    }

    public function updatingSearchQuery()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingSubjectFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function confirmDelete($courseId)
    {
        $this->courseToDelete = $courseId;
        $this->showDeleteModal = true;
    }

    public function deleteCourse()
    {
        // In a real app, you would delete the course
        // Course::find($this->courseToDelete)->delete();

        // For now, show a toast message
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
        $this->courseToDelete = null;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->courseToDelete = null;
    }

    // Get courses with pagination, filtering, and sorting
    public function getCoursesProperty()
    {
        // In a real app, this would be a database query
        // For now, we'll return mock data
        return $this->getMockCourses();
    }

    private function getMockCourses()
    {
        $courses = collect([
            [
                'id' => 1,
                'name' => 'Advanced Laravel Development',
                'description' => 'Master advanced Laravel concepts including Middleware, Service Containers, and more.',
                'level' => 'Advanced',
                'subject_id' => 1,
                'subject_name' => 'Laravel Development',
                'teacher_profile_id' => $this->teacherProfile->id ?? 1,
                'price' => 299.99,
                'status' => 'active',
                'students_count' => 12,
                'max_students' => 20,
                'start_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'end_date' => Carbon::now()->addMonths(3)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(30)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->subDays(5)->format('Y-m-d H:i:s'),
                'curriculum' => [
                    'Module 1: Advanced Routing',
                    'Module 2: Service Containers and IoC',
                    'Module 3: Custom Middleware',
                    'Module 4: Advanced Eloquent',
                    'Module 5: Final Project'
                ],
                'learning_outcomes' => [
                    'Build complex Laravel applications',
                    'Implement custom service providers',
                    'Optimize database queries',
                    'Create reusable packages'
                ]
            ],
            [
                'id' => 2,
                'name' => 'React and Redux Masterclass',
                'description' => 'Comprehensive guide to building scalable applications with React and Redux.',
                'level' => 'Intermediate',
                'subject_id' => 2,
                'subject_name' => 'React Development',
                'teacher_profile_id' => $this->teacherProfile->id ?? 1,
                'price' => 249.99,
                'status' => 'active',
                'students_count' => 8,
                'max_students' => 15,
                'start_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'end_date' => Carbon::now()->addMonths(2)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(45)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->subDays(2)->format('Y-m-d H:i:s'),
                'curriculum' => [
                    'Module 1: React Fundamentals',
                    'Module 2: React Hooks',
                    'Module 3: Redux Basics',
                    'Module 4: Advanced Redux',
                    'Module 5: Testing React Applications'
                ],
                'learning_outcomes' => [
                    'Build complex React applications',
                    'Manage state with Redux',
                    'Implement testing strategies',
                    'Deploy React applications'
                ]
            ],
            [
                'id' => 3,
                'name' => 'UI/UX Design Fundamentals',
                'description' => 'Learn the principles of effective UI/UX design and implement them in real projects.',
                'level' => 'Beginner',
                'subject_id' => 3,
                'subject_name' => 'UI/UX Design',
                'teacher_profile_id' => $this->teacherProfile->id ?? 1,
                'price' => 199.99,
                'status' => 'draft',
                'students_count' => 0,
                'max_students' => 25,
                'start_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
                'end_date' => Carbon::now()->addMonths(2)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(10)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->subDays(10)->format('Y-m-d H:i:s'),
                'curriculum' => [
                    'Module 1: Design Principles',
                    'Module 2: Color Theory',
                    'Module 3: Typography',
                    'Module 4: User Research',
                    'Module 5: Prototyping'
                ],
                'learning_outcomes' => [
                    'Create effective user interfaces',
                    'Conduct user research',
                    'Build interactive prototypes',
                    'Implement design systems'
                ]
            ],
            [
                'id' => 4,
                'name' => 'Mobile Development with Flutter',
                'description' => 'Build cross-platform mobile applications with Flutter and Dart.',
                'level' => 'Intermediate',
                'subject_id' => 4,
                'subject_name' => 'Mobile Development',
                'teacher_profile_id' => $this->teacherProfile->id ?? 1,
                'price' => 279.99,
                'status' => 'inactive',
                'students_count' => 5,
                'max_students' => 20,
                'start_date' => Carbon::now()->addDays(-60)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(-5)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(90)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->subDays(65)->format('Y-m-d H:i:s'),
                'curriculum' => [
                    'Module 1: Dart Programming',
                    'Module 2: Flutter Basics',
                    'Module 3: State Management',
                    'Module 4: Advanced Widgets',
                    'Module 5: Publishing Apps'
                ],
                'learning_outcomes' => [
                    'Build cross-platform mobile apps',
                    'Implement complex UI designs',
                    'Manage application state',
                    'Publish to app stores'
                ]
            ],
        ]);

        // Apply filters
        if ($this->statusFilter) {
            $courses = $courses->filter(function($course) {
                return $course['status'] === $this->statusFilter;
            });
        }

        if ($this->subjectFilter) {
            $courses = $courses->filter(function($course) {
                return $course['subject_id'] == $this->subjectFilter;
            });
        }

        if ($this->searchQuery) {
            $query = strtolower($this->searchQuery);
            $courses = $courses->filter(function($course) use ($query) {
                return str_contains(strtolower($course['name']), $query) ||
                       str_contains(strtolower($course['description']), $query) ||
                       str_contains(strtolower($course['subject_name']), $query);
            });
        }

        // Apply sorting
        $courses = $courses->sortBy($this->sortBy, SORT_REGULAR, $this->sortDirection === 'desc');

        return $courses->values()->all();
    }

    // Format date
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // Get course status badge class
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

    // Get stats for course dashboard
    public function getStatsProperty()
    {
        return [
            'total' => count($this->getMockCourses()),
            'active' => count(array_filter($this->getMockCourses(), function($course) {
                return $course['status'] === 'active';
            })),
            'draft' => count(array_filter($this->getMockCourses(), function($course) {
                return $course['status'] === 'draft';
            })),
            'inactive' => count(array_filter($this->getMockCourses(), function($course) {
                return $course['status'] === 'inactive';
            })),
            'total_students' => array_sum(array_column($this->getMockCourses(), 'students_count'))
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
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">My Courses</h1>
                <p class="mt-1 text-base-content/70">Manage your teaching courses</p>
            </div>
            <div>
                <a href="{{ route('teachers.courses.create') }}" class="btn btn-primary">
                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                    Create New Course
                </a>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="p-4 mb-8 shadow-lg rounded-xl bg-base-100 sm:p-6">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['total'] }}</div>
                    <div class="text-xs opacity-70">Total Courses</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['active'] }}</div>
                    <div class="text-xs opacity-70">Active</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['draft'] }}</div>
                    <div class="text-xs opacity-70">Draft</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['inactive'] }}</div>
                    <div class="text-xs opacity-70">Inactive</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['total_students'] }}</div>
                    <div class="text-xs opacity-70">Total Students</div>
                </div>
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
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Subject Filter -->
                <div>
                    <select wire:model.live="subjectFilter" class="w-full select select-bordered">
                        <option value="">All Subjects</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <select wire:model.live="sortBy" class="w-full select select-bordered">
                        <option value="created_at">Date Created</option>
                        <option value="name">Name (A-Z)</option>
                        <option value="students_count">Enrollment Count</option>
                        <option value="start_date">Start Date</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Courses List -->
        <div class="shadow-xl rounded-xl bg-base-100">
            @if(count($this->courses) > 0)
                <div class="p-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($this->courses as $course)
                            <div class="h-full overflow-hidden transition-all border rounded-lg shadow-sm hover:shadow-md border-base-200">
                                <div class="p-6">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <h3 class="text-lg font-bold">{{ $course['name'] }}</h3>
                                            <p class="text-sm text-base-content/70">{{ $course['subject_name'] }}</p>
                                        </div>
                                        <div class="badge {{ $this->getStatusBadgeClass($course['status']) }}">
                                            {{ ucfirst($course['status']) }}
                                        </div>
                                    </div>

                                    <p class="mb-4 text-sm line-clamp-2">{{ $course['description'] }}</p>

                                    <div class="grid grid-cols-2 gap-2 mb-4">
                                        <div>
                                            <div class="text-xs text-base-content/70">Level</div>
                                            <div class="text-sm font-medium">{{ $course['level'] }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-base-content/70">Price</div>
                                            <div class="text-sm font-medium">${{ number_format($course['price'], 2) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-base-content/70">Students</div>
                                            <div class="text-sm font-medium">{{ $course['students_count'] }}/{{ $course['max_students'] }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-base-content/70">Start Date</div>
                                            <div class="text-sm font-medium">{{ $this->formatDate($course['start_date']) }}</div>
                                        </div>
                                    </div>

                                    <!-- Progress Bar for student capacity -->
                                    <div class="mb-4">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs">Enrollment</span>
                                            <span class="text-xs font-medium">{{ round(($course['students_count'] / $course['max_students']) * 100) }}%</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-base-300">
                                            <div
                                                class="h-full bg-primary"
                                                style="width: {{ ($course['students_count'] / $course['max_students']) * 100 }}%"
                                            ></div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap justify-end gap-2 mt-auto">
                                        <a
                                            href="{{ route('teachers.courses.show', $course['id']) }}"
                                            class="btn btn-sm btn-outline"
                                        >
                                            <x-icon name="o-eye" class="w-4 h-4 mr-1" />
                                            View
                                        </a>
                                        <a
                                            href="{{ route('teachers.courses.edit', $course['id']) }}"
                                            class="btn btn-sm btn-outline"
                                        >
                                            <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                            Edit
                                        </a>
                                        <button
                                            wire:click="confirmDelete({{ $course['id'] }})"
                                            class="btn btn-sm btn-outline btn-error"
                                        >
                                            <x-icon name="o-trash" class="w-4 h-4 mr-1" />
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                        <x-icon name="o-academic-cap" class="w-16 h-16 mb-4 text-base-content/30" />
                        <h3 class="text-xl font-bold">No courses found</h3>
                        <p class="mt-2 text-base-content/70">
                            @if($searchQuery || $statusFilter || $subjectFilter)
                                Try adjusting your search or filters
                            @else
                                You don't have any courses yet
                            @endif
                        </p>

                        @if($searchQuery || $statusFilter || $subjectFilter)
                            <button
                                wire:click="$set('searchQuery', ''); $set('statusFilter', ''); $set('subjectFilter', '');"
                                class="mt-4 btn btn-outline"
                            >
                                Clear Filters
                            </button>
                        @else
                            <a href="{{ route('teachers.courses.create') }}" class="mt-4 btn btn-primary">
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Create Course
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal {{ $showDeleteModal ? 'modal-open' : '' }}">
            <div class="modal-box">
                <h3 class="text-lg font-bold">Confirm Delete</h3>
                <p class="py-4">Are you sure you want to delete this course? This action cannot be undone.</p>
                <div class="modal-action">
                    <button wire:click="cancelDelete" class="btn btn-outline">Cancel</button>
                    <button wire:click="deleteCourse" class="btn btn-error">Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>
