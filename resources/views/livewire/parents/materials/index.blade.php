<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Material;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    
    public $user;
    public $children = [];
    public $subjects = [];
    
    // Filters
    public $searchQuery = '';
    public $typeFilter = '';
    public $subjectFilter = '';
    public $childFilter = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    
    // View options
    public $viewMode = 'grid'; // grid or list
    
    // Modal
    public $selectedMaterial = null;
    public $showMaterialModal = false;
    
    public function mount()
    {
        $this->user = Auth::user();
        $this->loadChildren();
        $this->loadSubjects();
    }
    
    public function loadChildren()
    {
        if ($this->user->parentProfile) {
            $this->children = $this->user->parentProfile->children()->get();
        }
    }
    
    public function loadSubjects()
    {
        $this->subjects = Subject::all();
    }
    
    public function getMaterialsProperty()
    {
        $query = Material::query()->with(['subject', 'teacher']);
        
        // Apply filters
        if ($this->searchQuery) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('description', 'like', '%' . $this->searchQuery . '%');
            });
        }
        
        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }
        
        if ($this->subjectFilter) {
            $query->where('subject_id', $this->subjectFilter);
        }
        
        if ($this->childFilter) {
            // Filter by grade level or materials specifically assigned to the child
            $child = $this->children->firstWhere('id', $this->childFilter);
            if ($child) {
                $query->where(function($q) use ($child) {
                    $q->where('grade_level', $child->grade)
                      ->orWhereHas('children', function($sq) use ($child) {
                          $sq->where('children.id', $child->id);
                      });
                });
            }
        }
        
        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);
        
        return $query->paginate(12);
    }
    
    public function getMaterialTypesProperty()
    {
        return [
            'document' => 'Documents',
            'video' => 'Videos',
            'audio' => 'Audio',
            'link' => 'Links',
            'interactive' => 'Interactive',
            'worksheet' => 'Worksheets',
            'quiz' => 'Quizzes',
        ];
    }
    
    public function getRecentMaterialsProperty()
    {
        return Material::latest()->take(5)->get();
    }
    
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }
    
    public function viewMaterial($materialId)
    {
        $this->selectedMaterial = Material::with(['subject', 'teacher', 'attachments'])
            ->findOrFail($materialId);
        $this->showMaterialModal = true;
    }
    
    public function getMaterialIcon($type)
    {
        return match($type) {
            'document' => 'o-document-text',
            'video' => 'o-film',
            'audio' => 'o-musical-note',
            'link' => 'o-link',
            'interactive' => 'o-puzzle-piece',
            'worksheet' => 'o-clipboard-document-list',
            'quiz' => 'o-clipboard-document-check',
            default => 'o-document'
        };
    }
    
    public function getMaterialColor($type)
    {
        return match($type) {
            'document' => 'bg-blue-100 text-blue-800',
            'video' => 'bg-red-100 text-red-800',
            'audio' => 'bg-purple-100 text-purple-800',
            'link' => 'bg-green-100 text-green-800',
            'interactive' => 'bg-yellow-100 text-yellow-800',
            'worksheet' => 'bg-indigo-100 text-indigo-800',
            'quiz' => 'bg-pink-100 text-pink-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }
    
    public function closeModal()
    {
        $this->showMaterialModal = false;
        $this->selectedMaterial = null;
    }
    
    // Reset pagination when filters change
    public function updatedSearchQuery() { $this->resetPage(); }
    public function updatedTypeFilter() { $this->resetPage(); }
    public function updatedSubjectFilter() { $this->resetPage(); }
    public function updatedChildFilter() { $this->resetPage(); }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="max-w-7xl mx-auto">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-primary to-secondary text-white rounded-xl shadow-lg overflow-hidden mb-8">
            <div class="p-6 md:p-8">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold">Learning Materials</h1>
                        <p class="mt-2 text-white/80">
                            Access educational resources, worksheets, videos, and more for your child's learning journey
                        </p>
                    </div>
                    
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('parents.dashboard') }}" class="btn btn-ghost btn-sm text-white bg-white/10">
                            <x-icon name="o-home" class="w-4 h-4 mr-1" />
                            Dashboard
                        </a>
                        <a href="{{ route('parents.homework.index') }}" class="btn btn-ghost btn-sm text-white bg-white/10">
                            <x-icon name="o-document-text" class="w-4 h-4 mr-1" />
                            Homework
                        </a>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <div class="bg-white/20 rounded-lg p-4 flex flex-col items-center">
                        <span class="text-3xl font-bold">{{ count($this->subjects) }}</span>
                        <span class="text-white/90 mt-1">Subjects</span>
                    </div>
                    <div class="bg-white/20 rounded-lg p-4 flex flex-col items-center">
                        <span class="text-3xl font-bold">{{ $this->materials->total() }}</span>
                        <span class="text-white/90 mt-1">Resources</span>
                    </div>
                    <div class="bg-white/20 rounded-lg p-4 flex flex-col items-center">
                        <span class="text-3xl font-bold">{{ count($this->materialTypes) }}</span>
                        <span class="text-white/90 mt-1">Material Types</span>
                    </div>
                    <div class="bg-white/20 rounded-lg p-4 flex flex-col items-center">
                        <span class="text-3xl font-bold">{{ $this->recentMaterials->count() }}</span>
                        <span class="text-white/90 mt-1">New This Week</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left Sidebar -->
            <div class="space-y-6">
                <!-- Search and Filters Card -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title mb-4">
                            <x-icon name="o-funnel" class="w-5 h-5 mr-2" />
                            Search & Filters
                        </h2>
                        
                        <div class="form-control mb-4">
                            <div class="relative">
                                <input type="text" placeholder="Search materials..." 
                                    class="input input-bordered w-full pl-10" 
                                    wire:model.live.debounce.300ms="searchQuery">
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                                    <x-icon name="o-magnifying-glass" class="w-4 h-4 text-base-content/60" />
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label">
                                <span class="label-text">Material Type</span>
                            </label>
                            <select class="select select-bordered w-full" wire:model.live="typeFilter">
                                <option value="">All Types</option>
                                @foreach($this->materialTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="form-control mb-4">
                            <label class="label">
                                <span class="label-text">Subject</span>
                            </label>
                            <select class="select select-bordered w-full" wire:model.live="subjectFilter">
                                <option value="">All Subjects</option>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        @if(count($children) > 0)
                            <div class="form-control mb-4">
                                <label class="label">
                                    <span class="label-text">Child</span>
                                </label>
                                <select class="select select-bordered w-full" wire:model.live="childFilter">
                                    <option value="">All Children</option>
                                    @foreach($children as $child)
                                        <option value="{{ $child->id }}">{{ $child->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Sort By</span>
                            </label>
                            <div class="flex gap-2">
                                <select class="select select-bordered w-full" wire:model.live="sortField">
                                    <option value="created_at">Date Added</option>
                                    <option value="title">Title</option>
                                    <option value="type">Type</option>
                                </select>
                                <button class="btn btn-square btn-outline"
                                    wire:click="$set('sortDirection', '{{ $sortDirection === 'asc' ? 'desc' : 'asc' }}')">
                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-arrow-up' : 'o-arrow-down' }}" class="w-5 h-5" />
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button class="btn btn-sm" wire:click="$set('viewMode', 'list')" 
                                :class="{ 'btn-primary': viewMode === 'list', 'btn-ghost': viewMode !== 'list' }">
                                <x-icon name="o-bars-3" class="w-5 h-5" />
                            </button>
                            <button class="btn btn-sm" wire:click="$set('viewMode', 'grid')"
                                :class="{ 'btn-primary': viewMode === 'grid', 'btn-ghost': viewMode !== 'grid' }">
                                <x-icon name="o-squares-2x2" class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Material Types Quick Links -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title mb-4">
                            <x-icon name="o-rectangle-stack" class="w-5 h-5 mr-2" />
                            Browse by Type
                        </h2>
                        
                        <div class="space-y-2">
                            @foreach($this->materialTypes as $type => $label)
                                <button 
                                    class="btn btn-block justify-start {{ $typeFilter === $type ? 'btn-primary' : 'btn-outline' }}" 
                                    wire:click="$set('typeFilter', '{{ $type }}')">
                                    <x-icon name="{{ $this->getMaterialIcon($type) }}" class="w-5 h-5 mr-2" />
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <!-- Recently Added Materials -->
                <div class="card bg-base-100 shadow-xl hidden lg:block">
                    <div class="card-body">
                        <h2 class="card-title mb-4">
                            <x-icon name="o-clock" class="w-5 h-5 mr-2" />
                            Recently Added
                        </h2>
                        
                        <div class="space-y-4">
                            @foreach($this->recentMaterials as $material)
                                <div class="flex items-start gap-3 hover:bg-base-200 p-2 rounded-lg transition-colors cursor-pointer"
                                    wire:click="viewMaterial({{ $material->id }})">
                                    <div class="w-10 h-10 rounded-md flex items-center justify-center {{ $this->getMaterialColor($material->type) }}">
                                        <x-icon name="{{ $this->getMaterialIcon($material->type) }}" class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <div class="font-medium line-clamp-1">{{ $material->title }}</div>
                                        <div class="text-xs text-base-content/70">
                                            {{ $material->created_at->format('M d, Y') }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="lg:col-span-3 space-y-6">
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="card-title">
                                @if($searchQuery || $typeFilter || $subjectFilter || $childFilter)
                                    <span>Search Results</span>
                                    <span class="text-base font-normal text-base-content/70">({{ $this->materials->total() }} items)</span>
                                @else
                                    <span>All Learning Materials</span>
                                @endif
                            </h2>
                            
                            @if($searchQuery || $typeFilter || $subjectFilter || $childFilter)
                                <button class="btn btn-sm btn-ghost" wire:click="resetFilters">
                                    <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                                    Clear Filters
                                </button>
                            @endif
                        </div>
                        
                        @if($this->materials->isEmpty())
                            <div class="py-12 text-center">
                                <x-icon name="o-document-magnifying-glass" class="w-16 h-16 mx-auto text-base-content/30" />
                                <h3 class="mt-4 text-lg font-medium">No materials found</h3>
                                <p class="mt-1 text-base-content/70">
                                    @if($searchQuery || $typeFilter || $subjectFilter || $childFilter)
                                        Try adjusting your search or filters
                                    @else
                                        No learning materials available yet
                                    @endif
                                </p>
                            </div>
                        @else
                            <!-- Grid View -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" x-show="$wire.viewMode === 'grid'">
                                @foreach($this->materials as $material)
                                    <div class="card bg-base-200 hover:shadow-md transition-shadow cursor-pointer" 
                                        wire:click="viewMaterial({{ $material->id }})">
                                        <div class="card-body p-4">
                                            <div class="flex justify-between items-start mb-2">
                                                <div class="w-10 h-10 rounded-md flex items-center justify-center {{ $this->getMaterialColor($material->type) }}">
                                                    <x-icon name="{{ $this->getMaterialIcon($material->type) }}" class="w-5 h-5" />
                                                </div>
                                                <div class="badge badge-sm">{{ ucfirst($material->type) }}</div>
                                            </div>
                                            
                                            <h3 class="font-bold text-lg line-clamp-1">{{ $material->title }}</h3>
                                            
                                            <p class="text-sm line-clamp-2 text-base-content/70 mt-1">{{ $material->description }}</p>
                                            
                                            <div class="card-actions justify-between items-center mt-4">
                                                <div class="badge badge-outline">{{ $material->subject?->name ?? 'General' }}</div>
                                                <div class="text-xs text-base-content/70">
                                                    {{ $material->created_at->format('M d, Y') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                            <!-- List View -->
                            <div class="overflow-x-auto" x-show="$wire.viewMode === 'list'">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Subject</th>
                                            <th>Added On</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->materials as $material)
                                            <tr class="hover:bg-base-200 cursor-pointer" wire:click="viewMaterial({{ $material->id }})">
                                                <td>
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-8 h-8 rounded-md flex items-center justify-center {{ $this->getMaterialColor($material->type) }}">
                                                            <x-icon name="{{ $this->getMaterialIcon($material->type) }}" class="w-4 h-4" />
                                                        </div>
                                                        <div>
                                                            <div class="font-bold">{{ $material->title }}</div>
                                                            <div class="text-xs opacity-70 line-clamp-1">{{ $material->description }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="badge badge-sm">{{ ucfirst($material->type) }}</div>
                                                </td>
                                                <td>{{ $material->subject?->name ?? 'General' }}</td>
                                                <td>{{ $material->created_at->format('M d, Y') }}</td>
                                                <td>
                                                    <button class="btn btn-ghost btn-sm">
                                                        <x-icon name="o-eye" class="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-6">
                                {{ $this->materials->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Material Detail Modal -->
    @if($showMaterialModal && $selectedMaterial)
        <div class="fixed inset-0 bg-black bg-opacity-50 z-40 flex items-center justify-center p-4">
            <div class="modal-box max-w-4xl w-full">
                <h3 class="font-bold text-xl mb-2">{{ $selectedMaterial->title }}</h3>
                <button wire:click="closeModal" class="absolute btn btn-sm btn-circle btn-ghost right-2 top-2">✕</button>
                
                <div class="flex flex-wrap gap-2 mb-4">
                    <div class="badge {{ $this->getMaterialColor($selectedMaterial->type) }}">
                        {{ ucfirst($selectedMaterial->type) }}
                    </div>
                    <div class="badge badge-outline">{{ $selectedMaterial->subject?->name ?? 'General' }}</div>
                    @if($selectedMaterial->grade_level)
                        <div class="badge badge-outline">Grade {{ $selectedMaterial->grade_level }}</div>
                    @endif
                </div>
                
                <div class="divider my-2"></div>
                
                <div class="prose max-w-none mb-4">
                    {{ $selectedMaterial->description }}
                </div>
                
                @if($selectedMaterial->content)
                    <div class="bg-base-200 p-4 rounded-lg mb-4">
                        <div class="font-medium mb-2">Content</div>
                        <div class="prose max-w-none">
                            {!! $selectedMaterial->content !!}
                        </div>
                    </div>
                @endif
                
                @if($selectedMaterial->external_url)
                    <div class="mb-4">
                        <div class="font-medium mb-2">External Resource</div>
                        <a href="{{ $selectedMaterial->external_url }}" target="_blank" class="btn btn-primary btn-block">
                            <x-icon name="o-arrow-top-right-on-square" class="w-5 h-5 mr-2" />
                            Open External Resource
                        </a>
                    </div>
                @endif
                
                @if($selectedMaterial->attachments && $selectedMaterial->attachments->isNotEmpty())
                    <div class="mb-4">
                        <div class="font-medium mb-2">Attachments</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($selectedMaterial->attachments as $attachment)
                                <a href="{{ asset('storage/' . $attachment->file_path) }}" target="_blank" class="card bg-base-200 hover:bg-base-300 transition-colors">
                                    <div class="card-body p-4">
                                        <div class="flex items-center gap-3">
                                            @php
                                                $extension = pathinfo($attachment->file_name, PATHINFO_EXTENSION);
                                                $iconClass = match(strtolower($extension)) {
                                                    'pdf' => 'text-red-500',
                                                    'doc', 'docx' => 'text-blue-500',
                                                    'xls', 'xlsx' => 'text-green-500',
                                                    'jpg', 'jpeg', 'png', 'gif' => 'text-purple-500',
                                                    default => 'text-gray-500'
                                                };
                                            @endphp
                                            
                                            <div class="w-10 h-10 flex items-center justify-center rounded bg-base-300 {{ $iconClass }}">
                                                <x-icon name="o-document" class="w-6 h-6" />
                                            </div>
                                            
                                            <div>
                                                <div class="font-medium">{{ $attachment->file_name }}</div>
                                                <div class="text-xs text-base-content/70">
                                                    {{ number_format($attachment->file_size / 1024, 2) }} KB
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                
                <div class="divider my-4"></div>
                
                <div class="flex justify-between items-center">
                    <div>
                        <div class="text-sm text-base-content/70">Added by</div>
                        <div class="font-medium">{{ $selectedMaterial->teacher?->name ?? 'System' }}</div>
                        <div class="text-xs text-base-content/70">{{ $selectedMaterial->created_at->format('M d, Y') }}</div>
                    </div>
                    
                    <div class="space-x-2">
                        @if($selectedMaterial->external_url)
                            <a href="{{ $selectedMaterial->external_url }}" target="_blank" class="btn btn-outline btn-sm">
                                <x-icon name="o-link" class="w-4 h-4 mr-2" />
                                Open Link
                            </a>
                        @endif
                        
                        <a href="{{ route('materials.show', $selectedMaterial->id) }}" class="btn btn-primary btn-sm">
                            <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4 mr-2" />
                            View Full Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>