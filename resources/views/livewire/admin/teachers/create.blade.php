<?php

use function Livewire\Volt\{state, computed, mount, boot};
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

// Define state variables
state([
    'name' => '',
    'email' => '',
    'phone' => '',
    'employee_id' => '',
    'address' => '',
    'qualification' => '',
    'experience' => '',
    'bio' => '',
    'subjects' => [],
    'status' => 'active',
    'send_invitation' => true,
    'generate_password' => true,
    'password' => '',
    'password_confirmation' => '',
    'photo' => null,
    'current_step' => 1,
    'total_steps' => 3,
    'availableSubjects' => [],
    'currentPhoto' => null,
    'formSubmitted' => false,
    'generatedPassword' => '',
]);

// Boot the component
boot(function() {
    // Load available subjects
    $this->loadSubjects();
    
    // Generate a unique employee ID
    $this->employee_id = 'TCHR-' . strtoupper(Str::random(6));
    
    // Generate a secure password
    $this->generatePassword();
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

// Generate a secure password
$generatePassword = function() {
    $this->generatedPassword = Str::password(12, true, true, true, false);
    $this->password = $this->generatedPassword;
    $this->password_confirmation = $this->generatedPassword;
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
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'employee_id' => 'required|string|max:20|unique:users',
        ];
    } elseif ($step === 2) {
        $rules = [
            'address' => 'nullable|string|max:500',
            'qualification' => 'nullable|string|max:255',
            'experience' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'subjects' => 'nullable|array',
            'subjects.*' => 'exists:subjects,id',
        ];
    } elseif ($step === 3) {
        $passwordRules = $this->generate_password ? [] : ['required', 'string', 'min:8', 'confirmed'];
        $rules = [
            'password' => $passwordRules,
            'status' => 'required|in:active,inactive,pending',
        ];
    }
    
    $this->validate($rules);
};

