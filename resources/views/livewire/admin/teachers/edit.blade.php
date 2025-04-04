<?php

use function Livewire\Volt\{state, computed, mount, boot};
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

// Define state variables
state([
    'teacher' => null,
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'qualification' => '',
    'bio' => '',
    'subjects' => [],
    'status' => 'active',
    'change_password' => false,
    'password' => '',
    'password_confirmation' => '',
    'photo' => null,
    'current_step' => 1,
    'total_steps' => 3,
    'availableSubjects' => [],
    'currentPhoto' => null,
    'existingPhoto' => null,
    'formSubmitted' => false,
]);

// Mount component with teacher data
mount(function(User $teacher) {
    $this->teacher = $teacher;
    
    // Load basic information
    $this->name = $teacher->name;
    $this->email = $teacher->email;
    $this->phone = $teacher->phone;
    $this->status = $teacher->status;
    
// Load profile information
if ($profile = $teacher->teacherProfile) {
    $this->address = $profile->whatsapp;
    $this->bio = $profile->bio;
    
    // Get qualification from education JSON field
    if ($profile->education && isset($profile->education['qualification'])) {
        $this->qualification = $profile->education['qualification'];
    }
}
    // Load subjects
    $this->subjects = $teacher->subjects()->pluck('subjects.id')->toArray();
    
    // Load photo
    if ($teacher->avatar) {
        $this->existingPhoto = $teacher->avatar;
    }
    
    // Get available subjects
    $this->loadSubjects();
});

// Load available subjects
$loadSubjects = function() {
    try {
        $this->availableSubjects = \App\Models\Subject::orderBy('name')->get();
    } catch (\Exception $e) {
        // Handle case when Subject model might not exist
        $this->availableSubjects = [];
    }
};

// Next step in the multi-step form
$nextStep = function() {
    if ($this->current_step < $this->total_steps) {
        $this->validateStep($this->current_step);
        $this->current_step++;
    }
};

// Previous step in the multi-step form
$prevStep = function() {
    if ($this->current_step > 1) {
        $this->current_step--;
    }
};

// Validate each step
$validateStep = function($step) {
    $rules = [];
    
    if ($step === 1) {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->teacher->id)],
            'phone' => 'nullable|string|max:20',
        ];
    } elseif ($step === 2) {
        $rules = [
            'address' => 'nullable|string|max:500',
            'qualification' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'subjects' => 'nullable|array',
            'subjects.*' => 'exists:subjects,id',
        ];
    } elseif ($step === 3) {
        $rules = [
            'status' => 'required|in:active,inactive,pending',
        ];
        
        if ($this->change_password) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }
    }
    
    $this->validate($rules);
};

// Update teacher
$updateTeacher = function() {
    $validationRules = [
        'name' => 'required|string|max:255',
        'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->teacher->id)],
        'phone' => 'nullable|string|max:20',
        'address' => 'nullable|string|max:500',
        'qualification' => 'nullable|string|max:255',
        'bio' => 'nullable|string|max:1000',
        'subjects' => 'nullable|array',
        'subjects.*' => 'exists:subjects,id',
        'status' => 'required|in:active,inactive,pending',
    ];
    
    if ($this->change_password) {
        $validationRules['password'] = ['required', 'string', 'min:8', 'confirmed'];
    }
    
    if ($this->photo) {
        $validationRules['photo'] = 'image|max:1024';
    }
    
    $this->validate($validationRules);
    
    $this->formSubmitted = true;
    
    try {
        DB::beginTransaction();
        
        // Update user basic info
        $this->teacher->update([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
        ]);
        
        // Update password if requested
        if ($this->change_password && $this->password) {
            $this->teacher->update([
                'password' => Hash::make($this->password),
            ]);
        }
        
        // Update or create teacher profile with the correct field names
        $this->teacher->teacherProfile()->updateOrCreate(
            [], // Where conditions
            [
                'whatsapp' => $this->address,
        'specialization' => $this->qualification,
            ]
        );
        
        // Sync subjects
        $this->teacher->subjects()->sync($this->subjects);
        
        // Update photo if provided
        if ($this->photo) {
            // Delete old avatar if exists
            if ($this->teacher->avatar) {
                Storage::disk('public')->delete($this->teacher->avatar);
            }
            
            $this->teacher->update([
                'avatar' => $this->photo->store('avatars', 'public'),
            ]);
        }
        
        // Try to add to activity log if it exists
        try {
            activity()
                ->causedBy(auth()->user())
                ->performedOn($this->teacher)
                ->withProperties(['action' => 'update'])
                ->log('Updated teacher account');
        } catch (\Exception $e) {
            // Silently fail if activity log package isn't available
        }
        
        DB::commit();
        
        session()->flash('success', 'Teacher successfully updated!');
        $this->redirectRoute('admin.teachers.index');
        
    } catch (\Exception $e) {
        DB::rollBack();
        session()->flash('error', 'Failed to update teacher: ' . $e->getMessage());
    }
};

