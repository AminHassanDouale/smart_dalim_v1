<?php

namespace App\Livewire\Parents;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public $user;
    public $selectedPayment = null;
    public $showPaymentDetails = false;

    // Filter states
    public $statusFilter = 'all';
    public $dateRangeFilter = 'all';
    public $amountFilter = 'all';
    public $searchQuery = '';
    public $methodFilter = 'all';

    // Sort state
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // Payment statistics
    public $stats = [
        'total_spent' => 0,
        'successful_payments' => 0,
        'failed_payments' => 0,
        'pending_payments' => 0,
        'average_payment' => 0,
        'recurring_payments' => 0
    ];

    // Downloaded payment receipt
    public $downloadingReceiptId = null;

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadPaymentStats();
    }

    public function loadPaymentStats()
    {
        $payments = Payment::where('user_id', $this->user->id)->get();

        $this->stats['total_spent'] = $payments->where('status', 'completed')->sum('amount');
        $this->stats['successful_payments'] = $payments->where('status', 'completed')->count();
        $this->stats['failed_payments'] = $payments->where('status', 'failed')->count();
        $this->stats['pending_payments'] = $payments->where('status', 'pending')->count();

        $avgPayment = $payments->where('status', 'completed')->avg('amount');
        $this->stats['average_payment'] = $avgPayment ? round($avgPayment, 2) : 0;

        // Count recurring payments (payments that happen monthly)
        $this->stats['recurring_payments'] = Payment::where('user_id', $this->user->id)
            ->where('status', 'completed')
            ->whereHas('invoice', function($query) {
                $query->whereNotNull('subscription_id');
            })
            ->count();
    }

    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedDateRangeFilter() { $this->resetPage(); }
    public function updatedAmountFilter() { $this->resetPage(); }
    public function updatedSearchQuery() { $this->resetPage(); }
    public function updatedMethodFilter() { $this->resetPage(); }

    public function sortBy($field)
    {
        if($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function viewPaymentDetails($paymentId)
    {
        $this->selectedPayment = Payment::with(['invoice.subscription.plan', 'paymentMethod'])
            ->where('user_id', $this->user->id)
            ->findOrFail($paymentId);
        $this->showPaymentDetails = true;
    }

    public function closePaymentDetails()
    {
        $this->showPaymentDetails = false;
        $this->selectedPayment = null;
    }

    public function downloadReceipt($paymentId)
    {
        $this->downloadingReceiptId = $paymentId;

        // In a real application, this would generate a PDF receipt
        // For demo purposes, we'll just simulate a download with a delay

        // After download completes
        $this->dispatch('receiptDownloaded', [
            'paymentId' => $paymentId
        ]);
    }

    public function getPaymentsProperty()
    {
        $query = Payment::where('user_id', $this->user->id)
            ->with(['invoice.subscription.plan', 'paymentMethod'])
            ->when($this->searchQuery, function($query) {
                return $query->where(function($q) {
                    $q->where('transaction_id', 'like', '%' . $this->searchQuery . '%')
                    ->orWhereHas('invoice', function($q2) {
                        $q2->where('invoice_number', 'like', '%' . $this->searchQuery . '%');
                    });
                });
            });

        // Apply status filter
        if($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Apply date range filter
        if($this->dateRangeFilter !== 'all') {
            switch($this->dateRangeFilter) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', Carbon::now()->month)
                          ->whereYear('created_at', Carbon::now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month)
                          ->whereYear('created_at', Carbon::now()->subMonth()->year);
                    break;
                case 'this_year':
                    $query->whereYear('created_at', Carbon::now()->year);
                    break;
                case 'last_year':
                    $query->whereYear('created_at', Carbon::now()->subYear()->year);
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

        // Apply payment method filter
        if($this->methodFilter !== 'all') {
            $query->where('payment_method_id', $this->methodFilter);
        }

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate(10);
    }

    public function getPaymentMethodsProperty()
    {
        return PaymentMethod::where('user_id', $this->user->id)
            ->orderBy('is_default', 'desc')
            ->get();
    }

    protected function getCardIcon($type)
    {
        return match($type) {
            'visa' => 'fa-brands fa-cc-visa text-blue-600',
            'mastercard' => 'fa-brands fa-cc-mastercard text-orange-600',
            'amex' => 'fa-brands fa-cc-amex text-blue-800',
            'discover' => 'fa-brands fa-cc-discover text-orange-500',
            default => 'fa-solid fa-credit-card text-gray-600'
        };
    }

    protected function getStatusColor($status)
    {
        return match($status) {
            'completed' => 'success',
            'pending' => 'warning',
            'failed' => 'error',
            default => 'info'
        };
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="mb-8 overflow-hidden text-white shadow-lg bg-gradient-to-r from-primary to-secondary rounded-xl">
            <div class="p-6 md:p-8">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 class="text-3xl font-bold">Payment History</h1>
                        <p class="mt-2 text-white/80">View and manage your payment transactions</p>
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
                    </div>
                </div>

                <!-- Payment Stats -->
                <div class="grid grid-cols-2 gap-4 mt-6 md:grid-cols-4">
                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Total Spent</div>
                        <div class="text-2xl font-bold">${{ number_format($stats['total_spent'], 2) }}</div>
                    </div>

                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Successful</div>
                        <div class="text-2xl font-bold">{{ $stats['successful_payments'] }}</div>
                    </div>

                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Avg. Payment</div>
                        <div class="text-2xl font-bold">${{ number_format($stats['average_payment'], 2) }}</div>
                    </div>

                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Failed</div>
                        <div class="text-2xl font-bold">{{ $stats['failed_payments'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-6 shadow-xl card bg-base-100">
            <div class="p-4 card-body">
                <h2 class="mb-4 text-lg card-title">Filter Payments</h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-5">
                    <!-- Search Filter -->
                    <div>
                        <label class="label">
                            <span class="label-text">Search</span>
                        </label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="searchQuery"
                                placeholder="Transaction ID or Invoice #"
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
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>

                    <!-- Date Range Filter -->
                    <div>
                        <label class="label">
                            <span class="label-text">Date Range</span>
                        </label>
                        <select wire:model.live="dateRangeFilter" class="w-full select select-bordered">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="this_week">This Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_year">This Year</option>
                            <option value="last_year">Last Year</option>
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

                    <!-- Payment Method Filter -->
                    <div>
                        <label class="label">
                            <span class="label-text">Payment Method</span>
                        </label>
                        <select wire:model.live="methodFilter" class="w-full select select-bordered">
                            <option value="all">All Methods</option>
                            @foreach($this->paymentMethods as $method)
                                <option value="{{ $method->id }}">•••• {{ $method->last_four }} {{ $method->is_default ? '(Default)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History Table -->
        <div class="shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="mb-6 text-xl card-title">Payment History</h2>

                @if($this->payments->isEmpty())
                    <div class="py-8 text-center">
                        <i class="mb-4 text-6xl fa-solid fa-credit-card text-base-content/30"></i>
                        <h3 class="text-lg font-medium">No payments found</h3>
                        <p class="mt-1 text-base-content/70">
                            @if($searchQuery || $statusFilter !== 'all' || $dateRangeFilter !== 'all' || $amountFilter !== 'all' || $methodFilter !== 'all')
                                Try adjusting your filter criteria
                            @else
                                You haven't made any payments yet
                            @endif
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table w-full table-zebra">
                            <thead>
                                <tr>
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
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->payments as $payment)
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $payment->created_at->format('M d, Y') }}</div>
                                            <div class="text-xs text-base-content/70">{{ $payment->created_at->format('h:i A') }}</div>
                                        </td>
                                        <td>
                                            <div>
                                                @if($payment->invoice?->subscription)
                                                    <div class="font-medium">{{ $payment->invoice->subscription->plan->name ?? 'Subscription Payment' }}</div>
                                                    <div class="text-xs text-base-content/70">
                                                        Invoice #{{ $payment->invoice->invoice_number }}
                                                    </div>
                                                @elseif($payment->invoice)
                                                    <div class="font-medium">{{ $payment->invoice->description ?? 'One-time Payment' }}</div>
                                                    <div class="text-xs text-base-content/70">
                                                        Invoice #{{ $payment->invoice->invoice_number }}
                                                    </div>
                                                @else
                                                    <div class="font-medium">Payment</div>
                                                    <div class="text-xs text-base-content/70">
                                                        Transaction #{{ $payment->transaction_id }}
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="font-medium">${{ number_format($payment->amount, 2) }}</td>
                                        <td>
                                            @if($payment->paymentMethod)
                                                <div class="flex items-center">
                                                    <i class="{{ $this->getCardIcon($payment->paymentMethod->card_type) }} mr-2"></i>
                                                    <span>•••• {{ $payment->paymentMethod->last_four }}</span>
                                                </div>
                                            @else
                                                Unknown
                                            @endif
                                        </td>
                                        <td>
                                            <div class="badge badge-{{ $this->getStatusColor($payment->status) }}">
                                                {{ ucfirst($payment->status) }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex space-x-2">
                                                <button
                                                    class="btn btn-ghost btn-xs"
                                                    wire:click="viewPaymentDetails({{ $payment->id }})"
                                                >
                                                    <i class="mr-1 fa-solid fa-eye"></i>
                                                    Details
                                                </button>

                                                @if($payment->status === 'completed')
                                                    <button
                                                        class="btn btn-ghost btn-xs {{ $downloadingReceiptId === $payment->id ? 'loading' : '' }}"
                                                        wire:click="downloadReceipt({{ $payment->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="downloadReceipt({{ $payment->id }})"
                                                    >
                                                        <i class="mr-1 fa-solid fa-download" wire:loading.remove wire:target="downloadReceipt({{ $payment->id }})"></i>
                                                        Receipt
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
                        {{ $this->payments->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Payment Details Modal -->
    @if($showPaymentDetails && $selectedPayment)
        <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="w-full max-w-2xl modal-box">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold">Payment Details</h3>
                    <button class="btn btn-sm btn-circle btn-ghost" wire:click="closePaymentDetails">✕</button>
                </div>

                <div class="my-0 divider"></div>

                <!-- Payment Header -->
                <div class="flex flex-col justify-between gap-6 mt-4 md:flex-row">
                    <div>
                        <p class="text-sm text-base-content/70">Transaction ID</p>
                        <p class="font-medium">{{ $selectedPayment->transaction_id ?? 'N/A' }}</p>

                        <p class="mt-3 text-sm text-base-content/70">Date & Time</p>
                        <p class="font-medium">{{ $selectedPayment->created_at->format('M d, Y h:i A') }}</p>
                    </div>

                    <div>
                        <p class="text-sm text-base-content/70">Status</p>
                        <div class="badge badge-{{ $this->getStatusColor($selectedPayment->status) }} mt-1">
                            {{ ucfirst($selectedPayment->status) }}
                        </div>

                        <p class="mt-3 text-sm text-base-content/70">Amount</p>
                        <p class="text-2xl font-bold">${{ number_format($selectedPayment->amount, 2) }}</p>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Payment Method -->
                <div class="mb-6">
                    <h4 class="mb-2 font-medium">Payment Method</h4>
                    @if($selectedPayment->paymentMethod)
                        <div class="flex items-center p-3 rounded-lg bg-base-200">
                            <i class="{{ $this->getCardIcon($selectedPayment->paymentMethod->card_type) }} text-2xl mr-3"></i>
                            <div>
                                <p class="font-medium">
                                    {{ ucfirst($selectedPayment->paymentMethod->card_type) }} •••• {{ $selectedPayment->paymentMethod->last_four }}
                                </p>
                                <p class="text-sm text-base-content/70">
                                    {{ $selectedPayment->paymentMethod->card_holder }} | Expires {{ $selectedPayment->paymentMethod->expiry_month }}/{{ $selectedPayment->paymentMethod->expiry_year }}
                                </p>
                            </div>
                        </div>
                    @else
                        <p class="text-base-content/70">Payment method information not available</p>
                    @endif
                </div>

                <!-- Invoice Information -->
                @if($selectedPayment->invoice)
                    <div class="mb-6">
                        <h4 class="mb-2 font-medium">Invoice Information</h4>
                        <div class="p-3 rounded-lg bg-base-200">
                            <div class="flex items-center justify-between mb-2">
                                <p class="font-medium">Invoice #{{ $selectedPayment->invoice->invoice_number }}</p>
                                <div class="badge {{ $selectedPayment->invoice->status === 'paid' ? 'badge-success' : 'badge-warning' }}">
                                    {{ ucfirst($selectedPayment->invoice->status) }}
                                </div>
                            </div>
                            <p class="text-sm">{{ $selectedPayment->invoice->description }}</p>

                            @if($selectedPayment->invoice->due_date)
                                <p class="mt-2 text-sm text-base-content/70">
                                    Due Date: {{ Carbon::parse($selectedPayment->invoice->due_date)->format('M d, Y') }}
                                </p>
                            @endif

                            @if($selectedPayment->invoice->subscription)
                                <div class="pt-2 mt-2 border-t border-base-300">
                                    <p class="text-sm font-medium">Subscription: {{ $selectedPayment->invoice->subscription->plan->name }}</p>
                                    <p class="text-xs text-base-content/70">
                                        Billing period: {{ Carbon::parse($selectedPayment->invoice->subscription->start_date)->format('M d, Y') }}
                                        to {{ Carbon::parse($selectedPayment->invoice->subscription->end_date)->format('M d, Y') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Actions -->
                <div class="flex justify-between mt-6">
                    <div>
                        @if($selectedPayment->status === 'failed')
                            <button class="btn btn-error btn-sm">
                                <i class="mr-1 fa-solid fa-triangle-exclamation"></i>
                                Report Issue
                            </button>
                        @endif
                    </div>

                    <div class="space-x-2">
                        @if($selectedPayment->invoice)
                            <a href="{{ route('parents.invoices.show', $selectedPayment->invoice_id) }}" class="btn btn-outline btn-sm">
                                <i class="mr-1 fa-solid fa-file-invoice"></i>
                                View Invoice
                            </a>
                        @endif

                        @if($selectedPayment->status === 'completed')
                            <button
                                class="btn btn-primary btn-sm"
                                wire:click="downloadReceipt({{ $selectedPayment->id }})"
                                wire:loading.attr="disabled"
                                wire:target="downloadReceipt"
                            >
                                <i class="mr-1 fa-solid fa-download" wire:loading.remove wire:target="downloadReceipt"></i>
                                <span wire:loading.remove wire:target="downloadReceipt">Download Receipt</span>
                                <span wire:loading wire:target="downloadReceipt">Preparing...</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Add script to handle receipt download completion -->
    <script>
        document.addEventListener('receiptDownloaded', function(e) {
            // In a real application, this would trigger the browser download
            // For this demo, we'll just show a notification
            const paymentId = e.detail.paymentId;
            const notification = document.createElement('div');
            notification.className = 'toast toast-top toast-end';
            notification.innerHTML = `
                <div class="alert alert-success">
                    <i class="fa-solid fa-check-circle"></i>
                    <span>Receipt downloaded successfully</span>
                </div>
            `;
            document.body.appendChild(notification);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        });
    </script>
</div>
