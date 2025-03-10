<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Material;
use App\Models\Subject;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

new class extends Component {
    use WithPagination, WithFileUploads;

    // User and profile
    public $teacher;
    public $teacherProfile;
    public $teacherProfileId = null;

    // Filters
    public $search = '';
    public $typeFilter = '';
    public $subjectFilter = '';
    public $courseFilter = '';
    public $visibilityFilter = '';

    // Material form
    public $showMaterialModal = false;
    public $materialId = null;
    public $title = '';
    public $description = '';
    public $materialType = 'document';
    public $file;
    public $externalUrl = '';
    public $isPublic = false;
    public $isFeatured = false;
    public $selectedSubjects = [];
    public $selectedCourses = [];

    // Delete confirmation
    public $showDeleteModal = false;
    public $materialToDelete = null;

    // Material viewing
    public $showViewModal = false;
    public $viewingMaterial = null;

    // Error state
    public $hasError = false;
    public $errorMessage = '';

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

    public $subjects = [];
    public $courses = [];

    // Set up rules for validation
    protected function rules()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'materialType' => 'required|string',
            'isPublic' => 'boolean',
            'isFeatured' => 'boolean',
            'selectedSubjects' => 'array',
            'selectedCourses' => 'array',
        ];

        if ($this->materialType === 'link') {
            $rules['externalUrl'] = 'required|url|max:255';
        } else {
            $rules['file'] = $this->materialId ? 'nullable|file|max:51200' : 'required|file|max:51200'; // 50MB max
        }

        return $rules;
    }

    public function mount()
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        if ($this->teacherProfile) {
            $this->teacherProfileId = $this->teacherProfile->id;

            // Check if the materials table has the needed column
            if (!$this->checkMaterialsTableColumn()) {
                $this->hasError = true;
                $this->errorMessage = "The materials table is missing required columns. Please run migrations.";
                return;
            }

            // Load subjects and courses
            $this->loadSubjects();
            $this->loadCourses();
        } else {
            $this->hasError = true;
            $this->errorMessage = "Teacher profile not found. Please complete your profile setup first.";
        }
    }

    protected function checkMaterialsTableColumn()
    {
        // Check if the materials table has the teacher_profile_id column
        return Schema::hasTable('materials') && Schema::hasColumn('materials', 'teacher_profile_id');
    }

    public function with(): array
    {
        if ($this->hasError) {
            return [
                'materials' => collect(),
                'hasError' => $this->hasError,
                'errorMessage' => $this->errorMessage
            ];
        }

        if (!$this->teacherProfileId) {
            return [
                'materials' => collect(),
                'hasError' => true,
                'errorMessage' => 'Teacher profile not properly loaded.'
            ];
        }

        try {
            $query = Material::query();

            if (Schema::hasColumn('materials', 'teacher_profile_id')) {
                $query->where('teacher_profile_id', $this->teacherProfileId);
            } else {
                // Fallback if the column doesn't exist yet
                return [
                    'materials' => collect(),
                    'hasError' => true,
                    'errorMessage' => 'The materials table structure is not complete. Please run migrations.'
                ];
            }

            $query->with(['subjects', 'courses']);

            if ($this->search) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            }

            if ($this->typeFilter) {
                $query->where('type', $this->typeFilter);
            }

            if ($this->subjectFilter && method_exists(Material::class, 'scopeForSubject')) {
                $query->forSubject($this->subjectFilter);
            }

            if ($this->courseFilter && method_exists(Material::class, 'scopeForCourse')) {
                $query->forCourse($this->courseFilter);
            }

            if ($this->visibilityFilter === 'public') {
                $query->where('is_public', true);
            } elseif ($this->visibilityFilter === 'private') {
                $query->where('is_public', false);
            } elseif ($this->visibilityFilter === 'featured') {
                $query->where('is_featured', true);
            }

            $materials = $query->latest()->paginate(12);

            return [
                'materials' => $materials,
                'hasError' => false,
                'errorMessage' => ''
            ];
        } catch (\Exception $e) {
            return [
                'materials' => collect(),
                'hasError' => true,
                'errorMessage' => 'Error loading materials: ' . $e->getMessage()
            ];
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

    // Open modal for creating a new material
    public function openCreateModal()
    {
        if ($this->hasError) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: $this->errorMessage,
                icon: 'o-x-circle',
                css: 'alert-error'
            );
            return;
        }

        $this->resetValidation();
        $this->resetForm();
        $this->showMaterialModal = true;
    }

    // Open modal for editing an existing material
    public function openEditModal($id)
    {
        if ($this->hasError) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: $this->errorMessage,
                icon: 'o-x-circle',
                css: 'alert-error'
            );
            return;
        }

        $this->resetValidation();
        $this->resetForm();

        try {
            $material = Material::findOrFail($id);
            $this->materialId = $material->id;
            $this->title = $material->title;
            $this->description = $material->description;
            $this->materialType = $material->type;
            $this->externalUrl = $material->external_url;
            $this->isPublic = $material->is_public;
            $this->isFeatured = $material->is_featured;
            $this->selectedSubjects = $material->subjects->pluck('id')->toArray();
            $this->selectedCourses = $material->courses->pluck('id')->toArray();

            $this->showMaterialModal = true;
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: 'Failed to load material: ' . $e->getMessage(),
                icon: 'o-x-circle',
                css: 'alert-error'
            );
        }
    }

    // Open modal for confirming deletion
    public function confirmDelete($id)
    {
        if ($this->hasError) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: $this->errorMessage,
                icon: 'o-x-circle',
                css: 'alert-error'
            );
            return;
        }

        $this->materialToDelete = $id;
        $this->showDeleteModal = true;
    }

    // Open modal for viewing material details
    public function viewMaterial($id)
    {
        if ($this->hasError) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: $this->errorMessage,
                icon: 'o-x-circle',
                css: 'alert-error'
            );
            return;
        }

        try {
            $this->viewingMaterial = Material::with(['subjects', 'courses'])->findOrFail($id);
            $this->showViewModal = true;
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: 'Failed to load material: ' . $e->getMessage(),
                icon: 'o-x-circle',
                css: 'alert-error'
            );
        }
    }

    // Reset the form fields
    private function resetForm()
    {
        $this->materialId = null;
        $this->title = '';
        $this->description = '';
        $this->materialType = 'document';
        $this->file = null;
        $this->externalUrl = '';
        $this->isPublic = false;
        $this->isFeatured = false;
        $this->selectedSubjects = [];
        $this->selectedCourses = [];
    }

    // Save the material (create or update)
    public function saveMaterial()
    {
        if ($this->hasError) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: $this->errorMessage,
                icon: 'o-x-circle',
                css: 'alert-error'
            );
            return;
        }

        $this->validate();

        try {
            $isNew = !$this->materialId;

            // Create or update the material
            $material = $isNew ? new Material() : Material::findOrFail($this->materialId);

            $material->title = $this->title;
            $material->description = $this->description;
            $material->type = $this->materialType;
            $material->teacher_profile_id = $this->teacherProfileId;
            $material->is_public = $this->isPublic;
            $material->is_featured = $this->isFeatured;

            if ($this->materialType === 'link') {
                $material->external_url = $this->externalUrl;
                // Clear file fields if switching to link
                $material->file_path = null;
                $material->file_name = null;
                $material->file_type = null;
                $material->file_size = null;
            } elseif ($this->file) {
                // If there's a new file uploaded
                $fileName = $this->sanitizeFileName($this->file->getClientOriginalName());
                $filePath = $this->file->storeAs('materials/' . $this->teacherProfileId, $fileName, 'public');

                // Delete the old file if it exists
                if (!$isNew && $material->file_path) {
                    Storage::delete($material->file_path);
                }

                $material->file_path = $filePath;
                $material->file_name = $fileName;
                $material->file_type = $this->file->getMimeType();
                $material->file_size = $this->file->getSize();
                $material->external_url = null;
            }

            $material->save();

            // Sync subjects and courses
            $material->subjects()->sync($this->selectedSubjects);
            $material->courses()->sync($this->selectedCourses);

            $this->showMaterialModal = false;

            $this->dispatch('refresh');

            // Show success toast
            $this->toast(
                type: 'success',
                title: $isNew ? 'Material created' : 'Material updated',
                description: $isNew
                    ? 'Your new teaching material has been created successfully.'
                    : 'The teaching material has been updated successfully.',
                icon: 'o-check-circle',
                css: 'alert-success'
            );
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: 'An error occurred: ' . $e->getMessage(),
                icon: 'o-x-circle',
                css: 'alert-error'
            );
        }
    }

    // Delete a material
    public function deleteMaterial()
    {
        if ($this->hasError) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: $this->errorMessage,
                icon: 'o-x-circle',
                css: 'alert-error'
            );
            return;
        }

        try {
            $material = Material::findOrFail($this->materialToDelete);

            // Delete the file if it exists
            if ($material->file_path) {
                Storage::delete($material->file_path);
            }

            $material->delete();

            $this->showDeleteModal = false;
            $this->materialToDelete = null;

            $this->dispatch('refresh');

            $this->toast(
                type: 'success',
                title: 'Material deleted',
                description: 'The teaching material has been deleted successfully.',
                icon: 'o-check-circle',
                css: 'alert-success'
            );
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Error',
                description: 'An error occurred: ' . $e->getMessage(),
                icon: 'o-x-circle',
                css: 'alert-error'
            );
        }
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
};?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Teaching Materials</h1>
                <p class="mt-1 text-base-content/70">Upload and manage your teaching resources</p>
            </div>
            <button
                wire:click="openCreateModal"
                class="btn btn-primary"
            >
                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                Upload Material
            </button>
        </div>

        @if($hasError)
            <div class="p-6 mb-6 shadow-lg alert alert-error">
                <div>
                    <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
                    <div>
                        <h3 class="font-bold">Error</h3>
                        <div class="text-xs">{{ $errorMessage }}</div>
                    </div>
                </div>
                <div class="flex-none">
                    <a href="{{ route('home') }}" class="btn btn-sm">Go Home</a>
                </div>
            </div>
        @else
            <!-- Filters and Search -->
            <div class="p-4 mb-6 shadow-lg rounded-xl bg-base-100">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <!-- Search -->
                    <div>
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                            </div>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search materials..."
                                class="w-full pl-10 input input-bordered"
                            >
                        </div>
                    </div>

                    <!-- Type Filter -->
                    <div>
                        <select wire:model.live="typeFilter" class="w-full select select-bordered">
                            <option value="">All Types</option>
                            @foreach($availableTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Subject Filter -->
                    <div>
                        <select wire:model.live="subjectFilter" class="w-full select select-bordered">
                            <option value="">All Subjects</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Course Filter -->
                    <div>
                        <select wire:model.live="courseFilter" class="w-full select select-bordered">
                            <option value="">All Courses</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}">{{ $course->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Visibility Filter -->
                    <div>
                        <select wire:model.live="visibilityFilter" class="w-full select select-bordered">
                            <option value="">All Visibility</option>
                            <option value="public">Public</option>
                            <option value="private">Private</option>
                            <option value="featured">Featured</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Materials Grid -->
            <div>
                @if(count($materials) > 0)
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach($materials as $material)
                            <div class="h-full overflow-hidden shadow-md card bg-base-100">
                                <div class="p-6">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 rounded-md bg-primary/10">
                                                <x-icon name="{{ $material->file_icon }}" class="w-6 h-6 text-primary" />
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-semibold truncate">{{ $material->title }}</h3>
                                                <p class="text-sm text-base-content/70">{{ $availableTypes[$material->type] ?? $material->type }}</p>
                                            </div>
                                        </div>

                                        <div class="flex gap-1">
                                            @if($material->is_featured)
                                                <div class="badge badge-warning">Featured</div>
                                            @endif

                                            @if($material->is_public)
                                                <div class="badge badge-success">Public</div>
                                            @else
                                                <div class="badge badge-ghost">Private</div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <p class="text-sm line-clamp-2">{{ $material->description }}</p>
                                    </div>

                                    <div class="flex flex-wrap gap-1 mt-3">
                                        @foreach($material->subjects as $subject)
                                            <span class="text-xs badge badge-sm">{{ $subject->name }}</span>
                                        @endforeach
                                    </div>

                                    <div class="flex items-center justify-between mt-4 text-xs text-base-content/70">
                                        <span>{{ $material->created_at->format('M d, Y') }}</span>
                                        <span>{{ $material->formatted_file_size }}</span>
                                    </div>

                                    <div class="flex justify-between mt-4">
                                        <button
                                            wire:click="viewMaterial({{ $material->id }})"
                                            class="btn btn-sm btn-ghost"
                                        >
                                            <x-icon name="o-eye" class="w-4 h-4 mr-1" />
                                            View
                                        </button>

                                        <div>
                                            <button
                                                wire:click="openEditModal({{ $material->id }})"
                                                class="btn btn-sm btn-ghost"
                                            >
                                                <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                                Edit
                                            </button>

                                            <button
                                                wire:click="confirmDelete({{ $material->id }})"
                                                class="btn btn-sm btn-ghost text-error"
                                            >
                                                <x-icon name="o-trash" class="w-4 h-4 mr-1" />
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $materials->links() }}
                    </div>
                @else
                    <div class="p-12 text-center shadow-lg rounded-xl bg-base-100">
                        <div class="flex flex-col items-center justify-center">
                            <x-icon name="o-document" class="w-16 h-16 mb-4 text-base-content/30" />
                            <h3 class="text-xl font-bold">No materials found</h3>
                            <p class="mt-2 text-base-content/70">
                                @if($search || $typeFilter || $subjectFilter || $courseFilter || $visibilityFilter)
                                    No materials match your search filters. Try adjusting your criteria.
                                @else
                                    You haven't uploaded any teaching materials yet. Start by uploading your first material.
                                @endif
                            </p>

                            @if($search || $typeFilter || $subjectFilter || $courseFilter || $visibilityFilter)
                                <button
                                    wire:click="$set('search', ''); $set('typeFilter', ''); $set('subjectFilter', ''); $set('courseFilter', ''); $set('visibilityFilter', '');"
                                    class="mt-4 btn btn-outline"
                                >
                                    Clear Filters
                                </button>
                            @else
                                <button
                                    wire:click="openCreateModal"
                                    class="mt-4 btn btn-primary"
                                >
                                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                    Upload Material
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Material Form Modal -->
    <div class="modal {{ $showMaterialModal ? 'modal-open' : '' }}">
        <div class="max-w-2xl modal-box">
            <h3 class="text-lg font-bold">{{ $materialId ? 'Edit Material' : 'Upload New Material' }}</h3>

            <form wire:submit.prevent="saveMaterial">
                <div class="grid grid-cols-1 gap-4 mt-4">
                    <!-- Title -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Title</span>
                        </label>
                        <input
                            type="text"
                            wire:model="title"
                            class="input input-bordered @error('title') input-error @enderror"
                            placeholder="Enter material title"
                        />
                        @error('title') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <!-- Description -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Description</span>
                        </label>
                        <textarea
                            wire:model="description"
                            class="textarea textarea-bordered @error('description') textarea-error @enderror"
                            placeholder="Describe this material"
                            rows="3"
                        ></textarea>
                        @error('description') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>
                    <!-- Material Type -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Material Type</span>
                        </label>
                        <select
                            wire:model.live="materialType"
                            class="select select-bordered @error('materialType') select-error @enderror"
                        >
                            @foreach($availableTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('materialType') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <!-- File Upload or External URL based on type -->
                    @if($materialType === 'link')
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">URL</span>
                            </label>
                            <input
                                type="url"
                                wire:model="externalUrl"
                                class="input input-bordered @error('externalUrl') input-error @enderror"
                                placeholder="https://example.com/resource"
                            />
                            @error('externalUrl') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">File Upload</span>
                            </label>
                            <input
                                type="file"
                                wire:model="file"
                                class="file-input file-input-bordered w-full @error('file') file-input-error @enderror"
                            />
                            <div class="mt-1 text-xs">Maximum size: 50MB</div>
                            @error('file') <span class="text-sm text-error">{{ $message }}</span> @enderror

                            @if($materialId && !$file)
                                <div class="mt-2 text-sm">
                                    <span class="font-medium">Current file:</span> {{ $viewingMaterial?->file_name ?? 'No file uploaded' }}
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Subjects Selection -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Related Subjects</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach($subjects as $subject)
                                <label class="flex items-center gap-2 cursor-pointer label">
                                    <input
                                        type="checkbox"
                                        value="{{ $subject->id }}"
                                        wire:click="toggleSubject({{ $subject->id }})"
                                        @if(in_array($subject->id, $selectedSubjects)) checked @endif
                                        class="checkbox checkbox-sm checkbox-primary"
                                    />
                                    <span class="label-text">{{ $subject->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedSubjects') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <!-- Courses Selection -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Related Courses</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach($courses as $course)
                                <label class="flex items-center gap-2 cursor-pointer label">
                                    <input
                                        type="checkbox"
                                        value="{{ $course->id }}"
                                        wire:click="toggleCourse({{ $course->id }})"
                                        @if(in_array($course->id, $selectedCourses)) checked @endif
                                        class="checkbox checkbox-sm checkbox-primary"
                                    />
                                    <span class="label-text">{{ $course->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedCourses') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <!-- Visibility Options -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="form-control">
                            <label class="cursor-pointer label">
                                <span class="label-text">Make Public</span>
                                <input
                                    type="checkbox"
                                    wire:model="isPublic"
                                    class="toggle toggle-primary"
                                />
                            </label>
                            <span class="text-xs text-base-content/70">Public materials can be viewed by students</span>
                        </div>

                        <div class="form-control">
                            <label class="cursor-pointer label">
                                <span class="label-text">Feature Material</span>
                                <input
                                    type="checkbox"
                                    wire:model="isFeatured"
                                    class="toggle toggle-primary"
                                />
                            </label>
                            <span class="text-xs text-base-content/70">Appear in featured materials list</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6 modal-action">
                    <button type="button" wire:click="$set('showMaterialModal', false)" class="btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        {{ $materialId ? 'Update Material' : 'Upload Material' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal {{ $showDeleteModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Confirm Delete</h3>
            <p class="py-4">Are you sure you want to delete this material? This action cannot be undone.</p>
            <div class="flex justify-end gap-2 modal-action">
                <button type="button" wire:click="$set('showDeleteModal', false)" class="btn">Cancel</button>
                <button type="button" wire:click="deleteMaterial" class="btn btn-error">Delete</button>
            </div>
        </div>
    </div>

    <!-- View Material Modal -->
    <div class="modal {{ $showViewModal ? 'modal-open' : '' }}">
        <div class="max-w-3xl modal-box">
            <h3 class="text-xl font-bold">{{ $viewingMaterial?->title }}</h3>

            <div class="grid gap-6 mt-4">
                <!-- Material Type and Badges -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="badge badge-lg">{{ $availableTypes[$viewingMaterial?->type ?? ''] ?? $viewingMaterial?->type }}</div>

                    @if($viewingMaterial?->is_featured)
                        <div class="badge badge-warning badge-lg">Featured</div>
                    @endif

                    @if($viewingMaterial?->is_public)
                        <div class="badge badge-success badge-lg">Public</div>
                    @else
                        <div class="badge badge-ghost badge-lg">Private</div>
                    @endif
                </div>

                <!-- Description -->
                @if($viewingMaterial?->description)
                    <div>
                        <h4 class="font-semibold">Description</h4>
                        <p class="mt-1">{{ $viewingMaterial?->description }}</p>
                    </div>
                @endif

                <!-- File Details -->
                @if($viewingMaterial?->file_path)
                    <div>
                        <h4 class="font-semibold">File Details</h4>
                        <div class="grid grid-cols-1 gap-2 mt-2 sm:grid-cols-2">
                            <div>
                                <span class="text-sm font-medium text-base-content/70">File Name:</span>
                                <span class="text-sm">{{ $viewingMaterial?->file_name }}</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-base-content/70">File Size:</span>
                                <span class="text-sm">{{ $viewingMaterial?->formatted_file_size }}</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-base-content/70">File Type:</span>
                                <span class="text-sm">{{ $viewingMaterial?->file_type }}</span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-base-content/70">Uploaded On:</span>
                                <span class="text-sm">{{ $viewingMaterial?->created_at->format('M d, Y') }}</span>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="{{ $viewingMaterial?->file_url }}" target="_blank" class="btn btn-primary btn-sm">
                                <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                                Download File
                            </a>
                        </div>
                    </div>
                @endif

                <!-- External URL -->
                @if($viewingMaterial?->external_url)
                    <div>
                        <h4 class="font-semibold">External URL</h4>
                        <div class="mt-2">
                            <a href="{{ $viewingMaterial?->external_url }}" target="_blank" class="break-all link link-primary">
                                {{ $viewingMaterial?->external_url }}
                            </a>
                        </div>

                        <div class="mt-4">
                            <a href="{{ $viewingMaterial?->external_url }}" target="_blank" class="btn btn-primary btn-sm">
                                <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4 mr-2" />
                                Open URL
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Subjects -->
                @if($viewingMaterial?->subjects->isNotEmpty())
                    <div>
                        <h4 class="font-semibold">Related Subjects</h4>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($viewingMaterial?->subjects ?? [] as $subject)
                                <span class="badge badge-outline">{{ $subject->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Courses -->
                @if($viewingMaterial?->courses->isNotEmpty())
                    <div>
                        <h4 class="font-semibold">Related Courses</h4>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($viewingMaterial?->courses ?? [] as $course)
                                <span class="badge badge-outline">{{ $course->name }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Last Updated -->
                <div class="text-sm text-base-content/70">
                    Last updated on {{ $viewingMaterial?->updated_at->format('M d, Y \a\t h:i A') }}
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6 modal-action">
                <button type="button" wire:click="$set('showViewModal', false)" class="btn">Close</button>
                @if($viewingMaterial)
                    <button
                        type="button"
                        wire:click="openEditModal({{ $viewingMaterial?->id }}); $set('showViewModal', false);"
                        class="btn btn-primary"
                    >
                        <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                        Edit
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