// Delete teacher
$deleteTeacher = function() {
    if (!$this->teacher) {
        return;
    }
    
    try {
        DB::beginTransaction();
        
        // Try to log deletion if activity log exists
        try {
            activity()
                ->causedBy(auth()->user())
                ->performedOn($this->teacher)
                ->withProperties(['action' => 'delete'])
                ->log('Deleted teacher account');
        } catch (\Exception $e) {
            // Silently fail if activity log package isn't available
        }
        
        // Delete teacher
        $this->teacher->delete();
        
        DB::commit();
        
        session()->flash('success', 'Teacher successfully deleted!');
        $this->redirectRoute('admin.teachers.index');
        
    } catch (\Exception $e) {
        DB::rollBack();
        session()->flash('error', 'Failed to delete teacher: ' . $e->getMessage());
    }
};

// Upload photo preview
$updatedPhoto = function() {
    try {
        $this->validate([
            'photo' => 'nullable|image|max:1024',
        ]);
        
        $this->currentPhoto = $this->photo->temporaryUrl();
    } catch (\Exception $e) {
        $this->photo = null;
        session()->flash('error', 'Invalid image upload: ' . $e->getMessage());
    }
};

?>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-900">Edit Teacher: {{ $teacher->name }}</h2>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.teachers.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path></svg>
                    Back to Teachers
                </a>
                <button type="button" onclick="confirm('Are you sure you want to delete this teacher?') || event.stopImmediatePropagation()" wire:click="deleteTeacher" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Delete Teacher
                </button>
            </div>
        </div>
        
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif
        
        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif
        
        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm font-medium text-gray-500">Step {{ $current_step }} of {{ $total_steps }}</div>
                <div class="text-sm font-medium text-gray-500">{{ floor(($current_step / $total_steps) * 100) }}% Complete</div>
            </div>
            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200">
                <div style="width:{{ ($current_step / $total_steps) * 100 }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500 transition-all duration-300"></div>
            </div>
            
            <div class="flex justify-between">
                <div class="text-xs font-medium {{ $current_step >= 1 ? 'text-indigo-600' : 'text-gray-500' }}">Basic Information</div>
                <div class="text-xs font-medium {{ $current_step >= 2 ? 'text-indigo-600' : 'text-gray-500' }}">Professional Details</div>
                <div class="text-xs font-medium {{ $current_step >= 3 ? 'text-indigo-600' : 'text-gray-500' }}">Account Settings</div>
            </div>
        </div>
        
        <!-- Form Content -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <form wire:submit.prevent="updateTeacher">
                
                    <!-- Step 1: Basic Information -->
                    <div x-data="{ step: @entangle('current_step') }" x-show="step === 1" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" id="name" wire:model="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter full name">
                                @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address <span class="text-red-500">*</span></label>
                                <input type="email" id="email" wire:model="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter email address">
                                @error('email') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="text" id="phone" wire:model="phone" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter phone number">
                                @error('phone') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="photo" class="block text-sm font-medium text-gray-700">Profile Photo</label>
                                <div class="mt-1 flex items-center space-x-6">
                                    <div class="flex-shrink-0">
                                        @if ($currentPhoto)
                                            <img class="h-24 w-24 rounded-full object-cover" src="{{ $currentPhoto }}" alt="New profile photo">
                                        @elseif ($existingPhoto)
                                            <img class="h-24 w-24 rounded-full object-cover" src="{{ asset('storage/'.$existingPhoto) }}" alt="Existing profile photo">
                                        @else
                                            <div class="h-24 w-24 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 016 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500">
                                            <span>Upload a new photo</span>
                                            <input id="file-upload" wire:model="photo" type="file" class="sr-only">
                                        </label>
                                        <p class="text-xs text-gray-500">PNG, JPG, GIF up to 1MB</p>
                                        @error('photo') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Professional Details -->
                    <div x-data="{ step: @entangle('current_step') }" x-show="step === 2" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="address" class="block text-sm font-medium text-gray-700">WhatsApp</label>
                                <textarea id="address" wire:model="address" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter WhatsApp number"></textarea>
                                @error('address') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label for="qualification" class="block text-sm font-medium text-gray-700">Specialization</label>
                                <input type="text" id="qualification" wire:model="qualification" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="e.g. Mathematics">
                                @error('qualification') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="bio" class="block text-sm font-medium text-gray-700">Bio</label>
                                <textarea id="bio" wire:model="bio" rows="4" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="A brief introduction about the teacher"></textarea>
                                @error('bio') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Subjects</label>
                                <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-2">
                                    @if(count($availableSubjects) > 0)
                                        @foreach($availableSubjects as $subject)
                                            <label class="inline-flex items-center">
                                                <input type="checkbox" wire:model="subjects" value="{{ $subject->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700">{{ $subject->name }}</span>
                                            </label>
                                        @endforeach
                                    @else
                                        <p class="text-sm text-gray-500">No subjects available.</p>
                                    @endif
                                </div>
                                @error('subjects') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Account Settings -->
                    <div x-data="{ step: @entangle('current_step') }" x-show="step === 3" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                                <select id="status" wire:model="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="pending">Pending</option>
                                </select>
                                @error('status') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="md:col-span-2">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="change_password" wire:model="change_password" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="change_password" class="font-medium text-gray-700">Change Password</label>
                                        <p class="text-gray-500">Check this box to change the teacher's password.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:col-span-2" x-data="{ showPassword: false }" x-show="$wire.change_password">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="password" class="block text-sm font-medium text-gray-700">New Password <span class="text-red-500">*</span></label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input :type="showPassword ? 'text' : 'password'" id="password" wire:model="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter new password">
                                            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                                <button type="button" @click="showPassword = !showPassword" class="text-gray-500 hover:text-gray-600 focus:outline-none">
                                                    <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                    <svg x-show="showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                        @error('password') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    
                                    <div>
                                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New Password <span class="text-red-500">*</span></label>
                                        <input type="password" id="password_confirmation" wire:model="password_confirmation" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Confirm new password">
                                    </div>
                                </div>
                                
                                <div class="mt-4 p-3 bg-yellow-50 rounded-md">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-yellow-800">Password Change Warning</h3>
                                            <div class="mt-1 text-sm text-yellow-700">
                                                <p>Changing the password will log the teacher out of all devices. Make sure to communicate this change to them.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="mt-8 flex justify-between items-center">
                        <div>
                            @if ($current_step > 1)
                                <button type="button" wire:click="prevStep" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                                    Previous
                                </button>
                            @endif
                        </div>
                        
                        <div>
                            @if ($current_step < $total_steps)
