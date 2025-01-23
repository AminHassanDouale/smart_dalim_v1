<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.empty')]
#[Title('Login')]
class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $this->remember)) {
            session()->regenerate();

            // Get the authenticated user
            $user = Auth::user();

            // Redirect based on role
            if ($user->role === 'parent') {
                // Check if parent profile is completed
                if ($user->parentProfile && $user->parentProfile->has_completed_profile) {
                    $this->redirect('/parents/profile-setup', navigate: true);
                } else {
                    $this->redirect('/parents/dashboard', navigate: true);
                }
                return;
            } elseif ($user->role === 'teacher') {
                $this->redirect('/teachers/dashboard', navigate: true);
                return;
            } else {
                // Default redirect for other roles or if no role is set
                $this->redirect('/home', navigate: true);
                return;
            }
        }

        $this->addError('email', __('auth.failed'));
        $this->password = '';
    }
}; ?>

<div class="flex items-center justify-center min-h-screen">
    <div class="p-6 mx-auto w-96">
        <div class="text-center">
            <img src="/images/login.png" width="96" class="mx-auto mb-8" />
            <h2 class="mb-4 text-2xl font-bold">Welcome Back!</h2>
        </div>

        @if (session('status'))
            <x-alert type="success" title="Success!" class="mb-4">
                {{ session('status') }}
            </x-alert>
        @endif

        <x-form wire:submit="login">
            <x-input
                label="E-mail"
                name="email"
                wire:model="email"
                icon="o-envelope"
                type="email"
                autocomplete="email"
                inline
            />

            <x-input
                label="Password"
                name="password"
                wire:model="password"
                type="password"
                icon="o-key"
                autocomplete="current-password"
                inline
            />

            <div class="flex items-center justify-between mb-4">
                <x-checkbox
                    id="remember"
                    wire:model="remember"
                    label="Remember me"
                />

                <a
                    href="{{ route('password.request') }}"
                    class="text-sm text-primary hover:underline"
                >
                    Forgot Password?
                </a>
            </div>

            <x-slot:actions>
                <x-button
                    label="Login"
                    type="submit"
                    icon="o-lock-closed"
                    class="w-full btn-primary"
                    spinner="login"
                />
            </x-slot:actions>
        </x-form>

        <div class="mt-4 text-sm text-center text-gray-600">
            <span>Dont have an account?</span>
            <a
                href="{{ route('register') }}"
                class="ml-1 text-primary hover:underline"
            >
                Register
            </a>
        </div>

        <div class="mt-6 text-center">
            <a
                href="/"
                class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
            >
                <x-icon name="o-home" class="w-4 h-4" />
                Go Home
            </a>
        </div>
    </div>
</div>
