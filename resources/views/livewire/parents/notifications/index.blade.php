<?php

namespace App\Livewire\Parents\Notifications;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $notificationTypeFilter = 'all';
    public $unreadOnly = false;
    public $searchQuery = '';
    public $dateRangeFilter = 'all';
    public $customStartDate = null;
    public $customEndDate = null;
    public $showFilters = false;
    public $showCustomDateRange = false;

    // Bulk actions
    public $selectedNotifications = [];
    public $selectAll = false;
    public $bulkActionType = '';
    public $showBulkActionConfirmation = false;

    // Notification drawer for previewing
    public $activeNotification = null;
    public $showNotificationDetail = false;

    // New notification animation
    public $newNotifications = [];
    public $hasNewNotifications = false;

    // Stats
    public $stats = [
        'total_unread' => 0,
        'total_notifications' => 0,
        'notifications_today' => 0,
        'academic_notifications' => 0,
        'billing_notifications' => 0,
        'system_notifications' => 0,
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadStats();
        $this->checkForNewNotifications();
    }

    public function loadStats()
    {
        $allNotifications = $this->user->notifications;

        $this->stats = [
            'total_unread' => $allNotifications->where('read_at', null)->count(),
            'total_notifications' => $allNotifications->count(),
            'notifications_today' => $allNotifications->where('created_at', '>=', Carbon::today())->count(),
            'academic_notifications' => $allNotifications->where('type', 'academic')->count(),
            'billing_notifications' => $allNotifications->where('type', 'billing')->count(),
            'system_notifications' => $allNotifications->where('type', 'system')->count(),
        ];
    }

    public function checkForNewNotifications()
    {
        // Get latest unseen notifications
        $this->newNotifications = $this->user->notifications()
            ->where('seen_at', null)
            ->latest()
            ->take(5)
            ->get();

        $this->hasNewNotifications = count($this->newNotifications) > 0;

        // Mark notifications as seen
        if ($this->hasNewNotifications) {
            $notificationIds = $this->newNotifications->pluck('id')->toArray();
            $this->user->notifications()
                ->whereIn('id', $notificationIds)
                ->update(['seen_at' => now()]);
        }
    }

    public function updatedDateRangeFilter()
    {
        $this->resetPage();
        $this->showCustomDateRange = ($this->dateRangeFilter === 'custom');
    }

    public function updatedNotificationTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedUnreadOnly()
    {
        $this->resetPage();
    }

    public function updatedSearchQuery()
    {
        $this->resetPage();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedNotifications = $this->getNotificationsProperty()
                ->pluck('id')
                ->map(fn($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedNotifications = [];
        }
    }

    public function toggleFilters()
    {
        $this->showFilters = !$this->showFilters;
    }

    public function resetFilters()
    {
        $this->notificationTypeFilter = 'all';
        $this->unreadOnly = false;
        $this->searchQuery = '';
        $this->dateRangeFilter = 'all';
        $this->customStartDate = null;
        $this->customEndDate = null;
        $this->resetPage();
    }

    public function markAsRead($notificationId)
    {
        $notification = $this->user->notifications()->findOrFail($notificationId);
        $notification->update(['read_at' => now()]);
        $this->loadStats();
        
        $this->dispatch('notification-read');
    }

    public function markAsUnread($notificationId)
    {
        $notification = $this->user->notifications()->findOrFail($notificationId);
        $notification->update(['read_at' => null]);
        $this->loadStats();
        
        $this->dispatch('notification-updated');
    }

    public function openNotificationDetail($notificationId)
    {
        $this->activeNotification = $this->user->notifications()->findOrFail($notificationId);
        
        // If unread, mark as read when opened
        if (!$this->activeNotification->read_at) {
            $this->markAsRead($notificationId);
        }
        
        $this->showNotificationDetail = true;
    }

    public function closeNotificationDetail()
    {
        $this->showNotificationDetail = false;
        $this->activeNotification = null;
    }

    public function openBulkAction($actionType)
    {
        if (empty($this->selectedNotifications)) {
            return;
        }
        
        $this->bulkActionType = $actionType;
        $this->showBulkActionConfirmation = true;
    }

    public function closeBulkActionConfirmation()
    {
        $this->showBulkActionConfirmation = false;
        $this->bulkActionType = '';
    }

    public function confirmBulkAction()
    {
        if ($this->bulkActionType === 'mark-read') {
            $this->user->notifications()
                ->whereIn('id', $this->selectedNotifications)
                ->update(['read_at' => now()]);
                
            $this->dispatch('notifications-updated', [
                'message' => count($this->selectedNotifications) . ' notifications marked as read',
                'type' => 'success'
            ]);
        } elseif ($this->bulkActionType === 'mark-unread') {
            $this->user->notifications()
                ->whereIn('id', $this->selectedNotifications)
                ->update(['read_at' => null]);
                
            $this->dispatch('notifications-updated', [
                'message' => count($this->selectedNotifications) . ' notifications marked as unread',
                'type' => 'success'
            ]);
        } elseif ($this->bulkActionType === 'delete') {
            $this->user->notifications()
                ->whereIn('id', $this->selectedNotifications)
                ->delete();
                
            $this->dispatch('notifications-updated', [
                'message' => count($this->selectedNotifications) . ' notifications deleted',
                'type' => 'success'
            ]);
        }
        
        $this->selectedNotifications = [];
        $this->selectAll = false;
        $this->loadStats();
        $this->closeBulkActionConfirmation();
    }

    public function deleteNotification($notificationId)
    {
        $this->user->notifications()->findOrFail($notificationId)->delete();
        $this->loadStats();
        $this->activeNotification = null;
        $this->showNotificationDetail = false;
        
        $this->dispatch('notification-deleted');
    }

    public function markAllAsRead()
    {
        $this->user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
            
        $this->loadStats();
        $this->dispatch('notifications-updated', [
            'message' => 'All notifications marked as read',
            'type' => 'success'
        ]);
    }

    public function getNotificationsProperty()
    {
        $query = $this->user->notifications()
            ->when($this->unreadOnly, function($query) {
                return $query->whereNull('read_at');
            })
            ->when($this->notificationTypeFilter !== 'all', function($query) {
                return $query->where('type', $this->notificationTypeFilter);
            })
            ->when($this->searchQuery, function($query) {
                return $query->where(function($q) {
                    $q->where('title', 'like', '%' . $this->searchQuery . '%')
                      ->orWhere('message', 'like', '%' . $this->searchQuery . '%');
                });
            });

        // Apply date range filter
        if($this->dateRangeFilter !== 'all') {
            switch($this->dateRangeFilter) {
                case 'today':
                    $query->whereDate('created_at', Carbon::today());
                    break;
                case 'yesterday':
                    $query->whereDate('created_at', Carbon::yesterday());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                    break;
                case 'last_week':
                    $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', Carbon::now()->month)
                          ->whereYear('created_at', Carbon::now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month)
                          ->whereYear('created_at', Carbon::now()->subMonth()->year);
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

        return $query->orderBy('created_at', 'desc')->paginate(10);
    }

    public function getNotificationIcon($type)
    {
        return match($type) {
            'academic' => 'fa-solid fa-graduation-cap',
            'billing' => 'fa-solid fa-credit-card',
            'system' => 'fa-solid fa-gear',
            'message' => 'fa-solid fa-envelope',
            'schedule' => 'fa-solid fa-calendar',
            'homework' => 'fa-solid fa-book',
            'attendance' => 'fa-solid fa-user-check',
            default => 'fa-solid fa-bell'
        };
    }

    public function getNotificationColor($type)
    {
        return match($type) {
            'academic' => 'bg-blue-100 text-blue-600',
            'billing' => 'bg-green-100 text-green-600',
            'system' => 'bg-gray-100 text-gray-600',
            'message' => 'bg-yellow-100 text-yellow-600',
            'schedule' => 'bg-indigo-100 text-indigo-600',
            'homework' => 'bg-purple-100 text-purple-600',
            'attendance' => 'bg-teal-100 text-teal-600',
            default => 'bg-slate-100 text-slate-600'
        };
    }

    public function getStatusColor($type)
    {
        return match($type) {
            'academic' => 'bg-blue-500',
            'billing' => 'bg-green-500',
            'system' => 'bg-gray-500',
            'message' => 'bg-yellow-500',
            'schedule' => 'bg-indigo-500',
            'homework' => 'bg-purple-500',
            'attendance' => 'bg-teal-500',
            default => 'bg-slate-500'
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
    x-on:notifications-updated.window="showToastNotification($event.detail.message, $event.detail.type)"
    x-on:notification-read.window="showToastNotification('Notification marked as read')"
    x-on:notification-deleted.window="showToastNotification('Notification deleted', 'info')"
    x-on:notification-updated.window="showToastNotification('Notification updated')"
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
        <div class="mb-8 overflow-hidden text-white shadow-lg bg-gradient-to-r from-primary to-accent rounded-xl">
            <div class="p-6 md:p-8">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 class="text-3xl font-bold">Notifications</h1>
                        <p class="mt-2 text-white/80">Stay updated with all your important alerts</p>
                    </div>
                    
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('parents.dashboard') }}" class="text-white btn btn-ghost btn-sm bg-white/10">
                            <i class="mr-1 fa-solid fa-house w-4 h-4"></i>
                            Dashboard
                        </a>
                        @if($stats['total_unread'] > 0)
                        <button wire:click="markAllAsRead" class="text-white btn btn-ghost btn-sm bg-white/10">
                            <i class="mr-1 fa-solid fa-check-double w-4 h-4"></i>
                            Mark All as Read
                        </button>
                        @endif
                    </div>
                </div>
                
                <!-- Stats Overview -->
                <div class="grid grid-cols-2 gap-4 mt-6 md:grid-cols-4">
                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Unread</div>
                        <div class="text-2xl font-bold">{{ $stats['total_unread'] }}</div>
                    </div>
                    
                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Today</div>
                        <div class="text-2xl font-bold">{{ $stats['notifications_today'] }}</div>
                    </div>
                    
                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Academic</div>
                        <div class="text-2xl font-bold">{{ $stats['academic_notifications'] }}</div>
                    </div>
                    
                    <div class="p-3 rounded-lg bg-white/20">
                        <div class="text-sm text-white/80">Billing</div>
                        <div class="text-2xl font-bold">{{ $stats['billing_notifications'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- New Notifications Animation -->
        @if($hasNewNotifications)
        <div class="mb-6">
            <div class="p-4 bg-blue-50 text-blue-700 rounded-lg shadow-md animate-pulse">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fa-solid fa-bell text-2xl mr-3"></i>
                        <div>
                            <h3 class="font-bold">New Notifications</h3>
                            <p>You have {{ count($newNotifications) }} new notification(s)</p>
                        </div>
                    </div>
                    <a href="#latest" class="btn btn-sm btn-primary">View Latest</a>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Main content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Notification filters -->
            <div class="lg:col-span-1">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg card-title">Filters</h2>
                            <button wire:click="toggleFilters" class="btn btn-ghost btn-sm btn-circle">
                                <i class="fa-solid {{ $showFilters ? 'fa-angle-up' : 'fa-angle-down' }}"></i>
                            </button>
                        </div>
                        
                        <div class="{{ $showFilters ? 'block' : 'hidden' }} space-y-4">
                            <!-- Search -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Search</span>
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        wire:model.live.debounce.300ms="searchQuery" 
                                        placeholder="Search notifications..." 
                                        class="w-full pl-10 input input-bordered"
                                    >
                                    <div class="absolute transform -translate-y-1/2 left-3 top-1/2">
                                        <i class="fa-solid fa-magnifying-glass text-base-content/60"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Type Filter -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Notification Type</span>
                                </label>
                                <select wire:model.live="notificationTypeFilter" class="w-full select select-bordered">
                                    <option value="all">All Types</option>
                                    <option value="academic">Academic</option>
                                    <option value="billing">Billing</option>
                                    <option value="system">System</option>
                                    <option value="message">Messages</option>
                                    <option value="schedule">Schedule</option>
                                    <option value="homework">Homework</option>
                                    <option value="attendance">Attendance</option>
                                </select>
                            </div>
                            
                            <!-- Date Range Filter -->
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Date Range</span>
                                </label>
                                <select wire:model.live="dateRangeFilter" class="w-full select select-bordered">
                                    <option value="all">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="this_week">This Week</option>
                                    <option value="last_week">Last Week</option>
                                    <option value="this_month">This Month</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                            </div>
                            
                            <!-- Custom Date Range -->
                            @if($showCustomDateRange)
                            <div class="space-y-4">
                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text">Start Date</span>
                                    </label>
                                    <input type="date" wire:model.live="customStartDate" class="w-full input input-bordered">
                                </div>
                                
                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text">End Date</span>
                                    </label>
                                    <input type="date" wire:model.live="customEndDate" class="w-full input input-bordered">
                                </div>
                            </div>
                            @endif
                            
                            <!-- Unread Only Toggle -->
                            <div class="form-control">
                                <label class="cursor-pointer label">
                                    <span class="label-text">Unread Only</span>
                                    <input type="checkbox" wire:model.live="unreadOnly" class="toggle toggle-primary" />
                                </label>
                            </div>
                            
                            <div class="card-actions">
                                <button wire:click="resetFilters" class="btn btn-outline btn-block">
                                    <i class="mr-2 fa-solid fa-filter-circle-xmark"></i>
                                    Reset Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Actions (Mobile) -->
                <div class="mt-6 shadow-xl card bg-base-100 lg:hidden">
                    <div class="card-body">
                        <h2 class="card-title">Bulk Actions</h2>
                        <div class="mt-2 space-y-2">
                            <button wire:click="openBulkAction('mark-read')" class="btn btn-outline btn-block btn-sm" @if(empty($selectedNotifications)) disabled @endif>
                                <i class="mr-2 fa-solid fa-check"></i>
                                Mark Selected as Read
                            </button>
                            <button wire:click="openBulkAction('mark-unread')" class="btn btn-outline btn-block btn-sm" @if(empty($selectedNotifications)) disabled @endif>
                                <i class="mr-2 fa-solid fa-envelope"></i>
                                Mark Selected as Unread
                            </button>
                            <button wire:click="openBulkAction('delete')" class="btn btn-outline btn-error btn-block btn-sm" @if(empty($selectedNotifications)) disabled @endif>
                                <i class="mr-2 fa-solid fa-trash"></i>
                                Delete Selected
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Notification Categories Info -->
                <div class="hidden mt-6 shadow-xl card bg-base-100 lg:block">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Categories</h2>
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-100">
                                    <i class="fa-solid fa-graduation-cap text-blue-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold">Academic</h3>
                                    <p class="text-sm text-base-content/70">Updates about your child's academic progress</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-green-100">
                                    <i class="fa-solid fa-credit-card text-green-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold">Billing</h3>
                                    <p class="text-sm text-base-content/70">Invoice, payment, and billing information</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-yellow-100">
                                    <i class="fa-solid fa-envelope text-yellow-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold">Messages</h3>
                                    <p class="text-sm text-base-content/70">Communication from teachers and staff</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-indigo-100">
                                    <i class="fa-solid fa-calendar text-indigo-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold">Schedule</h3>
                                    <p class="text-sm text-base-content/70">Class schedules and session reminders</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100">
                                    <i class="fa-solid fa-gear text-gray-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-semibold">System</h3>
                                    <p class="text-sm text-base-content/70">Platform updates and maintenance notices</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notification List -->
            <div class="lg:col-span-2">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg card-title">Your Notifications</h2>
                                <div class="badge badge-primary">{{ $stats['total_notifications'] }}</div>
                            </div>
                            
                            <!-- Bulk Actions (Desktop) -->
                            <div class="hidden lg:flex lg:gap-2">
                                <button wire:click="openBulkAction('mark-read')" class="btn btn-outline btn-sm" @if(empty($selectedNotifications)) disabled @endif>
                                    <i class="mr-1 fa-solid fa-check"></i>
                                    Mark Read
                                </button>
                                <button wire:click="openBulkAction('delete')" class="btn btn-outline btn-error btn-sm" @if(empty($selectedNotifications)) disabled @endif>
                                    <i class="mr-1 fa-solid fa-trash"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                        
                        <!-- Notification Count Info -->
                        <div class="flex justify-between mb-4 text-sm text-base-content/70">
                            <div>
                                Showing 
                                <span class="font-medium">{{ $this->notifications->count() }}</span> 
                                of 
                                <span class="font-medium">{{ $this->notifications->total() }}</span> 
                                notifications
                            </div>
                            <div>
                                <span class="font-medium">{{ count($selectedNotifications) }}</span> selected
                            </div>
                        </div>
                        
                        <!-- Notification Table -->
                        @if($this->notifications->isEmpty())
                        <div class="py-12 text-center">
                            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-base-200">
                                <i class="text-3xl fa-solid fa-bell-slash text-base-content/30"></i>
                            </div>
                            <h3 class="text-lg font-medium">No notifications found</h3>
                            <p class="mt-1 text-base-content/70">
                                @if($searchQuery || $notificationTypeFilter !== 'all' || $dateRangeFilter !== 'all' || $unreadOnly)
                                    Try adjusting your filter criteria
                                @else
                                    You don't have any notifications yet
                                @endif
                            </p>
                            @if($searchQuery || $notificationTypeFilter !== 'all' || $dateRangeFilter !== 'all' || $unreadOnly)
                            <button wire:click="resetFilters" class="mt-4 btn btn-outline btn-sm">
                                <i class="mr-2 fa-solid fa-filter-circle-xmark"></i>
                                Reset Filters
                            </button>
                            @endif
                        </div>
                        @else
                        <div class="overflow-x-auto">
                            <table class="table w-full table-zebra">
                                <thead>
                                    <tr>
                                        <th class="w-10">
                                            <label>
                                                <input 
                                                    type="checkbox" 
                                                    class="checkbox checkbox-sm" 
                                                    wire:model.live="selectAll"
                                                />
                                            </label>
                                        </th>
                                        <th>Notification</th>
                                        <th class="hidden lg:table-cell">Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->notifications as $notification)
                                    <tr 
                                        class="{{ $notification->read_at ? '' : 'bg-blue-50 hover:bg-blue-100' }}"
                                        wire:key="notification-{{ $notification->id }}"
                                        id="{{ $loop->first && $hasNewNotifications ? 'latest' : '' }}"
                                    >
                                        <td>
                                            <label>
                                                <input 
                                                    type="checkbox" 
                                                    class="checkbox checkbox-sm"
                                                    value="{{ $notification->id }}"
                                                    wire:model.live="selectedNotifications" 
                                                />
                                            </label>
                                        </td>
                                        <td>
                                            <div class="flex items-start gap-3" wire:click="openNotificationDetail({{ $notification->id }})" style="cursor:pointer">
                                                <div class="mt-1 flex-shrink-0">
                                                    <div class="w-10 h-10 flex items-center justify-center rounded-full {{ $this->getNotificationColor($notification->type) }}">
                                                        <i class="{{ $this->getNotificationIcon($notification->type) }}"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-semibold flex items-center gap-2">
                                                        {{ $notification->title ?? 'Notification' }}
                                                        @if(!$notification->read_at)
                                                            <span class="badge badge-sm badge-primary">New</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-sm line-clamp-2">{{ $notification->message }}</div>
                                                    <div class="mt-1 text-xs text-base-content/60 lg:hidden">
                                                        {{ $this->formatTimeAgo($notification->created_at) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="hidden lg:table-cell">
                                            <div class="text-sm">{{ $notification->created_at->format('M d, Y') }}</div>
                                            <div class="text-xs text-base-content/60">{{ $notification->created_at->format('h:i A') }}</div>
                                        </td>
                                        <td>
                                            <div class="flex space-x-1">
                                                @if($notification->read_at)
                                                    <button 
                                                        wire:click="markAsUnread({{ $notification->id }})"
                                                        class="btn btn-ghost btn-xs"
                                                        title="Mark as unread"
                                                    >
                                                        <i class="fa-solid fa-envelope"></i>
                                                    </button>
                                                @else
                                                    <button 
                                                        wire:click="markAsRead({{ $notification->id }})"
                                                        class="btn btn-ghost btn-xs"
                                                        title="Mark as read"
                                                    >
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                @endif
                                                <button 
                                                    wire:click="deleteNotification({{ $notification->id }})"
                                                    class="btn btn-ghost btn-xs text-error"
                                                    title="Delete"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $this->notifications->links() }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notification Detail Modal -->
    @if($showNotificationDetail && $activeNotification)
    <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
        <div class="w-full max-w-2xl modal-box">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 flex items-center justify-center rounded-full {{ $this->getNotificationColor($activeNotification->type) }}">
                        <i class="{{ $this->getNotificationIcon($activeNotification->type) }}"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">{{ $activeNotification->title }}</h3>
                        <div class="text-sm text-base-content/70">{{ $activeNotification->created_at->format('M d, Y h:i A') }}</div>
                    </div>
                </div>
                <button class="btn btn-sm btn-circle btn-ghost" wire:click="closeNotificationDetail">✕</button>
            </div>
            
            <div class="my-4 divider"></div>
            
            <!-- Notification Content -->
            <div class="py-4">
                <p class="whitespace-pre-line">{{ $activeNotification->message }}</p>
                
                @if($activeNotification->action_url)
                <div class="mt-6">
                    <a 
                        href="{{ $activeNotification->action_url }}" 
                        class="btn btn-primary"
                    >
                        {{ $activeNotification->action_text ?? 'View Details' }}
                    </a>
                </div>
                @endif
                
                @if($activeNotification->metadata)
                <div class="mt-6 p-4 bg-base-200 rounded-lg">
                    <h4 class="font-semibold mb-2">Additional Information</h4>
                    <div class="space-y-2">
                        @foreach($activeNotification->metadata as $key => $value)
                        <div class="flex justify-between">
                            <span class="text-base-content/70">{{ Str::title(str_replace('_', ' ', $key)) }}</span>
                            <span class="font-medium">{{ $value }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            
            <div class="my-4 divider"></div>
            
            <div class="modal-action">
                <button 
                    wire:click="deleteNotification({{ $activeNotification->id }})" 
                    class="btn btn-outline btn-error"
                >
                    <i class="fa-solid fa-trash mr-2"></i>
                    Delete
                </button>
                
                <button 
                    wire:click="closeNotificationDetail" 
                    class="btn btn-primary"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
    @endif
    
    <!-- Bulk Action Confirmation Modal -->
    @if($showBulkActionConfirmation)
    <div class="fixed inset-0 z-40 flex items-center justify-center p-4 bg-black bg-opacity-50">
        <div class="w-full max-w-md modal-box">
            <h3 class="text-lg font-bold">
                @if($bulkActionType === 'mark-read')
                    Mark Notifications as Read
                @elseif($bulkActionType === 'mark-unread')
                    Mark Notifications as Unread
                @elseif($bulkActionType === 'delete')
                    Delete Notifications
                @endif
            </h3>
            
            <p class="py-4">
                @if($bulkActionType === 'mark-read')
                    Are you sure you want to mark {{ count($selectedNotifications) }} notification(s) as read?
                @elseif($bulkActionType === 'mark-unread')
                    Are you sure you want to mark {{ count($selectedNotifications) }} notification(s) as unread?
                @elseif($bulkActionType === 'delete')
                    Are you sure you want to delete {{ count($selectedNotifications) }} notification(s)? This action cannot be undone.
                @endif
            </p>
            
            <div class="modal-action">
                <button wire:click="closeBulkActionConfirmation" class="btn btn-ghost">Cancel</button>
                <button 
                    wire:click="confirmBulkAction" 
                    class="btn {{ $bulkActionType === 'delete' ? 'btn-error' : 'btn-primary' }}"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>
    @endif
</div>