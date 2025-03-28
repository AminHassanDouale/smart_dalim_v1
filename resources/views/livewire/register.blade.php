<?php

use App\Models\User;
use App\Models\Subject;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\DB;
use App\Models\TeacherProfile;
use App\Models\ParentProfile;
use App\Models\ClientProfile;

new class extends Component {
    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = '';
    public array $selectedSubjects = [];
    public int $currentStep = 1;

    // Added fields for client profile
    public string $company_name = '';
    public string $position = '';

    // Debug
    public string $debug_message = '';

    public function roles(): array
    {
        return [
            ['id' => 'parent', 'name' => 'Parent'],
            ['id' => 'teacher', 'name' => 'Teacher'],
            ['id' => 'client', 'name' => 'Client'], // Added client role
        ];
    }

    public function subjects(): array
    {
        return Subject::select('id', 'name')->get()->toArray();
    }

    public function nextStep()
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', 'unique:users'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            ]);
        }

        if ($this->currentStep === 2) {
            $this->validate([
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'role' => ['required', 'in:parent,teacher,client'],
            ]);
        }

        if ($this->currentStep === 3 && $this->role === 'teacher') {
            $this->validate([
                'selectedSubjects' => ['required', 'array', 'min:1'],
            ]);
        }

        if ($this->currentStep === 3 && $this->role === 'client') {
            $this->validate([
                'company_name' => ['required', 'string', 'max:255'],
                'position' => ['required', 'string', 'max:100'],
            ]);
        }

        $this->currentStep++;
    }

    public function previousStep()
    {
        $this->currentStep--;
    }

    public function register()
    {
        try {
            $validationRules = [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', 'unique:users'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'role' => ['required', 'in:parent,teacher,client'],
            ];

            // Add role-specific validation
            if ($this->role === 'teacher') {
                $validationRules['selectedSubjects'] = ['required', 'array', 'min:1'];
            }

            if ($this->role === 'client') {
                $validationRules['company_name'] = ['required', 'string', 'max:255'];
                $validationRules['position'] = ['required', 'string', 'max:100'];
            }

            $validated = $this->validate($validationRules);

            // Start with a simpler approach - don't use DB transaction initially
            $user = User::create([
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => $this->role,
            ]);

            $this->debug_message = "User created with ID: {$user->id}";

            // Create role-specific profiles
            if ($this->role === 'teacher') {
                $teacherProfile = new TeacherProfile([
                    'user_id' => $user->id,
                    'has_completed_profile' => false,
                    'status' => TeacherProfile::STATUS_SUBMITTED
                ]);
                $user->teacherProfile()->save($teacherProfile);

                // Attach subjects if selected
                if (!empty($this->selectedSubjects)) {
                    $user->subjects()->attach($this->selectedSubjects);
                }

                $this->debug_message .= " | Teacher profile created";
            }
            elseif ($this->role === 'parent') {
                $parentProfile = new ParentProfile([
                    'user_id' => $user->id,
                    'has_completed_profile' => false,
                ]);
                $user->parentProfile()->save($parentProfile);

                $this->debug_message .= " | Parent profile created";
            }
            elseif ($this->role === 'client') {
                $clientProfile = new ClientProfile([
                    'user_id' => $user->id,
                    'has_completed_profile' => false,
                    'status' => ClientProfile::STATUS_PENDING,
                    'company_name' => $this->company_name,
                    'position' => $this->position,
                ]);
                $user->clientProfile()->save($clientProfile);

                $this->debug_message .= " | Client profile created";
            }

            event(new Registered($user));

            // Login the user automatically
            Auth::login($user);

            $this->debug_message .= " | User logged in";

            $this->js("
                Toaster.success('Registration Successful!', {
                    description: 'Redirecting to profile setup...',
                    position: 'toast-bottom toast-end',
                    icon: 'o-check-circle',
                    css: 'alert-success',
                    timeout: 2000
                });
            ");

            // Use route helper to get full URL instead of just name
            $redirectRoute = '';

            if ($this->role === 'parent') {
                $redirectRoute = route('profile-setup');
                $this->debug_message .= " | Should redirect to parent profile setup";
            }
            elseif ($this->role === 'teacher') {
                $redirectRoute = route('teachers.profile-setup');
                $this->debug_message .= " | Should redirect to teacher profile setup";
            }
            elseif ($this->role === 'client') {
                $redirectRoute = route('clients.profile-setup');
                $this->debug_message .= " | Should redirect to client profile setup";
            }

            // Use JavaScript redirect as fallback
            $this->js("
                setTimeout(function() {
                    window.location.href = '{$redirectRoute}';
                }, 2000);
            ");

            // Return PHP redirect as primary method
            if (!empty($redirectRoute)) {
                return redirect($redirectRoute);
            }

        } catch (\Exception $e) {
            $this->debug_message = "Error: " . $e->getMessage();

            $this->js("
                Toaster.error('Registration failed: {$e->getMessage()}', {
                    position: 'toast-bottom toast-end',
                    icon: 'o-x-circle',
                    css: 'alert-error',
                    timeout: 3000
                });
            ");
        }
    }

    public function updatedRole($value)
    {
        if ($value === 'parent' || $value === 'client') {
            $this->selectedSubjects = [];
        }
    }
}; ?>

<div class="flex">
    <div class="mx-auto w-96">
        <img src="/images/login.png" width="96" class="mx-auto mb-8" />

        <!-- Progress Steps -->
        <div class="mb-8">
            <ol class="flex items-center w-full">
                <li class="flex items-center text-blue-600 after:content-[''] after:w-full after:h-1 after:border-b after:border-blue-100 after:border-4 after:inline-block">
                    <span @class([
                        'flex items-center justify-center w-10 h-10 rounded-full shrink-0',
                        'bg-blue-600 text-white' => $currentStep >= 1,
                        'bg-gray-100' => $currentStep < 1
                    ])>1</span>
                </li>
                <li class="flex items-center text-blue-600 after:content-[''] after:w-full after:h-1 after:border-b after:border-blue-100 after:border-4 after:inline-block">
                    <span @class([
                        'flex items-center justify-center w-10 h-10 rounded-full shrink-0',
                        'bg-blue-600 text-white' => $currentStep >= 2,
                        'bg-gray-100' => $currentStep < 2
                    ])>2</span>
                </li>
                <li class="flex items-center">
                    <span @class([
                        'flex items-center justify-center w-10 h-10 rounded-full shrink-0',
                        'bg-blue-600 text-white' => $currentStep >= 3,
                        'bg-gray-100' => $currentStep < 3
                    ])>3</span>
                </li>
            </ol>
        </div>

        <x-form wire:submit="register">
            @if ($currentStep === 1)
            <div>
                <!-- Step 1: Basic Information -->
                <x-input
                    label="Name"
                    wire:model="name"
                    icon="o-user"
                    inline
                    required
                />

                <x-input
                    label="Username"
                    wire:model="username"
                    icon="o-at-symbol"
                    inline
                    required
                />

                <x-input
                    label="E-mail"
                    wire:model="email"
                    icon="o-envelope"
                    inline
                    required
                />

                <x-slot:actions>
                    <div class="flex justify-end w-full">
                        <x-button
                            label="Next"
                            icon="o-arrow-right"
                            wire:click="nextStep"
                            class="btn-primary"
                        />
                    </div>
                </x-slot:actions>
            </div>
            @elseif ($currentStep === 2)
            <div>
                <!-- Step 2: Role and Password -->
                <x-select
                    label="Role"
                    wire:model.live="role"
                    icon="o-users"
                    inline
                    required
                    placeholder="Select a role"
                    :options="$this->roles()"
                    option-label="name"
                    option-value="id"
                />

                <x-input
                    label="Password"
                    wire:model="password"
                    type="password"
                    icon="o-key"
                    inline
                    required
                />

                <x-input
                    label="Confirm Password"
                    wire:model="password_confirmation"
                    type="password"
                    icon="o-key"
                    inline
                    required
                />

                <x-slot:actions>
                    <div class="flex justify-between w-full">
                        <x-button
                            label="Previous"
                            icon="o-arrow-left"
                            wire:click="previousStep"
                            class="btn-secondary"
                        />
                        <x-button
                            label="Next"
                            icon="o-arrow-right"
                            wire:click="nextStep"
                            class="btn-primary"
                        />
                    </div>
                </x-slot:actions>
            </div>
            @else
            <div>
                <!-- Step 3: Role-specific Information -->
                @if($role === 'teacher')
                    <div class="mb-4">
                        <label class="block mb-1 text-sm font-medium text-gray-700">
                            Select Subjects You Teach
                        </label>
                        <x-choices
                            wire:model="selectedSubjects"
                            :options="$this->subjects()"
                            option-label="name"
                            option-value="id"
                            :searchable="true"
                            multiple
                        />
                        @error('selectedSubjects') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    </div>
                @elseif($role === 'client')
                    <x-input
                        label="Company Name"
                        wire:model="company_name"
                        icon="o-building-office"
                        inline
                        required
                    />
                    <x-input
                        label="Position/Title"
                        wire:model="position"
                        icon="o-briefcase"
                        inline
                        required
                    />
                @endif

                <div class="py-4 text-center">
                    <p class="text-gray-600">
                        @if($role === 'teacher')
                            Ready to complete your registration as a Teacher!
                        @elseif($role === 'client')
                            Ready to complete your registration as a Client!
                        @else
                            Ready to complete your registration as a Parent!
                        @endif
                    </p>
                </div>

                <x-slot:actions>
                    <div class="flex justify-between w-full">
                        <x-button
                            label="Previous"
                            icon="o-arrow-left"
                            wire:click="previousStep"
                            class="btn-secondary"
                        />
                        <x-button
                            label="Register"
                            type="submit"
                            icon="o-user-plus"
                            class="btn-primary"
                            spinner="register"
                        />
                    </div>
                </x-slot:actions>
            </div>
            @endif
        </x-form>

        @if(!empty($debug_message))
        <div class="p-3 mt-4 text-sm bg-yellow-100 border border-yellow-300 rounded">
            <p class="font-medium">Debug Info:</p>
            <p>{{ $debug_message }}</p>
        </div>
        @endif

        <div class="mt-4 text-center">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">
                Already have an account?
            </a>
        </div>
    </div>
</div>
