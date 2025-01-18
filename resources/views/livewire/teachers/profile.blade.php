<?php

use App\Models\TeacherProfile;
use Livewire\Volt\Component;

new class extends Component {
    public string $phone_number = '';
    public string $qualification = '';
    public string $subject_specialty = '';
    public int $years_of_experience = 0;
    public ?string $bio = '';

    public function saveProfile()
    {
        $validated = $this->validate([
            'phone_number' => ['required', 'string', 'max:20'],
            'qualification' => ['required', 'string', 'max:255'],
            'subject_specialty' => ['required', 'string', 'max:255'],
            'years_of_experience' => ['required', 'integer', 'min:0'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);

        $profile = TeacherProfile::create([
            'user_id' => auth()->id(),
            ...$validated
        ]);

        return $this->redirect('/teachers/dashboard', navigate: true);
    }
}; ?>

<div class="max-w-2xl p-4 mx-auto">
    <h2 class="mb-6 text-2xl font-bold">Complete Your Teacher Profile</h2>

    <x-form wire:submit="saveProfile">
        <x-input
            label="Phone Number"
            wire:model="phone_number"
            required
        />

        <x-input
            label="Qualification"
            wire:model="qualification"
            required
        />

        <x-input
            label="Subject Specialty"
            wire:model="subject_specialty"
            required
        />

        <x-input
            type="number"
            label="Years of Experience"
            wire:model="years_of_experience"
            min="0"
            required
        />

        <x-textarea
            label="Bio"
            wire:model="bio"
            placeholder="Tell us about yourself and your teaching experience..."
        />

        <x-button
            type="submit"
            label="Complete Profile"
            class="btn-primary"
            spinner="saveProfile"
        />
    </x-form>
</div>
