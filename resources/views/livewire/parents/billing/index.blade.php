<?php

namespace App\Livewire\Parents;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\PaymentMethod;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use WithPagination;

    public $user;
    public $activeTab = 'overview';
    public $subscription;
    public $paymentMethods = [];
    public $selectedPaymentMethod = null;

    // Payment method form
    public $showAddPaymentMethodModal = false;
    public $newPaymentMethod = [
        'card_holder' => '',
        'card_number' => '',
        'expiry_month' => '',
        'expiry_year' => '',
        'cvv' => '',
        'is_default' => false
    ];

    // Invoice filters
    public $statusFilter = 'all';
    public $dateRangeFilter = 'all';
    public $searchQuery = '';

    // Subscription change
    public $showChangePlanModal = false;
    public $availablePlans = [];
    public $selectedPlan = null;

    // Cancel subscription
    public $showCancelModal = false;
    public $cancellationReason = '';

    // Notification state
    public $notification = [
        'show' => false,
        'message' => '',
        'type' => 'success' // success, error, warning
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadSubscriptionData();
        $this->loadPaymentMethods();
        $this->loadAvailablePlans();
    }

    public function loadSubscriptionData()
    {
        try {
            // Get the active subscription with plan details
            $this->subscription = Subscription::where('user_id', $this->user->id)
                ->with(['plan'])
                ->where(function($query) {
                    $query->where('status', 'active')
                          ->orWhere(function($q) {
                              $q->where('status', 'cancelled')
                                ->where('end_date', '>=', now());
                          });
                })
                ->latest()
                ->first();

            Log::info('Loaded subscription data', [
                'user_id' => $this->user->id,
                'has_subscription' => $this->subscription ? 'yes' : 'no'
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading subscription data', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function loadPaymentMethods()
    {
        try {
            // Get all payment methods for the user
            $this->paymentMethods = PaymentMethod::where('user_id', $this->user->id)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            $this->selectedPaymentMethod = $this->paymentMethods->where('is_default', true)->first()?->id ?? null;

            Log::info('Loaded payment methods', [
                'user_id' => $this->user->id,
                'count' => $this->paymentMethods->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading payment methods', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function loadAvailablePlans()
    {
        try {
            // Fetch plans from the database
            $plans = Plan::where('is_active', true)
                ->orderBy('price')
                ->get();

            // Transform to array with features
            $this->availablePlans = $plans->map(function($plan) {
                $features = $plan->features ? json_decode($plan->features, true) : [];
                if (empty($features)) {
                    // Generate default features based on plan tier
                    if ($plan->price < 30) {
                        $features = [
                            'Single child access',
                            'Core learning materials',
                            'Basic progress tracking',
                            'Email support',
                        ];
                    } elseif ($plan->price < 60) {
                        $features = [
                            'Up to 3 children',
                            'All learning materials',
                            'Advanced progress tracking',
                            'Priority email support',
                            'Live tutoring sessions (2/month)',
                        ];
                    } else {
                        $features = [
                            'Unlimited children',
                            'All learning materials',
                            'Advanced progress tracking',
                            '24/7 priority support',
                            'Live tutoring sessions (5/month)',
                            'Personalized learning plans',
                        ];
                    }
                }

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'interval' => $plan->interval ?? 'month',
                    'features' => $features,
                    'children_limit' => $plan->children_limit ?? ($plan->name == 'Basic' ? 1 : ($plan->name == 'Family' ? 3 : 999)),
                    'sessions_limit' => $plan->sessions_limit ?? ($plan->name == 'Basic' ? 5 : ($plan->name == 'Family' ? 10 : 20)),
                    'storage_limit' => $plan->storage_limit ?? ($plan->name == 'Basic' ? 1024 : ($plan->name == 'Family' ? 5120 : 10240)),
                ];
            })->toArray();

            // If plans are empty from database, provide fallback plans
            if (empty($this->availablePlans)) {
                $this->availablePlans = [
                    [
                        'id' => 1,
                        'name' => 'Basic Plan',
                        'description' => '1 child, basic features',
                        'price' => 29.99,
                        'interval' => 'month',
                        'features' => [
                            'Single child access',
                            'Core learning materials',
                            'Basic progress tracking',
                            'Email support',
                        ],
                        'children_limit' => 1,
                        'sessions_limit' => 5,
                        'storage_limit' => 1024,
                    ],
                    [
                        'id' => 2,
                        'name' => 'Family Plan',
                        'description' => 'Up to 3 children, all features',
                        'price' => 49.99,
                        'interval' => 'month',
                        'features' => [
                            'Up to 3 children',
                            'All learning materials',
                            'Advanced progress tracking',
                            'Priority email support',
                            'Live tutoring sessions (2/month)',
                        ],
                        'children_limit' => 3,
                        'sessions_limit' => 10,
                        'storage_limit' => 5120,
                    ],
                    [
                        'id' => 3,
                        'name' => 'Premium Plan',
                        'description' => 'Unlimited children, all features + premium support',
                        'price' => 79.99,
                        'interval' => 'month',
                        'features' => [
                            'Unlimited children',
                            'All learning materials',
                            'Advanced progress tracking',
                            '24/7 priority support',
                            'Live tutoring sessions (5/month)',
                            'Personalized learning plans',
                        ],
                        'children_limit' => 999,
                        'sessions_limit' => 20,
                        'storage_limit' => 10240,
                    ],
                ];
            }

            // Set selected plan if subscription exists
            if ($this->subscription) {
                $this->selectedPlan = $this->subscription->plan_id;
            }

            Log::info('Loaded available plans', [
                'user_id' => $this->user->id,
                'count' => count($this->availablePlans)
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading available plans', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getInvoicesProperty()
    {
        try {
            $query = Invoice::where('user_id', $this->user->id)
                ->with(['subscription.plan', 'payment']);

            // Apply filters
            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }

            if ($this->dateRangeFilter !== 'all') {
                if ($this->dateRangeFilter === 'this_month') {
                    $query->whereMonth('created_at', Carbon::now()->month)
                          ->whereYear('created_at', Carbon::now()->year);
                } elseif ($this->dateRangeFilter === 'last_month') {
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month)
                          ->whereYear('created_at', Carbon::now()->subMonth()->year);
                } elseif ($this->dateRangeFilter === 'last_3_months') {
                    $query->where('created_at', '>=', Carbon::now()->subMonths(3));
                } elseif ($this->dateRangeFilter === 'last_6_months') {
                    $query->where('created_at', '>=', Carbon::now()->subMonths(6));
                } elseif ($this->dateRangeFilter === 'this_year') {
                    $query->whereYear('created_at', Carbon::now()->year);
                }
            }

            if ($this->searchQuery) {
                $query->where('invoice_number', 'like', '%' . $this->searchQuery . '%');
            }

            return $query->orderBy('created_at', 'desc')->paginate(10);
        } catch (\Exception $e) {
            Log::error('Error fetching invoices', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);

            return collect([])->paginate(10);
        }
    }

    public function getPaymentsProperty()
    {
        try {
            return Payment::where('user_id', $this->user->id)
                ->with(['invoice', 'paymentMethod'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();
        } catch (\Exception $e) {
            Log::error('Error fetching payments', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);

            return collect([]);
        }
    }

    public function getCurrentUsageProperty()
    {
        try {
            $parentProfile = $this->user->parentProfile;

            if (!$parentProfile || !$this->subscription) {
                return [
                    'children' => 0,
                    'children_limit' => 0,
                    'children_percentage' => 0,
                    'sessions' => 0,
                    'sessions_limit' => 0,
                    'sessions_percentage' => 0,
                    'storage' => 0,
                    'storage_limit' => 0,
                    'storage_percentage' => 0,
                ];
            }

            // Get actual children count
            $childrenCount = $parentProfile->children()->count();

            // Find the plan details
            $planDetails = collect($this->availablePlans)
                ->firstWhere('id', $this->subscription->plan_id);

            // If plan details not found, use default values
            if (!$planDetails) {
                $childrenLimit = $this->subscription->plan->children_limit ?? 1;
                $sessionsLimit = $this->subscription->plan->sessions_limit ?? 10;
                $storageLimit = $this->subscription->plan->storage_limit ?? 1024;
            } else {
                $childrenLimit = $planDetails['children_limit'];
                $sessionsLimit = $planDetails['sessions_limit'];
                $storageLimit = $planDetails['storage_limit'];
            }

            // Calculate sessions used (actual query)
            $sessionsUsed = DB::table('learning_sessions')
                ->join('children', 'learning_sessions.children_id', '=', 'children.id')
                ->join('parent_profiles', 'children.parent_profile_id', '=', 'parent_profiles.id')
                ->where('parent_profiles.user_id', $this->user->id)
                ->where('learning_sessions.created_at', '>=', now()->startOfMonth())
                ->count();

            // Calculate storage used in MB (would typically be calculated based on uploads)
            // For demo: estimate 50MB per child plus base 100MB
            $storageUsed = ($childrenCount * 50) + 100;

            // Ensure we don't divide by zero
            $childrenPercentage = $childrenLimit > 0 ? min(100, round(($childrenCount / $childrenLimit) * 100)) : 100;
            $sessionsPercentage = $sessionsLimit > 0 ? min(100, round(($sessionsUsed / $sessionsLimit) * 100)) : 100;
            $storagePercentage = $storageLimit > 0 ? min(100, round(($storageUsed / $storageLimit) * 100)) : 100;

            return [
                'children' => $childrenCount,
                'children_limit' => $childrenLimit,
                'children_percentage' => $childrenPercentage,
                'sessions' => $sessionsUsed,
                'sessions_limit' => $sessionsLimit,
                'sessions_percentage' => $sessionsPercentage,
                'storage' => $storageUsed,
                'storage_limit' => $storageLimit,
                'storage_percentage' => $storagePercentage,
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating usage data', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'children' => 0,
                'children_limit' => 0,
                'children_percentage' => 0,
                'sessions' => 0,
                'sessions_limit' => 0,
                'sessions_percentage' => 0,
                'storage' => 0,
                'storage_limit' => 0,
                'storage_percentage' => 0,
            ];
        }
    }

    public function showNotification($message, $type = 'success')
    {
        $this->notification = [
            'show' => true,
            'message' => $message,
            'type' => $type
        ];

        // Auto-hide after 5 seconds
        $this->dispatch('autoDismissNotification');
    }

    public function dismissNotification()
    {
        $this->notification['show'] = false;
    }

    public function addPaymentMethod()
    {
        $this->validate([
            'newPaymentMethod.card_holder' => 'required|string|max:255',
            'newPaymentMethod.card_number' => 'required|string|size:16',
            'newPaymentMethod.expiry_month' => 'required|numeric|min:1|max:12',
            'newPaymentMethod.expiry_year' => 'required|numeric|min:' . date('Y') . '|max:' . (date('Y') + 20),
            'newPaymentMethod.cvv' => 'required|string|size:3',
        ]);

        try {
            // Start transaction
            DB::beginTransaction();

            // Create new payment method record
            $paymentMethod = new PaymentMethod();
            $paymentMethod->user_id = $this->user->id;
            $paymentMethod->card_holder = $this->newPaymentMethod['card_holder'];
            $paymentMethod->card_type = $this->detectCardType($this->newPaymentMethod['card_number']);
            $paymentMethod->last_four = substr($this->newPaymentMethod['card_number'], -4);
            $paymentMethod->expiry_month = $this->newPaymentMethod['expiry_month'];
            $paymentMethod->expiry_year = $this->newPaymentMethod['expiry_year'];
            $paymentMethod->is_default = $this->newPaymentMethod['is_default'];

            // If this is the first card or set as default, update other cards
            if ($this->paymentMethods->isEmpty() || $this->newPaymentMethod['is_default']) {
                PaymentMethod::where('user_id', $this->user->id)
                    ->update(['is_default' => false]);
                $paymentMethod->is_default = true;
            }

            $paymentMethod->save();

            // Commit transaction
            DB::commit();

            // Reload payment methods
            $this->loadPaymentMethods();
            $this->showAddPaymentMethodModal = false;
            $this->resetPaymentMethodForm();

            $this->showNotification('Payment method added successfully', 'success');

            Log::info('Payment method added', [
                'user_id' => $this->user->id,
                'payment_method_id' => $paymentMethod->id
            ]);
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();

            $this->showNotification('Failed to add payment method: ' . $e->getMessage(), 'error');

            Log::error('Error adding payment method', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function setDefaultPaymentMethod($id)
    {
        try {
            // Start transaction
            DB::beginTransaction();

            // Clear all default flags
            PaymentMethod::where('user_id', $this->user->id)
                ->update(['is_default' => false]);

            // Set the selected one as default
            PaymentMethod::where('id', $id)
                ->where('user_id', $this->user->id)
                ->update(['is_default' => true]);

            // Commit transaction
            DB::commit();

            // Reload payment methods
            $this->loadPaymentMethods();

            $this->showNotification('Default payment method updated', 'success');

            Log::info('Default payment method updated', [
                'user_id' => $this->user->id,
                'payment_method_id' => $id
            ]);
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();

            $this->showNotification('Failed to update default payment method', 'error');

            Log::error('Error updating default payment method', [
                'user_id' => $this->user->id,
                'payment_method_id' => $id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deletePaymentMethod($id)
    {
        try {
            // Start transaction
            DB::beginTransaction();

            // Find the payment method
            $method = PaymentMethod::where('id', $id)
                ->where('user_id', $this->user->id)
                ->first();

            if (!$method) {
                throw new \Exception('Payment method not found');
            }

            // Check if it's the only payment method
            if ($this->paymentMethods->count() <= 1) {
                throw new \Exception('Cannot delete the only payment method');
            }

            // If it's the default payment method, set another one as default
            if ($method->is_default) {
                $newDefault = $this->paymentMethods->where('id', '!=', $id)->first();
                if ($newDefault) {
                    $newDefault->is_default = true;
                    $newDefault->save();
                }
            }

            // Delete the payment method
            $method->delete();

            // Commit transaction
            DB::commit();

            // Reload payment methods
            $this->loadPaymentMethods();

            $this->showNotification('Payment method deleted successfully', 'success');

            Log::info('Payment method deleted', [
                'user_id' => $this->user->id,
                'payment_method_id' => $id
            ]);
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();

            $this->showNotification('Failed to delete payment method: ' . $e->getMessage(), 'error');

            Log::error('Error deleting payment method', [
                'user_id' => $this->user->id,
                'payment_method_id' => $id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function changePlan()
    {
        $this->validate([
            'selectedPlan' => 'required|numeric',
        ]);

        try {
            // Start transaction
            DB::beginTransaction();

            // Check if default payment method exists
            if (!$this->paymentMethods->where('is_default', true)->first() && !$this->subscription) {
                throw new \Exception('Please add a payment method first');
            }

            // Find the plan
            $plan = Plan::find($this->selectedPlan);
            if (!$plan) {
                throw new \Exception('Selected plan not found');
            }

            // Calculate proration if needed
            $prorationAmount = 0;
            if ($this->subscription) {
                // In a real app, calculate actual proration
                $remainingDays = Carbon::now()->diffInDays($this->subscription->end_date, false);
                $totalDays = 30; // Assume 30-day billing cycle

                if ($remainingDays > 0) {
                    $currentPlanDailyRate = $this->subscription->plan->price / $totalDays;
                    $newPlanDailyRate = $plan->price / $totalDays;
                    $prorationAmount = ($newPlanDailyRate - $currentPlanDailyRate) * $remainingDays;
                }
            }

            // Get the default payment method
            $paymentMethod = $this->paymentMethods->where('is_default', true)->first();

            // Create or update subscription
            if ($this->subscription) {
                // Update existing subscription
                $oldPlanId = $this->subscription->plan_id;
                $this->subscription->plan_id = $this->selectedPlan;

                // If subscription was cancelled, reactivate it
                if ($this->subscription->status === 'cancelled') {
                    $this->subscription->status = 'active';
                    $this->subscription->cancelled_at = null;
                    $this->subscription->cancellation_reason = null;

                    // Set next billing date
                    $this->subscription->end_date = Carbon::now()->addMonth();
                }

                $this->subscription->save();

                // Create an invoice for the proration amount if needed
                if ($prorationAmount != 0) {
                    $invoice = new Invoice();
                    $invoice->user_id = $this->user->id;
                    $invoice->subscription_id = $this->subscription->id;
                    $invoice->invoice_number = 'INV-' . strtoupper(uniqid());
                    $invoice->amount = abs($prorationAmount);
                    $invoice->status = $prorationAmount > 0 ? 'unpaid' : 'paid';
                    $invoice->description = $prorationAmount > 0
                        ? "Plan change proration: {$this->subscription->plan->name} to {$plan->name}"
                        : "Plan change credit: {$this->subscription->plan->name} to {$plan->name}";
                    $invoice->due_date = Carbon::now()->addDays(7);
                    $invoice->save();

                    // Create a payment record for credits
                    if ($prorationAmount < 0) {
                        $payment = new Payment();
                        $payment->user_id = $this->user->id;
                        $payment->invoice_id = $invoice->id;
                        $payment->payment_method_id = $paymentMethod->id;
                        $payment->amount = abs($prorationAmount);
                        $payment->status = 'completed';
                        $payment->transaction_id = 'CREDIT-' . strtoupper(uniqid());
                        $payment->save();
                    }
                }

                Log::info('Updated subscription plan', [
                    'user_id' => $this->user->id,
                    'subscription_id' => $this->subscription->id,
                    'old_plan_id' => $oldPlanId,
                    'new_plan_id' => $this->selectedPlan
                ]);
            } else {
                // Create new subscription
                $subscription = new Subscription();
                $subscription->user_id = $this->user->id;
                $subscription->plan_id = $this->selectedPlan;
                $subscription->status = 'active';
                $subscription->start_date = Carbon::now();
                $subscription->end_date = Carbon::now()->addMonth();
                $subscription->save();

                // Create an invoice for the initial subscription
                $invoice = new Invoice();
                $invoice->user_id = $this->user->id;
                $invoice->subscription_id = $subscription->id;
                $invoice->invoice_number = 'INV-' . strtoupper(uniqid());
                $invoice->amount = $plan->price;
                $invoice->status = 'paid'; // Assume paid immediately
                $invoice->description = "Initial subscription: {$plan->name}";
                $invoice->due_date = Carbon::now();
                $invoice->save();

                // Create a payment record
                $payment = new Payment();
                $payment->user_id = $this->user->id;
                $payment->invoice_id = $invoice->id;
                $payment->payment_method_id = $paymentMethod->id;
                $payment->amount = $plan->price;
                $payment->status = 'completed';
                $payment->transaction_id = 'TXN-' . strtoupper(uniqid());
                $payment->save();

                $this->subscription = $subscription;

                Log::info('Created new subscription', [
                    'user_id' => $this->user->id,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $this->selectedPlan
                ]);
            }

            // Commit transaction
            DB::commit();

            // Close modal and reload data
            $this->showChangePlanModal = false;
            $this->loadSubscriptionData();

            $this->showNotification('Subscription plan updated successfully', 'success');
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();

            $this->showNotification('Failed to update subscription plan: ' . $e->getMessage(), 'error');

            Log::error('Error changing subscription plan', [
                'user_id' => $this->user->id,
                'selected_plan' => $this->selectedPlan,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function cancelSubscription()
    {
        $this->validate([
            'cancellationReason' => 'required|string|min:10',
        ]);

        try {
            // Start transaction
            DB::beginTransaction();

            if (!$this->subscription) {
                throw new \Exception('No active subscription found');
            }

            if ($this->subscription->status !== 'active') {
                throw new \Exception('Subscription is not active');
            }

            // Cancel the subscription
            $this->subscription->status = 'cancelled';
            $this->subscription->cancellation_reason = $this->cancellationReason;
            $this->subscription->cancelled_at = Carbon::now();
            $this->subscription->save();

            // Commit transaction
            DB::commit();

            // Close modal and reload data
            $this->showCancelModal = false;
            $this->cancellationReason = '';
            $this->loadSubscriptionData();

            $this->showNotification('Subscription cancelled successfully. Service will continue until the end of your billing period.', 'success');

            Log::info('Subscription cancelled', [
                'user_id' => $this->user->id,
                'subscription_id' => $this->subscription->id,
                'reason' => $this->cancellationReason
            ]);
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();

            $this->showNotification('Failed to cancel subscription: ' . $e->getMessage(), 'error');

            Log::error('Error cancelling subscription', [
                'user_id' => $this->user->id,
                'subscription_id' => $this->subscription ? $this->subscription->id : null,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function resetPaymentMethodForm()
    {
        $this->newPaymentMethod = [
            'card_holder' => '',
            'card_number' => '',
            'expiry_month' => '',
            'expiry_year' => '',
            'cvv' => '',
            'is_default' => false
        ];
    }

    public function downloadInvoice($invoiceId)
    {
        try {
            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $this->user->id)
                ->first();

            if (!$invoice) {
                $this->showNotification('Invoice not found', 'error');
                return;
            }

            // In a real app, this would generate a PDF and trigger a download
            // For demo purposes, just show a notification
            $this->showNotification('Invoice #' . $invoice->invoice_number . ' is being downloaded', 'success');

            Log::info('Invoice download requested', [
                'user_id' => $this->user->id,
                'invoice_id' => $invoiceId
            ]);
        } catch (\Exception $e) {
            $this->showNotification('Failed to download invoice: ' . $e->getMessage(), 'error');

            Log::error('Error downloading invoice', [
                'user_id' => $this->user->id,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function detectCardType($number)
    {
        $firstDigit = substr($number, 0, 1);
        $firstTwoDigits = substr($number, 0, 2);

        if ($firstDigit === '4') {
            return 'visa';
        } elseif ($firstTwoDigits >= '51' && $firstTwoDigits <= '55') {
            return 'mastercard';
        } elseif ($firstTwoDigits === '34' || $firstTwoDigits === '37') {
            return 'amex';
        } elseif ($firstTwoDigits === '65' || $firstTwoDigits === '60') {
            return 'discover';
        } else {
            return 'unknown';
        }
    }

    public function getCardIcon($type)
    {
        return match($type) {
            'visa' => 'fa-brands fa-cc-visa text-blue-600',
            'mastercard' => 'fa-brands fa-cc-mastercard text-orange-600',
            'amex' => 'fa-brands fa-cc-amex text-blue-800',
            'discover' => 'fa-brands fa-cc-discover text-orange-500',
            default => 'fa-solid fa-credit-card text-gray-600'
        };
    }

    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedDateRangeFilter() { $this->resetPage(); }
    public function updatedSearchQuery() { $this->resetPage(); }
}; ?>

<div class="min-h-screen p-6 bg-base-200"
    x-data="{
        showNotification: false,
        notificationMessage: '',
        notificationType: 'success',
        initNotification() {
            $wire.$watch('notification', value => {
                this.showNotification = value.show;
                this.notificationMessage = value.message;
                this.notificationType = value.type;

                if (this.showNotification) {
                    setTimeout(() => {
                        $wire.dismissNotification();
                    }, 5000);
                }
            });
        }
    }"
    x-init="initNotification()"
    @autoDismissNotification.window="setTimeout(() => { $wire.dismissNotification() }, 5000)"
>
    <!-- Notification Toast -->
    <div
        x-show="showNotification"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform scale-90"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-90"
        :class="{
            'alert-success': notificationType === 'success',
            'alert-error': notificationType === 'error',
            'alert-warning': notificationType === 'warning'
        }"
        class="fixed z-50 max-w-sm shadow-lg top-4 right-4 alert"
    >
        <div class="flex justify-between w-full">
            <div class="flex">
                <svg x-show="notificationType === 'success'" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <svg x-show="notificationType === 'error'" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <svg x-show="notificationType === 'warning'" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-current" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                <span class="ml-2" x-text="notificationMessage">Message here</span>
            </div>
            <button @click="$wire.dismissNotification()" class="btn btn-sm btn-ghost">×</button>
        </div>
    </div>

    <div class="mx-auto max-w-7xl">
        <!-- Header with summary stats -->
        <div class="mb-8 overflow-hidden text-white shadow-lg bg-gradient-to-r from-primary to-secondary rounded-xl">
            <div class="p-6 md:p-8">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 class="text-3xl font-bold">Billing & Subscription</h1>
                        <p class="mt-2 text-white/80">
                            Manage your subscription plan, payment methods, and view billing history
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('parents.dashboard') }}" class="text-white btn btn-ghost btn-sm bg-white/10">
                            <x-icon name="o-home" class="w-4 h-4 mr-1" />
                            Dashboard
                        </a>
                        <a href="{{ route('parents.invoices.index') }}" class="text-white btn btn-ghost btn-sm bg-white/10">
                            <x-icon name="o-document-text" class="w-4 h-4 mr-1" />
                            Invoices
                        </a>
                    </div>
                </div>

                @if($subscription)
                    <div class="p-4 mt-6 rounded-lg bg-white/20">
                        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                            <div>
                                <div class="text-sm text-white/80">Current Plan</div>
                                <div class="text-xl font-bold">{{ $subscription->plan->name }}</div>
                                <div class="badge badge-sm mt-1 {{ $subscription->status === 'active' ? 'badge-success' : ($subscription->status === 'cancelled' ? 'badge-error' : 'badge-warning') }}">
                                    {{ ucfirst($subscription->status) }}
                                </div>
                            </div>

                            <div>
                                <div class="text-sm text-white/80">Next Billing Date</div>
                                <div class="font-medium">
                                    @if($subscription->status === 'active')
                                        {{ Carbon::parse($subscription->end_date)->format('M d, Y') }}
                                    @else
                                        N/A
                                    @endif
                                </div>
                            </div>

                            <div>
                                <div class="text-sm text-white/80">Amount</div>
                                <div class="font-medium">${{ number_format($subscription->plan->price, 2) }}/{{ $subscription->plan->interval }}</div>
                            </div>

                            <div class="flex gap-2">
                                @if($subscription->status === 'active')
                                    <button class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30" wire:click="$set('showChangePlanModal', true)">
                                        Change Plan
                                    </button>
                                    <button class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30" wire:click="$set('showCancelModal', true)">
                                        Cancel
                                    </button>
                                @elseif($subscription->status === 'cancelled')
                                    <button class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30" wire:click="$set('showChangePlanModal', true)">
                                        Reactivate
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="p-4 mt-6 rounded-lg bg-white/20">
                        <div class="text-center">
                            <div class="mb-2 text-xl font-bold">No Active Subscription</div>
                            <p class="mb-4">Choose a subscription plan to get started with our services.</p>
                            <button class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30" wire:click="$set('showChangePlanModal', true)">
                                Select Plan
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="p-1 mb-6 tabs tabs-boxed bg-base-100">
            <button class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}" wire:click="$set('activeTab', 'overview')">Overview</button>
            <button class="tab {{ $activeTab === 'payment_methods' ? 'tab-active' : '' }}" wire:click="$set('activeTab', 'payment_methods')">Payment Methods</button>
            <button class="tab {{ $activeTab === 'invoices' ? 'tab-active' : '' }}" wire:click="$set('activeTab', 'invoices')">Invoices & Payments</button>
            <button class="tab {{ $activeTab === 'plans' ? 'tab-active' : '' }}" wire:click="$set('activeTab', 'plans')">Plans & Pricing</button>
        </div>

        <!-- Tab Content -->
        <div>
            <!-- Overview Tab -->
            @if($activeTab === 'overview')
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Subscription Summary -->
                    <div class="space-y-6 lg:col-span-2">
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="flex items-center card-title">
                                    <x-icon name="o-chart-bar" class="w-5 h-5 mr-2" />
                                    Current Usage
                                </h2>

                                <div class="my-2 divider"></div>

                                @php
                                    $usage = $this->currentUsage;
                                @endphp

                                <div class="space-y-6">
                                    <!-- Children Usage -->
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="font-medium">Children</span>
                                            <span>{{ $usage['children'] }} / {{ $usage['children_limit'] > 0 ? $usage['children_limit'] : 'Unlimited' }}</span>
                                        </div>
                                        <div class="w-full bg-base-200 rounded-full h-2.5">
                                            <div
                                                class="h-2.5 rounded-full transition-all duration-500 bg-primary"
                                                style="width: {{ $usage['children_percentage'] }}%"
                                            ></div>
                                        </div>
                                    </div>

                                    <!-- Sessions Usage -->
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="font-medium">Learning Sessions</span>
                                            <span>{{ $usage['sessions'] }} / {{ $usage['sessions_limit'] > 0 ? $usage['sessions_limit'] : 'Unlimited' }}</span>
                                        </div>
                                        <div class="w-full bg-base-200 rounded-full h-2.5">
                                            <div
                                                class="h-2.5 rounded-full transition-all duration-500 bg-secondary"
                                                style="width: {{ $usage['sessions_percentage'] }}%"
                                            ></div>
                                        </div>
                                    </div>

                                    <!-- Storage Usage -->
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="font-medium">Storage Usage</span>
                                            <span>{{ $usage['storage'] }} MB / {{ $usage['storage_limit'] > 0 ? $usage['storage_limit'] . ' MB' : 'Unlimited' }}</span>
                                        </div>
                                        <div class="w-full bg-base-200 rounded-full h-2.5">
                                            <div
                                                class="h-2.5 rounded-full transition-all duration-500 bg-accent"
                                                style="width: {{ $usage['storage_percentage'] }}%"
                                            ></div>
                                        </div>
                                    </div>
                                </div>

                                @if($subscription && $usage['children_percentage'] > 80)
                                    <div class="mt-6 alert alert-warning">
                                        <x-icon name="o-exclamation-triangle" class="w-5 h-5" />
                                        <span>You're approaching your children limit. Consider upgrading your plan for more capacity.</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Recent Payments -->
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="flex items-center card-title">
                                    <x-icon name="o-credit-card" class="w-5 h-5 mr-2" />
                                    Recent Payments
                                </h2>

                                <div class="my-2 divider"></div>

                                @if($this->payments->isEmpty())
                                    <div class="py-8 text-center">
                                        <x-icon name="o-credit-card" class="w-16 h-16 mx-auto text-base-content/30" />
                                        <h3 class="mt-4 text-lg font-medium">No payment history</h3>
                                        <p class="mt-1 text-base-content/70">
                                            No payments have been processed yet.
                                        </p>
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="table w-full table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Amount</th>
                                                    <th>Method</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($this->payments as $payment)
                                                    <tr>
                                                        <td>{{ $payment->created_at->format('M d, Y') }}</td>
                                                        <td>${{ number_format($payment->amount, 2) }}</td>
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
                                                            <div class="badge {{ $payment->status === 'completed' ? 'badge-success' : ($payment->status === 'failed' ? 'badge-error' : 'badge-warning') }}">
                                                                {{ ucfirst($payment->status) }}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="flex justify-center mt-4">
                                        <a href="{{ route('parents.payments.index') }}" class="btn btn-outline btn-sm">
                                            View All Payments
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Subscription Details -->
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="flex items-center card-title">
                                    <x-icon name="o-clipboard-document-list" class="w-5 h-5 mr-2" />
                                    Subscription Details
                                </h2>

                                <div class="my-2 divider"></div>

                                @if($subscription)
                                    <table class="w-full">
                                        <tbody>
                                            <tr class="border-b">
                                                <td class="py-2 text-base-content/70">Plan</td>
                                                <td class="py-2 font-medium text-right">{{ $subscription->plan->name }}</td>
                                            </tr>
                                            <tr class="border-b">
                                                <td class="py-2 text-base-content/70">Status</td>
                                                <td class="py-2 text-right">
                                                    <div class="badge {{ $subscription->status === 'active' ? 'badge-success' : ($subscription->status === 'cancelled' ? 'badge-error' : 'badge-warning') }}">
                                                        {{ ucfirst($subscription->status) }}
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr class="border-b">
                                                <td class="py-2 text-base-content/70">Price</td>
                                                <td class="py-2 font-medium text-right">${{ number_format($subscription->plan->price, 2) }}/{{ $subscription->plan->interval }}</td>
                                            </tr>
                                            <tr class="border-b">
                                                <td class="py-2 text-base-content/70">Start Date</td>
                                                <td class="py-2 font-medium text-right">{{ Carbon::parse($subscription->start_date)->format('M d, Y') }}</td>
                                            </tr>
                                            <tr class="border-b">
                                                <td class="py-2 text-base-content/70">
                                                    @if($subscription->status === 'active')
                                                        Next Billing
                                                    @elseif($subscription->status === 'cancelled')
                                                        Expires On
                                                    @else
                                                        End Date
                                                    @endif
                                                </td>
                                                <td class="py-2 font-medium text-right">
                                                    @if($subscription->end_date)
                                                 {{ Carbon::parse($subscription->end_date)->format('M d, Y') }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                            </tr>
                                            @if($subscription->status === 'cancelled' && $subscription->cancelled_at)
                                                <tr class="border-b">
                                                    <td class="py-2 text-base-content/70">Cancelled On</td>
                                                    <td class="py-2 font-medium text-right">{{ Carbon::parse($subscription->cancelled_at)->format('M d, Y') }}</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>

                                    <div class="mt-4 space-y-2">
                                        @if($subscription->status === 'active')
                                            <button class="btn btn-outline btn-block btn-sm" wire:click="$set('showChangePlanModal', true)">
                                                <x-icon name="o-arrow-path" class="w-4 h-4 mr-2" />
                                                Change Plan
                                            </button>
                                            <button class="btn btn-error btn-outline btn-block btn-sm" wire:click="$set('showCancelModal', true)">
                                                <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                                                Cancel Subscription
                                            </button>
                                        @elseif($subscription->status === 'cancelled')
                                            <button class="btn btn-primary btn-block btn-sm" wire:click="$set('showChangePlanModal', true)">
                                                <x-icon name="o-arrow-path" class="w-4 h-4 mr-2" />
                                                Reactivate Subscription
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <div class="py-6 text-center">
                                        <x-icon name="o-credit-card" class="w-12 h-12 mx-auto text-base-content/30" />
                                        <h3 class="mt-2 text-lg font-medium">No Active Subscription</h3>
                                        <p class="mt-1 mb-4 text-base-content/70">You don't have an active subscription.</p>
                                        <button class="btn btn-primary btn-block" wire:click="$set('showChangePlanModal', true)">
                                            <x-icon name="o-credit-card" class="w-4 h-4 mr-2" />
                                            Subscribe Now
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="shadow-xl card bg-base-100">
                            <div class="card-body">
                                <h2 class="flex items-center card-title">
                                    <x-icon name="o-bolt" class="w-5 h-5 mr-2" />
                                    Quick Actions
                                </h2>

                                <div class="my-2 divider"></div>

                                <div class="space-y-2">
                                    <a href="{{ route('parents.invoices.index') }}" class="btn btn-outline btn-block btn-sm">
                                        <x-icon name="o-document-text" class="w-4 h-4 mr-2" />
                                        View All Invoices
                                    </a>
                                    <a href="{{ route('parents.payments.index') }}" class="btn btn-outline btn-block btn-sm">
                                        <x-icon name="o-banknotes" class="w-4 h-4 mr-2" />
                                        Payment History
                                    </a>
                                    <button class="btn btn-outline btn-block btn-sm" wire:click="$set('activeTab', 'payment_methods')">
                                        <x-icon name="o-credit-card" class="w-4 h-4 mr-2" />
                                        Manage Payment Methods
                                    </button>
                                    <a href="{{ route('parents.support.index') }}" class="btn btn-outline btn-block btn-sm">
                                        <x-icon name="o-lifebuoy" class="w-4 h-4 mr-2" />
                                        Billing Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Payment Methods Tab -->
            @if($activeTab === 'payment_methods')
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Payment Methods</h2>
                            <button class="btn btn-primary btn-sm" wire:click="$set('showAddPaymentMethodModal', true)">
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Add Payment Method
                            </button>
                        </div>

                        <div class="my-2 divider"></div>

                        @if($paymentMethods->isEmpty())
                            <div class="py-8 text-center">
                                <x-icon name="o-credit-card" class="w-16 h-16 mx-auto text-base-content/30" />
                                <h3 class="mt-4 text-lg font-medium">No payment methods</h3>
                                <p class="mt-1 mb-4 text-base-content/70">
                                    Add a payment method to manage your subscription.
                                </p>
                                <button class="btn btn-primary" wire:click="$set('showAddPaymentMethodModal', true)">
                                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                    Add Payment Method
                                </button>
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach($paymentMethods as $method)
                                    <div class="card {{ $method->is_default ? 'bg-base-200 border border-primary' : 'bg-base-200' }}">
                                        <div class="p-4 card-body">
                                            <div class="flex items-start justify-between">
                                                <div class="flex items-center">
                                                    <i class="{{ $this->getCardIcon($method->card_type) }} text-2xl mr-3"></i>
                                                    <div>
                                                        <div class="font-medium">•••• {{ $method->last_four }}</div>
                                                        <div class="text-xs text-base-content/70">
                                                            {{ $method->card_holder }} • Expires {{ $method->expiry_month }}/{{ $method->expiry_year }}
                                                        </div>
                                                        @if($method->is_default)
                                                            <div class="mt-1 badge badge-primary badge-sm">Default</div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="dropdown dropdown-end">
                                                    <label tabindex="0" class="btn btn-ghost btn-sm btn-square">
                                                        <x-icon name="o-ellipsis-vertical" class="w-5 h-5" />
                                                    </label>
                                                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                        @if(!$method->is_default)
                                                            <li>
                                                                <button wire:click="setDefaultPaymentMethod({{ $method->id }})">
                                                                    <x-icon name="o-star" class="w-4 h-4" />
                                                                    Set as Default
                                                                </button>
                                                            </li>
                                                        @endif
                                                        <li>
                                                            <button wire:click="deletePaymentMethod({{ $method->id }})">
                                                                <x-icon name="o-trash" class="w-4 h-4" />
                                                                Remove
                                                            </button>
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
            @endif

            <!-- Invoices Tab -->
            @if($activeTab === 'invoices')
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-6 card-title">Invoices & Payments</h2>

                        <!-- Filters -->
                        <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-3">
                            <div>
                                <label class="label">
                                    <span class="label-text">Filter by Status</span>
                                </label>
                                <select class="w-full select select-bordered" wire:model.live="statusFilter">
                                    <option value="all">All Statuses</option>
                                    <option value="paid">Paid</option>
                                    <option value="unpaid">Unpaid</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                            </div>

                            <div>
                                <label class="label">
                                    <span class="label-text">Date Range</span>
                                </label>
                                <select class="w-full select select-bordered" wire:model.live="dateRangeFilter">
                                    <option value="all">All Time</option>
                                    <option value="this_month">This Month</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="last_3_months">Last 3 Months</option>
                                    <option value="last_6_months">Last 6 Months</option>
                                    <option value="this_year">This Year</option>
                                </select>
                            </div>

                            <div>
                                <label class="label">
                                    <span class="label-text">Search</span>
                                </label>
                                <div class="relative">
                                    <input type="text" placeholder="Search invoice #..."
                                        class="w-full pl-10 input input-bordered"
                                        wire:model.live.debounce.300ms="searchQuery">
                                    <div class="absolute transform -translate-y-1/2 left-3 top-1/2">
                                        <x-icon name="o-magnifying-glass" class="w-4 h-4 text-base-content/60" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Invoices Table -->
                        @if($this->invoices->isEmpty())
                            <div class="py-8 text-center">
                                <x-icon name="o-document-text" class="w-16 h-16 mx-auto text-base-content/30" />
                                <h3 class="mt-4 text-lg font-medium">No invoices found</h3>
                                <p class="mt-1 text-base-content/70">
                                    @if($searchQuery || $statusFilter !== 'all' || $dateRangeFilter !== 'all')
                                        Try adjusting your filters or search criteria
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
                                            <th>Invoice #</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->invoices as $invoice)
                                            <tr>
                                                <td>{{ $invoice->invoice_number }}</td>
                                                <td>{{ Carbon::parse($invoice->created_at)->format('M d, Y') }}</td>
                                                <td>${{ number_format($invoice->amount, 2) }}</td>
                                                <td>
                                                    <div class="badge {{ $invoice->status === 'paid' ? 'badge-success' : ($invoice->status === 'overdue' ? 'badge-error' : 'badge-warning') }}">
                                                        {{ ucfirst($invoice->status) }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="flex space-x-2">
                                                        <a href="{{ route('parents.invoices.show', $invoice->id) }}" class="btn btn-ghost btn-xs">
                                                            <x-icon name="o-eye" class="w-4 h-4" />
                                                            View
                                                        </a>
                                                        <button wire:click="downloadInvoice({{ $invoice->id }})" class="btn btn-ghost btn-xs">
                                                            <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                                            Download
                                                        </button>
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
            @endif

            <!-- Plans Tab -->
            @if($activeTab === 'plans')
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-6 card-title">Subscription Plans</h2>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                            @foreach($availablePlans as $plan)
                                <div class="card bg-base-200 hover:shadow-lg transition-shadow {{ $subscription && $subscription->plan_id === $plan['id'] ? 'border-2 border-primary' : '' }}">
                                    <div class="p-6 card-body">
                                        <h3 class="text-xl font-bold">{{ $plan['name'] }}</h3>
                                        <p class="mb-4 text-sm text-base-content/70">{{ $plan['description'] }}</p>

                                        <div class="mb-4 text-3xl font-bold">
                                            ${{ number_format($plan['price'], 2) }}
                                            <span class="text-base font-normal text-base-content/70">/{{ $plan['interval'] }}</span>
                                        </div>

                                        <ul class="mb-6 space-y-2">
                                            @foreach($plan['features'] as $feature)
                                                <li class="flex items-start">
                                                    <x-icon name="o-check" class="flex-shrink-0 w-5 h-5 mr-2 text-success" />
                                                    <span>{{ $feature }}</span>
                                                </li>
                                            @endforeach
                                        </ul>

                                        <div class="justify-center card-actions">
                                            @if($subscription && $subscription->plan_id === $plan['id'])
                                                <div class="badge badge-primary">Current Plan</div>
                                            @else
                                                <button
                                                    class="btn btn-primary btn-block"
                                                    wire:click="$set('selectedPlan', {{ $plan['id'] }}); $set('showChangePlanModal', true);"
                                                >
                                                    {{ $subscription ? 'Switch to This Plan' : 'Select Plan' }}
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Add Payment Method Modal -->
    @if($showAddPaymentMethodModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="w-full max-w-md modal-box">
                <h3 class="mb-4 text-lg font-bold">Add Payment Method</h3>
                <button wire:click="$set('showAddPaymentMethodModal', false)" class="absolute btn btn-sm btn-circle btn-ghost right-2 top-2">✕</button>

                <form wire:submit.prevent="addPaymentMethod">
                    <div class="mb-4 form-control">
                        <label class="label">
                            <span class="label-text">Card Holder Name</span>
                        </label>
                        <input
                            type="text"
                            class="w-full input input-bordered"
                            placeholder="John Doe"
                            wire:model="newPaymentMethod.card_holder"
                        />
                        @error('newPaymentMethod.card_holder') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4 form-control">
                        <label class="label">
                            <span class="label-text">Card Number</span>
                        </label>
                        <input
                            type="text"
                            class="w-full input input-bordered"
                            placeholder="1234 5678 9012 3456"
                            wire:model="newPaymentMethod.card_number"
                            maxlength="16"
                        />
                        @error('newPaymentMethod.card_number') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="label">
                                <span class="label-text">Expiry Month</span>
                            </label>
                            <select class="w-full select select-bordered" wire:model="newPaymentMethod.expiry_month">
                                <option value="">Month</option>
                                @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}">{{ sprintf('%02d', $i) }}</option>
                                @endfor
                            </select>
                            @error('newPaymentMethod.expiry_month') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="label">
                                <span class="label-text">Expiry Year</span>
                            </label>
                            <select class="w-full select select-bordered" wire:model="newPaymentMethod.expiry_year">
                                <option value="">Year</option>
                                @for($i = date('Y'); $i <= date('Y') + 20; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                            @error('newPaymentMethod.expiry_year') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mb-4 form-control">
                        <label class="label">
                            <span class="label-text">CVV</span>
                        </label>
                        <input
                            type="text"
                            class="w-full input input-bordered"
                            placeholder="123"
                            wire:model="newPaymentMethod.cvv"
                            maxlength="3"
                        />
                        @error('newPaymentMethod.cvv') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-6 form-control">
                        <label class="justify-start cursor-pointer label">
                            <input type="checkbox" class="mr-2 checkbox checkbox-primary" wire:model="newPaymentMethod.is_default" />
                            <span class="label-text">Make this my default payment method</span>
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="mr-2 btn btn-ghost"
                            wire:click="$set('showAddPaymentMethodModal', false)"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            wire:loading.attr="disabled"
                            wire:target="addPaymentMethod"
                        >
                            <span wire:loading.remove wire:target="addPaymentMethod">Add Payment Method</span>
                            <span wire:loading wire:target="addPaymentMethod">Processing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Change Plan Modal -->
    @if($showChangePlanModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="w-full max-w-2xl modal-box">
                <h3 class="mb-4 text-lg font-bold">{{ $subscription ? 'Change Subscription Plan' : 'Choose a Plan' }}</h3>
                <button wire:click="$set('showChangePlanModal', false)" class="absolute btn btn-sm btn-circle btn-ghost right-2 top-2">✕</button>

                <form wire:submit.prevent="changePlan">
                    <div class="mb-6 space-y-4">
                        @foreach($availablePlans as $plan)
                            <div class="flex items-center">
                                <input
                                    type="radio"
                                    name="selectedPlan"
                                    id="plan-{{ $plan['id'] }}"
                                    value="{{ $plan['id'] }}"
                                    class="radio radio-primary"
                                    wire:model="selectedPlan"
                                />
                                <label for="plan-{{ $plan['id'] }}" class="flex flex-1 p-4 ml-3 rounded-lg cursor-pointer hover:bg-base-200">
                                    <div class="flex-1">
                                        <div class="font-bold">{{ $plan['name'] }}</div>
                                        <div class="text-sm text-base-content/70">{{ $plan['description'] }}</div>
                                    </div>
                                    <div class="font-bold">${{ number_format($plan['price'], 2) }}/{{ $plan['interval'] }}</div>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    @error('selectedPlan') <span class="block mb-4 text-sm text-error">{{ $message }}</span> @enderror

                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="mr-2 btn btn-ghost"
                            wire:click="$set('showChangePlanModal', false)"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            wire:loading.attr="disabled"
                            wire:target="changePlan"
                        >
                            <span wire:loading.remove wire:target="changePlan">
                                {{ $subscription ? 'Change Plan' : 'Subscribe' }}
                            </span>
                            <span wire:loading wire:target="changePlan">Processing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Cancel Subscription Modal -->
    @if($showCancelModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="w-full max-w-md modal-box">
                <h3 class="mb-4 text-lg font-bold">Cancel Subscription</h3>
                <button wire:click="$set('showCancelModal', false)" class="absolute btn btn-sm btn-circle btn-ghost right-2 top-2">✕</button>

                <p class="mb-4">We're sorry to see you go. Please let us know why you're cancelling your subscription to help us improve our service.</p>

                <form wire:submit.prevent="cancelSubscription">
                    <div class="mb-6 form-control">
                        <label class="label">
                            <span class="label-text">Reason for Cancellation</span>
                        </label>
                        <textarea
                            class="h-32 textarea textarea-bordered"
                            placeholder="Please share your reason for cancelling..."
                            wire:model="cancellationReason"
                        ></textarea>
                        @error('cancellationReason') <span class="mt-1 text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-6 alert alert-warning">
                        <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
                        <span>Your subscription will remain active until the end of the current billing period.</span>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="mr-2 btn btn-ghost"
                            wire:click="$set('showCancelModal', false)"
                        >
                            Never Mind
                        </button>
                        <button
                            type="submit"
                            class="btn btn-error"
                            wire:loading.attr="disabled"
                            wire:target="cancelSubscription"
                        >
                            <span wire:loading.remove wire:target="cancelSubscription">Confirm Cancellation</span>
                            <span wire:loading wire:target="cancelSubscription">Processing...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
