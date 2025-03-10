<?php

namespace App\Livewire\Parents\Profile;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\ParentProfile;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    // User and profile data
    public $user;
    public $parentProfile;
    public $children = [];

    // Form data
    public $formData = [
        'personal' => [
            'name' => '',
            'email' => '',
            'phone_number' => '',
            'date_of_birth' => '',
            'bio' => '',
        ],
        'address' => [
            'address' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
        ],
        'emergency_contacts' => [],
        'preferences' => [
            'preferred_communication_method' => 'email',
            'preferred_session_times' => [],
            'areas_of_interest' => [],
            'special_requirements' => '',
            'newsletter_subscription' => true,
        ],
    ];

    // Profile photo
    public $profilePhoto;
    public $existingPhoto;
    public $removePhoto = false;

    // Active section
    public $activeSection = 'personal';

    // Progress tracking
    public $sectionCompletion = [
        'personal' => false,
        'address' => false,
        'emergency' => false,
        'preferences' => false,
    ];

    // Validation rules
    protected $rules = [
        'formData.personal.name' => 'required|min:2',
        'formData.personal.email' => 'required|email',
        'formData.personal.phone_number' => 'required|min:10',
        'formData.personal.date_of_birth' => 'nullable|date|before:today',
        'formData.personal.bio' => 'nullable|max:500',

        'formData.address.address' => 'nullable|min:5',
        'formData.address.city' => 'nullable|min:2',
        'formData.address.state' => 'nullable',
        'formData.address.postal_code' => 'nullable',
        'formData.address.country' => 'nullable',

        'formData.emergency_contacts.*.name' => 'required|min:2',
        'formData.emergency_contacts.*.relationship' => 'required',
        'formData.emergency_contacts.*.phone' => 'required|min:10',
        'formData.emergency_contacts.*.email' => 'required|email',

        'formData.preferences.preferred_communication_method' => 'required|in:email,phone,sms',
        'formData.preferences.preferred_session_times' => 'array',
        'formData.preferences.areas_of_interest' => 'array',
        'formData.preferences.special_requirements' => 'nullable|max:500',
        'profilePhoto' => 'nullable|image|max:1024',
    ];

    public function mount($user)
    {
        $this->user = User::findOrFail($user);
        $this->parentProfile = $this->user->parentProfile;

        if (!$this->parentProfile) {
            // Create parent profile if it doesn't exist
            $this->parentProfile = ParentProfile::create([
                'user_id' => $this->user->id,
            ]);
        }

        $this->children = $this->parentProfile->children ?? collect([]);
        $this->existingPhoto = $this->parentProfile->profile_photo_path;

        $this->loadFormData();
        $this->calculateSectionCompletion();
    }

    public function loadFormData()
    {
        // Personal info
        $this->formData['personal']['name'] = $this->user->name;
        $this->formData['personal']['email'] = $this->user->email;
        $this->formData['personal']['phone_number'] = $this->parentProfile->phone_number ?? '';
        $this->formData['personal']['date_of_birth'] = $this->parentProfile->date_of_birth ? Carbon::parse($this->parentProfile->date_of_birth)->format('Y-m-d') : '';
        $this->formData['personal']['bio'] = $this->parentProfile->bio ?? '';

        // Address info
        $this->formData['address']['address'] = $this->parentProfile->address ?? '';
        $this->formData['address']['city'] = $this->parentProfile->city ?? '';
        $this->formData['address']['state'] = $this->parentProfile->state ?? '';
        $this->formData['address']['postal_code'] = $this->parentProfile->postal_code ?? '';
        $this->formData['address']['country'] = $this->parentProfile->country ?? '';

        // Emergency contacts
        $this->formData['emergency_contacts'] = $this->parentProfile->emergency_contacts ?? [[
            'name' => '',
            'relationship' => '',
            'phone' => '',
            'email' => '',
            'is_primary' => true
        ]];

        // Preferences
        $this->formData['preferences']['preferred_communication_method'] = $this->parentProfile->preferred_communication_method ?? 'email';
        $this->formData['preferences']['preferred_session_times'] = $this->parentProfile->preferred_session_times ?? [];
        $this->formData['preferences']['areas_of_interest'] = $this->parentProfile->areas_of_interest ?? [];
        $this->formData['preferences']['special_requirements'] = $this->parentProfile->special_requirements ?? '';
        $this->formData['preferences']['newsletter_subscription'] = $this->parentProfile->newsletter_subscription ?? true;
    }

    public function calculateSectionCompletion()
    {
        // Personal section
        $this->sectionCompletion['personal'] =
            !empty($this->formData['personal']['name']) &&
            !empty($this->formData['personal']['email']) &&
            !empty($this->formData['personal']['phone_number']);

        // Address section
        $this->sectionCompletion['address'] =
            !empty($this->formData['address']['address']) &&
            !empty($this->formData['address']['city']) &&
            !empty($this->formData['address']['country']);

        // Emergency contacts section
        $this->sectionCompletion['emergency'] =
            isset($this->formData['emergency_contacts'][0]) &&
            !empty($this->formData['emergency_contacts'][0]['name']) &&
            !empty($this->formData['emergency_contacts'][0]['phone']);

        // Preferences section
        $this->sectionCompletion['preferences'] =
            !empty($this->formData['preferences']['preferred_communication_method']) &&
            count($this->formData['preferences']['preferred_session_times']) > 0;
    }

    public function setActiveSection($section)
    {
        $this->activeSection = $section;
    }

    public function addEmergencyContact()
    {
        $this->formData['emergency_contacts'][] = [
            'name' => '',
            'relationship' => '',
            'phone' => '',
            'email' => '',
            'is_primary' => false
        ];
    }

    public function removeEmergencyContact($index)
    {
        if (count($this->formData['emergency_contacts']) > 1) {
            unset($this->formData['emergency_contacts'][$index]);
            $this->formData['emergency_contacts'] = array_values($this->formData['emergency_contacts']);
        }
    }

    public function updatePersonalInfo()
    {
        $this->validate([
            'formData.personal.name' => $this->rules['formData.personal.name'],
            'formData.personal.email' => $this->rules['formData.personal.email'],
            'formData.personal.phone_number' => $this->rules['formData.personal.phone_number'],
            'formData.personal.date_of_birth' => $this->rules['formData.personal.date_of_birth'],
            'formData.personal.bio' => $this->rules['formData.personal.bio'],
            'profilePhoto' => $this->rules['profilePhoto'],
        ]);

        // Update user
        $this->user->update([
            'name' => $this->formData['personal']['name'],
            'email' => $this->formData['personal']['email'],
        ]);

        // Process profile photo
        if ($this->removePhoto && $this->existingPhoto) {
            Storage::disk('public')->delete($this->existingPhoto);
            $this->existingPhoto = null;
            $photoPath = null;
        } elseif ($this->profilePhoto) {
            // Upload new photo
            $photoPath = $this->profilePhoto->store('profile-photos', 'public');

            // Delete old photo if exists
            if ($this->existingPhoto) {
                Storage::disk('public')->delete($this->existingPhoto);
            }

            $this->existingPhoto = $photoPath;
        } else {
            $photoPath = $this->existingPhoto;
        }

        // Update parent profile
        $this->parentProfile->update([
            'phone_number' => $this->formData['personal']['phone_number'],
            'date_of_birth' => $this->formData['personal']['date_of_birth'],
            'bio' => $this->formData['personal']['bio'],
            'profile_photo_path' => $photoPath,
        ]);

        $this->profilePhoto = null;
        $this->removePhoto = false;
        $this->calculateSectionCompletion();

        session()->flash('message', 'Personal information updated successfully.');
        $this->activeSection = 'address';
    }

    public function updateAddressInfo()
    {
        $this->validate([
            'formData.address.address' => $this->rules['formData.address.address'],
            'formData.address.city' => $this->rules['formData.address.city'],
            'formData.address.state' => $this->rules['formData.address.state'],
            'formData.address.postal_code' => $this->rules['formData.address.postal_code'],
            'formData.address.country' => $this->rules['formData.address.country'],
        ]);

        $this->parentProfile->update([
            'address' => $this->formData['address']['address'],
            'city' => $this->formData['address']['city'],
            'state' => $this->formData['address']['state'],
            'postal_code' => $this->formData['address']['postal_code'],
            'country' => $this->formData['address']['country'],
        ]);

        $this->calculateSectionCompletion();

        session()->flash('message', 'Address information updated successfully.');
        $this->activeSection = 'emergency';
    }

    public function updateEmergencyContacts()
    {
        $rules = [];
        foreach ($this->formData['emergency_contacts'] as $index => $contact) {
            $rules["formData.emergency_contacts.{$index}.name"] = $this->rules['formData.emergency_contacts.*.name'];
            $rules["formData.emergency_contacts.{$index}.relationship"] = $this->rules['formData.emergency_contacts.*.relationship'];
            $rules["formData.emergency_contacts.{$index}.phone"] = $this->rules['formData.emergency_contacts.*.phone'];
            $rules["formData.emergency_contacts.{$index}.email"] = $this->rules['formData.emergency_contacts.*.email'];
        }

        $this->validate($rules);

        $this->parentProfile->update([
            'emergency_contacts' => $this->formData['emergency_contacts'],
        ]);

        $this->calculateSectionCompletion();

        session()->flash('message', 'Emergency contacts updated successfully.');
        $this->activeSection = 'preferences';
    }

    public function updatePreferences()
    {
        $this->validate([
            'formData.preferences.preferred_communication_method' => $this->rules['formData.preferences.preferred_communication_method'],
            'formData.preferences.preferred_session_times' => $this->rules['formData.preferences.preferred_session_times'],
            'formData.preferences.areas_of_interest' => $this->rules['formData.preferences.areas_of_interest'],
            'formData.preferences.special_requirements' => $this->rules['formData.preferences.special_requirements'],
        ]);

        $this->parentProfile->update([
            'preferred_communication_method' => $this->formData['preferences']['preferred_communication_method'],
            'preferred_session_times' => $this->formData['preferences']['preferred_session_times'],
            'areas_of_interest' => $this->formData['preferences']['areas_of_interest'],
            'special_requirements' => $this->formData['preferences']['special_requirements'],
            'newsletter_subscription' => $this->formData['preferences']['newsletter_subscription'],
            'has_completed_profile' => true,
        ]);

        $this->calculateSectionCompletion();

        session()->flash('message', 'Preferences updated successfully.');
        session()->flash('profile-completed', 'Your profile is now complete!');

        return redirect()->route('parents.profile');
    }

    public function saveAllAndContinue()
    {
        // This method saves all sections at once and redirects
        $this->updatePersonalInfo();
        $this->updateAddressInfo();
        $this->updateEmergencyContacts();
        $this->updatePreferences();

        return redirect()->route('parents.profile');
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Edit Profile</h1>
                <p class="mt-1 text-base-content/70">Update your personal information and preferences</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('parents.profile') }}" class="btn btn-outline">
                    <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                    Back to Profile
                </a>
                <button wire:click="saveAllAndContinue" class="btn btn-primary">
                    <x-icon name="o-check" class="w-4 h-4 mr-2" />
                    Save All Changes
                </button>
            </div>
        </div>

        @if(session()->has('message'))
            <div class="mb-6 alert alert-success">
                <x-icon name="o-check-circle" class="w-6 h-6" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        <!-- Progress Indicator -->
        <div class="mb-8">
            <div class="w-full h-3 overflow-hidden rounded-full bg-base-300">
                @php
                    $progress = 0;
                    foreach ($sectionCompletion as $section => $completed) {
                        if ($completed) $progress += 25;
                    }
                @endphp
                <div class="h-full transition-all duration-300 bg-primary" style="width: {{ $progress }}%"></div>
            </div>
            <div class="flex justify-between mt-2 text-sm">
                <span>Profile {{ $progress }}% complete</span>
                @if($progress < 100)
                    <span class="text-primary">Complete your profile to unlock all features</span>
                @else
                    <span class="text-success">Your profile is complete!</span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Sidebar Navigation -->
            <div class="lg:col-span-1">
                <div class="sticky shadow-xl card bg-base-100 top-6">
                    <div class="p-4 card-body">
                        <h3 class="mb-4 text-lg font-bold">Profile Sections</h3>

                        <ul class="w-full p-0 menu bg-base-100 rounded-box">
                            <li>
                                <button
                                    wire:click="setActiveSection('personal')"
                                    class="{{ $activeSection === 'personal' ? 'active font-bold' : '' }} flex justify-between"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg {{ $sectionCompletion['personal'] ? 'bg-primary text-primary-content' : 'bg-base-300' }} flex items-center justify-center">
                                            @if($sectionCompletion['personal'])
                                                <x-icon name="o-check" class="w-5 h-5" />
                                            @else
                                                <x-icon name="o-user" class="w-5 h-5" />
                                            @endif
                                        </div>
                                        <span>Personal Information</span>
                                    </div>
                                    <x-icon name="o-chevron-right" class="w-5 h-5" />
                                </button>
                            </li>
                            <li>
                                <button
                                    wire:click="setActiveSection('address')"
                                    class="{{ $activeSection === 'address' ? 'active font-bold' : '' }} flex justify-between"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg {{ $sectionCompletion['address'] ? 'bg-primary text-primary-content' : 'bg-base-300' }} flex items-center justify-center">
                                            @if($sectionCompletion['address'])
                                                <x-icon name="o-check" class="w-5 h-5" />
                                            @else
                                                <x-icon name="o-map-pin" class="w-5 h-5" />
                                            @endif
                                        </div>
                                        <span>Address</span>
                                    </div>
                                    <x-icon name="o-chevron-right" class="w-5 h-5" />
                                </button>
                            </li>
                            <li>
                                <button
                                    wire:click="setActiveSection('emergency')"
                                    class="{{ $activeSection === 'emergency' ? 'active font-bold' : '' }} flex justify-between"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg {{ $sectionCompletion['emergency'] ? 'bg-primary text-primary-content' : 'bg-base-300' }} flex items-center justify-center">
                                            @if($sectionCompletion['emergency'])
                                                <x-icon name="o-check" class="w-5 h-5" />
                                            @else
                                                <x-icon name="o-phone" class="w-5 h-5" />
                                            @endif
                                        </div>
                                        <span>Emergency Contacts</span>
                                    </div>
                                    <x-icon name="o-chevron-right" class="w-5 h-5" />
                                </button>
                            </li>
                            <li>
                                <button
                                    wire:click="setActiveSection('preferences')"
                                    class="{{ $activeSection === 'preferences' ? 'active font-bold' : '' }} flex justify-between"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg {{ $sectionCompletion['preferences'] ? 'bg-primary text-primary-content' : 'bg-base-300' }} flex items-center justify-center">
                                            @if($sectionCompletion['preferences'])
                                                <x-icon name="o-check" class="w-5 h-5" />
                                            @else
                                                <x-icon name="o-cog" class="w-5 h-5" />
                                            @endif
                                        </div>
                                        <span>Preferences</span>
                                    </div>
                                    <x-icon name="o-chevron-right" class="w-5 h-5" />
                                </button>
                            </li>
                        </ul>

                        <div class="justify-center mt-6 card-actions">
                            <button wire:click="saveAllAndContinue" class="btn btn-primary btn-block">
                                Save All Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="lg:col-span-3">
                <!-- Personal Information Section -->
                <div class="card bg-base-100 shadow-xl mb-6 {{ $activeSection === 'personal' ? 'block' : 'hidden' }}">
                    <div class="card-body">
                        <h2 class="flex items-center mb-6 text-xl card-title">
                            <div class="flex items-center justify-center w-8 h-8 mr-2 rounded-lg bg-primary text-primary-content">
                                <x-icon name="o-user" class="w-5 h-5" />
                            </div>
                            Personal Information
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <!-- Profile Photo -->
                            <div class="flex flex-col items-center gap-6 mb-4 md:col-span-2 md:flex-row">
                                <div class="relative group">
                                    <div class="avatar">
                                        <div class="w-24 h-24 rounded-full bg-base-300">
                                            @if($profilePhoto)
                                                <img src="{{ $profilePhoto->temporaryUrl() }}" alt="{{ $formData['personal']['name'] }}" />
                                            @elseif($existingPhoto && !$removePhoto)
                                                <img src="{{ Storage::url($existingPhoto) }}" alt="{{ $formData['personal']['name'] }}" />
                                            @else
                                                <div class="flex items-center justify-center w-full h-full text-3xl font-bold text-base-content/30">
                                                    {{ substr($formData['personal']['name'], 0, 1) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    @if($existingPhoto && !$removePhoto && !$profilePhoto)
                                        <button
                                            wire:click="$set('removePhoto', true)"
                                            class="absolute p-1 text-white transition-opacity rounded-full opacity-0 -top-2 -right-2 bg-error group-hover:opacity-100"
                                            title="Remove photo"
                                        >
                                            <x-icon name="o-x-mark" class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>

                                <div class="flex flex-col gap-2">
                                    <div class="flex gap-2">
                                        <label class="btn btn-outline btn-sm">
                                            <x-icon name="o-camera" class="w-4 h-4 mr-2" />
                                            Upload Photo
                                            <input
                                                type="file"
                                                wire:model="profilePhoto"
                                                class="hidden"
                                                accept="image/*"
                                            />
                                        </label>

                                        @if($profilePhoto || ($existingPhoto && $removePhoto))
                                            <button
                                                wire:click="$set('profilePhoto', null); $set('removePhoto', false);"
                                                class="btn btn-ghost btn-sm"
                                            >
                                                Cancel
                                            </button>
                                        @endif
                                    </div>
                                    <div class="text-xs text-base-content/70">
                                        JPEG, PNG or GIF, max 1MB
                                    </div>
                                    @error('profilePhoto')
                                        <span class="text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Name -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Full Name</span>
                                    <span class="label-text-alt text-error">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="formData.personal.name"
                                    class="input input-bordered"
                                    placeholder="Enter your full name"
                                />
                                @error('formData.personal.name')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Email Address</span>
                                    <span class="label-text-alt text-error">*</span>
                                </label>
                                <input
                                    type="email"
                                    wire:model="formData.personal.email"
                                    class="input input-bordered"
                                    placeholder="Enter your email address"
                                />
                                @error('formData.personal.email')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Phone Number -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Phone Number</span>
                                    <span class="label-text-alt text-error">*</span>
                                </label>
                                <input
                                    type="tel"
                                    wire:model="formData.personal.phone_number"
                                    class="input input-bordered"
                                    placeholder="Enter your phone number"
                                />
                                @error('formData.personal.phone_number')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Date of Birth -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Date of Birth</span>
                                </label>
                                <input
                                    type="date"
                                    wire:model="formData.personal.date_of_birth"
                                    class="input input-bordered"
                                />
                                @error('formData.personal.date_of_birth')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Bio -->
                            <div class="form-control md:col-span-2">
                                <label class="label">
                                    <span class="label-text">Bio</span>
                                    <span class="label-text-alt">Brief introduction about yourself</span>
                                </label>
                                <textarea
                                    wire:model="formData.personal.bio"
                                    class="h-24 textarea textarea-bordered"
                                    placeholder="Tell us a bit about yourself..."
                                ></textarea>
                                @error('formData.personal.bio')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="justify-end mt-6 card-actions">
                            <button wire:click="updatePersonalInfo" class="btn btn-primary">
                                Save & Continue
                                <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Address Information Section -->
                <div class="card bg-base-100 shadow-xl mb-6 {{ $activeSection === 'address' ? 'block' : 'hidden' }}">
                    <div class="card-body">
                        <h2 class="flex items-center mb-6 text-xl card-title">
                            <div class="flex items-center justify-center w-8 h-8 mr-2 rounded-lg bg-primary text-primary-content">
                                <x-icon name="o-map-pin" class="w-5 h-5" />
                            </div>
                            Address Information
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <!-- Street Address -->
                            <div class="form-control md:col-span-2">
                                <label class="label">
                                    <span class="label-text">Street Address</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="formData.address.address"
                                    class="input input-bordered"
                                    placeholder="Enter your street address"
                                />
                                @error('formData.address.address')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- City -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">City</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="formData.address.city"
                                    class="input input-bordered"
                                    placeholder="Enter your city"
                                />
                                @error('formData.address.city')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- State/Province -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">State/Province</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="formData.address.state"
                                    class="input input-bordered"
                                    placeholder="Enter your state/province"
                                />
                                @error('formData.address.state')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Postal/ZIP Code -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Postal/ZIP Code</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="formData.address.postal_code"
                                    class="input input-bordered"
                                    placeholder="Enter your postal/ZIP code"
                                />
                                @error('formData.address.postal_code')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Country -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Country</span>
                                </label>
                                <select
                                    wire:model="formData.address.country"
                                    class="select select-bordered"
                                >
                                    <option value="">Select a country</option>
                                    <option value="US">United States</option>
                                    <option value="CA">Canada</option>
                                    <option value="UK">United Kingdom</option>
                                    <option value="AU">Australia</option>
                                    <option value="DE">Germany</option>
                                    <option value="FR">France</option>
                                    <option value="IT">Italy</option>
                                    <option value="ES">Spain</option>
                                    <option value="BR">Brazil</option>
                                    <option value="IN">India</option>
                                    <option value="JP">Japan</option>
                                    <!-- Add more countries as needed -->
                                </select>
                                @error('formData.address.country')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="justify-between mt-6 card-actions">
                            <button wire:click="setActiveSection('personal')" class="btn btn-outline">
                                <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                Previous
                            </button>
                            <button wire:click="updateAddressInfo" class="btn btn-primary">
                                Save & Continue
                                <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contacts Section -->
                <div class="card bg-base-100 shadow-xl mb-6 {{ $activeSection === 'emergency' ? 'block' : 'hidden' }}">
                    <div class="card-body">
                        <h2 class="flex items-center mb-6 text-xl card-title">
                            <div class="flex items-center justify-center w-8 h-8 mr-2 rounded-lg bg-primary text-primary-content">
                                <x-icon name="o-phone" class="w-5 h-5" />
                            </div>
                            Emergency Contacts
                        </h2>

                        <div class="space-y-8">
                            @foreach($formData['emergency_contacts'] as $index => $contact)
                                <div class="relative p-4 rounded-lg bg-base-200">
                                    @if($index > 0)
                                        <button
                                            wire:click="removeEmergencyContact({{ $index }})"
                                            class="absolute btn btn-sm btn-circle btn-ghost top-2 right-2"
                                        >
                                            <x-icon name="o-x-mark" class="w-4 h-4" />
                                        </button>
                                    @endif

                                    <div class="mb-2">
                                        <div class="badge {{ $index === 0 ? 'badge-primary' : 'badge-ghost' }}">
                                            {{ $index === 0 ? 'Primary Contact' : 'Secondary Contact' }}
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Contact Name</span>
                                                <span class="label-text-alt text-error">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                wire:model="formData.emergency_contacts.{{ $index }}.name"
                                                class="input input-bordered"
                                                placeholder="Enter contact name"
                                            />
                                            @error("formData.emergency_contacts.{$index}.name")
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Relationship</span>
                                                <span class="label-text-alt text-error">*</span>
                                            </label>
                                            <select
                                                wire:model="formData.emergency_contacts.{{ $index }}.relationship"
                                                class="select select-bordered"
                                            >
                                                <option value="">Select relationship</option>
                                                <option value="Spouse">Spouse</option>
                                                <option value="Parent">Parent</option>
                                                <option value="Sibling">Sibling</option>
                                                <option value="Friend">Friend</option>
                                                <option value="Relative">Other Relative</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            @error("formData.emergency_contacts.{$index}.relationship")
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Phone Number</span>
                                                <span class="label-text-alt text-error">*</span>
                                            </label>
                                            <input
                                                type="tel"
                                                wire:model="formData.emergency_contacts.{{ $index }}.phone"
                                                class="input input-bordered"
                                                placeholder="Enter phone number"
                                            />
                                            @error("formData.emergency_contacts.{$index}.phone")
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Email Address</span>
                                                <span class="label-text-alt text-error">*</span>
                                            </label>
                                            <input
                                                type="email"
                                                wire:model="formData.emergency_contacts.{{ $index }}.email"
                                                class="input input-bordered"
                                                placeholder="Enter email address"
                                            />
                                            @error("formData.emergency_contacts.{$index}.email")
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="flex justify-center">
                                <button
                                    wire:click="addEmergencyContact"
                                    class="btn btn-outline"
                                >
                                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                    Add Another Contact
                                </button>
                            </div>
                        </div>

                        <div class="justify-between mt-6 card-actions">
                            <button wire:click="setActiveSection('address')" class="btn btn-outline">
                                <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                Previous
                            </button>
                            <button wire:click="updateEmergencyContacts" class="btn btn-primary">
                                Save & Continue
                                <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Preferences Section -->
                <div class="card bg-base-100 shadow-xl mb-6 {{ $activeSection === 'preferences' ? 'block' : 'hidden' }}">
                    <div class="card-body">
                        <h2 class="flex items-center mb-6 text-xl card-title">
                            <div class="flex items-center justify-center w-8 h-8 mr-2 rounded-lg bg-primary text-primary-content">
                                <x-icon name="o-cog" class="w-5 h-5" />
                            </div>
                            Preferences
                        </h2>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="space-y-4">
                                <div class="form-control">
                                    <label class="label">
                                        <span class="font-medium label-text">Preferred Communication Method</span>
                                    </label>
                                    <div class="flex flex-col gap-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                value="email"
                                                wire:model="formData.preferences.preferred_communication_method"
                                                class="radio radio-primary"
                                            />
                                            <span>Email</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                value="phone"
                                                wire:model="formData.preferences.preferred_communication_method"
                                                class="radio radio-primary"
                                            />
                                            <span>Phone Call</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="radio"
                                                value="sms"
                                                wire:model="formData.preferences.preferred_communication_method"
                                                class="radio radio-primary"
                                            />
                                            <span>SMS/Text Message</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-control">
                                    <label class="label">
                                        <span class="font-medium label-text">Preferred Session Times</span>
                                    </label>
                                    <div class="flex flex-col gap-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                value="morning"
                                                wire:model="formData.preferences.preferred_session_times"
                                                class="checkbox checkbox-primary"
                                            />
                                            <span>Morning (8am - 12pm)</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                value="afternoon"
                                                wire:model="formData.preferences.preferred_session_times"
                                                class="checkbox checkbox-primary"
                                            />
                                            <span>Afternoon (12pm - 4pm)</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                value="evening"
                                                wire:model="formData.preferences.preferred_session_times"
                                                class="checkbox checkbox-primary"
                                            />
                                            <span>Evening (4pm - 8pm)</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                value="weekend"
                                                wire:model="formData.preferences.preferred_session_times"
                                                class="checkbox checkbox-primary"
                                            />
                                            <span>Weekends</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="form-control">
                                    <label class="label">
                                        <span class="font-medium label-text">Areas of Interest</span>
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <label class="flex items-center gap-2 px-3 py-1 transition-colors border rounded-full cursor-pointer hover:bg-base-200">
                                            <input
                                                type="checkbox"
                                                value="mathematics"
                                                wire:model="formData.preferences.areas_of_interest"
                                                class="checkbox checkbox-xs checkbox-primary"
                                            />
                                            <span>Mathematics</span>
                                        </label>
                                        <label class="flex items-center gap-2 px-3 py-1 transition-colors border rounded-full cursor-pointer hover:bg-base-200">
                                            <input
                                                type="checkbox"
                                                value="science"
                                                wire:model="formData.preferences.areas_of_interest"
                                                class="checkbox checkbox-xs checkbox-primary"
                                            />
                                            <span>Science</span>
                                        </label>
                                        <label class="flex items-center gap-2 px-3 py-1 transition-colors border rounded-full cursor-pointer hover:bg-base-200">
                                            <input
                                                type="checkbox"
                                                value="language"
                                                wire:model="formData.preferences.areas_of_interest"
                                                class="checkbox checkbox-xs checkbox-primary"
                                            />
                                            <span>Language Arts</span>
                                        </label>
                                        <label class="flex items-center gap-2 px-3 py-1 transition-colors border rounded-full cursor-pointer hover:bg-base-200">
                                            <input
                                                type="checkbox"
                                                value="history"
                                                wire:model="formData.preferences.areas_of_interest"
                                                class="checkbox checkbox-xs checkbox-primary"
                                            />
                                            <span>History</span>
                                        </label>
                                        <label class="flex items-center gap-2 px-3 py-1 transition-colors border rounded-full cursor-pointer hover:bg-base-200">
                                            <input
                                                type="checkbox"
                                                value="arts"
                                                wire:model="formData.preferences.areas_of_interest"
                                                class="checkbox checkbox-xs checkbox-primary"
                                            />
                                            <span>Arts</span>
                                        </label>
                                        <label class="flex items-center gap-2 px-3 py-1 transition-colors border rounded-full cursor-pointer hover:bg-base-200">
                                            <input
                                                type="checkbox"
                                                value="programming"
                                                wire:model="formData.preferences.areas_of_interest"
                                                class="checkbox checkbox-xs checkbox-primary"
                                            />
                                            <span>Programming</span>
                                        </label>
                                        <label class="flex items-center gap-2 px-3 py-1 transition-colors border rounded-full cursor-pointer hover:bg-base-200">
                                            <input
                                                type="checkbox"
                                                value="music"
                                                wire:model="formData.preferences.areas_of_interest"
                                                class="checkbox checkbox-xs checkbox-primary"
                                            />
                                            <span>Music</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-control">
                                    <label class="label">
                                        <span class="font-medium label-text">Special Requirements or Notes</span>
                                    </label>
                                    <textarea
                                        wire:model="formData.preferences.special_requirements"
                                        class="h-24 textarea textarea-bordered"
                                        placeholder="Any special requirements, accommodations, or notes about your children's learning needs"
                                    ></textarea>
                                </div>

                                <div class="form-control">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="formData.preferences.newsletter_subscription"
                                            class="checkbox checkbox-primary"
                                        />
                                        <span>Subscribe to our newsletter for updates and educational resources</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="justify-between mt-6 card-actions">
                            <button wire:click="setActiveSection('emergency')" class="btn btn-outline">
                                <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                Previous
                            </button>
                            <button wire:click="updatePreferences" class="btn btn-primary">
                                Complete Profile
                                <x-icon name="o-check" class="w-4 h-4 ml-2" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="p-4 mt-8 text-center rounded-lg bg-base-200">
            <p class="text-sm">
                Need help? Contact our support team at
                <a href="mailto:support@example.com" class="font-medium text-primary">support@example.com</a>
                or call us at <span class="font-medium">123-456-7890</span>
            </p>
        </div>
    </div>
</div>
