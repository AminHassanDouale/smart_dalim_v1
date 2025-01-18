<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new class extends Component {
    public function logout()
    {
        Auth::logout();
        Session::flush();
        Session::regenerate();

        return redirect()->route('login');
    }

    public function with(): array
    {
        return [
            'user' => Auth::user(),
        ];
    }
}; ?>

<div class="grid items-center gap-10 mt-10 lg:grid-cols-2">
    <div>
        <img src="/images/support-us.png" width="300" class="mx-auto" />
    </div>
    <div>
        <!-- User session info -->
        @if($user)
        <div class="flex items-center justify-between mb-6">
            <span class="text-lg">Welcome, {{ $user->name }}</span>
            <x-button
                label="Logout"
                icon-right="o-arrow-right-on-rectangle"
                class="btn-warning"
                wire:click="logout"
            />
        </div>
        @endif

        <p class="mb-8 text-3xl font-bold leading-10">
            MaryUI components <span class="font-bold underline decoration-pink-500">are open source</span>
            <x-icon name="o-heart" class="w-10 h-10 text-pink-500" />
        </p>
        <p class="text-lg leading-7">
            Deep dive into the source code of this demo and
            <span class="p-1 font-bold bg-warning dark:text-white">get amazed</span>
            how much you can do with <span class="font-bold underline decoration-warning">minimal effort</span> learning by example.
        </p>
        <p class="mt-5 text-lg leading-7">
            Each demo contains <span class="font-bold underline decoration-warning">real world code</span> and straight approaches to get the most out of MaryUI and Livewire.
        </p>
        <p class="mt-5 text-lg leading-7">
            Support MaryUI buying this demo.
        </p>
        <div class="mt-8">
            <x-button
                label="Buy this demo"
                icon-right="o-arrow-right"
                link="https://mary-ui.lemonsqueezy.com/checkout/buy/e1ab8189-d5fa-4127-9f19-794c1f2c3468?discount=0"
                class="btn-primary"
                external
            />
        </div>
    </div>
</div>
