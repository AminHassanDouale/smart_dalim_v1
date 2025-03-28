<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Children;
use App\Models\Enrollment;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $courseFilter = '';
    public $statusFilter = '';
    public $sortField = 'name';
    public $sortDirection = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'courseFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function mount()
    {
        // Initialize component
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

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCourseFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function getStudentsProperty()
    {
        // Get the authenticated teacher
        $teacher = Auth::user();
        
        // Find all enrollments for courses taught by this teacher
        $teacherCourseIds = $teacher->teacherProfile->courses()->pluck('id')->toArray();
        
        // Get students enrolled in teacher's courses
        $query = Children::whereHas('enrollments', function ($query) use ($teacherCourseIds) {
            $query->whereIn('course_id', $teacherCourseIds);
        })->with(['enrollments.course', 'user']);
        
        // Apply search filter
        if (!empty($this->search)) {
            $search = '%' . $this->search . '%';
            $query->where(function ($query) use ($search) {
                $query->whereHas('user', function ($query) use ($search) {
                    $query->where('name', 'like', $search)
                          ->orWhere('email', 'like', $search);
                });
            });
        }
        
        // Apply course filter
        if (!empty($this->courseFilter)) {
            $query->whereHas('enrollments', function ($query) {
                $query->where('course_id', $this->courseFilter);
            });
        }
        
        // Apply status filter
        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }
        
        // Get results
        $students = $query->get();
        
        // Transform the data to include required info
        return $students->map(function ($student) {
            $enrolledCourses = $student->enrollments->map(function ($enrollment) {
                return [
                    'id' => $enrollment->course->id,
                    'name' => $enrollment->course->name
                ];
            })->unique('id')->values();
            
            $lastSession = $student->learningSessions()
                ->orderBy('start_time', 'desc')
                ->first();
                
            $progress = $student->enrollments->avg('progress_percentage') ?? 0;
            
            return [
                'id' => $student->id,
                'name' => $student->user->name,
                'email' => $student->user->email,
                'avatar' => $student->user->profile_photo_path,
                'status' => $student->status ?? 'active',
                'enrolled_at' => $student->created_at,
                'last_session_at' => $lastSession ? $lastSession->start_time : null,
                'enrolled_course_ids' => $enrolledCourses->pluck('id')->toArray(),
                'enrolled_courses' => $enrolledCourses->pluck('name')->toArray(),
                'total_sessions' => $student->learningSessions()->count(),
                'attendance_rate' => $this->calculateAttendanceRate($student),
                'progress' => round($progress),
            ];
        })->sortBy([[$this->sortField, $this->sortDirection]])
          ->values()
          ->all();
    }
    
    protected function calculateAttendanceRate($student)
    {
        $completedSessions = $student->learningSessions()
            ->where('status', 'completed')
            ->count();
            
        $attendedSessions = $student->learningSessions()
            ->where('status', 'completed')
            ->where('attended', true)
            ->count();
            
        return $completedSessions > 0 
            ? round(($attendedSessions / $completedSessions) * 100) 
            : 0;
    }
    
    public function getCoursesProperty()
{
    $teacher = Auth::user();
    
    // Check if teacher profile exists
    if (!$teacher->teacherProfile) {
        return collect();
    }
    
    // Check if courses relationship method exists
    if (!method_exists($teacher->teacherProfile, 'courses')) {
        return collect();
    }
    
    return $teacher->teacherProfile->courses()
        ->select('id', 'name')
        ->orderBy('name')
        ->get();
}
    public function getStatusBadgeClass($status)
    {
        switch ($status) {
            case 'active':
                return 'badge-success';
            case 'inactive':
                return 'badge-error';
            default:
                return 'badge-info';
        }
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">My Students</h1>
                <p class="mt-1 text-base-content/70">View and manage your students</p>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="p-4 mb-6 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Search -->
                <div>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-base-content/50" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search students by name or email..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- Course Filter -->
                <div>
                    <select wire:model.live="courseFilter" class="w-full select select-bordered">
                        <option value="">All Courses</option>
                        @foreach($this->courses as $course)
                            <option value="{{ $course->id }}">{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <select wire:model.live="statusFilter" class="w-full select select-bordered">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="shadow-xl rounded-xl bg-base-100">
            @if(count($this->students) > 0)
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th class="cursor-pointer" wire:click="sortBy('name')">
                                    <div class="flex items-center">
                                        Student
                                        @if($sortField === 'name')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 ml-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @endif
                                    </div>
                                </th>
                                <th>Courses</th>
                                <th class="cursor-pointer" wire:click="sortBy('enrolled_at')">
                                    <div class="flex items-center">
                                        Enrolled
                                        @if($sortField === 'enrolled_at')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 ml-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @endif
                                    </div>
                                </th>
                                <th class="cursor-pointer" wire:click="sortBy('progress')">
                                    <div class="flex items-center">
                                        Progress
                                        @if($sortField === 'progress')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 ml-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @endif
                                    </div>
                                </th>
                                <th class="cursor-pointer" wire:click="sortBy('status')">
                                    <div class="flex items-center">
                                        Status
                                        @if($sortField === 'status')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 ml-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                        @endif
                                    </div>
                                </th>
                                <th>Last Session</th>
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
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($student['enrolled_courses'] as $index => $course)
                                                @if($index < 2)
                                                    <div class="badge badge-outline">{{ $course }}</div>
                                                @elseif($index == 2)
                                                    <div class="badge badge-outline">+{{ count($student['enrolled_courses']) - 2 }} more</div>
                                                    @break
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $student['enrolled_at']->format('M d, Y') }}</div>
                                        <div class="text-xs opacity-70">{{ $student['enrolled_at']->diffForHumans() }}</div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <progress class="w-16 progress" value="{{ $student['progress'] }}" max="100"></progress>
                                            <span>{{ $student['progress'] }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="badge {{ $this->getStatusBadgeClass($student['status']) }}">
                                            {{ ucfirst($student['status']) }}
                                        </div>
                                    </td>
                                    <td>
                                        @if($student['last_session_at'])
                                            <div>{{ $student['last_session_at']->format('M d, Y') }}</div>
                                            <div class="text-xs opacity-70">{{ $student['last_session_at']->diffForHumans() }}</div>
                                        @else
                                            <span class="text-base-content/50">No sessions yet</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <a
                                                href="{{ route('teachers.students.show', $student['id']) }}"
                                                class="btn btn-sm btn-ghost"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center">
                    <div class="max-w-md mx-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mx-auto mb-4 text-base-content/30" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <h3 class="text-lg font-semibold">No students found</h3>
                        <p class="mt-1 text-base-content/70">
                            @if($search || $courseFilter || $statusFilter)
                                Try adjusting your search or filters to find what you're looking for.
                            @else
                                You don't have any students assigned to you yet.
                            @endif
                        </p>

                        @if($search || $courseFilter || $statusFilter)
                            <button
                                wire:click="$set('search', ''); $set('courseFilter', ''); $set('statusFilter', '');"
                                class="mt-4 btn btn-outline"
                            >
                                Clear Filters
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>