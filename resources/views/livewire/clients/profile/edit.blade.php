<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\ClientProfile;
use App\Models\User;
use App\Models\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

new class extends Component {
    use WithFileUploads;

    public User $user;
    public $clientProfile;

    // Basic info
    public string $company_name = '';
    public string $whatsapp = '';
    public string $phone = '';
    public string $website = '';
    public string $position = '';

    // Company details
    public string $address = '';
    public string $city = '';
    public string $country = '';
    public string $industry = '';
    public string $company_size = '';

    // Preferences
    public array $preferred_services = [];
    public string $preferred_contact_method = '';
    public string $notes = '';

    // File uploads
    public $logo;
    public $files = [];

    public $existing_files;
    public $existing_logo = null;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->clientProfile = $user->clientProfile;

        // Load existing data if profile exists
        if ($this->clientProfile) {
            // Basic info
            $this->company_name = $this->clientProfile->company_name ?? '';
            $this->whatsapp = $this->clientProfile->whatsapp ?? '';
            $this->phone = $this->clientProfile->phone ?? '';
            $this->website = $this->clientProfile->website ?? '';
            $this->position = $this->clientProfile->position ?? '';

            // Company details
            $this->address = $this->clientProfile->address ?? '';
            $this->city = $this->clientProfile->city ?? '';
            $this->country = $this->clientProfile->country ?? '';
            $this->industry = $this->clientProfile->industry ?? '';
            $this->company_size = $this->clientProfile->company_size ?? '';

            // Preferences
            $this->preferred_services = $this->clientProfile->preferred_services ?? [];
            $this->preferred_contact_method = $this->clientProfile->preferred_contact_method ?? '';
            $this->notes = $this->clientProfile->notes ?? '';

            // Files
            $this->existing_logo = $this->clientProfile->logo;
            $this->existing_files = $this->clientProfile->files()
                ->where('collection', 'client_documents')
                ->get();
        } else {
            $this->existing_files = collect();
        }
    }

    protected function storeFile($file)
    {
        $path = $file->store('client_documents', 'public');

        return File::create([
            'name' => Str::random(40),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'model_type' => ClientProfile::class,
            'model_id' => $this->clientProfile->id,
            'collection' => 'client_documents',
            'user_id' => $this->user->id
        ]);
    }

    public function save()
    {
        $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'position' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'industry' => ['required', 'string', 'max:100'],
            'company_size' => ['required', 'string', 'max:50'],
            'preferred_services' => ['required', 'array', 'min:1'],
            'preferred_contact_method' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'logo' => [
                $this->existing_logo ? 'nullable' : 'required',
                'image',
                'max:2048',
            ],
            'files.*' => ['nullable', 'file', 'max:10240'],
        ]);

        try {
            if ($this->logo) {
                if ($this->existing_logo) {
                    Storage::disk('public')->delete($this->existing_logo);
                }
                $logoPath = $this->logo->store('client_logos', 'public');
            }

            // Create or update the profile
            $profile = ClientProfile::updateOrCreate(
                ['user_id' => $this->user->id],
                [
                    // Basic info
                    'company_name' => $this->company_name,
                    'whatsapp' => $this->whatsapp,
                    'phone' => $this->phone,
                    'website' => $this->website,
                    'position' => $this->position,

                    // Company details
                    'address' => $this->address,
                    'city' => $this->city,
                    'country' => $this->country,
                    'industry' => $this->industry,
                    'company_size' => $this->company_size,

                    // Preferences
                    'preferred_services' => $this->preferred_services,
                    'preferred_contact_method' => $this->preferred_contact_method,
                    'notes' => $this->notes,

                    // Files
                    'logo' => $this->logo ? $logoPath : $this->existing_logo,

                    // Status
                    'has_completed_profile' => true,
                ]
            );

            $this->clientProfile = $profile;

            // Store additional files
            foreach($this->files as $file) {
                $this->storeFile($file);
            }

            // Refresh the list of files
            if ($this->clientProfile) {
                $this->existing_files = $this->clientProfile->files()
                    ->where('collection', 'client_documents')
                    ->get();
            }

            // Reset file uploads
            $this->logo = null;
            $this->files = [];

            // Show success message
            session()->flash('message', 'Profile updated successfully!');

            $this->redirect(route('clients.profile'), navigate: true);

        } catch (\Exception $e) {
            session()->flash('error', 'Error updating profile: ' . $e->getMessage());
        }
    }

    public function deleteFile($fileId)
    {
        $file = File::find($fileId);
        if ($file && $file->user_id === $this->user->id) {
            try {
                Storage::disk('public')->delete($file->path);
                $file->delete();

                // Refresh the file list
                $this->existing_files = $this->clientProfile->files()
                    ->where('collection', 'client_documents')
                    ->get();

                session()->flash('message', 'File deleted successfully!');
            } catch (\Exception $e) {
                session()->flash('error', 'Could not delete the file: ' . $e->getMessage());
            }
        }
    }

    public function deleteFileWithConfirm($fileId)
    {
        $this->js("
            if (confirm('Are you sure you want to delete this file?')) {
                $wire.deleteFile($fileId);
            }
        ");
    }
}; ?>

