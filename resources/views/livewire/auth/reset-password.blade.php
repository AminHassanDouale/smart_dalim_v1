<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

new #[Layout('components.layouts.empty')] #[Title('Reset Password')] class extends Component {
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('status', __($status));
            $this->redirect(route('login'), navigate: true);
            return;
        }

        $this->addError('email', __($status));
    }
}; ?>

<div class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md px-6 py-4">
        <div class="text-center">
            <img src="/images/logo.png" class="w-auto h-24 mx-auto mb-8" />
            <h2 class="mb-4 text-2xl font-bold">Reset Password</h2>
        </div>

        @if (session('status'))
            <x-alert type="success" title="Success!" class="mb-4">
                {{ session('status') }}
            </x-alert>
        @endif

        <x-form wire:submit="resetPassword" class="space-y-4">
            <x-input
                wire:model="email"
                type="email"
                label="Email"
                placeholder="Enter your email"
                icon="o-envelope"
                inline
            />

            <x-input
                wire:model="password"
                type="password"
                label="New Password"
                placeholder="Enter your new password"
                icon="o-key"
                inline
            />

            <x-input
                wire:model="password_confirmation"
                type="password"
                label="Confirm Password"
                placeholder="Confirm your new password"
                icon="o-key"
                inline
            />

            <div class="flex items-center justify-between">
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
                >
                    <x-icon name="o-arrow-left" class="w-4 h-4" />
                    Back to Login
                </a>

                <x-button
                    type="submit"
                    icon="o-key"
                    label="Reset Password"
                    class="btn-primary"
                    spinner="resetPassword"
                />
            </div>
        </x-form>
    </div>
</div>
