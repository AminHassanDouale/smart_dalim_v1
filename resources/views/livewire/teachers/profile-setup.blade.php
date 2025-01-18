<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Carbon\Carbon;
use App\Models\File;
use App\Models\TeacherProfile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;

new class extends Component {
    use WithFileUploads;

    public string $whatsapp = '';
    public string $phone = '';
    public string $fix_number = '';
    public $photo;
    public $files = [];
    public string $status = '';

    public string $date_of_birth = '';
    public string $place_of_birth = '';
    public Collection $existing_files;
    public $existing_photo = null;

    public function mount()
    {
        $this->existing_files = collect();

    $user = auth()->user();
    if ($user->teacherProfile) {
        $profile = $user->teacherProfile;
        $this->whatsapp = $profile->whatsapp ?? '';
        $this->phone = $profile->phone ?? '';
        $this->fix_number = $profile->fix_number ?? '';
        $this->date_of_birth = $profile->date_of_birth ? $profile->date_of_birth->format('Y-m-d') : '';
        $this->place_of_birth = $profile->place_of_birth ?? '';
        $this->existing_photo = $profile->photo;
        $this->status = $profile->status ?? '';

        $this->existing_files = $user->teacherProfile->files()
            ->where('collection', 'documents')
            ->get();
    }
        }


    protected function storeFile($file)
    {
        $path = $file->store('documents', 'public');

        return File::create([
            'name' => Str::random(40),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'model_type' => TeacherProfile::class,
            'model_id' => auth()->user()->teacherProfile->id,
            'collection' => 'documents',
            'user_id' => auth()->id()
        ]);
    }

    public function save()
    {
        $validated = $this->validate([
            'whatsapp' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
            'fix_number' => ['nullable', 'string', 'max:20'],
            'photo' => [
                $this->existing_photo ? 'nullable' : 'required',
                'image',
            ],
            'files.*' => ['file'],
            'date_of_birth' => ['required', 'date'],
            'place_of_birth' => ['required', 'string', 'max:255'],
        ]);

    try {
        $user = auth()->user();

        if ($this->photo) {
            if ($this->existing_photo) {
                Storage::disk('public')->delete($this->existing_photo);
            }
            $photoPath = $this->photo->store('photos', 'public');
        }

        $profile = $user->teacherProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'whatsapp' => $this->whatsapp,
                'phone' => $this->phone,
                'fix_number' => $this->fix_number,
                'photo' => $this->photo ? $photoPath : $this->existing_photo,
                'date_of_birth' => $this->date_of_birth,
                'place_of_birth' => $this->place_of_birth,
                'has_completed_profile' => true,
                'status' => $user->teacherProfile && $user->teacherProfile->status ?
                    $user->teacherProfile->status :
                    TeacherProfile::STATUS_SUBMITTED
            ]
        );

        foreach($this->files as $file) {
            $this->storeFile($file);
        }

        $this->toast(
                type: 'success',
                title: 'Profile updated successfully!',
                description: $profile->status === TeacherProfile::STATUS_SUBMITTED ?
                    'Your profile will be reviewed by an administrator.' :
                    'Your profile has been updated.',
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                css: 'alert-success',
                timeout: 3000,
                redirectTo: route('teachers.dashboard')
            );

            return $this->redirect(route('teachers.dashboard'), navigate: true);

        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Update failed!',
                description: 'Something went wrong while updating your profile.',
                position: 'toast-bottom toast-end',
                icon: 'o-x-circle',
                css: 'alert-error',
                timeout: 3000
            );
        }
    }

    public function deleteFile($fileId)
    {
        $file = File::find($fileId);
        if ($file && $file->user_id === auth()->id()) {
            try {
                Storage::disk('public')->delete($file->path);
                $file->delete();

                $this->existing_files = auth()->user()->teacherProfile
                    ->files()
                    ->where('collection', 'documents')
                    ->get();

                $this->toast(
                    type: 'success',
                    title: 'File deleted successfully!',
                    position: 'toast-bottom toast-end',
                    icon: 'o-trash',
                    css: 'alert-success',
                    timeout: 3000
                );
            } catch (\Exception $e) {
                $this->toast(
                    type: 'error',
                    title: 'Delete failed!',
                    description: 'Could not delete the file.',
                    position: 'toast-bottom toast-end',
                    icon: 'o-x-circle',
                    css: 'alert-error',
                    timeout: 3000
                );
            }
        }
    }

    public function deleteFileWithConfirm($fileId)
    {
        $file = File::find($fileId);
        if ($file && $file->user_id === auth()->id()) {
            $this->toast(
                type: 'warning',
                title: 'Delete File?',
                description: 'Are you sure you want to delete this file?',
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
}; ?>

<div class="p-6">
    <div class="max-w-2xl mx-auto">
        <h2 class="mb-6 text-lg font-medium">Teacher Profile</h2>

        <x-form wire:submit="save" class="space-y-6">
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

            <!-- File Uploads -->
            <div class="space-y-4">
                <!-- Profile Photo -->
                <div>
                    @if($existing_photo)
                        <div class="mb-2">
                            <img src="{{ Storage::url($existing_photo) }}" class="object-cover w-20 h-20 rounded-full">
                        </div>
                    @endif

                    <x-input
                        label="Profile Photo"
                        wire:model="photo"
                        type="file"
                        accept="image/*"
                        icon="o-camera"
                        inline
                        required="{{ !$existing_photo }}"
                    />

                    @if($photo)
                        <div class="mt-2">
                            <img src="{{ $photo->temporaryUrl() }}" class="object-cover w-20 h-20 rounded-full">
                        </div>
                    @endif
                </div>

                <!-- Documents Upload -->
                <div>
                    <x-input
                        label="Upload Documents"
                        wire:model="files"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        icon="o-document"
                        inline
                        multiple
                    />
                </div>

                <div>
                    @if($existing_files && $existing_files->count() > 0)
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
                                    @foreach($existing_files as $file)
                                        <tr>
                                            <td>{{ $file->original_name }}</td>
                                            <td>{{ number_format($file->size / 1024, 2) }} KB</td>
                                            <td class="flex gap-2">
                                                <a href="{{ Storage::disk('public')->url($file->path) }}"
                                                   download="{{ $file->original_name }}"
                                                   class="btn btn-sm btn-primary">
                                                    <x-icon name="s-arrow-down-on-square" class="w-4 h-4" />
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
                        <div class="py-4 text-center">
                            <p>No files uploaded yet.</p>
                        </div>
                    @endif
                </div>
                <h2 class="mb-6 text-lg font-medium">Teacher Profile</h2>

@if($status)
    <div class="mb-6">
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium">Status:</span>
            <span class="badge {{
                $status === TeacherProfile::STATUS_SUBMITTED ? 'badge-primary' :
                ($status === TeacherProfile::STATUS_CHECKING ? 'badge-warning' :
                ($status === TeacherProfile::STATUS_VERIFIED ? 'badge-success' : ''))
            }}">
                {{ ucfirst($status) }}
            </span>
        </div>
    </div>
@endif
            </div>

            <x-slot:actions>
                <x-button
                    label="Save Profile"
                    type="submit"
                    icon="o-check"
                    class="w-full btn-primary"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </div>
</div>
