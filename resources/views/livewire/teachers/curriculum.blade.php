<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use App\Models\Course;
use App\Models\TeacherProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

new class extends Component {
    public $teacher;
    public $teacherProfile;
    public $courses = [];
    
    // Filter properties
    public $search = '';
    public $subjectFilter = '';
    public $levelFilter = '';

    public function mount()
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        // Load courses
        $this->loadCourses();
    }

    protected function loadCourses()
    {
        if ($this->teacherProfile) {
            // In a real app, get courses created by this teacher
            $this->courses = Course::where('teacher_profile_id', $this->teacherProfile->id)
                ->with(['subject'])
                ->get()
                ->map(function($course) {
                    // Transform course data to include curriculum structure if needed
                    return [
                        'id' => $course->id,
                        'name' => $course->name,
                        'description' => $course->description,
                        'level' => $course->level,
                        'duration' => $course->duration,
                        'subject_id' => $course->subject_id,
                        'subject_name' => $course->subject->name ?? 'Unknown',
                        'status' => $course->status,
                        'curriculum' => $course->curriculum ?? [],
                        'created_at' => $course->created_at,
                        'updated_at' => $course->updated_at,
                    ];
                });
        } else {
            $this->courses = collect();
        }
    }

    public function getFilteredCoursesProperty()
    {
        $courses = collect($this->courses);

        // Apply search filter
        if ($this->search) {
            $search = strtolower($this->search);
            $courses = $courses->filter(function($course) use ($search) {
                return Str::contains(strtolower($course['name']), $search) ||
                       Str::contains(strtolower($course['description'] ?? ''), $search);
            });
        }

        // Apply subject filter
        if ($this->subjectFilter) {
            $courses = $courses->filter(function($course) {
                return $course['subject_id'] == $this->subjectFilter;
            });
        }

        // Apply level filter
        if ($this->levelFilter) {
            $courses = $courses->filter(function($course) {
                return $course['level'] == $this->levelFilter;
            });
        }

        return $courses;
    }
    
    public function getSubjectsProperty()
    {
        if ($this->teacherProfile) {
            return $this->teacherProfile->subjects()->get();
        }
        
        return collect();
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">My Curriculum</h1>
                <p class="mt-1 text-base-content/70">View and manage your course curriculum</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('teachers.courses.create') }}" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    Create New Course
                </a>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="p-4 mb-6 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <!-- Search -->
                <div>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-base-content/50" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search courses..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- Subject Filter -->
                <div>
                    <select wire:model.live="subjectFilter" class="w-full select select-bordered">
                        <option value="">All Subjects</option>
                        @foreach($this->subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Level Filter -->
                <div>
                    <select wire:model.live="levelFilter" class="w-full select select-bordered">
                        <option value="">All Levels</option>
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                        <option value="all">All Levels</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Curriculum List -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="text-xl font-semibold">My Courses</h2>

                @if(count($this->filteredCourses) > 0)
                    <div class="space-y-4">
                        @foreach($this->filteredCourses as $course)
                            <div class="collapse collapse-arrow bg-base-200">
                                <input type="checkbox" /> 
                                <div class="flex items-center gap-2 font-medium collapse-title">
                                    <div class="badge {{ $course['status'] === 'draft' ? 'badge-warning' : 'badge-success' }} badge-sm">
                                        {{ ucfirst($course['status']) }}
                                    </div>
                                    {{ $course['name'] }} ({{ count($course['curriculum'] ?? []) }} modules)
                                </div>
                                <div class="collapse-content">
                                    <div class="p-2 space-y-2">
                                        @if(isset($course['curriculum']) && count($course['curriculum']) > 0)
                                            @foreach($course['curriculum'] as $moduleIndex => $module)
                                                <div class="border rounded-md collapse collapse-arrow bg-base-100 border-base-300">
                                                    <input type="checkbox" />
                                                    <div class="flex items-center justify-between collapse-title">
                                                        <div>
                                                            <span class="font-medium">{{ $module['title'] }}</span>
                                                            <span class="ml-2 text-xs text-base-content/70">({{ count($module['lessons'] ?? []) }} lessons)</span>
                                                        </div>
                                                    </div>
                                                    <div class="p-2 collapse-content">
                                                        <div class="mb-2 text-sm">{{ $module['description'] }}</div>
                                                        @if(isset($module['lessons']) && count($module['lessons']) > 0)
                                                            <div class="overflow-x-auto">
                                                                <table class="table table-xs">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Order</th>
                                                                            <th>Lesson</th>
                                                                            <th>Duration</th>
                                                                            <th>Required</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($module['lessons'] as $lessonIndex => $lesson)
                                                                            <tr>
                                                                                <td>{{ $lesson['order'] }}</td>
                                                                                <td>{{ $lesson['title'] }}</td>
                                                                                <td>{{ $lesson['duration'] ?? 'N/A' }} min</td>
                                                                                <td>
                                                                                    @if(isset($lesson['is_required']) && $lesson['is_required'])
                                                                                        <span class="text-success">Yes</span>
                                                                                    @else
                                                                                        <span class="text-warning">No</span>
                                                                                    @endif
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        @else
                                                            <div class="p-4 text-center">
                                                                <p>No lessons found for this module.</p>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="p-4 text-center">
                                                <p>No curriculum defined for this course yet.</p>
                                                <a href="{{ route('teachers.courses.edit', $course['id']) }}?tab=curriculum" class="mt-2 btn btn-sm">
                                                    Add Curriculum
                                                </a>
                                            </div>
                                        @endif
                                        <div class="flex justify-end">
                                            <a
                                                href="{{ route('teachers.courses.edit', $course['id']) }}?tab=curriculum"
                                                class="btn btn-sm"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                Edit Curriculum
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mb-4 text-base-content/30" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            <h3 class="text-xl font-bold">No courses found</h3>
                            <p class="mt-2 text-base-content/70">
                                @if($search || $subjectFilter || $levelFilter)
                                    No courses match your filters. Try adjusting your search criteria.
                                @else
                                    You haven't created any courses yet. Create your first course to get started.
                                @endif
                            </p>
                            <a href="{{ route('teachers.courses.create') }}" class="mt-4 btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                Create Course
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>