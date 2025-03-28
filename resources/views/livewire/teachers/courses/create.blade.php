<?php

namespace App\Livewire\Teachers\Courses;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Subject;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

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

    // Curriculum
    public $modules = [['title' => '', 'description' => '']];

    // Learning outcomes
    public $outcomes = [''];

    // Prerequisites
    public $prerequisites = [''];

    // Cover image
    public $coverImage;

    // Available subjects
    public $availableSubjects = [];

    // Available levels
    public $availableLevels = [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
        'all' => 'All Levels'
    ];

    // Step tracking
    public $currentStep = 1;
    public $totalSteps = 4;

    // Validation rules
    protected function rules()
    {
        return [
            'name' => 'required|string|min:5|max:100',
            'slug' => 'nullable|string|max:100|unique:courses,slug',
            'description' => 'required|string|min:20',
            'level' => 'required|string|in:beginner,intermediate,advanced,all',
            'duration' => 'required|integer|min:1|max:52',
            'price' => 'required|numeric|min:0',
            'subjectId' => 'required|exists:subjects,id',
            'maxStudents' => 'required|integer|min:1|max:100',
            'startDate' => 'required|date|after_or_equal:today',
            'endDate' => 'required|date|after:startDate',

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

    public function mount()
    {
        \Log::info('Mounting course create component');
        $this->user = Auth::user();
        $this->teacherProfile = $this->user->teacherProfile;

        if (!$this->teacherProfile) {
            \Log::warning('Teacher profile not found for user', ['user_id' => $this->user->id]);
        }

        // Set default dates
        $this->startDate = Carbon::now()->addWeek()->format('Y-m-d');
        $this->endDate = Carbon::now()->addWeeks(9)->format('Y-m-d');

        \Log::info('Loading subjects for teacher');
        $this->loadSubjects();
        \Log::info('Loaded subjects', ['count' => count($this->availableSubjects)]);
    }

    public function loadSubjects()
    {
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
        }

        // Set default subject if available
        if (count($this->availableSubjects) > 0) {
            $this->subjectId = $this->availableSubjects[0]['id'];
        }
    }

    // Auto-generate slug from name
    public function updatedName()
    {
        $this->slug = Str::slug($this->name);
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
  // Update the submit method to include detailed logging
public function submit()
{
    try {
        \Log::info('Starting course creation process');
        $this->validateCurrentStep();
        \Log::info('Current step validation passed');

        // Validate all fields before final submission
        $this->validate();
        \Log::info('All form validation passed');

        // Filter out empty prerequisites
        $filteredPrerequisites = array_filter($this->prerequisites, function($item) {
            return !empty($item);
        });
        \Log::info('Filtered prerequisites', ['count' => count($filteredPrerequisites)]);

        // Log form data before submission
        \Log::info('Form data being submitted', [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => substr($this->description, 0, 100) . '...',
            'level' => $this->level,
            'duration' => $this->duration,
            'price' => $this->price,
            'subject_id' => $this->subjectId,
            'teacher_profile_id' => $this->teacherProfile ? $this->teacherProfile->id : null,
            'modules_count' => count($this->modules),
            'max_students' => $this->maxStudents,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'has_cover_image' => $this->coverImage ? true : false
        ]);

        // Check if teacher profile exists
        if (!$this->teacherProfile) {
            \Log::error('Teacher profile not found for user', ['user_id' => $this->user->id]);
            throw new \Exception('Teacher profile not found. Please complete your profile first.');
        }

        // Create course
        $course = Course::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'level' => $this->level,
            'duration' => $this->duration,
            'price' => $this->price,
            'subject_id' => $this->subjectId,
            'teacher_profile_id' => $this->teacherProfile->id,
            'curriculum' => $this->modules,
            'prerequisites' => $filteredPrerequisites,
            'learning_outcomes' => $this->outcomes,
            'max_students' => $this->maxStudents,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => 'draft'
        ]);

        \Log::info('Course created successfully', ['course_id' => $course->id]);

        // Handle cover image upload
        if ($this->coverImage) {
            \Log::info('Uploading cover image');
            try {
                $imagePath = $this->coverImage->storePublicly('course-covers', 'public');
                $course->cover_image = $imagePath;
                $course->save();
                \Log::info('Cover image uploaded successfully', ['path' => $imagePath]);
            } catch (\Exception $imageException) {
                \Log::error('Cover image upload failed', [
                    'error' => $imageException->getMessage(),
                    'course_id' => $course->id
                ]);
                // Don't rethrow - course creation was successful, image is optional
            }
        }

        // Show success message
        $this->toast(
            type: 'success',
            title: 'Course created',
            description: 'Your course has been created successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        \Log::info('Course creation completed, redirecting to courses list');
        // Redirect to courses index
        return redirect()->route('teachers.courses');

    } catch (\Exception $e) {
        \Log::error('Course creation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Show error message
        $this->toast(
            type: 'error',
            title: 'Error',
            description: 'There was an error creating your course: ' . $e->getMessage(),
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
                <h1 class="text-3xl font-bold">Create New Course</h1>
                <p class="mt-1 text-base-content/70">Create and design a new course curriculum</p>
            </div>
            <div>
                <a href="{{ route('teachers.courses') }}" class="btn btn-outline">
                    <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                    Back to Courses
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
                                <span class="label-text-alt">Auto-generated from name</span>
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
                            @endif

                            <input
                                type="file"
                                wire:model="coverImage"
                                id="coverImage"
                                class="w-full file-input file-input-bordered"
                            />
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
                                    Your course will be created with <strong>Draft</strong> status. You can review and publish it from the courses dashboard when ready.
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
                    Create Course
                </button>
            @endif
        </div>

        <!-- Information Box -->
        <div class="p-4 mt-8 shadow-md rounded-xl bg-primary/10 text-primary">
            <div class="flex gap-3">
                <x-icon name="o-light-bulb" class="flex-shrink-0 w-6 h-6" />
                <div>
                    <h3 class="font-semibold">Tips for Creating Great Courses</h3>
                    <ul class="mt-1 space-y-1 text-sm list-disc list-inside">
                        <li>Be specific about what students will learn</li>
                        <li>Break content into logical, manageable modules</li>
                        <li>Include practical exercises and examples</li>
                        <li>Set realistic prerequisites to help students prepare</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
