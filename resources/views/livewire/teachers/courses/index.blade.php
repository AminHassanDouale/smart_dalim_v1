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
        try {
            $course = Course::findOrFail($this->courseToDelete);

            // Ensure course belongs to this teacher
            if ($course->teacher_profile_id != $this->teacherProfile->id) {
                throw new \Exception('You are not authorized to delete this course.');
            }

            // Delete the course
            $course->delete();

            $this->toast(
                type: 'success',
                title: 'Course deleted',
                description: 'The course has been deleted successfully.',
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                css: 'alert-success',
                timeout: 3000
            );
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: 'There was an error deleting the course: ' . $e->getMessage(),
                position: 'toast-bottom toast-end',
                icon: 'o-x-circle',
                css: 'alert-error',
                timeout: 5000
            );
        }

        $this->showDeleteModal = false;
        $this->courseToDelete = null;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->courseToDelete = null;
    }

    public function getCoursesProperty()
    {
        // Get the teacher's courses from the database
        $query = Course::query()
            ->where('teacher_profile_id', $this->teacherProfile->id)
            ->with(['subject', 'enrollments']); // Eager load the subject and enrollments relationships

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Apply subject filter
        if ($this->subjectFilter) {
            $query->where('subject_id', $this->subjectFilter);
        }

        // Apply search filter
        if ($this->searchQuery) {
            $searchTerm = '%' . $this->searchQuery . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('description', 'like', $searchTerm);
            });
        }

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        // Return paginated results directly without transforming to array
        return $query->paginate(9);
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
        // If teacherProfile isn't loaded, return empty stats
        if (!$this->teacherProfile) {
            return [
                'total' => 0,
                'active' => 0,
                'draft' => 0,
                'inactive' => 0,
                'total_students' => 0
            ];
        }

        // Query for total counts
        $totalCourses = Course::where('teacher_profile_id', $this->teacherProfile->id)->count();
        $activeCourses = Course::where('teacher_profile_id', $this->teacherProfile->id)
                            ->where('status', 'active')
                            ->count();
        $draftCourses = Course::where('teacher_profile_id', $this->teacherProfile->id)
                           ->where('status', 'draft')
                           ->count();
        $inactiveCourses = Course::where('teacher_profile_id', $this->teacherProfile->id)
                              ->where('status', 'inactive')
                              ->count();

        // Count total students enrolled in teacher's courses
        $totalStudents = Course::where('teacher_profile_id', $this->teacherProfile->id)
                           ->withCount('enrollments')
                           ->get()
                           ->sum('enrollments_count');

        return [
            'total' => $totalCourses,
            'active' => $activeCourses,
            'draft' => $draftCourses,
            'inactive' => $inactiveCourses,
            'total_students' => $totalStudents
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
                                            <h3 class="text-lg font-bold">{{ $course->name }}</h3>
                                            <p class="text-sm text-base-content/70">{{ $course->subject->name }}</p>
                                        </div>
                                        <div class="badge {{ $this->getStatusBadgeClass($course->status) }}">
                                            {{ ucfirst($course->status) }}
                                        </div>
                                    </div>

                                    <p class="mb-4 text-sm line-clamp-2">{{ $course->description }}</p>

                                    <div class="grid grid-cols-2 gap-2 mb-4">
                                        <div>
                                            <div class="text-xs text-base-content/70">Level</div>
                                            <div class="text-sm font-medium">{{ ucfirst($course->level) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-base-content/70">Price</div>
                                            <div class="text-sm font-medium">${{ number_format($course->price, 2) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-base-content/70">Students</div>
                                            <div class="text-sm font-medium">{{ $course->enrollments->count() }}/{{ $course->max_students }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-base-content/70">Start Date</div>
                                            <div class="text-sm font-medium">{{ $this->formatDate($course->start_date) }}</div>
                                        </div>
                                    </div>

                                    <!-- Progress Bar for student capacity -->
                                    <div class="mb-4">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs">Enrollment</span>
                                            <span class="text-xs font-medium">{{ $course->max_students > 0 ? round(($course->enrollments->count() / $course->max_students) * 100) : 0 }}%</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-base-300">
                                            <div
                                                class="h-full bg-primary"
                                                style="width: {{ $course->max_students > 0 ? ($course->enrollments->count() / $course->max_students) * 100 : 0 }}%"
                                            ></div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap justify-end gap-2 mt-auto">
                                        
                                           <a href="{{ route('teachers.courses.show', $course->id) }}"
                                            class="btn btn-sm btn-outline"
                                        >
                                            <x-icon name="o-eye" class="w-4 h-4 mr-1" />
                                            View
                                        </a>
                                        
                                        <a    href="{{ route('teachers.courses.edit', $course->id) }}"
                                            class="btn btn-sm btn-outline"
                                        >
                                            <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                            Edit
                                        </a>
                                        <button
                                            wire:click="confirmDelete({{ $course->id }})"
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
                    
                    <!-- Pagination Links -->
                    <div class="py-4 mt-6">
                        {{ $this->courses->links() }}
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