<button type="button" wire:click="nextStep" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Next
                                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </button>
                            @else
                                <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <span wire:loading.remove wire:target="updateTeacher">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Save Changes
                                    </span>
                                    <span wire:loading wire:target="updateTeacher">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Saving...
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Last Login and Activity Info -->
        <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Created On</h4>
                        <p class="mt-1 text-sm text-gray-900">{{ $teacher->created_at->format('F j, Y \a\t g:i a') }}</p>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Last Updated</h4>
                        <p class="mt-1 text-sm text-gray-900">{{ $teacher->updated_at->format('F j, Y \a\t g:i a') }}</p>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Last Login</h4>
                        <p class="mt-1 text-sm text-gray-900">
                            @if(isset($teacher->last_login_at))
                                {{ \Carbon\Carbon::parse($teacher->last_login_at)->format('F j, Y \a\t g:i a') }}
                            @else
                                Never logged in
                            @endif
                        </p>
                    </div>
                    
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">IP Address</h4>
                        <p class="mt-1 text-sm text-gray-900">
                            {{ $teacher->last_login_ip ?? 'N/A' }}
                        </p>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h4 class="text-sm font-medium text-gray-500">Recent Activity</h4>
                    <div class="mt-2 bg-gray-50 rounded-md p-4">
                        <ul class="space-y-3">
                            @php
                                try {
                                    $activities = \Spatie\Activitylog\Models\Activity::where('subject_type', get_class($teacher))
                                        ->where('subject_id', $teacher->id)
                                        ->latest()
                                        ->take(5)
                                        ->get();
                                } catch (\Exception $e) {
                                    $activities = collect(); // Empty collection if Spatie package is not installed
                                }
                            @endphp
                            
                            @forelse($activities as $activity)
                                <li class="text-sm">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-indigo-100">
                                                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <p class="text-gray-900">{{ $activity->description }}</p>
                                            <p class="text-gray-500">{{ $activity->created_at->diffForHumans() }} by {{ $activity->causer ? $activity->causer->name : 'System' }}</p>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="text-sm text-gray-500">No recent activity found.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Teacher Stats -->
        <div class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Teacher Statistics</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-100 rounded-md p-3">
                                <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Subjects</h4>
                                <p class="mt-1 text-xl font-semibold text-gray-900">{{ count($subjects) }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                                <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Classes</h4>
                                <p class="mt-1 text-xl font-semibold text-gray-900">
                                    @if(method_exists($teacher, 'classes'))
                                        {{ $teacher->classes()->count() ?? 0 }}
                                    @else
                                        0
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                                <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Students</h4>
                                <p class="mt-1 text-xl font-semibold text-gray-900">
                                    @if(method_exists($teacher, 'students'))
                                        {{ $teacher->students()->count() ?? 0 }}
                                    @else
                                        0
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                                <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-sm font-medium text-gray-500">Assignments</h4>
                                <p class="mt-1 text-xl font-semibold text-gray-900">
                                    @if(method_exists($teacher, 'assignedHomework'))
                                        {{ $teacher->assignedHomework()->count() ?? 0 }}
                                    @else
                                        0
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>