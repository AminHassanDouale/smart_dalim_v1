<?php

namespace App\Livewire\Parents\Support;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\SupportAttachment;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public $user;
    public $ticket;
    public $messages;
    public $newMessage = '';
    public $attachments = [];
    public $maxAttachments = 3;

    // Rating
    public $showSatisfactionSurvey = false;
    public $satisfactionRating = 0;
    public $feedbackComment = '';

    // Close ticket confirmation
    public $showCloseConfirmation = false;

    // Related entity details
    public $relatedEntity = null;

    public function mount($ticket)
    {
        $this->user = Auth::user();

        // Load ticket with relations
        $this->ticket = SupportTicket::with(['messages.attachments', 'attachments'])
            ->where('user_id', $this->user->id)
            ->findOrFail($ticket);

        $this->messages = $this->ticket->messages;

        // Load related entity if exists
        if ($this->ticket->related_entity_type && $this->ticket->related_entity_id) {
            $this->loadRelatedEntity();
        }

        // Show satisfaction survey if ticket was recently resolved
        if ($this->ticket->status === 'resolved' && !$this->ticket->satisfaction_rating && $this->ticket->resolved_at) {
            $resolvedDate = Carbon::parse($this->ticket->resolved_at);
            // Show survey if resolved in the past 7 days
            if ($resolvedDate->diffInDays(now()) <= 7) {
                $this->showSatisfactionSurvey = true;
            }
        }
    }

    protected function rules()
    {
        return [
            'newMessage' => 'required|min:5',
            'attachments.*' => 'nullable|file|max:5120|mimes:jpeg,png,gif,pdf,txt,doc,docx'
        ];
    }

    public function sendMessage()
    {
        $this->validate();

        // Create message
        $message = SupportMessage::create([
            'support_ticket_id' => $this->ticket->id,
            'user_id' => $this->user->id,
            'message' => $this->newMessage,
            'message_type' => 'user',
        ]);

        // Save attachments
        foreach ($this->attachments as $attachment) {
            $path = $attachment->store('support-attachments/' . $this->ticket->id, 'public');

            SupportAttachment::create([
                'support_message_id' => $message->id,
                'file_path' => $path,
                'file_name' => $attachment->getClientOriginalName(),
                'file_size' => $attachment->getSize(),
                'file_type' => $attachment->getMimeType()
            ]);
        }

        // Update ticket status if it was closed
        if ($this->ticket->status === 'closed') {
            $this->ticket->update([
                'status' => 'open',
                'reopened_at' => now()
            ]);
        }

        // Clear form
        $this->newMessage = '';
        $this->attachments = [];

        // Refresh ticket data
        $this->ticket = $this->ticket->fresh(['messages.attachments', 'attachments']);
        $this->messages = $this->ticket->messages;

        $this->dispatch('message-sent');
    }

    public function removeAttachment($index)
    {
        array_splice($this->attachments, $index, 1);
    }

    public function loadRelatedEntity()
    {
        if ($this->ticket->related_entity_type === 'invoice') {
            $invoice = $this->user->invoices()->find($this->ticket->related_entity_id);
            if ($invoice) {
                $this->relatedEntity = [
                    'type' => 'invoice',
                    'title' => "Invoice #{$invoice->invoice_number}",
                    'date' => $invoice->created_at->format('M d, Y'),
                    'status' => $invoice->status,
                    'amount' => $invoice->amount,
                    'url' => route('parents.invoices.show', $invoice->id)
                ];
            }
        } elseif ($this->ticket->related_entity_type === 'session') {
            // Find the session across all children
            foreach ($this->user->parentProfile->children ?? [] as $child) {
                $session = $child->learningSessions()->find($this->ticket->related_entity_id);
                if ($session) {
                    $this->relatedEntity = [
                        'type' => 'session',
                        'title' => "{$child->name}'s " . ($session->subject->name ?? 'Session'),
                        'date' => $session->start_time->format('M d, Y h:i A'),
                        'status' => $session->status,
                        'url' => route('parents.sessions.show', $session->id)
                    ];
                    break;
                }
            }
        }
    }

    public function confirmCloseTicket()
    {
        $this->showCloseConfirmation = true;
    }

    public function closeTicket()
    {
        $this->ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by_user' => true
        ]);

        $this->ticket = $this->ticket->fresh();
        $this->showCloseConfirmation = false;

        $this->dispatch('ticket-closed');
    }

    public function cancelClose()
    {
        $this->showCloseConfirmation = false;
    }

    public function submitSatisfactionRating()
    {
        $this->validate([
            'satisfactionRating' => 'required|integer|min:1|max:5',
            'feedbackComment' => 'nullable|string'
        ]);

        $this->ticket->update([
            'satisfaction_rating' => $this->satisfactionRating,
            'satisfaction_comment' => $this->feedbackComment,
            'rated_at' => now()
        ]);

        $this->ticket = $this->ticket->fresh();
        $this->showSatisfactionSurvey = false;

        $this->dispatch('rating-submitted');
    }

    public function skipSatisfactionRating()
    {
        $this->showSatisfactionSurvey = false;
    }

    public function getStatusColor($status)
    {
        return match($status) {
            'open' => 'badge-warning',
            'in_progress' => 'badge-info',
            'resolved' => 'badge-success',
            'closed' => 'badge-neutral',
            default => 'badge-ghost'
        };
    }

    public function getPriorityColor($priority)
    {
        return match($priority) {
            'low' => 'bg-blue-100 text-blue-800',
            'medium' => 'bg-green-100 text-green-800',
            'high' => 'bg-orange-100 text-orange-800',
            'urgent' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    public function formatTimeAgo($dateTime)
    {
        return Carbon::parse($dateTime)->diffForHumans();
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200"
    x-data="{
        showToast: false,
        toastMessage: '',
        toastType: 'success',
        showToastNotification(message, type = 'success') {
            this.toastMessage = message;
            this.toastType = type;
            this.showToast = true;
            setTimeout(() => { this.showToast = false; }, 3000);
        }
    }"
    x-on:message-sent.window="showToastNotification('Message sent successfully')"
    x-on:ticket-closed.window="showToastNotification('Ticket closed successfully')"
    x-on:rating-submitted.window="showToastNotification('Thank you for your feedback')"
>
    <!-- Toast Notification -->
    <div
        x-show="showToast"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform scale-90"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-90"
        class="fixed z-50 max-w-sm shadow-lg right-4 top-4"
        :class="{
            'alert-success': toastType === 'success',
            'alert-error': toastType === 'error',
            'alert-warning': toastType === 'warning',
            'alert-info': toastType === 'info'
        }"
        class="alert"
    >
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center">
                <i
                    :class="{
                        'fa-check-circle': toastType === 'success',
                        'fa-circle-xmark': toastType === 'error',
                        'fa-triangle-exclamation': toastType === 'warning',
                        'fa-info-circle': toastType === 'info'
                    }"
                    class="mr-2 fa-solid"
                ></i>
                <span x-text="toastMessage"></span>
            </div>
            <button class="btn btn-ghost btn-xs" @click="showToast = false">×</button>
        </div>
    </div>

    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col items-start justify-between md:flex-row md:items-center">
                <div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('parents.support.index') }}" class="btn btn-ghost btn-sm">
                            <i class="fa-solid fa-arrow-left"></i>
                        </a>
                        <h1 class="text-2xl font-bold">Support Ticket: {{ $ticket->ticket_id }}</h1>
                    </div>
                    <p class="mt-1 text-base-content/70">{{ $ticket->title }}</p>
                </div>

                <div class="mt-3 md:mt-0">
                    @if($ticket->status !== 'closed')
                        <button
                            wire:click="confirmCloseTicket"
                            class="btn btn-outline btn-sm"
                        >
                            <i class="mr-1 fa-solid fa-check-circle"></i>
                            Close Ticket
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Ticket Details Sidebar -->
            <div class="lg:col-span-1">
                <div class="space-y-6">
                    <!-- Ticket Info Card -->
                    <div class="shadow-lg card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Ticket Information</h2>

                            <div class="mb-4 divider"></div>

                            <div class="space-y-3">
                                <div>
                                    <span class="text-sm text-base-content/70">Status:</span>
                                    <div class="mt-1">
                                        <span class="badge {{ $this->getStatusColor($ticket->status) }}">
                                            {{ ucfirst($ticket->status) }}
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <span class="text-sm text-base-content/70">Priority:</span>
                                    <div class="mt-1">
                                        <span class="px-2 py-1 text-xs rounded-full {{ $this->getPriorityColor($ticket->priority) }}">
                                            {{ ucfirst($ticket->priority) }}
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <span class="text-sm text-base-content/70">Category:</span>
                                    <div class="mt-1 font-medium capitalize">{{ $ticket->category }}</div>
                                </div>

                                <div>
                                    <span class="text-sm text-base-content/70">Created:</span>
                                    <div class="mt-1 font-medium">{{ $ticket->created_at->format('M d, Y g:i A') }}</div>
                                </div>

                                @if($ticket->status === 'resolved' || $ticket->status === 'closed')
                                    <div>
                                        <span class="text-sm text-base-content/70">
                                            {{ $ticket->status === 'resolved' ? 'Resolved' : 'Closed' }}:
                                        </span>
                                        <div class="mt-1 font-medium">
                                            {{ $ticket->status === 'resolved'
                                                ? ($ticket->resolved_at ? Carbon::parse($ticket->resolved_at)->format('M d, Y g:i A') : 'N/A')
                                                : ($ticket->closed_at ? Carbon::parse($ticket->closed_at)->format('M d, Y g:i A') : 'N/A')
                                            }}
                                        </div>
                                    </div>
                                @endif

                                @if($ticket->satisfaction_rating)
                                    <div>
                                        <span class="text-sm text-base-content/70">Satisfaction:</span>
                                        <div class="flex mt-1">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="text-yellow-500 fa-{{ $i <= $ticket->satisfaction_rating ? 'solid' : 'regular' }} fa-star"></i>
                                            @endfor
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Related Item -->
                    @if($relatedEntity)
                        <div class="shadow-lg card bg-base-100">
                            <div class="card-body">
                                <h2 class="card-title">Related {{ ucfirst($relatedEntity['type']) }}</h2>

                                <div class="my-2 divider"></div>

                                <div class="p-3 rounded-lg bg-base-200">
                                    <div class="font-semibold">{{ $relatedEntity['title'] }}</div>
                                    <div class="mt-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-base-content/70">Date:</span>
                                            <span>{{ $relatedEntity['date'] }}</span>
                                        </div>

                                        <div class="flex justify-between mt-1">
                                            <span class="text-base-content/70">Status:</span>
                                            <span class="capitalize">{{ $relatedEntity['status'] }}</span>
                                        </div>

                                        @if(isset($relatedEntity['amount']))
                                            <div class="flex justify-between mt-1">
                                                <span class="text-base-content/70">Amount:</span>
                                                <span>${{ number_format($relatedEntity['amount'], 2) }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mt-3 text-center">
                                        <a href="{{ $relatedEntity['url'] }}" class="btn btn-outline btn-sm btn-block">
                                            <i class="mr-2 fa-solid fa-eye"></i>
                                            View {{ ucfirst($relatedEntity['type']) }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Initial Attachments -->
                    @if($ticket->attachments->count() > 0)
                        <div class="shadow-lg card bg-base-100">
                            <div class="card-body">
                                <h2 class="card-title">Ticket Attachments</h2>

                                <div class="my-2 divider"></div>

                                <div class="space-y-2">
                                    @foreach($ticket->attachments as $attachment)
                                        <a
                                            href="{{ Storage::url($attachment->file_path) }}"
                                            target="_blank"
                                            class="flex items-center p-3 transition rounded-lg bg-base-200 hover:bg-base-300"
                                        >
                                            <i class="mr-3 text-xl fa-solid {{ Str::contains($attachment->file_type, 'image') ? 'fa-file-image text-blue-500' : 'fa-file-lines text-gray-500' }}"></i>
                                            <div class="overflow-hidden">
                                                <p class="overflow-hidden font-medium text-ellipsis whitespace-nowrap">{{ $attachment->file_name }}</p>
                                                <p class="text-xs text-base-content/60">{{ round($attachment->file_size / 1024) }} KB</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Conversation Thread -->
            <div class="lg:col-span-2">
                <!-- Satisfaction Survey -->
                @if($showSatisfactionSurvey)
                    <div class="p-4 mb-6 rounded-lg shadow-lg bg-base-100">
                        <h3 class="text-lg font-medium">How was your support experience?</h3>
                        <p class="mt-1 text-sm text-base-content/70">Your feedback helps us improve our service</p>

                        <div class="flex justify-center my-4">
                            <div
                                class="rating rating-lg"
                                x-data="{ rating: @entangle('satisfactionRating').live }"
                            >
                                @for($i = 1; $i <= 5; $i++)
                                    <input
                                        type="radio"
                                        name="rating-{{ $i }}"
                                        class="bg-orange-400 mask mask-star-2"
                                        value="{{ $i }}"
                                        wire:model.live="satisfactionRating"
                                    />
                                @endfor
                            </div>
                        </div>

                        <div class="form-control">
                            <textarea
                                wire:model="feedbackComment"
                                class="h-24 textarea textarea-bordered"
                                placeholder="Any additional comments about your experience? (optional)"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-2 mt-4">
                            <button
                                wire:click="skipSatisfactionRating"
                                class="btn btn-ghost btn-sm"
                            >
                                Skip
                            </button>
                            <button
                                wire:click="submitSatisfactionRating"
                                class="btn btn-primary btn-sm"
                                {{ $satisfactionRating > 0 ? '' : 'disabled' }}
                            >
                                Submit Feedback
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Main Ticket Content -->
                <div class="mb-6 shadow-lg card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-start gap-4">
                            <div class="avatar">
                                <div class="w-12 h-12 rounded-full bg-primary">
                                    <span class="text-xl text-white">{{ substr($user->name, 0, 1) }}</span>
                                </div>
                            </div>

                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="font-semibold">{{ $user->name }}</h3>
                                        <p class="text-sm text-base-content/60">{{ $ticket->created_at->format('M d, Y g:i A') }}</p>
                                    </div>
                                    <div class="badge {{ $this->getStatusColor($ticket->status) }}">
                                        {{ ucfirst($ticket->status) }}
                                    </div>
                                </div>

                                <div class="mt-3 whitespace-pre-line text-base/relaxed">{{ $ticket->description }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages Thread -->
                <div class="space-y-4">
                    @foreach($messages as $message)
                        <div class="shadow-md card {{ $message->message_type === 'user' ? 'bg-base-100' : 'bg-secondary/10' }}">
                            <div class="card-body">
                                <div class="flex items-start gap-4">
                                    <div class="avatar">
                                        <div class="w-10 h-10 rounded-full {{ $message->message_type === 'user' ? 'bg-primary' : 'bg-secondary' }}">
                                            <span class="text-white">
                                                {{ $message->message_type === 'user' ? substr($user->name, 0, 1) : 'S' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex-1">
                                        <div>
                                            <h3 class="font-semibold">
                                                {{ $message->message_type === 'user' ? $user->name : 'Support Team' }}
                                            </h3>
                                            <p class="text-sm text-base-content/60">{{ $message->created_at->format('M d, Y g:i A') }}</p>
                                        </div>

                                        <div class="mt-3 whitespace-pre-line text-base/relaxed">{{ $message->message }}</div>

                                        <!-- Message Attachments -->
                                        @if($message->attachments->count() > 0)
                                            <div class="p-3 mt-3 rounded-lg bg-base-200">
                                                <h4 class="text-sm font-medium">Attachments:</h4>
                                                <div class="grid gap-2 mt-2 md:grid-cols-2">
                                                    @foreach($message->attachments as $attachment)
                                                        <a
                                                            href="{{ Storage::url($attachment->file_path) }}"
                                                            target="_blank"
                                                            class="flex items-center p-2 transition rounded-lg bg-base-100 hover:bg-base-300"
                                                        >
                                                            <i class="mr-2 text-lg fa-solid {{ Str::contains($attachment->file_type, 'image') ? 'fa-file-image text-blue-500' : 'fa-file-lines text-gray-500' }}"></i>
                                                            <div class="overflow-hidden">
                                                                <p class="overflow-hidden text-sm font-medium text-ellipsis whitespace-nowrap">{{ $attachment->file_name }}</p>
                                                                <p class="text-xs text-base-content/60">{{ round($attachment->file_size / 1024) }} KB</p>
                                                            </div>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Reply Form -->
                @if($ticket->status !== 'closed')
                    <div class="mt-6 shadow-lg card bg-base-100">
                        <div class="card-body">
                            <h3 class="font-semibold">Reply to this ticket</h3>

                            <div class="mt-3 form-control">
                                <textarea
                                    wire:model="newMessage"
                                    class="h-32 textarea textarea-bordered"
                                    placeholder="Type your message here..."
                                ></textarea>
                                @error('newMessage')
                                    <label class="label">
                                        <span class="text-error label-text-alt">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>

                            <!-- Attachment Upload -->
                            <div class="mt-3 form-control">
                                <label class="label">
                                    <span class="label-text">Attachments ({{ count($attachments) }}/{{ $maxAttachments }})</span>
                                </label>

                                <div
                                    class="p-4 border-2 border-dashed rounded-lg cursor-pointer bg-base-200 hover:bg-base-300"
                                    x-data="{ isUploading: false, progress: 0 }"
                                    x-on:livewire-upload-start="isUploading = true"
                                    x-on:livewire-upload-finish="isUploading = false"
                                    x-on:livewire-upload-error="isUploading = false"
                                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                                >
                                    <div class="text-center">
                                        <i class="mb-2 text-2xl fa-solid fa-cloud-arrow-up text-base-content/50"></i>

                                        <p class="text-sm font-medium">
                                            @if(count($attachments) < $maxAttachments)
                                                Click to upload or drag and drop files here
                                            @else
                                                Maximum number of attachments reached
                                            @endif
                                        </p>

                                        <input
                                            type="file"
                                            wire:model="attachments"
                                            class="hidden"
                                            multiple
                                            accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.doc,.docx"
                                            {{ count($attachments) >= $maxAttachments ? 'disabled' : '' }}
                                        />

                                        <!-- Upload Progress Bar -->
                                        <div x-show="isUploading" class="w-full mt-4">
                                            <progress class="w-full progress" x-bind:value="progress" max="100"></progress>
                                        </div>
                                    </div>
                                </div>

                                @error('attachments.*')
                                    <label class="label">
                                        <span class="text-error label-text-alt">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>

                            <!-- Attachment Preview -->
                            @if(count($attachments) > 0)
                                <div class="mt-3">
                                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                        @foreach($attachments as $index => $attachment)
                                            <div class="flex items-center justify-between p-2 rounded-lg bg-base-200">
                                                <div class="flex items-center flex-1 overflow-hidden">
                                                    <i class="mr-2 text-lg fa-solid {{ Str::contains($attachment->getMimeType(), 'image') ? 'fa-file-image text-blue-500' : 'fa-file-lines text-gray-500' }}"></i>
                                                    <div class="overflow-hidden">
                                                        <p class="overflow-hidden text-sm font-medium text-ellipsis whitespace-nowrap">{{ $attachment->getClientOriginalName() }}</p>
                                                        <p class="text-xs text-base-content/60">{{ round($attachment->getSize() / 1024) }} KB</p>
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    wire:click="removeAttachment({{ $index }})"
                                                    class="ml-2 btn btn-ghost btn-circle btn-xs"
                                                >
                                                    <i class="fa-solid fa-times"></i>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="mt-4">
                                <button
                                    wire:click="sendMessage"
                                    class="btn btn-primary"
                                    wire:loading.attr="disabled"
                                    wire:target="sendMessage"
                                >
                                    <i class="mr-2 fa-solid fa-paper-plane"></i>
                                    <span wire:loading.remove wire:target="sendMessage">Send Reply</span>
                                    <span wire:loading wire:target="sendMessage">
                                        <span class="mr-1 loading loading-spinner loading-xs"></span>
                                        Sending...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Closed Ticket Message -->
                    <div class="p-4 mt-6 text-center rounded-lg shadow-lg bg-base-100">
                        <i class="mb-2 text-3xl fa-solid fa-lock text-base-content/50"></i>
                        <h3 class="text-lg font-medium">This ticket is closed</h3>
                        <p class="mt-1 text-base-content/70">If you need further assistance, please create a new ticket.</p>
                        <div class="mt-4">
                            <a href="{{ route('support.create') }}" class="btn btn-primary">
                                <i class="mr-2 fa-solid fa-plus"></i>
                                Create New Ticket
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Close Ticket Confirmation Modal -->
    @if($showCloseConfirmation)
        <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="w-full max-w-md modal-box">
                <h3 class="text-lg font-bold">Close Support Ticket?</h3>

                <p class="py-4">
                    Are you sure you want to close this ticket? If you need further assistance later, you'll need to create a new ticket.
                </p>

                <div class="modal-action">
                    <button wire:click="cancelClose" class="btn btn-ghost">Cancel</button>
                    <button
                        wire:click="closeTicket"
                        class="btn btn-primary"
                    >
                        Yes, Close Ticket
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
