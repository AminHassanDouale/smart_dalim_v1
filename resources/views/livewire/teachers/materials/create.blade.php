<?php

namespace App\Livewire\Teachers\Materials;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Material;
use App\Models\Subject;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new class extends Component {
    use WithFileUploads;

    // User and profile
    public $teacher;
    public $teacherProfile;
    public $teacherProfileId = null;

    // Material form fields
    public $title = '';
    public $description = '';
    public $materialType = 'document';
    public $file;
    public $externalUrl = '';
    public $isPublic = false;
    public $isFeatured = false;
    public $selectedSubjects = [];
    public $selectedCourses = [];
    public $tags = [];
    public $customTags = '';

    // UI state
    public $currentStep = 1;
    public $totalSteps = 4;
    public $previewMode = false;
    public $uploadProgress = 0;
    public $dragActive = false;
    public $saveButtonDisabled = false;

    // File preview data
    public $filePreviewUrl = null;
    public $fileSize = null;
    public $fileName = null;
    public $fileType = null;

    // Lists for selection
    public $availableTypes = [
        'document' => 'Document',
        'pdf' => 'PDF',
        'spreadsheet' => 'Spreadsheet',
        'presentation' => 'Presentation',
        'video' => 'Video',
        'audio' => 'Audio',
        'image' => 'Image',
        'link' => 'Link/URL',
        'archive' => 'Archive/ZIP',
        'other' => 'Other',
    ];

    public $predefinedTags = [
        'Homework', 'Quiz', 'Exam', 'Lecture', 'Tutorial',
        'Worksheet', 'Reference', 'Assignment', 'Reading',
        'Project', 'Lab', 'Research', 'Practice', 'Summary'
    ];

    // File type icons mapping
    public $typeIcons = [
        'document' => 'o-document-text',
        'pdf' => 'o-document',
        'spreadsheet' => 'o-table-cells',
        'presentation' => 'o-presentation-chart-bar',
        'video' => 'o-film',
        'audio' => 'o-musical-note',
        'image' => 'o-photo',
        'link' => 'o-link',
        'archive' => 'o-archive-box',
        'other' => 'o-document',
    ];

    public $subjects = [];
    public $courses = [];

    // Validation rules
    protected function rules()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'materialType' => 'required|string|in:' . implode(',', array_keys($this->availableTypes)),
            'isPublic' => 'boolean',
            'isFeatured' => 'boolean',
            'selectedSubjects' => 'array',
            'selectedCourses' => 'array',
            'tags' => 'array',
        ];

        if ($this->materialType === 'link') {
            $rules['externalUrl'] = 'required|url|max:500';
        } else {
            $rules['file'] = 'required|file|max:51200'; // 50MB max
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'title.required' => 'Please provide a title for your material.',
            'file.required' => 'Please upload a file.',
            'externalUrl.required' => 'Please provide a valid URL.',
            'externalUrl.url' => 'The URL format is invalid.',
            'file.max' => 'The file size cannot exceed 50MB.'
        ];
    }

    public function mount()
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        if ($this->teacherProfile) {
            $this->teacherProfileId = $this->teacherProfile->id;
            $this->loadSubjects();
            $this->loadCourses();
        } else {
            return redirect()->route('teachers.profile-setup')
                ->with('error', 'Please complete your teacher profile before creating materials.');
        }
    }

    protected function loadSubjects()
    {
        if ($this->teacherProfile) {
            $this->subjects = $this->teacherProfile->subjects ?? collect();
        } else {
            $this->subjects = collect();
        }
    }

    protected function loadCourses()
    {
        if ($this->teacherProfile) {
            try {
                $this->courses = Course::where('teacher_profile_id', $this->teacherProfileId)->get() ?? collect();
            } catch (\Exception $e) {
                $this->courses = collect();
            }
        } else {
            $this->courses = collect();
        }
    }

    // Handle file upload and preview
    public function updatedFile()
    {
        if ($this->file) {
            $this->validateOnly('file');

            $this->fileName = $this->file->getClientOriginalName();
            $this->fileSize = $this->humanReadableSize($this->file->getSize());
            $this->fileType = $this->file->getMimeType();

            // Generate preview for images
            if (Str::startsWith($this->fileType, 'image/')) {
                $this->filePreviewUrl = $this->file->temporaryUrl();
            } else {
                $this->filePreviewUrl = null;
            }
        }
    }

    // Convert bytes to human-readable format
    private function humanReadableSize($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // Handle file drop zone activation
    public function setDragActive($status)
    {
        $this->dragActive = $status;
    }

    public function removeFile()
    {
        $this->file = null;
        $this->filePreviewUrl = null;
        $this->fileName = null;
        $this->fileSize = null;
        $this->fileType = null;
    }

    // Toggle a subject selection
    public function toggleSubject($id)
    {
        if (in_array($id, $this->selectedSubjects)) {
            $this->selectedSubjects = array_diff($this->selectedSubjects, [$id]);
        } else {
            $this->selectedSubjects[] = $id;
        }
    }

    // Toggle a course selection
    public function toggleCourse($id)
    {
        if (in_array($id, $this->selectedCourses)) {
            $this->selectedCourses = array_diff($this->selectedCourses, [$id]);
        } else {
            $this->selectedCourses[] = $id;
        }
    }

    // Toggle a tag selection
    public function toggleTag($tag)
    {
        if (in_array($tag, $this->tags)) {
            $this->tags = array_diff($this->tags, [$tag]);
        } else {
            $this->tags[] = $tag;
        }
    }

    // Add custom tags
    public function addCustomTags()
    {
        if (empty($this->customTags)) {
            return;
        }

        $newTags = explode(',', $this->customTags);
        foreach ($newTags as $tag) {
            $tag = trim($tag);
            if (!empty($tag) && !in_array($tag, $this->tags)) {
                $this->tags[] = $tag;
            }
        }

        $this->customTags = '';
    }

    // Remove a specific tag
    public function removeTag($tag)
    {
        $this->tags = array_diff($this->tags, [$tag]);
    }

    // Multi-step form navigation
    public function nextStep()
    {
        if ($this->currentStep === 1) {
            $this->validateOnly('title');
            $this->validateOnly('materialType');

            if ($this->materialType === 'link') {
                $this->validateOnly('externalUrl');
            } else {
                $this->validateOnly('file');
            }
        }

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function prevStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    // Toggle preview mode
    public function togglePreview()
    {
        $this->previewMode = !$this->previewMode;
    }

    // Helper to sanitize file names
    private function sanitizeFileName($fileName)
    {
        // Get the file extension
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);

        // Create a base name without extension
        $baseName = Str::slug(pathinfo($fileName, PATHINFO_FILENAME));

        // Return the sanitized file name with the original extension
        return $baseName . '-' . time() . '.' . $extension;
    }

    // Save the material
    public function saveMaterial()
    {
        $this->validate();
        $this->saveButtonDisabled = true;

        try {
            // Create new material
            $material = new Material();
            $material->title = $this->title;
            $material->description = $this->description;
            $material->type = $this->materialType;
            $material->teacher_profile_id = $this->teacherProfileId;
            $material->is_public = $this->isPublic;
            $material->is_featured = $this->isFeatured;

            if ($this->materialType === 'link') {
                $material->external_url = $this->externalUrl;
            } else {
                // Store the file
                $fileName = $this->sanitizeFileName($this->file->getClientOriginalName());
                $filePath = $this->file->storeAs('materials/' . $this->teacherProfileId, $fileName, 'public');

                $material->file_path = $filePath;
                $material->file_name = $fileName;
                $material->file_type = $this->file->getMimeType();
                $material->file_size = $this->file->getSize();
            }

            // Save tags as JSON in material's metadata (assuming you have this column)
            if (!empty($this->tags)) {
                $material->metadata = json_encode(['tags' => $this->tags]);
            }

            $material->save();

            // Sync subjects and courses
            $material->subjects()->sync($this->selectedSubjects);
            $material->courses()->sync($this->selectedCourses);

            // Show success toast
            $this->toast(
                type: 'success',
                title: 'Material Created',
                description: 'Your teaching material has been created successfully.',
                icon: 'o-check-circle',
                css: 'alert-success'
            );

            // Redirect to materials listing
            return redirect()->route('teachers.materials.index');

        } catch (\Exception $e) {
            $this->saveButtonDisabled = false;
            $this->toast(
                type: 'error',
                title: 'Error',
                description: 'An error occurred: ' . $e->getMessage(),
                icon: 'o-x-circle',
                css: 'alert-error'
            );
        }
    }

    // Toast notification helper function
    protected function toast(
        string $type,
        string $title,
        $description = '',
        string $position = 'toast-bottom toast-end',
        string $icon = '',
        string $css = '',
        $timeout = 3000,
        $action = null
    ) {
        $actionJson = $action ? json_encode($action) : 'null';

        $this->js("
            Toaster.{$type}('{$title}', {
                description: '{$description}',
                position: '{$position}',
                icon: '{$icon}',
                css: '{$css}',
                timeout: {$timeout},
                action: {$actionJson}
            });
        ");
    }
};
?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col gap-4 mb-6 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-3xl font-bold">Create Teaching Material</h1>
                <p class="mt-1 text-base-content/70">Upload and share resources with your students</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('teachers.materials.index') }}" class="btn btn-ghost">
                    <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                    Back to Materials
                </a>
                <button
                    wire:click="togglePreview"
                    class="btn {{ $previewMode ? 'btn-ghost' : 'btn-outline' }}"
                >
                    <x-icon name="o-eye" class="w-4 h-4 mr-2" />
                    {{ $previewMode ? 'Edit Material' : 'Preview Material' }}
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <!-- Left Column: Multi-step Form -->
            <div class="lg:col-span-2 {{ $previewMode ? 'hidden lg:block' : '' }}">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <!-- Progress indicator -->
                        <div class="mb-8">
                            <div class="flex justify-between mb-2">
                                @for ($i = 1; $i <= $totalSteps; $i++)
                                    <div class="flex flex-col items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $i <= $currentStep ? 'bg-primary text-primary-content' : 'bg-base-300' }}">
                                            {{ $i }}
                                        </div>
                                        <span class="mt-1 text-xs">
                                            @if($i == 1) Basics @endif
                                            @if($i == 2) Content @endif
                                            @if($i == 3) Organization @endif
                                            @if($i == 4) Settings @endif
                                        </span>
                                    </div>

                                    @if($i < $totalSteps)
                                        <div class="flex items-center flex-1">
                                            <div class="h-0.5 w-full {{ $i < $currentStep ? 'bg-primary' : 'bg-base-300' }}"></div>
                                        </div>
                                    @endif
                                @endfor
                            </div>
                        </div>

                        <!-- Step 1: Basic Information -->
                        @if($currentStep === 1)
                            <div>
                                <h2 class="mb-6 text-2xl font-bold">Basic Information</h2>

                                <!-- Title -->
                                <div class="w-full mb-4 form-control">
                                    <label class="label">
                                        <span class="font-medium label-text">Title <span class="text-error">*</span></span>
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="title"
                                        class="input input-bordered w-full @error('title') input-error @enderror"
                                        placeholder="Enter a descriptive title"
                                    />
                                    @error('title') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
                                </div>

                                <!-- Description -->
                                <div class="w-full mb-4 form-control">
                                    <label class="label">
                                        <span class="font-medium label-text">Description</span>
                                    </label>
                                    <textarea
                                        wire:model="description"
                                        class="h-32 textarea textarea-bordered"
                                        placeholder="Describe what this material contains and how students should use it"
                                    ></textarea>
                                </div>

                                <!-- Material Type -->
                                <div class="w-full mb-4 form-control">
                                    <label class="label">
                                        <span class="font-medium label-text">Material Type <span class="text-error">*</span></span>
                                    </label>
                                    <select
                                        wire:model.live="materialType"
                                        class="select select-bordered w-full @error('materialType') select-error @enderror"
                                    >
                                        @foreach($availableTypes as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('materialType') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        <!-- Step 2: Content Upload -->
                        @if($currentStep === 2)
                            <div>
                                <h2 class="mb-6 text-2xl font-bold">Material Content</h2>

                                @if($materialType === 'link')
                                    <!-- External URL Input -->
                                    <div class="w-full form-control">
                                        <label class="label">
                                            <span class="font-medium label-text">External URL <span class="text-error">*</span></span>
                                        </label>
                                        <div class="flex">
                                            <span class="inline-flex items-center px-3 text-sm border border-r-0 rounded-l-lg bg-base-300">
                                                <x-icon name="o-link" class="w-5 h-5" />
                                            </span>
                                            <input
                                                type="url"
                                                wire:model="externalUrl"
                                                class="input input-bordered flex-1 rounded-l-none @error('externalUrl') input-error @enderror"
                                                placeholder="https://example.com/resource"
                                            />
                                        </div>
                                        @error('externalUrl') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror

                                        <div class="p-4 mt-4 rounded-lg bg-base-200">
                                            <h3 class="mb-2 font-medium">URL Tips:</h3>
                                            <ul class="pl-5 space-y-1 text-sm list-disc">
                                                <li>Ensure the URL is publicly accessible</li>
                                                <li>Check that the link doesn't require login credentials</li>
                                                <li>For YouTube videos, use the "Share" URL</li>
                                                <li>For Google Docs, use the "Share" link with viewing permissions set</li>
                                            </ul>
                                        </div>
                                    </div>
                                @else
                                    <!-- File Upload -->
                                    <div
                                        class="border-2 border-dashed rounded-lg p-6 text-center {{ $dragActive ? 'border-primary bg-primary/5' : 'border-base-300' }}"
                                        x-data="{}"
                                        x-on:dragover.prevent="$wire.setDragActive(true)"
                                        x-on:dragleave.prevent="$wire.setDragActive(false)"
                                        x-on:drop.prevent="$wire.setDragActive(false)"
                                    >
                                        @if(!$file)
                                            <div class="space-y-4">
                                                <x-icon name="o-cloud-arrow-up" class="w-12 h-12 mx-auto text-base-content/60" />

                                                <div>
                                                    <p class="text-lg">
                                                        Drag and drop your file here, or
                                                        <label for="file-upload" class="cursor-pointer text-primary">browse</label>
                                                    </p>
                                                    <p class="mt-1 text-sm text-base-content/60">
                                                        Maximum file size: 50MB
                                                    </p>
                                                </div>

                                                <input
                                                    id="file-upload"
                                                    type="file"
                                                    wire:model="file"
                                                    class="hidden"
                                                />

                                                <button
                                                    type="button"
                                                    onclick="document.getElementById('file-upload').click()"
                                                    class="btn btn-primary"
                                                >
                                                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                                    Select File
                                                </button>
                                            </div>
                                        @else
                                            <!-- File preview area -->
                                            <div class="flex items-center p-4 rounded-lg bg-base-200">
                                                <div class="p-3 mr-4 rounded-md bg-base-100">
                                                    <x-icon name="{{ $typeIcons[$materialType] ?? 'o-document' }}" class="w-8 h-8 text-primary" />
                                                </div>

                                                <div class="flex-1 min-w-0">
                                                    <h3 class="font-medium truncate">{{ $fileName }}</h3>
                                                    <p class="text-sm text-base-content/70">{{ $fileSize }} · {{ $fileType }}</p>
                                                </div>

                                                <button
                                                    wire:click="removeFile"
                                                    type="button"
                                                    class="btn btn-sm btn-ghost text-error"
                                                >
                                                    <x-icon name="o-trash" class="w-4 h-4" />
                                                </button>
                                            </div>

                                            <!-- Image preview if applicable -->
                                            @if($filePreviewUrl)
                                                <div class="mt-4 overflow-hidden border rounded-lg">
                                                    <img src="{{ $filePreviewUrl }}" alt="Preview" class="mx-auto max-h-80" />
                                                </div>
                                            @endif
                                        @endif

                                        @error('file') <span class="block mt-2 text-sm text-error">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Recommended file types -->
                                    <div class="p-4 mt-4 rounded-lg bg-base-200">
                                        <h3 class="mb-2 font-medium">Recommended File Types:</h3>
                                        <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                                            <div><span class="font-medium">Documents:</span> PDF, DOCX, TXT</div>
                                            <div><span class="font-medium">Spreadsheets:</span> XLSX, CSV</div>
                                            <div><span class="font-medium">Presentations:</span> PPTX, PPT</div>
                                            <div><span class="font-medium">Images:</span> PNG, JPG, SVG</div>
                                            <div><span class="font-medium">Audio:</span> MP3, WAV</div>
                                            <div><span class="font-medium">Video:</span> MP4</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Step 3: Organization -->
                        @if($currentStep === 3)
                            <div>
                                <h2 class="mb-6 text-2xl font-bold">Organization</h2>

                                <!-- Subjects Selection -->
                                <div class="mb-6">
                                    <label class="block mb-2 font-medium">
                                        Related Subjects
                                    </label>

                                    @if(count($subjects) > 0)
                                        <div class="grid grid-cols-2 gap-2 mb-2 sm:grid-cols-3">
                                            @foreach($subjects as $subject)
                                                <div
                                                    wire:click="toggleSubject({{ $subject->id }})"
                                                    class="border rounded-lg p-3 cursor-pointer transition-colors {{ in_array($subject->id, $selectedSubjects) ? 'bg-primary/10 border-primary' : 'hover:bg-base-200' }}"
                                                >
                                                    <div class="flex items-center">
                                                        <div class="w-4 h-4 rounded-sm mr-2 flex items-center justify-center {{ in_array($subject->id, $selectedSubjects) ? 'bg-primary text-primary-content' : 'border border-base-content/30' }}">
                                                            @if(in_array($subject->id, $selectedSubjects))
                                                                <x-icon name="o-check" class="w-3 h-3" />
                                                            @endif
                                                        </div>
                                                        <span class="truncate">{{ $subject->name }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="alert alert-info">
                                            <x-icon name="o-information-circle" class="w-5 h-5" />
                                            <span>No subjects available. Please add subjects in your profile first.</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Courses Selection -->
                                <div class="mb-6">
                                    <label class="block mb-2 font-medium">
                                        Related Courses
                                    </label>

                                    @if(count($courses) > 0)
                                        <div class="grid grid-cols-2 gap-2 mb-2 sm:grid-cols-3">
                                            @foreach($courses as $course)
                                                <div
                                                    wire:click="toggleCourse({{ $course->id }})"
                                                    class="border rounded-lg p-3 cursor-pointer transition-colors {{ in_array($course->id, $selectedCourses) ? 'bg-primary/10 border-primary' : 'hover:bg-base-200' }}"
                                                >
                                                    <div class="flex items-center">
                                                        <div class="w-4 h-4 rounded-sm mr-2 flex items-center justify-center {{ in_array($course->id, $selectedCourses) ? 'bg-primary text-primary-content' : 'border border-base-content/30' }}">
                                                            @if(in_array($course->id, $selectedCourses))
                                                                <x-icon name="o-check" class="w-3 h-3" />
                                                            @endif
                                                        </div>
                                                        <span class="truncate">{{ $course->name }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="alert alert-info">
                                            <x-icon name="o-information-circle" class="w-5 h-5" />
                                            <span>No courses available. Please create courses first.</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Tags -->
                                <div class="mb-6">
                                    <label class="block mb-2 font-medium">
                                        Tags
                                        <span class="text-xs font-normal text-base-content/70">(Optional)</span>
                                    </label>

                                    <!-- Selected tags -->
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        @foreach($tags as $tag)
                                            <div class="gap-1 badge badge-lg">
                                                {{ $tag }}
                                                <button wire:click="removeTag('{{ $tag }}')" class="btn btn-ghost btn-xs btn-circle">
                                                    <x-icon name="o-x-mark" class="w-3 h-3" />
                                                </button>
                                            </div>
                                        @endforeach

                                        @if(empty($tags))
                                            <span class="text-sm text-base-content/60">No tags selected</span>
                                        @endif
                                    </div>

                                    <!-- Predefined tags -->
                                    <div class="mb-3">
                                        <p class="mb-2 text-sm">Common tags:</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($predefinedTags as $tag)
                                                <div
                                                    wire:click="toggleTag('{{ $tag }}')"
                                                    class="badge badge-outline cursor-pointer {{ in_array($tag, $tags) ? 'badge-primary' : '' }}"
                                                >
                                                    {{ $tag }}
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Custom tags -->
                                    <div class="flex gap-2">
                                        <input
                                            type="text"
                                            wire:model="customTags"
                                            placeholder="Add custom tags (comma separated)"
                                            class="flex-1 input input-bordered"
                                        />
                                        <button
                                            wire:click="addCustomTags"
                                            class="btn btn-primary btn-square">
                                            <x-icon name="o-plus" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Step 4: Settings -->
                        @if($currentStep === 4)
                            <div>
                                <h2 class="mb-6 text-2xl font-bold">Material Settings</h2>

                                <!-- Visibility Settings -->
                                <div class="p-4 mb-6 rounded-lg bg-base-200">
                                    <h3 class="mb-4 font-medium">Visibility Options</h3>

                                    <div class="form-control">
                                        <label class="justify-start gap-3 cursor-pointer label">
                                            <input
                                                type="checkbox"
                                                wire:model="isPublic"
                                                class="toggle toggle-primary"
                                            />
                                            <div>
                                                <span class="font-medium label-text">Make Public</span>
                                                <p class="mt-1 text-xs text-base-content/70">
                                                    Public materials can be viewed by all students enrolled in your courses
                                                </p>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="mt-4 form-control">
                                        <label class="justify-start gap-3 cursor-pointer label">
                                            <input
                                                type="checkbox"
                                                wire:model="isFeatured"
                                                class="toggle toggle-primary"
                                            />
                                            <div>
                                                <span class="font-medium label-text">Feature Material</span>
                                                <p class="mt-1 text-xs text-base-content/70">
                                                    Featured materials appear at the top of your materials list and may be highlighted on the student dashboard
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Summary -->
                                <div class="p-4 rounded-lg bg-base-200">
                                    <h3 class="mb-3 font-medium">Material Summary</h3>

                                    <div class="space-y-3">
                                        <div class="grid grid-cols-3 gap-2 text-sm">
                                            <div class="text-base-content/70">Title:</div>
                                            <div class="col-span-2 font-medium">{{ $title ?: 'Not specified' }}</div>

                                            <div class="text-base-content/70">Type:</div>
                                            <div class="col-span-2">{{ $availableTypes[$materialType] }}</div>

                                            <div class="text-base-content/70">Content:</div>
                                            <div class="col-span-2">
                                                @if($materialType === 'link')
                                                    <span class="block truncate text-primary">{{ $externalUrl ?: 'No URL provided' }}</span>
                                                @else
                                                    @if($file)
                                                        <span>{{ $fileName }} ({{ $fileSize }})</span>
                                                    @else
                                                        <span class="text-error">No file uploaded</span>
                                                    @endif
                                                @endif
                                            </div>

                                            <div class="text-base-content/70">Subjects:</div>
                                            <div class="col-span-2">
                                                @if(count($selectedSubjects) > 0)
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach($selectedSubjects as $subjectId)
                                                            @php $subject = $subjects->firstWhere('id', $subjectId); @endphp
                                                            @if($subject)
                                                                <span class="badge badge-sm">{{ $subject->name }}</span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-base-content/50">None selected</span>
                                                @endif
                                            </div>

                                            <div class="text-base-content/70">Courses:</div>
                                            <div class="col-span-2">
                                                @if(count($selectedCourses) > 0)
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach($selectedCourses as $courseId)
                                                            @php $course = $courses->firstWhere('id', $courseId); @endphp
                                                            @if($course)
                                                                <span class="badge badge-sm">{{ $course->name }}</span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-base-content/50">None selected</span>
                                                @endif
                                            </div>

                                            <div class="text-base-content/70">Visibility:</div>
                                            <div class="col-span-2">
                                                {{ $isPublic ? 'Public' : 'Private' }}
                                                @if($isFeatured)
                                                    <span class="ml-2 badge badge-warning badge-sm">Featured</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Form Navigation Buttons -->
                        <div class="flex justify-between mt-8">
                            @if($currentStep > 1)
                                <button
                                    wire:click="prevStep"
                                    type="button"
                                    class="btn btn-outline"
                                >
                                    <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                    Previous
                                </button>
                            @else
                                <div></div>
                            @endif

                            <div>
                                @if($currentStep < $totalSteps)
                                    <button
                                        wire:click="nextStep"
                                        type="button"
                                        class="btn btn-primary"
                                    >
                                        Next
                                        <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                                    </button>
                                @else
                                    <button
                                        wire:click="saveMaterial"
                                        type="button"
                                        class="btn btn-success"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-75"
                                        {{ $saveButtonDisabled ? 'disabled' : '' }}
                                    >
                                        <x-icon name="o-check" class="w-4 h-4 mr-2" wire:loading.remove />
                                        <span wire:loading.remove>Create Material</span>
                                        <span wire:loading wire:target="saveMaterial">
                                            <svg class="w-5 h-5 mr-2 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Creating...
                                        </span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Material Preview -->
            <div class="{{ $previewMode ? 'lg:col-span-3' : 'hidden lg:block' }}">
                <div class="h-full shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 text-2xl card-title">Material Preview</h2>

                        @if(empty($title))
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <x-icon name="o-document-magnifying-glass" class="w-16 h-16 mb-4 text-base-content/30" />
                                <h3 class="text-lg font-medium">Complete the form to preview your material</h3>
                                <p class="max-w-md mt-2 text-base-content/60">
                                    As you fill out the form, you'll see a preview of how your material will appear to students.
                                </p>
                            </div>
                        @else
                            <div class="space-y-4">
                                <!-- Material Header -->
                                <div class="pb-4 border-b">
                                    <div class="flex items-start gap-4">
                                        <div class="p-3 rounded-lg bg-primary/10">
                                            <x-icon name="{{ $typeIcons[$materialType] ?? 'o-document' }}" class="w-10 h-10 text-primary" />
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-xl font-bold">{{ $title }}</h3>
                                            <div class="flex flex-wrap gap-2 mt-2">
                                                <span class="badge">{{ $availableTypes[$materialType] }}</span>
                                                @if($isPublic)
                                                    <span class="badge badge-success">Public</span>
                                                @else
                                                    <span class="badge badge-ghost">Private</span>
                                                @endif

                                                @if($isFeatured)
                                                    <span class="badge badge-warning">Featured</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Material Description -->
                                @if($description)
                                    <div>
                                        <h4 class="mb-2 font-medium">Description</h4>
                                        <p class="whitespace-pre-line text-base-content/80">{{ $description }}</p>
                                    </div>
                                @endif

                                <!-- Material Content Preview -->
                                <div>
                                    <h4 class="mb-2 font-medium">Content</h4>

                                    @if($materialType === 'link')
                                        <div class="p-4 rounded-lg bg-base-200">
                                            <div class="flex items-center">
                                                <x-icon name="o-link" class="w-5 h-5 mr-3 text-primary" />
                                                <a href="{{ $externalUrl }}" target="_blank" class="truncate link link-primary">
                                                    {{ $externalUrl ?: 'https://example.com/your-resource-link' }}
                                                </a>
                                            </div>
                                        </div>
                                    @else
                                        <div class="p-4 rounded-lg bg-base-200">
                                            @if($file)
                                                <div class="flex items-center">
                                                    <x-icon name="{{ $typeIcons[$materialType] ?? 'o-document' }}" class="w-5 h-5 mr-3 text-primary" />
                                                    <span class="truncate">{{ $fileName }}</span>
                                                    <span class="ml-2 text-sm text-base-content/60">{{ $fileSize }}</span>
                                                </div>

                                                @if($filePreviewUrl)
                                                    <div class="mt-3 overflow-hidden bg-white border rounded-lg">
                                                        <img src="{{ $filePreviewUrl }}" alt="Preview" class="mx-auto max-h-80" />
                                                    </div>
                                                @endif
                                            @else
                                                <div class="py-6 text-center">
                                                    <x-icon name="o-document" class="w-10 h-10 mx-auto mb-2 text-base-content/30" />
                                                    <p class="text-base-content/60">No file uploaded yet</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <!-- Tags, Subjects, Courses Display -->
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <h4 class="mb-2 font-medium">Subjects</h4>
                                        @if(count($selectedSubjects) > 0)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($selectedSubjects as $subjectId)
                                                    @php $subject = $subjects->firstWhere('id', $subjectId); @endphp
                                                    @if($subject)
                                                        <div class="badge badge-outline">{{ $subject->name }}</div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-sm text-base-content/60">No subjects assigned</p>
                                        @endif
                                    </div>

                                    <div>
                                        <h4 class="mb-2 font-medium">Courses</h4>
                                        @if(count($selectedCourses) > 0)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($selectedCourses as $courseId)
                                                    @php $course = $courses->firstWhere('id', $courseId); @endphp
                                                    @if($course)
                                                        <div class="badge badge-outline">{{ $course->name }}</div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-sm text-base-content/60">No courses assigned</p>
                                        @endif
                                    </div>
                                </div>

                                <!-- Tags -->
                                <div>
                                    <h4 class="mb-2 font-medium">Tags</h4>
                                    @if(count($tags) > 0)
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($tags as $tag)
                                                <div class="badge">{{ $tag }}</div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm text-base-content/60">No tags added</p>
                                    @endif
                                </div>

                                <!-- Teacher Information -->
                                <div class="pt-4 mt-4 border-t">
                                    <div class="flex items-center">
                                        <div class="mr-3 avatar">
                                            <div class="w-10 h-10 rounded-full bg-base-300">
                                                @if($teacherProfile && $teacherProfile->avatar_url)
                                                    <img src="{{ $teacherProfile->avatar_url }}" alt="Teacher" />
                                                @else
                                                    <div class="flex items-center justify-center w-full h-full text-lg font-bold bg-primary text-primary-content">
                                                        {{ strtoupper(substr($teacher->name ?? 'T', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <div>
                                            <p class="font-medium">{{ $teacher->name ?? 'Teacher Name' }}</p>
                                            <p class="text-sm text-base-content/60">
                                                Uploaded on {{ date('M d, Y') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
