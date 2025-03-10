<?php

namespace App\Livewire\Parents\ProfileSetup;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\ParentProfile;
use App\Models\User;

new class extends Component {
    use WithFileUploads;

    // User and profile data
    public $user;
    public $parentProfile;

    // Current wizard step
    public $currentStep = 1;
    public $totalSteps = 5;
    public $stepCompleted = [
        1 => false,
        2 => false,
        3 => false,
        4 => false,
        5 => false,
    ];

    // Form data
    public $name;
    public $email;
    public $phone_number;
    public $address;
    public $city;
    public $state;
    public $postal_code;
    public $country;
    public $date_of_birth;
    public $profilePhoto;
    public $existingPhoto = null;

    // Emergency contacts
    public $emergency_contacts = [
        [
            'name' => '',
            'relationship' => '',
            'phone' => '',
            'email' => '',
            'is_primary' => true
        ]
    ];

    // Preferences
    public $preferred_communication_method = 'email';
    public $preferred_session_times = [];
    public $areas_of_interest = [];
    public $special_requirements = '';
    public $how_did_you_hear = '';

    // Terms & Privacy
    public $acceptTerms = false;
    public $acceptPrivacyPolicy = false;
    public $newsletter_subscription = true;

    // Validation rules
    protected $rules = [
        // Step 1: Basic Information
        'name' => 'required|min:2',
        'email' => 'required|email',
        'phone_number' => 'required|min:10',
        'date_of_birth' => 'required|date|before:today',

        // Step 2: Address
        'address' => 'required|min:5',
        'city' => 'required',
        'state' => 'required',
        'postal_code' => 'required',
        'country' => 'required',

        // Step 3: Emergency Contacts
        'emergency_contacts.*.name' => 'required|min:2',
        'emergency_contacts.*.relationship' => 'required',
        'emergency_contacts.*.phone' => 'required|min:10',
        'emergency_contacts.*.email' => 'required|email',

        // Step 4: Preferences
        'preferred_communication_method' => 'required|in:email,phone,sms',
        'preferred_session_times' => 'required|array|min:1',
        'areas_of_interest' => 'array',

        // Step 5: Terms & Privacy
        'acceptTerms' => 'accepted',
        'acceptPrivacyPolicy' => 'accepted',
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        // If profile exists, populate form fields
        if ($this->parentProfile) {
            $this->name = $this->user->name;
            $this->email = $this->user->email;
            $this->phone_number = $this->parentProfile->phone_number;
            $this->address = $this->parentProfile->address ?? '';
            $this->city = $this->parentProfile->city ?? '';
            $this->state = $this->parentProfile->state ?? '';
            $this->postal_code = $this->parentProfile->postal_code ?? '';
            $this->country = $this->parentProfile->country ?? '';
            $this->date_of_birth = $this->parentProfile->date_of_birth ?? '';
            $this->existingPhoto = $this->parentProfile->profile_photo_path;

            // Emergency contacts
            if ($this->parentProfile->emergency_contacts) {
                $this->emergency_contacts = $this->parentProfile->emergency_contacts;
            }

            // Preferences
            $this->preferred_communication_method = $this->parentProfile->preferred_communication_method ?? 'email';
            $this->preferred_session_times = $this->parentProfile->preferred_session_times ?? [];
            $this->areas_of_interest = $this->parentProfile->areas_of_interest ?? [];
            $this->special_requirements = $this->parentProfile->special_requirements ?? '';
            $this->how_did_you_hear = $this->parentProfile->how_did_you_hear ?? '';

            // Check which steps are completed
            $this->checkStepCompletion();
        }
    }

    public function checkStepCompletion()
    {
        // Step 1
        $this->stepCompleted[1] = !empty($this->name) &&
                                 !empty($this->email) &&
                                 !empty($this->phone_number) &&
                                 !empty($this->date_of_birth);

        // Step 2
        $this->stepCompleted[2] = !empty($this->address) &&
                                 !empty($this->city) &&
                                 !empty($this->state) &&
                                 !empty($this->postal_code) &&
                                 !empty($this->country);

        // Step 3
        $this->stepCompleted[3] = count($this->emergency_contacts) > 0 &&
                                 !empty($this->emergency_contacts[0]['name']) &&
                                 !empty($this->emergency_contacts[0]['phone']);

        // Step 4
        $this->stepCompleted[4] = !empty($this->preferred_communication_method) &&
                                 count($this->preferred_session_times) > 0;

        // Step 5
        $this->stepCompleted[5] = $this->acceptTerms &&
                                 $this->acceptPrivacyPolicy;
    }

    public function gotoStep($step)
    {
        $this->checkStepCompletion();

        // Ensure we can't skip too far ahead
        $maxAccessibleStep = 1;
        for ($i = 1; $i <= $this->totalSteps; $i++) {
            if ($this->stepCompleted[$i]) {
                $maxAccessibleStep = $i + 1;
            }
        }

        // Can only access completed steps or next step
        if ($step <= $maxAccessibleStep) {
            $this->currentStep = $step;
        }
    }

    public function nextStep()
    {
        if ($this->currentStep < $this->totalSteps) {
            $this->validateCurrentStep();
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
        // Validate only the fields for the current step
        $validationRules = [];

        switch ($this->currentStep) {
            case 1:
                $validationRules = [
                    'name' => $this->rules['name'],
                    'email' => $this->rules['email'],
                    'phone_number' => $this->rules['phone_number'],
                    'date_of_birth' => $this->rules['date_of_birth'],
                ];
                break;

            case 2:
                $validationRules = [
                    'address' => $this->rules['address'],
                    'city' => $this->rules['city'],
                    'state' => $this->rules['state'],
                    'postal_code' => $this->rules['postal_code'],
                    'country' => $this->rules['country'],
                ];
                break;

            case 3:
                // Validate all emergency contacts
                foreach ($this->emergency_contacts as $index => $contact) {
                    $validationRules["emergency_contacts.{$index}.name"] = $this->rules['emergency_contacts.*.name'];
                    $validationRules["emergency_contacts.{$index}.relationship"] = $this->rules['emergency_contacts.*.relationship'];
                    $validationRules["emergency_contacts.{$index}.phone"] = $this->rules['emergency_contacts.*.phone'];
                    $validationRules["emergency_contacts.{$index}.email"] = $this->rules['emergency_contacts.*.email'];
                }
                break;

            case 4:
                $validationRules = [
                    'preferred_communication_method' => $this->rules['preferred_communication_method'],
                    'preferred_session_times' => $this->rules['preferred_session_times'],
                ];
                break;

            case 5:
                $validationRules = [
                    'acceptTerms' => $this->rules['acceptTerms'],
                    'acceptPrivacyPolicy' => $this->rules['acceptPrivacyPolicy'],
                ];
                break;
        }

        // Validate current step fields
        $this->validate($validationRules);

        // Mark current step as completed
        $this->stepCompleted[$this->currentStep] = true;
    }

    public function addEmergencyContact()
    {
        $this->emergency_contacts[] = [
            'name' => '',
            'relationship' => '',
            'phone' => '',
            'email' => '',
            'is_primary' => false
        ];
    }

    public function removeEmergencyContact($index)
    {
        if (count($this->emergency_contacts) > 1) {
            // Ensure at least one contact remains
            unset($this->emergency_contacts[$index]);
            $this->emergency_contacts = array_values($this->emergency_contacts);
        }
    }

    public function saveProfile()
    {
        // Validate all fields
        $this->validate();

        // Upload profile photo if provided
        $photoPath = $this->existingPhoto;
        if ($this->profilePhoto) {
            $photoPath = $this->profilePhoto->store('profile-photos', 'public');
        }

        // Update user name and email
        $this->user->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        // Create or update parent profile
        $profileData = [
            'user_id' => $this->user->id,
            'phone_number' => $this->phone_number,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'date_of_birth' => $this->date_of_birth,
            'profile_photo_path' => $photoPath,
            'emergency_contacts' => $this->emergency_contacts,
            'preferred_communication_method' => $this->preferred_communication_method,
            'preferred_session_times' => $this->preferred_session_times,
            'areas_of_interest' => $this->areas_of_interest,
            'special_requirements' => $this->special_requirements,
            'how_did_you_hear' => $this->how_did_you_hear,
            'newsletter_subscription' => $this->newsletter_subscription,
            'has_completed_profile' => true
        ];

        if ($this->parentProfile) {
            $this->parentProfile->update($profileData);
        } else {
            ParentProfile::create($profileData);
        }

        session()->flash('success', 'Profile setup completed successfully!');

        // Redirect to dashboard
        return redirect()->route('parents.dashboard');
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="mb-2 text-3xl font-bold">Profile Setup</h1>
            <p class="text-base-content/70">Complete your profile to get started with our learning platform</p>
        </div>

        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex justify-between">
                @for($i = 1; $i <= $totalSteps; $i++)
                    <div
                        class="relative z-10 flex flex-col items-center cursor-pointer step-item"
                        wire:click="gotoStep({{ $i }})"
                    >
                        <div class="flex items-center justify-center">
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center {{ $i < $currentStep ? 'bg-primary text-primary-content' : ($i === $currentStep ? 'bg-primary text-primary-content' : ($stepCompleted[$i] ? 'bg-primary text-primary-content' : 'bg-base-300 text-base-content')) }}"
                            >
                                @if($stepCompleted[$i] && $i < $currentStep)
                                    <x-icon name="o-check" class="w-6 h-6" />
                                @else
                                    {{ $i }}
                                @endif
                            </div>
                        </div>
                        <div class="text-xs mt-2 font-medium {{ $i === $currentStep ? 'text-primary' : '' }}">
                            @switch($i)
                                @case(1)
                                    Basic Info
                                    @break
                                @case(2)
                                    Address
                                    @break
                                @case(3)
                                    Emergency Contacts
                                    @break
                                @case(4)
                                    Preferences
                                    @break
                                @case(5)
                                    Terms & Privacy
                                    @break
                            @endswitch
                        </div>
                    </div>
                @endfor
            </div>
            <div class="absolute z-0 w-full h-1 mt-5 bg-base-300">
                <div class="h-1 bg-primary" style="width: {{ (($currentStep - 1) / ($totalSteps - 1)) * 100 }}%"></div>
            </div>
        </div>

        @if(session()->has('success'))
            <div class="mb-6 alert alert-success">
                <x-icon name="o-check-circle" class="w-6 h-6" />
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Form Card -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <!-- Step 1: Basic Information -->
                <div class="{{ $currentStep === 1 ? 'block' : 'hidden' }}">
                    <h2 class="mb-6 text-xl font-bold">Basic Information</h2>

                    <div class="flex flex-col gap-6 md:flex-row">
                        <!-- Left column with photo upload -->
                        <div class="md:w-1/3">
                            <div class="text-center">
                                <div class="flex justify-center w-full mb-3 avatar">
                                    <div class="w-32 h-32 rounded-full bg-base-300">
                                        @if($existingPhoto)
                                            <img src="{{ Storage::url($existingPhoto) }}" alt="{{ $name }}" />
                                        @elseif($profilePhoto)
                                            <img src="{{ $profilePhoto->temporaryUrl() }}" alt="{{ $name }}" />
                                        @else
                                            <div class="flex items-center justify-center h-full text-3xl font-bold text-base-content/30">
                                                {{ $name ? substr($name, 0, 1) : 'P' }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <label for="profilePhoto" class="w-full btn btn-outline btn-sm">
                                    <x-icon name="o-camera" class="w-4 h-4 mr-2" />
                                    Upload Photo
                                </label>
                                <input
                                    type="file"
                                    id="profilePhoto"
                                    wire:model="profilePhoto"
                                    class="hidden"
                                    accept="image/jpeg,image/png,image/gif"
                                />

                                @error('profilePhoto')
                                    <span class="block mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror

                                <p class="mt-2 text-xs opacity-70">JPEG, PNG or GIF, max 2MB</p>
                            </div>
                        </div>

                        <!-- Right column with form fields -->
                        <div class="space-y-4 md:w-2/3">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Full Name</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="name"
                                    class="input input-bordered"
                                    placeholder="Enter your full name"
                                />
                                @error('name')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Email Address</span>
                                </label>
                                <input
                                    type="email"
                                    wire:model="email"
                                    class="input input-bordered"
                                    placeholder="Enter your email address"
                                />
                                @error('email')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Phone Number</span>
                                </label>
                                <input
                                    type="tel"
                                    wire:model="phone_number"
                                    class="input input-bordered"
                                    placeholder="Enter your phone number"
                                />
                                @error('phone_number')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Date of Birth</span>
                                </label>
                                <input
                                    type="date"
                                    wire:model="date_of_birth"
                                    class="input input-bordered"
                                />
                                @error('date_of_birth')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Address -->
                <div class="{{ $currentStep === 2 ? 'block' : 'hidden' }}">
                    <h2 class="mb-6 text-xl font-bold">Address Information</h2>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="form-control md:col-span-2">
                            <label class="label">
                                <span class="label-text">Street Address</span>
                            </label>
                            <input
                                type="text"
                                wire:model="address"
                                class="input input-bordered"
                                placeholder="Enter your street address"
                            />
                            @error('address')
                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">City</span>
                            </label>
                            <input
                                type="text"
                                wire:model="city"
                                class="input input-bordered"
                                placeholder="Enter your city"
                            />
                            @error('city')
                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">State/Province</span>
                            </label>
                            <input
                                type="text"
                                wire:model="state"
                                class="input input-bordered"
                                placeholder="Enter your state/province"
                            />
                            @error('state')
                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Postal/ZIP Code</span>
                            </label>
                            <input
                                type="text"
                                wire:model="postal_code"
                                class="input input-bordered"
                                placeholder="Enter your postal/ZIP code"
                            />
                            @error('postal_code')
                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Country</span>
                            </label>
                            <select wire:model="country" class="select select-bordered">
                                <option value="">Select a country</option>
                                <option value="US">United States</option>
                                <option value="CA">Canada</option>
                                <option value="UK">United Kingdom</option>
                                <option value="AU">Australia</option>
                                <option value="FR">France</option>
                                <option value="DE">Germany</option>
                                <!-- Add more countries as needed -->
                            </select>
                            @error('country')
                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Step 3: Emergency Contacts -->
                <div class="{{ $currentStep === 3 ? 'block' : 'hidden' }}">
                    <h2 class="mb-6 text-xl font-bold">Emergency Contacts</h2>

                    <div class="space-y-8">
                        @foreach($emergency_contacts as $index => $contact)
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
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="emergency_contacts.{{ $index }}.name"
                                            class="input input-bordered"
                                            placeholder="Enter contact name"
                                        />
                                        @error("emergency_contacts.{$index}.name")
                                            <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text">Relationship</span>
                                        </label>
                                        <select
                                            wire:model="emergency_contacts.{{ $index }}.relationship"
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
                                        @error("emergency_contacts.{$index}.relationship")
                                            <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text">Phone Number</span>
                                        </label>
                                        <input
                                            type="tel"
                                            wire:model="emergency_contacts.{{ $index }}.phone"
                                            class="input input-bordered"
                                            placeholder="Enter phone number"
                                        />
                                        @error("emergency_contacts.{$index}.phone")
                                            <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-control">
                                        <label class="label">
                                            <span class="label-text">Email Address</span>
                                        </label>
                                        <input
                                            type="email"
                                            wire:model="emergency_contacts.{{ $index }}.email"
                                            class="input input-bordered"
                                            placeholder="Enter email address"
                                        />
                                        @error("emergency_contacts.{$index}.email")
                                            <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="text-center">
                            <button
                                wire:click="addEmergencyContact"
                                class="btn btn-outline"
                            >
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Add Another Contact
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Preferences -->
                <div class="{{ $currentStep === 4 ? 'block' : 'hidden' }}">
                    <h2 class="mb-6 text-xl font-bold">Learning Preferences</h2>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Preferred Communication Method</span>
                                </label>
                                <div class="flex flex-col gap-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            value="email"
                                            wire:model="preferred_communication_method"
                                            class="radio radio-primary"
                                        />
                                        <span>Email</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            value="phone"
                                            wire:model="preferred_communication_method"
                                            class="radio radio-primary"
                                        />
                                        <span>Phone Call</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            value="sms"
                                            wire:model="preferred_communication_method"
                                            class="radio radio-primary"
                                        />
                                        <span>SMS/Text Message</span>
                                    </label>
                                </div>
                                @error('preferred_communication_method')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Preferred Session Times</span>
                                </label>
                                <div class="flex flex-col gap-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            value="morning"
                                            wire:model="preferred_session_times"
                                            class="checkbox checkbox-primary"
                                        />
                                        <span>Morning (8am - 12pm)</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            value="afternoon"
                                            wire:model="preferred_session_times"
                                            class="checkbox checkbox-primary"
                                        />
                                        <span>Afternoon (12pm - 4pm)</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            value="evening"
                                            wire:model="preferred_session_times"
                                            class="checkbox checkbox-primary"
                                        />
                                        <span>Evening (4pm - 8pm)</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            value="weekend"
                                            wire:model="preferred_session_times"
                                            class="checkbox checkbox-primary"
                                        />
                                        <span>Weekends</span>
                                    </label>
                                </div>
                                @error('preferred_session_times')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Areas of Interest</span>
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                        <input
                                            type="checkbox"
                                            value="mathematics"
                                            wire:model="areas_of_interest"
                                            class="checkbox checkbox-xs checkbox-primary"
                                        />
                                        <span>Mathematics</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                        <input
                                            type="checkbox"
                                            value="science"
                                            wire:model="areas_of_interest"
                                            class="checkbox checkbox-xs checkbox-primary"
                                        />
                                        <span>Science</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                        <input
                                            type="checkbox"
                                            value="language"
                                            wire:model="areas_of_interest"
                                            class="checkbox checkbox-xs checkbox-primary"
                                        />
                                        <span>Language Arts</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                        <input
                                            type="checkbox"
                                            value="history"
                                            wire:model="areas_of_interest"
                                            class="checkbox checkbox-xs checkbox-primary"
                                        />
                                        <span>History</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                        <input
                                            type="checkbox"
                                            value="arts"
                                            wire:model="areas_of_interest"
                                            class="checkbox checkbox-xs checkbox-primary"
                                        />
                                        <span>Arts</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                        <input
                                            type="checkbox"
                                            value="programming"
                                            wire:model="areas_of_interest"
                                            class="checkbox checkbox-xs checkbox-primary"
                                        />
                                        <span>Programming</span>
                                    </label>
                                    <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                        <input
                                            type="checkbox"
                                            value="music"
                                            wire:model="areas_of_interest"
                                            class="checkbox checkbox-xs checkbox-primary"
                                        />
                                        <span>Music</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Special Requirements or Notes</span>
                                </label>
                                <textarea
                                    wire:model="special_requirements"
                                    class="h-24 textarea textarea-bordered"
                                    placeholder="Any special requirements, accommodations, or notes"
                                ></textarea>
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">How did you hear about us?</span>
                                </label>
                                <select wire:model="how_did_you_hear" class="select select-bordered">
                                    <option value="">Select an option</option>
                                    <option value="friend">Friend/Family Referral</option>
                                    <option value="search">Search Engine</option>
                                    <option value="social">Social Media</option>
                                    <option value="advertisement">Advertisement</option>
                                    <option value="school">School/Teacher</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Terms & Privacy -->
                <div class="{{ $currentStep === 5 ? 'block' : 'hidden' }}">
                    <h2 class="mb-6 text-xl font-bold">Terms & Privacy</h2>

                    <div class="space-y-6">
                        <div class="p-4 rounded-lg bg-base-200">
                            <h3 class="mb-2 font-bold">Terms of Service</h3>
                            <div class="p-2 mb-3 overflow-y-auto rounded max-h-48 bg-base-100">
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla quam velit, vulputate eu pharetra nec, mattis ac neque. Duis vulputate commodo lectus, ac blandit elit tincidunt id. Sed rhoncus, tortor sed eleifend tristique, tortor mauris molestie elit, et lacinia ipsum quam nec dui.</p>
                                <p class="mt-2">Donec ut libero sed arcu vehicula ultricies a non tortor. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean ut gravida lorem. Ut turpis felis, pulvinar a semper sed, adipiscing id dolor.</p>
                                <p class="mt-2">Pellentesque auctor nisi id magna consequat sagittis. Curabitur dapibus enim sit amet elit pharetra tincidunt feugiat nisl imperdiet. Ut convallis libero in urna ultrices accumsan. Donec sed odio eros.</p>
                            </div>
                            <div class="form-control">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="acceptTerms"
                                        class="checkbox checkbox-primary"
                                    />
                                    <span>I accept the Terms of Service</span>
                                </label>
                                @error('acceptTerms')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="p-4 rounded-lg bg-base-200">
                            <h3 class="mb-2 font-bold">Privacy Policy</h3>
                            <div class="p-2 mb-3 overflow-y-auto rounded max-h-48 bg-base-100">
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla quam velit, vulputate eu pharetra nec, mattis ac neque. Duis vulputate commodo lectus, ac blandit elit tincidunt id. Sed rhoncus, tortor sed eleifend tristique, tortor mauris molestie elit, et lacinia ipsum quam nec dui.</p>
                                <p class="mt-2">Donec ut libero sed arcu vehicula ultricies a non tortor. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean ut gravida lorem. Ut turpis felis, pulvinar a semper sed, adipiscing id dolor.</p>
                                <p class="mt-2">Pellentesque auctor nisi id magna consequat sagittis. Curabitur dapibus enim sit amet elit pharetra tincidunt feugiat nisl imperdiet. Ut convallis libero in urna ultrices accumsan. Donec sed odio eros.</p>
                            </div>
                            <div class="form-control">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="acceptPrivacyPolicy"
                                        class="checkbox checkbox-primary"
                                    />
                                    <span>I accept the Privacy Policy</span>
                                </label>
                                @error('acceptPrivacyPolicy')
                                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-control">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="newsletter_subscription"
                                    class="checkbox checkbox-primary"
                                />
                                <span>Subscribe to our newsletter for updates and educational resources</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between mt-8">
                    <button
                        wire:click="previousStep"
                        class="btn btn-outline {{ $currentStep === 1 ? 'invisible' : '' }}"
                    >
                        <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                        Previous
                    </button>

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
                            wire:click="saveProfile"
                            class="btn btn-primary"
                        >
                            Complete Setup
                            <x-icon name="o-check" class="w-4 h-4 ml-2" />
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Information Section -->
        <div class="p-4 mt-8 text-center rounded-lg bg-primary bg-opacity-10">
            <p class="text-sm">
                Need help? Contact our support team at
                <a href="mailto:support@example.com" class="font-medium underline">support@example.com</a>
                or call us at <span class="font-medium">123-456-7890</span>
            </p>
        </div>
    </div>
</div>
