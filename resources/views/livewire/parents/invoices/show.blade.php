<?php

namespace App\Livewire\Parents;

use Livewire\Volt\Component;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $user;
    public $invoice;
    public $showPaymentModal = false;
    public $paymentProcessing = false;
    public $showPrintView = false;
    public $errorMessage = '';

    // For PDF download
    public $downloadInProgress = false;

    public function mount($invoice)
    {
        $this->user = Auth::user();
        $this->invoice = Invoice::with(['subscription.plan', 'payment.paymentMethod', 'user'])
            ->where('user_id', $this->user->id)
            ->findOrFail($invoice);
    }

    public function openPaymentModal()
    {
        if($this->invoice->status !== 'paid') {
            $this->showPaymentModal = true;
        }
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->errorMessage = '';
    }

    public function togglePrintView()
    {
        $this->showPrintView = !$this->showPrintView;
    }

    public function downloadInvoice()
    {
        $this->downloadInProgress = true;

        // In a real app, this would generate a PDF invoice
        // For demo purposes, we'll simulate a download with a delay

        // After a brief delay, show a success message
        $this->dispatch('downloadStarted');
    }

    public function payInvoice()
    {
        if($this->invoice->status === 'paid') {
            return;
        }

        $this->paymentProcessing = true;
        $this->errorMessage = '';

        try {
            // Start transaction
            DB::beginTransaction();

            // Get default payment method
            $paymentMethod = \App\Models\PaymentMethod::where('user_id', $this->user->id)
                ->where('is_default', true)
                ->first();

            if(!$paymentMethod) {
                throw new \Exception('No default payment method found');
            }

            // Process payment
            // In a real app, this would call a payment processor

            // Create payment record
            $payment = new Payment();
            $payment->user_id = $this->user->id;
            $payment->invoice_id = $this->invoice->id;
            $payment->payment_method_id = $paymentMethod->id;
            $payment->amount = $this->invoice->amount;
            $payment->status = 'completed';
            $payment->transaction_id = 'TXN-' . strtoupper(uniqid());
            $payment->save();

            // Update invoice
            $this->invoice->status = 'paid';
            $this->invoice->save();

            // Commit transaction
            DB::commit();

            // Refresh the invoice data
            $this->invoice = Invoice::with(['subscription.plan', 'payment.paymentMethod', 'user'])
                ->findOrFail($this->invoice->id);

            // Close modal
            $this->showPaymentModal = false;
            $this->paymentProcessing = false;

            // Show success message
            $this->dispatch('paymentSuccess');
        } catch(\Exception $e) {
            // Rollback transaction
            DB::rollBack();

            $this->errorMessage = $e->getMessage();
            $this->paymentProcessing = false;

            // Show error message
            $this->dispatch('paymentFailed', ['message' => $e->getMessage()]);
        }
    }

    public function shareInvoice()
    {
        // In a real app, this would create a shareable link or email the invoice
        // For demo purposes, we'll just show a success message
        $this->dispatch('invoiceShared');
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
            'paid' => 'fa-circle-check',
            'unpaid' => 'fa-clock',
            'overdue' => 'fa-triangle-exclamation',
            default => 'fa-info-circle'
        };
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
    x-on:paymentSuccess.window="showToastNotification('Payment successful! Invoice has been marked as paid.')"
    x-on:paymentFailed.window="showToastNotification($event.detail.message, 'error')"
    x-on:downloadStarted.window="showToastNotification('Invoice download started')"
    x-on:invoiceShared.window="showToastNotification('Invoice shared successfully')"
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

    <div class="max-w-5xl mx-auto">
        <!-- Actions Bar -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 sm:flex-row sm:items-center">
            <div class="flex gap-2">
                <a href="{{ route('parents.invoices.index') }}" class="gap-2 btn btn-ghost btn-sm">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Invoices
                </a>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($invoice->status !== 'paid')
                    <button class="gap-2 btn btn-primary btn-sm" wire:click="openPaymentModal">
                        <i class="fa-solid fa-credit-card"></i>
                        Pay Now
                    </button>
                @endif
                <div class="dropdown dropdown-end">
                    <button tabindex="0" class="gap-2 btn btn-ghost btn-sm">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                        <li>
                            <button wire:click="downloadInvoice">
                                <i class="fa-solid fa-download"></i>
                                Download PDF
                            </button>
                        </li>
                        <li>
                            <button wire:click="togglePrintView">
                                <i class="fa-solid fa-print"></i>
                                Print View
                            </button>
                        </li>
                        <li>
                            <button wire:click="shareInvoice">
                                <i class="fa-solid fa-share-nodes"></i>
                                Share Invoice
                            </button>
                        </li>
                        @if($invoice->status !== 'paid')
                            <li>
                                <a href="{{ route('parents.support.index') }}">
                                    <i class="fa-solid fa-circle-question"></i>
                                    Payment Help
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>

        <!-- Invoice Card -->
        <div class="overflow-hidden shadow-xl card bg-base-100" :class="{'print-mode': $wire.showPrintView}">
            <!-- Invoice Header with Status Banner -->
            <div class="bg-{{ $this->getStatusColor($invoice->status) }}/10 border-b border-{{ $this->getStatusColor($invoice->status) }}/20 px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Invoice #{{ $invoice->invoice_number }}</h1>
                    <p class="text-base-content/70">
                        {{ $invoice->description ?: ($invoice->subscription ? 'Subscription Invoice' : 'One-time Payment') }}
                    </p>
                </div>
                <div class="badge badge-lg badge-{{ $this->getStatusColor($invoice->status) }} gap-1">
                    <i class="fa-solid {{ $this->getStatusIcon($invoice->status) }}"></i>
                    {{ ucfirst($invoice->status) }}
                </div>
            </div>

            <div class="p-6 card-body">
                <!-- Invoice Info -->
                <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-2">
                    <!-- Company Info (Left) -->
                    <div>
                        <div class="mb-2 text-2xl font-bold text-primary">Learning Platform</div>
                        <div class="text-sm">123 Education Street</div>
                        <div class="text-sm">Learning City, LC 12345</div>
                        <div class="text-sm">support@learningplatform.com</div>
                        <div class="text-sm">+1 (555) 123-4567</div>
                    </div>

                    <!-- Invoice Details (Right) -->
                    <div class="space-y-1 md:text-right">
                        <div class="flex md:justify-end md:flex-row-reverse">
                            <div class="mr-2 font-medium md:ml-2 md:mr-0">Invoice Date:</div>
                            <div>{{ $invoice->created_at->format('F d, Y') }}</div>
                        </div>

                        <div class="flex md:justify-end md:flex-row-reverse">
                            <div class="mr-2 font-medium md:ml-2 md:mr-0">Due Date:</div>
                            <div>{{ $invoice->due_date ? Carbon::parse($invoice->due_date)->format('F d, Y') : 'N/A' }}</div>
                        </div>

                        @if($invoice->payment)
                            <div class="flex md:justify-end md:flex-row-reverse">
                                <div class="mr-2 font-medium md:ml-2 md:mr-0">Payment Date:</div>
                                <div>{{ $invoice->payment->created_at->format('F d, Y') }}</div>
                            </div>
                        @endif

                        <div class="flex md:justify-end md:flex-row-reverse">
                            <div class="mr-2 font-medium md:ml-2 md:mr-0">Amount:</div>
                            <div class="font-bold">${{ number_format($invoice->amount, 2) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Client Info -->
                <div class="mb-8">
                    <h3 class="mb-2 text-lg font-bold">Bill To:</h3>
                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="font-medium">{{ $invoice->user->name }}</div>
                        <div>{{ $invoice->user->email }}</div>
                        @if($invoice->user->parentProfile)
                            <div>{{ $invoice->user->parentProfile->phone_number ?: '' }}</div>
                            <div>
                                {{ $invoice->user->parentProfile->address ?: '' }}
                                {{ $invoice->user->parentProfile->city ? ', ' . $invoice->user->parentProfile->city : '' }}
                                {{ $invoice->user->parentProfile->state ? ', ' . $invoice->user->parentProfile->state : '' }}
                                {{ $invoice->user->parentProfile->postal_code ?: '' }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Invoice Details -->
                <div class="mb-8">
                    <h3 class="mb-4 text-lg font-bold">Invoice Details</h3>
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($invoice->subscription)
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $invoice->subscription->plan->name }} Subscription</div>
                                            <div class="text-sm text-base-content/70">
                                                Billing period: {{ $invoice->subscription->start_date ? Carbon::parse($invoice->subscription->start_date)->format('M d, Y') : '' }}
                                                to {{ $invoice->subscription->end_date ? Carbon::parse($invoice->subscription->end_date)->format('M d, Y') : '' }}
                                            </div>
                                        </td>
                                        <td class="text-right">${{ number_format($invoice->amount, 2) }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $invoice->description ?: 'Service Fee' }}</div>
                                        </td>
                                        <td class="text-right">${{ number_format($invoice->amount, 2) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total</th>
                                    <th class="text-right">${{ number_format($invoice->amount, 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Payment Information -->
                @if($invoice->payment)
                    <div class="mb-8">
                        <h3 class="mb-4 text-lg font-bold">Payment Information</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="p-4 border rounded-lg bg-success/10 border-success/20">
                                <div class="flex items-center mb-3">
                                    <i class="mr-2 fa-solid fa-circle-check text-success"></i>
                                    <span class="font-medium">Payment Completed</span>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div class="text-sm text-base-content/70">Transaction ID:</div>
                                    <div class="text-sm">{{ $invoice->payment->transaction_id }}</div>

                                    <div class="text-sm text-base-content/70">Payment Date:</div>
                                    <div class="text-sm">{{ $invoice->payment->created_at->format('M d, Y h:i A') }}</div>

                                    <div class="text-sm text-base-content/70">Amount Paid:</div>
                                    <div class="text-sm font-bold">${{ number_format($invoice->payment->amount, 2) }}</div>
                                </div>
                            </div>

                            <div class="p-4 rounded-lg bg-base-200">
                                <div class="mb-3 font-medium">Payment Method</div>

                                @if($invoice->payment->paymentMethod)
                                    <div class="flex items-center">
                                        <i class="{{ $this->getCardIcon($invoice->payment->paymentMethod->card_type) }} text-2xl mr-3"></i>
                                        <div>
                                            <div>{{ ucfirst($invoice->payment->paymentMethod->card_type) }} •••• {{ $invoice->payment->paymentMethod->last_four }}</div>
                                            <div class="text-sm text-base-content/70">
                                                {{ $invoice->payment->paymentMethod->card_holder }}
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div>Payment method information not available</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Notes & Terms -->
                <div class="pt-6 border-t">
                    @if($invoice->status !== 'paid')
                        <div class="mb-4 alert alert-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div>
                                <div class="font-bold">Payment Required</div>
                                <div class="text-sm">
                                    Please complete payment by {{ $invoice->due_date ? Carbon::parse($invoice->due_date)->format('F d, Y') : 'as soon as possible' }}.
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="text-sm text-base-content/70">
                        <p class="mb-2 font-medium">Terms & Conditions:</p>
                        <p>Payment is due within 14 days of invoice date. Please make payments via the payment portal or contact support for assistance.</p>
                        <p class="mt-2">Thank you for choosing our Learning Platform!</p>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 text-sm text-center bg-base-200 text-base-content/70">
                <p>If you have any questions about this invoice, please contact our support team.</p>
                <p>support@learningplatform.com | +1 (555) 123-4567</p>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    @if($showPaymentModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="w-full max-w-md modal-box">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold">Pay Invoice</h3>
                    <button
                        class="btn btn-sm btn-circle btn-ghost"
                        wire:click="closePaymentModal"
                        @if($paymentProcessing) disabled @endif
                    >✕</button>
                </div>

                <div class="my-0 divider"></div>

                @if($errorMessage)
                    <div class="mb-4 alert alert-error">
                        <i class="fa-solid fa-circle-xmark"></i>
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif

                <div class="mt-4">
                    <div class="text-sm text-base-content/70">Invoice Number</div>
                    <div class="font-medium">{{ $invoice->invoice_number }}</div>

                    <div class="mt-3 mb-6">
                        <div class="text-sm text-base-content/70">Amount Due</div>
                        <div class="text-3xl font-bold">${{ number_format($invoice->amount, 2) }}</div>
                    </div>

                    <div class="mb-6">
                        <div class="mb-2 text-sm font-medium">Payment Method</div>

                        @php
                            $paymentMethods = \App\Models\PaymentMethod::where('user_id', $this->user->id)->get();
                        @endphp

                        @if($paymentMethods->isEmpty())
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <span>You don't have any saved payment methods. Please add a payment method in your billing settings.</span>
                            </div>

                            <div class="mt-4">
                                <a href="{{ route('parents.billing.index') }}" class="btn btn-primary btn-block">
                                    <i class="mr-1 fa-solid fa-credit-card"></i>
                                    Add Payment Method
                                </a>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($paymentMethods as $method)
                                    <div class="flex items-center p-3 rounded-lg bg-base-200 {{ $method->is_default ? 'border border-primary' : '' }}">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="{{ $method->id }}"
                                            class="mr-3 radio radio-primary"
                                            {{ $method->is_default ? 'checked' : '' }}
                                            disabled
                                        >
                                        <div class="flex items-center flex-grow">
                                            <i class="{{ $this->getCardIcon($method->card_type) }} text-xl mr-3"></i>
                                            <div>
                                                <div class="font-medium">•••• {{ $method->last_four }}</div>
                                                <div class="text-xs text-base-content/70">
                                                    {{ $method->card_holder }} • Expires {{ $method->expiry_month }}/{{ $method->expiry_year }}
                                                </div>
                                            </div>
                                        </div>
                                        @if($method->is_default)
                                            <div class="ml-2 badge badge-primary">Default</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-6">
                                <button
                                    class="btn btn-primary btn-block"
                                    wire:click="payInvoice"
                                    wire:loading.attr="disabled"
                                    @if($paymentProcessing) disabled @endif
                                >
                                    <i class="mr-1 fa-solid fa-lock"></i>
                                    <span wire:loading.remove wire:target="payInvoice">Pay ${{ number_format($invoice->amount, 2) }} Now</span>
                                    <span wire:loading wire:target="payInvoice">Processing Payment...</span>
                                </button>

                                <div class="flex items-center justify-center gap-2 mt-3 text-xs text-base-content/70">
                                    <i class="fa-solid fa-shield-halved"></i>
                                    <span>Payments are secure and encrypted</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Print-Only Styles -->
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .print-mode, .print-mode * {
                visibility: visible;
            }
            .print-mode {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .print-mode .dropdown,
            .print-mode button {
                display: none !important;
            }
        }
    </style>

    <!-- Auto-print when print view is enabled -->
    <script>
        document.addEventListener('livewire:initialized', () => {
            @this.on('updatedShowPrintView', (value) => {
                if (value) {
                    setTimeout(() => {
                        window.print();
                        @this.set('showPrintView', false);
                    }, 300);
                }
            });
        });
    </script>
</div>
