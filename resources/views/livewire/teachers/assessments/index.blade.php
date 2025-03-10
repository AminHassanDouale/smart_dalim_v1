<?php

namespace App\Livewire\Teachers\Assessments;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Children;
use App\Models\ClientProfile;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    // User and profile
    public $teacher;
    public $teacherProfile;
    public $teacherProfileId = null;

    // Filters
    public $search = '';
    public $typeFilter = '';
    public $courseFilter = '';
    public $subjectFilter = '';
    public $statusFilter = '';
    public $participantFilter = ''; // 'children', 'clients', or '' for all
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // UI state
    public $showDeleteModal = false;
    public $assessmentToDelete = null;
    public $showDuplicateModal = false;
    public $assessmentToDuplicate = null;

    // Lists for selection
    public $courses = [];
    public $subjects = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'courseFilter' => ['except' => ''],
        'subjectFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'participantFilter' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount()
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        if ($this->teacherProfile) {
            $this->teacherProfileId = $this->teacherProfile->id;

            // Load courses and subjects
            $this->loadCourses();
            $this->loadSubjects();
        } else {
            return redirect()->route('teachers.profile-setup')
                ->with('error', 'Please complete your teacher profile before accessing assessments.');
        }
    }

    protected function loadCourses()
    {
        $this->courses = Course::where('teacher_profile_id', $this->teacherProfileId)
            ->orderBy('name')
            ->get();
    }

    protected function loadSubjects()
    {
        if ($this->teacherProfile->subjects) {
            $this->subjects = $this->teacherProfile->subjects;
        } else {
            $this->subjects = collect();
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingCourseFilter()
    {
        $this->resetPage();
    }

    public function updatingSubjectFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingParticipantFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function confirmDelete($id)
    {
        $this->assessmentToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function deleteAssessment()
    {
        try {
            $assessment = Assessment::findOrFail($this->assessmentToDelete);
            $assessment->delete();

            $this->showDeleteModal = false;
            $this->assessmentToDelete = null;

            $this->toast(
                type: 'success',
                title: 'Assessment Deleted',
                description: 'The assessment has been deleted successfully.',
                icon: 'o-check-circle',
                css: 'alert-success'
            );
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: 'An error occurred: ' . $e->getMessage(),
                icon: 'o-x-circle',
                css: 'alert-error'
            );
        }
    }

    public function confirmDuplicate($id)
    {
        $this->assessmentToDuplicate = $id;
        $this->showDuplicateModal = true;
    }

    public function duplicateAssessment()
    {
        try {
            $original = Assessment::with(['questions'])->findOrFail($this->assessmentToDuplicate);

            // Create duplicate assessment
            $duplicate = $original->replicate();
            $duplicate->title = 'Copy of ' . $original->title;
            $duplicate->is_published = false;
            $duplicate->status = 'draft';
            $duplicate->save();

            // Duplicate questions
            foreach ($original->questions as $question) {
                $newQuestion = $question->replicate();
                $newQuestion->assessment_id = $duplicate->id;
                $newQuestion->save();
            }

            // Copy material relations
            if ($original->materials) {
                $duplicate->materials()->attach($original->materials->pluck('id'));
            }

            $this->showDuplicateModal = false;
            $this->assessmentToDuplicate = null;

            $this->toast(
                type: 'success',
                title: 'Assessment Duplicated',
                description: 'The assessment has been duplicated successfully.',
                icon: 'o-check-circle',
                css: 'alert-success'
            );

            return redirect()->route('teachers.assessments.edit', $duplicate->id);
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: 'An error occurred: ' . $e->getMessage(),
                icon: 'o-x-circle',
                css: 'alert-error'
            );
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'typeFilter', 'courseFilter', 'subjectFilter', 'statusFilter', 'participantFilter']);
        $this->resetPage();
    }

    public function with(): array
    {
        try {
            $query = Assessment::query()
                ->where('teacher_profile_id', $this->teacherProfileId)
                ->with(['course', 'subject', 'questions', 'submissions', 'children', 'clients']);

            // Apply search
            if ($this->search) {
                $query->where(function($q) {
                    $q->where('title', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%");
                });
            }

            // Apply filters
            if ($this->typeFilter) {
                $query->where('type', $this->typeFilter);
            }

            if ($this->courseFilter) {
                $query->where('course_id', $this->courseFilter);
            }

            if ($this->subjectFilter) {
                $query->where('subject_id', $this->subjectFilter);
            }

            if ($this->statusFilter) {
                if ($this->statusFilter === 'draft') {
                    $query->where('is_published', false);
                } elseif ($this->statusFilter === 'published') {
                    $query->where('is_published', true)
                          ->where(function ($q) {
                              $q->whereNull('start_date')
                                ->orWhere('start_date', '>', now());
                          });
                } elseif ($this->statusFilter === 'active') {
                    $query->where('is_published', true)
                          ->where(function ($q) {
                              $q->whereNotNull('start_date')
                                ->where('start_date', '<=', now())
                                ->where(function ($q2) {
                                    $q2->whereNull('due_date')
                                       ->orWhere('due_date', '>=', now());
                                });
                          });
                } elseif ($this->statusFilter === 'ended') {
                    $query->where('is_published', true)
                          ->whereNotNull('due_date')
                          ->where('due_date', '<', now());
                }
            }

            if ($this->participantFilter === 'children') {
                $query->whereHas('children');
            } elseif ($this->participantFilter === 'clients') {
                $query->whereHas('clients');
            }

            // Apply sorting
            $query->orderBy($this->sortField, $this->sortDirection);

            $assessments = $query->paginate(10);

            // Calculate metrics for dashboard
            $totalSubmissions = 0;
            $pendingSubmissions = 0;
            $activeAssessments = 0;

            // Process assessment items for stats
            foreach ($assessments->items() as $assessment) {
                // Count active assessments
                if ($assessment->is_published
                    && $assessment->start_date
                    && $assessment->start_date <= now()
                    && (!$assessment->due_date || $assessment->due_date >= now())) {
                    $activeAssessments++;
                }

                // Count submissions
                if (isset($assessment->submissions)) {
                    $totalSubmissions += $assessment->submissions->where('status', 'completed')->count();
                    $pendingSubmissions += $assessment->submissions->where('status', 'completed')
                                                                 ->where('graded_at', null)
                                                                 ->count();
                }
            }

            // List of assessment types
            $assessmentTypes = Assessment::$types ?? [
                'quiz' => 'Quiz',
                'test' => 'Test',
                'exam' => 'Exam',
                'assignment' => 'Assignment',
                'project' => 'Project',
                'essay' => 'Essay',
                'presentation' => 'Presentation',
                'other' => 'Other',
            ];

            // List of assessment statuses
            $assessmentStatuses = Assessment::$statuses ?? [
                'draft' => 'Draft',
                'published' => 'Published',
                'active' => 'Active',
                'ended' => 'Ended',
                'archived' => 'Archived',
            ];

            return [
                'assessments' => $assessments,
                'assessmentTypes' => $assessmentTypes,
                'assessmentStatuses' => $assessmentStatuses,
                'totalSubmissions' => $totalSubmissions,
                'pendingSubmissions' => $pendingSubmissions,
                'activeAssessments' => $activeAssessments
            ];
        } catch (\Exception $e) {
            return [
                'assessments' => collect(),
                'assessmentTypes' => [],
                'assessmentStatuses' => [],
                'totalSubmissions' => 0,
                'pendingSubmissions' => 0,
                'activeAssessments' => 0,
                'error' => 'Error loading assessments: ' . $e->getMessage()
            ];
        }
    }
};
?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Assessments & Grading</h1>
                <p class="mt-1 text-base-content/70">Create and manage quizzes, tests, and assignments</p>
            </div>
            <div class="flex gap-2">
                <a
                    href="{{ route('teachers.assessments.create') }}"
                    class="btn btn-primary"
                >
                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                    Create Assessment
                </a>
                <div class="dropdown dropdown-end">
                    <label tabindex="0" class="btn btn-outline">
                        <x-icon name="o-bars-3" class="w-4 h-4 mr-2" />
                        More Options
                    </label>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                        <li>
                            <a href="{{ route('teachers.assessments.reviews') }}">
                                <x-icon name="o-clipboard-document-check" class="w-4 h-4" />
                                Review All Submissions
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('teachers.assessments.reports') }}">
                                <x-icon name="o-chart-bar" class="w-4 h-4" />
                                Performance Reports
                            </a>
                        </li>
                        <li>



                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <!-- Stats Overview with calculated metrics -->
