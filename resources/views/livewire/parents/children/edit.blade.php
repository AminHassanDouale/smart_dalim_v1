<?php

namespace App\Livewire\Parents\Children;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Children;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    // User and profile data
    public $user;
    public $parentProfile;
    public $child;

    // Child data form
    public $childData = [
        'name' => '',
        'age' => '',
        'date_of_birth' => '',
        'gender' => '',
        'grade' => '',
        'school_name' => '',
        'teacher_id' => null,
        'learning_style' => '',
        'interests' => [],
        'special_needs' => '',
        'allergies' => '',
        'available_times' => [],
        'preferred_subjects' => [],
        'goals' => '',
    ];

    // Child photo
    public $photo;
    public $existingPhoto;

    // Steps management
    public $currentStep = 1;
    public $totalSteps = 4;

    // Available teachers
    public $availableTeachers = [];

    // Available subjects
    public $availableSubjects = [];

    // UI States
    public $subjectSearch = '';
    public $showPhotoPreview = false;
    public $showSuccessMessage = false;
    public $showFormErrors = false;

    public function mount($child)
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        if (!$this->parentProfile) {
            return redirect()->route('parents.profile-setup');
        }

        // Get the child data
        $this->child = Children::where('id', $child)
            ->where('parent_profile_id', $this->parentProfile->id)
            ->with(['subjects', 'teacher'])
            ->firstOrFail();

        // Load child data into the form
        $this->loadChildData();
        $this->loadTeachers();
        $this->loadSubjects();
    }

    private function loadChildData()
    {
        $this->childData = [
            'name' => $this->child->name,
            'age' => $this->child->age,
            'date_of_birth' => $this->child->date_of_birth ? Carbon::parse($this->child->date_of_birth)->format('Y-m-d') : null,
            'gender' => $this->child->gender,
            'grade' => $this->child->grade,
            'school_name' => $this->child->school_name,
            'teacher_id' => $this->child->teacher_id,
            'learning_style' => $this->child->learning_style ?? '',
            'interests' => $this->child->interests ?? [],
            'special_needs' => $this->child->special_needs ?? '',
            'allergies' => $this->child->allergies ?? '',
            'available_times' => $this->child->available_times ?? [],
            'preferred_subjects' => $this->child->subjects->pluck('id')->toArray() ?? [],
            'goals' => $this->child->goals ?? '',
        ];

        // Store the existing photo
        $this->existingPhoto = $this->child->photo;
    }

    private function loadTeachers()
    {
        // In a real app, you would fetch actual teachers from the database
        // Either based on authentication or from User model with teacher role
        $this->availableTeachers = User::where('role', 'teacher')
            ->with('teacherProfile')
            ->get()
            ->map(function ($teacher) {
                $subjects = $teacher->teacherProfile ? $teacher->teacherProfile->subjects->pluck('name')->join(', ') : '';
                return [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'rating' => $teacher->teacherProfile ? rand(40, 50) / 10 : 4.5, // Placeholder for rating
                    'subject' => $subjects ?: 'Various Subjects'
                ];
            })
            ->toArray();

        // If no teachers found, use mock data
        if (empty($this->availableTeachers)) {
            $this->availableTeachers = [
                ['id' => 1, 'name' => 'Sarah Johnson', 'rating' => 4.9, 'subject' => 'Mathematics'],
                ['id' => 2, 'name' => 'Michael Chen', 'rating' => 4.7, 'subject' => 'Science'],
                ['id' => 3, 'name' => 'Emily Rodriguez', 'rating' => 4.8, 'subject' => 'English'],
                ['id' => 4, 'name' => 'David Wilson', 'rating' => 4.6, 'subject' => 'History'],
                ['id' => 5, 'name' => 'James Anderson', 'rating' => 4.5, 'subject' => 'Programming'],
            ];
        }
    }

    private function loadSubjects()
    {
        // Fetch from database
        $this->availableSubjects = Subject::all()->toArray() ?? [
            ['id' => 1, 'name' => 'Mathematics', 'description' => 'Basic and advanced mathematics'],
            ['id' => 2, 'name' => 'Science', 'description' => 'Physics, chemistry, and biology'],
            ['id' => 3, 'name' => 'English', 'description' => 'Reading, writing, and language arts'],
            ['id' => 4, 'name' => 'History', 'description' => 'World and local history'],
            ['id' => 5, 'name' => 'Programming', 'description' => 'Introduction to coding'],
            ['id' => 6, 'name' => 'Art', 'description' => 'Drawing, painting, and creative expression'],
            ['id' => 7, 'name' => 'Music', 'description' => 'Instrumental and vocal music education'],
            ['id' => 8, 'name' => 'Physical Education', 'description' => 'Sports and physical fitness'],
        ];
    }

    public function nextStep()
    {
        // Validate current step
        $this->validateCurrentStep();

        // Calculate date of birth from age if needed
        if ($this->currentStep === 1 && $this->childData['age'] && !$this->childData['date_of_birth']) {
            $this->childData['date_of_birth'] = Carbon::now()->subYears($this->childData['age'])->format('Y-m-d');
        }

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

    public function validateCurrentStep()
    {
        $this->showFormErrors = true;

        if ($this->currentStep === 1) {
            $this->validate([
                'childData.name' => 'required|min:2',
                'childData.gender' => 'required|in:male,female,other',
                'photo' => 'nullable|image|max:1024',
                'childData.age' => 'required_without:childData.date_of_birth|nullable|numeric|min:3|max:18',
                'childData.date_of_birth' => 'required_without:childData.age|nullable|date|before:today',
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'childData.grade' => 'required',
                'childData.school_name' => 'required|min:2',
                'childData.teacher_id' => 'nullable',
                'childData.learning_style' => 'nullable',
            ]);
        } elseif ($this->currentStep === 3) {
            $this->validate([
                'childData.interests' => 'nullable|array',
                'childData.special_needs' => 'nullable',
                'childData.allergies' => 'nullable',
                'childData.available_times' => 'required|array|min:1',
                'childData.preferred_subjects' => 'required|array|min:1',
            ]);
        }

        $this->showFormErrors = false;
    }

    public function goToStep($step)
    {
        // Validate current step before allowing navigation
        if ($step > $this->currentStep) {
            for ($i = 1; $i < $step; $i++) {
                $this->currentStep = $i;
                $this->validateCurrentStep();
            }
        }

        $this->currentStep = $step;
    }

    public function toggleTimeSlot($slot)
    {
        if (in_array($slot, $this->childData['available_times'])) {
            $this->childData['available_times'] = array_diff($this->childData['available_times'], [$slot]);
        } else {
            $this->childData['available_times'][] = $slot;
        }
    }

    public function toggleSubject($subjectId)
    {
        if (in_array($subjectId, $this->childData['preferred_subjects'])) {
            $this->childData['preferred_subjects'] = array_diff($this->childData['preferred_subjects'], [$subjectId]);
        } else {
            $this->childData['preferred_subjects'][] = $subjectId;
        }
    }

    public function toggleInterest($interest)
    {
        if (in_array($interest, $this->childData['interests'])) {
            $this->childData['interests'] = array_diff($this->childData['interests'], [$interest]);
        } else {
            $this->childData['interests'][] = $interest;
        }
    }

    public function filterSubjects()
    {
        if (empty($this->subjectSearch)) {
            return $this->availableSubjects;
        }

        // Filter subjects by search term
        return collect($this->availableSubjects)->filter(function($subject) {
            return stripos($subject['name'], $this->subjectSearch) !== false;
        })->values()->all();
    }

    public function removePhoto()
    {
        $this->photo = null;
        $this->existingPhoto = null;
    }

    public function calculateAge()
    {
        if ($this->childData['date_of_birth']) {
            $this->childData['age'] = Carbon::parse($this->childData['date_of_birth'])->age;
        }
    }

    public function updateChild()
    {
        // Final validation of all steps
        $this->validate([
            'childData.name' => 'required|min:2',
            'childData.gender' => 'required|in:male,female,other',
            'childData.age' => 'required_without:childData.date_of_birth|nullable|numeric|min:3|max:18',
            'childData.date_of_birth' => 'required_without:childData.age|nullable|date|before:today',
            'childData.grade' => 'required',
            'childData.school_name' => 'required|min:2',
            'childData.available_times' => 'required|array|min:1',
            'childData.preferred_subjects' => 'required|array|min:1',
            'childData.goals' => 'required|min:5',
        ]);

        // Process photo if uploaded
        $photoPath = $this->existingPhoto;
        if ($this->photo) {
            // Delete the old photo if it exists
            if ($this->existingPhoto) {
                Storage::disk('public')->delete($this->existingPhoto);
            }
            $photoPath = $this->photo->store('child-photos', 'public');
        } elseif ($this->existingPhoto === null && $this->child->photo) {
            // If photo was removed
            Storage::disk('public')->delete($this->child->photo);
            $photoPath = null;
        }

        // Update child record
        $this->child->update([
            'name' => $this->childData['name'],
            'age' => $this->childData['age'],
            'date_of_birth' => $this->childData['date_of_birth'],
            'gender' => $this->childData['gender'],
            'grade' => $this->childData['grade'],
            'school_name' => $this->childData['school_name'],
            'teacher_id' => $this->childData['teacher_id'],
            'photo' => $photoPath,
            'learning_style' => $this->childData['learning_style'],
            'special_needs' => $this->childData['special_needs'],
            'allergies' => $this->childData['allergies'],
            'available_times' => $this->childData['available_times'],
            'interests' => $this->childData['interests'],
            'goals' => $this->childData['goals'],
        ]);

        // Sync subjects
        $this->child->subjects()->sync($this->childData['preferred_subjects']);

        // Show success message temporarily
        $this->showSuccessMessage = true;

        // Redirect after 2 seconds
        $this->dispatch('refreshComponent');
    }

    public function getTimeSlots()
    {
        return [
            'morning' => 'Morning (8am - 12pm)',
            'afternoon' => 'Afternoon (12pm - 4pm)',
            'evening' => 'Evening (4pm - 8pm)',
            'weekend_morning' => 'Weekend Morning',
            'weekend_afternoon' => 'Weekend Afternoon',
            'weekend_evening' => 'Weekend Evening',
        ];
    }

    public function getInterestOptions()
    {
        return [
            'reading' => 'Reading',
            'sports' => 'Sports',
            'music' => 'Music',
            'art' => 'Art & Crafts',
            'science' => 'Science Experiments',
            'coding' => 'Coding/Technology',
            'games' => 'Games & Puzzles',
            'nature' => 'Nature & Outdoors',
            'cooking' => 'Cooking',
            'languages' => 'Languages',
            'writing' => 'Writing & Storytelling',
            'history' => 'History & Culture',
            'math' => 'Math & Logic',
            'robotics' => 'Robotics & Engineering',
            'drama' => 'Drama & Performance',
        ];
    }

    public function getLearningStyles()
    {
        return [
            '' => 'Select learning style',
            'visual' => 'Visual (learns best by seeing)',
            'auditory' => 'Auditory (learns best by hearing)',
            'reading' => 'Reading/Writing (learns best by reading and writing)',
            'kinesthetic' => 'Kinesthetic (learns best by doing)',
            'mixed' => 'Mixed (combination of styles)',
        ];
    }

    public function getGradeOptions()
    {
        return [
            '' => 'Select grade',
            'Preschool' => 'Preschool',
            'Kindergarten' => 'Kindergarten',
            'Grade 1' => 'Grade 1',
            'Grade 2' => 'Grade 2',
            'Grade 3' => 'Grade 3',
            'Grade 4' => 'Grade 4',
            'Grade 5' => 'Grade 5',
            'Grade 6' => 'Grade 6',
            'Grade 7' => 'Grade 7',
            'Grade 8' => 'Grade 8',
            'Grade 9' => 'Grade 9',
            'Grade 10' => 'Grade 10',
            'Grade 11' => 'Grade 11',
            'Grade 12' => 'Grade 12',
        ];
    }
}; ?>

