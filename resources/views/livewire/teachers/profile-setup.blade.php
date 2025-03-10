<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\TeacherProfile;
use App\Models\Subject;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public $currentStep = 1;
    public $totalSteps = 4;

    // User data
    public $user;

    // Step 1: Basic Information
    public $name;
    public $username;
    public $email;
    public $photo;

    // Step 2: Personal Details
    public $dateOfBirth;
    public $placeOfBirth;

    // Step 3: Contact Information
    public $phone;
    public $whatsapp;
    public $fixNumber;

    // Step 4: Teaching Subjects
    public $availableSubjects = [];
    public $selectedSubjects = [];

    // Validation rules by step
    protected function rules()
    {
        return [
            // Step 1: Basic Information
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username,' . $this->user->id,
            'email' => 'required|email|max:255|unique:users,email,' . $this->user->id,
            'photo' => 'nullable|image|max:1024',

            // Step 2: Personal Details
            'dateOfBirth' => 'nullable|date|before:today',
            'placeOfBirth' => 'nullable|string|max:255',

            // Step 3: Contact Information
            'phone' => 'required|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'fixNumber' => 'nullable|string|max:20',

            // Step 4: Teaching Subjects
            'selectedSubjects' => 'required|array|min:1'
        ];
    }

    // Validation messages
    protected function messages()
    {
        return [
            'selectedSubjects.required' => 'Please select at least one subject that you can teach.',
            'selectedSubjects.min' => 'Please select at least one subject that you can teach.',
            'phone.required' => 'A phone number is required for teacher verification.'
        ];
    }

    public function mount()
    {
        $this->user = Auth::user();

        // Check if teacher profile exists already
        if ($this->user->teacherProfile && $this->user->teacherProfile->has_completed_profile) {
            return redirect()->route('teachers.profile');
        }

        // Pre-fill form with any existing information
        $this->name = $this->user->name;
        $this->username = $this->user->username;
        $this->email = $this->user->email;

        if ($this->user->teacherProfile) {
            $teacherProfile = $this->user->teacherProfile;

            $this->dateOfBirth = $teacherProfile->date_of_birth ? $teacherProfile->date_of_birth->format('Y-m-d') : null;
            $this->placeOfBirth = $teacherProfile->place_of_birth;
            $this->phone = $teacherProfile->phone;
            $this->whatsapp = $teacherProfile->whatsapp;
            $this->fixNumber = $teacherProfile->fix_number;

            // Load selected subjects if any
            if ($teacherProfile->subjects) {
                $this->selectedSubjects = $teacherProfile->subjects->pluck('id')->toArray();
            }
        }

        // Load all available subjects
        $this->loadAvailableSubjects();
    }

    public function loadAvailableSubjects()
    {
        $this->availableSubjects = Subject::orderBy('name')->get()->map(function($subject) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'description' => $subject->description
            ];
        })->toArray();
    }

    public function nextStep()
    {
        // Validate the current step
        $this->validateCurrentStep();

        // Save progress
        $this->saveProgress();

        // Advance to next step
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
        $rules = [];

        // Determine which rules to apply based on current step
        switch($this->currentStep) {
            case 1:
                $rules = [
                    'name' => $this->rules()['name'],
                    'username' => $this->rules()['username'],
                    'email' => $this->rules()['email'],
                    'photo' => $this->rules()['photo']
                ];
                break;
            case 2:
                $rules = [
                    'dateOfBirth' => $this->rules()['dateOfBirth'],
                    'placeOfBirth' => $this->rules()['placeOfBirth']
                ];
                break;
            case 3:
                $rules = [
                    'phone' => $this->rules()['phone'],
                    'whatsapp' => $this->rules()['whatsapp'],
                    'fixNumber' => $this->rules()['fixNumber']
                ];
                break;
            case 4:
                $rules = [
                    'selectedSubjects' => $this->rules()['selectedSubjects']
                ];
                break;
        }

        $this->validate($rules, $this->messages());
    }

    public function saveProgress()
    {
        // Update user info
        if ($this->currentStep == 1) {
            $this->user->name = $this->name;
            $this->user->username = $this->username;
            $this->user->email = $this->email;
            $this->user->save();
        }

        // Create or update teacher profile
        $teacherProfile = $this->user->teacherProfile;

        if (!$teacherProfile) {
            $teacherProfile = new TeacherProfile([
                'status' => 'submitted'
            ]);
            $this->user->teacherProfile()->save($teacherProfile);
        }

        // Update profile fields based on current step
        switch($this->currentStep) {
            case 1:
                // Handle photo upload
                if ($this->photo) {
                    // Delete old photo if exists
                    if ($teacherProfile->photo && Storage::exists($teacherProfile->photo)) {
                        Storage::delete($teacherProfile->photo);
                    }

                    // Save new photo
                    $photoPath = $this->photo->storePublicly('teacher-photos', 'public');
                    $teacherProfile->photo = $photoPath;
                }
                break;
            case 2:
                $teacherProfile->date_of_birth = $this->dateOfBirth;
                $teacherProfile->place_of_birth = $this->placeOfBirth;
                break;
            case 3:
                $teacherProfile->phone = $this->phone;
                $teacherProfile->whatsapp = $this->whatsapp;
                $teacherProfile->fix_number = $this->fixNumber;
                break;
            case 4:
                // Will be saved in submit() method
                break;
        }

        $teacherProfile->save();
    }

    public function submit()
    {
        // Validate the final step
        $this->validateCurrentStep();

        // Get teacher profile
        $teacherProfile = $this->user->teacherProfile;

        if (!$teacherProfile) {
            $teacherProfile = new TeacherProfile([
                'status' => 'submitted'
            ]);
            $this->user->teacherProfile()->save($teacherProfile);
        }

        // Update subjects
        $teacherProfile->subjects()->sync($this->selectedSubjects);

        // Mark profile as completed
        $teacherProfile->has_completed_profile = true;
        $teacherProfile->save();

        // Show success message and redirect
        session()->flash('message', 'Profile setup completed successfully! Your profile will be reviewed by our team.');

        return redirect()->route('teachers.dashboard');
    }

    // Toggle subject selection
    public function toggleSubject($subjectId)
    {
        if (in_array($subjectId, $this->selectedSubjects)) {
            $this->selectedSubjects = array_diff($this->selectedSubjects, [$subjectId]);
        } else {
            $this->selectedSubjects[] = $subjectId;
        }
    }

    // Check if a subject is selected
    public function isSubjectSelected($subjectId)
    {
        return in_array($subjectId, $this->selectedSubjects);
    }

    // Get progress percentage
    public function getProgressPercentage()
    {
        return ($this->currentStep - 1) / $this->totalSteps * 100;
    }
}; ?>