<div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
    <div class="shadow stats bg-base-100">
        <div class="stat">
            <div class="stat-figure text-primary">
                <x-icon name="o-document-text" class="w-8 h-8" />
            </div>
            <div class="stat-title">Total</div>
            <div class="stat-value text-primary">{{ $assessments->total() }}</div>
            <div class="stat-desc">All assessments</div>
        </div>
    </div>
    <div class="shadow stats bg-base-100">
        <div class="stat">
            <div class="stat-figure text-secondary">
                <x-icon name="o-check-circle" class="w-8 h-8" />
            </div>
            <div class="stat-title">Active</div>
            <div class="stat-value text-secondary">
                {{ $activeAssessments }}
            </div>
            <div class="stat-desc">Currently running</div>
        </div>
    </div>
    <div class="shadow stats bg-base-100">
        <div class="stat">
            <div class="stat-figure text-success">
                <x-icon name="o-academic-cap" class="w-8 h-8" />
            </div>
            <div class="stat-title">Submissions</div>
            <div class="stat-value text-success">
                {{ $totalSubmissions }}
            </div>
            <div class="stat-desc">From all assessments</div>
        </div>
    </div>
    <div class="shadow stats bg-base-100">
        <div class="stat">
            <div class="stat-figure text-info">
                <x-icon name="o-pencil-square" class="w-8 h-8" />
            </div>
            <div class="stat-title">Pending</div>
            <div class="stat-value text-info">
                {{ $pendingSubmissions }}
            </div>
            <div class="stat-desc">Need grading</div>
        </div>
    </div>
