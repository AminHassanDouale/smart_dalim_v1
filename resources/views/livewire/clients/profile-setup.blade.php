<?php
// 1. First, update web.php with these routes

/*
Route::middleware(['auth', 'role:client'])->group(function () {
    // Profile Setup Route
    Volt::route('/clients/profile-setup', 'clients.profile-setup')
        ->name('clients.profile-setup');

    // Other Client Routes
    Volt::route('/clients/profile', 'clients.profile')
        ->name('clients.profile');

    Volt::route('/clients/dashboard', 'clients.dashboard')
        ->name('clients.dashboard');

    Volt::route('/clients/{user}/edit', 'clients.profile.edit')
        ->name('clients.profile.edit');

    Volt::route('/clients/projects', 'clients.projects')
        ->name('clients.projects');

    Volt::route('/clients/session-requests', 'clients.session-requests')
        ->name('clients.session-requests');
});
*/

// 2. Create ClientProfile Model (app/Models/ClientProfile.php)
/*
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ClientProfile extends Model
{
    use HasFactory;

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'company_name',
        'whatsapp',
        'phone',
        'website',
        'position',
        'address',
        'city',
        'country',
        'industry',
        'company_size',
        'preferred_services',
        'preferred_contact_method',
        'notes',
        'logo',
        'has_completed_profile',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'preferred_services' => 'array',
        'has_completed_profile' => 'boolean',
    ];

    /**
     * Get the user that owns the client profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the files for the client profile.
     */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    /**
     * Check if profile is pending approval
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if profile is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if profile is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
*/

// 3. Create the migration for client_profiles table
// php artisan make:migration create_client_profiles_table
/*
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ClientProfile;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Basic information
            $table->string('company_name');
            $table->string('whatsapp');
            $table->string('phone');
            $table->string('website')->nullable();
            $table->string('position');

            // Company details
            $table->string('address');
            $table->string('city');
            $table->string('country');
            $table->string('industry');
            $table->string('company_size');

            // Preferences
            $table->json('preferred_services');
            $table->string('preferred_contact_method');
            $table->text('notes')->nullable();

            // Files
            $table->string('logo')->nullable();

            // Status
            $table->boolean('has_completed_profile')->default(false);
            $table->string('status')->default(ClientProfile::STATUS_PENDING);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
*/

// 4. Here's the complete Livewire component for resources/views/livewire/clients/profile-setup.blade.php
?>

<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Carbon\Carbon;
use App\Models\File;
use App\Models\ClientProfile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;

