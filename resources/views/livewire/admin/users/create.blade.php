<?php

// admin/users/create.blade.php or admin/users/edit.blade.php (Volt Component)
use function Livewire\Volt\{state, mount, rules};
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Storage;

// Define component state
state([
    'user' => null,
    'isEdit' => false,
    'name' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => '',
    'profilePhoto' => null,
    'temporaryUrl' => null,
    'selectedRoles' => [],
    'permissions' => [],
    'customFields' => [
        'phone' => '',
        'address' => '',
        'bio' => '',
        'twitter' => '',
        'linkedin' => '',
    ],
    'availableRoles' => [],
    'availablePermissions' => [],
]);

// Validation rules
rules([
    'name' => 'required|string|max:255',
    'email' => 'required|string|email|max:255',
    'password' => fn() => $this->isEdit
        ? 'nullable|string|min:8|confirmed'
        : 'required|string|min:8|confirmed',
    'profilePhoto' => 'nullable|image|max:1024',
    'selectedRoles' => 'array',
    'permissions' => 'array',
    'customFields.phone' => 'nullable|string|max:20',
    'customFields.address' => 'nullable|string|max:255',
    'customFields.bio' => 'nullable|string|max:1000',
    'customFields.twitter' => 'nullable|string|max:255',
    'customFields.linkedin' => 'nullable|string|max:255',
]);

// Initialize component based on the route
mount(function ($user = null) {
    $this->availableRoles = Role::all();
    $this->availablePermissions = [
        'create_content', 'edit_content', 'delete_content',
        'manage_users', 'manage_settings', 'view_reports'
    ];

    if ($user) {
        $this->user = $user;
        $this->isEdit = true;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->selectedRoles = $user->roles->pluck('id')->toArray();
        $this->permissions = $user->permissions ?? [];
        $this->customFields = array_merge(
            [
                'phone' => '',
                'address' => '',
                'bio' => '',
                'twitter' => '',
                'linkedin' => '',
            ],
            $user->custom_fields ?? []
        );
    }
});

// Methods for handling the form
function save() {
    $this->validate();

    $userData = [
        'name' => $this->name,
        'email' => $this->email,
        'custom_fields' => $this->customFields,
        'permissions' => $this->permissions,
    ];

    if ($this->password) {
        $userData['password'] = Hash::make($this->password);
    }

    if ($this->isEdit) {
        $this->user->update($userData);
        $user = $this->user;
    } else {
        $user = User::create($userData);
    }

    // Handle profile photo upload
    if ($this->profilePhoto) {
        $fileName = Str::random(20) . '.' . $this->profilePhoto->getClientOriginalExtension();
        $path = $this->profilePhoto->storeAs('profile-photos', $fileName, 'public');
        $user->profile_photo_path = $path;
        $user->save();
    }

    // Sync roles
    $user->roles()->sync($this->selectedRoles);

    session()->flash('message', $this->isEdit ? 'User updated successfully!' : 'User created successfully!');

    return redirect()->route('admin.users.index');
}

function updatedProfilePhoto() {
    $this->temporaryUrl = $this->profilePhoto->temporaryUrl();
}

function cancel() {
    return redirect()->route('admin.users.index');
}

function generatePassword() {
    $this->password = Str::random(12);
    $this->password_confirmation = $this->password;
}

function togglePermission($permission) {
    if (in_array($permission, $this->permissions)) {
        $this->permissions = array_diff($this->permissions, [$permission]);
    } else {
        $this->permissions[] = $permission;
    }
}

?>