</div>

        <!-- Filters and Search -->
        <div class="p-4 mb-6 shadow-xl rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search by title or description..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- Type Filter -->
                <div>
                    <select wire:model.live="typeFilter" class="w-full select select-bordered">
                        <option value="">All Types</option>
                        @foreach($assessmentTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Course Filter -->
                <div>
                    <select wire:model.live="courseFilter" class="w-full select select-bordered">
                        <option value="">All Courses</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <select wire:model.live="statusFilter" class="w-full select select-bordered">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="active">Active</option>
                        <option value="ended">Ended</option>
                    </select>
                </div>

                <!-- Participant Filter -->
                <div>
                    <select wire:model.live="participantFilter" class="w-full select select-bordered">
                        <option value="">All Participants</option>
                        <option value="children">Students Only</option>
                        <option value="clients">Clients Only</option>
                    </select>
                </div>
            </div>

            @if($search || $typeFilter || $courseFilter || $subjectFilter || $statusFilter || $participantFilter)
                <div class="flex justify-end mt-4">
                    <button
                        wire:click="clearFilters"
                        class="btn btn-sm btn-ghost"
                    >
                        <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                        Clear Filters
                    </button>
                </div>
            @endif
        </div>

        <!-- Assessments Table -->
        <div class="overflow-hidden shadow-xl bg-base-100 rounded-xl">
            @if(isset($error))
                <div class="alert alert-error">
                    <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
                    <span>{{ $error }}</span>
                </div>
            @elseif(count($assessments) > 0)
                <div class="overflow-x-auto">
                    <table class="table w-full table-zebra">
                        <thead>
                            <tr>
                                <th class="cursor-pointer" wire:click="sortBy('title')">
                                    <div class="flex items-center">
                                        Title
                                        @if($sortField === 'title')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th class="cursor-pointer" wire:click="sortBy('type')">
                                    <div class="flex items-center">
                                        Type
                                        @if($sortField === 'type')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th>Course/Subject</th>
                                <th class="cursor-pointer" wire:click="sortBy('due_date')">
                                    <div class="flex items-center">
                                        Due Date
                                        @if($sortField === 'due_date')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th>Participants</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assessments as $assessment)
                                <tr>
                                    <td>
                                        <div class="font-medium">{{ $assessment->title }}</div>
                                        @if($assessment->description)
                                            <div class="max-w-sm text-xs truncate text-base-content/60">
                                                {{ Str::limit($assessment->description, 50) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="badge {{ match($assessment->type) {
                                            'quiz' => 'badge-primary',
                                            'test' => 'badge-secondary',
                                            'exam' => 'badge-accent',
                                            'assignment' => 'badge-info',
                                            default => 'badge-ghost'
                                        } }}">
                                            {{ $assessmentTypes[$assessment->type] ?? ucfirst($assessment->type) }}
                                        </div>

                                        <div class="flex items-center mt-1">
                                            <div class="badge badge-ghost badge-sm">{{ $assessment->questions->count() }} questions</div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($assessment->course)
                                            <div class="text-sm">
                                                <span class="font-medium">Course:</span> {{ $assessment->course->name }}
                                            </div>
                                        @endif

                                        @if($assessment->subject)
                                            <div class="text-sm">
                                                <span class="font-medium">Subject:</span> {{ $assessment->subject->name }}
                                            </div>
                                        @endif

                                        @if(!$assessment->course && !$assessment->subject)
                                            <span class="text-xs text-base-content/60">None assigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($assessment->due_date)
                                            <div class="text-sm">
                                                {{ $assessment->due_date->format('M d, Y') }}
                                                <div class="text-xs text-base-content/60">
                                                    {{ $assessment->due_date->format('h:i A') }}
                                                </div>
                                            </div>

                                            @if($assessment->due_date->isPast())
                                                <div class="badge badge-error badge-sm">Ended</div>
                                            @elseif($assessment->due_date->diffInDays(now()) <= 3)
                                                <div class="badge badge-warning badge-sm">Ending Soon</div>
                                            @endif
                                        @else
                                            <span class="text-base-content/60">No due date</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex flex-col gap-1">
                                            @if(isset($assessment->children) && $assessment->children->count() > 0)
                                                <div class="flex items-center">
                                                    <div class="badge badge-info">{{ $assessment->children->count() }}</div>
                                                    <span class="ml-1 text-xs">students</span>
                                                </div>
                                            @endif

                                            @if(isset($assessment->clients) && $assessment->clients->count() > 0)
                                                <div class="flex items-center">
                                                    <div class="badge badge-secondary">{{ $assessment->clients->count() }}</div>
                                                    <span class="ml-1 text-xs">clients</span>
                                                </div>
                                            @endif

                                            @if((!isset($assessment->children) || $assessment->children->count() === 0) &&
                                               (!isset($assessment->clients) || $assessment->clients->count() === 0))
                                                <span class="text-xs text-base-content/60">No participants</span>
                                            @endif

                                            @if($assessment->submissions->count() > 0)
                                                <div class="text-xs">
                                                    {{ $assessment->submissions->where('status', 'completed')->count() }}/{{ $assessment->submissions->count() }} submitted
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if(!$assessment->is_published)
                                            <div class="badge badge-ghost">Draft</div>
                                        @elseif($assessment->due_date && $assessment->due_date->isPast())
                                            <div class="badge badge-error">Ended</div>
                                        @elseif($assessment->start_date && $assessment->start_date->isPast())
                                            <div class="badge badge-success">Active</div>
                                        @else
                                            <div class="badge badge-info">Published</div>
                                        @endif

                                        @if($assessment->submissions->count() > 0 && $assessment->submissions->where('status', 'completed')->where('graded_at', null)->count() > 0)
                                            <div class="mt-1 badge badge-warning badge-sm">Needs grading</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <a
                                                href="{{ route('teachers.assessments.show', $assessment->id) }}"
                                                class="btn btn-square btn-sm btn-ghost"
                                                title="View"
                                            >
                                                <x-icon name="o-eye" class="w-4 h-4" />
                                            </a>

                                            <a
                                                href="{{ route('teachers.assessments.edit', $assessment->id) }}"
                                                class="btn btn-square btn-sm btn-ghost"
                                                title="Edit"
                                            >
                                                <x-icon name="o-pencil-square" class="w-4 h-4" />
                                            </a>

                                            <div class="dropdown dropdown-end">
                                                <label tabindex="0" class="btn btn-square btn-sm btn-ghost">
                                                    <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                                </label>
                                                <ul tabindex="0" class="z-10 p-2 shadow dropdown-content menu bg-base-100 rounded-box w-52">
                                                    @if($assessment->submissions->where('status', 'completed')->count() > 0)
                                                        <li>
                                                            <a href="{{ route('teachers.assessments.results', $assessment->id) }}">
                                                                <x-icon name="o-chart-bar" class="w-4 h-4" />
                                                                View Results
                                                            </a>
                                                        </li>
                                                    @endif

                                                    <li>
                                                        <button wire:click="confirmDuplicate({{ $assessment->id }})">
                                                            <x-icon name="o-document-duplicate" class="w-4 h-4" />
                                                            Duplicate
                                                        </button>
                                                    </li>

                                                    @if(!$assessment->is_published)
                                                        <li>
                                                            <a href="{{ route('teachers.assessments.publish', $assessment->id) }}">
                                                                <x-icon name="o-paper-airplane" class="w-4 h-4" />
                                                                Publish
                                                            </a>
                                                        </li>
                                                    @endif

                                                    <li>
                                                        <a href="{{ route('teachers.assessments.assign', $assessment->id) }}">
                                                            <x-icon name="o-user-plus" class="w-4 h-4" />
                                                            Assign
                                                        </a>
                                                    </li>

                                                    <li>
                                                        <button wire:click="confirmDelete({{ $assessment->id }})" class="text-error">
                                                            <x-icon name="o-trash" class="w-4 h-4" />
                                                            Delete
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t">
                    {{ $assessments->links() }}
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                        <x-icon name="o-document-chart-bar" class="w-20 h-20 mb-4 text-base-content/20" />

                        @if($search || $typeFilter || $courseFilter || $subjectFilter || $statusFilter || $participantFilter)
                            <h3 class="text-xl font-bold">No matching assessments</h3>
                            <p class="max-w-md mx-auto mt-2 text-base-content/70">
                                Try adjusting your search filters to find what you're looking for.
                            </p>

                            <button
                                wire:click="clearFilters"
                                class="mt-4 btn btn-outline"
                            >
                                <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                                Clear Filters
                            </button>
                        @else
                            <h3 class="text-xl font-bold">No assessments yet</h3>
                            <p class="max-w-md mx-auto mt-2 text-base-content/70">
                                Create your first assessment to start testing and evaluating your students.
                            </p>

                            <a
                                href="{{ route('teachers.assessments.create') }}"
                                class="mt-4 btn btn-primary"
                            >
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Create Assessment
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Recent Activity -->
        <div class="mt-8">
            <h2 class="mb-4 text-xl font-bold">Recent Activity</h2>

            <div class="overflow-hidden shadow-xl bg-base-100 rounded-xl">
              @php