// Create teacher
$createTeacher = function() {
    $this->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'phone' => 'nullable|string|max:20',
        'employee_id' => 'required|string|max:20|unique:users',
        'address' => 'nullable|string|max:500',
        'qualification' => 'nullable|string|max:255',
        'experience' => 'nullable|string|max:255',
        'bio' => 'nullable|string|max:1000',
        'subjects' => 'nullable|array',
        'subjects.*' => 'exists:subjects,id',
        'status' => 'required|in:active,inactive,pending',
    ]);
    
    if (!$this->generate_password) {
        $this->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }
    
    $this->formSubmitted = true;
    
    try {
        DB::beginTransaction();
        
        // Create the user
        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'employee_id' => $this->employee_id,
            'password' => Hash::make($this->password),
            'role' => User::ROLE_TEACHER,
            'status' => $this->status,
        ]);
        
        // Create teacher profile
        $user->teacherProfile()->create([
            'address' => $this->address,
            'qualification' => $this->qualification,
            'experience' => $this->experience,
            'bio' => $this->bio,
        ]);
        
        // Attach subjects
        if (!empty($this->subjects)) {
            $user->subjects()->attach($this->subjects);
        }
        
        // Save photo if uploaded
        if ($this->photo) {
            $user->update([
                'avatar' => $this->photo->store('avatars', 'public'),
            ]);
        }
        
        // Send invitation email
        if ($this->send_invitation) {
            // Here you would trigger an invitation email with the generated credentials
            // Mail::to($user->email)->send(new TeacherInvitation($user, $this->password));
        }
        
        // Add to activity log
        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->withProperties(['action' => 'create'])
            ->log('Created new teacher account');
        
        DB::commit();
        
        session()->flash('success', 'Teacher successfully created!');
        return redirect()->route('admin.teachers.index');
        
    } catch (\Exception $e) {
        DB::rollBack();
        session()->flash('error', 'Failed to create teacher: ' . $e->getMessage());
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

// Generate employee ID
$generateEmployeeId = function() {
    $this->employee_id = 'TCHR-' . strtoupper(Str::random(6));
};

// Updated generate password option
$updatedGeneratePassword = function() {
    if ($this->generate_password) {
        $this->generatePassword();
    } else {
        $this->password = '';
        $this->password_confirmation = '';
    }
};

// Check if form is valid
$isFormValid = computed(function() {
    // Validate all steps
    $isValid = true;
    
    // Basic validation check for required fields
    if (empty($this->name) || empty($this->email) || empty($this->employee_id)) {
        $isValid = false;
    }
    
    // Password validation
    if (!$this->generate_password && (empty($this->password) || $this->password !== $this->password_confirmation)) {
        $isValid = false;
    }
    
    return $isValid;
});

?>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-900">Add New Teacher</h2>
            <a href="{{ route('admin.teachers.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path></svg>
                Back to Teachers
            </a>
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
                <div class="text-xs font-medium {{ $current_step >= 3 ? 'text-indigo-600' : 'text-gray-500' }}">Account Setup</div>
            </div>
        </div>
        
        <!-- Form Content -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <form wire:submit.prevent="createTeacher">
                
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
                            
                            <div>
                                <label for="employee_id" class="block text-sm font-medium text-gray-700">Employee ID <span class="text-red-500">*</span></label>
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <input type="text" id="employee_id" wire:model="employee_id" class="flex-grow block w-full border-gray-300 rounded-l-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter employee ID">
                                    <button type="button" wire:click="generateEmployeeId" class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm hover:bg-gray-100 focus:outline-none focus:ring-indigo-500">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    </button>
                                </div>
                                @error('employee_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="photo" class="block text-sm font-medium text-gray-700">Profile Photo</label>
                                <div class="mt-1 flex items-center space-x-6">
                                    <div class="flex-shrink-0">
                                        @if ($currentPhoto)
                                            <img class="h-24 w-24 rounded-full object-cover" src="{{ $currentPhoto }}" alt="Profile photo preview">
                                        @else
                                            <div class="h-24 w-24 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500">
                                            <span>Upload a file</span>
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
                                <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                                <textarea id="address" wire:model="address" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter full address"></textarea>
                                @error('address') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label for="qualification" class="block text-sm font-medium text-gray-700">Qualifications</label>
                                <input type="text" id="qualification" wire:model="qualification" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="e.g. MSc in Mathematics">
                                @error('qualification') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label for="experience" class="block text-sm font-medium text-gray-700">Experience</label>
                                <input type="text" id="experience" wire:model="experience" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="e.g. 5 years of teaching">
                                @error('experience') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
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
                                        <p class="text-sm text-gray-500">No subjects available. You can assign subjects later.</p>
                                    @endif
                                </div>
                                @error('subjects') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Account Setup -->
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
                                        <input id="send_invitation" wire:model="send_invitation" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="send_invitation" class="font-medium text-gray-700">Send Email Invitation</label>
                                        <p class="text-gray-500">Send an email with login credentials to the teacher.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:col-span-2">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="generate_password" wire:model="generate_password" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="generate_password" class="font-medium text-gray-700">Auto-generate Password</label>
                                        <p class="text-gray-500">Automatically generate a secure password.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:col-span-2" x-data="{ showPassword: false }">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="password" class="block text-sm font-medium text-gray-700">Password {{ $generate_password ? '' : '*' }}</label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input :type="showPassword ? 'text' : 'password'" id="password" wire:model="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter password" {{ $generate_password ? 'readonly' : '' }}>
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
                                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password {{ $generate_password ? '' : '*' }}</label>
                                        <input type="password" id="password_confirmation" wire:model="password_confirmation" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Confirm password" {{ $generate_password ? 'readonly' : '' }}>
                                    </div>
                                </div>
                                
                                @if($generate_password && $generatedPassword)
                                <div class="mt-4 p-3 bg-gray-50 rounded-md">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-gray-700">Generated Password</h3>
                                            <div class="mt-1 text-sm text-gray-500">
                                                <code class="bg-gray-100 px-2 py-1 rounded">{{ $generatedPassword }}</code>
                                                <button type="button" onclick="navigator.clipboard.writeText('{{ $generatedPassword }}')" class="ml-2 text-indigo-600 hover:text-indigo-500 text-xs">
                                                    Copy
                                                </button>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">Make sure to save this password. It will be sent to the teacher if email invitation is enabled.</p>
                                        </div>
                                    </div>
                                </div>
                                @endif
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
                                    <span wire:loading.remove wire:target="createTeacher">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Create Teacher
                                    </span>
                                    <span wire:loading wire:target="createTeacher">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing...
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Teacher Preview Card -->
        <div x-data="{ show: @entangle('current_step') === 3 }" x-show="show" class="mt-8 bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Teacher Preview</h3>
                
                <div class="flex flex-col md:flex-row">
                    <!-- Teacher Avatar -->
                    <div class="flex-shrink-0 mb-4 md:mb-0 md:mr-6">
                        @if ($currentPhoto)
                            <img class="h-32 w-32 rounded-full object-cover" src="{{ $currentPhoto }}" alt="Profile photo preview">
                        @else
                            <div class="h-32 w-32 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Teacher Details -->
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Name</h4>
                            <p class="mt-1 text-sm text-gray-900">{{ $name ?: 'Not provided' }}</p>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Email</h4>
                            <p class="mt-1 text-sm text-gray-900">{{ $email ?: 'Not provided' }}</p>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Phone</h4>
                            <p class="mt-1 text-sm text-gray-900">{{ $phone ?: 'Not provided' }}</p>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Employee ID</h4>
                            <p class="mt-1 text-sm text-gray-900">{{ $employee_id ?: 'Not provided' }}</p>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Qualification</h4>
                            <p class="mt-1 text-sm text-gray-900">{{ $qualification ?: 'Not provided' }}</p>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Experience</h4>
                            <p class="mt-1 text-sm text-gray-900">{{ $experience ?: 'Not provided' }}</p>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Status</h4>
                            <p class="mt-1">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($status === 'active')
                                        bg-green-100 text-green-800
                                    @elseif($status === 'inactive')
                                        bg-red-100 text-red-800
                                    @else
                                        bg-yellow-100 text-yellow-800
                                    @endif
                                ">
                                    {{ ucfirst($status) }}
                                </span>
                            </p>
                        </div>
                        
                        <div class="md:col-span-2">
                            <h4 class="text-sm font-medium text-gray-500">Address</h4>
                            <p class="mt-1 text-sm text-gray-900">{{ $address ?: 'Not provided' }}</p>
                        </div>
                        
                        @if (!empty($subjects))
                        <div class="md:col-span-2">
                            <h4 class="text-sm font-medium text-gray-500">Subjects</h4>
                            <div class="mt-1 flex flex-wrap gap-2">
                                @foreach($subjects as $subjectId)
                                    @php
                                        $subject = $availableSubjects->firstWhere('id', $subjectId);
                                    @endphp
                                    @if($subject)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $subject->name }}
                                    </span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        @endif
                        
                        <div class="md:col-span-2">
                            <h4 class="text-sm font-medium text-gray-500">Bio</h4>
                            <p class="mt-1 text-sm text-gray-900">{{ $bio ?: 'Not provided' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>