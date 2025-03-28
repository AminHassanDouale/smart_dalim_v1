<?php

use Livewire\Volt\Component;
use App\Models\Homework;
use App\Models\HomeworkAttachment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;
    
    public $homework;
    public $uploadedFile;
    public $submissionComment;
    public $uploadingProgress = false;
    public $showDeleteModal = false;
    public $showSubmitModal = false;
    
    // Progress and achievement
    public $progress = 0;
    public $completionTime = null;
    public $showCompletionModal = false;
    
    public function mount($homework)
    {
        $this->homework = Homework::with(['child', 'subject', 'teacher', 'attachments'])
            ->findOrFail($homework);
        
        // Check if the homework belongs to one of the parent's children
        $user = Auth::user();
        $childrenIds = $user->parentProfile->children()->pluck('id')->toArray();
        
        if (!in_array($this->homework->child_id, $childrenIds)) {
            return redirect()->route('parents.homework.index')
                ->with('error', 'You do not have permission to view this homework.');
        }
        
        $this->initializeProgress();
    }
    
    private function initializeProgress()
    {
        if ($this->homework->is_completed) {
            $this->progress = 100;
            $this->completionTime = $this->homework->completed_at;
        } else {
            // Calculate approximate progress if not completed
            $totalDays = Carbon::parse($this->homework->created_at)
                ->diffInDays(Carbon::parse($this->homework->due_date));
            
            if ($totalDays > 0) {
                $daysElapsed = Carbon::parse($this->homework->created_at)
                    ->diffInDays(now());
                $this->progress = min(95, ($daysElapsed / $totalDays) * 100);
            } else {
                $this->progress = 50; // Default progress if assigned and due on the same day
            }
        }
    }
    
    public function toggleHomeworkStatus()
    {
        $this->homework->is_completed = !$this->homework->is_completed;
        $this->homework->completed_at = $this->homework->is_completed ? now() : null;
        $this->homework->save();
        
        $this->initializeProgress();
        
        if ($this->homework->is_completed) {
            $this->showCompletionModal = true;
        }
        
        $this->dispatch('toast', [
            'message' => $this->homework->is_completed 
                ? 'Homework marked as completed!' 
                : 'Homework marked as pending.',
            'type' => 'success'
        ]);
    }
    
    public function uploadSubmission()
    {
        $this->validate([
            'uploadedFile' => 'required|file|max:10240', // 10MB max
            'submissionComment' => 'nullable|string|max:500',
        ]);
        
        $this->uploadingProgress = true;
        
        try {
            $filename = $this->uploadedFile->getClientOriginalName();
            $path = $this->uploadedFile->storeAs(
                'homework_submissions/' . $this->homework->id,
                $filename,
                'public'
            );
            
            HomeworkAttachment::create([
                'homework_id' => $this->homework->id,
                'file_name' => $filename,
                'file_path' => $path,
                'file_type' => $this->uploadedFile->getMimeType(),
                'file_size' => $this->uploadedFile->getSize(),
            ]);
            
            // Add submission comment if provided
            if ($this->submissionComment) {
                // Here you might add the comment to a comments table
                // or update the homework with the comment
            }
            
            $this->uploadedFile = null;
            $this->submissionComment = null;
            $this->showSubmitModal = false;
            
            // Refresh homework data
            $this->homework = Homework::with(['child', 'subject', 'teacher', 'attachments'])
                ->findOrFail($this->homework->id);
                
            $this->dispatch('toast', [
                'message' => 'Submission uploaded successfully!',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            $this->dispatch('toast', [
                'message' => 'Failed to upload submission: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
        
        $this->uploadingProgress = false;
    }
    
    public function deleteAttachment($attachmentId)
    {
        $attachment = HomeworkAttachment::findOrFail($attachmentId);
        
        // Check if the attachment belongs to this homework
        if ($attachment->homework_id !== $this->homework->id) {
            $this->dispatch('toast', [
                'message' => 'You do not have permission to delete this attachment.',
                'type' => 'error'
            ]);
            return;
        }
        
        // Delete the file from storage
        if (\Storage::disk('public')->exists($attachment->file_path)) {
            \Storage::disk('public')->delete($attachment->file_path);
        }
        
        // Delete the record
        $attachment->delete();
        
        // Refresh homework data
        $this->homework = Homework::with(['child', 'subject', 'teacher', 'attachments'])
            ->findOrFail($this->homework->id);
            
        $this->showDeleteModal = false;
        
        $this->dispatch('toast', [
            'message' => 'Attachment deleted successfully.',
            'type' => 'success'
        ]);
    }
    
    public function getDaysRemainingText()
    {
        if ($this->homework->is_completed) {
            return 'Completed';
        }
        
        $dueDate = Carbon::parse($this->homework->due_date);
        $now = Carbon::now();
        
        if ($dueDate->isPast()) {
            $days = $now->diffInDays($dueDate);
            return $days === 0 ? 'Due today (overdue)' : "$days days overdue";
        } else {
            $days = $now->diffInDays($dueDate);
            return $days === 0 ? 'Due today' : "$days days remaining";
        }
    }
    
    public function getStatusClass()
    {
        if ($this->homework->is_completed) {
            return 'text-success';
        } elseif (Carbon::parse($this->homework->due_date)->isPast()) {
            return 'text-error';
        } elseif (Carbon::parse($this->homework->due_date)->diffInDays(now()) <= 2) {
            return 'text-warning';
        } else {
            return 'text-info';
        }
    }
    
    public function confirmDeleteAttachment($attachmentId)
    {
        $this->attachmentToDelete = $attachmentId;
        $this->showDeleteModal = true;
    }
    
    public function openSubmitModal()
    {
        $this->showSubmitModal = true;
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="max-w-5xl mx-auto">
        <!-- Breadcrumb and Back Button -->
        <div class="flex items-center justify-between mb-6">
            <div class="text-sm breadcrumbs">
                <ul>
                    <li><a href="{{ route('parents.dashboard') }}">Dashboard</a></li>
                    <li><a href="{{ route('parents.homework.index') }}">Homework</a></li>
                    <li>Details</li>
                </ul>
            </div>
            <a href="{{ route('parents.homework.index') }}" class="btn btn-outline btn-sm">
                <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                Back to List
            </a>
        </div>

        <!-- Homework Header -->
        <div class="card bg-base-100 shadow-xl mb-6 overflow-hidden">
            <div class="p-6 border-b border-base-200">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold">{{ $homework->title }}</h1>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <div class="badge badge-primary">{{ $homework->subject?->name ?? 'Unknown Subject' }}</div>
                            <div class="badge {{ $homework->is_completed ? 'badge-success' : ($homework->due_date < now() ? 'badge-error' : 'badge-info') }}">
                                {{ $homework->is_completed ? 'Completed' : ($homework->due_date < now() ? 'Overdue' : 'Pending') }}
                            </div>
                        </div>
                    </div>
                    <div class="md:text-right">
                        <div class="text-sm text-base-content/70">Assigned to</div>
                        <div class="font-medium flex items-center md:justify-end">
                            <div class="avatar placeholder mr-2">
                                <div class="bg-neutral text-neutral-content rounded-full w-8">
                                    <span>{{ substr($homework->child?->name ?? 'U', 0, 1) }}</span>
                                </div>
                            </div>
                            {{ $homework->child?->name ?? 'Unknown Student' }}
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="px-6 py-4 bg-base-100">
                <div class="flex justify-between mb-2">
                    <span class="text-sm">Progress</span>
                    <span class="text-sm font-medium">{{ $progress }}%</span>
                </div>
                <div class="w-full bg-base-200 rounded-full h-3">
                    <div class="h-3 rounded-full transition-all duration-500 {{ $homework->is_completed ? 'bg-success' : ($homework->due_date < now() ? 'bg-error' : 'bg-primary') }}" 
                         style="width: {{ $progress }}%"></div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Description Card -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">
                            <x-icon name="o-document-text" class="w-5 h-5 mr-2" />
                            Assignment Details
                        </h2>
                        
                        <div class="divider my-2"></div>
                        
                        <div class="prose max-w-none">
                            {{ $homework->description }}
                        </div>
                        
                        @if($homework->max_score)
                            <div class="mt-4 p-4 bg-base-200 rounded-lg">
                                <div class="flex justify-between">
                                    <span class="font-medium">Maximum Score:</span>
                                    <span>{{ $homework->max_score }} points</span>
                                </div>
                                
                                @if($homework->achieved_score)
                                    <div class="flex justify-between mt-2">
                                        <span class="font-medium">Achieved Score:</span>
                                        <span class="{{ $homework->achieved_score >= ($homework->max_score * 0.7) ? 'text-success' : 'text-warning' }}">
                                            {{ $homework->achieved_score }} points
                                        </span>
                                    </div>
                                    
                                    <div class="w-full bg-base-300 rounded-full h-2.5 mt-3">
                                        <div class="h-2.5 rounded-full {{ $homework->achieved_score >= ($homework->max_score * 0.7) ? 'bg-success' : 'bg-warning' }}"
                                             style="width: {{ ($homework->achieved_score / $homework->max_score) * 100 }}%"></div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                
                <!-- Teacher Feedback Card -->
                @if($homework->teacher_feedback)
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">
                            <h2 class="card-title">
                                <x-icon name="o-chat-bubble-left-right" class="w-5 h-5 mr-2" />
                                Teacher Feedback
                            </h2>
                            
                            <div class="divider my-2"></div>
                            
                            <div class="flex items-start gap-3">
                                <div class="avatar placeholder">
                                    <div class="bg-primary text-primary-content rounded-full w-10">
                                        <span>{{ substr($homework->teacher?->name ?? 'T', 0, 1) }}</span>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium">{{ $homework->teacher?->name ?? 'Teacher' }}</div>
                                    <div class="text-xs text-base-content/70 mb-2">
                                        {{ $homework->updated_at?->format('M d, Y - h:i A') ?? 'Date unknown' }}
                                    </div>
                                    <div class="p-4 bg-base-200 rounded-lg">
                                        {{ $homework->teacher_feedback }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                
                <!-- Attachments Card -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <div class="flex justify-between items-center">
                            <h2 class="card-title">
                                <x-icon name="o-paper-clip" class="w-5 h-5 mr-2" />
                                Attachments & Submissions
                            </h2>
                            
                            <button class="btn btn-primary btn-sm" wire:click="openSubmitModal">
                                <x-icon name="o-arrow-up-tray" class="w-4 h-4 mr-2" />
                                Submit Work
                            </button>
                        </div>
                        
                        <div class="divider my-2"></div>
                        
                        @if($homework->attachments->isEmpty())
                            <div class="py-8 text-center">
                                <x-icon name="o-document" class="w-12 h-12 mx-auto text-base-content/30" />
                                <h3 class="mt-2 text-base font-medium">No attachments yet</h3>
                                <p class="mt-1 text-sm text-base-content/70">Upload assignments or homework resources</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($homework->attachments as $attachment)
                                    <div class="card bg-base-200">
                                        <div class="card-body p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center flex-1 min-w-0">
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
                                                    
                                                    <div class="w-10 h-10 flex items-center justify-center rounded bg-base-300 {{ $iconClass }} mr-3">
                                                        <x-icon name="o-document" class="w-6 h-6" />
                                                    </div>
                                                    
                                                    <div class="flex-1 min-w-0">
                                                        <h3 class="font-medium truncate">{{ $attachment->file_name }}</h3>
                                                        <p class="text-xs text-base-content/70">
                                                            {{ $attachment->created_at?->format('M d, Y') }} 
                                                            · {{ number_format($attachment->file_size / 1024, 2) }} KB
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <div class="dropdown dropdown-end">
                                                    <label tabindex="0" class="btn btn-ghost btn-sm p-1">
                                                        <x-icon name="o-ellipsis-vertical" class="w-5 h-5" />
                                                    </label>
                                                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-40">
                                                        <li>
                                                            <a href="{{ asset('storage/' . $attachment->file_path) }}" target="_blank">
                                                                <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                                                Download
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="#" wire:click.prevent="confirmDeleteAttachment({{ $attachment->id }})">
                                                                <x-icon name="o-trash" class="w-4 h-4" />
                                                                Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Right Column (1/3) Sidebar -->
            <div class="space-y-6">
                <!-- Homework Status Card -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">
                            <x-icon name="o-clock" class="w-5 h-5 mr-2" />
                            Deadline
                        </h2>
                        
                        <div class="divider my-2"></div>
                        
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-base-content/70">Due Date</div>
                            <div class="font-medium">{{ Carbon::parse($homework->due_date)->format('M d, Y') }}</div>
                        </div>
                        
                        <div class="flex items-center justify-between mt-1">
                            <div class="text-sm text-base-content/70">Due Time</div>
                            <div class="font-medium">{{ Carbon::parse($homework->due_date)->format('h:i A') }}</div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="font-medium mb-2 {{ $this->getStatusClass() }}">
                                {{ $this->getDaysRemainingText() }}
                            </div>
                            
                            @if(!$homework->is_completed)
                                <div class="radial-progress text-primary mx-auto flex items-center justify-center" 
                                     style="--value:{{ min(100, max(0, 100 - Carbon::parse($homework->due_date)->diffInHours(now()) / 24 * 10)) }}; --size: 5rem; --thickness: 5px;">
                                    <span class="text-sm">
                                        {{ Carbon::parse($homework->due_date)->diffForHumans(['parts' => 1]) }}
                                    </span>
                                </div>
                            @endif
                        </div>
                        
                        <div class="card-actions mt-4">
                            <button 
                                class="btn {{ $homework->is_completed ? 'btn-error' : 'btn-success' }} btn-block"
                                wire:click="toggleHomeworkStatus">
                                @if($homework->is_completed)
                                    <x-icon name="o-x-mark" class="w-5 h-5 mr-2" />
                                    Mark as Incomplete
                                @else
                                    <x-icon name="o-check" class="w-5 h-5 mr-2" />
                                    Mark as Complete
                                @endif
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Assigned By Card -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">
                            <x-icon name="o-user" class="w-5 h-5 mr-2" />
                            Assigned By
                        </h2>
                        
                        <div class="divider my-2"></div>
                        
                        <div class="flex items-center gap-4">
                            <div class="avatar placeholder">
                                <div class="bg-primary text-primary-content rounded-full w-16">
                                    <span class="text-xl">{{ substr($homework->teacher?->name ?? 'T', 0, 1) }}</span>
                                </div>
                            </div>
                            
                            <div>
                                <div class="font-medium text-lg">{{ $homework->teacher?->name ?? 'Unknown Teacher' }}</div>
                                <div class="text-sm opacity-70">{{ $homework->teacher?->teacherProfile?->title ?? 'Teacher' }}</div>
                                
                                @if($homework->teacher)
                                    <a href="{{ route('parents.messages.show', ['conversation' => $homework->teacher_id]) }}" class="btn btn-outline btn-sm mt-2">
                                        <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 mr-2" />
                                        Message Teacher
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Related Homework Card -->
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <h2 class="card-title">
                            <x-icon name="o-document-duplicate" class="w-5 h-5 mr-2" />
                            Related Homework
                        </h2>
                        
                        <div class="divider my-2"></div>
                        
                        @php
                            // Get related homework (same subject, same child)
                            $relatedHomework = \App\Models\Homework::where('id', '!=', $homework->id)
                                ->where(function($query) use ($homework) {
                                    $query->where('subject_id', $homework->subject_id)
                                        ->where('child_id', $homework->child_id);
                                })
                                ->latest()
                                ->take(3)
                                ->get();
                        @endphp
                        
                        @if($relatedHomework->isEmpty())
                            <div class="py-4 text-center">
                                <p class="text-sm text-base-content/70">No related homework found</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($relatedHomework as $related)
                                    <a href="{{ route('parents.homework.show', $related) }}" class="block p-3 rounded-lg bg-base-200 hover:bg-base-300 transition-colors">
                                        <div class="font-medium truncate">{{ $related->title }}</div>
                                        <div class="flex justify-between items-center mt-1">
                                            <span class="text-xs text-base-content/70">
                                                Due: {{ Carbon::parse($related->due_date)->format('M d, Y') }}
                                            </span>
                                            <span class="badge badge-sm {{ $related->is_completed ? 'badge-success' : ($related->due_date < now() ? 'badge-error' : 'badge-info') }}">
                                                {{ $related->is_completed ? 'Completed' : ($related->due_date < now() ? 'Overdue' : 'Pending') }}
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                            
                            <div class="mt-2 text-center">
                                <a href="{{ route('parents.homework.index', ['childFilter' => $homework->child_id, 'subjectFilter' => $homework->subject_id]) }}" class="btn btn-ghost btn-sm">
                                    View All
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Submit Modal -->
    @if($showSubmitModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 z-40 flex items-center justify-center p-4">
            <div class="modal-box max-w-md w-full">
                <h3 class="font-bold text-lg mb-4">Submit Homework</h3>
                <button wire:click="$set('showSubmitModal', false)" class="absolute btn btn-sm btn-circle btn-ghost right-2 top-2">✕</button>
                
                <form wire:submit.prevent="uploadSubmission">
                    <div class="form-control mb-4">
                        <label class="label">
                            <span class="label-text">Upload File</span>
                        </label>
                        <input 
                            type="file" 
                            class="file-input file-input-bordered w-full" 
                            wire:model="uploadedFile"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                        />
                        @error('uploadedFile') <span class="text-error text-sm mt-1">{{ $message }}</span> @enderror
                        
                        <div class="text-xs text-base-content/70 mt-2">
                            Accepted formats: PDF, DOC, DOCX, JPG, JPEG, PNG (Max 10MB)
                        </div>
                    </div>
                    
                    <div class="form-control mb-4">
                        <label class="label">
                            <span class="label-text">Comment (Optional)</span>
                        </label>
                        <textarea 
                            class="textarea textarea-bordered" 
                            placeholder="Add a comment about your submission..."
                            wire:model="submissionComment"
                            rows="3"
                        ></textarea>
                        @error('submissionComment') <span class="text-error text-sm mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <p class="text-base-content/70 mt-2">Assignment: {{ $homework->title }}</p>
                    <p class="text-base-content/70">Completed on: {{ Carbon::parse($completionTime)->format('M d, Y - h:i A') }}</p>
                </div>
                
                <div class="mt-6">
                    <button class="btn btn-primary" wire:click="$set('showCompletionModal', false)">Continue</button>
                </div>
            </div>
        </div>
    @endif
</div>