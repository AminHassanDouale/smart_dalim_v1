<?php

namespace App\Livewire\Teachers\Profile;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\TeacherProfile;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public $teacher;
    public $teacherProfile;

    // Form inputs
    public $name;
    public $email;
    public $username;
    public $phone;
    public $whatsapp;
    public $fixNumber;
    public $dateOfBirth;
    public $placeOfBirth;
    public $photo;
    public $newPhoto;

    // Subjects management
    public $availableSubjects = [];
    public $selectedSubjects = [];

    // Tab management
    public $activeTab = 'personal';

    // Form validation rules
    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username,' . $this->teacher->id,
            'email' => 'required|email|max:255|unique:users,email,' . $this->teacher->id,
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'fixNumber' => 'nullable|string|max:20',
            'dateOfBirth' => 'nullable|date|before:today',
            'placeOfBirth' => 'nullable|string|max:255',
            'newPhoto' => 'nullable|image|max:1024',
            'selectedSubjects' => 'array'
        ];
    }

    public function mount($teacher)
    {
        // Load user and profile data
        $this->teacher = User::findOrFail($teacher);

        // Ensure the authenticated user can only edit their own profile
        if (Auth::id() !== $this->teacher->id) {
            return redirect()->route('teachers.profile');
        }

        $this->teacherProfile = $this->teacher->teacherProfile;

        // Set form values from existing data
        $this->name = $this->teacher->name;
        $this->email = $this->teacher->email;
        $this->username = $this->teacher->username;

        if ($this->teacherProfile) {
            $this->phone = $this->teacherProfile->phone;
            $this->whatsapp = $this->teacherProfile->whatsapp;
            $this->fixNumber = $this->teacherProfile->fix_number;
            $this->dateOfBirth = $this->teacherProfile->date_of_birth ? $this->teacherProfile->date_of_birth->format('Y-m-d') : null;
            $this->placeOfBirth = $this->teacherProfile->place_of_birth;
            $this->photo = $this->teacherProfile->photo;

            // Load selected subjects
            $this->selectedSubjects = $this->teacherProfile->subjects->pluck('id')->toArray();
        }

        // Load all available subjects
        $this->loadAvailableSubjects();

        // Set active tab from query parameter if available
        $this->activeTab = request()->has('tab') ? request()->get('tab') : 'personal';
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

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function save()
    {
        $this->validate();

        // Update user information
        $this->teacher->name = $this->name;
        $this->teacher->email = $this->email;
        $this->teacher->username = $this->username;
        $this->teacher->save();

        // Create or update teacher profile
        if (!$this->teacherProfile) {
            $this->teacherProfile = new TeacherProfile([
                'status' => 'submitted'
            ]);
            $this->teacher->teacherProfile()->save($this->teacherProfile);
        }

        // Update profile fields
        $this->teacherProfile->phone = $this->phone;
        $this->teacherProfile->whatsapp = $this->whatsapp;
        $this->teacherProfile->fix_number = $this->fixNumber;
        $this->teacherProfile->date_of_birth = $this->dateOfBirth;
        $this->teacherProfile->place_of_birth = $this->placeOfBirth;

        // Handle photo upload if there's a new photo
        if ($this->newPhoto) {
            // Delete old photo if it exists
            if ($this->photo && Storage::exists($this->photo)) {
                Storage::delete($this->photo);
            }

            // Save new photo
            $photoPath = $this->newPhoto->storePublicly('teacher-photos', 'public');
            $this->teacherProfile->photo = $photoPath;
            $this->photo = $photoPath;
            $this->newPhoto = null;
        }

        // Save profile changes
        $this->teacherProfile->has_completed_profile = true;
        $this->teacherProfile->save();

        // Update subjects
        $this->teacherProfile->subjects()->sync($this->selectedSubjects);

        // Show success message
        session()->flash('message', 'Profile updated successfully.');

        // Redirect to profile view
        return redirect()->route('teachers.profile');
    }

    public function cancel()
    {
        return redirect()->route('teachers.profile');
    }

    // Toggle a subject selection
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

    // Get full photo URL
    public function getPhotoUrl()
    {
        if ($this->photo) {
            return Storage::url($this->photo);
        }
        return null;
    }

    // Get new photo preview URL
    public function getNewPhotoUrl()
    {
        if ($this->newPhoto) {
            return $this->newPhoto->temporaryUrl();
        }
        return null;
    }

    // Remove current photo
    public function removePhoto()
    {
        if ($this->photo && Storage::exists($this->photo)) {
            Storage::delete($this->photo);
        }

        $this->photo = null;
        if ($this->teacherProfile) {
            $this->teacherProfile->photo = null;
            $this->teacherProfile->save();
        }

        session()->flash('message', 'Photo removed successfully.');
    }
}; ?>