<div class="p-6">
    <div class="max-w-3xl mx-auto">
        <!-- Header Section -->
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold">Teacher Profile Setup</h1>
            <p class="mt-2 text-lg text-base-content/70">Complete your profile to start teaching</p>
        </div>

        <!-- Progress Bar -->
        <div class="mb-10">
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

        <!-- Flash Messages -->
        @if (session()->has('message'))
            <div class="mb-6 alert alert-success">
                <div>
                    <x-icon name="o-check-circle" class="w-6 h-6" />
                    <span>{{ session('message') }}</span>
                </div>
            </div>
        @endif

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
                        <!-- Profile Photo -->
                        <div>
                            <label class="block mb-2 font-medium">Profile Photo</label>
                            <div class="flex items-center gap-4">
                                <div>
                                    @if($photo && is_object($photo))
                                        <div class="avatar">
                                            <div class="w-24 h-24 rounded-full">
                                                <img src="{{ $photo->temporaryUrl() }}" alt="Profile Preview" />
                                            </div>
                                        </div>
                                    @elseif($user->teacherProfile && $user->teacherProfile->photo)
                                        <div class="avatar">
                                            <div class="w-24 h-24 rounded-full">
                                                <img src="{{ Storage::url($user->teacherProfile->photo) }}" alt="{{ $user->name }}" />
                                            </div>
                                        </div>
                                    @else
                                        <div class="avatar placeholder">
                                            <div class="w-24 h-24 rounded-full bg-neutral-focus text-neutral-content">
                                                <span class="text-3xl">{{ substr($name, 0, 1) }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    <input
                                        type="file"
                                        wire:model="photo"
                                        id="photo-upload"
                                        class="hidden"
                                        accept="image/*"
                                    />
                                    <label for="photo-upload" class="btn btn-outline">
                                        <x-icon name="o-arrow-up-tray" class="w-4 h-4 mr-2" />
                                        Upload Photo
                                    </label>
                                    <p class="mt-2 text-sm text-base-content/70">
                                        A professional photo is recommended. Maximum size: 1MB.
                                    </p>
                                </div>
                            </div>
                            @error('photo') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Name -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Full Name</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="text"
                                wire:model="name"
                                class="input input-bordered @error('name') input-error @enderror"
                                placeholder="Your full name"
                            />
                            @error('name') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Username -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Username</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="text"
                                wire:model="username"
                                class="input input-bordered @error('username') input-error @enderror"
                                placeholder="Choose a username"
                            />
                            @error('username') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Email Address</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="email"
                                wire:model="email"
                                class="input input-bordered @error('email') input-error @enderror"
                                placeholder="Your email address"
                            />
                            @error('email') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif

                <!-- Step 2: Personal Details -->
                @if ($currentStep == 2)
                    <h2 class="text-xl font-bold">Personal Details</h2>
                    <div class="divider"></div>

                    <div class="grid grid-cols-1 gap-6">
                        <!-- Date of Birth -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Date of Birth</span>
                            </label>
                            <input
                                type="date"
                                wire:model="dateOfBirth"
                                class="input input-bordered @error('dateOfBirth') input-error @enderror"
                            />
                            <label class="label">
                                <span class="label-text-alt text-base-content/70">This information is kept private and used for verification purposes only.</span>
                            </label>
                            @error('dateOfBirth') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Place of Birth -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Place of Birth</span>
                            </label>
                            <input
                                type="text"
                                wire:model="placeOfBirth"
                                class="input input-bordered @error('placeOfBirth') input-error @enderror"
                                placeholder="City, Country"
                            />
                            <label class="label">
                                <span class="label-text-alt text-base-content/70">This information is kept private and used for verification purposes only.</span>
                            </label>
                            @error('placeOfBirth') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif

                <!-- Step 3: Contact Information -->
                @if ($currentStep == 3)
                    <h2 class="text-xl font-bold">Contact Information</h2>
                    <div class="divider"></div>

                    <div class="grid grid-cols-1 gap-6">
                        <!-- Phone Number -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Phone Number</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="tel"
                                wire:model="phone"
                                class="input input-bordered @error('phone') input-error @enderror"
                                placeholder="Your phone number"
                            />
                            <label class="label">
                                <span class="label-text-alt text-base-content/70">This is your primary contact number for verification.</span>
                            </label>
                            @error('phone') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- WhatsApp -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">WhatsApp Number</span>
                            </label>
                            <input
                                type="tel"
                                wire:model="whatsapp"
                                class="input input-bordered @error('whatsapp') input-error @enderror"
                                placeholder="Your WhatsApp number (if different)"
                            />
                            @error('whatsapp') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <!-- Fixed Number -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Fixed Number</span>
                            </label>
                            <input
                                type="tel"
                                wire:model="fixNumber"
                                class="input input-bordered @error('fixNumber') input-error @enderror"
                                placeholder="Your fixed/landline number (optional)"
                            />
                            @error('fixNumber') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif

                <!-- Step 4: Teaching Subjects -->
                @if ($currentStep == 4)
                    <h2 class="text-xl font-bold">Teaching Subjects</h2>
                    <div class="divider"></div>

                    <p class="mb-4 text-base-content/70">
                        Select the subjects you are qualified to teach. You must select at least one subject.
                    </p>

                    @error('selectedSubjects')
                        <div class="mb-4 alert alert-error">
                            <div>
                                <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
                                <span>{{ $message }}</span>
                            </div>
                        </div>
                    @enderror

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach($availableSubjects as $subject)
                            <div
                                wire:click="toggleSubject({{ $subject['id'] }})"
                                class="p-4 transition-colors border rounded-lg cursor-pointer border-base-300 hover:bg-base-200 {{ $this->isSubjectSelected($subject['id']) ? 'bg-primary/10 border-primary' : '' }}"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="font-medium">{{ $subject['name'] }}</h3>
                                        @if(isset($subject['description']) && $subject['description'])
                                            <p class="mt-1 text-sm text-base-content/70">{{ $subject['description'] }}</p>
                                        @endif
                                    </div>
                                    <div class="form-control">
                                        <input
                                            type="checkbox"
                                            class="checkbox checkbox-primary"
                                            checked="{{ $this->isSubjectSelected($subject['id']) ? 'checked' : '' }}"
                                            readOnly
                                        />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if(count($availableSubjects) === 0)
                        <div class="p-4 text-center bg-base-200 rounded-xl">
                            <p class="text-base-content/70">No subjects available. Please contact the administrator.</p>
                        </div>
                    @endif
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
                    Complete Setup
                </button>
            @endif
        </div>

        <!-- Information Box -->
        <div class="p-4 mt-8 shadow-md rounded-xl bg-info/10 text-info">
            <div class="flex gap-3">
                <x-icon name="o-information-circle" class="flex-shrink-0 w-6 h-6" />
                <div>
                    <h3 class="font-semibold">Verification Process</h3>
                    <p class="mt-1">
                        After completing your profile, our team will review your information for verification.
                        This process usually takes 1-2 business days. You ll receive an email once your profile is verified.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
