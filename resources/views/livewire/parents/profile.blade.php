<?php

namespace App\Livewire\Parents;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\ParentProfile;
use App\Models\User;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public $user;
    public $parentProfile;
    public $children = [];

    // Tab state
    public $activeTab = 'profile';

    // Profile picture update
    public $newProfilePhoto;
    public $showProfilePhotoModal = false;

    // Edit mode states
    public $isEditingBasicInfo = false;
    public $isEditingContactInfo = false;
    public $isEditingPreferences = false;

    // Form data
    public $formData = [
        'name' => '',
        'email' => '',
        'phone_number' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'postal_code' => '',
        'country' => '',
        'emergency_contacts' => [],
        'preferred_communication_method' => '',
        'preferred_session_times' => [],
        'areas_of_interest' => [],
        'newsletter_subscription' => true,
    ];

    // Stats data
    public $stats = [
        'total_children' => 0,
        'total_sessions' => 0,
        'active_teachers' => 0,
        'upcoming_sessions' => 0,
        'total_materials' => 0,
        'account_age_days' => 0
    ];

    // Activity data
    public $recentActivity = [];

    public function mount()
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;
        $this->children = $this->parentProfile->children ?? collect([]);

        $this->loadProfileData();
        $this->loadStats();
        $this->loadRecentActivity();
    }

    public function loadProfileData()
    {
        if ($this->parentProfile) {
            $this->formData = [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone_number' => $this->parentProfile->phone_number ?? '',
                'address' => $this->parentProfile->address ?? '',
                'city' => $this->parentProfile->city ?? '',
                'state' => $this->parentProfile->state ?? '',
                'postal_code' => $this->parentProfile->postal_code ?? '',
                'country' => $this->parentProfile->country ?? '',
                'emergency_contacts' => $this->parentProfile->emergency_contacts ?? [],
                'preferred_communication_method' => $this->parentProfile->preferred_communication_method ?? 'email',
                'preferred_session_times' => $this->parentProfile->preferred_session_times ?? [],
                'areas_of_interest' => $this->parentProfile->areas_of_interest ?? [],
                'newsletter_subscription' => $this->parentProfile->newsletter_subscription ?? true,
            ];
        }
    }

    public function loadStats()
    {
        // In a real app, these would be calculated from database queries
        $this->stats = [
            'total_children' => $this->children->count(),
            'total_sessions' => rand(15, 50),
            'active_teachers' => rand(1, 5),
            'upcoming_sessions' => rand(2, 10),
            'total_materials' => rand(10, 30),
            'account_age_days' => $this->user->created_at
                ? Carbon::parse($this->user->created_at)->diffInDays(now())
                : 0
        ];
    }

    public function loadRecentActivity()
    {
        // In a real app, these would be fetched from the database
        $activities = [
            'session_attended', 'profile_updated', 'child_added',
            'session_scheduled', 'homework_submitted', 'material_downloaded'
        ];

        $this->recentActivity = [];

        for ($i = 0; $i < 5; $i++) {
            $activity = $activities[array_rand($activities)];
            $daysAgo = rand(0, 10);

            $childName = $this->children->count() > 0
                ? $this->children[rand(0, $this->children->count() - 1)]->name ?? 'Your child'
                : 'Your child';

            $description = match($activity) {
                'session_attended' => "$childName attended a learning session",
                'profile_updated' => "You updated your profile information",
                'child_added' => "You added $childName to your account",
                'session_scheduled' => "New session scheduled for $childName",
                'homework_submitted' => "$childName submitted homework assignment",
                'material_downloaded' => "You downloaded learning materials",
                default => "Activity recorded"
            };

            $icon = match($activity) {
                'session_attended' => 'o-academic-cap',
                'profile_updated' => 'o-user',
                'child_added' => 'o-user-plus',
                'session_scheduled' => 'o-calendar',
                'homework_submitted' => 'o-document-text',
                'material_downloaded' => 'o-arrow-down-tray',
                default => 'o-bell'
            };

            $color = match($activity) {
                'session_attended' => 'bg-primary text-primary-content',
                'profile_updated' => 'bg-info text-info-content',
                'child_added' => 'bg-success text-success-content',
                'session_scheduled' => 'bg-secondary text-secondary-content',
                'homework_submitted' => 'bg-accent text-accent-content',
                'material_downloaded' => 'bg-neutral text-neutral-content',
                default => 'bg-base-300'
            };

            $this->recentActivity[] = [
                'type' => $activity,
                'description' => $description,
                'date' => Carbon::now()->subDays($daysAgo)->format('M d, Y'),
                'time_ago' => Carbon::now()->subDays($daysAgo)->diffForHumans(),
                'icon' => $icon,
                'color' => $color
            ];
        }

        // Sort by most recent
        usort($this->recentActivity, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function toggleEditBasicInfo()
    {
        $this->isEditingBasicInfo = !$this->isEditingBasicInfo;

        if (!$this->isEditingBasicInfo) {
            $this->loadProfileData();
        }
    }

    public function toggleEditContactInfo()
    {
        $this->isEditingContactInfo = !$this->isEditingContactInfo;

        if (!$this->isEditingContactInfo) {
            $this->loadProfileData();
        }
    }

    public function toggleEditPreferences()
    {
        $this->isEditingPreferences = !$this->isEditingPreferences;

        if (!$this->isEditingPreferences) {
            $this->loadProfileData();
        }
    }

    public function saveBasicInfo()
    {
        $this->validate([
            'formData.name' => 'required|min:2',
            'formData.email' => 'required|email',
            'formData.phone_number' => 'required|min:10',
        ]);

        $this->user->update([
            'name' => $this->formData['name'],
            'email' => $this->formData['email'],
        ]);

        if ($this->parentProfile) {
            $this->parentProfile->update([
                'phone_number' => $this->formData['phone_number'],
            ]);
        } else {
            // Create parent profile if it doesn't exist
            $this->parentProfile = ParentProfile::create([
                'user_id' => $this->user->id,
                'phone_number' => $this->formData['phone_number'],
                'has_completed_profile' => true
            ]);
        }

        $this->isEditingBasicInfo = false;
        $this->dispatchBrowserEvent('profile-updated', ['message' => 'Basic information updated successfully']);
    }

    public function saveContactInfo()
    {
        $this->validate([
            'formData.address' => 'required',
            'formData.city' => 'required',
            'formData.state' => 'required',
            'formData.postal_code' => 'required',
            'formData.country' => 'required',
        ]);

        if ($this->parentProfile) {
            $this->parentProfile->update([
                'address' => $this->formData['address'],
                'city' => $this->formData['city'],
                'state' => $this->formData['state'],
                'postal_code' => $this->formData['postal_code'],
                'country' => $this->formData['country'],
            ]);
        }

        $this->isEditingContactInfo = false;
        $this->dispatchBrowserEvent('profile-updated', ['message' => 'Contact information updated successfully']);
    }

    public function savePreferences()
    {
        $this->validate([
            'formData.preferred_communication_method' => 'required',
            'formData.preferred_session_times' => 'array',
            'formData.areas_of_interest' => 'array',
        ]);

        if ($this->parentProfile) {
            $this->parentProfile->update([
                'preferred_communication_method' => $this->formData['preferred_communication_method'],
                'preferred_session_times' => $this->formData['preferred_session_times'],
                'areas_of_interest' => $this->formData['areas_of_interest'],
                'newsletter_subscription' => $this->formData['newsletter_subscription'],
            ]);
        }

        $this->isEditingPreferences = false;
        $this->dispatchBrowserEvent('profile-updated', ['message' => 'Preferences updated successfully']);
    }

    public function openProfilePhotoModal()
    {
        $this->showProfilePhotoModal = true;
    }

    public function closeProfilePhotoModal()
    {
        $this->showProfilePhotoModal = false;
        $this->newProfilePhoto = null;
    }

    public function saveProfilePhoto()
    {
        $this->validate([
            'newProfilePhoto' => 'required|image|max:1024', // 1MB Max
        ]);

        $photoPath = $this->newProfilePhoto->store('profile-photos', 'public');

        if ($this->parentProfile) {
            // Delete old photo if exists
            if ($this->parentProfile->profile_photo_path) {
                Storage::disk('public')->delete($this->parentProfile->profile_photo_path);
            }

            $this->parentProfile->update([
                'profile_photo_path' => $photoPath,
            ]);
        }

        $this->closeProfilePhotoModal();
        $this->dispatchBrowserEvent('profile-updated', ['message' => 'Profile photo updated successfully']);
    }

    public function downloadData()
    {
        // In a real app, you would generate a downloadable file with user data
        $this->dispatchBrowserEvent('profile-updated', ['message' => 'Your data is being prepared for download. You will receive an email when it\'s ready.']);
    }
}; ?>

<div
    x-data="{
        showNotification: false,
        notificationMessage: '',
        showNotificationFor(message, duration = 3000) {
            this.notificationMessage = message;
            this.showNotification = true;
            setTimeout(() => {
                this.showNotification = false;
            }, duration);
        }
    }"
    x-on:profile-updated.window="showNotificationFor($event.detail.message)"
