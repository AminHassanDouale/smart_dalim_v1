<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\TeacherProfile;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public User $teacher;
    public TeacherProfile $profile;

    public $name = '';
    public $email = '';
    public $whatsapp = '';
    public $phone = '';
    public $fix_number = '';
    public $date_of_birth = '';
    public $place_of_birth = '';

    public function mount($user)
    {
        $this->teacher = User::findOrFail($user);
        $this->profile = $this->teacher->teacherProfile;

        $this->name = $this->teacher->name;
        $this->email = $this->teacher->email;
        $this->whatsapp = $this->profile->whatsapp ?? '';
        $this->phone = $this->profile->phone ?? '';
        $this->fix_number = $this->profile->fix_number ?? '';
        $this->date_of_birth = $this->profile->date_of_birth ? $this->profile->date_of_birth->format('Y-m-d') : '';
        $this->place_of_birth = $this->profile->place_of_birth ?? '';
    }

    protected function toast(
        string $type,
        string $title,
        $description = '',
        string $position = 'toast-bottom toast-end',
        string $icon = '',
        string $css = '',
        $timeout = 3000,
        $action = null,
        $redirectTo = null
    ) {
        $actionJson = $action ? json_encode($action) : 'null';
        $redirectPath = $redirectTo ? $redirectTo : '';

        $this->js("
            Toaster.{$type}('{$title}', {
                description: '{$description}',
                position: '{$position}',
                icon: '{$icon}',
                css: '{$css}',
                timeout: {$timeout},
                action: {$actionJson},
                redirectTo: '{$redirectPath}'
            });
        ");
    }
    public function deleteFileWithConfirm($fileId)
    {
        $this->toast(
            type: 'warning',
            title: 'Delete File?',
            description: 'Are you sure?',
            position: 'toast-bottom toast-end',
            icon: 'o-exclamation-triangle',
            css: 'alert-warning',
            timeout: false,
            action: [
                'label' => 'Delete',
                'onClick' => fn() => $this->deleteFile($fileId)
            ]
        );
    }

    public function deleteFile($fileId)
    {
        $file = File::find($fileId);
        if ($file && $file->model_id === $this->profile->id) {
            Storage::disk('public')->delete($file->path);
            $file->delete();
            $this->toast(
                type: 'success',
                title: 'File deleted',
                icon: 'o-trash',
                css: 'alert-success'
            );
        }
    }
    public function updateTeacher()
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $this->teacher->id],
            'whatsapp' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
            'fix_number' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date'],
            'place_of_birth' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->teacher->update([
                'name' => $this->name,
                'email' => $this->email,
            ]);

            $this->profile->update([
                'whatsapp' => $this->whatsapp,
                'phone' => $this->phone,
                'fix_number' => $this->fix_number,
                'date_of_birth' => $this->date_of_birth,
                'place_of_birth' => $this->place_of_birth,
            ]);

            $this->toast(
                type: 'success',
                title: 'Teacher updated successfully!',
                position: 'toast-bottom toast-end',
                icon: 'o-check',
                css: 'alert-success',
                timeout: 3000
            );

            return $this->redirect(url()->previous(), navigate: true);
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Update failed!',
                description: 'Something went wrong while updating the teacher.',
                position: 'toast-bottom toast-end',
                icon: 'o-x-circle',
                css: 'alert-error',
                timeout: 3000
            );
        }
    }
};?>

<div class="p-6">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-medium">Edit Teacher: {{ $teacher->name }}</h2>
            <x-button
                label="Back to List"
                icon="o-arrow-left"
                link="/teachers/dashboard"
                class="btn-ghost"
                navigate
            />
        </div>

        <x-form wire:submit="updateTeacher" class="space-y-6">
            <!-- Basic Information -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-input
                    label="Name"
                    wire:model="name"
                    icon="o-user"
                    inline
                    required
                />

                <x-input
                    label="Email"
                    wire:model="email"
                    icon="o-envelope"
                    type="email"
                    inline
                    required
                />
            </div>

            <!-- Contact Information -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-input
                    label="WhatsApp Number"
                    wire:model="whatsapp"
                    icon="o-phone"
                    inline
                    required
                />

                <x-input
                    label="Phone Number"
                    wire:model="phone"
                    icon="o-device-phone-mobile"
                    inline
                    required
                />

                <x-input
                    label="Fix Number"
                    wire:model="fix_number"
                    icon="o-phone"
                    inline
                />
            </div>

            <!-- Birth Information -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-input
                    label="Date of Birth"
                    wire:model="date_of_birth"
                    type="date"
                    icon="o-calendar"
                    inline
                    required
                />

                <x-input
                    label="Place of Birth"
                    wire:model="place_of_birth"
                    icon="o-map-pin"
                    inline
                    required
                />
            </div>

            <x-slot:actions>
                <x-button
                    label="Update Teacher"
                    type="submit"
                    icon="o-check"
                    class="w-full btn-primary"
                    spinner="updateTeacher"
                />
            </x-slot:actions>
        </x-form>
        <!-- Documents Section -->
<div class="space-y-4">
    <h3 class="text-lg font-medium">Documents</h3>
    @if($this->profile->files()->where('collection', 'documents')->count() > 0)
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->profile->files()->where('collection', 'documents')->get() as $file)
                        <tr>
                            <td>{{ $file->original_name }}</td>
                            <td>{{ number_format($file->size / 1024, 2) }} KB</td>
                            <td class="flex gap-2">
                                <a href="{{ Storage::disk('public')->url($file->path) }}"
                                   target="_blank"
                                   class="btn btn-sm btn-primary">
                                    <x-icon name="o-eye" class="w-4 h-4" />
                                </a>
                                <button wire:click="deleteFileWithConfirm({{ $file->id }})"
                                        class="btn btn-sm btn-error">
                                    <x-icon name="o-trash" class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-gray-500">No documents uploaded</p>
    @endif
</div>
    </div>
</div>
