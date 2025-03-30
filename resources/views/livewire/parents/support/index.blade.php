<?php

namespace App\Livewire\Parents\Support;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $activeTab = 'active';
    public $searchQuery = '';
    public $statusFilter = 'all';
    public $categoryFilter = 'all';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // Stats
    public $stats = [
        'total_tickets' => 0,
        'open_tickets' => 0,
        'resolved_tickets' => 0,
        'avg_response_time' => 0,
    ];

    // Knowledge Base Search
    public $knowledgeBaseQuery = '';
    public $showKnowledgeBaseResults = false;
    public $knowledgeBaseResults = [];

    // Common Issues
    public $commonIssues = [
        [
            'title' => 'Account Access Issues',
            'icon' => 'fa-solid fa-lock',
            'color' => 'bg-blue-100 text-blue-600',
            'count' => 0,
            'link' => '#'
        ],
        [
            'title' => 'Billing Questions',
            'icon' => 'fa-solid fa-credit-card',
            'color' => 'bg-green-100 text-green-600',
            'count' => 0,
            'link' => '#'
        ],
        [
            'title' => 'Technical Problems',
            'icon' => 'fa-solid fa-gears',
            'color' => 'bg-purple-100 text-purple-600',
            'count' => 0,
            'link' => '#'
        ],
        [
            'title' => 'Session Issues',
            'icon' => 'fa-solid fa-calendar-check',
            'color' => 'bg-yellow-100 text-yellow-600',
            'count' => 0,
            'link' => '#'
        ],
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadStats();
        $this->loadCommonIssuesCounts();
    }

    public function loadStats()
    {
        // Get all user's tickets
        $tickets = SupportTicket::where('user_id', $this->user->id)->get();

        // Calculate stats
        $openTickets = $tickets->whereIn('status', ['open', 'in_progress']);
        $resolvedTickets = $tickets->where('status', 'resolved');

        // Calculate average response time (in hours)
        $responseTimes = [];
        foreach ($tickets as $ticket) {
            if ($ticket->first_response_at && $ticket->created_at) {
                $responseTimes[] = $ticket->created_at->diffInHours($ticket->first_response_at);
            }
        }

        $avgResponseTime = count($responseTimes) > 0 ? array_sum($responseTimes) / count($responseTimes) : 0;

        $this->stats = [
            'total_tickets' => $tickets->count(),
            'open_tickets' => $openTickets->count(),
            'resolved_tickets' => $resolvedTickets->count(),
            'avg_response_time' => round($avgResponseTime, 1)
        ];
    }

    public function loadCommonIssuesCounts()
    {
        // Map categories to common issues indexes
        $categoryMapping = [
            'account' => 0,
            'billing' => 1,
            'technical' => 2,
            'session' => 3
        ];

        // Count tickets by category
        $categoryCounts = SupportTicket::where('user_id', $this->user->id)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        // Update counts for common issues
        foreach ($categoryCounts as $category => $count) {
            if (isset($categoryMapping[$category])) {
                $this->commonIssues[$categoryMapping[$category]]['count'] = $count;
            }
        }
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedSearchQuery()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
    }

    public function searchKnowledgeBase()
    {
        if (strlen($this->knowledgeBaseQuery) >= 3) {
            // Simulated knowledge base search
            // In a real app, you would search your knowledge base articles
            $this->knowledgeBaseResults = [
                [
                    'title' => 'How to reset your password',
                    'excerpt' => 'Learn how to reset your password if you have forgotten it...',
                    'url' => '/faq#password-reset'
                ],
                [
                    'title' => 'Billing cycles explained',
                    'excerpt' => 'Understanding how our billing cycles work and when payments are processed...',
                    'url' => '/faq#billing-cycles'
                ],
                [
                    'title' => 'Technical requirements for virtual sessions',
                    'excerpt' => 'Make sure your computer meets these requirements for the best experience...',
                    'url' => '/faq#technical-requirements'
                ],
            ];

            $this->showKnowledgeBaseResults = true;
        } else {
            $this->knowledgeBaseResults = [];
            $this->showKnowledgeBaseResults = false;
        }
    }

    public function clearKnowledgeBaseSearch()
    {
        $this->knowledgeBaseQuery = '';
        $this->knowledgeBaseResults = [];
        $this->showKnowledgeBaseResults = false;
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

    public function getTicketsProperty()
    {
        $query = SupportTicket::where('user_id', $this->user->id);

        // Apply active tab filter
        if ($this->activeTab === 'active') {
            $query->whereIn('status', ['open', 'in_progress']);
        } elseif ($this->activeTab === 'resolved') {
            $query->where('status', 'resolved');
        } elseif ($this->activeTab === 'closed') {
            $query->where('status', 'closed');
        }

        // Apply search
        if ($this->searchQuery) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('ticket_id', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('description', 'like', '%' . $this->searchQuery . '%');
            });
        }

        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Apply category filter
        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate(10);
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

    public function getCategoryIcon($category)
    {
        return match($category) {
            'account' => 'fa-solid fa-user',
            'billing' => 'fa-solid fa-credit-card',
            'technical' => 'fa-solid fa-wrench',
            'session' => 'fa-solid fa-calendar',
            'feedback' => 'fa-solid fa-comment',
            default => 'fa-solid fa-ticket'
        };
    }

    public function getCategoryColor($category)
    {
        return match($category) {
            'account' => 'text-blue-600',
            'billing' => 'text-green-600',
            'technical' => 'text-purple-600',
            'session' => 'text-yellow-600',
            'feedback' => 'text-pink-600',
            default => 'text-gray-600'
        };
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="mb-8 overflow-hidden text-white shadow-lg rounded-xl bg-gradient-to-r from-primary to-secondary">
            <div class="p-6 md:p-8">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 class="text-3xl font-bold">Support Center</h1>
                        <p class="mt-2 text-white/80">Get help with your account, billing, and more</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('parents.dashboard') }}" class="text-white btn btn-ghost btn-sm bg-white/10">
                            <i class="w-4 h-4 mr-1 fa-solid fa-house"></i>
                            Dashboard
                        </a>
                        <a href="{{ route('support.create') }}" class="text-white btn btn-primary btn-sm">
                            <i class="w-4 h-4 mr-1 fa-solid fa-plus"></i>
                            New Support Ticket
                        </a>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="grid grid-cols-2 gap-3 mt-6 md:grid-cols-4">
                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Total Tickets</div>
                        <div class="text-2xl font-bold">{{ $stats['total_tickets'] }}</div>
                    </div>

                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Open Tickets</div>
                        <div class="text-2xl font-bold">{{ $stats['open_tickets'] }}</div>
                    </div>

                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Resolved</div>
                        <div class="text-2xl font-bold">{{ $stats['resolved_tickets'] }}</div>
                    </div>

                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Avg. Response</div>
                        <div class="text-2xl font-bold">{{ $stats['avg_response_time'] }}h</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Knowledge Base -->
        <div class="mb-6 shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="mb-4 card-title">Find Quick Solutions</h2>

                <div class="relative">
                    <div class="relative">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="knowledgeBaseQuery"
                            wire:keydown.enter="searchKnowledgeBase"
                            placeholder="Search our knowledge base..."
                            class="w-full pl-10 pr-16 input input-bordered"
                        >
                        <div class="absolute transform -translate-y-1/2 left-3 top-1/2">
                            <i class="fa-solid fa-magnifying-glass text-base-content/60"></i>
                        </div>
                        <button
                            class="absolute transform -translate-y-1/2 right-3 top-1/2 btn btn-primary btn-sm"
                            wire:click="searchKnowledgeBase"
                        >
                            Search
                        </button>
                    </div>

                    <!-- Knowledge Base Results -->
                    @if($showKnowledgeBaseResults)
                    <div class="absolute z-10 w-full p-4 mt-2 bg-white rounded-lg shadow-xl">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-semibold">Search Results</h3>
                            <button class="btn btn-ghost btn-xs" wire:click="clearKnowledgeBaseSearch">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>

                        <div class="my-1 divider"></div>

                        @if(count($knowledgeBaseResults) > 0)
                            <div class="space-y-3">
                                @foreach($knowledgeBaseResults as $result)
                                <a href="{{ $result['url'] }}" class="block p-3 transition rounded-lg hover:bg-base-200">
                                    <div class="font-medium">{{ $result['title'] }}</div>
                                    <p class="mt-1 text-sm text-base-content/70">{{ $result['excerpt'] }}</p>
                                </a>
                                @endforeach
                            </div>

                            <div class="mt-3 text-center">
                                <a href="{{ route('faq') }}" class="text-primary">View all articles</a>
                            </div>
                        @else
                            <div class="py-4 text-center">
                                <p>No results found. Try different keywords or check our <a href="{{ route('faq') }}" class="text-primary">FAQ</a>.</p>
                            </div>
                        @endif
                    </div>
                    @endif
                </div>

                <p class="mt-3 text-center text-base-content/70">
                    Can't find what you're looking for?
                    <a href="{{ route('support.create') }}" class="text-primary">Create a new support ticket</a>
                </p>
            </div>
        </div>

        <!-- Common Issues -->
        <div class="mb-6 shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="mb-3 card-title">Common Issues</h2>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                    @foreach($commonIssues as $issue)
                    <div class="p-4 transition rounded-lg shadow-sm bg-base-100 hover:shadow-md">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full {{ $issue['color'] }}">
                                <i class="{{ $issue['icon'] }}"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold">{{ $issue['title'] }}</h3>
                                <div class="text-sm text-base-content/70">{{ $issue['count'] }} tickets</div>
                            </div>
                        </div>
                        <a href="{{ $issue['link'] }}" class="mt-3 btn btn-outline btn-block btn-sm">View Solutions</a>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Support Tickets -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="mb-4 card-title">My Support Tickets</h2>

                <!-- Tabs -->
                <div class="p-1 mb-4 tabs tabs-boxed bg-base-200">

                   <a wire:click.prevent="setActiveTab('all')"
                    class="tab {{ $activeTab === 'all' ? 'tab-active' : '' }}"
                >All Tickets</a>

                   <a wire:click.prevent="setActiveTab('active')"
                    class="tab {{ $activeTab === 'active' ? 'tab-active' : '' }}"
                >Active</a>

                   <a wire:click.prevent="setActiveTab('resolved')"
                    class="tab {{ $activeTab === 'resolved' ? 'tab-active' : '' }}"
                >Resolved</a>

                  <a  wire:click.prevent="setActiveTab('closed')"
                    class="tab {{ $activeTab === 'closed' ? 'tab-active' : '' }}"
                >Closed</a>
            </div>

                <!-- Filters -->
                <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-3">
                    <!-- Search -->
                    <div class="form-control">
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="searchQuery"
                                placeholder="Search tickets..."
                                class="w-full pl-10 input input-bordered input-sm"
                            >
                            <div class="absolute transform -translate-y-1/2 left-3 top-1/2">
                                <i class="fa-solid fa-magnifying-glass text-base-content/60"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="form-control">
                        <select wire:model.live="statusFilter" class="w-full select select-bordered select-sm">
                            <option value="all">All Statuses</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>

                    <!-- Category Filter -->
                    <div class="form-control">
                        <select wire:model.live="categoryFilter" class="w-full select select-bordered select-sm">
                            <option value="all">All Categories</option>
                            <option value="account">Account</option>
                            <option value="billing">Billing</option>
                            <option value="technical">Technical</option>
                            <option value="session">Session</option>
                            <option value="feedback">Feedback</option>
                        </select>
                    </div>
                </div>

                <!-- Tickets Table -->
                @if($this->tickets->isEmpty())
                <div class="py-12 text-center">
                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-base-200">
                        <i class="text-3xl fa-solid fa-ticket text-base-content/30"></i>
                    </div>
                    <h3 class="text-lg font-medium">No support tickets found</h3>
                    <p class="mt-1 text-base-content/70">
                        @if($searchQuery || $statusFilter !== 'all' || $categoryFilter !== 'all')
                            Try adjusting your filter criteria
                        @else
                            You haven't created any support tickets yet
                        @endif
                    </p>
                    <a href="{{ route('support.create') }}" class="mt-4 btn btn-primary">
                        <i class="mr-2 fa-solid fa-plus"></i>
                        Create a New Ticket
                    </a>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="table w-full table-zebra">
                        <thead>
                            <tr>
                                <th class="cursor-pointer" wire:click="sortBy('ticket_id')">
                                    <div class="flex items-center">
                                        Ticket #
                                        @if($sortField === 'ticket_id')
                                            <i class="ml-1 fa-solid fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </div>
                                </th>
                                <th class="cursor-pointer" wire:click="sortBy('title')">
                                    <div class="flex items-center">
                                        Subject
                                        @if($sortField === 'title')
                                            <i class="ml-1 fa-solid fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </div>
                                </th>
                                <th>Category</th>
                                <th>Status</th>
                                <th class="cursor-pointer" wire:click="sortBy('created_at')">
                                    <div class="flex items-center">
                                        Created
                                        @if($sortField === 'created_at')
                                            <i class="ml-1 fa-solid fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </div>
                                </th>
                                <th class="cursor-pointer" wire:click="sortBy('updated_at')">
                                    <div class="flex items-center">
                                        Last Update
                                        @if($sortField === 'updated_at')
                                            <i class="ml-1 fa-solid fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                                        @endif
                                    </div>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->tickets as $ticket)
                            <tr>
                                <td class="font-medium">{{ $ticket->ticket_id }}</td>
                                <td>{{ $ticket->title }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <i class="{{ $this->getCategoryIcon($ticket->category) }} {{ $this->getCategoryColor($ticket->category) }}"></i>
                                        <span class="capitalize">{{ $ticket->category }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="badge {{ $this->getStatusColor($ticket->status) }}">
                                        {{ ucfirst($ticket->status) }}
                                    </div>
                                </td>
                                <td>
                                    <div class="text-sm">{{ $ticket->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-base-content/60">{{ $ticket->created_at->format('h:i A') }}</div>
                                </td>
                                <td>
                                    <div class="text-sm">{{ $ticket->updated_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-base-content/60">{{ $ticket->updated_at->format('h:i A') }}</div>
                                </td>
                                <td>
                                    <a
                                        href="{{ route('support.show', $ticket->id) }}"
                                        class="btn btn-ghost btn-sm"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                        <span class="hidden ml-1 sm:inline">View</span>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $this->tickets->links() }}
                </div>
                @endif

                <div class="mt-4 divider"></div>

                <!-- Help Resources -->
                <div class="flex flex-col items-center justify-between gap-4 p-4 rounded-lg bg-base-200 md:flex-row">
                    <div>
                        <h3 class="font-semibold">Need more help?</h3>
                        <p class="text-sm">Check our FAQ or contact our support team</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('faq') }}" class="btn btn-outline btn-sm">
                            <i class="mr-1 fa-solid fa-circle-question"></i>
                            FAQ
                        </a>
                        <a href="{{ route('support.create') }}" class="btn btn-primary btn-sm">
                            <i class="mr-1 fa-solid fa-plus"></i>
                            New Ticket
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
