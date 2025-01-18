<?php

use function Livewire\Volt\{state, computed, mount, uses,rules};
use Livewire\WithFileUploads;
use App\Models\ParentProfile;

// Add this line to use the trait
uses(WithFileUploads::class);

state([
    'phone_number' => '',
    'address' => '',
    'number_of_children' => 1,
    'additional_information' => '',
    'profile_photo' => null,
    'emergency_contacts' => [
        ['name' => '', 'relationship' => '', 'phone' => '']
    ]
]);

// Change $rules to rules() function
rules([
    'phone_number' => ['required', 'string', 'min:10'],
    'address' => ['required', 'string', 'max:500'],
    'number_of_children' => ['required', 'integer', 'min:1'],
    'additional_information' => ['nullable', 'string', 'max:1000'],
    'profile_photo' => ['nullable', 'image', 'max:1024'],
    'emergency_contacts' => ['required', 'array', 'min:1'],
    'emergency_contacts.*.name' => ['required', 'string'],
    'emergency_contacts.*.relationship' => ['required', 'string'],
    'emergency_contacts.*.phone' => ['required', 'string', 'min:10'],
]);

$addEmergencyContact = function () {
    $this->emergency_contacts[] = ['name' => '', 'relationship' => '', 'phone' => ''];
};

$removeEmergencyContact = function ($index) {
    if (count($this->emergency_contacts) > 1) {
        unset($this->emergency_contacts[$index]);
        $this->emergency_contacts = array_values($this->emergency_contacts);
    }
};

$saveProfile = function () {
    $this->validate();

    $user = auth()->user();
    $profile_photo_path = null;

    try {
        if ($this->profile_photo) {
            $profile_photo_path = $this->profile_photo->store('profile-photos', 'public');
        }

        ParentProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone_number' => $this->phone_number,
                'address' => $this->address,
                'number_of_children' => $this->number_of_children,
                'additional_information' => $this->additional_information,
                'profile_photo_path' => $profile_photo_path,
                'emergency_contacts' => $this->emergency_contacts,
                'has_completed_profile' => true,
            ]
        );

        // Instead of dispatching events, redirect directly
        return $this->redirect(route('parents.profile-setup.children'), navigate: true);
    } catch (\Exception $e) {
        $this->dispatch('notify', [
            'type' => 'error',
            'message' => 'Error saving profile: ' . $e->getMessage()
        ]);
    }
};

?>

<div class="max-w-2xl mx-auto">
    <h2 class="mb-8 text-2xl font-bold">Step 1: Complete Your Parent Profile</h2>

    <x-form wire:submit="saveProfile">
        <div class="space-y-6">
            <!-- Profile Photo -->
            <div>
                <x-input
                    type="file"
                    label="Profile Photo"
                    wire:model="profile_photo"
                    accept="image/*"
                    icon="o-camera"
                />
                @if ($profile_photo)
                    <div class="mt-2">
                        <img src="{{ $profile_photo->temporaryUrl() }}" class="object-cover w-20 h-20 rounded-full" />
                    </div>
                @endif
            </div>

            <!-- Phone Number -->
            <x-input
                label="Phone Number"
                wire:model="phone_number"
                icon="o-phone"
                required
            />

            <!-- Address -->
            <x-textarea
                label="Address"
                wire:model="address"
                rows="3"
                required
            />

            <!-- Number of Children -->
            <x-input
                type="number"
                label="Number of Children"
                wire:model="number_of_children"
                min="1"
                icon="o-users"
                required
            />

            <!-- Additional Information -->
            <x-textarea
                label="Additional Information"
                wire:model="additional_information"
                rows="3"
                placeholder="Any additional information you'd like to share..."
            />

            <!-- Emergency Contacts -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Emergency Contacts</h3>
                    <x-button
                        label="Add Contact"
                        icon="o-plus"
                        wire:click="addEmergencyContact"
                        class="btn-secondary btn-sm"
                    />
                </div>

                @foreach($emergency_contacts as $index => $contact)
                    <div class="p-4 space-y-3 rounded-lg bg-gray-50">
                        <div class="flex items-start justify-between">
                            <h4 class="text-sm font-medium">Contact #{{ $index + 1 }}</h4>
                            @if(count($emergency_contacts) > 1)
                                <x-button
                                    icon="o-x-mark"
                                    wire:click="removeEmergencyContact({{ $index }})"
                                    class="text-red-600 btn-ghost btn-sm"
                                />
                            @endif
                        </div>

                        <x-input
                            label="Name"
                            wire:model="emergency_contacts.{{ $index }}.name"
                            required
                        />

                        <x-input
                            label="Relationship"
                            wire:model="emergency_contacts.{{ $index }}.relationship"
                            required
                        />

                        <x-input
                            label="Phone"
                            wire:model="emergency_contacts.{{ $index }}.phone"
                            required
                        />
                    </div>
                @endforeach
            </div>

            <x-slot:actions>
                <x-button
                    label="Next Step"
                    type="submit"
                    icon="o-arrow-right"
                    class="btn-primary"
                    spinner="saveProfile"
                />
            </x-slot:actions>
        </div>
    </x-form>
</div>
