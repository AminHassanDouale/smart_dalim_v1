<?php
// resources/views/livewire/parents/profile-setup/steps.blade.php

use function Livewire\Volt\{state, computed};

state([
    'currentStep' => 1,
    'initialized' => false
]);

// Replace mount function with mountStep
$mountStep = function() {
    if (!$this->initialized) {
        $profile = auth()->user()->parentProfile;
        // If profile exists but children aren't added, go to step 2
        if ($profile?->has_completed_profile) {
            $this->currentStep = 2;
        }
        $this->initialized = true;
    }
};

$nextStep = function() {
    $this->currentStep++;
};

$previousStep = function() {
    $this->currentStep--;
};

// Listen for events from child components
$listeners = [
    'profile-completed' => 'nextStep',
    'children-completed' => 'handleChildrenCompleted',
    'next-step' => 'nextStep',
    'previous-step' => 'previousStep'
];

$handleChildrenCompleted = function() {
    return redirect()->route('parents.dashboard');
};

?>

<div wire:init="mountStep" class="max-w-4xl py-8 mx-auto">
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
            <li class="flex items-center">
                <span @class([
                    'flex items-center justify-center w-10 h-10 rounded-full shrink-0',
                    'bg-blue-600 text-white' => $currentStep >= 2,
                    'bg-gray-100' => $currentStep < 2
                ])>2</span>
            </li>
        </ol>
    </div>

    <!-- Step Content -->
    <div>
        @if($currentStep === 1)
            <livewire:parents.profile-setup.profile-form/>
        @else
            <livewire:parents.profile-setup.children-form/>
        @endif
    </div>
</div>
