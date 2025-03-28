<?php

use App\Models\TeacherProfile;
use App\Models\Subject;
use App\Models\File;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $user;

    // Teacher profile fields
    public $whatsapp = '';
    public $phone = '';
    public $fix_number = '';
    public $date_of_birth = '';
    public $place_of_birth = '';
    public $photo;
    public $selectedSubjects = [];

    // Document upload fields
    public $documents = [];
    public $document_types = [];

    // Step tracking
    public $currentStep = 1;
    public $totalSteps = 2;

    public function mount()
    {
        $this->user = auth()->user();

        // Pre-fill the data if profile exists
        if ($teacherProfile = $this->user->teacherProfile) {
            $this->whatsapp = $teacherProfile->whatsapp;
            $this->phone = $teacherProfile->phone;
            $this->fix_number = $teacherProfile->fix_number;
            $this->date_of_birth = $teacherProfile->date_of_birth ? $teacherProfile->date_of_birth->format('Y-m-d') : '';
            $this->place_of_birth = $teacherProfile->place_of_birth;
        }

        // Get selected subjects from the user
        $this->selectedSubjects = $this->user->subjects->pluck('id')->toArray();
    }

    public function subjects()
    {
        return Subject::all();
    }

    public function documentTypes()
    {
        return [
            ['id' => 'cv', 'name' => 'CV/Resume'],
            ['id' => 'diploma', 'name' => 'Diploma/Degree'],
            ['id' => 'certificate', 'name' => 'Certificate'],
            ['id' => 'id_card', 'name' => 'ID Card'],
            ['id' => 'other', 'name' => 'Other Document'],
        ];
    }

    public function saveBasicInfo()
    {
        $this->validate([
            'whatsapp' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'fix_number' => 'nullable|string|max:20',
            'date_of_birth' => 'required|date|before:today',
            'place_of_birth' => 'required|string|max:255',
            'photo' => 'nullable|image|max:2048',
            'selectedSubjects' => 'required|array|min:1',
        ]);

        $profileData = [
            'whatsapp' => $this->whatsapp,
            'phone' => $this->phone,
            'fix_number' => $this->fix_number,
            'date_of_birth' => $this->date_of_birth,
            'place_of_birth' => $this->place_of_birth,
        ];

        // Handle photo upload if provided
        if ($this->photo) {
            $path = $this->photo->store('teacher-photos', 'public');
            $profileData['photo'] = $path;
        }

        // Update teacher profile
        $this->user->teacherProfile->update($profileData);

        // Sync subjects
        $this->user->subjects()->sync($this->selectedSubjects);
        $this->user->teacherProfile->subjects()->sync($this->selectedSubjects);

        $this->js("
            Toaster.success('Basic information saved!', {
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                css: 'alert-success',
                timeout: 2000
            });
        ");

        $this->nextStep();
    }

    public function completeSetup()
    {
        // If documents are provided, validate them
        if (count($this->documents) > 0) {
            $this->validate([
                'documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
                'document_types' => 'array',
                'document_types.*' => 'required|string|in:cv,diploma,certificate,id_card,other',
            ]);

            // Handle document uploads
           // Handle document uploads
foreach ($this->documents as $index => $document) {
    $path = $document->store('teacher-documents', 'public');
    $type = $this->document_types[$index] ?? 'other';

    File::create([
        'name' => $document->getClientOriginalName(), // This was renamed from file_name
        'original_name' => $document->getClientOriginalName(),
        'path' => $path, // This was renamed from file_path
        'mime_type' => $document->getClientMimeType(), // This was renamed from file_type
        'size' => $document->getSize(), // This was renamed from file_size
        'model_type' => TeacherProfile::class,
        'model_id' => $this->user->teacherProfile->id,
        'collection' => $type, // This was renamed from category
        'user_id' => $this->user->id // This is required in your model
           ]);
            }
        }

        // Mark profile as complete
        $this->user->teacherProfile->update([
            'has_completed_profile' => true,
        ]);

        $this->js("
            Toaster.success('Profile setup completed!', {
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                css: 'alert-success',
                timeout: 2000
            });
        ");

        return redirect()->route('teachers.dashboard');
    }

    public function nextStep()
    {
        $this->currentStep++;
    }

    public function previousStep()
    {
        $this->currentStep--;
    }
}; ?>

<div class="container max-w-3xl px-4 py-6 mx-auto">
    <div class="mb-8 text-center">
        <h2 class="text-2xl font-bold">Teacher Profile Setup</h2>
        <p class="text-gray-600">Complete your profile to get started</p>
    </div>

    <!-- Progress Steps -->
    <div class="mb-8">
        <ol class="flex items-center w-full">
            @for ($i = 1; $i <= $totalSteps; $i++)
                <li class="flex items-center {{ $i < $totalSteps ? 'after:content-[\'\'] after:w-full after:h-1 after:border-b after:border-blue-100 after:border-4 after:inline-block' : '' }}">
                    <span @class([
                        'flex items-center justify-center w-10 h-10 rounded-full shrink-0',
                        'bg-blue-600 text-white' => $currentStep >= $i,
                        'bg-gray-100' => $currentStep < $i
                    ])>{{ $i }}</span>
                </li>
            @endfor
        </ol>
    </div>

    <!-- Step 1: Basic Information -->
    @if ($currentStep === 1)
        <div class="p-6 bg-white rounded-lg shadow-md">
            <h3 class="mb-4 text-xl font-semibold">Basic Information</h3>

            <x-form wire:submit="saveBasicInfo">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input
                        label="Phone Number"
                        wire:model="phone"
                        icon="o-device-phone-mobile"
                        required
                    />

                    <x-input
                        label="WhatsApp Number"
                        wire:model="whatsapp"
                        icon="o-chat-bubble-left-ellipsis"
                        required
                    />
                </div>

                <x-input
                    label="Landline (Optional)"
                    wire:model="fix_number"
                    icon="o-phone"
                />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input
                        label="Date of Birth"
                        wire:model="date_of_birth"
                        type="date"
                        icon="o-calendar"
                        required
                    />

                    <x-input
                        label="Place of Birth"
                        wire:model="place_of_birth"
                        icon="o-map-pin"
                        required
                    />
                </div>

                <div class="mb-4">
                    <label class="block mb-1 text-sm font-medium text-gray-700">
                        Profile Photo (Optional)
                    </label>
                    <x-file wire:model="photo" accept="image/*" />
                    @if($photo)
                        <div class="mt-2">
                            <p>Preview:</p>
                            <img src="{{ $photo->temporaryUrl() }}" class="object-cover w-24 h-24 rounded-full">
                        </div>
                    @endif
                </div>

                <div class="mb-4">
                    <label class="block mb-1 text-sm font-medium text-gray-700">
                        Subjects You Teach
                    </label>
                    <x-choices
                        wire:model="selectedSubjects"
                        :options="$this->subjects()"
                        option-label="name"
                        option-value="id"
                        :searchable="true"
                        multiple
                        required
                    />
                    @error('selectedSubjects') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                <x-slot:actions>
                    <div class="flex justify-end">
                        <x-button
                            type="submit"
                            label="Save & Continue"
                            icon="o-arrow-right"
                            class="btn-primary"
                        />
                    </div>
                </x-slot:actions>
            </x-form>
        </div>
    <!-- Step 2: Document Upload -->
    @elseif ($currentStep === 2)
        <div class="p-6 bg-white rounded-lg shadow-md">
            <h3 class="mb-4 text-xl font-semibold">Upload Documents</h3>

            <p class="mb-4 text-gray-600">
                Please upload your CV, certificates, and other relevant documents to complete your profile.
            </p>

            <x-form wire:submit="completeSetup">
                <div class="mb-6">
                    <label class="block mb-1 text-sm font-medium text-gray-700">
                        Upload Documents (Optional)
                    </label>
                    <x-file wire:model="documents" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                    <p class="mt-1 text-sm text-gray-500">
                        Accepted formats: PDF, Word, JPG, PNG (max 5MB each)
                    </p>
                    @error('documents') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                    @error('documents.*') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                </div>

                @if (count($documents) > 0)
                    <div class="mb-6">
                        <h4 class="mb-2 text-lg font-medium">Selected Documents</h4>
                        <div class="space-y-3">
                            @foreach ($documents as $index => $document)
                                <div class="p-3 mb-3 border rounded-md bg-gray-50">
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <p class="font-medium">{{ $document->getClientOriginalName() }}</p>
                                            <p class="text-sm text-gray-500">{{ round($document->getSize() / 1024) }} KB</p>
                                        </div>
                                    </div>

                                    <x-select
                                        label="Document Type"
                                        wire:model="document_types.{{ $index }}"
                                        :options="$this->documentTypes()"
                                        option-label="name"
                                        option-value="id"
                                        required
                                    />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex justify-between mt-6">
                    <x-button
                        wire:click="previousStep"
                        type="button"
                        label="Back"
                        icon="o-arrow-left"
                        class="btn-secondary"
                    />

                    <x-button
                        type="submit"
                        label="Complete Setup"
                        icon="o-check"
                        class="btn-primary"
                    />
                </div>
            </x-form>
        </div>
    @endif
</div>
