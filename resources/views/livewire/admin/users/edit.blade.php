<?php

use function Livewire\Volt\{state, rules, mount};
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

// Define the state for the component
state([
    'user' => null,
    'userId' => '',
    'name' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => '',
    'roles' => [],
    'selectedRoles' => [],
    'isActive' => true,
    'formTitle' => '',
]);

// Set up validation rules
rules([
    'name' => ['required', 'string', 'max:255'],
    'email' => [
        'required',
        'string',
        'email',
        'max:255',
        Rule::unique('users')->ignore(fn() => $this->userId),
    ],
    'password' => ['nullable', 'string', 'min:8', 'confirmed'],
    'selectedRoles' => ['sometimes', 'array'],
    'isActive' => ['boolean'],
]);

// Mount the component and initialize data
mount(function ($user) {
    // Check if $user is a string (ID) or already a User object
    if (is_string($user) || is_numeric($user)) {
        $this->userId = $user;
        $this->user = User::findOrFail($user);
    } else {
        $this->user = $user;
        $this->userId = $user->id;
    }

    // Now safely access the user object properties
    $this->formTitle = 'Edit User: ' . $this->user->name;
    $this->name = $this->user->name;
    $this->email = $this->user->email;
    $this->isActive = $this->user->is_active ?? true;

    // Check if roles functionality exists in your application
    // If your app doesn't have a Role model, we'll handle it safely
    try {
        // First, check if the Spatie Permission package is installed
        if (class_exists('\Spatie\Permission\Models\Role')) {
            $this->roles = \Spatie\Permission\Models\Role::all();
            if (method_exists($this->user, 'roles')) {
                $this->selectedRoles = $this->user->roles->pluck('id')->toArray();
            }
        }
        // If not, check for a custom Role model
        else if (class_exists('\App\Models\Role')) {
            $this->roles = \App\Models\Role::all();
            if (method_exists($this->user, 'roles')) {
                $this->selectedRoles = $this->user->roles->pluck('id')->toArray();
            }
        } else {
            // No roles system found
            $this->roles = [];
            $this->selectedRoles = [];
        }
    } catch (\Exception $e) {
        // If any error occurs, default to empty roles
        $this->roles = [];
        $this->selectedRoles = [];
    }
});

// Method to update the user
$updateUser = function () {
    $this->validate();

    $userData = [
        'name' => $this->name,
        'email' => $this->email,
    ];

    // Check for is_active column
    if (Schema::hasColumn('users', 'is_active')) {
        $userData['is_active'] = $this->isActive;
    }

    // Only update password if provided
    if (!empty($this->password)) {
        $userData['password'] = Hash::make($this->password);
    }

    // Update user record
    $this->user->update($userData);

    // Sync roles if relationship exists - safely handle this
    try {
        if (!empty($this->selectedRoles) && method_exists($this->user, 'roles')) {
            $this->user->roles()->sync($this->selectedRoles);
        }
    } catch (\Exception $e) {
        // Just continue if roles functionality doesn't exist
    }

    session()->flash('success', 'User updated successfully');

    return redirect()->route('admin.users.show', $this->user);
};

?>

<div class="py-6">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <h2 class="mb-4 text-2xl font-bold">{{ $formTitle }}</h2>

                @if (session('success'))
                    <div class="px-4 py-3 mb-4 text-green-700 bg-green-100 border border-green-400 rounded">
                        {{ session('success') }}
                    </div>
                @endif

                <form wire:submit="updateUser">
                    <!-- Name -->
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" id="name" wire:model="name" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        @error('name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- Email -->
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="email" wire:model="email" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        @error('email') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password (leave blank to keep current)</label>
                        <input type="password" id="password" wire:model="password" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        @error('password') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- Password Confirmation -->
                    <div class="mb-4">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                        <input type="password" id="password_confirmation" wire:model="password_confirmation" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    </div>

                    <!-- Active Status -->
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="isActive" class="text-indigo-600 border-gray-300 rounded shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-600">Active</span>
                        </label>
                    </div>

                    <!-- Roles Selection - Only show if roles are available -->
                    @if(count($roles) > 0)
                    <div class="mb-4">
                        <label class="block mb-2 text-sm font-medium text-gray-700">Roles</label>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
                            @foreach ($roles as $role)
                                <label class="flex items-center">
                                    <input type="checkbox"
                                           wire:model="selectedRoles"
                                           value="{{ $role->id }}"
                                           class="text-indigo-600 border-gray-300 rounded shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-600">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedRoles') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>
                    @endif

                    <!-- Last Login Info (with proper error handling) -->
                    <div class="mb-6 text-sm text-gray-600">
                        <p>Last login: {{ $user && isset($user->last_login_at) ? $user->last_login_at->diffForHumans() : 'Never' }}</p>
                        <p>Created: {{ $user && isset($user->created_at) ? $user->created_at->format('M d, Y') : 'Unknown' }}</p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end space-x-3">
                        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase transition duration-150 ease-in-out bg-gray-300 border border-transparent rounded-md hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring ring-gray-300 disabled:opacity-25">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition duration-150 ease-in-out bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:border-indigo-800 focus:ring ring-indigo-300 disabled:opacity-25">
                            Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