<div class="p-6">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Edit Company Profile</h1>
            <a href="{{ route('clients.profile') }}" class="btn btn-outline">
                <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                Back to Profile
            </a>
        </div>

        @if(session('message'))
            <div class="mb-6 alert alert-success">
                <x-icon name="o-check-circle" class="w-5 h-5" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 alert alert-error">
                <x-icon name="o-exclamation-circle" class="w-5 h-5" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <x-form wire:submit="save" class="space-y-6">
            <!-- Basic Information Card -->
            <div class="shadow-xl card bg-base-100">
                <div class="card-body">
                    <h2 class="mb-4 card-title">Basic Information</h2>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <x-input
                            label="Company Name"
                            wire:model="company_name"
                            icon="o-eye"
                            inline
                            required
                        />

                        <x-input
                            label="Your Position/Title"
                            wire:model="position"
                            icon="o-briefcase"
                            inline
                            required
                        />

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
                            label="Company Website"
                            wire:model="website"
                            icon="o-globe-alt"
                            placeholder="https://example.com"
                            inline
                        />
                    </div>
                </div>
            </div>

            <!-- Company Details Card -->
            <div class="shadow-xl card bg-base-100">
                <div class="card-body">
                    <h2 class="mb-4 card-title">Company Details</h2>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <x-input
                            label="Address"
                            wire:model="address"
                            icon="o-map-pin"
                            inline
                            required
                        />

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-input
                                label="City"
                                wire:model="city"
                                icon="o-eye"
                                inline
                                required
                            />

                            <x-input
                                label="Country"
                                wire:model="country"
                                icon="o-flag"
                                inline
                                required
                            />
                        </div>

                        <x-input
                            label="Industry"
                            wire:model="industry"
                            icon="o-academic-cap"
                            inline
                            required
                        />

                        <x-select
                            label="Company Size"
                            wire:model="company_size"
                            icon="o-user-group"
                            inline
                            required
                        >
                            <option value="">Select company size</option>
                            <option value="1-10">1-10 employees</option>
                            <option value="11-50">11-50 employees</option>
                            <option value="51-200">51-200 employees</option>
                            <option value="201-500">201-500 employees</option>
                            <option value="501+">501+ employees</option>
                        </x-select>
                    </div>
                </div>
            </div>

            <!-- Preferences Card -->
            <div class="shadow-xl card bg-base-100">
                <div class="card-body">
                    <h2 class="mb-4 card-title">Preferences</h2>

                    <div class="space-y-6">
                        <div>
                            <label class="w-full form-control">
                                <div class="label">
                                    <span class="font-medium label-text">Services Interested In</span>
                                </div>
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="preferred_services" value="consulting" class="checkbox" />
                                        <span>Consulting</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="preferred_services" value="development" class="checkbox" />
                                        <span>Development</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="preferred_services" value="training" class="checkbox" />
                                        <span>Training</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model="preferred_services" value="support" class="checkbox" />
                                        <span>Support</span>
                                    </label>
                                </div>
                                @error('preferred_services') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                            </label>
                        </div>

                        <x-select
                            label="Preferred Contact Method"
                            wire:model="preferred_contact_method"
                            icon="o-chat-bubble-left-right"
                            inline
                            required
                        >
                            <option value="">Select contact method</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                            <option value="whatsapp">WhatsApp</option>
                        </x-select>

                        <x-textarea
                            label="Additional Notes"
                            wire:model="notes"
                            icon="o-document-text"
                            inline
                            placeholder="Any specific requirements or information you'd like to share"
                        />
                    </div>
                </div>
            </div>

            <!-- Files Card -->
            <div class="shadow-xl card bg-base-100">
                <div class="card-body">
                    <h2 class="mb-4 card-title">Files & Documents</h2>

                    <div class="space-y-6">
                        <!-- Company Logo Upload -->
                        <div>
                            <label class="w-full form-control">
                                <div class="label">
                                    <span class="font-medium label-text">Company Logo</span>
                                </div>

                                @if($existing_logo)
                                    <div class="flex items-center mb-4">
                                        <img src="{{ Storage::url($existing_logo) }}" class="object-contain h-24 rounded" alt="Current logo">
                                        <div class="ml-4">
                                            <div class="mb-1 text-sm opacity-70">Current logo</div>
                                            <label class="btn btn-sm btn-outline">
                                                <x-icon name="o-arrow-path" class="w-4 h-4 mr-2" />
                                                Replace
                                                <input type="file" wire:model="logo" class="hidden" accept="image/*" />
                                            </label>
                                        </div>
                                    </div>
                                @else
                                    <input
                                        type="file"
                                        wire:model="logo"
                                        accept="image/*"
                                        class="w-full file-input file-input-bordered"
                                        required
                                    />
                                @endif

                                @if($logo)
                                    <div class="mt-4">
                                        <div class="mb-1 text-sm opacity-70">New logo preview</div>
                                        <img src="{{ $logo->temporaryUrl() }}" class="object-contain h-24 rounded" alt="New logo preview">
                                    </div>
                                @endif

                                @error('logo') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                            </label>
                        </div>

                        <!-- Documents Upload -->
                        <div>
                            <label class="w-full form-control">
                                <div class="label">
                                    <span class="font-medium label-text">Company Documents (Optional)</span>
                                </div>
                                <input
                                    type="file"
                                    wire:model="files"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                    class="w-full file-input file-input-bordered"
                                />
                                <div class="label">
                                    <span class="label-text-alt">Upload brochures, certificates, or other relevant documents</span>
                                </div>
                                @error('files.*') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                            </label>
                        </div>

                        <!-- Existing Files -->
                        @if($existing_files && $existing_files->count() > 0)
                            <div>
                                <div class="label">
                                    <span class="font-medium label-text">Uploaded Documents</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Size</th>
                                                <th>Uploaded</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($existing_files as $file)
                                                <tr>
                                                    <td>{{ $file->original_name }}</td>
                                                    <td>{{ number_format($file->size / 1024, 2) }} KB</td>
                                                    <td>{{ $file->created_at ? $file->created_at->format('M d, Y') : 'N/A' }}</td>
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
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-between pt-4">
                <a href="{{ route('clients.profile') }}" class="btn btn-outline">Cancel</a>
                <x-button
                    label="Save Changes"
                    type="submit"
                    icon="o-check"
                    class="btn-primary"
                    spinner="save"
                />
            </div>
        </x-form>
    </div>
</div>