$recentSubmissions = collect();

// If there are assessments, process them
if ($assessments->count() > 0) {
    // Get the items from the paginator and collect submissions
    foreach ($assessments->items() as $assessment) {
        if (isset($assessment->submissions)) {
            $recentSubmissions = $recentSubmissions->concat($assessment->submissions);
        }
    }

    // Sort and limit the submissions
    $recentSubmissions = $recentSubmissions->sortByDesc('updated_at')->take(5);
}
@endphp

@if($recentSubmissions->count() > 0)
    <div class="overflow-x-auto">
        <table class="table w-full">
            <thead>
                <tr>
                    <th>Assessment</th>
                    <th>Participant</th>
                    <th>Submitted</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentSubmissions as $submission)
                    <tr>
                        <td>{{ $submission->assessment->title }}</td>
                        <td>
                            @if($submission->children_id && isset($submission->children))
                                {{ $submission->children->name }}
                                <div class="badge badge-info badge-sm">Student</div>
                            @elseif($submission->client_profile_id && isset($submission->client))
                                {{ $submission->client->company_name ?? $submission->client->user->name }}
                                <div class="badge badge-secondary badge-sm">Client</div>
                            @else
                                Unknown participant
                            @endif
                        </td>
                        <td>{{ $submission->end_time ? $submission->end_time->diffForHumans() : 'In progress' }}</td>
                        <td>
                            @if($submission->score !== null)
                                <div class="radial-progress text-primary" style="--value:{{ min(100, round(($submission->score / $submission->assessment->total_points) * 100)) }}; --size: 2rem;">
                                    <span class="text-xs">{{ $submission->score }}</span>
                                </div>
                            @else
                                <span class="text-base-content/60">-</span>
                            @endif
                        </td>
                        <td>
                            <div class="badge {{ match($submission->status) {
                                'not_started' => 'badge-ghost',
                                'in_progress' => 'badge-info',
                                'completed' => 'badge-warning',
                                'graded' => 'badge-success',
                                'late' => 'badge-error',
                                default => 'badge-ghost'
                            } }}">
                                {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                            </div>
                        </td>
                        <td>
                            <a
                                href="{{ route('teachers.assessment-submissions.show', $submission->id) }}"
                                class="btn btn-sm btn-ghost"
                            >
                                <x-icon name="o-eye" class="w-4 h-4 mr-1" />
                                View
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="p-8 text-center">
        <p class="text-base-content/70">No recent activity to display.</p>
    </div>
@endif
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal {{ $showDeleteModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold text-error">Delete Assessment</h3>
            <p class="py-4">Are you sure you want to delete this assessment? This action cannot be undone and will remove all associated questions and submissions.</p>
            <div class="modal-action">
                <button wire:click="$set('showDeleteModal', false)" class="btn">Cancel</button>
                <button wire:click="deleteAssessment" class="btn btn-error">Delete</button>
            </div>
        </div>
    </div>

    <!-- Duplicate Confirmation Modal -->
    <div class="modal {{ $showDuplicateModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Duplicate Assessment</h3>
            <p class="py-4">This will create a new copy of the assessment with all its questions. The new assessment will be saved as a draft.</p>
            <div class="modal-action">
                <button wire:click="$set('showDuplicateModal', false)" class="btn">Cancel</button>
                <button wire:click="duplicateAssessment" class="btn btn-primary">Duplicate</button>
            </div>
        </div>
    </div>
</div>
