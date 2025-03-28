<?php

use Livewire\Volt\Component;
use App\Models\Material;
use App\Models\MaterialComment;
use App\Models\MaterialProgress;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;
    
    public $material;
    public $user;
    public $comment = '';
    public $rating = 0;
    public $progress = 0;
    public $uploadedFile;
    public $submissionNote = '';
    public $showSubmitModal = false;
    public $activeTab = 'overview';
    public $relatedMaterials = [];
    
    // For child progress tracking
    public $childId;
    public $children = [];
    public $bookmarked = false;
    
    public function mount($material)
    {
        $this->material = Material::with(['subject', 'teacher', 'attachments', 'comments.user'])
            ->findOrFail($material);
        
        $this->user = Auth::user();
        $this->loadChildren();
        $this->loadProgress();
        $this->loadRelatedMaterials();
        $this->checkBookmarkStatus();
    }
    
    public function loadChildren()
    {
        if ($this->user->parentProfile) {
            $this->children = $this->user->parentProfile->children()->get();
            
            // Select the first child by default if available
            if ($this->children->isNotEmpty() && !$this->childId) {
                $this->childId = $this->children->first()->id;
            }
        }
    }
    
    public function loadProgress()
    {
        if ($this->childId) {
            $progressRecord = MaterialProgress::where('material_id', $this->material->id)
                ->where('child_id', $this->childId)
                ->first();
                
            if ($progressRecord) {
                $this->progress = $progressRecord->progress_percentage;
                $this->rating = $progressRecord->rating ?? 0;
            }
        }
    }
    
    public function loadRelatedMaterials()
    {
        // Get related materials with same subject or type
        $this->relatedMaterials = Material::where('id', '!=', $this->material->id)
            ->where(function($query) {
                $query->where('subject_id', $this->material->subject_id)
                    ->orWhere('type', $this->material->type);
            })
            ->latest()
            ->take(4)
            ->get();
    }
    
    public function checkBookmarkStatus()
    {
        if ($this->childId) {
            $this->bookmarked = $this->material->bookmarks()
                ->where('child_id', $this->childId)
                ->exists();
        }
    }
    
    public function updateProgress($value)
    {
        $this->progress = $value;
        
        if ($this->childId) {
            MaterialProgress::updateOrCreate(
                [
                    'material_id' => $this->material->id,
                    'child_id' => $this->childId
                ],
                [
                    'progress_percentage' => $value,
                    'last_accessed_at' => now(),
                ]
            );
            
            $this->dispatch('toast', [
                'message' => 'Progress updated',
                'type' => 'success'
            ]);
        }
    }
    
    public function updateRating($value)
    {
        $this->rating = $value;
        
        if ($this->childId) {
            MaterialProgress::updateOrCreate(
                [
                    'material_id' => $this->material->id,
                    'child_id' => $this->childId
                ],
                [
                    'rating' => $value,
                ]
            );
            
            $this->dispatch('toast', [
                'message' => 'Rating updated',
                'type' => 'success'
            ]);
        }
    }
    
    public function toggleBookmark()
    {
        if (!$this->childId) {
            return;
        }
        
        if ($this->bookmarked) {
            $this->material->bookmarks()->where('child_id', $this->childId)->delete();
            $this->bookmarked = false;
            $message = 'Bookmark removed';
        } else {
            $this->material->bookmarks()->create([
                'child_id' => $this->childId,
                'user_id' => $this->user->id,
            ]);
            $this->bookmarked = true;
            $message = 'Material bookmarked';
        }
        
        $this->dispatch('toast', [
            'message' => $message,
            'type' => 'success'
        ]);
    }
    
    public function submitComment()
    {
        $this->validate([
            'comment' => 'required|min:3|max:500',
        ]);
        
        $this->material->comments()->create([
            'user_id' => $this->user->id,
            'comment' => $this->comment,
        ]);
        
        $this->comment = '';
        
        // Reload the material to get the new comment
        $this->material = Material::with(['subject', 'teacher', 'attachments', 'comments.user'])
            ->findOrFail($this->material->id);
            
        $this->dispatch('toast', [
            'message' => 'Comment added',
            'type' => 'success'
        ]);
    }
    
    public function uploadSubmission()
    {
        $this->validate([
            'uploadedFile' => 'required|file|max:10240', // 10MB max
            'submissionNote' => 'nullable|string|max:500',
            'childId' => 'required',
        ]);
        
        try {
            $filename = $this->uploadedFile->getClientOriginalName();
            $path = $this->uploadedFile->storeAs(
                'material_submissions/' . $this->material->id,
                $filename,
                'public'
            );
            
            // Create submission record
            $this->material->submissions()->create([
                'child_id' => $this->childId,
                'user_id' => $this->user->id,
                'file_name' => $filename,
                'file_path' => $path,
                'file_type' => $this->uploadedFile->getMimeType(),
                'file_size' => $this->uploadedFile->getSize(),
                'notes' => $this->submissionNote,
            ]);
            
            // Update progress to 100% on submission
            $this->updateProgress(100);
            
            $this->uploadedFile = null;
            $this->submissionNote = '';
            $this->showSubmitModal = false;
            
            $this->dispatch('toast', [
                'message' => 'Work submitted successfully!',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'message' => 'Failed to upload: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }
    
    public function changeChild()
    {
        $this->loadProgress();
        $this->checkBookmarkStatus();
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
            'quiz' => 'o-question-mark-circle',
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
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="max-w-6xl mx-auto">
        <!-- Breadcrumb and Back Button -->
        <div class="flex items-center justify-between mb-6">
            <div class="text-sm breadcrumbs">
                <ul>
                    <li><a href="{{ route('parents.dashboard') }}">Dashboard</a></li>
                    <li><a href="{{ route('parents.materials.index') }}">Learning Materials</a></li>
                    <li>{{ $material->title }}</li>
                </ul>
            </div>
            <a href="{{ route('parents.materials.index') }}" class="btn btn-outline btn-sm">
                <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                Back to Materials
            </a>
        </div>

        <!-- Material Header Card -->
        <div class="card bg-base-100 shadow-xl mb-6 overflow-hidden">
            <div class="p-6 md:p-8">
                <div class="flex flex-col md:flex-row md:items-start gap-6">
                    <!-- Type Icon -->
                    <div class="w-20 h-20 rounded-xl flex items-center justify-center {{ $this->getMaterialColor($material->type) }} shrink-0">
                        <x-icon name="{{ $this->getMaterialIcon($material->type) }}" class="w-10 h-10" />
                    </div>
                    
                    <!-- Title and Info -->
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold mb-2">{{ $material->title }}</h1>
                        
                        <div class="flex flex-wrap gap-2 mb-4">
                            <div class="badge {{ $this->getMaterialColor($material->type) }}">
                                {{ ucfirst($material->type) }}
                            </div>
                            <div class="badge badge-outline">{{ $material->subject?->name ?? 'General' }}</div>
                            @if($material->grade_level)
                                <div class="badge badge-outline">Grade {{ $material->grade_level }}</div>
                            @endif
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-6 text-sm text-base-content/70">
                            <div class="flex items-center">
                                <x-icon name="o-user" class="w-4 h-4 mr-2" />
                                By: {{ $material->teacher?->name ?? 'System' }}
                            </div>
                            <div class="flex items-center">
                                <x-icon name="o-calendar" class="w-4 h-4 mr-2" />
                                Added: {{ $material->created_at->format('M d, Y') }}
                            </div>
                            <div class="flex items-center">
                                <x-icon name="o-clock" class="w-4 h-4 mr-2" />
                                Duration: {{ $material->estimated_time ?? 'Not specified' }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Child Progress Tracking -->
                    @if(count($children) > 0)
                        <div class="bg-base-200 p-4 rounded-xl min-w-60">
                            <div class="mb-3">
                                <label class="text-sm font-medium mb-1 block">Select Child</label>
                                <select class="select select-bordered w-full" wire:model.live="childId" wire:change="changeChild">
                                    @foreach($children as $child)
                                        <option value="{{ $child->id }}">{{ $child->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="divider my-2"></div>
                            
                            <div class="mb-3">
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium">Progress</span>
                                    <span class="text-sm">{{ $progress }}%</span>
                                </div>
                                <div class="w-full bg-base-300 rounded-full h-2.5">
                                    <div 
                                        class="h-2.5 rounded-full transition-all duration-500 {{ $progress >= 100 ? 'bg-success' : 'bg-primary' }}"
                                        style="width: {{ $progress }}%"
                                    ></div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between gap-2">
                                <button 
                                    class="btn btn-outline btn-sm flex-1"
                                    wire:click="toggleBookmark"
                                >
                                    <x-icon name="{{ $bookmarked ? 'o-bookmark-slash' : 'o-bookmark' }}" class="w-4 h-4 mr-1" />
                                    {{ $bookmarked ? 'Remove' : 'Bookmark' }}
                                </button>
                                
                                <button 
                                    class="btn btn-primary btn-sm flex-1"
                                    wire:click="$set('showSubmitModal', true)"
                                >
                                    <x-icon name="o-paper-airplane" class="w-4 h-4 mr-1" />
                                    Submit
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Tab Navigation -->
            <div class="tabs tabs-bordered px-6">
                <button 
                    class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}" 
                    wire:click="$set('activeTab', 'overview')"
                >
                    Overview
                </button>
                <button 
                    class="tab {{ $activeTab === 'content' ? 'tab-active' : '' }}" 
                    wire:click="$set('activeTab', 'content')"
                >
                    Content
                </button>
                <button 
                    class="tab {{ $activeTab === 'attachments' ? 'tab-active' : '' }}" 
                    wire:click="$set('activeTab', 'attachments')"
                >
                    Attachments
                </button>
                <button 
                    class="tab {{ $activeTab === 'comments' ? 'tab-active' : '' }}" 
                    wire:click="$set('activeTab', 'comments')"
                >
                    Comments
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content Area (2/3) -->
            <div class="lg:col-span-2">
                <!-- Content Cards -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        @if($activeTab === 'overview')
                            <h2 class="card-title">
                                <x-icon name="o-information-circle" class="w-5 h-5 mr-2" />
                                Overview
                            </h2>
                            
                            <div class="divider my-2"></div>
                            
                            <div class="prose max-w-none mb-6">
                                {{ $material->description }}
                            </div>
                            
                            @if($material->learning_objectives)
                                <div class="bg-base-200 p-4 rounded-lg mb-6">
                                    <h3 class="font-bold mb-3">Learning Objectives</h3>
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach(explode("\n", $material->learning_objectives) as $objective)
                                            @if(trim($objective))
                                                <li>{{ trim($objective) }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            
                            @if($material->teacher)
                                <div class="flex items-center gap-4 bg-base-200 p-4 rounded-lg">
                                    <div class="avatar placeholder">
                                        <div class="bg-primary text-primary-content rounded-full w-16">
                                            <span class="text-xl">{{ substr($material->teacher->name, 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium">Created by {{ $material->teacher->name }}</div>
                                        <div class="text-sm text-base-content/70">{{ $material->teacher->teacherProfile?->title ?? 'Teacher' }}</div>
                                        @if($material->teacher)
                                            <a href="{{ route('parents.messages.show', ['conversation' => $material->teacher_id]) }}" class="btn btn-outline btn-sm mt-2">
                                                <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 mr-2" />
                                                Message Teacher
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            
                        @elseif($activeTab === 'content')
                            <h2 class="card-title">
                                <x-icon name="o-document-text" class="w-5 h-5 mr-2" />
                                Content
                            </h2>
                            
                            <div class="divider my-2"></div>
                            
                            @if($material->content)
                                <div class="prose max-w-none">
                                    {!! $material->content !!}
                                </div>
                            @elseif($material->external_url)
                                <div class="flex flex-col items-center py-10">
                                    <x-icon name="o-link" class="w-16 h-16 text-primary mb-4" />
                                    <h3 class="text-xl font-bold mb-2">External Resource</h3>
                                    <p class="text-base-content/70 mb-6 text-center max-w-md">
                                        This material is hosted externally. Click the button below to access it.
                                    </p>
                                    <a href="{{ $material->external_url }}" target="_blank" class="btn btn-primary">
                                        <x-icon name="o-arrow-top-right-on-square" class="w-5 h-5 mr-2" />
                                        Open External Resource
                                    </a>
                                </div>
                            @elseif($material->type === 'video' && $material->video_url)
                                <div class="aspect-video w-full mb-4 bg-base-300 rounded-lg overflow-hidden">
                                    <iframe 
                                        src="{{ str_replace('watch?v=', 'embed/', $material->video_url) }}" 
                                        class="w-full h-full" 
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen
                                    ></iframe>
                                </div>
                                
                                @if($material->video_transcript)
                                    <div class="mt-6">
                                        <h3 class="font-bold mb-2">Video Transcript</h3>
                                        <div class="prose max-w-none bg-base-200 p-4 rounded-lg">
                                            {{ $material->video_transcript }}
                                        </div>
                                    </div>
                                @endif
                            @elseif($material->type === 'audio' && $material->audio_url)
                                <div class="bg-base-200 p-4 rounded-lg">
                                    <audio controls class="w-full">
                                        <source src="{{ $material->audio_url }}" type="audio/mpeg">
                                        Your browser does not support the audio element.
                                    </audio>
                                </div>
                                
                                @if($material->audio_transcript)
                                    <div class="mt-6">
                                        <h3 class="font-bold mb-2">Audio Transcript</h3>
                                        <div class="prose max-w-none bg-base-200 p-4 rounded-lg">
                                            {{ $material->audio_transcript }}
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="py-8 text-center">
                                    <x-icon name="o-document" class="w-16 h-16 mx-auto text-base-content/30" />
                                    <h3 class="mt-4 text-lg font-medium">No content available</h3>
                                    <p class="mt-1 text-base-content/70">
                                        This material has attachments but no main content.
                                    </p>
                                </div>
                            @endif
                            
                            @if($children->isNotEmpty())
                                <div class="mt-8">
                                    <h3 class="font-bold mb-3">Mark Your Progress</h3>
                                    <div class="flex flex-col gap-2">
                                        <input 
                                            type="range" 
                                            min="0" 
                                            max="100" 
                                            value="{{ $progress }}" 
                                            class="range range-primary" 
                                            step="25"
                                            wire:model.live="progress"
                                            wire:change="updateProgress($event.target.value)"
                                        />
                                        <div class="w-full flex justify-between text-xs px-2">
                                            <span>Not Started</span>
                                            <span>In Progress</span>
                                            <span>Mostly Done</span>
                                            <span>Completed</span>
                                            <span>Mastered</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <h3 class="font-bold mb-3">Rate This Material</h3>
                                    <div class="rating rating-lg">
                                        @for($i = 1; $i <= 5; $i++)
                                            <input 
                                                type="radio" 
                                                name="rating" 
                                                class="mask mask-star-2 bg-orange-400" 
                                                value="{{ $i }}" 
                                                @checked($rating === $i)
                                                wire:click="updateRating({{ $i }})"
                                            />
                                        @endfor
                                    </div>
                                </div>
                            @endif
                            
                        @elseif($activeTab === 'attachments')
                            <h2 class="card-title">
                                <x-icon name="o-paper-clip" class="w-5 h-5 mr-2" />
                                Attachments
                            </h2>
                            
                            <div class="divider my-2"></div>
                            
                            @if($material->attachments->isEmpty())
                                <div class="py-8 text-center">
                                    <x-icon name="o-document" class="w-16 h-16 mx-auto text-base-content/30" />
                                    <h3 class="mt-4 text-lg font-medium">No attachments available</h3>
                                    <p class="mt-1 text-base-content/70">
                                        This material doesn't have any downloadable attachments.
                                    </p>
                                </div>
                            @else
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    @foreach($material->attachments as $attachment)
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
                                                            'ppt', 'pptx' => 'text-orange-500',
                                                            'zip', 'rar' => 'text-yellow-500',
                                                            default => 'text-gray-500'
                                                        };
                                                    @endphp
                                                    
                                                    <div class="w-10 h-10 flex items-center justify-center rounded bg-base-300 {{ $iconClass }}">
                                                        <x-icon name="o-document" class="w-6 h-6" />
                                                    </div>
                                                    
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium truncate">{{ $attachment->file_name }}</div>
                                                        <div class="text-xs text-base-content/70">
                                                            {{ number_format($attachment->file_size / 1024, 2) }} KB
                                                            · {{ strtoupper($extension) }}
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="tooltip" data-tip="Download">
                                                        <x-icon name="o-arrow-down-tray" class="w-5 h-5 text-primary" />
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            
                        @elseif($activeTab === 'comments')
                            <h2 class="card-title">
                                <x-icon name="o-chat-bubble-left-right" class="w-5 h-5 mr-2" />
                                Comments & Questions
                            </h2>
                            
                            <div class="divider my-2"></div>
                            
                            <!-- Comment Form -->
                            <div class="mb-6">
                                <div class="flex items-start gap-3">
                                    <div class="avatar placeholder">
                                        <div class="bg-neutral text-neutral-content rounded-full w-10">
                                            <span>{{ substr($user->name, 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <textarea 
                                            class="textarea textarea-bordered w-full" 
                                            placeholder="Add a comment or question..."
                                            rows="3"
                                            wire:model="comment"
                                        ></textarea>
                                        @error('comment') <span class="text-error text-sm">{{ $message }}</span> @enderror
                                        
                                        <div class="flex justify-end mt-2">
                                            <button 
                                                class="btn btn-primary btn-sm" 
                                                wire:click="submitComment"
                                                wire:loading.attr="disabled"
                                                wire:target="submitComment"
                                            >
                                                <span wire:loading.remove wire:target="submitComment">Submit</span>
                                                <span wire:loading wire:target="submitComment">Submitting...</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Comments List -->
                            @if($material->comments->isEmpty())
                                <div class="py-8 text-center">
                                    <x-icon name="o-chat-bubble-left-right" class="w-16 h-16 mx-auto text-base-content/30" />
                                    <h3 class="mt-4 text-lg font-medium">No comments yet</h3>
                                    <p class="mt-1 text-base-content/70">
                                        Be the first to add a comment or question.
                                    </p>
                                </div>
                            @else
                                <div class="space-y-6">
                                    @foreach($material->comments->sortByDesc('created_at') as $commentItem)
                                        <div class="flex gap-3">
                                            <div class="avatar placeholder">
                                                <div class="bg-neutral text-neutral-content rounded-full w-10">
                                                    <span>{{ substr($commentItem->user?->name ?? 'U', 0, 1) }}</span>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between">
                                                    <div class="font-medium">{{ $commentItem->user?->name ?? 'Unknown User' }}</div>
                                                    <div class="text-xs text-base-content/70">{{ $commentItem->created_at->format('M d, Y - h:i A') }}</div>
                                                </div>
                                                <div class="mt-2 text-base-content/90">
                                                    {{ $commentItem->comment }}
                                                </div>
                                                
                                                @if($commentItem->user_id === $user->id)
                                                    <div class="flex justify-end mt-2">
                                                        <button class="btn btn-ghost btn-xs text-error">
                                                            <x-icon name="o-trash" class="w-4 h-4 mr-1" />
                                                            Delete
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Right Sidebar (1/3) -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title mb-4">
                            <x-icon name="o-bolt" class="w-5 h-5 mr-2" />
                            Quick Actions
                        </h2>
                        <div class="space-y-3">
                            @if($material->external_url)
                                <a href="{{ $material->external_url }}" target="_blank" class="btn btn-primary btn-block">
                                    <x-icon name="o-arrow-top-right-on-square" class="w-5 h-5 mr-2" />
                                    Open Resource
                                </a>
                            @endif
                            
                            @if($children->isNotEmpty())
                                <button 
                                    class="btn btn-outline btn-block"
                                    wire:click="$set('showSubmitModal', true)"
                                >
                                    <x-icon name="o-paper-airplane" class="w-5 h-5 mr-2" />
                                    Submit Work
                                </button>
                                
                                <button 
                                    class="btn btn-outline btn-block"
                                    wire:click="toggleBookmark"
                                >
                                    <x-icon name="{{ $bookmarked ? 'o-bookmark-slash' : 'o-bookmark' }}" class="w-5 h-5 mr-2" />
                                    {{ $bookmarked ? 'Remove Bookmark' : 'Add Bookmark' }}
                                </button>
                            @endif
                            
                            <a href="{{ route('parents.materials.index') }}" class="btn btn-outline btn-block">
                                <x-icon name="o-rectangle-stack" class="w-5 h-5 mr-2" />
                                Browse Materials
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Related Materials -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title mb-4">
                            <x-icon name="o-document-duplicate" class="w-5 h-5 mr-2" />
                            Related Materials
                        </h2>
                        
                        @if($relatedMaterials->isEmpty())
                            <div class="py-4 text-center">
                                <p class="text-sm text-base-content/70">No related materials found</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($relatedMaterials as $relatedMaterial)
                                    <a href="{{ route('materials.show', $relatedMaterial) }}" class="block p-3 rounded-lg bg-base-200 hover:bg-base-300 transition-colors">
                                        <div class="flex items-start gap-3">
                                            <div class="w-8 h-8 rounded-md flex items-center justify-center {{ $this->getMaterialColor($relatedMaterial->type) }}">
                                                <x-icon name="{{ $this->getMaterialIcon($relatedMaterial->type) }}" class="w-4 h-4" />
                                            </div>
                                            <div>
                                                <div class="font-medium line-clamp-1">{{ $relatedMaterial->title }}</div>
                                                <div class="text-xs text-base-content/70">{{ $relatedMaterial->subject?->name ?? 'General' }}</div>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                
                <!-- Material Information -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title mb-4">
                            <x-icon name="o-information-circle" class="w-5 h-5 mr-2" />
                            Material Information
                        </h2>
                        
                        <table class="w-full">
                            <tbody>
                                <tr class="border-b">
                                    <td class="py-2 text-base-content/70">Type</td>
                                    <td class="py-2 text-right font-medium">{{ ucfirst($material->type) }}</td>
                                </tr>
                                <tr class="border-b">
                                    <td class="py-2 text-base-content/70">Subject</td>
                                    <td class="py-2 text-right font-medium">{{ $material->subject?->name ?? 'General' }}</td>
                                </tr>
                                @if($material->grade_level)
                                    <tr class="border-b">
                                        <td class="py-2 text-base-content/70">Grade Level</td>
                                        <td class="py-2 text-right font-medium">{{ $material->grade_level }}</td>
                                    </tr>
                                @endif
                                <tr class="border-b">
                                    <td class="py-2 text-base-content/70">Created By</td>
                                    <td class="py-2 text-right font-medium">{{ $material->teacher?->name ?? 'System' }}</td>
                                </tr>
                                <tr class="border-b">
                                    <td class="py-2 text-base-content/70">Added On</td>
                                    <td class="py-2 text-right font-medium">{{ $material->created_at->format('M d, Y') }}</td>
                                </tr>
                                @if($material->estimated_time)
                                    <tr class="border-b">
                                        <td class="py-2 text-base-content/70">Est. Duration</td>
                                        <td class="py-2 text-right font-medium">{{ $material->estimated_time }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="py-2 text-base-content/70">Attachments</td>
                                    <td class="py-2 text-right font-medium">{{ $material->attachments->count() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Work Submission Modal -->
    @if($showSubmitModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 z-40 flex items-center justify-center p-4">
            <div class="modal-box max-w-md w-full">
                <h3 class="font-bold text-lg mb-4">Submit Work</h3>
                <button wire:click="$set('showSubmitModal', false)" class="absolute btn btn-sm btn-circle btn-ghost right-2 top-2">✕</button>
                
                <form wire:submit.prevent="uploadSubmission">
                    <div class="form-control mb-4">
                        <label class="label">
                            <span class="label-text">Select Child</span>
                        </label>
                        <select class="select select-bordered w-full" wire:model="childId">
                            <option value="">-- Select Child --</option>
                            @foreach($children as $child)
                                <option value="{{ $child->id }}">{{ $child->name }}</option>
                            @endforeach
                        </select>
                        @error('childId') <span class="text-error text-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="form-control mb-4">
                        <label class="label">
                            <span class="label-text">Upload File</span>
                        </label>
                        <input 
                            type="file" 
                            class="file-input file-input-bordered w-full" 
                            wire:model="uploadedFile"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip"
                        />
                        @error('uploadedFile') <span class="text-error text-sm mt-1">{{ $message }}</span> @enderror
                        
                        <div class="text-xs text-base-content/70 mt-2">
                            Accepted formats: PDF, DOC, DOCX, JPG, JPEG, PNG, ZIP (Max 10MB)
                        </div>
                    </div>
                    
                    <div class="form-control mb-4">
                        <label class="label">
                            <span class="label-text">Note (Optional)</span>
                        </label>
                        <textarea 
                            class="textarea textarea-bordered" 
                            placeholder="Add a note about your submission..."
                            wire:model="submissionNote"
                            rows="3"
                        ></textarea>
                        @error('submissionNote') <span class="text-error text-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button 
                            type="button" 
                            class="btn btn-ghost mr-2" 
                            wire:click="$set('showSubmitModal', false)"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="btn btn-primary" 
                            wire:loading.attr="disabled"
                            wire:target="uploadSubmission"
                        >
                            <span wire:loading.remove wire:target="uploadSubmission">Submit</span>
                            <span wire:loading wire:target="uploadSubmission">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Uploading...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>