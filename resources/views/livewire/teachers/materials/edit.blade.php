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

    // Material model
    public $material;
    public $materialId;

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
    public $isEditing = true;
    public $activeSection = 'basic'; // basic, content, organization, settings
    public $dragActive = false;
    public $saveButtonDisabled = false;
    public $showDeleteModal = false;
    public $isDirty = false;
    public $showUnsavedChangesModal = false;
    public $redirectAfterSave = false;

    // File preview data
    public $originalFileUrl = null;
    public $originalFileName = null;
    public $originalFileSize = null;
    $filePreviewUrl = null;
    public $fileSize = null;
    public $fileName = null;
    public $fileType = null;
    public $replaceFile = false;

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

    // Editor configuration
    public $editorConfig = [
        'placeholder' => 'Enter a detailed description of this material...',
        'toolbar' => [
            'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'outdent', 'indent', '|', 'undo', 'redo'
        ],
        'heading' => [
            'options' => [
                ['model' => 'paragraph', 'title' => 'Paragraph', 'class' => 'ck-heading_paragraph'],
                ['model' => 'heading2', 'view' => 'h2', 'title' => 'Heading 1', 'class' => 'ck-heading_heading2'],
                ['model' => 'heading3', 'view' => 'h3', 'title' => 'Heading 2', 'class' => 'ck-heading_heading3'],
            ]
        ]
    ];

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
        } elseif ($this->replaceFile) {
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

    public function mount($material)
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        if (!$this->teacherProfile) {
            return redirect()->route('teachers.profile-setup')
                ->with('error', 'Please complete your teacher profile before editing materials.');
        }

        $this->teacherProfileId = $this->teacherProfile->id;

        // Load material with relationships
        $this->material = Material::with(['subjects', 'courses'])
            ->where('id', $material)
            ->where('teacher_profile_id', $this->teacherProfileId)
            ->firstOrFail();

        $this->materialId = $this->material->id;

        // Load subjects and courses
        $this->loadSubjects();
        $this->loadCourses();

        // Populate form fields from material
        $this->populateFields();
    }

    // Initialize form with existing data
    private function populateFields()
    {
        $this->title = $this->material->title;
        $this->description = $this->material->description;
        $this->materialType = $this->material->type;
        $this->externalUrl = $this->material->external_url;
        $this->isPublic = $this->material->is_public;
        $this->isFeatured = $this->material->is_featured;

        // Set original file data
        if ($this->material->file_path) {
            $this->originalFileUrl = Storage::url($this->material->file_path);
            $this->originalFileName = $this->material->file_name;
            $this->originalFileSize = $this->material->formatted_file_size;
        }

        // Load selected subjects and courses
        $this->selectedSubjects = $this->material->subjects->pluck('id')->toArray();
        $this->selectedCourses = $this->material->courses->pluck('id')->toArray();

        // Load tags from metadata
        if ($this->material->metadata) {
            try {
                $metadata = json_decode($this->material->metadata, true);
                if (isset($metadata['tags'])) {
                    $this->tags = $metadata['tags'];
                }
            } catch (\Exception $e) {
                // Handle JSON parsing error
            }
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

    // Toggle editing mode
    public function toggleEditing()
    {
        $this->isEditing = !$this->isEditing;
    }

    // Set active section
    public function setActiveSection($section)
    {
        $this->activeSection = $section;
    }

    // Handle file upload and preview
    public function updatedFile()
    {
        $this->replaceFile = true;

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

            $this->markAsDirty();
        }
    }

    // Handle model changes to track if form is dirty
    public function updated($field)
    {
        if ($field !== 'isEditing' && $field !== 'activeSection' && $field !== 'dragActive' && $field !== 'showDeleteModal' && $field !== 'showUnsavedChangesModal') {
            $this->markAsDirty();
        }
    }

    // Mark form as having unsaved changes
    public function markAsDirty()
    {
        $this->isDirty = true;
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

    // Remove new file and reset to original
    public function removeFile()
    {
        $this->file = null;
        $this->filePreviewUrl = null;
        $this->fileName = null;
        $this->fileSize = null;
        $this->fileType = null;
        $this->replaceFile = false;

        $this->markAsDirty();
    }

    // Toggle a subject selection
    public function toggleSubject($id)
    {
        if (in_array($id, $this->selectedSubjects)) {
            $this->selectedSubjects = array_diff($this->selectedSubjects, [$id]);
        } else {
            $this->selectedSubjects[] = $id;
        }

        $this->markAsDirty();
    }

    // Toggle a course selection
    public function toggleCourse($id)
    {
        if (in_array($id, $this->selectedCourses)) {
            $this->selectedCourses = array_diff($this->selectedCourses, [$id]);
        } else {
            $this->selectedCourses[] = $id;
        }

        $this->markAsDirty();
    }

    // Toggle a tag selection
    public function toggleTag($tag)
    {
        if (in_array($tag, $this->tags)) {
            $this->tags = array_diff($this->tags, [$tag]);
        } else {
            $this->tags[] = $tag;
        }

        $this->markAsDirty();
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
        $this->markAsDirty();
    }

    // Remove a specific tag
    public function removeTag($tag)
    {
        $this->tags = array_diff($this->tags, [$tag]);
        $this->markAsDirty();
    }

    // Confirm delete modal
    public function confirmDelete()
    {
        $this->showDeleteModal = true;
    }

    // Delete material
    public function deleteMaterial()
    {
        try {
            // Delete the file if it exists
            if ($this->material->file_path) {
                Storage::delete($this->material->file_path);
            }

            // Delete the material
            $this->material->delete();

            $this->toast(
                type: 'success',
                title: 'Material Deleted',
                description: 'The material has been deleted successfully.',
                icon: 'o-check-circle',
                css: 'alert-success'
            );

            return redirect()->route('teachers.materials');

        } catch (\Exception $e) {
            $this->showDeleteModal = false;

            $this->toast(
                type: 'error',
                title: 'Delete Failed',
                description: 'An error occurred: ' . $e->getMessage(),
                icon: 'o-x-circle',
                css: 'alert-error'
            );
        }
    }

    // Check for unsaved changes before navigating away
    public function checkUnsavedChanges($action = 'cancel')
    {
        if ($this->isDirty) {
            $this->redirectAfterSave = $action === 'save';
            $this->showUnsavedChangesModal = true;
            return false;
        }

        if ($action === 'cancel') {
            return redirect()->route('teachers.materials.show', $this->materialId);
        }

        return true;
    }

    // Proceed without saving
    public function proceedWithoutSaving()
    {
        $this->showUnsavedChangesModal = false;
        return redirect()->route('teachers.materials.show', $this->materialId);
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

    // Save the updated material
    public function updateMaterial()
    {
        $this->validate();
        $this->saveButtonDisabled = true;

        try {
            // Update material attributes
            $this->material->title = $this->title;
            $this->material->description = $this->description;
            $this->material->type = $this->materialType;
            $this->material->is_public = $this->isPublic;
            $this->material->is_featured = $this->isFeatured;

            if ($this->materialType === 'link') {
                $this->material->external_url = $this->externalUrl;

                // Clear file fields if switching to link
                if ($this->material->file_path) {
                    Storage::delete($this->material->file_path);
                    $this->material->file_path = null;
                    $this->material->file_name = null;
                    $this->material->file_type = null;
                    $this->material->file_size = null;
                }
            } elseif ($this->replaceFile && $this->file) {
                // If there's a new file uploaded
                $fileName = $this->sanitizeFileName($this->file->getClientOriginalName());
                $filePath = $this->file->storeAs('materials/' . $this->teacherProfileId, $fileName, 'public');

                // Delete the old file if it exists
                if ($this->material->file_path) {
                    Storage::delete($this->material->file_path);
                }

                $this->material->file_path = $filePath;
                $this->material->file_name = $fileName;
                $this->material->file_type = $this->file->getMimeType();
                $this->material->file_size = $this->file->getSize();
                $this->material->external_url = null;
            }

            // Save tags as JSON in material's metadata
            if (!empty($this->tags)) {
                $metadata = json_decode($this->material->metadata ?? '{}', true) ?: [];
                $metadata['tags'] = $this->tags;
                $this->material->metadata = json_encode($metadata);
            } else if ($this->material->metadata) {
                // Remove tags from metadata if it exists
                $metadata = json_decode($this->material->metadata, true) ?: [];
                if (isset($metadata['tags'])) {
                    unset($metadata['tags']);
                    $this->material->metadata = !empty($metadata) ? json_encode($metadata) : null;
                }
            }

            $this->material->save();

            // Sync subjects and courses
            $this->material->subjects()->sync($this->selectedSubjects);
            $this->material->courses()->sync($this->selectedCourses);

            // Reset state
            $this->isDirty = false;
            $this->saveButtonDisabled = false;

            // Show success toast
            $this->toast(
                type: 'success',
                title: 'Material Updated',
                description: 'Your teaching material has been updated successfully.',
                icon: 'o-check-circle',
                css: 'alert-success'
            );

            if ($this->redirectAfterSave) {
                return redirect()->route('teachers.materials');
            } else {
                // Refresh data
                $this->material = Material::with(['subjects', 'courses'])->find($this->materialId);
                $this->populateFields();
            }

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

    public function cancelEdit()
    {
        return $this->checkUnsavedChanges('cancel');
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

<div class="min-h-screen p-6 bg-base-200"
    x-data="{
        confirmLeave: function(event) {
            if (@js($isDirty)) {
                event.preventDefault();
                event.returnValue = '';
                return '';
            }
        }
    }"
    x-init="window.addEventListener('beforeunload', confirmLeave)"
    x-on:unload="window.removeEventListener('beforeunload', confirmLeave)"
>
    <div class="mx-auto max-w-7xl">
        <!-- Header with Breadcrumbs -->
        <div class="mb-6">
            <div class="mb-2 text-sm breadcrumbs">
                <ul>
                    <li><a href="{{ route('teachers.dashboard') }}">Dashboard</a></li>
                    <li><a href="{{ route('teachers.materials') }}">Materials</a></li>
                    <li><a href="{{ route('teachers.materials.show', $materialId) }}">{{ Str::limit($material->title, 20) }}</a></li>
                    <li>Edit</li>
                </ul>
            </div>

            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Edit Material</h1>
                    <p class="mt-1 text-base-content/70">Update details and content for your teaching material</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        wire:click="cancelEdit"
                        class="btn btn-outline"
                    >
                        <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                        Cancel
                    </button>

                    <button
                        wire:click="updateMaterial"
                        class="btn btn-primary"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-75"
                        {{ $saveButtonDisabled ? 'disabled' : '' }}
                    >
                        <x-icon name="o-check" class="w-4 h-4 mr-2" wire:loading.remove wire:target="updateMaterial" />
                        <span wire:loading.remove wire:target="updateMaterial">Save Changes</span>
                        <span wire:loading wire:target="updateMaterial">
                            <svg class="w-5 h-5 mr-2 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Saving...
                        </span>
                    </button>

                    <div class="dropdown dropdown-end">
                        <label tabindex="0" class="btn btn-ghost">
                            <x-icon name="o-ellipsis-vertical" class="w-5 h-5" />
                        </label>
                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                            <li>
                                <a href="{{ route('teachers.materials.show', $materialId) }}">
                                    <x-icon name="o-eye" class="w-4 h-4" />
                                    View Material
                                </a>
                            </li>
                            <li>
                                <button wire:click="confirmDelete" class="text-error">
                                    <x-icon name="o-trash" class="w-4 h-4" />
                                    Delete Material
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            @if($isDirty)
                <div class="mt-4 shadow-lg alert alert-warning">
                    <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
                    <span>You have unsaved changes. Be sure to save before leaving this page.</span>
                </div>
            @endif
        </div>

        <!-- Main Content Area -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Left Sidebar: Navigation -->
            <div class="lg:col-span-1">
                <div class="sticky shadow-xl card bg-base-100 top-6">
                    <div class="p-3 card-body">
                        <h3 class="mb-3 text-lg font-medium">Sections</h3>

                        <ul class="w-full menu bg-base-100 rounded-box">
                            <li>
                                <button
                                    wire:click="setActiveSection('basic')"
                                    class="font-medium {{ $activeSection === 'basic' ? 'active' : '' }}"
                                >
                                    <x-icon name="o-information-circle" class="w-5 h-5" />
                                    Basic Information
                                </button>
                            </li>
                            <li>
                                <button
                                    wire:click="setActiveSection('content')"
                                    class="font-medium {{ $activeSection === 'content' ? 'active' : '' }}"
                                >
                                    <x-icon name="o-document-text" class="w-5 h-5" />
                                    Content
                                </button>
                            </li>
                            <li>
                                <button
                                    wire:click="setActiveSection('organization')"
                                    class="font-medium {{ $activeSection === 'organization' ? 'active' : '' }}"
                                >
                                    <x-icon name="o-folder" class="w-5 h-5" />
                                    Organization
                                </button>
                            </li>
                            <li>
                                <button
                                    wire:click="setActiveSection('settings')"
                                    class="font-medium {{ $activeSection === 'settings' ? 'active' : '' }}"
                                >
                                    <x-icon name="o-cog-6-tooth" class="w-5 h-5" />
                                    Settings
                                </button>
                            </li>
                        </ul>

                        <div class="my-2 divider"></div>

                        <button
                            wire:click="confirmDelete"
                            class="w-full btn btn-error btn-sm btn-outline"
                        >
                            <x-icon name="o-trash" class="w-4 h-4 mr-2" />
                            Delete Material
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Edit Form -->
            <div class="lg:col-span-3">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <form wire:submit.prevent="updateMaterial">
                            <!-- Basic Information Section -->
                            <div class="{{ $activeSection === 'basic' ? 'block' : 'hidden' }}">
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

                                    <div wire:ignore>
                                        <textarea
                                            x-data="{
                                                editor: null,
                                                init() {
                                                    ClassicEditor
                                                        .create($refs.editor, @js($editorConfig))
                                                        .then(editor => {
                                                            this.editor = editor;
                                                            editor.model.document.on('change:data', () => {
                                                                @this.set('description', editor.getData());
                                                                @this.markAsDirty();
                                                            });

                                                            if (@js($description)) {
                                                                editor.setData(@js($description));
                                                            }
                                                        })
                                                        .catch(error => {
                                                            console.error(error);
                                                        });
                                                }
                                            }"
                                            x-ref="editor"
                                            wire:key="ck-editor-description"
                                        ></textarea>
                                    </div>
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

                            <!-- Content Section -->
                            <div class="{{ $activeSection === 'content' ? 'block' : 'hidden' }}">
                                <h2 class="mb-6 text-2xl font-bold">Material Content</h2>

                                @if($materialType === 'link')
                                    <!-- External URL Input -->
                                    <div class="w-full form-control">
                                        <label class="label">
                                            <span class="font-medium label-text">External URL <span class="text-error">*</span></span>
                                        </label>
                                        <div class="flex">
                                            <span class="inline-flex items-center px-3 text-sm border bg-base-300 border-r-0rounded-l-lg">
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
                                    <!-- File Upload Section -->
                                    <div class="mb-6">
                                        <label class="block mb-2 font-medium">Current File</label>

                                        @if($originalFileName)
                                            <div class="flex items-center p-4 mb-4 rounded-lg bg-base-200">
                                                <div class="p-3 mr-4 rounded-md bg-base-100">
                                                    <x-icon name="{{ $typeIcons[$materialType] ?? 'o-document' }}" class="w-8 h-8 text-primary" />
                                                </div>

                                                <div class="flex-1 min-w-0">
                                                    <h3 class="font-medium truncate">{{ $originalFileName }}</h3>
                                                    <p class="text-sm text-base-content/70">{{ $originalFileSize }}</p>
                                                </div>

                                                <a
                                                    href="{{ $originalFileUrl }}"
                                                    target="_blank"
                                                    class="btn btn-sm btn-ghost"
                                                >
                                                    <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4" />
                                                </a>
                                            </div>
                                        @else
                                            <div class="p-4 mb-4 text-center rounded-lg bg-base-200">
                                                <p class="text-base-content/70">No file currently associated with this material.</p>
                                            </div>
                                        @endif

                                        <!-- Replace file toggle -->
                                        <div class="mb-4 form-control">
                                            <label class="justify-start gap-3 cursor-pointer label">
                                                <input
                                                    type="checkbox"
                                                    wire:model.live="replaceFile"
                                                    class="checkbox checkbox-primary"
                                                />
                                                <span class="label-text">Replace with new file</span>
                                            </label>
                                        </div>

                                        @if($replaceFile)
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
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Organization Section -->
                            <div class="{{ $activeSection === 'organization' ? 'block' : 'hidden' }}">
                                <h2 class="mb-6 text-2xl font-bold">Organization</h2>

                                <!-- Subjects Selection -->
                                <div class="mb-6">
                                    <label class="block mb-2 font-medium">
                                        Related Subjects
                                    </label>

                                    @if(count($subjects) > 0)
                                        <div class="grid grid-cols-2 gap-2 mb-2 md:grid-cols-3">
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
                                        <div class="grid grid-cols-2 gap-2 mb-2 md:grid-cols-3">
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
                                                <button
                                                    wire:click.prevent="removeTag('{{ $tag }}')"
                                                    class="btn btn-ghost btn-xs btn-circle"
                                                >
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
                                                    wire:click.prevent="toggleTag('{{ $tag }}')"
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
                                            wire:click.prevent="addCustomTags"
                                            class="btn btn-primary btn-square"
                                        >
                                            <x-icon name="o-plus" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Settings Section -->
                            <div class="{{ $activeSection === 'settings' ? 'block' : 'hidden' }}">
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

                                <!-- Advanced Settings (placeholder) -->
                                <div class="p-4 mb-6 rounded-lg bg-base-200">
                                    <h3 class="mb-4 font-medium">Advanced Settings</h3>

                                    <div class="mb-4 form-control">
                                        <label class="justify-start gap-3 cursor-pointer label">
                                            <input type="checkbox" class="toggle toggle-primary" />
                                            <div>
                                                <span class="font-medium label-text">Allow Downloads</span>
                                                <p class="mt-1 text-xs text-base-content/70">
                                                    Students can download this material
                                                </p>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="form-control">
                                        <label class="justify-start gap-3 cursor-pointer label">
                                            <input type="checkbox" class="toggle toggle-primary" />
                                            <div>
                                                <span class="font-medium label-text">Track Engagement</span>
                                                <p class="mt-1 text-xs text-base-content/70">
                                                    Monitor student interactions with this material
                                                </p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Analytics Section (placeholder) -->
                                <div class="p-4 rounded-lg bg-base-200">
                                    <h3 class="mb-4 font-medium">Analytics</h3>
                                    <p class="text-sm text-base-content/70">
                                        This material has been viewed {{ rand(10, 100) }} times and downloaded {{ rand(5, 30) }} times.
                                    </p>
                                </div>
                            </div>

                            <!-- Form Action Buttons -->
                            <div class="flex justify-between mt-8">
                                <button
                                    type="button"
                                    wire:click="cancelEdit"
                                    class="btn btn-outline"
                                >
                                    Cancel
                                </button>

                                <div class="flex gap-2">
                                    @if($activeSection !== 'basic')
                                        <button
                                            type="button"
                                            wire:click="setActiveSection('{{ $activeSection === 'content' ? 'basic' : ($activeSection === 'organization' ? 'content' : 'organization') }}')"
                                            class="btn btn-ghost"
                                        >
                                            <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                            Previous
                                        </button>
                                    @endif

                                    @if($activeSection !== 'settings')
                                        <button
                                            type="button"
                                            wire:click="setActiveSection('{{ $activeSection === 'basic' ? 'content' : ($activeSection === 'content' ? 'organization' : 'settings') }}')"
                                            class="btn btn-primary"
                                        >
                                            Next
                                            <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                                        </button>
                                    @else
                                        <button
                                            type="submit"
                                            class="btn btn-success"
                                            wire:loading.attr="disabled"
                                            wire:loading.class="opacity-75"
                                            {{ $saveButtonDisabled ? 'disabled' : '' }}
                                        >
                                            <x-icon name="o-check" class="w-4 h-4 mr-2" wire:loading.remove wire:target="updateMaterial" />
                                            <span wire:loading.remove wire:target="updateMaterial">Save Material</span>
                                            <span wire:loading wire:target="updateMaterial">
                                                <svg class="w-5 h-5 mr-2 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Saving...
                                            </span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal {{ $showDeleteModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold text-error">Delete Material</h3>
            <p class="py-4">Are you sure you want to delete "{{ $material->title }}"? This action cannot be undone.</p>
            <div class="modal-action">
                <button class="btn" wire:click="$set('showDeleteModal', false)">Cancel</button>
                <button class="btn btn-error" wire:click="deleteMaterial">Delete</button>
            </div>
        </div>
    </div>

    <!-- Unsaved Changes Modal -->
    <div class="modal {{ $showUnsavedChangesModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Unsaved Changes</h3>
            <p class="py-4">You have unsaved changes. What would you like to do?</p>
            <div class="modal-action">
                <button class="btn btn-outline" wire:click="proceedWithoutSaving">Discard Changes</button>
                <button class="btn btn-primary" wire:click="updateMaterial">Save Changes</button>
            </div>
        </div>
    </div>
</div>
