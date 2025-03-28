<?php

namespace App\Livewire\Teachers\Courses;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Subject;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public $course;
    public $user;
    public $teacherProfile;

    // Form inputs
    public $name = '';
    public $slug = '';
    public $description = '';
    public $level = 'beginner';
    public $duration = 8; // weeks
    public $price = 0;
    public $subjectId = '';
    public $maxStudents = 20;
    public $startDate = '';
    public $endDate = '';
    public $status = 'draft';

    // Curriculum
    public $modules = [['title' => '', 'description' => '']];

    // Learning outcomes
    public $outcomes = [''];

    // Prerequisites
    public $prerequisites = [''];

    // Cover image
    public $coverImage;
    public $existingCoverImage;
    public $removeCoverImage = false;

    // Available subjects
    public $availableSubjects = [];

    // Available levels
    public $availableLevels = [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
        'all' => 'All Levels'
    ];

    // Available statuses
    public $availableStatuses = [
        'draft' => 'Draft',
        'active' => 'Active',
        'inactive' => 'Inactive'
    ];

    // Step tracking
    public $currentStep = 1;
    public $totalSteps = 4;

    // Validation rules
    protected function rules()
    {
        return [
            'name' => 'required|string|min:5|max:100',
            'slug' => 'required|string|max:100|unique:courses,slug,' . $this->course->id,
            'description' => 'required|string|min:20',
            'level' => 'required|string|in:beginner,intermediate,advanced,all',
            'duration' => 'required|integer|min:1|max:52',
            'price' => 'required|numeric|min:0',
            'subjectId' => 'required|exists:subjects,id',
            'maxStudents' => 'required|integer|min:1|max:100',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
            'status' => 'required|string|in:draft,active,inactive',

            'modules' => 'required|array|min:1',
            'modules.*.title' => 'required|string|min:3',
            'modules.*.description' => 'required|string|min:10',

            'outcomes' => 'required|array|min:1',
            'outcomes.*' => 'required|string|min:5',

            'prerequisites' => 'nullable|array',
            'prerequisites.*' => 'nullable|string',

            'coverImage' => 'nullable|image|max:2048'
        ];
    }

    public function mount($course)
    {
        \Log::info('Mounting course edit component', ['course_id' => $course]);
        
        $this->user = Auth::user();
        $this->teacherProfile = $this->user->teacherProfile;

        // Fetch the course from database
        $this->course = Course::with(['subject'])->findOrFail($course);
        
        // Check if this course belongs to the current teacher
        if ($this->course->teacher_profile_id != $this->teacherProfile->id) {
            \Log::warning('Unauthorized course edit attempt', [
                'course_id' => $id, 
                'teacher_id' => $this->teacherProfile->id
            ]);
            return redirect()->route('teachers.courses')->with('error', 'You are not authorized to edit this course.');
        }

        \Log::info('Found course for editing', ['course' => $this->course->name]);

        // Populate form fields with existing data
        $this->name = $this->course->name;
        $this->slug = $this->course->slug;
        $this->description = $this->course->description;
        $this->level = $this->course->level;
        $this->duration = $this->course->duration;
        $this->price = $this->course->price;
        $this->subjectId = $this->course->subject_id;
        $this->maxStudents = $this->course->max_students;
        $this->startDate = $this->course->start_date ? Carbon::parse($this->course->start_date)->format('Y-m-d') : '';
        $this->endDate = $this->course->end_date ? Carbon::parse($this->course->end_date)->format('Y-m-d') : '';
        $this->status = $this->course->status;
        $this->modules = $this->course->curriculum ?: [['title' => '', 'description' => '']];
        $this->outcomes = $this->course->learning_outcomes ?: [''];
        $this->prerequisites = $this->course->prerequisites ?: [''];
        $this->existingCoverImage = $this->course->cover_image;

        // Set active tab from query parameter if available
        if (request()->has('tab')) {
            $tab = request()->get('tab');
            switch ($tab) {
                case 'curriculum':
                    $this->currentStep = 2;
                    break;
                case 'outcomes':
                    $this->currentStep = 3;
                    break;
                case 'schedule':
                    $this->currentStep = 4;
                    break;
                default:
                    $this->currentStep = 1;
            }
        }

        $this->loadSubjects();
    }

    public function loadSubjects()
    {
        \Log::info('Loading subjects for teacher');
        if ($this->teacherProfile) {
            $this->availableSubjects = $this->teacherProfile->subjects()
                ->get()
                ->map(function($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name
                    ];
                })
                ->toArray();
            \Log::info('Loaded teacher subjects', ['count' => count($this->availableSubjects)]);
        } else {
            // Fallback to all subjects if teacher profile doesn't exist
            $this->availableSubjects = Subject::select('id', 'name')
                ->get()
                ->map(function($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name
                    ];
                })
                ->toArray();
            \Log::info('Loaded all subjects as fallback', ['count' => count($this->availableSubjects)]);
        }
    }

    // Auto-generate slug from name if it wasn't modified
    public function updatedName()
    {
        // Only update slug if it hasn't been manually edited
        if ($this->slug === $this->course->slug || $this->slug === Str::slug($this->course->name)) {
            $this->slug = Str::slug($this->name);
        }
    }

    // Add a new module
    public function addModule()
    {
        $this->modules[] = ['title' => '', 'description' => ''];
    }

    // Remove a module
    public function removeModule($index)
    {
        if (count($this->modules) > 1) {
            unset($this->modules[$index]);
            $this->modules = array_values($this->modules);
        }
    }

    // Add a new learning outcome
    public function addOutcome()
    {
        $this->outcomes[] = '';
    }

    // Remove a learning outcome
    public function removeOutcome($index)
    {
        if (count($this->outcomes) > 1) {
            unset($this->outcomes[$index]);
            $this->outcomes = array_values($this->outcomes);
        }
    }

    // Add a new prerequisite
    public function addPrerequisite()
    {
        $this->prerequisites[] = '';
    }

    // Remove a prerequisite
    public function removePrerequisite($index)
    {
        if (count($this->prerequisites) > 0) {
            unset($this->prerequisites[$index]);
            $this->prerequisites = array_values($this->prerequisites);
        }
    }

    // Navigation between steps
    public function nextStep()
    {
        $this->validateCurrentStep();

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    // Validate current step
    protected function validateCurrentStep()
    {
        \Log::info('Validating step', ['step' => $this->currentStep]);
        $rules = [];

        switch ($this->currentStep) {
            case 1:
                $rules = [
                    'name' => $this->rules()['name'],
                    'slug' => $this->rules()['slug'],
                    'description' => $this->rules()['description'],
                    'level' => $this->rules()['level'],
                    'subjectId' => $this->rules()['subjectId'],
                    'price' => $this->rules()['price'],
                    'status' => $this->rules()['status'],
                ];
                break;

            case 2:
                $rules = [
                    'modules' => $this->rules()['modules'],
                    'modules.*.title' => $this->rules()['modules.*.title'],
                    'modules.*.description' => $this->rules()['modules.*.description'],
                ];
                break;

            case 3:
                $rules = [
                    'outcomes' => $this->rules()['outcomes'],
                    'outcomes.*' => $this->rules()['outcomes.*'],
                    'prerequisites' => $this->rules()['prerequisites'],
                    'prerequisites.*' => $this->rules()['prerequisites.*'],
                ];
                break;

            case 4:
                $rules = [
                    'duration' => $this->rules()['duration'],
                    'maxStudents' => $this->rules()['maxStudents'],
                    'startDate' => $this->rules()['startDate'],
                    'endDate' => $this->rules()['endDate'],
                    'coverImage' => $this->rules()['coverImage'],
                ];
                break;
        }

        try {
            $this->validate($rules);
            \Log::info('Step validation passed', ['step' => $this->currentStep]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed for step', [
                'step' => $this->currentStep,
                'errors' => $e->errors()
            ]);
            throw $e;
        }
    }

    // Submit the form
    public function submit()
    {
        \Log::info('Starting course update process', ['course_id' => $this->course->id]);
        $this->validateCurrentStep();

        // Validate all fields before final submission
        $this->validate();
        \Log::info('All form validation passed');

        // Filter out empty prerequisites
        $filteredPrerequisites = array_filter($this->prerequisites, function($item) {
            return !empty($item);
        });
        \Log::info('Filtered prerequisites', ['count' => count($filteredPrerequisites)]);

        try {
            // Update course from the database
            $this->course->update([
                'name' => $this->name,
                'slug' => $this->slug,
                'description' => $this->description,
                'level' => $this->level,
                'duration' => $this->duration,
                'price' => $this->price,
                'subject_id' => $this->subjectId,
                'curriculum' => $this->modules,
                'prerequisites' => $filteredPrerequisites,
                'learning_outcomes' => $this->outcomes,
                'max_students' => $this->maxStudents,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'status' => $this->status
            ]);
            
            \Log::info('Course data updated successfully');

            // Handle cover image upload or removal
            if ($this->removeCoverImage) {
                \Log::info('Removing cover image');
                // Delete existing cover image
                if ($this->course->cover_image && Storage::disk('public')->exists($this->course->cover_image)) {
                    Storage::disk('public')->delete($this->course->cover_image);
                }
                $this->course->cover_image = null;
                $this->course->save();
                \Log::info('Cover image removed successfully');
            } elseif ($this->coverImage) {
                \Log::info('Uploading new cover image');
                // Delete old cover image if exists
                if ($this->course->cover_image && Storage::disk('public')->exists($this->course->cover_image)) {
                    Storage::disk('public')->delete($this->course->cover_image);
                }

                // Save new cover image
                $imagePath = $this->coverImage->storePublicly('course-covers', 'public');
                $this->course->cover_image = $imagePath;
                $this->course->save();
                \Log::info('Cover image uploaded successfully', ['path' => $imagePath]);
            }

            // Show success message
            $this->toast(
                type: 'success',
                title: 'Course updated',
                description: 'Your course has been updated successfully.',
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                css: 'alert-success',
                timeout: 3000
            );
            
            \Log::info('Course update completed, redirecting to course page');
            // Redirect to course page
            return redirect()->route('teachers.courses.show', $this->course->id);

        } catch (\Exception $e) {
            \Log::error('Course update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Show error message
            $this->toast(
                type: 'error',
                title: 'Error',
                description: 'There was an error updating your course: ' . $e->getMessage(),
                position: 'toast-bottom toast-end',
                icon: 'o-x-circle',
                css: 'alert-error',
                timeout: 5000
            );
        }
    }

    // Calculate progress percentage
    public function getProgressPercentage()
    {
        return ($this->currentStep - 1) / $this->totalSteps * 100;
    }

    // Get cover image URL
    public function getCoverImageUrl()
    {
        if ($this->existingCoverImage) {
            return Storage::disk('public')->url($this->existingCoverImage);
        }

        return null;
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
    <div class="max-w-4xl mx-auto">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Edit Course</h1>
                <p class="mt-1 text-base-content/70">Update your course information and settings</p>
            </div>
            <div>
                <a href="{{ route('teachers.courses.show', $course->id) }}" class="btn btn-outline">
                    <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                    Back to Course
                </a>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex justify-between mb-2">
                <span>Step {{ $currentStep }} of {{ $totalSteps }}</span>
                <span>{{ round($this->getProgressPercentage()) }}% complete</span>
            </div>
            <div class="w-full h-3 rounded-full bg-base-200">
                <div
                    class="h-3 rounded-full bg-primary"
                    style="width: {{ $this->getProgressPercentage() }}%"
                ></div>
            </div>
        </div>

        <!-- Step Indicators -->
        <div class="flex justify-center mb-8">
            @for ($i = 1; $i <= $totalSteps; $i++)
                <div class="flex items-center">
                    <div
                        class="flex items-center justify-center w-10 h-10 rounded-full border-2 {{ $i <= $currentStep ? 'border-primary bg-primary text-primary-content' : 'border-base-300 bg-base-100' }}"
                    >
                        @if ($i < $currentStep)
                            <x-icon name="o-check" class="w-5 h-5" />
                        @else
                            {{ $i }}
                        @endif
                    </div>
                    @if ($i < $totalSteps)
                        <div class="w-12 h-1 {{ $i < $currentStep ? 'bg-primary' : 'bg-base-300' }}"></div>
                    @endif
                </div>
            @endfor
        </div>

        <!-- Form Steps -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <!-- Step 1: Basic Information -->
                @if ($currentStep == 1)
                    <h2 class="text-xl font-bold">Basic Information</h2>
                    <div class="divider"></div>

                    <div class="grid grid-cols-1 gap-6">
                        <!-- Course Name -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Course Name</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="text"
                                wire:model="name"
                                class="input input-bordered @error('name') input-error @enderror"
                                placeholder="Enter course name"
                            />
                            @error('name') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Course Slug -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">URL Slug</span>
                                <span class="label-text-alt">Unique identifier for your course URL</span>
                            </label>
                            <input
                                type="text"
                                wire:model="slug"
                                class="input input-bordered @error('slug') input-error @enderror"
                                placeholder="course-url-slug"
                            />
                            @error('slug') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Course Description -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Description</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <textarea
                                wire:model="description"
                                class="h-32 textarea textarea-bordered @error('description') textarea-error @enderror"
                                placeholder="Describe your course and what students will learn"
                            ></textarea>
                            @error('description') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Two columns for Level and Subject -->
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Level -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Difficulty Level</span>
                                    <span class="label-text-alt text-error">Required</span>
                                </label>
                                <select
                                    wire:model="level"
                                    class="select select-bordered @error('level') select-error @enderror"
                                >
                                    @foreach($availableLevels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('level') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

                            <!-- Subject -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Subject</span>
                                    <span class="label-text-alt text-error">Required</span>
                                </label>
                                <select
                                    wire:model="subjectId"
                                    class="select select-bordered @error('subjectId') select-error @enderror"
                                >
                                    <option value="">Select a subject</option>
                                    @foreach($availableSubjects as $subject)
                                        <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('subjectId') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Price and Status in two columns -->
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Price -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Price</span>
                                    <span class="label-text-alt text-error">Required</span>
                                </label>
                                <div class="flex items-center">
                                    <span class="px-3 py-2 border rounded-l-lg bg-base-200 border-base-300">$</span>
                                    <input
                                        type="number"
                                        wire:model="price"
                                        step="0.01"
                                        min="0"
                                        class="w-full rounded-l-none input input-bordered @error('price') input-error @enderror"
                                        placeholder="0.00"
                                    />
                                </div>
                                @error('price') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

                            <!-- Status -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Status</span>
                                    <span class="label-text-alt text-error">Required</span>
                                </label>
                                <select
                                    wire:model="status"
                                    class="select select-bordered @error('status') select-error @enderror"
                                >
                                    @foreach($availableStatuses as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label class="label">
                                    <span class="label-text-alt">
                                        @if($status === 'draft')
                                            Draft courses are only visible to you
                                        @elseif($status === 'active')
                                            Active courses are visible to students
                                        @else
                                            Inactive courses are hidden from students
                                        @endif
                                    </span>
                                </label>
                                @error('status') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Step 2: Curriculum -->
                @if ($currentStep == 2)
                    <h2 class="text-xl font-bold">Course Curriculum</h2>
                    <div class="divider"></div>

                    <p class="mb-4 text-base-content/70">
                        Define the modules and topics that will be covered in your course.
                    </p>

                    @error('modules') <div class="mb-4 alert alert-error">{{ $message }}</div> @enderror

                    <div class="space-y-6">
                        @foreach($modules as $index => $module)
                            <div class="p-4 border rounded-lg border-base-300">
                                <div class="flex items-start justify-between mb-4">
                                    <h3 class="text-lg font-medium">Module {{ $index + 1 }}</h3>
                                    @if(count($modules) > 1)
                                        <button
                                            wire:click="removeModule({{ $index }})"
                                            type="button"
                                            class="btn btn-sm btn-ghost btn-circle"
                                        >
                                            <x-icon name="o-x-mark" class="w-5 h-5" />
                                        </button>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 gap-4">
                                    <!-- Module Title -->
                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text">Module Title</span>
                                            <span class="label-text-alt text-error">Required</span>
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="modules.{{ $index }}.title"
                                            class="input input-bordered @error('modules.'.$index.'.title') input-error @enderror"
                                            placeholder="e.g. Introduction to the Course"
                                        />
                                        @error('modules.'.$index.'.title') <span class="text-sm text-error">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Module Description -->
                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text">Module Description</span>
                                            <span class="label-text-alt text-error">Required</span>
                                        </label>
                                        <textarea
                                            wire:model="modules.{{ $index }}.description"
                                            class="textarea textarea-bordered @error('modules.'.$index.'.description') textarea-error @enderror"
                                            placeholder="Describe what will be covered in this module"
                                            rows="3"
                                        ></textarea>
                                        @error('modules.'.$index.'.description') <span class="text-sm text-error">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Module Completion Status -->
                                    <div class="form-control">
                                        <label class="cursor-pointer label">
                                            <span class="label-text">Mark as completed</span>
                                            <input
                                                type="checkbox"
                                                wire:model="modules.{{ $index }}.completed"
                                                class="checkbox checkbox-primary"
                                            />
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <button
                            wire:click="addModule"
                            type="button"
                            class="w-full btn btn-outline"
                        >
                            <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                            Add Module
                        </button>
                    </div>
                @endif

                <!-- Step 3: Learning Outcomes & Prerequisites -->
                @if ($currentStep == 3)
                    <h2 class="text-xl font-bold">Learning Outcomes & Prerequisites</h2>
                    <div class="divider"></div>

                    <!-- Learning Outcomes -->
                    <div class="mb-6">
                        <h3 class="mb-2 text-lg font-medium">Learning Outcomes</h3>
                        <p class="mb-4 text-base-content/70">
                            What will students be able to do after completing this course?
                        </p>

                        @error('outcomes') <div class="mb-4 alert alert-error">{{ $message }}</div> @enderror

                        <div class="space-y-4">
                            @foreach($outcomes as $index => $outcome)
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 form-control">
                                        <div class="input-group">
                                            <span class="bg-base-300">{{ $index + 1 }}</span>
                                            <input
                                                type="text"
                                                wire:model="outcomes.{{ $index }}"
                                                class="w-full input input-bordered @error('outcomes.'.$index) input-error @enderror"
                                                placeholder="e.g. Build a responsive website using modern CSS techniques"
                                            />
                                        </div>
                                        @error('outcomes.'.$index) <span class="text-sm text-error">{{ $message }}</span> @enderror
                                    </div>
                                    @if(count($outcomes) > 1)
                                        <button
                                            wire:click="removeOutcome({{ $index }})"
                                            type="button"
                                            class="btn btn-sm btn-ghost btn-circle"
                                        >
                                            <x-icon name="o-x-mark" class="w-5 h-5" />
                                        </button>
                                    @endif
                                </div>
                            @endforeach

                            <button
                                wire:click="addOutcome"
                                type="button"
                                class="w-full btn btn-outline btn-sm"
                            >
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Add Learning Outcome
                            </button>
                        </div>
                    </div>

                    <!-- Prerequisites -->
                    <div>
                        <h3 class="mb-2 text-lg font-medium">Prerequisites</h3>
                        <p class="mb-4 text-base-content/70">
                            What should students know before taking this course? (Optional)
                        </p>

                        <div class="space-y-4">
                            @foreach($prerequisites as $index => $prerequisite)
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 form-control">
                                        <div class="input-group">
                                            <span class="bg-base-300">{{ $index + 1 }}</span>
                                            <input
                                                type="text"
                                                wire:model="prerequisites.{{ $index }}"
                                                class="w-full input input-bordered"
                                                placeholder="e.g. Basic HTML knowledge"
                                            />
                                        </div>
                                    </div>
                                    <button
                                        wire:click="removePrerequisite({{ $index }})"
                                        type="button"
                                        class="btn btn-sm btn-ghost btn-circle"
                                    >
                                        <x-icon name="o-x-mark" class="w-5 h-5" />
                                    </button>
                                </div>
                            @endforeach

                            <button
                                wire:click="addPrerequisite"
                                type="button"
                                class="w-full btn btn-outline btn-sm"
                            >
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Add Prerequisite
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Step 4: Schedule & Additional Details -->
                @if ($currentStep == 4)
                    <h2 class="text-xl font-bold">Schedule & Additional Details</h2>
                    <div class="divider"></div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Duration -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Duration (weeks)</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="number"
                                wire:model="duration"
                                min="1"
                                max="52"
                                class="input input-bordered @error('duration') input-error @enderror"
                                placeholder="8"
                            />
                            @error('duration') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Max Students -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Maximum Students</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="number"
                                wire:model="maxStudents"
                                min="1"
                                max="100"
                                class="input input-bordered @error('maxStudents') input-error @enderror"
                                placeholder="20"
                            />
                            @error('maxStudents') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Start Date -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Start Date</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="date"
                                wire:model="startDate"
                                class="input input-bordered @error('startDate') input-error @enderror"
                            />
                            @error('startDate') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- End Date -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">End Date</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="date"
                                wire:model="endDate"
                                class="input input-bordered @error('endDate') input-error @enderror"
                            />
                            @error('endDate') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Cover Image -->
                    <div class="mt-6 form-control">
                        <label class="label">
                            <span class="label-text">Cover Image (Optional)</span>
                            <span class="label-text-alt">Max size: 2MB</span>
                        </label>

                        <div class="flex items-center gap-4">
                            @if($coverImage)
                                <div class="avatar">
                                    <div class="w-24 h-24 rounded-md">
                                        <img src="{{ $coverImage->temporaryUrl() }}" alt="Course cover preview" />
                                    </div>
                                </div>
                            @elseif($existingCoverImage && !$removeCoverImage)
                                <div class="avatar">
                                    <div class="w-24 h-24 rounded-md">
                                        <img src="{{ $this->getCoverImageUrl() }}" alt="Current course cover" />
                                    </div>
                                </div>
                            @endif

                            <div class="flex flex-col gap-2">
                                <input
                                    type="file"
                                    wire:model="coverImage"
                                    id="coverImage"
                                    class="w-full file-input file-input-bordered"
                                />

                                @if($existingCoverImage && !$removeCoverImage)
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            wire:model="removeCoverImage"
                                            id="removeCoverImage"
                                            class="checkbox checkbox-sm"
                                        />
                                        <label for="removeCoverImage" class="text-sm">Remove existing image</label>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @error('coverImage') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <!-- Course Status Notice -->
                    <div class="p-4 mt-6 border rounded-lg border-info bg-info/10">
                        <div class="flex gap-3">
                            <x-icon name="o-information-circle" class="flex-shrink-0 w-6 h-6 text-info" />
                            <div>
                                <h3 class="font-semibold text-info">Course Status</h3>
                                <p class="mt-1 text-sm">
                                    Current status: <strong>{{ ucfirst($status) }}</strong>
                                    <br>
                                    @if($status === 'draft')
                                        This course is only visible to you. Set to 'Active' to make it visible to students.
                                    @elseif($status === 'active')
                                        This course is visible to students and open for enrollment.
                                    @else
                                        This course is hidden from students but can be reactivated later.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Navigation Buttons -->
        <div class="flex justify-between mt-6">
            @if($currentStep > 1)
                <button
                    wire:click="previousStep"
                    class="btn btn-outline"
                >
                    <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                    Back
                </button>
            @else
                <div></div>
            @endif

            @if($currentStep < $totalSteps)
                <button
                    wire:click="nextStep"
                    class="btn btn-primary"
                >
                    Next
                    <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                </button>
            @else
                <button
                    wire:click="submit"
                    class="btn btn-primary"
                >
                    <x-icon name="o-check" class="w-4 h-4 mr-2" />
                    Save Changes
                </button>
            @endif
        </div>

        <!-- Information Box -->
        <div class="p-4 mt-8 shadow-md rounded-xl bg-primary/10 text-primary">
            <div class="flex gap-3">
                <x-icon name="o-light-bulb" class="flex-shrink-0 w-6 h-6" />
                <div>
                    <h3 class="font-semibold">Tips for Managing Your Course</h3>
                    <ul class="mt-1 space-y-1 text-sm list-disc list-inside">
                        <li>Use course status to control visibility to students</li>
                        <li>Mark modules as completed to track your teaching progress</li>
                        <li>Update your course details regularly to keep content fresh</li>
                        <li>Clear learning outcomes help students understand what they'll gain</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>