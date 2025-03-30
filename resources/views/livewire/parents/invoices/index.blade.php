<?php

namespace App\Livewire\Parents;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public $user;
    public $selectedInvoice = null;
    public $showInvoiceDetails = false;
    public $showPaymentModal = false;

    // Filter states
    public $statusFilter = 'all';
    public $dateRangeFilter = 'all';
    public $amountFilter = 'all';
    public $searchQuery = '';
    public $subscriptionFilter = 'all';

    // Custom date range
    public $customStartDate = null;
    public $customEndDate = null;
    public $showCustomDateRange = false;

    // Sort state
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // Invoice statistics
    public $stats = [
        'total_amount' => 0,
        'total_paid' => 0,
        'total_unpaid' => 0,
        'total_overdue' => 0,
        'invoices_due_soon' => 0,
        'average_amount' => 0
    ];

    // Export options
    public $showExportOptions = false;
    public $exportFormat = 'pdf';
    public $exportSelection = 'filtered';
    public $exportingInProgress = false;

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadInvoiceStats();
    }

    public function loadInvoiceStats()
    {
        $invoices = Invoice::where('user_id', $this->user->id)->get();

        $this->stats['total_amount'] = $invoices->sum('amount');
        $this->stats['total_paid'] = $invoices->where('status', 'paid')->sum('amount');
        $this->stats['total_unpaid'] = $invoices->whereIn('status', ['unpaid', 'overdue'])->sum('amount');
        $this->stats['total_overdue'] = $invoices->where('status', 'overdue')->sum('amount');

        // Count invoices due in the next 7 days
        $this->stats['invoices_due_soon'] = $invoices->where('status', 'unpaid')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->count();

        $avgAmount = $invoices->avg('amount');
        $this->stats['average_amount'] = $avgAmount ? round($avgAmount, 2) : 0;
    }

    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedDateRangeFilter() {
        $this->resetPage();
        $this->showCustomDateRange = ($this->dateRangeFilter === 'custom');
    }
    public function updatedAmountFilter() { $this->resetPage(); }
    public function updatedSearchQuery() { $this->resetPage(); }
    public function updatedSubscriptionFilter() { $this->resetPage(); }
    public function updatedCustomStartDate() { if($this->dateRangeFilter === 'custom') $this->resetPage(); }
    public function updatedCustomEndDate() { if($this->dateRangeFilter === 'custom') $this->resetPage(); }

    public function sortBy($field)
    {
        if($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function viewInvoiceDetails($invoiceId)
    {
        $this->selectedInvoice = Invoice::with(['subscription.plan', 'payment.paymentMethod'])
            ->where('user_id', $this->user->id)
            ->findOrFail($invoiceId);
        $this->showInvoiceDetails = true;
    }

    public function closeInvoiceDetails()
    {
        $this->showInvoiceDetails = false;
        $this->selectedInvoice = null;
    }

    public function openPaymentModal($invoiceId)
    {
        $this->selectedInvoice = Invoice::where('user_id', $this->user->id)
            ->findOrFail($invoiceId);
        $this->showPaymentModal = true;
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
    }

    public function downloadInvoice($invoiceId)
    {
        // In a real app, this would generate a PDF invoice
        // For demo purposes, show a notification via JavaScript event
        $this->dispatch('invoiceDownloaded', [
            'invoiceId' => $invoiceId
        ]);
    }

    public function payInvoice()
    {
        if (!$this->selectedInvoice || $this->selectedInvoice->status === 'paid') {
            return;
        }

        try {
            // Start transaction
            DB::beginTransaction();

            // In a real app, you would call a payment processor here
            // For demo, we'll just mark the invoice as paid and create a payment record

            // Get default payment method
            $paymentMethod = \App\Models\PaymentMethod::where('user_id', $this->user->id)
                ->where('is_default', true)
                ->first();

            if (!$paymentMethod) {
                throw new \Exception('No default payment method found');
            }

            // Create payment record
            $payment = new \App\Models\Payment();
            $payment->user_id = $this->user->id;
            $payment->invoice_id = $this->selectedInvoice->id;
            $payment->payment_method_id = $paymentMethod->id;
            $payment->amount = $this->selectedInvoice->amount;
            $payment->status = 'completed';
            $payment->transaction_id = 'TXN-' . strtoupper(uniqid());
            $payment->save();

            // Update invoice status
            $this->selectedInvoice->status = 'paid';
            $this->selectedInvoice->save();

            // Commit transaction
            DB::commit();

            // Close modal and refresh data
            $this->closePaymentModal();
            $this->loadInvoiceStats();

            // Show success message
            $this->dispatch('showToast', [
                'message' => 'Invoice paid successfully',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();

            // Show error message
            $this->dispatch('showToast', [
                'message' => 'Payment failed: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    public function toggleExportOptions()
    {
        $this->showExportOptions = !$this->showExportOptions;
    }

    public function exportInvoices()
    {
        $this->exportingInProgress = true;

        // In a real app, you would generate the export file here
        // For demo, we'll just simulate a delay and then show a success message

        // Show success message after "processing"
        $this->dispatch('exportCompleted', [
            'format' => $this->exportFormat
        ]);

        $this->showExportOptions = false;
        $this->exportingInProgress = false;
    }

    public function getInvoicesProperty()
    {
        $query = Invoice::where('user_id', $this->user->id)
            ->with(['subscription.plan', 'payment.paymentMethod'])
            ->when($this->searchQuery, function($query) {
                return $query->where(function($q) {
                    $q->where('invoice_number', 'like', '%' . $this->searchQuery . '%')
                      ->orWhere('description', 'like', '%' . $this->searchQuery . '%');
                });
            });

        // Apply status filter
        if($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Apply date range filter
        if($this->dateRangeFilter !== 'all') {
            switch($this->dateRangeFilter) {
                case 'this_month':
                    $query->whereMonth('created_at', Carbon::now()->month)
                          ->whereYear('created_at', Carbon::now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month)
                          ->whereYear('created_at', Carbon::now()->subMonth()->year);
                    break;
                case 'last_3_months':
                    $query->where('created_at', '>=', Carbon::now()->subMonths(3));
                    break;
                case 'last_6_months':
                    $query->where('created_at', '>=', Carbon::now()->subMonths(6));
                    break;
                case 'this_year':
                    $query->whereYear('created_at', Carbon::now()->year);
                    break;
                case 'last_year':
                    $query->whereYear('created_at', Carbon::now()->subYear()->year);
                    break;
                case 'custom':
                    if($this->customStartDate) {
                        $query->whereDate('created_at', '>=', $this->customStartDate);
                    }
                    if($this->customEndDate) {
                        $query->whereDate('created_at', '<=', $this->customEndDate);
                    }
                    break;
            }
        }

        // Apply amount filter
        if($this->amountFilter !== 'all') {
            switch($this->amountFilter) {
                case 'under_25':
                    $query->where('amount', '<', 25);
                    break;
                case '25_50':
                    $query->whereBetween('amount', [25, 50]);
                    break;
                case '50_100':
                    $query->whereBetween('amount', [50, 100]);
                    break;
                case 'over_100':
                    $query->where('amount', '>', 100);
                    break;
            }
        }

        // Apply subscription filter
        if($this->subscriptionFilter !== 'all') {
            if($this->subscriptionFilter === 'subscription') {
                $query->whereNotNull('subscription_id');
            } else {
                $query->whereNull('subscription_id');
            }
        }

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate(10);
    }

    public function getSubscriptionsProperty()
    {
        return \App\Models\Subscription::where('user_id', $this->user->id)
            ->with('plan')
            ->get();
    }

    protected function getStatusColor($status)
    {
        return match($status) {
            'paid' => 'success',
            'unpaid' => 'warning',
            'overdue' => 'error',
            default => 'info'
        };
    }

    protected function getStatusIcon($status)
    {
        return match($status) {
            'paid' => 'fa-check-circle',
            'unpaid' => 'fa-clock',
            'overdue' => 'fa-exclamation-circle',
            default => 'fa-info-circle'
        };
    }

    protected function formatDueStatus($dueDate)
    {
        if(!$dueDate) return 'No due date';

        $date = Carbon::parse($dueDate);
        $now = Carbon::now();

        if($date->isPast()) {
            $daysOverdue = $date->diffInDays($now);
            return "Overdue by {$daysOverdue} " . ($daysOverdue == 1 ? 'day' : 'days');
        } else {
            $daysUntilDue = $now->diffInDays($date);
            if($daysUntilDue === 0) {
                return 'Due today';
            } else {
                return "Due in {$daysUntilDue} " . ($daysUntilDue == 1 ? 'day' : 'days');
            }
        }
    }
}; ?>

<div
    class="min-h-screen p-6 bg-base-200"
    x-data="{
        showToast: false,
        toastMessage: '',
        toastType: 'success',
        showToastNotification(message, type = 'success') {
            this.toastMessage = message;
            this.toastType = type;
            this.showToast = true;
            setTimeout(() => {
                this.showToast = false;
            }, 3000);
        }
    }"
    x-on:showtoast.window="showToastNotification($event.detail.message, $event.detail.type)"
    x-on:invoicedownloaded.window="showToastNotification('Invoice downloaded successfully')"
    x-on:exportcompleted.window="showToastNotification(`Invoices exported as ${$event.detail.format.toUpperCase()} successfully`)"
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
        class="z-50 toast toast-top toast-end"
    >
        <div
            :class="{
                'alert-success': toastType === 'success',
                'alert-error': toastType === 'error',
                'alert-warning': toastType === 'warning',
                'alert-info': toastType === 'info'
            }"
            class="shadow-lg alert"
        >
            <i
                :class="{
                    'fa-check-circle': toastType === 'success',
                    'fa-circle-xmark': toastType === 'error',
                    'fa-triangle-exclamation': toastType === 'warning',
                    'fa-info-circle': toastType === 'info'
                }"
                class="fa-solid"
            ></i>
            <span x-text="toastMessage"></span>
        </div>
    </div>

    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="mb-8 overflow-hidden text-white shadow-lg bg-gradient-to-r from-primary to-accent rounded-xl">
            <div class="p-6 md:p-8">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 class="text-3xl font-bold">Invoices</h1>
                        <p class="mt-2 text-white/80">Manage and track all your billing documents</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('parents.dashboard') }}" class="text-white btn btn-ghost btn-sm bg-white/10">
                            <i class="w-4 h-4 mr-1 fa-solid fa-house"></i>
                            Dashboard
                        </a>
                        <a href="{{ route('parents.billing.index') }}" class="text-white btn btn-ghost btn-sm bg-white/10">
                            <i class="w-4 h-4 mr-1 fa-solid fa-credit-card"></i>
                            Billing
                        </a>
                        <div class="dropdown dropdown-end">
                            <button class="text-white btn btn-ghost btn-sm bg-white/10">
                                <i class="w-4 h-4 mr-1 fa-solid fa-download"></i>
                                Export
                            </button>
                            <div class="dropdown-content z-[1] menu bg-base-200 rounded-box w-64 p-4 shadow-xl text-base-content"
                                x-show="$wire.showExportOptions"
                                x-transition
                                @click.outside="$wire.showExportOptions = false"
                            >
                                <h3 class="mb-2 font-medium">Export Invoices</h3>

                                <div class="mb-2 form-control">
                                    <label class="label">
                                        <span class="label-text">Format</span>
                                    </label>
                                    <select wire:model="exportFormat" class="w-full select select-bordered select-sm">
                                        <option value="pdf">PDF</option>
                                        <option value="csv">CSV</option>
                                        <option value="excel">Excel</option>
                                    </select>
                                </div>

                                <div class="mb-4 form-control">
                                    <label class="label">
                                        <span class="label-text">Selection</span>
                                    </label>
                                    <select wire:model="exportSelection" class="w-full select select-bordered select-sm">
                                        <option value="filtered">Current Filtered View</option>
                                        <option value="selected">Selected Invoices</option>
                                        <option value="all">All Invoices</option>
                                    </select>
                                </div>

                                <button
                                    class="w-full btn btn-primary btn-sm"
                                    wire:click="exportInvoices"
                                    wire:loading.attr="disabled"
                                    wire:target="exportInvoices"
                                >
                                    <span wire:loading.remove wire:target="exportInvoices">Export Now</span>
                                    <span wire:loading wire:target="exportInvoices">Processing...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Overview -->
                <div class="grid grid-cols-2 gap-4 mt-6 md:grid-cols-4">
                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Total Amount</div>
                        <div class="text-2xl font-bold">${{ number_format($stats['total_amount'], 2) }}</div>
                    </div>

                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Paid</div>
                        <div class="text-2xl font-bold">${{ number_format($stats['total_paid'], 2) }}</div>
                    </div>

                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Unpaid</div>
                        <div class="text-2xl font-bold">${{ number_format($stats['total_unpaid'], 2) }}</div>
                    </div>

                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Due Soon</div>
                        <div class="text-2xl font-bold">{{ $stats['invoices_due_soon'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-6 shadow-xl card bg-base-100">
            <div class="p-4 card-body">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg card-title">Filter Invoices</h2>
                    <button
                        wire:click="$refresh"
                        class="btn btn-ghost btn-sm"
                        title="Refresh data"
                    >
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <!-- Search Filter -->
                    <div>
                        <label class="label">
                            <span class="label-text">Search</span>
                        </label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="searchQuery"
                                placeholder="Invoice # or Description"
                                class="w-full pl-10 input input-bordered"
                            >
                            <div class="absolute transform -translate-y-1/2 left-3 top-1/2">
                                <i class="fa-solid fa-magnifying-glass text-base-content/60"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="label">
                            <span class="label-text">Status</span>
                        </label>
                        <select wire:model.live="statusFilter" class="w-full select select-bordered">
                            <option value="all">All Statuses</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>

                    <!-- Date Range Filter -->
                    <div>
                        <label class="label">
                            <span class="label-text">Date Range</span>
                        </label>
                        <select wire:model.live="dateRangeFilter" class="w-full select select-bordered">
                            <option value="all">All Time</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="last_3_months">Last 3 Months</option>
                            <option value="last_6_months">Last 6 Months</option>
                            <option value="this_year">This Year</option>
                            <option value="last_year">Last Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>

                    <!-- Amount Filter -->
                    <div>
                        <label class="label">
                            <span class="label-text">Amount</span>
                        </label>
                        <select wire:model.live="amountFilter" class="w-full select select-bordered">
                            <option value="all">All Amounts</option>
                            <option value="under_25">Under $25</option>
                            <option value="25_50">$25 to $50</option>
                            <option value="50_100">$50 to $100</option>
                            <option value="over_100">Over $100</option>
                        </select>
                    </div>

                    <!-- Invoice Type Filter -->
                    <div>
                        <label class="label">
                            <span class="label-text">Invoice Type</span>
                        </label>
                        <select wire:model.live="subscriptionFilter" class="w-full select select-bordered">
                            <option value="all">All Types</option>
                            <option value="subscription">Subscription</option>
                            <option value="one_time">One-time</option>
                        </select>
                    </div>
                </div>

                <!-- Custom Date Range -->
                @if($showCustomDateRange)
                    <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2">
                        <div>
                            <label class="label">
                                <span class="label-text">Start Date</span>
                            </label>
                            <input
                                type="date"
                                wire:model.live="customStartDate"
                                class="w-full input input-bordered"
                            >
                        </div>

                        <div>
                            <label class="label">
                                <span class="label-text">End Date</span>
                            </label>
                            <input
                                type="date"
                                wire:model.live="customEndDate"
                                class="w-full input input-bordered"
                            >
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Invoices Table -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <div class="flex flex-col justify-between mb-6 md:flex-row md:items-center">
                    <h2 class="text-xl card-title">Your Invoices</h2>

                    <div class="mt-2 text-sm text-base-content/70 md:mt-0">
                        Showing {{ $this->invoices->count() }} of {{ $this->invoices->total() }} invoices
                    </div>
                </div>

                @if($this->invoices->isEmpty())
                    <div class="py-8 text-center">
                        <i class="mb-4 text-6xl fa-solid fa-file-invoice text-base-content/30"></i>
                        <h3 class="text-lg font-medium">No invoices found</h3>
                        <p class="mt-1 text-base-content/70">
                            @if($searchQuery || $statusFilter !== 'all' || $dateRangeFilter !== 'all' || $amountFilter !== 'all' || $subscriptionFilter !== 'all')
                                Try adjusting your filter criteria
                            @else
                                You don't have any invoices yet
                            @endif
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table w-full table-zebra">
                            <thead>
                                <tr>
                                    <th class="cursor-pointer" wire:click="sortBy('invoice_number')">
                                        <div class="flex items-center">
                                            Invoice #
                                            @if($sortField === 'invoice_number')
                                                <i class="fa-solid fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1 text-xs"></i>
                                            @endif
                                        </div>
                                    </th>
                                    <th class="cursor-pointer" wire:click="sortBy('created_at')">
                                        <div class="flex items-center">
                                            Date
                                            @if($sortField === 'created_at')
                                                <i class="fa-solid fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1 text-xs"></i>
                                            @endif
                                        </div>
                                    </th>
                                    <th>Description</th>
                                    <th class="cursor-pointer" wire:click="sortBy('amount')">
                                        <div class="flex items-center">
                                            Amount
                                            @if($sortField === 'amount')
                                                <i class="fa-solid fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1 text-xs"></i>
                                            @endif
                                        </div>
                                    </th>
                                    <th class="cursor-pointer" wire:click="sortBy('status')">
                                        <div class="flex items-center">
                                            Status
                                            @if($sortField === 'status')
                                                <i class="fa-solid fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1 text-xs"></i>
                                            @endif
                                        </div>
                                    </th>
                                    <th>Due</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->invoices as $invoice)
                                    <tr>
                                        <td class="font-medium">{{ $invoice->invoice_number }}</td>
                                        <td>
                                            <div>{{ $invoice->created_at->format('M d, Y') }}</div>
                                            <div class="text-xs text-base-content/70">{{ $invoice->created_at->format('h:i A') }}</div>
                                        </td>
                                        <td>
                                            <div>
                                                @if($invoice->subscription)
                                                    <div class="font-medium">{{ $invoice->subscription->plan->name ?? 'Subscription' }}</div>
                                                    <div class="text-xs text-base-content/70">
                                                        {{ $invoice->description ?: 'Subscription payment' }}
                                                    </div>
                                                @else
                                                    <div class="font-medium">{{ $invoice->description ?: 'One-time payment' }}</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="font-medium">${{ number_format($invoice->amount, 2) }}</td>
                                        <td>
                                            <div class="badge badge-{{ $this->getStatusColor($invoice->status) }} gap-1">
                                                <i class="fa-solid {{ $this->getStatusIcon($invoice->status) }} text-xs"></i>
                                                {{ ucfirst($invoice->status) }}
                                            </div>
                                        </td>
                                        <td>
                                            @if($invoice->status !== 'paid')
                                                <div class="{{ $invoice->status === 'overdue' ? 'text-error' : '' }}">
                                                    {{ $this->formatDueStatus($invoice->due_date) }}
                                                </div>
                                            @else
                                                <div class="text-sm text-success">
                                                    <i class="mr-1 fa-solid fa-check-circle"></i>
                                                    Paid
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="flex space-x-1">
                                                <button
                                                    class="btn btn-ghost btn-xs"
                                                    wire:click="viewInvoiceDetails({{ $invoice->id }})"
                                                >
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>

                                                <button
                                                    class="btn btn-ghost btn-xs"
                                                    wire:click="downloadInvoice({{ $invoice->id }})"
                                                >
                                                    <i class="fa-solid fa-download"></i>
                                                </button>

                                                @if($invoice->status !== 'paid')
                                                    <button
                                                        class="btn btn-ghost btn-xs text-success"
                                                        wire:click="openPaymentModal({{ $invoice->id }})"
                                                    >
                                                        <i class="fa-solid fa-credit-card"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $this->invoices->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Invoice Details Modal -->
    @if($showInvoiceDetails && $selectedInvoice)
        <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="w-full max-w-3xl modal-box">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold">Invoice Details</h3>
                    <button class="btn btn-sm btn-circle btn-ghost" wire:click="closeInvoiceDetails">✕</button>
                </div>

                <div class="my-0 divider"></div>

                <!-- Invoice Header -->
                <div class="flex items-start justify-between mt-4">
                    <div>
                        <div class="text-sm text-base-content/70">Invoice Number</div>
                        <div class="text-xl font-bold">{{ $selectedInvoice->invoice_number }}</div>

                        <div class="mt-4 text-sm text-base-content/70">Date Issued</div>
                        <div>{{ $selectedInvoice->created_at->format('M d, Y') }}</div>
                    </div>

                    <div class="text-right">
                        <div class="text-sm text-base-content/70">Status</div>
                        <div class="badge badge-lg badge-{{ $this->getStatusColor($selectedInvoice->status) }} mt-1">
                            {{ ucfirst($selectedInvoice->status) }}
                        </div>

                        <div class="mt-4 text-sm text-base-content/70">Due Date</div>
                        <div>{{ $selectedInvoice->due_date ? Carbon::parse($selectedInvoice->due_date)->format('M d, Y') : 'N/A' }}</div>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Invoice Details -->
                <div class="mb-6">
                    <h4 class="mb-3 font-medium">Invoice Items</h4>
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        @if($selectedInvoice->subscription)
                                            <div class="font-medium">{{ $selectedInvoice->subscription->plan->name }} Subscription</div>
                                            <div class="text-xs text-base-content/70">
                                                Billing period: {{ $selectedInvoice->subscription->start_date ? Carbon::parse($selectedInvoice->subscription->start_date)->format('M d, Y') : 'N/A' }}
                                                to {{ $selectedInvoice->subscription->end_date ? Carbon::parse($selectedInvoice->subscription->end_date)->format('M d, Y') : 'N/A' }}
                                            </div>
                                        @else
                                            <div class="font-medium">{{ $selectedInvoice->description }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right">${{ number_format($selectedInvoice->amount, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-right">${{ number_format($selectedInvoice->amount, 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Payment Information -->
                @if($selectedInvoice->payment)
                    <div class="mb-6">
                        <h4 class="mb-3 font-medium">Payment Information</h4>
                        <div class="p-4 rounded-lg bg-base-200">
                            <div class="flex justify-between mb-2">
                                <div class="text-sm text-base-content/70">Transaction ID</div>
                                <div>{{ $selectedInvoice->payment->transaction_id }}</div>
                            </div>
                            <div class="flex justify-between mb-2">
                                <div class="text-sm text-base-content/70">Payment Date</div>
                                <div>{{ $selectedInvoice->payment->created_at->format('M d, Y h:i A') }}</div>
                            </div>
                            <div class="flex justify-between mb-2">
                                <div class="text-sm text-base-content/70">Method</div>
                                <div>
                                    @if($selectedInvoice->payment->paymentMethod)
                                        <div class="flex items-center">
                                            <i class="{{ $this->getCardIcon($selectedInvoice->payment->paymentMethod->card_type) }} mr-2"></i>
                                            <span>•••• {{ $selectedInvoice->payment->paymentMethod->last_four }}</span>
                                        </div>
                                    @else
                                        Unknown
                                    @endif
                                </div>
                            </div>
                            <div class="flex justify-between">
                                <div class="text-sm text-base-content/70">Amount</div>
                                <div class="font-bold">${{ number_format($selectedInvoice->payment->amount, 2) }}</div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Actions -->
                <div class="flex justify-between mt-6">
                    <div>
                        @if($selectedInvoice->status === 'overdue')
                            <button class="btn btn-error btn-sm">
                                <i class="mr-1 fa-solid fa-triangle-exclamation"></i>
                                Report Issue
                            </button>
                        @endif
                    </div>

                    <div class="space-x-2">
                        <button
                            class="btn btn-outline btn-sm"
                            wire:click="downloadInvoice({{ $selectedInvoice->id }})"
                        >
                            <i class="mr-1 fa-solid fa-download"></i>
                            Download
                        </button>

                        @if($selectedInvoice->status !== 'paid')
                            <button
                                class="btn btn-primary btn-sm"
                                wire:click="openPaymentModal({{ $selectedInvoice->id }})"
                            >
                                <i class="mr-1 fa-solid fa-credit-card"></i>
                                Pay Now
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Payment Modal -->
    @if($showPaymentModal && $selectedInvoice)
        <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="w-full max-w-md modal-box">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold">Pay Invoice</h3>
                    <button class="btn btn-sm btn-circle btn-ghost" wire:click="closePaymentModal">✕</button>
                </div>

                <div class="my-0 divider"></div>

                <div class="mt-4">
                    <div class="text-sm text-base-content/70">Invoice Number</div>
                    <div class="font-medium">{{ $selectedInvoice->invoice_number }}</div>

                    <div class="mt-3 text-sm text-base-content/70">Amount Due</div>
                    <div class="text-2xl font-bold">${{ number_format($selectedInvoice->amount, 2) }}</div>

                    <div class="mt-3 text-sm text-base-content/70">Due Date</div>
                    <div class="{{ $selectedInvoice->status === 'overdue' ? 'text-error' : '' }}">
                        {{ $selectedInvoice->due_date ? Carbon::parse($selectedInvoice->due_date)->format('M d, Y') : 'N/A' }}
                        @if($selectedInvoice->status === 'overdue')
                            <span class="text-error">
                                (Overdue)
                            </span>
                        @endif
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Payment Method Selection -->
                <div class="mb-6">
                    <h4 class="mb-3 font-medium">Select Payment Method</h4>

                    @php
                        $paymentMethods = \App\Models\PaymentMethod::where('user_id', $this->user->id)->get();
                    @endphp

                    @if($paymentMethods->isEmpty())
                        <div class="mb-4 alert alert-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>You don't have any saved payment methods. Please add a payment method in your billing settings.</span>
                        </div>

                        <a href="{{ route('parents.billing.index') }}" class="btn btn-primary btn-block">
                            <i class="mr-1 fa-solid fa-credit-card"></i>
                            Add Payment Method
                        </a>
                    @else
                        <div class="space-y-3">
                            @foreach($paymentMethods as $method)
                                <div class="flex items-center justify-between p-3 bg-base-200 rounded-lg {{ $method->is_default ? 'border border-primary' : '' }}">
                                    <div class="flex items-center">
                                        <i class="{{ $this->getCardIcon($method->card_type) }} mr-3 text-xl"></i>
                                        <div>
                                            <div class="font-medium">•••• {{ $method->last_four }}</div>
                                            <div class="text-xs text-base-content/70">
                                                {{ $method->card_holder }} • Expires {{ $method->expiry_month }}/{{ $method->expiry_year }}
                                                @if($method->is_default)
                                                    <span class="ml-1 badge badge-sm badge-primary">Default</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            <button
                                class="btn btn-primary btn-block"
                                wire:click="payInvoice"
                                wire:loading.attr="disabled"
                                wire:target="payInvoice"
                            >
                                <i class="mr-1 fa-solid fa-lock"></i>
                                <span wire:loading.remove wire:target="payInvoice">
                                    Pay ${{ number_format($selectedInvoice->amount, 2) }} Now
                                </span>
                                <span wire:loading wire:target="payInvoice">Processing...</span>
                            </button>

                            <div class="mt-3 text-xs text-center text-base-content/70">
                                <i class="mr-1 fa-solid fa-lock"></i>
                                Secure payment processed via our payment gateway
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
