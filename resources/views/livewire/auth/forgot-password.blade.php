<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Password;

new #[Layout('components.layouts.empty')] #[Title('Forgot Password')] class extends Component {
    public string $email = '';

    public function sendResetLink(): void
    {
        $this->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('status', __($status));
            $this->email = '';
        } else {
            $this->addError('email', __($status));
        }
    }
}; ?>

<div class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md px-6 py-4">
        <div class="text-center">
            <img src="/images/logo.png" class="w-auto h-24 mx-auto mb-8" />
            <h2 class="mb-4 text-2xl font-bold">Forgot Password</h2>
        </div>

        @if (session('status'))
            <x-alert type="success" title="Success!" class="mb-4">
                {{ session('status') }}
            </x-alert>
        @endif

        <x-form wire:submit="sendResetLink" class="space-y-4">
            <x-input
                wire:model="email"
                type="email"
                label="Email"
                placeholder="Enter your email"
                icon="o-envelope"
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
                    icon="o-paper-airplane"
                    label="Send Reset Link"
                    class="btn-primary"
                    spinner="sendResetLink"
                />
            </div>
        </x-form>
    </div>
</div>
