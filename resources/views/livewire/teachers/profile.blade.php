<?php

use App\Models\TeacherProfile;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    use WithFileUploads;

    public User $user;
    public $photo;
    public string $name = '';
    public string $email = '';
    public string $whatsapp = '';
    public string $phone = '';
    public string $fix_number = '';
    public $date_of_birth;
    public string $place_of_birth = '';
    public array $selectedSubjects = [];

    // Password fields
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;

        if ($profile = $user->teacherProfile) {
            $this->whatsapp = $profile->whatsapp;
            $this->phone = $profile->phone;
            $this->fix_number = $profile->fix_number;
            $this->date_of_birth = $profile->date_of_birth?->format('Y-m-d');
            $this->place_of_birth = $profile->place_of_birth;
            $this->selectedSubjects = $profile->subjects->pluck('id')->toArray();
        }
    }

    public function updateProfile()
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $this->user->id],
            'whatsapp' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
            'fix_number' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date'],
            'place_of_birth' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:1024'],
            'selectedSubjects' => ['required', 'array', 'min:1'],
        ]);

        $this->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $profileData = collect($validated)
            ->except(['name', 'email', 'photo', 'selectedSubjects'])
            ->toArray();

        if ($this->photo) {
            $profileData['photo'] = $this->photo->store('teacher-photos', 'public');
        }

        $profile = $this->user->teacherProfile()->updateOrCreate(
            ['user_id' => $this->user->id],
            $profileData
        );

        $profile->subjects()->sync($this->selectedSubjects);

        $this->dispatch('profile-updated');
    }

    public function updatePassword()
    {
        $validated = $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $this->user->update([
            'password' => Hash::make($validated['password'])
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->dispatch('password-updated');
    }
}; ?>

<div class="space-y-10">
    <div class="p-4 bg-white rounded-lg shadow sm:p-6">
        <h2 class="text-lg font-medium">Profile Information</h2>

        <form wire:submit="updateProfile" class="mt-6 space-y-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <x-input label="Name" wire:model="name" required />
                <x-input type="email" label="Email" wire:model="email" required />
                <x-input label="WhatsApp" wire:model="whatsapp" required />
                <x-input label="Phone" wire:model="phone" required />
                <x-input label="Fix Number" wire:model="fix_number" />
                <x-input type="date" label="Date of Birth" wire:model="date_of_birth" required />
                <x-input label="Place of Birth" wire:model="place_of_birth" required />

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Subjects</label>
                    <x-select
                        wire:model="selectedSubjects"
                        :options="App\Models\Subject::all()"
                        option-label="name"
                        option-value="id"
                        multiple
                        required
                    />
                </div>

                <div class="col-span-2">
                    <x-input type="file" wire:model="photo" label="Profile Photo" accept="image/*" />
                    <div wire:loading wire:target="photo" class="mt-2 text-sm text-gray-500">
                        Uploading...
                    </div>
                </div>
            </div>

            <x-button type="submit" class="w-full sm:w-auto">
                Save Changes
            </x-button>
        </form>
    </div>

    <div class="p-4 bg-white rounded-lg shadow sm:p-6">
        <h2 class="text-lg font-medium">Update Password</h2>

        <form wire:submit="updatePassword" class="mt-6 space-y-6">
            <div class="space-y-4">
                <x-input type="password" label="Current Password" wire:model="current_password" required />
                <x-input type="password" label="New Password" wire:model="password" required />
                <x-input type="password" label="Confirm Password" wire:model="password_confirmation" required />
            </div>

            <x-button type="submit" class="w-full sm:w-auto">
                Update Password
            </x-button>
        </form>
    </div>
</div>
