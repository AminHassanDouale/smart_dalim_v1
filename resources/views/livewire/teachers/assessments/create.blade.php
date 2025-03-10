<?php

use Livewire\Volt\Component;
use App\Models\Assessment;
use App\Models\Subject;
use App\Models\Course;
use App\Models\Material;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $assessment = [
        'title' => '',
        'description' => '',
        'type' => 'quiz',
        'teacher_profile_id' => null,
        'course_id' => null,
        'subject_id' => null,
        'total_points' => 100,
        'passing_points' => 70,
        'due_date' => null,
        'start_date' => null,
        'time_limit' => 60, // in minutes
        'is_published' => false,
        'settings' => [
            'shuffle_questions' => false,
            'show_correct_answers' => true,
            'allow_retakes' => false,
            'max_retakes' => 1,
        ],
        'instructions' => '',
        'status' => 'draft'
    ];

    public $courses = [];
    public $subjects = [];
    public $materials = [];
    public $selectedMaterials = [];

    // Form validation rules
    protected function rules() {
        return [
            'assessment.title' => 'required|string|max:255',
            'assessment.description' => 'nullable|string',
            'assessment.type' => 'required|string|in:' . implode(',', array_keys(Assessment::$types)),
            'assessment.course_id' => 'nullable|exists:courses,id',
            'assessment.subject_id' => 'nullable|exists:subjects,id',
            'assessment.total_points' => 'required|integer|min:1',
            'assessment.passing_points' => 'nullable|integer|lte:assessment.total_points',
            'assessment.due_date' => 'nullable|date|after_or_equal:assessment.start_date',
            'assessment.start_date' => 'nullable|date',
            'assessment.time_limit' => 'nullable|integer|min:1',
            'assessment.instructions' => 'nullable|string',
            'selectedMaterials' => 'nullable|array',
            'selectedMaterials.*' => 'exists:materials,id',
        ];
    }

    public function mount()
    {
        $user = Auth::user();
        $this->assessment['teacher_profile_id'] = $user->teacherProfile->id;

        // Load courses, subjects, and materials for dropdown selects
        $this->courses = Course::where('teacher_profile_id', $user->teacherProfile->id)
            ->orWhere('status', 'active')
            ->orderBy('name')
            ->get();

        $this->subjects = Subject::orderBy('name')->get();
        $this->materials = Material::where('teacher_profile_id', $user->teacherProfile->id)
            ->orderBy('title')
            ->get();
    }

    public function getAssessmentTypesProperty()
    {
        return Assessment::$types;
    }

    public function createAssessment()
    {
        $this->validate();

        // Convert dates to proper format if provided
        if ($this->assessment['start_date']) {
            $this->assessment['start_date'] = date('Y-m-d H:i:s', strtotime($this->assessment['start_date']));
        }

        if ($this->assessment['due_date']) {
            $this->assessment['due_date'] = date('Y-m-d H:i:s', strtotime($this->assessment['due_date']));
        }

        // Convert settings to JSON
        $this->assessment['settings'] = json_encode($this->assessment['settings']);

        // Create the assessment
        $assessment = Assessment::create($this->assessment);

        // Attach selected materials if any
        if (!empty($this->selectedMaterials)) {
            $assessment->materials()->attach($this->selectedMaterials);
        }

        // Redirect to the questions page or detailed view
        return redirect()->route('teachers.assessments.questions.create', $assessment)
            ->with('success', 'Assessment created successfully! Now add some questions.');
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-3xl font-bold">Create New Assessment</h1>
                    <p class="mt-1 text-base-content/70">Design a new assessment for your students</p>
                </div>
                <div>
                    <a href="{{ route('teachers.assessments.index') }}" class="btn btn-outline">
                        <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                        Back to Assessments
                    </a>
                </div>
            </div>
        </div>

        <!-- Assessment Form -->
        <form wire:submit="createAssessment" class="space-y-8">
            <!-- Basic Information Card -->
            <div class="shadow-lg card bg-base-100">
                <div class="card-body">
                    <h2 class="text-xl font-bold card-title">Basic Information</h2>

                    <!-- Title & Description -->
                    <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-2">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Assessment Title *</span>
                            </label>
                            <input
                                type="text"
                                wire:model="assessment.title"
                                class="input input-bordered"
                                placeholder="Enter assessment title"
                            />
                            @error('assessment.title')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Assessment Type *</span>
                            </label>
                            <select wire:model="assessment.type" class="select select-bordered">
                                @foreach($this->assessmentTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('assessment.type')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Description</span>
                                </label>
                                <textarea
                                    wire:model="assessment.description"
                                    class="h-32 textarea textarea-bordered"
                                    placeholder="Enter assessment description"
                                ></textarea>
                                @error('assessment.description')
                                    <label class="label">
                                        <span class="text-error label-text-alt">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Course & Subject -->
                    <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-2">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Course (Optional)</span>
                            </label>
                            <select wire:model="assessment.course_id" class="select select-bordered">
                                <option value="">Select a course</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}">{{ $course->title }}</option>
                                @endforeach
                            </select>
                            @error('assessment.course_id')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Subject (Optional)</span>
                            </label>
                            <select wire:model="assessment.subject_id" class="select select-bordered">
                                <option value="">Select a subject</option>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                            @error('assessment.subject_id')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assessment Settings Card -->
            <div class="shadow-lg card bg-base-100">
                <div class="card-body">
                    <h2 class="text-xl font-bold card-title">Assessment Settings</h2>

                    <!-- Points & Timing -->
                    <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-3">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Total Points *</span>
                            </label>
                            <input
                                type="number"
                                wire:model="assessment.total_points"
                                class="input input-bordered"
                                min="1"
                            />
                            @error('assessment.total_points')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Passing Points</span>
                            </label>
                            <input
                                type="number"
                                wire:model="assessment.passing_points"
                                class="input input-bordered"
                                min="0"
                                max="{{ $assessment['total_points'] }}"
                            />
                            @error('assessment.passing_points')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Time Limit (minutes)</span>
                            </label>
                            <input
                                type="number"
                                wire:model="assessment.time_limit"
                                class="input input-bordered"
                                min="1"
                                placeholder="Leave empty for no limit"
                            />
                            @error('assessment.time_limit')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-2">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Start Date</span>
                            </label>
                            <input
                                type="datetime-local"
                                wire:model="assessment.start_date"
                                class="input input-bordered"
                            />
                            @error('assessment.start_date')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Due Date</span>
                            </label>
                            <input
                                type="datetime-local"
                                wire:model="assessment.due_date"
                                class="input input-bordered"
                            />
                            @error('assessment.due_date')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                    </div>

                    <!-- Assessment Options -->
                    <h3 class="mt-6 text-lg font-semibold">Assessment Options</h3>
                    <div class="grid grid-cols-1 gap-4 mt-2 md:grid-cols-2">
                        <div class="form-control">
                            <label class="cursor-pointer label">
                                <span class="label-text">Shuffle Questions</span>
                                <input
                                    type="checkbox"
                                    wire:model="assessment.settings.shuffle_questions"
                                    class="toggle toggle-primary"
                                />
                            </label>
                        </div>

                        <div class="form-control">
                            <label class="cursor-pointer label">
                                <span class="label-text">Show Correct Answers After Submission</span>
                                <input
                                    type="checkbox"
                                    wire:model="assessment.settings.show_correct_answers"
                                    class="toggle toggle-primary"
                                />
                            </label>
                        </div>

                        <div class="form-control">
                            <label class="cursor-pointer label">
                                <span class="label-text">Allow Retakes</span>
                                <input
                                    type="checkbox"
                                    wire:model="assessment.settings.allow_retakes"
                                    class="toggle toggle-primary"
                                />
                            </label>
                        </div>

                        @if($assessment['settings']['allow_retakes'])
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Maximum Retakes</span>
                                </label>
                                <input
                                    type="number"
                                    wire:model="assessment.settings.max_retakes"
                                    class="input input-bordered"
                                    min="1"
                                />
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Instructions & Materials Card -->
            <div class="shadow-lg card bg-base-100">
                <div class="card-body">
                    <h2 class="text-xl font-bold card-title">Instructions & Materials</h2>

                    <!-- Instructions -->
                    <div class="mt-4">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Instructions for Students</span>
                            </label>
                            <textarea
                                wire:model="assessment.instructions"
                                class="h-32 textarea textarea-bordered"
                                placeholder="Enter instructions for students taking this assessment"
                            ></textarea>
                            @error('assessment.instructions')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                    </div>

                    <!-- Materials -->
                    <div class="mt-4">
                        <label class="label">
                            <span class="label-text">Attach Materials (Optional)</span>
                        </label>

                        @if($materials->isEmpty())
                            <div class="p-4 text-center bg-base-200 rounded-box">
                                <p class="text-base-content/70">No materials available. Upload materials first.</p>
                                <a href="{{ route('teachers.materials.index') }}" class="mt-2 btn btn-sm btn-outline">
                                    Manage Materials
                                </a>
                            </div>
                        @else
                            <div class="p-4 bg-base-200 rounded-box">
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
                                    @foreach($materials as $material)
                                        <div class="flex items-center">
                                            <label class="flex items-center gap-2 cursor-pointer label">
                                                <input
                                                    type="checkbox"
                                                    wire:model="selectedMaterials"
                                                    value="{{ $material->id }}"
                                                    class="checkbox checkbox-primary"
                                                />
                                                <div>
                                                    <div class="font-medium">{{ $material->title }}</div>
                                                    <div class="text-xs text-base-content/70">{{ $material->type }}</div>
                                                </div>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @error('selectedMaterials')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-2">
                <button type="reset" class="btn btn-outline">
                    Reset Form
                </button>
                <button type="submit" class="btn btn-primary">
                    Create Assessment & Add Questions
                </button>
            </div>
        </form>
    </div>
</div>