<div>
    <div class="py-10 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900">{{ $isEdit ? 'Edit User' : 'Create User' }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ $isEdit ? 'Update user information and permissions.' : 'Add a new user to your system.' }}
                    </p>
                </div>
            </div>

            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="overflow-hidden shadow sm:rounded-md">
                        <div class="px-4 py-5 bg-white sm:p-6">
                            @if (session()->has('message'))
                                <div class="p-4 mb-6 rounded-md bg-green-50">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-green-800">
                                                {{ session('message') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-6 gap-6">
                                <!-- Profile Photo -->
                                <div class="col-span-6 sm:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Profile Photo
                                    </label>
                                    <div class="flex items-center mt-2">
                                        <div class="mr-4">
                                            @if ($temporaryUrl)
                                                <img src="{{ $temporaryUrl }}" alt="Profile preview" class="object-cover w-20 h-20 rounded-full">
                                            @elseif ($isEdit && $user->profile_photo_url)
                                                <img src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}" class="object-cover w-20 h-20 rounded-full">
                                            @else
                                                <div class="flex items-center justify-center w-20 h-20 bg-gray-200 rounded-full">
                                                    <svg class="w-10 h-10 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <input
                                            type="file"
                                            wire:model="profilePhoto"
                                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                        />
                                    </div>
                                    @error('profilePhoto') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <!-- Name -->
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" id="name" wire:model="name" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    @error('name') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <!-- Email -->
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" id="email" wire:model="email" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    @error('email') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <!-- Password with Generate Button -->
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="password" class="block text-sm font-medium text-gray-700">
                                        Password {{ $isEdit ? '(leave blank to keep current)' : '' }}
                                    </label>
                                    <div class="flex mt-1 rounded-md shadow-sm">
                                        <input type="password" id="password" wire:model="password" class="flex-1 block w-full border-gray-300 rounded-none rounded-l-md focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <button type="button" wire:click="generatePassword" class="inline-flex items-center px-3 py-2 text-sm text-gray-500 border border-l-0 border-gray-300 rounded-r-md bg-gray-50">
                                            Generate
                                        </button>
                                    </div>
                                    @error('password') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <!-- Password Confirmation -->
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                                        Confirm Password
                                    </label>
                                    <input type="password" id="password_confirmation" wire:model="password_confirmation" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>

                                <!-- Phone -->
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                    <input type="text" id="phone" wire:model="customFields.phone" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    @error('customFields.phone') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <!-- Address -->
                                <div class="col-span-6">
                                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                                    <input type="text" id="address" wire:model="customFields.address" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    @error('customFields.address') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <!-- Bio -->
                                <div class="col-span-6">
                                    <label for="bio" class="block text-sm font-medium text-gray-700">Bio</label>
                                    <textarea id="bio" wire:model="customFields.bio" rows="3" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"></textarea>
                                    @error('customFields.bio') <span class="mt-2 text-sm text-red-600">{{ $message }}</span> @enderror
                                </div>

                                <!-- Social Media -->
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="twitter" class="block text-sm font-medium text-gray-700">Twitter</label>
                                    <div class="flex mt-1 rounded-md shadow-sm">
                                        <span class="inline-flex items-center px-3 text-sm text-gray-500 border border-r-0 border-gray-300 rounded-l-md bg-gray-50">
                                            twitter.com/
                                        </span>
                                        <input type="text" id="twitter" wire:model="customFields.twitter" class="flex-1 block w-full border-gray-300 rounded-none rounded-r-md focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    </div>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="linkedin" class="block text-sm font-medium text-gray-700">LinkedIn</label>
                                    <div class="flex mt-1 rounded-md shadow-sm">
                                        <span class="inline-flex items-center px-3 text-sm text-gray-500 border border-r-0 border-gray-300 rounded-l-md bg-gray-50">
                                            linkedin.com/in/
                                        </span>
                                        <input type="text" id="linkedin" wire:model="customFields.linkedin" class="flex-1 block w-full border-gray-300 rounded-none rounded-r-md focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    </div>
                                </div>

                                <!-- Roles -->
                                <div class="col-span-6">
                                    <label class="block mb-2 text-sm font-medium text-gray-700">Roles</label>
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                        @foreach ($availableRoles as $role)
                                            <div class="relative flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input
                                                        type="checkbox"
                                                        id="role_{{ $role->id }}"
                                                        wire:model="selectedRoles"
                                                        value="{{ $role->id }}"
                                                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                    >
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="role_{{ $role->id }}" class="font-medium text-gray-700">{{ $role->name }}</label>
                                                    <p class="text-gray-500">{{ $role->description }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Permissions -->
                                <div class="col-span-6">
                                    <label class="block mb-2 text-sm font-medium text-gray-700">Permissions</label>
                                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                        @foreach ($availablePermissions as $permission)
                                            <div class="relative flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input
                                                        type="checkbox"
                                                        id="perm_{{ $permission }}"
                                                        wire:model="permissions"
                                                        value="{{ $permission }}"
                                                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                    >
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="perm_{{ $permission }}" class="font-medium text-gray-700">
                                                        {{ ucwords(str_replace('_', ' ', $permission)) }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="px-4 py-3 text-right bg-gray-50 sm:px-6">
                            <button
                                type="button"
                                wire:click="cancel"
                                class="inline-flex justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="inline-flex justify-center px-4 py-2 ml-3 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ $isEdit ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
