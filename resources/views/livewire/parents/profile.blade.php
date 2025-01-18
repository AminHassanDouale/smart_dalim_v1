<?php

use App\Models\ParentProfile;
use Livewire\Volt\Component;

new class extends Component {
    public string $phone_number = '';
    public string $address = '';
    public int $number_of_children = 1;
    public ?string $additional_information = '';

    public function saveProfile()
    {
        $validated = $this->validate([
            'phone_number' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'number_of_children' => ['required', 'integer', 'min:1'],
            'additional_information' => ['nullable', 'string', 'max:1000'],
        ]);

        $profile = ParentProfile::create([
            'user_id' => auth()->id(),
            ...$validated
        ]);

        return $this->redirect('/parents/dashboard', navigate: true);
    }
}; ?>

<div class="max-w-2xl p-4 mx-auto">
    <h2 class="mb-6 text-2xl font-bold">Complete Your Parent Profile</h2>

    <x-form wire:submit="saveProfile">
        <x-input
            label="Phone Number"
            wire:model="phone_number"
            required
        />

        <x-input
            label="Address"
            wire:model="address"
            required
        />

        <x-input
            type="number"
            label="Number of Children"
            wire:model="number_of_children"
            min="1"
            required
        />

        <x-textarea
            label="Additional Information"
            wire:model="additional_information"
        />

        <x-button
            type="submit"
            label="Complete Profile"
            class="btn-primary"
            spinner="saveProfile"
        />
    </x-form>
</div>
