<?php

namespace App\Livewire\Teachers\Materials;

use Livewire\Volt\Component;
use App\Models\Material;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

new class extends Component {
    public $material;
    public $relatedMaterials = [];
    public $teacherProfile;

    // UI state
    public $showShareModal = false;
    public $showDeleteModal = false;
    public $showEmbedModal = false;
    public $showQrCodeModal = false;
    public $shareUrl = '';
    public $embedCode = '';
    public $qrCodeUrl = '';
    public $activeTab = 'details'; // details, usage, related

    // File viewer state
    public $isPreviewAvailable = false;
    public $isImageFile = false;
    public $isPdfFile = false;
    public $isVideoFile = false;
    public $isAudioFile = false;

    // Document stats
    public $downloadCount = 0;
    public $viewCount = 0;
    public $lastDownloaded = null;
    public $metadata = [];

    // Visibility states
    public $isPublic;
    public $isFeatured;

    // Type icons mapping
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

    // Material types for display
    public $materialTypes = [
        'document' => 'Document',
        'pdf' => 'PDF',
        'spreadsheet' => 'Spreadsheet',
        'presentation' => 'Presentation',
        'video' => 'Video',
        'audio' => 'Audio',
        'image' => 'Image',
        'link' => 'External Link',
        'archive' => 'Archive/ZIP',
        'other' => 'Other',
    ];

    public function mount($material)
    {
        $this->teacherProfile = Auth::user()->teacherProfile;

        if (!$this->teacherProfile) {
            return redirect()->route('teachers.profile-setup')
                ->with('error', 'Please complete your teacher profile first.');
        }

        // Fetch material with relationships
        $this->material = Material::with(['subjects', 'courses'])
            ->where('id', $material)
            ->where('teacher_profile_id', $this->teacherProfile->id)
            ->firstOrFail();

        // Set up file preview states
        $this->setupFilePreviewState();

        // Get metadata
        $this->loadMetadata();

        // Track view
        $this->trackView();

        // Get current states
        $this->isPublic = $this->material->is_public;
        $this->isFeatured = $this->material->is_featured;

        // Set up sharing and embed options
        $this->setupSharing();

        // Load related materials
        $this->loadRelatedMaterials();
    }

    private function setupFilePreviewState()
    {
        if ($this->material->type === 'link') {
            $this->isPreviewAvailable = false;
            return;
        }

        if (!$this->material->file_path) {
            $this->isPreviewAvailable = false;
            return;
        }

        $mimeType = $this->material->file_type;

        // Check if it's an image file
        if (Str::startsWith($mimeType, 'image/')) {
            $this->isImageFile = true;
            $this->isPreviewAvailable = true;
        }

        // Check if it's a PDF file
        if ($mimeType === 'application/pdf' || $this->material->type === 'pdf') {
            $this->isPdfFile = true;
            $this->isPreviewAvailable = true;
        }

        // Check if it's a video file
        if (Str::startsWith($mimeType, 'video/')) {
            $this->isVideoFile = true;
            $this->isPreviewAvailable = true;
        }

        // Check if it's an audio file
        if (Str::startsWith($mimeType, 'audio/')) {
            $this->isAudioFile = true;
            $this->isPreviewAvailable = true;
        }
    }

    private function loadMetadata()
    {
        // Parse metadata if exists
        if ($this->material->metadata) {
            try {
                $this->metadata = json_decode($this->material->metadata, true) ?? [];
            } catch (\Exception $e) {
                $this->metadata = [];
            }
        }

        // Mock data for stats (in a real app, this would come from a tracking system)
        $this->downloadCount = rand(5, 50);
        $this->viewCount = rand(15, 100);
        $this->lastDownloaded = now()->subDays(rand(0, 14))->format('M d, Y');
    }

    private function trackView()
    {
        // In a real app, you would increment view counters here
    }

    private function setupSharing()
    {
        // Generate share URL
        $this->shareUrl = route('teachers.materials.show', $this->material->id);

        // Generate embed code (basic iframe example)
        $this->embedCode = '<iframe src="' . route('teachers.materials.embed', $this->material->id) . '" width="100%" height="600" frameborder="0"></iframe>';

        // QR code URL (using a public API - in production use a proper package)
        $this->qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($this->shareUrl);
    }

    private function loadRelatedMaterials()
    {
        // Get related materials based on subjects and courses
        $subjectIds = $this->material->subjects->pluck('id')->toArray();
        $courseIds = $this->material->courses->pluck('id')->toArray();

        if (empty($subjectIds) && empty($courseIds)) {
            $this->relatedMaterials = [];
            return;
        }

        // Find materials with the same subjects or courses
        $relatedQuery = Material::where('teacher_profile_id', $this->teacherProfile->id)
            ->where('id', '!=', $this->material->id)
            ->where(function ($query) use ($subjectIds, $courseIds) {
                if (!empty($subjectIds)) {
                    $query->whereHas('subjects', function ($q) use ($subjectIds) {
                        $q->whereIn('subjects.id', $subjectIds);
                    });
                }

                if (!empty($courseIds)) {
                    $query->orWhereHas('courses', function ($q) use ($courseIds) {
                        $q->whereIn('courses.id', $courseIds);
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit(4);

        $this->relatedMaterials = $relatedQuery->get();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function togglePublic()
    {
        $this->material->is_public = !$this->material->is_public;
        $this->material->save();
        $this->isPublic = $this->material->is_public;

        $message = $this->isPublic ? 'Material is now public' : 'Material is now private';

        $this->toast(
            type: 'success',
            title: 'Visibility Updated',
            description: $message,
            icon: 'o-check-circle',
            css: 'alert-success'
        );
    }

    public function toggleFeatured()
    {
        $this->material->is_featured = !$this->material->is_featured;
        $this->material->save();
        $this->isFeatured = $this->material->is_featured;

        $message = $this->isFeatured ? 'Material is now featured' : 'Material is no longer featured';

        $this->toast(
            type: 'success',
            title: 'Featured Status Updated',
            description: $message,
            icon: 'o-check-circle',
            css: 'alert-success'
        );
    }

    public function downloadMaterial()
    {
        // In a real app, you would increment download counters here

        if ($this->material->file_path) {
            return redirect()->to(Storage::url($this->material->file_path));
        }

        $this->toast(
            type: 'error',
            title: 'Download Failed',
            description: 'File not found or inaccessible.',
            icon: 'o-x-circle',
            css: 'alert-error'
        );
    }

    public function confirmDelete()
    {
        $this->showDeleteModal = true;
    }

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

    public function openShareModal()
    {
        $this->showShareModal = true;
    }

    public function openEmbedModal()
    {
        $this->showEmbedModal = true;
    }

    public function openQrCodeModal()
    {
        $this->showQrCodeModal = true;
    }

    public function copyToClipboard($text)
    {
        $this->js("
            navigator.clipboard.writeText('{$text}').then(function() {
                Toaster.success('Copied to clipboard', {
                    position: 'toast-bottom toast-end',
                    timeout: 3000
                });
            }, function() {
                Toaster.error('Could not copy text', {
                    position: 'toast-bottom toast-end',
                    timeout: 3000
                });
            });
        ");
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
        <!-- Header with Breadcrumbs -->
        <div class="mb-6">
            <div class="mb-2 text-sm breadcrumbs">
                <ul>
                    <li><a href="{{ route('teachers.dashboard') }}">Dashboard</a></li>
                    <li><a href="{{ route('teachers.materials') }}">Materials</a></li>
                    <li>{{ Str::limit($material->title, 30) }}</li>
                </ul>
            </div>

            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <h1 class="text-3xl font-bold">{{ $material->title }}</h1>

                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('teachers.materials.edit', $material->id) }}"
                        class="btn btn-primary"
                    >
                        <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                        Edit Material
                    </a>

                    <div class="dropdown dropdown-end">
                        <label tabindex="0" class="btn btn-ghost">
                            <x-icon name="o-ellipsis-vertical" class="w-5 h-5" />
                        </label>
                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                            <li>
                                <button wire:click="openShareModal">
                                    <x-icon name="o-share" class="w-4 h-4" />
                                    Share
                                </button>
                            </li>
                            <li>
                                <button wire:click="openEmbedModal">
                                    <x-icon name="o-code-bracket" class="w-4 h-4" />
                                    Embed
                                </button>
                            </li>
                            <li>
                                <button wire:click="openQrCodeModal">
                                    <x-icon name="o-qr-code" class="w-4 h-4" />
                                    QR Code
                                </button>
                            </li>
                            <li>
                                <button wire:click="downloadMaterial">
                                    <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                    Download
                                </button>
                            </li>
                            <li>
                                <button wire:click="confirmDelete" class="text-error">
                                    <x-icon name="o-trash" class="w-4 h-4" />
                                    Delete
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column: Material Content -->
            <div class="lg:col-span-2">
                <div class="shadow-xl card bg-base-100">
                    <!-- Material Header with Icon and Type -->
                    <div class="card-body">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-3 rounded-md bg-primary/10">
                                <x-icon name="{{ $typeIcons[$material->type] ?? 'o-document' }}" class="w-8 h-8 text-primary" />
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="badge badge-lg">{{ $materialTypes[$material->type] ?? $material->type }}</span>

                                    @if($isPublic)
                                        <span class="badge badge-success badge-lg">Public</span>
                                    @else
                                        <span class="badge badge-ghost badge-lg">Private</span>
                                    @endif

                                    @if($isFeatured)
                                        <span class="badge badge-warning badge-lg">Featured</span>
                                    @endif
                                </div>

                                <div class="mt-1 text-sm text-base-content/70">
                                    Uploaded on {{ $material->created_at->format('M d, Y') }}
                                </div>
                            </div>
                        </div>

                        <!-- Tab Buttons -->
                        <div class="flex mb-6 border-b">
                            <button
                                wire:click="setActiveTab('details')"
                                class="pb-3 px-4 {{ $activeTab === 'details' ? 'border-b-2 border-primary font-medium text-primary' : 'text-base-content/70 hover:text-base-content' }}"
                            >
                                Details
                            </button>
                            <button
                                wire:click="setActiveTab('usage')"
                                class="pb-3 px-4 {{ $activeTab === 'usage' ? 'border-b-2 border-primary font-medium text-primary' : 'text-base-content/70 hover:text-base-content' }}"
                            >
                                Usage & Stats
                            </button>
                            <button
                                wire:click="setActiveTab('related')"
                                class="pb-3 px-4 {{ $activeTab === 'related' ? 'border-b-2 border-primary font-medium text-primary' : 'text-base-content/70 hover:text-base-content' }}"
                            >
                                Related Materials
                            </button>
                        </div>

                        <!-- Tab Content -->
                        <div>
                            <!-- Details Tab -->
                            @if($activeTab === 'details')
                                <div class="space-y-6">
                                    <!-- Description -->
                                    @if($material->description)
                                        <div>
                                            <h3 class="mb-2 text-lg font-medium">Description</h3>
                                            <div class="prose max-w-none">
                                                <p>{{ $material->description }}</p>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Material Content - File or Link -->
                                    <div>
                                        <h3 class="mb-3 text-lg font-medium">Content</h3>

                                        @if($material->type === 'link')
                                            <div class="p-4 rounded-lg bg-base-200">
                                                <div class="flex items-center">
                                                    <x-icon name="o-link" class="w-5 h-5 mr-3 text-primary" />
                                                    <a href="{{ $material->external_url }}" target="_blank" class="break-all link link-primary">
                                                        {{ $material->external_url }}
                                                    </a>
                                                </div>

                                                <div class="mt-3">
                                                    <a
                                                        href="{{ $material->external_url }}"
                                                        target="_blank"
                                                        class="btn btn-primary btn-sm"
                                                    >
                                                        <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4 mr-2" />
                                                        Open Link
                                                    </a>
                                                </div>
                                            </div>
                                        @elseif($isPreviewAvailable)
                                            <div class="p-4 rounded-lg bg-base-200">
                                                <!-- File Info -->
                                                <div class="flex items-center justify-between mb-3">
                                                    <div class="flex items-center">
                                                        <x-icon name="{{ $typeIcons[$material->type] ?? 'o-document' }}" class="w-5 h-5 mr-2 text-primary" />
                                                        <span class="font-medium">{{ $material->file_name }}</span>
                                                    </div>
                                                    <span class="text-sm text-base-content/60">{{ $material->formatted_file_size }}</span>
                                                </div>

                                                <!-- Preview Container -->
                                                <div class="mt-3 bg-base-100 rounded-lg overflow-hidden border min-h-[300px] flex items-center justify-center">
                                                    @if($isImageFile)
                                                        <img
                                                            src="{{ Storage::url($material->file_path) }}"
                                                            alt="{{ $material->title }}"
                                                            class="max-w-full max-h-[500px] object-contain"
                                                        />
                                                    @elseif($isPdfFile)
                                                        <div class="w-full h-[500px]">
                                                            <iframe
                                                                src="{{ Storage::url($material->file_path) }}"
                                                                width="100%"
                                                                height="100%"
                                                                class="border-0"
                                                            ></iframe>
                                                        </div>
                                                    @elseif($isVideoFile)
                                                        <video
                                                            controls
                                                            class="max-w-full max-h-[500px]"
                                                        >
                                                            <source src="{{ Storage::url($material->file_path) }}" type="{{ $material->file_type }}">
                                                            Your browser does not support the video tag.
                                                        </video>
                                                    @elseif($isAudioFile)
                                                        <audio
                                                            controls
                                                            class="w-full"
                                                        >
                                                            <source src="{{ Storage::url($material->file_path) }}" type="{{ $material->file_type }}">
                                                            Your browser does not support the audio tag.
                                                        </audio>
                                                    @endif
                                                </div>

                                                <!-- Download Button -->
                                                <div class="mt-3">
                                                    <button
                                                        wire:click="downloadMaterial"
                                                        class="btn btn-primary btn-sm"
                                                    >
                                                        <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                                                        Download {{ $materialTypes[$material->type] ?? 'File' }}
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="p-4 rounded-lg bg-base-200">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <x-icon name="{{ $typeIcons[$material->type] ?? 'o-document' }}" class="w-5 h-5 mr-2 text-primary" />
                                                        <span class="font-medium">{{ $material->file_name }}</span>
                                                    </div>
                                                    <span class="text-sm text-base-content/60">{{ $material->formatted_file_size }}</span>
                                                </div>

                                                <div class="mt-3">
                                                    <button
                                                        wire:click="downloadMaterial"
                                                        class="btn btn-primary btn-sm"
                                                    >
                                                        <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                                                        Download {{ $materialTypes[$material->type] ?? 'File' }}
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Tags -->
                                    @if(!empty($metadata['tags'] ?? []))
                                        <div>
                                            <h3 class="mb-3 text-lg font-medium">Tags</h3>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($metadata['tags'] as $tag)
                                                    <div class="badge badge-lg">{{ $tag }}</div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <!-- Usage & Stats Tab -->
                            @if($activeTab === 'usage')
                                <div class="space-y-6">
                                    <!-- Usage Stats -->
                                    <div>
                                        <h3 class="mb-3 text-lg font-medium">Material Statistics</h3>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <div class="rounded-lg stat bg-base-200">
                                                <div class="stat-figure text-primary">
                                                    <x-icon name="o-eye" class="w-8 h-8" />
                                                </div>
                                                <div class="stat-title">Views</div>
                                                <div class="stat-value">{{ $viewCount }}</div>
                                            </div>

                                            <div class="rounded-lg stat bg-base-200">
                                                <div class="stat-figure text-primary">
                                                    <x-icon name="o-arrow-down-tray" class="w-8 h-8" />
                                                </div>
                                                <div class="stat-title">Downloads</div>
                                                <div class="stat-value">{{ $downloadCount }}</div>
                                                <div class="stat-desc">Last: {{ $lastDownloaded }}</div>
                                            </div>

                                            <div class="rounded-lg stat bg-base-200">
                                                <div class="stat-figure text-primary">
                                                    <x-icon name="o-academic-cap" class="w-8 h-8" />
                                                </div>
                                                <div class="stat-title">Student Access</div>
                                                <div class="stat-value">{{ $isPublic ? 'Public' : 'Private' }}</div>
                                                <div class="stat-desc">{{ $isPublic ? 'Available to all students' : 'Restricted access' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Visibility Settings -->
                                    <div>
                                        <h3 class="mb-3 text-lg font-medium">Visibility Controls</h3>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div class="p-4 rounded-lg bg-base-200">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <h4 class="font-medium">Public Access</h4>
                                                        <p class="text-sm text-base-content/70">Make visible to all your students</p>
                                                    </div>
                                                    <input
                                                        type="checkbox"
                                                        wire:model="isPublic"
                                                        wire:click="togglePublic"
                                                        class="toggle toggle-primary"
                                                    />
                                                </div>
                                            </div>

                                            <div class="p-4 rounded-lg bg-base-200">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <h4 class="font-medium">Featured Material</h4>
                                                        <p class="text-sm text-base-content/70">Highlight in featured materials</p>
                                                    </div>
                                                    <input
                                                        type="checkbox"
                                                        wire:model="isFeatured"
                                                        wire:click="toggleFeatured"
                                                        class="toggle toggle-primary"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sharing Options -->
                                    <div>
                                        <h3 class="mb-3 text-lg font-medium">Sharing Options</h3>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <button
                                                wire:click="openShareModal"
                                                class="btn btn-outline"
                                            >
                                                <x-icon name="o-share" class="w-5 h-5 mr-2" />
                                                Share Link
                                            </button>

                                            <button
                                                wire:click="openEmbedModal"
                                                class="btn btn-outline"
                                            >
                                                <x-icon name="o-code-bracket" class="w-5 h-5 mr-2" />
                                                Embed Code
                                            </button>

                                            <button
                                                wire:click="openQrCodeModal"
                                                class="btn btn-outline"
                                            >
                                                <x-icon name="o-qr-code" class="w-5 h-5 mr-2" />
                                                QR Code
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Related Materials Tab -->
                            @if($activeTab === 'related')
                                <div>
                                    <h3 class="mb-3 text-lg font-medium">Related Teaching Materials</h3>

                                    @if(count($relatedMaterials) > 0)
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            @foreach($relatedMaterials as $relatedMaterial)
                                                <div class="card bg-base-200">
                                                    <div class="p-4 card-body">
                                                        <div class="flex items-start gap-3">
                                                            <div class="p-2 rounded-md bg-primary/10">
                                                                <x-icon name="{{ $typeIcons[$relatedMaterial->type] ?? 'o-document' }}" class="w-6 h-6 text-primary" />
                                                            </div>
                                                            <div>
                                                                <h4 class="font-medium">{{ $relatedMaterial->title }}</h4>
                                                                <p class="text-sm text-base-content/70">{{ $materialTypes[$relatedMaterial->type] ?? $relatedMaterial->type }}</p>
                                                            </div>
                                                        </div>

                                                        <div class="flex items-center justify-between mt-3">
                                                            <span class="text-xs text-base-content/70">{{ $relatedMaterial->created_at->format('M d, Y') }}</span>
                                                            <a
                                                                href="{{ route('teachers.materials.show', $relatedMaterial->id) }}"
                                                                class="btn btn-xs btn-outline"
                                                            >
                                                                View
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="py-8 text-center rounded-lg bg-base-200">
                                            <x-icon name="o-document-duplicate" class="w-12 h-12 mx-auto mb-3 text-base-content/30" />
                                            <h4 class="font-medium">No related materials found</h4>
                                            <p class="mt-1 text-sm text-base-content/70">This material doesn't share subjects or courses with other materials.</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Material Details and Organization -->
            <div>
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="mb-4 text-lg font-medium">Organization</h3>

                        <!-- Subjects -->
                        <div class="mb-6">
                            <h4 class="mb-2 font-medium">Subjects</h4>

                            @if($material->subjects->isNotEmpty())
                                <div class="flex flex-wrap gap-2">
                                    @foreach($material->subjects as $subject)
                                        <div class="badge badge-lg">{{ $subject->name }}</div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-base-content/70">No subjects associated</p>
                            @endif
                        </div>

                        <!-- Courses -->
                        <div class="mb-6">
                            <h4 class="mb-2 font-medium">Courses</h4>

                            @if($material->courses->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($material->courses as $course)
                                        <div class="flex items-center p-2 rounded-lg bg-base-200">
                                            <div class="flex-1">
                                                <p class="font-medium">{{ $course->name }}</p>
                                                <p class="text-xs text-base-content/70">{{ $course->code ?? '' }}</p>
                                            </div>
                                            <a
                                                href="{{ route('teachers.courses.show', $course->id) }}"
                                                class="btn btn-xs btn-ghost"
                                            >
                                                View
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-base-content/70">No courses associated</p>
                            @endif
                        </div>

                        <!-- File Details -->
                        <div>
                            <h4 class="mb-2 font-medium">File Details</h4>

                            <div class="overflow-x-auto">
                                <table class="table w-full text-sm table-zebra">
                                    <tbody>
                                        <tr>
                                            <th class="w-1/3">Format</th>
                                            <td>{{ $materialTypes[$material->type] ?? $material->type }}</td>
                                        </tr>

                                        @if($material->file_name)
                                        <tr>
                                            <th>Filename</th>
                                            <td class="truncate max-w-[200px]">{{ $material->file_name }}</td>
                                        </tr>
                                        @endif

                                        @if($material->file_size)
                                        <tr>
                                            <th>Size</th>
                                            <td>{{ $material->formatted_file_size }}</td>
                                        </tr>
                                        @endif

                                        @if($material->file_type)
                                        <tr>
                                            <th>MIME Type</th>
                                            <td>{{ $material->file_type }}</td>
                                        </tr>
                                        @endif

                                        <tr>
                                            <th>Created</th>
                                            <td>{{ $material->created_at->format('M d, Y') }}</td>
                                        </tr>

                                        <tr>
                                            <th>Last Updated</th>
                                            <td>{{ $material->updated_at->format('M d, Y') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="modal {{ $showShareModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="mb-4 text-lg font-bold">Share Material</h3>

            <p class="mb-4">Share this material with your students or colleagues:</p>

            <div class="form-control">
                <div class="input-group">
                    <input type="text" value="{{ $shareUrl }}" class="w-full input input-bordered" readonly />
                    <button
                        class="btn btn-square"
                        wire:click="copyToClipboard('{{ $shareUrl }}')"
                    >
                        <x-icon name="o-clipboard" class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 mt-6">
                <!-- Social media share buttons would go here -->
                <button class="btn btn-outline">
                    <x-icon name="o-envelope" class="w-5 h-5 mr-2" />
                    Email
                </button>
                <button class="btn btn-outline">
                    <x-icon name="o-chat-bubble-left-right" class="w-5 h-5 mr-2" />
                    Message
                </button>
                <button class="btn btn-outline">
                    <x-icon name="o-qr-code" class="w-5 h-5 mr-2" />
                    QR Code
                </button>
            </div>

            <div class="modal-action">
                <button class="btn" wire:click="$set('showShareModal', false)">Close</button>
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

    <!-- Embed Code Modal -->
    <div class="modal {{ $showEmbedModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="mb-4 text-lg font-bold">Embed Material</h3>
            <p class="mb-4">Copy this code to embed the material in your website or LMS:</p>

            <div class="form-control">
                <textarea class="h-24 font-mono text-sm textarea textarea-bordered" readonly>{{ $embedCode }}</textarea>
            </div>

            <div class="mt-3">
                <button
                    class="btn btn-outline btn-sm"
                    wire:click="copyToClipboard('{{ $embedCode }}')"
                >
                    <x-icon name="o-clipboard" class="w-4 h-4 mr-2" />
                    Copy Code
                </button>
            </div>

            <div class="modal-action">
                <button class="btn" wire:click="$set('showEmbedModal', false)">Close</button>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal {{ $showQrCodeModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="mb-4 text-lg font-bold">QR Code</h3>
            <p class="mb-4">Scan this QR code to access the material:</p>

            <div class="flex justify-center mb-4">
                <img src="{{ $qrCodeUrl }}" alt="QR Code" class="max-w-[200px]" />
            </div>

            <div class="text-center">
                <button
                    class="btn btn-outline btn-sm"
                    onclick="window.print()"
                >
                    <x-icon name="o-printer" class="w-4 h-4 mr-2" />
                    Print QR Code
                </button>

                <a
                    href="{{ $qrCodeUrl }}"
                    download="material-{{ $material->id }}-qrcode.png"
                    class="ml-2 btn btn-outline btn-sm"
                >
                    <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                    Download
                </a>
            </div>

            <div class="modal-action">
                <button class="btn" wire:click="$set('showQrCodeModal', false)">Close</button>
            </div>
        </div>
    </div>
</div>
