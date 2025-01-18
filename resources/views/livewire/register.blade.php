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

new class extends Component {
    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = '';
    public array $selectedSubjects = [];
    public int $currentStep = 1;

    public function roles(): array
    {
        return [
            ['id' => 'parent', 'name' => 'Parent'],
            ['id' => 'teacher', 'name' => 'Teacher'],
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
                'role' => ['required', 'in:parent,teacher'],
            ]);

          //  if ($this->role === 'teacher') {
          //      $this->validate([
          //          'selectedSubjects' => ['required', 'array', 'min:1'],
          //      ]);
          //  }
        }

        $this->currentStep++;
    }

    public function previousStep()
    {
        $this->currentStep--;
    }

    public function register()
    {
        $validationRules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:parent,teacher'],
        ];

        $validated = $this->validate($validationRules);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => $this->role,
            ]);

            if ($this->role === 'teacher') {
                $user->teacherProfile()->create([
                    'has_completed_profile' => false,
                    'status' => TeacherProfile::STATUS_SUBMITTED
                ]);
            }

            DB::commit();

            event(new Registered($user));

            // Remove the Auth::login($user) since we want them to log in manually
            // Auth::login($user);

            // Show success message
            $this->js("
                Toaster.success('Registration Successful!', {
                    description: 'Please log in to continue',
                    position: 'toast-bottom toast-end',
                    icon: 'o-check-circle',
                    css: 'alert-success',
                    timeout: 2000
                });
            ");

            // Redirect to login page for both roles
            return redirect()->route('login');

        } catch (\Exception $e) {
            DB::rollBack();

            $this->js("
                Toaster.error('Registration failed!', {
                    description: 'Something went wrong during registration. Please try again.',
                    position: 'toast-bottom toast-end',
                    icon: 'o-x-circle',
                    css: 'alert-error',
                    timeout: 3000
                });
            ");

            return;
        }
    }
    public function updatedRole($value)
    {
        if ($value === 'parent') {
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
                <!-- Step 3: Additional Information -->
                <div class="py-4 text-center">
                    <p class="text-gray-600">
                        @if($role === 'teacher')
                            Ready to complete your registration as a Teacher!
                        @else
                            Ready to complete your registration as a jio!
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

        <div class="mt-4 text-center">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">
                Already have an account?
            </a>
        </div>
    </div>
</div>