<div class="p-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Edit Profile</h1>
                <p class="mt-1 text-base-content/70">Update your teacher profile information</p>
            </div>
            <div>
                <a href="{{ route('teachers.profile') }}" class="btn btn-outline">
                    <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                    Back to Profile
                </a>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="mb-6 tabs">
            <a
                wire:click.prevent="setActiveTab('personal')"
                class="tab tab-bordered {{ $activeTab === 'personal' ? 'tab-active' : '' }}"
            >
                Personal Information
            </a>
            <a
                wire:click.prevent="setActiveTab('contact')"
                class="tab tab-bordered {{ $activeTab === 'contact' ? 'tab-active' : '' }}"
            >
                Contact Details
            </a>
            <a
                wire:click.prevent="setActiveTab('subjects')"
                class="tab tab-bordered {{ $activeTab === 'subjects' ? 'tab-active' : '' }}"
            >
                Teaching Subjects
            </a>
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

        <form wire:submit.prevent="save">
            <!-- Personal Information Tab -->
            <div class="{{ $activeTab === 'personal' ? '' : 'hidden' }}">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Personal Information</h2>
                        <div class="divider"></div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Profile Photo -->
                            <div class="md:col-span-2">
                                <label class="block mb-2 font-medium">Profile Photo</label>
                                <div class="flex items-center gap-4">
                                    <div>
                                        @if($this->getNewPhotoUrl())
                                            <div class="avatar">
                                                <div class="w-24 h-24 rounded-full">
                                                    <img src="{{ $this->getNewPhotoUrl() }}" alt="Profile Preview" />
                                                </div>
                                            </div>
                                        @elseif($this->getPhotoUrl())
                                            <div class="avatar">
                                                <div class="w-24 h-24 rounded-full">
                                                    <img src="{{ $this->getPhotoUrl() }}" alt="{{ $teacher->name }}" />
                                                </div>
                                            </div>
                                        @else
                                            <div class="avatar placeholder">
                                                <div class="w-24 h-24 rounded-full bg-neutral-focus text-neutral-content">
                                                    <span class="text-3xl">{{ substr($teacher->name, 0, 1) }}</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex flex-col gap-2">
                                        <input
                                            type="file"
                                            wire:model="newPhoto"
                                            id="photo-upload"
                                            class="hidden"
                                            accept="image/*"
                                        />
                                        <label for="photo-upload" class="btn btn-outline btn-sm">
                                            <x-icon name="o-arrow-up-tray" class="w-4 h-4 mr-2" />
                                            {{ $photo ? 'Change Photo' : 'Upload Photo' }}
                                        </label>

                                        @if($photo)
                                            <button
                                                type="button"
                                                wire:click="removePhoto"
                                                class="btn btn-outline btn-error btn-sm"
                                            >
                                                <x-icon name="o-trash" class="w-4 h-4 mr-2" />
                                                Remove Photo
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @error('newPhoto') <span class="text-sm text-error">{{ $message }}</span> @enderror
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
                                    placeholder="Your username"
                                />
                                @error('username') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

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
                                @error('placeOfBirth') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Details Tab -->
            <div class="{{ $activeTab === 'contact' ? '' : 'hidden' }}">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Contact Information</h2>
                        <div class="divider"></div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
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

                            <!-- Phone Number -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Phone Number</span>
                                </label>
                                <input
                                    type="tel"
                                    wire:model="phone"
                                    class="input input-bordered @error('phone') input-error @enderror"
                                    placeholder="Your phone number"
                                />
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
                                    placeholder="Your WhatsApp number"
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
                                    placeholder="Your fixed/landline number"
                                />
                                @error('fixNumber') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teaching Subjects Tab -->
            <div class="{{ $activeTab === 'subjects' ? '' : 'hidden' }}">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Teaching Subjects</h2>
                        <div class="divider"></div>

                        <p class="mb-4 text-base-content/70">
                            Select the subjects you are qualified to teach. This information will be used to  you with appropriate students.
                        </p>

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
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-3 mt-6">
                <button
                    type="button"
                    wire:click="cancel"
                    class="btn btn-outline"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <x-icon name="o-check" class="w-4 h-4 mr-2" />
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
