<?php

namespace App\Livewire\Parents\Support;

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\SupportTicket;
use App\Models\SupportAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public $user;
    public $title = '';
    public $category = '';
    public $priority = 'medium';
    public $description = '';
    public $attachments = [];
    public $maxAttachments = 3;
    public $maxFileSize = 5120; // 5MB
    public $allowedFileTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    // Related entities (for linking tickets to invoices, sessions, etc.)
    public $relatedEntityType = null;
    public $relatedEntityId = null;
    public $availableEntities = [];

    // Form step
    public $currentStep = 1;
    public $totalSteps = 3;

    // Knowledge base suggestions
    public $knowledgeBaseSuggestions = [];
    public $loadingSuggestions = false;

    // Success state
    public $ticketCreated = false;
    public $createdTicket = null;

    // Common categories with descriptions
    public $categories = [
        'account' => 'Account access, profile settings, user management',
        'billing' => 'Payments, invoices, subscription issues',
        'technical' => 'Platform errors, device compatibility, connection problems',
        'session' => 'Scheduling, attendance, virtual classroom issues',
        'feedback' => 'General feedback, feature requests, suggestions',
        'other' => 'Any other issues not listed above'
    ];

    protected $rules = [
        'title' => 'required|min:5|max:100',
        'category' => 'required|in:account,billing,technical,session,feedback,other',
        'priority' => 'required|in:low,medium,high,urgent',
        'description' => 'required|min:20',
        'attachments.*' => 'nullable|file|max:5120|mimes:jpeg,png,gif,pdf,txt,doc,docx',
        'relatedEntityType' => 'nullable|string',
        'relatedEntityId' => 'nullable|numeric',
    ];

    protected $messages = [
        'title.required' => 'Please provide a title for your support ticket',
        'category.required' => 'Please select a category for your issue',
        'description.required' => 'Please describe your issue in detail',
        'description.min' => 'Please provide more details about your issue (at least 20 characters)',
        'attachments.*.max' => 'Files must be smaller than 5MB',
        'attachments.*.mimes' => 'Only JPG, PNG, GIF, PDF, TXT, DOC, and DOCX files are allowed',
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadAvailableEntities();

        // Check if we're being asked to create a ticket for a specific entity
        if (request('type') && request('id')) {
            $this->relatedEntityType = request('type');
            $this->relatedEntityId = request('id');

            // Try to pre-fill details based on the entity
            $this->prefillFromEntity();
        }
    }

    public function loadAvailableEntities()
    {
        // Load recent invoices
        $invoices = $this->user->invoices()->latest()->take(5)->get()->map(function($invoice) {
            return [
                'type' => 'invoice',
                'id' => $invoice->id,
'name' => "{$child->name}'s " . ($session->subject && $session->subject->name ? $session->subject->name : 'Session') . " on {$session->start_time->format('M d, Y')}",
                'amount' => $invoice->amount
            ];
        });

        // Load recent sessions
        $sessions = [];
        $children = $this->user->parentProfile ? $this->user->parentProfile->children : [];
        foreach ($children as $child) {
            $childSessions = $child->learningSessions()->latest()->take(3)->get()->map(function($session) use ($child) {
                return [
                    'type' => 'session',
                    'id' => $session->id,
'name' => "{$child->name}'s " . ($session->subject && $session->subject->name ? $session->subject->name : 'Session') . " on {$session->start_time->format('M d, Y')}",

                    'date' => $session->start_time->format('M d, Y h:i A')
                ];
            });
            $sessions = array_merge($sessions, $childSessions->toArray());
        }

        $this->availableEntities = [
            'invoices' => $invoices->toArray(),
            'sessions' => $sessions
        ];
    }

    public function prefillFromEntity()
    {
        // Pre-fill ticket details based on the related entity
        if ($this->relatedEntityType === 'invoice') {
            $invoice = $this->user->invoices()->find($this->relatedEntityId);
            if ($invoice) {
                $this->title = "Issue with " . ($session->subject && $session->subject->name ? $session->subject->name : '') . " Session";                                $this->category = 'billing';
                                $this->description = "I'm having an issue with a session scheduled for {$session->start_time->format('M d, Y h:i A')}.\n\nSubject: " . ($session->subject && $session->subject->name ? $session->subject->name : 'Not specified') . "\n\nIssue details: ";            }
        } elseif ($this->relatedEntityType === 'session') {
            // Find the session across all children
            $session = null;
            $children = $this->user->parentProfile ? $this->user->parentProfile->children : [];
            foreach ($children as $child) {
                $foundSession = $child->learningSessions()->find($this->relatedEntityId);
                if ($foundSession) {
                    $session = $foundSession;
                    break;
                }
            }

            if ($session) {
                $this->title = "Issue with " . ($session->subject && $session->subject->name ? $session->subject->name : '') . " Session";
                $this->category = 'session';
                $this->description = "I'm having an issue with a session scheduled for {$session->start_time->format('M d, Y h:i A')}.\n\nSubject: " . ($session->subject && $session->subject->name ? $session->subject->name : 'Not specified') . "\n\nIssue details: ";            }
        }
    }

    public function updatedTitle()
    {
        // When title changes, look for relevant knowledge base articles
        if (strlen($this->title) > 8) {
            $this->loadingSuggestions = true;
            $this->getSuggestionsFromTitle();
        } else {
            $this->knowledgeBaseSuggestions = [];
        }
    }

    public function getSuggestionsFromTitle()
    {
        // Simulate fetching suggestions (would connect to a real knowledge base in production)
        // This would typically be an AJAX request to a search endpoint

        // Fake a short delay to simulate network request
        sleep(1);

        // Simple keyword matching
        $keywords = [
            'password' => [
                'title' => 'How to reset your password',
                'url' => '/faq#password-reset'
            ],
            'login' => [
                'title' => 'Login problems and solutions',
                'url' => '/faq#login-issues'
            ],
            'payment' => [
                'title' => 'Payment methods and billing',
                'url' => '/faq#payment-methods'
            ],
            'invoice' => [
                'title' => 'Understanding your invoice',
                'url' => '/faq#invoice-explanation'
            ],
            'connect' => [
                'title' => 'Connection issues during sessions',
                'url' => '/faq#connection-issues'
            ],
            'audio' => [
                'title' => 'Audio troubleshooting guide',
                'url' => '/faq#audio-troubleshooting'
            ],
            'camera' => [
                'title' => 'Video camera setup guide',
                'url' => '/faq#camera-setup'
            ],
            'refund' => [
                'title' => 'Refund policy explanation',
                'url' => '/faq#refund-policy'
            ]
        ];

        $this->knowledgeBaseSuggestions = [];

        foreach ($keywords as $keyword => $article) {
            if (stripos($this->title, $keyword) !== false) {
                $this->knowledgeBaseSuggestions[] = $article;
            }
        }

        // Limit to 3 suggestions
        $this->knowledgeBaseSuggestions = array_slice($this->knowledgeBaseSuggestions, 0, 3);

        $this->loadingSuggestions = false;
    }

    public function nextStep()
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'title' => 'required|min:5|max:100',
                'category' => 'required',
                'priority' => 'required'
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'description' => 'required|min:20'
            ]);
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

    public function removeAttachment($index)
    {
        array_splice($this->attachments, $index, 1);
    }

    public function createTicket()
    {
        $this->validate();

        // Generate a unique ticket ID
        $ticketId = 'TKT-' . strtoupper(Str::random(8));

        // Create the ticket
        $ticket = SupportTicket::create([
            'user_id' => $this->user->id,
            'ticket_id' => $ticketId,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => 'open',
            'related_entity_type' => $this->relatedEntityType,
            'related_entity_id' => $this->relatedEntityId
        ]);

        // Save attachments
        foreach ($this->attachments as $attachment) {
            $path = $attachment->store('support-attachments/' . $ticket->id, 'public');

            SupportAttachment::create([
                'support_ticket_id' => $ticket->id,
                'file_path' => $path,
                'file_name' => $attachment->getClientOriginalName(),
                'file_size' => $attachment->getSize(),
                'file_type' => $attachment->getMimeType()
            ]);
        }

        // Set success state
        $this->ticketCreated = true;
        $this->createdTicket = $ticket;

        // Dispatch event or notification
        // In a real app, you might want to notify admins or support staff
        // Event::dispatch(new SupportTicketCreated($ticket));
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

    public function getPriorityIcon($priority)
    {
        return match($priority) {
            'low' => 'fa-solid fa-arrow-down',
            'medium' => 'fa-solid fa-minus',
            'high' => 'fa-solid fa-arrow-up',
            'urgent' => 'fa-solid fa-exclamation',
            default => 'fa-solid fa-circle'
        };
    }
};?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8 overflow-hidden text-white shadow-lg rounded-xl bg-gradient-to-r from-primary to-secondary">
            <div class="p-6 md:p-8">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 class="text-3xl font-bold">Create Support Ticket</h1>
                        <p class="mt-2 text-white/80">Tell us about your issue and we'll help you resolve it</p>
                    </div>

                    <a href="{{ route('parents.support.index') }}" class="text-white btn btn-ghost btn-sm bg-white/10">
                        <i class="w-4 h-4 mr-1 fa-solid fa-arrow-left"></i>
                        Back to Support
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                @if($ticketCreated)
                    <!-- Success View -->
                    <div class="py-8 text-center">
                        <div class="flex items-center justify-center w-20 h-20 mx-auto mb-6 rounded-full bg-success/20">
                            <i class="text-4xl fa-solid fa-check text-success"></i>
                        </div>

                        <h2 class="text-2xl font-bold">Ticket Created Successfully!</h2>
                        <p class="mt-3 text-base-content/70">
                            Your ticket has been submitted. Our support team will review it shortly.
                        </p>

                        <div class="max-w-md p-4 mx-auto mt-6 rounded-lg bg-base-200">
                            <div class="mb-2 text-sm text-base-content/70">Ticket Reference:</div>
                            <div class="text-xl font-bold">{{ $createdTicket->ticket_id }}</div>
                            <div class="mt-3 text-sm">
                                <span class="font-medium">Status:</span>
                                <span class="badge badge-warning">Open</span>
                            </div>
                        </div>

                        <div class="flex justify-center gap-3 mt-8">
                            <a href="{{ route('support.show', $createdTicket->id) }}" class="btn btn-primary">
                                <i class="mr-2 fa-solid fa-eye"></i>
                                View Ticket
                            </a>
                            <a href="{{ route('parents.support.index') }}" class="btn btn-ghost">
                                <i class="mr-2 fa-solid fa-list"></i>
                                All Tickets
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Progress Steps -->
                    <div class="mb-6">
                        <ul class="w-full steps steps-horizontal">
                            <li class="step {{ $currentStep >= 1 ? 'step-primary' : '' }}">Issue Details</li>
                            <li class="step {{ $currentStep >= 2 ? 'step-primary' : '' }}">Description</li>
                            <li class="step {{ $currentStep >= 3 ? 'step-primary' : '' }}">Attachments</li>
                        </ul>
                    </div>

                    <!-- Step 1: Basic Info -->
                    <div class="{{ $currentStep == 1 ? 'block' : 'hidden' }}">
                        <h2 class="text-xl font-bold">Step 1: Issue Details</h2>
                        <p class="mb-6 text-base-content/70">Provide basic information about your issue</p>

                        <!-- Title -->
                        <div class="mb-4 form-control">
                            <label class="label">
                                <span class="label-text">Ticket Title <span class="text-error">*</span></span>
                            </label>
                            <input
                                type="text"
                                wire:model.live.debounce.500ms="title"
                                class="w-full input input-bordered"
                                placeholder="Brief description of your issue"
                                maxlength="100"
                            />
                            @error('title')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror

                            <!-- Knowledge Base Suggestions -->
                            <div class="mt-2">
                                @if($loadingSuggestions)
                                    <div class="flex items-center gap-2 text-sm text-base-content/70">
                                        <span class="loading loading-spinner loading-xs"></span>
                                        Looking for relevant solutions...
                                    </div>
                                @elseif(count($knowledgeBaseSuggestions) > 0)
                                    <div class="p-3 rounded-lg bg-info/10">
                                        <h3 class="flex items-center text-sm font-medium text-info">
                                            <i class="mr-2 fa-solid fa-lightbulb"></i>
                                            We found articles that might help:
                                        </h3>
                                        <ul class="mt-2 space-y-1">
                                            @foreach($knowledgeBaseSuggestions as $suggestion)
                                                <li>
                                                    <a href="{{ $suggestion['url'] }}" class="text-sm text-primary hover:underline" target="_blank">
                                                        {{ $suggestion['title'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Category -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Category <span class="text-error">*</span></span>
                                </label>
                                <select
                                    wire:model="category"
                                    class="w-full select select-bordered"
                                >
                                    <option value="">Select issue category</option>
                                    @foreach($categories as $key => $description)
                                        <option value="{{ $key }}">{{ ucfirst($key) }}</option>
                                    @endforeach
                                </select>
                                @error('category')
                                    <label class="label">
                                        <span class="text-error label-text-alt">{{ $message }}</span>
                                    </label>
                                @enderror

                                @if($category && isset($categories[$category]))
                                    <label class="label">
                                        <span class="text-xs label-text-alt">
                                            {{ $categories[$category] }}
                                        </span>
                                    </label>
                                @endif
                            </div>

                            <!-- Priority -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Priority <span class="text-error">*</span></span>
                                </label>
                                <div class="flex gap-2">
                                    @foreach(['low', 'medium', 'high', 'urgent'] as $priorityOption)
                                        <label
                                            class="flex-1 px-3 py-2 cursor-pointer border rounded-lg {{ $priority === $priorityOption ? $this->getPriorityColor($priorityOption) : 'bg-base-200' }}"
                                        >
                                            <input
                                                type="radio"
                                                wire:model="priority"
                                                value="{{ $priorityOption }}"
                                                class="hidden"
                                            />
                                            <div class="flex flex-col items-center">
                                                <i class="{{ $this->getPriorityIcon($priorityOption) }} mb-1"></i>
                                                <span class="text-sm capitalize">{{ $priorityOption }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @error('priority')
                                    <label class="label">
                                        <span class="text-error label-text-alt">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                        </div>

                        <!-- Related Entity Selection -->
                        @if(count($availableEntities['invoices']) > 0 || count($availableEntities['sessions']) > 0)
                            <div class="mt-6">
                                <h3 class="mb-2 font-medium">Link to Related Item (Optional)</h3>

                                <div class="p-4 rounded-lg bg-base-200">
                                    <!-- Entity Type Selection -->
                                    <div class="mb-3 tabs tabs-boxed bg-base-100">

                                        <div class="mb-3 tabs tabs-boxed bg-base-100">
    <a class="tab {{ $relatedEntityType === null ? 'tab-active' : '' }}" wire:click="$set('relatedEntityType', null)">None</a>
    @if(count($availableEntities['invoices']) > 0)
        <a class="tab {{ $relatedEntityType === 'invoice' ? 'tab-active' : '' }}" wire:click="$set('relatedEntityType', 'invoice')">Invoice</a>
    @endif
    @if(count($availableEntities['sessions']) > 0)
        <a class="tab {{ $relatedEntityType === 'session' ? 'tab-active' : '' }}" wire:click="$set('relatedEntityType', 'session')">Session</a>
    @endif
</div>
                                        @if(count($availableEntities['sessions']) > 0)

                                              <a  class="tab {{ $relatedEntityType === 'session' ? 'tab-active' : '' }}"
                                                wire:click="$set('relatedEntityType', 'session')"
                                            >Session</a>
                                        @endif
                                    </div>

                                    <!-- Entity Selection -->
                                    @if($relatedEntityType === 'invoice')
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Select Invoice</span>
                                            </label>
                                            <select
                                                wire:model="relatedEntityId"
                                                class="w-full select select-bordered"
                                            >
                                                <option value="">Select an invoice</option>
                                                @foreach($availableEntities['invoices'] as $invoice)
                                                    <option value="{{ $invoice['id'] }}">{{ $invoice['name'] }} - ${{ $invoice['amount'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @elseif($relatedEntityType === 'session')
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Select Session</span>
                                            </label>
                                            <select
                                                wire:model="relatedEntityId"
                                                class="w-full select select-bordered"
                                            >
                                                <option value="">Select a session</option>
                                                @foreach($availableEntities['sessions'] as $session)
                                                    <option value="{{ $session['id'] }}">{{ $session['name'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-end mt-6">
                            <button
                                wire:click="nextStep"
                                class="btn btn-primary"
                            >
                                Continue
                                <i class="ml-2 fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Description -->
                    <div class="{{ $currentStep == 2 ? 'block' : 'hidden' }}">
                        <h2 class="text-xl font-bold">Step 2: Issue Description</h2>
                        <p class="mb-6 text-base-content/70">Describe your issue in detail so we can help you better</p>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Detailed Description <span class="text-error">*</span></span>
                            </label>
                            <textarea
                                wire:model="description"
                                class="h-48 textarea textarea-bordered"
                                placeholder="Please describe your issue in detail. Include any error messages, steps to reproduce, and other relevant information."
                            ></textarea>
                            @error('description')
                                <label class="label">
                                    <span class="text-error label-text-alt">{{ $message }}</span>
                                </label>
                            @enderror

                            <label class="label">
                                <span class="text-xs label-text-alt">
                                    <i class="mr-1 fa-solid fa-circle-info"></i>
                                    The more information you provide, the faster we can resolve your issue.
                                </span>
                            </label>
                        </div>

                        <div class="flex justify-between mt-6">
                            <button
                                wire:click="prevStep"
                                class="btn btn-outline"
                            >
                                <i class="mr-2 fa-solid fa-arrow-left"></i>
                                Back
                            </button>

                            <button
                                wire:click="nextStep"
                                class="btn btn-primary"
                            >
                                Continue
                                <i class="ml-2 fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Attachments -->
                    <div class="{{ $currentStep == 3 ? 'block' : 'hidden' }}">
                        <h2 class="text-xl font-bold">Step 3: Attachments (Optional)</h2>
                        <p class="mb-6 text-base-content/70">Upload any relevant files or screenshots</p>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Attachments ({{ count($attachments) }}/{{ $maxAttachments }})</span>
                            </label>

                            <div
                                class="p-6 border-2 border-dashed rounded-lg cursor-pointer bg-base-200 hover:bg-base-300"
                                x-data="{ isUploading: false, progress: 0 }"
                                x-on:livewire-upload-start="isUploading = true"
                                x-on:livewire-upload-finish="isUploading = false"
                                x-on:livewire-upload-error="isUploading = false"
                                x-on:livewire-upload-progress="progress = $event.detail.progress"
                            >
                                <div class="text-center">
                                    <i class="mb-3 text-3xl fa-solid fa-cloud-arrow-up text-base-content/50"></i>

                                    <p class="mb-1 font-medium">
                                        @if(count($attachments) < $maxAttachments)
                                            Click to upload or drag and drop files here
                                        @else
                                            Maximum number of attachments reached
                                        @endif
                                    </p>

                                    <p class="text-sm text-base-content/60">
                                        JPG, PNG, GIF, PDF, TXT, DOC, DOCX (max 5MB each)
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
                            <div class="mt-4">
                                <h3 class="mb-2 font-medium">Attached Files</h3>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    @foreach($attachments as $index => $attachment)
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-base-200">
                                            <div class="flex items-center flex-1 overflow-hidden">
                                                <i class="mr-3 text-xl fa-solid {{ Str::contains($attachment->getMimeType(), 'image') ? 'fa-file-image text-blue-500' : 'fa-file-lines text-gray-500' }}"></i>
                                                <div class="overflow-hidden">
                                                    <p class="overflow-hidden font-medium text-ellipsis whitespace-nowrap">{{ $attachment->getClientOriginalName() }}</p>
                                                    <p class="text-xs text-base-content/60">{{ round($attachment->getSize() / 1024) }} KB</p>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="removeAttachment({{ $index }})"
                                                class="ml-2 btn btn-ghost btn-circle btn-sm"
                                            >
                                                <i class="fa-solid fa-times"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-between mt-6">
                            <button
                                wire:click="prevStep"
                                class="btn btn-outline"
                            >
                                <i class="mr-2 fa-solid fa-arrow-left"></i>
                                Back
                            </button>

                            <button
                                wire:click="createTicket"
                                class="btn btn-primary"
                                wire:loading.attr="disabled"
                                wire:target="createTicket"
                            >
                                <i class="mr-2 fa-solid fa-paper-plane"></i>
                                <span wire:loading.remove wire:target="createTicket">Submit Ticket</span>
                                <span wire:loading wire:target="createTicket">
                                    <span class="mr-1 loading loading-spinner loading-xs"></span>
                                    Submitting...
                                </span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