>
    <!-- Notification Toast -->
    <div
        x-show="showNotification"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform scale-90"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-90"
        class="fixed z-50 max-w-sm shadow-lg top-4 right-4 alert alert-success"
    >
        <div>
            <x-icon name="o-check-circle" class="w-6 h-6" />
            <span x-text="notificationMessage">Profile updated!</span>
        </div>
    </div>

    <div class="min-h-screen p-6 bg-base-200">
        <div class="mx-auto max-w-7xl">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold">My Profile</h1>
                <p class="mt-1 text-base-content/70">View and manage your personal information</p>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left Sidebar -->
                <div class="space-y-6 lg:col-span-1">
                    <!-- Profile Card -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="items-center text-center card-body">
                            <div class="relative group">
                                <div class="avatar">
                                    <div class="w-24 h-24 rounded-full bg-base-300">
                                        @if($parentProfile && $parentProfile->profile_photo_path)
                                            <img src="{{ Storage::url($parentProfile->profile_photo_path) }}" alt="{{ $user->name }}" />
                                        @else
                                            <div class="flex items-center justify-center w-full h-full text-3xl font-semibold text-base-content/30">
                                                {{ substr($user->name, 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <button
                                    wire:click="openProfilePhotoModal"
                                    class="absolute bottom-0 right-0 bg-primary text-primary-content p-1.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                                    title="Change photo"
                                >
                                    <x-icon name="o-camera" class="w-4 h-4" />
                                </button>
                            </div>

                            <h2 class="mt-2 card-title">{{ $user->name }}</h2>
                            <p class="text-base-content/70">{{ $user->email }}</p>

                            <div class="mt-1 badge badge-outline">
                                Parent
                            </div>

                            <div class="mt-3 mb-3 divider"></div>

                            <div class="flex flex-col w-full gap-2">
                                <button class="gap-2 btn btn-outline btn-sm" wire:click="setActiveTab('profile')">
                                    <x-icon name="o-user" class="w-4 h-4" />
                                    <span>Profile Information</span>
                                </button>

                                <button class="gap-2 btn btn-outline btn-sm" wire:click="setActiveTab('security')">
                                    <x-icon name="o-lock-closed" class="w-4 h-4" />
                                    <span>Security Settings</span>
                                </button>

                                <button class="gap-2 btn btn-outline btn-sm" wire:click="setActiveTab('notifications')">
                                    <x-icon name="o-bell" class="w-4 h-4" />
                                    <span>Notification Preferences</span>
                                </button>

                                <button class="gap-2 btn btn-outline btn-sm" wire:click="setActiveTab('privacy')">
                                    <x-icon name="o-shield-check" class="w-4 h-4" />
                                    <span>Privacy Settings</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Account Stats -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h3 class="mb-4 text-lg card-title">Account Overview</h3>

                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary/10">
                                            <x-icon name="o-user-group" class="w-4 h-4 text-primary" />
                                        </div>
                                        <span>Children</span>
                                    </div>
                                    <span class="font-semibold">{{ $stats['total_children'] }}</span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-secondary/10">
                                            <x-icon name="o-calendar" class="w-4 h-4 text-secondary" />
                                        </div>
                                        <span>Sessions</span>
                                    </div>
                                    <span class="font-semibold">{{ $stats['total_sessions'] }}</span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-accent/10">
                                            <x-icon name="o-academic-cap" class="w-4 h-4 text-accent" />
                                        </div>
                                        <span>Teachers</span>
                                    </div>
                                    <span class="font-semibold">{{ $stats['active_teachers'] }}</span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-success/10">
                                            <x-icon name="o-book-open" class="w-4 h-4 text-success" />
                                        </div>
                                        <span>Materials</span>
                                    </div>
                                    <span class="font-semibold">{{ $stats['total_materials'] }}</span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-info/10">
                                            <x-icon name="o-clock" class="w-4 h-4 text-info" />
                                        </div>
                                        <span>Account Age</span>
                                    </div>
                                    <span class="font-semibold">{{ $stats['account_age_days'] }} days</span>
                                </div>
                            </div>

                            <div class="justify-center mt-4 card-actions">
                                <a href="{{ route('parents.dashboard') }}" class="btn btn-sm btn-outline btn-block">
                                    Go to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Profile Information Tab -->
                    <div class="{{ $activeTab === 'profile' ? 'block' : 'hidden' }}">
                        <!-- Basic Information Card -->
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg card-title">Basic Information</h3>
                                    <button
                                        wire:click="toggleEditBasicInfo"
                                        class="btn btn-sm btn-ghost"
                                    >
                                        @if($isEditingBasicInfo)
                                            Cancel
                                        @else
                                            <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                            Edit
                                        @endif
                                    </button>
                                </div>

                                <!-- View Mode -->
                                <div class="{{ $isEditingBasicInfo ? 'hidden' : 'block' }}">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">Full Name</label>
                                            <p class="mt-1">{{ $formData['name'] }}</p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">Email Address</label>
                                            <p class="mt-1">{{ $formData['email'] }}</p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">Phone Number</label>
                                            <p class="mt-1">{{ $formData['phone_number'] ?: 'Not provided' }}</p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">Member Since</label>
                                            <p class="mt-1">{{ $user->created_at ? $user->created_at->format('M d, Y') : 'Unknown' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Mode -->
                                <div class="{{ $isEditingBasicInfo ? 'block' : 'hidden' }}">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Full Name</span>
                                            </label>
                                            <input
                                                type="text"
                                                wire:model="formData.name"
                                                class="input input-bordered"
                                            />
                                            @error('formData.name')
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Email Address</span>
                                            </label>
                                            <input
                                                type="email"
                                                wire:model="formData.email"
                                                class="input input-bordered"
                                            />
                                            @error('formData.email')
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Phone Number</span>
                                            </label>
                                            <input
                                                type="tel"
                                                wire:model="formData.phone_number"
                                                class="input input-bordered"
                                            />
                                            @error('formData.phone_number')
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="flex justify-end mt-4">
                                        <button
                                            wire:click="toggleEditBasicInfo"
                                            class="mr-2 btn btn-ghost"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            wire:click="saveBasicInfo"
                                            class="btn btn-primary"
                                        >
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Card -->
                        <div class="mt-6 shadow-xl card bg-base-100">
                            <div class="card-body">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg card-title">Contact Information</h3>
                                    <button
                                        wire:click="toggleEditContactInfo"
                                        class="btn btn-sm btn-ghost"
                                    >
                                        @if($isEditingContactInfo)
                                            Cancel
                                        @else
                                            <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                            Edit
                                        @endif
                                    </button>
                                </div>

                                <!-- View Mode -->
                                <div class="{{ $isEditingContactInfo ? 'hidden' : 'block' }}">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="md:col-span-2">
                                            <label class="text-sm font-medium text-base-content/70">Address</label>
                                            <p class="mt-1">{{ $formData['address'] ?: 'Not provided' }}</p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">City</label>
                                            <p class="mt-1">{{ $formData['city'] ?: 'Not provided' }}</p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">State/Province</label>
                                            <p class="mt-1">{{ $formData['state'] ?: 'Not provided' }}</p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">Postal/ZIP Code</label>
                                            <p class="mt-1">{{ $formData['postal_code'] ?: 'Not provided' }}</p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">Country</label>
                                            <p class="mt-1">{{ $formData['country'] ?: 'Not provided' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Mode -->
                                <div class="{{ $isEditingContactInfo ? 'block' : 'hidden' }}">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="form-control md:col-span-2">
                                            <label class="label">
                                                <span class="label-text">Address</span>
                                            </label>
                                            <input
                                                type="text"
                                                wire:model="formData.address"
                                                class="input input-bordered"
                                                placeholder="Street address"
                                            />
                                            @error('formData.address')
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">City</span>
                                            </label>
                                            <input
                                                type="text"
                                                wire:model="formData.city"
                                                class="input input-bordered"
                                            />
                                            @error('formData.city')
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">State/Province</span>
                                            </label>
                                            <input
                                                type="text"
                                                wire:model="formData.state"
                                                class="input input-bordered"
                                            />
                                            @error('formData.state')
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Postal/ZIP Code</span>
                                            </label>
                                            <input
                                                type="text"
                                                wire:model="formData.postal_code"
                                                class="input input-bordered"
                                            />
                                            @error('formData.postal_code')
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Country</span>
                                            </label>
                                            <select
                                                wire:model="formData.country"
                                                class="select select-bordered"
                                            >
                                                <option value="">Select country</option>
                                                <option value="US">United States</option>
                                                <option value="CA">Canada</option>
                                                <option value="UK">United Kingdom</option>
                                                <option value="AU">Australia</option>
                                                <!-- Add more countries as needed -->
                                            </select>
                                            @error('formData.country')
                                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="flex justify-end mt-4">
                                        <button
                                            wire:click="toggleEditContactInfo"
                                            class="mr-2 btn btn-ghost"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            wire:click="saveContactInfo"
                                            class="btn btn-primary"
                                        >
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preferences Card -->
                        <div class="mt-6 shadow-xl card bg-base-100">
                            <div class="card-body">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg card-title">Preferences</h3>
                                    <button
                                        wire:click="toggleEditPreferences"
                                        class="btn btn-sm btn-ghost"
                                    >
                                        @if($isEditingPreferences)
                                            Cancel
                                        @else
                                            <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                            Edit
                                        @endif
                                    </button>
                                </div>

                                <!-- View Mode -->
                                <div class="{{ $isEditingPreferences ? 'hidden' : 'block' }}">
                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">Preferred Communication</label>
                                            <p class="mt-1 capitalize">{{ $formData['preferred_communication_method'] ?: 'Not specified' }}</p>
                                        </div>

                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">Preferred Session Times</label>
                                            @if(count($formData['preferred_session_times']) > 0)
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @foreach($formData['preferred_session_times'] as $time)
                                                        <div class="capitalize badge badge-outline">{{ $time }}</div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="mt-1">None specified</p>
                                            @endif
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="text-sm font-medium text-base-content/70">Areas of Interest</label>
                                            @if(count($formData['areas_of_interest']) > 0)
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @foreach($formData['areas_of_interest'] as $area)
                                                        <div class="capitalize badge badge-primary">{{ $area }}</div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="mt-1">None specified</p>
                                            @endif
                                        </div>

                                        <div>
                                            <label class="text-sm font-medium text-base-content/70">Newsletter Subscription</label>
                                            <p class="mt-1">{{ $formData['newsletter_subscription'] ? 'Subscribed' : 'Not subscribed' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Mode -->
                                <div class="{{ $isEditingPreferences ? 'block' : 'hidden' }}">
                                    <div class="space-y-6">
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Preferred Communication Method</span>
                                            </label>
                                            <div class="flex flex-col gap-2">
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        value="email"
                                                        wire:model="formData.preferred_communication_method"
                                                        class="radio radio-primary"
                                                    />
                                                    <span>Email</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        value="phone"
                                                        wire:model="formData.preferred_communication_method"
                                                        class="radio radio-primary"
                                                    />
                                                    <span>Phone Call</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        value="sms"
                                                        wire:model="formData.preferred_communication_method"
                                                        class="radio radio-primary"
                                                    />
                                                    <span>SMS/Text Message</span>
                                                </label>
                                            </div>
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
                                                        wire:model="formData.preferred_session_times"
                                                        class="checkbox checkbox-primary"
                                                    />
                                                    <span>Morning (8am - 12pm)</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        value="afternoon"
                                                        wire:model="formData.preferred_session_times"
                                                        class="checkbox checkbox-primary"
                                                    />
                                                    <span>Afternoon (12pm - 4pm)</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        value="evening"
                                                        wire:model="formData.preferred_session_times"
                                                        class="checkbox checkbox-primary"
                                                    />
                                                    <span>Evening (4pm - 8pm)</span>
                                                </label>
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        value="weekend"
                                                        wire:model="formData.preferred_session_times"
                                                        class="checkbox checkbox-primary"
                                                    />
                                                    <span>Weekends</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Areas of Interest</span>
                                            </label>
                                            <div class="flex flex-wrap gap-2">
                                                <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                                    <input
                                                        type="checkbox"
                                                        value="mathematics"
                                                        wire:model="formData.areas_of_interest"
                                                        class="checkbox checkbox-xs checkbox-primary"
                                                    />
                                                    <span>Mathematics</span>
                                                </label>
                                                <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                                    <input
                                                        type="checkbox"
                                                        value="science"
                                                        wire:model="formData.areas_of_interest"
                                                        class="checkbox checkbox-xs checkbox-primary"
                                                    />
                                                    <span>Science</span>
                                                </label>
                                                <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                                    <input
                                                        type="checkbox"
                                                        value="language"
                                                        wire:model="formData.areas_of_interest"
                                                        class="checkbox checkbox-xs checkbox-primary"
                                                    />
                                                    <span>Language Arts</span>
                                                </label>
                                                <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                                    <input
                                                        type="checkbox"
                                                        value="history"
                                                        wire:model="formData.areas_of_interest"
                                                        class="checkbox checkbox-xs checkbox-primary"
                                                    />
                                                    <span>History</span>
                                                </label>
                                                <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                                    <input
                                                        type="checkbox"
                                                        value="arts"
                                                        wire:model="formData.areas_of_interest"
                                                        class="checkbox checkbox-xs checkbox-primary"
                                                    />
                                                    <span>Arts</span>
                                                </label>
                                                <label class="flex items-center gap-2 px-3 py-1 border rounded-full cursor-pointer hover:bg-base-200">
                                                    <input
                                                        type="checkbox"
                                                        value="programming"
                                                        wire:model="formData.areas_of_interest"
                                                        class="checkbox checkbox-xs checkbox-primary"
                                                    />
                                                    <span>Programming</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-control">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    wire:model="formData.newsletter_subscription"
                                                    class="checkbox checkbox-primary"
                                                />
                                                <span>Subscribe to newsletter and educational updates</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="flex justify-end mt-4">
                                        <button
                                            wire:click="toggleEditPreferences"
                                            class="mr-2 btn btn-ghost"
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            wire:click="savePreferences"
                                            class="btn btn-primary"
                                        >
                                            Save Changes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings Tab -->
                    <div class="{{ $activeTab === 'security' ? 'block' : 'hidden' }}">
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h3 class="mb-4 text-lg card-title">Security Settings</h3>

                                <div class="space-y-6">
                                    <!-- Password Change Section -->
                                    <div class="pb-4 border-b">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h4 class="font-semibold">Change Password</h4>
                                                <p class="text-sm text-base-content/70">Update your account password</p>
                                            </div>
                                            <a href="{{ route('password.request') }}" class="btn btn-outline btn-sm">
                                                Change Password
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Two-factor Authentication -->
                                    <div class="pb-4 border-b">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h4 class="font-semibold">Two-Factor Authentication</h4>
                                                <p class="text-sm text-base-content/70">Add additional security to your account</p>
                                            </div>
                                            <button class="btn btn-outline btn-sm">
                                                Set Up 2FA
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Active Sessions -->
                                    <div class="pb-4 border-b">
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h4 class="font-semibold">Active Sessions</h4>
                                                <p class="text-sm text-base-content/70">View and manage your active login sessions</p>
                                            </div>
                                            <button class="btn btn-outline btn-sm">
                                                View Sessions
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Login History -->
                                    <div>
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                <h4 class="font-semibold">Login History</h4>
                                                <p class="text-sm text-base-content/70">Monitor recent account activity</p>
                                            </div>
                                            <button class="btn btn-outline btn-sm">
                                                View History
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Preferences Tab -->
                    <div class="{{ $activeTab === 'notifications' ? 'block' : 'hidden' }}">
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h3 class="mb-4 text-lg card-title">Notification Preferences</h3>

                                <div class="space-y-6">
                                    <div class="pb-4 border-b">
                                        <h4 class="mb-2 font-semibold">Email Notifications</h4>

                                        <div class="mt-3 space-y-2">
                                            <label class="flex items-center justify-between cursor-pointer">
                                                <span>Session Reminders</span>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>

                                            <label class="flex items-center justify-between cursor-pointer">
                                                <span>Homework Updates</span>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>

                                            <label class="flex items-center justify-between cursor-pointer">
                                                <span>Assessment Results</span>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>

                                            <label class="flex items-center justify-between cursor-pointer">
                                                <span>Teacher Messages</span>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>

                                            <label class="flex items-center justify-between cursor-pointer">
                                                <span>Administrative Updates</span>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>
                                        </div>
                                    </div>

                                    <div class="pb-4 border-b">
                                        <h4 class="mb-2 font-semibold">SMS Notifications</h4>

                                        <div class="mt-3 space-y-2">
                                            <label class="flex items-center justify-between cursor-pointer">
                                                <span>Session Reminders</span>
                                                <input type="checkbox" class="toggle toggle-primary" />
                                            </label>

                                            <label class="flex items-center justify-between cursor-pointer">
                                                <span>Important Alerts</span>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="mb-2 font-semibold">App Notifications</h4>

                                        <div class="mt-3 space-y-2">
                                            <label class="flex items-center justify-between cursor-pointer">
                                                <span>Push Notifications</span>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>

                                            <label class="flex items-center justify-between cursor-pointer">
                                                <span>In-App Notifications</span>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="justify-end mt-4 card-actions">
                                    <button class="btn btn-primary">
                                        Save Preferences
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy Settings Tab -->
                    <div class="{{ $activeTab === 'privacy' ? 'block' : 'hidden' }}">
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h3 class="mb-4 text-lg card-title">Privacy Settings</h3>

                                <div class="space-y-6">
                                    <div class="pb-4 border-b">
                                        <h4 class="mb-2 font-semibold">Data Sharing</h4>

                                        <div class="mt-3 space-y-2">
                                            <label class="flex items-center justify-between cursor-pointer">
                                                <div>
                                                    <span class="block">Share progress data with teachers</span>
                                                    <span class="text-xs text-base-content/70">Allow teachers to view your child's learning progress</span>
                                                </div>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>

                                            <label class="flex items-center justify-between cursor-pointer">
                                                <div>
                                                    <span class="block">Anonymous analytics</span>
                                                    <span class="text-xs text-base-content/70">Share anonymous usage data to improve our services</span>
                                                </div>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>
                                        </div>
                                    </div>

                                    <div class="pb-4 border-b">
                                        <h4 class="mb-2 font-semibold">Profile Visibility</h4>

                                        <div class="mt-3 space-y-2">
                                            <label class="flex items-center justify-between cursor-pointer">
                                                <div>
                                                    <span class="block">Show contact information to teachers</span>
                                                    <span class="text-xs text-base-content/70">Make your contact details visible to assigned teachers</span>
                                                </div>
                                                <input type="checkbox" checked class="toggle toggle-primary" />
                                            </label>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 class="mb-2 font-semibold">Data Management</h4>

                                        <div class="mt-3 space-y-4">
                                            <button
                                                wire:click="downloadData"
                                                class="btn btn-outline btn-block"
                                            >
                                                <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                                                Download My Data
                                            </button>

                                            <button class="btn btn-outline btn-error btn-block">
                                                <x-icon name="o-trash" class="w-4 h-4 mr-2" />
                                                Delete My Account
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="justify-end mt-4 card-actions">
                                    <button class="btn btn-primary">
                                        Save Privacy Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="mt-6 shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h3 class="mb-4 text-lg card-title">Recent Activity</h3>

                            <div class="space-y-4">
                                @foreach($recentActivity as $activity)
                                    <div class="flex gap-4">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $activity['color'] }}">
                                                <x-icon name="{{ $activity['icon'] }}" class="w-5 h-5" />
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="font-medium">{{ $activity['description'] }}</div>
                                            <div class="text-sm opacity-70">{{ $activity['time_ago'] }}</div>
                                        </div>
                                    </div>
                                    @if(!$loop->last)
                                        <div class="h-4 ml-5 border-l-2 border-dashed border-base-300"></div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Photo Upload Modal -->
    <div class="modal {{ $showProfilePhotoModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Update Profile Photo</h3>

            <div class="mt-4">
                @if($newProfilePhoto)
                    <div class="flex justify-center mx-auto mb-4 avatar">
                        <div class="w-32 h-32 rounded-full">
                            <img src="{{ $newProfilePhoto->temporaryUrl() }}" alt="New profile photo" />
                        </div>
                    </div>
                @endif

                <div class="w-full form-control">
                    <label class="label">
                        <span class="label-text">Choose a new profile photo</span>
                    </label>
                    <input
                        type="file"
                        wire:model="newProfilePhoto"
                        class="w-full file-input file-input-bordered"
                        accept="image/jpeg,image/png,image/gif"
                    />
                    <label class="label">
                        <span class="label-text-alt">JPEG, PNG or GIF, max 1MB</span>
                    </label>
                    @error('newProfilePhoto')
                        <span class="mt-1 text-sm text-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="modal-action">
                <button wire:click="closeProfilePhotoModal" class="btn">Cancel</button>
                <button
                    wire:click="saveProfilePhoto"
                    class="btn btn-primary"
                    @if(!$newProfilePhoto) disabled @endif
                >
                    Save Photo
                </button>
            </div>
        </div>
        <div class="modal-backdrop" wire:click="closeProfilePhotoModal"></div>
    </div>
</div>