new class extends Component {
    use WithFileUploads;

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
    public string $status = '';

    public Collection $existing_files;
    public $existing_logo = null;

    // Step tracking
    public int $currentStep = 1;
    public int $totalSteps = 4;

    public function mount()
    {
        $this->existing_files = collect();

        $user = auth()->user();
        if ($user->clientProfile) {
            $profile = $user->clientProfile;

            // Basic info
            $this->company_name = $profile->company_name ?? '';
            $this->whatsapp = $profile->whatsapp ?? '';
            $this->phone = $profile->phone ?? '';
            $this->website = $profile->website ?? '';
            $this->position = $profile->position ?? '';

            // Company details
            $this->address = $profile->address ?? '';
            $this->city = $profile->city ?? '';
            $this->country = $profile->country ?? '';
            $this->industry = $profile->industry ?? '';
            $this->company_size = $profile->company_size ?? '';

            // Preferences
            $this->preferred_services = $profile->preferred_services ?? [];
            $this->preferred_contact_method = $profile->preferred_contact_method ?? '';
            $this->notes = $profile->notes ?? '';

            // Files
            $this->existing_logo = $profile->logo;
            $this->status = $profile->status ?? '';

            $this->existing_files = $user->clientProfile->files()
                ->where('collection', 'client_documents')
                ->get();

            // If profile completed, start at verification step
            if ($profile->has_completed_profile) {
                $this->currentStep = 4;
            }
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
            'model_id' => auth()->user()->clientProfile->id,
            'collection' => 'client_documents',
            'user_id' => auth()->id()
        ]);
    }

    public function nextStep()
    {
        // Validate current step before proceeding
        match ($this->currentStep) {
            1 => $this->validateBasicInfo(),
            2 => $this->validateCompanyDetails(),
            3 => $this->validatePreferences(),
            default => null
        };

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    protected function validateBasicInfo()
    {
        return $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'position' => ['required', 'string', 'max:100'],
        ]);
    }

    protected function validateCompanyDetails()
    {
        return $this->validate([
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'industry' => ['required', 'string', 'max:100'],
            'company_size' => ['required', 'string', 'max:50'],
        ]);
    }

    protected function validatePreferences()
    {
        return $this->validate([
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
    }

    public function validateVerification()
    {
        // Final validation before completing setup
        $this->validateBasicInfo();
        $this->validateCompanyDetails();
        $this->validatePreferences();

        return true;
    }

    public function save()
    {
        if (!$this->validateVerification()) {
            return;
        }

        try {
            $user = auth()->user();

            if ($this->logo) {
                if ($this->existing_logo) {
                    Storage::disk('public')->delete($this->existing_logo);
                }
                $logoPath = $this->logo->store('client_logos', 'public');
            }

            // First create or update the profile
            $profile = $user->clientProfile()->updateOrCreate(
                ['user_id' => $user->id],
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
                    'status' => $user->clientProfile && $user->clientProfile->status ?
                        $user->clientProfile->status :
                        ClientProfile::STATUS_PENDING
                ]
            );

            // Then store any additional files
            foreach($this->files as $file) {
                $this->storeFile($file);
            }

            $this->toast(
                type: 'success',
                title: 'Profile completed successfully!',
                description: 'Your client profile has been set up and is now pending review.',
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                css: 'alert-success',
                timeout: 3000,
                redirectTo: route('clients.dashboard')
            );

            return $this->redirect(route('clients.dashboard'), navigate: true);

        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Update failed!',
                description: 'Something went wrong while setting up your profile: ' . $e->getMessage(),
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

                $this->existing_files = auth()->user()->clientProfile
                    ->files()
                    ->where('collection', 'client_documents')
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
    <div class="max-w-4xl mx-auto">
        <h2 class="mb-6 text-2xl font-medium">ClientProfileSetup</h2>

        <!-- Steps progress -->
        <div class="mb-8">
            <div class="w-full steps">
                <a class="step {{ $currentStep >= 1 ? 'step-primary' : '' }}">Basic Information</a>
                <a class="step {{ $currentStep >= 2 ? 'step-primary' : '' }}">Company Details</a>
                <a class="step {{ $currentStep >= 3 ? 'step-primary' : '' }}">Preferences</a>
                <a class="step {{ $currentStep >= 4 ? 'step-primary' : '' }}">Verification</a>
            </div>
        </div>

        @if($status)
            <div class="mb-6">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium">Status:</span>
                    <span class="badge {{
                        $status === ClientProfile::STATUS_PENDING ? 'badge-warning' :
                        ($status === ClientProfile::STATUS_APPROVED ? 'badge-success' :
                        ($status === ClientProfile::STATUS_REJECTED ? 'badge-error' : ''))
                    }}">
                        {{ ucfirst($status) }}
                    </span>
                </div>
            </div>
        @endif

        <x-form wire:submit="save" class="space-y-6">
            <!-- Step 1: Basic Information -->
            <div class="{{ $currentStep === 1 ? 'block' : 'hidden' }}">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Basic Information</h3>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <x-input
                                label="Company Name"
                                wire:model="company_name"
                                icon="o-building-office"
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

                        <div class="justify-end mt-4 card-actions">
                            <x-button
                                label="Next"
                                icon-right="o-arrow-right"
                                wire:click="nextStep"
                                class="btn-primary"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Company Details -->
            <div class="{{ $currentStep === 2 ? 'block' : 'hidden' }}">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Company Details</h3>

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
                                    icon="o-building"
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

                        <div class="justify-between mt-4 card-actions">
                            <x-button
                                label="Previous"
                                icon="o-arrow-left"
                                wire:click="previousStep"
                                class="btn-outline"
                            />
                            <x-button
                                label="Next"
                                icon-right="o-arrow-right"
                                wire:click="nextStep"
                                class="btn-primary"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Preferences -->
            <div class="{{ $currentStep === 3 ? 'block' : 'hidden' }}">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Preferences & Documents</h3>

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

                            <!-- Company Logo Upload -->
                            <div>
                                <label class="w-full form-control">
                                    <div class="label">
                                        <span class="font-medium label-text">Company Logo</span>
                                    </div>

                                    @if($existing_logo)
                                        <div class="mb-2">
                                            <img src="{{ Storage::url($existing_logo) }}" class="object-contain h-20 rounded">
                                        </div>
                                    @endif

                                    <input
                                        type="file"
                                        wire:model="logo"
                                        accept="image/*"
                                        class="w-full file-input file-input-bordered"
                                        {{ !$existing_logo ? 'required' : '' }}
                                    />

                                    @if($logo)
                                        <div class="mt-2">
                                            <img src="{{ $logo->temporaryUrl() }}" class="object-contain h-20 rounded">
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
                            @endif
                        </div>

                        <div class="justify-between mt-4 card-actions">
                            <x-button
                                label="Previous"
                                icon="o-arrow-left"
                                wire:click="previousStep"
                                class="btn-outline"
                            />
                            <x-button
                                label="Next"
                                icon-right="o-arrow-right"
                                wire:click="nextStep"
                                class="btn-primary"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Verification -->
            <div class="{{ $currentStep === 4 ? 'block' : 'hidden' }}">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Verify Your Information</h3>

                        <div class="space-y-6">
                            <!-- Basic Information Summary -->
                            <div class="card bg-base-200">
                                <div class="card-body">
                                    <h4 class="font-medium">Basic Information</h4>
                                    <div class="grid grid-cols-1 gap-2 mt-2 md:grid-cols-2">
                                        <div class="flex items-start">
                                            <span class="mr-2 font-medium">Company:</span>
                                            <span>{{ $company_name }}</span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="mr-2 font-medium">Position:</span>
                                            <span>{{ $position }}</span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="mr-2 font-medium">WhatsApp:</span>
                                            <span>{{ $whatsapp }}</span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="mr-2 font-medium">Phone:</span>
                                            <span>{{ $phone }}</span>
                                        </div>
                                        @if($website)
                                        <div class="flex items-start md:col-span-2">
                                            <span class="mr-2 font-medium">Website:</span>
                                            <span>{{ $website }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Company Details Summary -->
                            <div class="card bg-base-200">
                                <div class="card-body">
                                    <h4 class="font-medium">Company Details</h4>
                                    <div class="grid grid-cols-1 gap-2 mt-2 md:grid-cols-2">
                                        <div class="flex items-start md:col-span-2">
                                            <span class="mr-2 font-medium">Address:</span>
                                            <span>{{ $address }}</span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="mr-2 font-medium">City:</span>
                                            <span>{{ $city }}</span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="mr-2 font-medium">Country:</span>
                                            <span>{{ $country }}</span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="mr-2 font-medium">Industry:</span>
                                            <span>{{ $industry }}</span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="mr-2 font-medium">Company Size:</span>
                                            <span>{{ $company_size }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Preferences Summary -->
                            <div class="card bg-base-200">
                                <div class="card-body">
                                    <h4 class="font-medium">Preferences & Documents</h4>
                                    <div class="grid grid-cols-1 gap-2 mt-2 md:grid-cols-2">
                                        <div class="flex items-start md:col-span-2">
                                            <span class="mr-2 font-medium">Services Interested In:</span>
                                            <span>{{ implode(', ', array_map('ucfirst', $preferred_services)) }}</span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="mr-2 font-medium">Preferred Contact:</span>
                                            <span>{{ ucfirst($preferred_contact_method) }}</span>
                                        </div>
                                        @if($notes)
                                        <div class="flex items-start md:col-span-2">
                                            <span class="mr-2 font-medium">Notes:</span>
                                            <span>{{ $notes }}</span>
                                        </div>
                                        @endif

                                        @if($existing_logo || $logo)
                                        <div class="flex items-start mt-2 md:col-span-2">
                                            <span class="mr-2 font-medium">Company Logo:</span>
                                            <div>
                                                @if($logo)
                                                    <img src="{{ $logo->temporaryUrl() }}" class="object-contain h-16 rounded">
                                                @elseif($existing_logo)
                                                    <img src="{{ Storage::url($existing_logo) }}" class="object-contain h-16 rounded">
                                                @endif
                                            </div>
                                        </div>
                                        @endif

                                        @if($existing_files && $existing_files->count() > 0)
                                        <div class="flex items-start mt-2 md:col-span-2">
                                            <span class="mr-2 font-medium">Documents:</span>
                                            <span>{{ $existing_files->count() }} file(s) uploaded</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <x-icon name="o-information-circle" class="w-5 h-5" />
                                <span>Please verify all information is correct before submitting. Once submitted, your profile will be reviewed by our team.</span>
                            </div>
                        </div>

                        <div class="justify-between mt-4 card-actions">
                            <x-button
                                label="Previous"
                                icon="o-arrow-left"
                                wire:click="previousStep"
                                class="btn-outline"
                            />
                            <x-button
                                label="Complete Setup"
                                icon="o-check"
                                type="submit"
                                class="btn-primary"
                                spinner="save"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </x-form>
    </div>
</div>