<div
    x-data="{
        showPhotoPreview: false,
        animateStepChange: false,
        animateDirection: 'forward'
    }"
    class="min-h-screen p-6 bg-base-200"
>
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-3xl font-bold">Edit Child Profile</h1>
                <p class="mt-1 text-base-content/70">Update {{ $child->name }}'s profile information and learning preferences</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('parents.children.show', $child->id) }}" class="gap-2 btn btn-outline">
                    <x-icon name="o-arrow-left" class="w-4 h-4" />
                    <span>Back to Profile</span>
                </a>
                <a href="{{ route('parents.children.index') }}" class="gap-2 btn btn-ghost btn-sm">
                    <x-icon name="o-user-group" class="w-4 h-4" />
                    <span>All Children</span>
                </a>
            </div>
        </div>

        @if($showSuccessMessage)
            <div class="mb-6 alert alert-success">
                <x-icon name="o-check-circle" class="w-6 h-6" />
                <span>Child profile updated successfully!</span>
            </div>
        @endif

        <!-- Progress Indicator -->
        <div class="mb-8">
            <div class="hidden steps sm:flex">
                <button
                    wire:click="goToStep(1)"
                    class="step {{ $currentStep >= 1 ? 'step-primary' : '' }}"
                >
                    Personal Info
                </button>
                <button
                    wire:click="goToStep(2)"
                    class="step {{ $currentStep >= 2 ? 'step-primary' : '' }}"
                >
                    Academic Details
                </button>
                <button
                    wire:click="goToStep(3)"
                    class="step {{ $currentStep >= 3 ? 'step-primary' : '' }}"
                >
                    Preferences
                </button>
                <button
                    wire:click="goToStep(4)"
                    class="step {{ $currentStep >= 4 ? 'step-primary' : '' }}"
                >
                    Goals & Review
                </button>
            </div>

            <!-- Mobile Progress Indicator -->
            <div class="flex items-center justify-between mb-2 sm:hidden">
                <span class="text-sm font-medium">Step {{ $currentStep }} of {{ $totalSteps }}</span>
                <div class="text-sm">
                    {{ $currentStep === 1 ? 'Personal Info' :
                       ($currentStep === 2 ? 'Academic Details' :
                       ($currentStep === 3 ? 'Preferences' : 'Goals & Review')) }}
                </div>
            </div>
            <div class="w-full h-2 mb-2 overflow-hidden rounded-full sm:hidden bg-base-300">
                <div class="h-full transition-all duration-500 bg-primary" style="width: {{ ($currentStep / $totalSteps) * 100 }}%"></div>
            </div>
        </div>

        <!-- Main Form Card -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <form wire:submit="updateChild">
                    <!-- Step 1: Personal Information -->
                    <div
                        x-show="true"
                        x-transition:enter="transition ease-out duration-300 transform"
                        x-transition:enter-start="opacity-0 translate-x-8"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="transition ease-in duration-300 transform"
                        x-transition:leave-start="opacity-100 translate-x-0"
                        x-transition:leave-end="opacity-0 -translate-x-8"
                        class="{{ $currentStep === 1 ? 'block' : 'hidden' }}"
                    >
                        <h2 class="flex items-center mb-6 text-xl font-bold">
                            <div class="flex items-center justify-center w-8 h-8 mr-2 rounded-lg bg-primary text-primary-content">
                                <x-icon name="o-user" class="w-5 h-5" />
                            </div>
                            Personal Information
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <!-- Profile Photo -->
                            <div class="flex flex-col items-center gap-6 mb-4 md:col-span-2 md:flex-row">
                                <div
                                    @click="showPhotoPreview = true"
                                    class="relative rounded-full cursor-pointer group avatar"
                                >
                                    <div class="w-24 h-24 rounded-full bg-base-300">
                                        @if($photo)
                                            <img src="{{ $photo->temporaryUrl() }}" alt="Child Photo Preview" />
                                        @elseif($existingPhoto)
                                            <img src="{{ Storage::url($existingPhoto) }}" alt="{{ $childData['name'] }}" />
                                        @else
                                            <div class="flex items-center justify-center w-full h-full text-3xl font-bold text-base-content/30">
                                                {{ substr($childData['name'], 0, 1) ?: 'C' }}
                                            </div>
                                        @endif
                                        <div class="absolute inset-0 flex items-center justify-center transition-opacity duration-300 rounded-full opacity-0 bg-black/40 group-hover:opacity-100">
                                            <div class="text-white">
                                                <x-icon name="o-eye" class="w-6 h-6" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <div class="flex gap-2">
                                        <label class="btn btn-primary btn-sm">
                                            <x-icon name="o-camera" class="w-4 h-4 mr-2" />
                                            Change Photo
                                            <input
                                                type="file"
                                                wire:model="photo"
                                                class="hidden"
                                                accept="image/*"
                                            />
                                        </label>

                                        @if($photo || $existingPhoto)
                                            <button
                                                type="button"
                                                wire:click="removePhoto"
                                                class="btn btn-outline btn-error btn-sm"
                                            >
                                                <x-icon name="o-x-mark" class="w-4 h-4 mr-1" />
                                                Remove
                                            </button>
                                        @endif
                                    </div>

                                    <div class="text-xs text-base-content/70">
                                        JPEG, PNG or GIF, max 1MB. A square image works best.
                                    </div>
                                    @error('photo')
                                        <span class="text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Name -->
                            <div class="form-control md:col-span-2">
                                <label class="label">
                                    <span class="label-text">Child's Full Name</span>
                                    <span class="label-text-alt text-error">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="childData.name"
                                    class="input input-bordered"
                                    placeholder="Enter your child's full name"
                                />
                                @error('childData.name')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Age and DOB group -->
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:col-span-2">
                                <!-- Age -->
                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text">Age</span>
                                        <span class="label-text-alt">Years</span>
                                    </label>
                                    <input
                                        type="number"
                                        wire:model.live="childData.age"
                                        class="input input-bordered"
                                        min="3"
                                        max="18"
                                        placeholder="Enter age"
                                    />
                                    @error('childData.age')
                                        <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Date of Birth -->
                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text">Date of Birth</span>
                                        <span class="label-text-alt">Updates age automatically</span>
                                    </label>
                                    <input
                                        type="date"
                                        wire:model.live="childData.date_of_birth"
                                        wire:change="calculateAge"
                                        class="input input-bordered"
                                    />
                                    @error('childData.date_of_birth')
                                        <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Gender -->
                            <div class="form-control md:col-span-2">
                                <label class="label">
                                    <span class="label-text">Gender</span>
                                    <span class="label-text-alt text-error">*</span>
                                </label>
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            value="male"
                                            wire:model="childData.gender"
                                            class="radio radio-primary"
                                        />
                                        <span>Male</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            value="female"
                                            wire:model="childData.gender"
                                            class="radio radio-primary"
                                        />
                                        <span>Female</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            value="other"
                                            wire:model="childData.gender"
                                            class="radio radio-primary"
                                        />
                                        <span>Other</span>
                                    </label>
                                </div>
                                @error('childData.gender')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Academic Details -->
                    <div
                        x-show="true"
                        x-transition:enter="transition ease-out duration-300 transform"
                        x-transition:enter-start="opacity-0 translate-x-8"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="transition ease-in duration-300 transform"
                        x-transition:leave-start="opacity-100 translate-x-0"
                        x-transition:leave-end="opacity-0 -translate-x-8"
                        class="{{ $currentStep === 2 ? 'block' : 'hidden' }}"
                    >
                        <h2 class="flex items-center mb-6 text-xl font-bold">
                            <div class="flex items-center justify-center w-8 h-8 mr-2 rounded-lg bg-primary text-primary-content">
                                <x-icon name="o-academic-cap" class="w-5 h-5" />
                            </div>
                            Academic Details
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <!-- Grade -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Current Grade</span>
                                    <span class="label-text-alt text-error">*</span>
                                </label>
                                <select wire:model="childData.grade" class="select select-bordered">
                                    @foreach($this->getGradeOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('childData.grade')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- School Name -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">School Name</span>
                                    <span class="label-text-alt text-error">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="childData.school_name"
                                    class="input input-bordered"
                                    placeholder="Enter school name"
                                />
                                @error('childData.school_name')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Learning Style -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Learning Style</span>
                                    <div class="tooltip tooltip-right" data-tip="Helps teachers tailor instruction to your child's needs">
                                        <x-icon name="o-information-circle" class="w-4 h-4 text-info" />
                                    </div>
                                </label>
                                <select wire:model="childData.learning_style" class="select select-bordered">
                                    @foreach($this->getLearningStyles() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('childData.learning_style')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Preferred Teacher -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Preferred Teacher</span>
                                    @error('childData.learning_style')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Preferred Teacher -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Preferred Teacher</span>
                                    <span class="label-text-alt">Optional</span>
                                </label>
                                <select wire:model="childData.teacher_id" class="select select-bordered">
                                    <option value="">No preference</option>
                                    @foreach($availableTeachers as $teacher)
                                        <option value="{{ $teacher['id'] }}">{{ $teacher['name'] }} ({{ $teacher['subject'] }})</option>
                                    @endforeach
                                </select>
                                @error('childData.teacher_id')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Special Needs -->
                            <div class="form-control md:col-span-2">
                                <label class="label">
                                    <span class="label-text">Special Needs or Accommodations</span>
                                    <span class="label-text-alt">Optional</span>
                                </label>
                                <textarea
                                    wire:model="childData.special_needs"
                                    class="h-20 textarea textarea-bordered"
                                    placeholder="Enter any special needs, accommodations, or learning disabilities if applicable"
                                ></textarea>
                                @error('childData.special_needs')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Allergies -->
                            <div class="form-control md:col-span-2">
                                <label class="label">
                                    <span class="label-text">Allergies or Medical Information</span>
                                    <span class="label-text-alt">Optional</span>
                                </label>
                                <textarea
                                    wire:model="childData.allergies"
                                    class="h-20 textarea textarea-bordered"
                                    placeholder="Enter any allergies or important medical information if applicable"
                                ></textarea>
                                @error('childData.allergies')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Preferences -->
                    <div
                        x-show="true"
                        x-transition:enter="transition ease-out duration-300 transform"
                        x-transition:enter-start="opacity-0 translate-x-8"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="transition ease-in duration-300 transform"
                        x-transition:leave-start="opacity-100 translate-x-0"
                        x-transition:leave-end="opacity-0 -translate-x-8"
                        class="{{ $currentStep === 3 ? 'block' : 'hidden' }}"
                    >
                        <h2 class="flex items-center mb-6 text-xl font-bold">
                            <div class="flex items-center justify-center w-8 h-8 mr-2 rounded-lg bg-primary text-primary-content">
                                <x-icon name="o-heart" class="w-5 h-5" />
                            </div>
                            Preferences & Interests
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                            <!-- Available Times -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="font-medium label-text">Available Times for Learning</span>
                                    <span class="label-text-alt text-error">*</span>
                                </label>
                                <div class="flex flex-col gap-2">
                                    @foreach($this->getTimeSlots() as $value => $label)
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                value="{{ $value }}"
                                                wire:click="toggleTimeSlot('{{ $value }}')"
                                                @if(in_array($value, $childData['available_times'])) checked @endif
                                                class="checkbox checkbox-primary"
                                            />
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('childData.available_times')
                                    <span class="mt-2 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Interests -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="font-medium label-text">Interests & Hobbies</span>
                                    <span class="label-text-alt">Optional</span>
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($this->getInterestOptions() as $value => $label)
                                        <label class="flex items-center gap-2 cursor-pointer border rounded-full px-3 py-1 hover:bg-base-200 transition-colors {{ in_array($value, $childData['interests']) ? 'bg-primary/10 border-primary' : '' }}">
                                            <input
                                                type="checkbox"
                                                value="{{ $value }}"
                                                wire:click="toggleInterest('{{ $value }}')"
                                                @if(in_array($value, $childData['interests'])) checked @endif
                                                class="checkbox checkbox-xs checkbox-primary"
                                            />
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('childData.interests')
                                    <span class="mt-2 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Preferred Subjects -->
                            <div class="form-control md:col-span-2">
                                <label class="label">
                                    <span class="font-medium label-text">Preferred Subjects</span>
                                    <span class="label-text-alt text-error">*</span>
                                </label>

                                <div class="mb-3">
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="subjectSearch"
                                        class="w-full input input-bordered md:w-1/2"
                                        placeholder="Search subjects..."
                                    />
                                </div>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    @foreach($this->filterSubjects() as $subject)
                                        <label class="flex items-center justify-between gap-2 p-3 border rounded-lg cursor-pointer hover:bg-base-200 transition-colors {{ in_array($subject['id'], $childData['preferred_subjects']) ? 'bg-primary/10 border-primary' : '' }}">
                                            <div>
                                                <div class="font-medium">{{ $subject['name'] }}</div>
                                                <div class="text-xs opacity-70">{{ $subject['description'] ?? 'Subject description' }}</div>
                                            </div>
                                            <input
                                                type="checkbox"
                                                wire:click="toggleSubject({{ $subject['id'] }})"
                                                @if(in_array($subject['id'], $childData['preferred_subjects'])) checked @endif
                                                class="checkbox checkbox-primary"
                                            />
                                        </label>
                                    @endforeach
                                </div>
                                @error('childData.preferred_subjects')
                                    <span class="mt-2 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Goals & Review -->
                    <div
                        x-show="true"
                        x-transition:enter="transition ease-out duration-300 transform"
                        x-transition:enter-start="opacity-0 translate-x-8"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="transition ease-in duration-300 transform"
                        x-transition:leave-start="opacity-100 translate-x-0"
                        x-transition:leave-end="opacity-0 -translate-x-8"
                        class="{{ $currentStep === 4 ? 'block' : 'hidden' }}"
                    >
                        <h2 class="flex items-center mb-6 text-xl font-bold">
                            <div class="flex items-center justify-center w-8 h-8 mr-2 rounded-lg bg-primary text-primary-content">
                                <x-icon name="o-flag" class="w-5 h-5" />
                            </div>
                            Learning Goals & Review
                        </h2>

                        <!-- Learning Goals -->
                        <div class="mb-6 form-control">
                            <label class="label">
                                <span class="font-medium label-text">Learning Goals</span>
                                <span class="label-text-alt text-error">*</span>
                            </label>
                            <textarea
                                wire:model="childData.goals"
                                class="h-32 textarea textarea-bordered"
                                placeholder="What would you like your child to achieve through these learning sessions? What specific skills or knowledge are you hoping for them to develop?"
                            ></textarea>
                            @error('childData.goals')
                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Review Summary -->
                        <div class="p-6 rounded-lg bg-base-200">
                            <h3 class="mb-4 font-bold">Review Information</h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                                <div>
                                    <h4 class="font-medium">Personal Information</h4>
                                    <div class="mt-2 space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Name:</span>
                                            <span class="text-sm">{{ $childData['name'] ?: 'Not provided' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Age:</span>
                                            <span class="text-sm">{{ $childData['age'] ?: 'Not provided' }} years</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Gender:</span>
                                            <span class="text-sm capitalize">{{ $childData['gender'] ?: 'Not provided' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-medium">Academic Details</h4>
                                    <div class="mt-2 space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Grade:</span>
                                            <span class="text-sm">{{ $childData['grade'] ?: 'Not provided' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">School:</span>
                                            <span class="text-sm">{{ $childData['school_name'] ?: 'Not provided' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Learning Style:</span>
                                            <span class="text-sm">{{ $childData['learning_style'] ?: 'Not specified' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-medium">Preferences</h4>
                                    <div class="mt-2 space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Available Times:</span>
                                            <span class="text-sm">{{ count($childData['available_times']) }} selected</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Subjects:</span>
                                            <span class="text-sm">{{ count($childData['preferred_subjects']) }} selected</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Interests:</span>
                                            <span class="text-sm">{{ count($childData['interests']) }} selected</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="font-medium">Special Considerations</h4>
                                    <div class="mt-2 space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Special Needs:</span>
                                            <span class="text-sm">{{ $childData['special_needs'] ? 'Provided' : 'None' }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm opacity-70">Allergies:</span>
                                            <span class="text-sm">{{ $childData['allergies'] ? 'Provided' : 'None' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 alert alert-info">
                            <x-icon name="o-information-circle" class="w-6 h-6" />
                            <span>Please review all information carefully before saving changes.</span>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="flex justify-between mt-8">
                        @if($currentStep > 1)
                            <button
                                type="button"
                                wire:click="previousStep"
                                class="btn btn-outline"
                            >
                                <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                Previous
                            </button>
                        @else
                            <div></div>
                        @endif

                        @if($currentStep < $totalSteps)
                            <button
                                type="button"
                                wire:click="nextStep"
                                class="btn btn-primary"
                            >
                                Next
                                <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                            </button>
                        @else
                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Save Changes
                                <x-icon name="o-check" class="w-4 h-4 ml-2" />
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Help & Tips Section -->
        <div class="p-4 mt-8 rounded-lg bg-primary bg-opacity-10">
            <div class="flex items-start gap-4">
                <x-icon name="o-information-circle" class="w-6 h-6 mt-1 text-primary" />
                <div>
                    <h3 class="font-medium">Tips for Updating Your Child's Profile</h3>
                    <ul class="mt-2 space-y-1 text-sm">
                        <li>• Providing accurate information helps us match your child with the right teachers</li>
                        <li>• Be specific about learning goals and preferences to personalize the learning experience</li>
                        <li>• Regular updates to your child's profile ensures we adapt to their changing needs</li>
                        <li>• You can update this information anytime as your child's interests or needs change</li>
                    </ul>
                    <p class="mt-3 text-sm">
                        Need help? <a href="#" class="text-primary hover:underline">View our guide</a> or
                        <a href="#" class="text-primary hover:underline">contact support</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Preview Modal -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
        x-show="showPhotoPreview"
        x-transition
        @click.self="showPhotoPreview = false"
    >
        <div class="max-w-3xl p-4 mx-auto bg-white rounded-lg">
            <div class="flex justify-end mb-2">
                <button
                    @click="showPhotoPreview = false"
                    class="btn btn-sm btn-circle"
                >
                    <x-icon name="o-x-mark" class="w-4 h-4" />
                </button>
            </div>

            <div class="flex items-center justify-center">
                @if($photo)
                    <img src="{{ $photo->temporaryUrl() }}" alt="Child Photo Preview" class="max-h-[70vh] rounded-lg" />
                @elseif($existingPhoto)
                    <img src="{{ Storage::url($existingPhoto) }}" alt="{{ $childData['name'] }}" class="max-h-[70vh] rounded-lg" />
                @else
                    <div class="flex items-center justify-center w-64 h-64 text-6xl font-bold rounded-full bg-base-300 text-base-content/30">
                        {{ substr($childData['name'], 0, 1) ?: 'C' }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
