<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Course;
use App\Models\Assessment;
use App\Models\LearningSession;
use App\Models\Invoice;
use App\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public $user;
    public $recentUsers = [];
    public $recentSessions = [];
    public $recentTickets = [];
    public $recentInvoices = [];

    // Dashboard statistics
    public $stats = [
        'total_users' => 0,
        'total_teachers' => 0,
        'total_parents' => 0,
        'total_clients' => 0,
        'total_sessions' => 0,
        'total_courses' => 0,
        'total_revenue' => 0,
        'pending_verifications' => 0
    ];

    // Filter states for user table
    public $roleFilter = '';
    public $searchQuery = '';
    public $statusFilter = '';

    // Chart data
    public $userSignupsData = [];
    public $revenueData = [];
    public $sessionData = [];

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadStatsFromDatabase();
        $this->loadRecentData();
        $this->prepareChartData();
    }

    public function loadStatsFromDatabase()
    {
        // Get actual statistics from database
        $this->stats = [
            'total_users' => User::count(),
            'total_teachers' => User::where('role', User::ROLE_TEACHER)->count(),
            'total_parents' => User::where('role', User::ROLE_PARENT)->count(),
            'total_clients' => User::where('role', User::ROLE_CLIENT)->count(),
            'total_sessions' => LearningSession::count(),
            'total_courses' => Course::count(),
            'total_revenue' => Invoice::where('status', 'paid')->sum('amount') ?? 0,
            'pending_verifications' => User::where('role', User::ROLE_TEACHER)
                ->whereHas('teacherProfile', function($query) {
                    $query->where('status', 'submitted');
                })->count()
        ];
    }

    public function loadRecentData()
    {
        // Recent users
        $this->recentUsers = User::latest()->take(5)->get();

        // Recent sessions
        $this->recentSessions = LearningSession::with(['teacher', 'children', 'subject'])
            ->orderBy('start_time', 'desc')
            ->take(5)
            ->get();

        // Recent support tickets
        $this->recentTickets = SupportTicket::with('user')
            ->latest()
            ->take(5)
            ->get();

        // Recent invoices
        $this->recentInvoices = Invoice::with('user')
            ->latest()
            ->take(5)
            ->get();
    }

    public function prepareChartData()
    {
        // User signups per month (last 6 months)
        $userSignups = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $count = User::whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();

            $userSignups[] = [
                'month' => $month->format('M'),
                'count' => $count
            ];
        }

        $this->userSignupsData = $userSignups;

        // Revenue per month (last 6 months)
        $revenueData = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $revenue = Invoice::where('status', 'paid')
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->sum('amount');

            $revenueData[] = [
                'month' => $month->format('M'),
                'amount' => $revenue ?? 0
            ];
        }

        $this->revenueData = $revenueData;

        // Sessions by type
        $this->sessionData = [
            ['type' => 'Completed', 'count' => LearningSession::where('status', 'completed')->count()],
            ['type' => 'Scheduled', 'count' => LearningSession::where('status', 'scheduled')->count()],
            ['type' => 'Cancelled', 'count' => LearningSession::where('status', 'cancelled')->count()],
        ];
    }

    // Filter handlers
    public function updatedRoleFilter()
    {
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

    public function getUsersProperty()
    {
        $query = User::query();

        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        if ($this->searchQuery) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('email', 'like', '%' . $this->searchQuery . '%');
            });
        }

        if ($this->statusFilter === 'verified') {
            $query->whereHas('teacherProfile', function($q) {
                $q->where('status', 'verified');
            })->orWhereHas('clientProfile', function($q) {
                $q->where('status', 'approved');
            })->orWhereHas('parentProfile', function($q) {
                $q->where('has_completed_profile', true);
            });
        } elseif ($this->statusFilter === 'pending') {
            $query->whereHas('teacherProfile', function($q) {
                $q->where('status', 'submitted');
            })->orWhereHas('clientProfile', function($q) {
                $q->where('status', 'pending');
            })->orWhereHas('parentProfile', function($q) {
                $q->where('has_completed_profile', false);
            });
        }

        return $query->latest()->paginate(10);
    }

    public function getRecentActivitiesProperty()
    {
        $activities = [];

        // Get recent user registrations
        $recentUsers = User::latest()->limit(2)->get();
        foreach ($recentUsers as $user) {
            $activities[] = [
                'id' => $user->id,
                'type' => 'user_registered',
                'title' => 'New User Registration',
                'description' => $user->name . ' (' . $user->role . ')',
                'time' => Carbon::parse($user->created_at)->format('M d, Y H:i'),
                'icon' => 'o-user-plus',
                'color' => 'bg-blue-100 text-blue-600'
            ];
        }

        // Get recent sessions
        $recentSessions = LearningSession::latest('updated_at')->limit(2)->get();
        foreach ($recentSessions as $session) {
            $activities[] = [
                'id' => $session->id,
                'type' => 'session_scheduled',
                'title' => 'Learning Session',
                'description' => 'Session ' . $session->status,
                'time' => Carbon::parse($session->updated_at)->format('M d, Y H:i'),
                'icon' => 'o-calendar',
                'color' => 'bg-purple-100 text-purple-600'
            ];
        }

        // Get recent tickets
        $recentTickets = SupportTicket::latest()->limit(2)->get();
        foreach ($recentTickets as $ticket) {
            $activities[] = [
                'id' => $ticket->id,
                'type' => 'ticket_created',
                'title' => 'Support Ticket',
                'description' => $ticket->title,
                'time' => Carbon::parse($ticket->created_at)->format('M d, Y H:i'),
                'icon' => 'o-ticket',
                'color' => 'bg-yellow-100 text-yellow-600'
            ];
        }

        // Get recent payments
        $recentPayments = Invoice::where('status', 'paid')->latest()->limit(2)->get();
        foreach ($recentPayments as $payment) {
            $activities[] = [
                'id' => $payment->id,
                'type' => 'payment_received',
                'title' => 'Payment Received',
                'description' => '$' . number_format($payment->amount, 2),
                'time' => Carbon::parse($payment->updated_at)->format('M d, Y H:i'),
                'icon' => 'o-currency-dollar',
                'color' => 'bg-green-100 text-green-600'
            ];
        }

        // Sort by time
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return array_slice($activities, 0, 6);
    }

    public function formatDate($date)
{
    return Carbon::parse($date)->format('M d, Y');
}

public function formatTime($date)
{
    return Carbon::parse($date)->format('h:i A');
}

    public function getStatusClass($status)
    {
        return match($status) {
            'verified', 'approved', 'completed', 'paid' => 'badge-success',
            'submitted', 'pending', 'scheduled' => 'badge-warning',
            'rejected', 'cancelled' => 'badge-error',
            default => 'badge-neutral'
        };
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Welcome Banner with Admin Info -->
        <div class="mb-8 overflow-hidden text-white shadow-lg rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600">
            <div class="flex flex-col items-center md:flex-row">
                <div class="flex-1 p-6 md:p-8">
                    <h1 class="mb-2 text-3xl font-bold">Admin Dashboard</h1>
                    <p class="mb-1 text-white/90">
                        Welcome back, {{ $user->name }}! | {{ Carbon::now()->format('l, F d, Y') }}
                    </p>

                    <!-- Admin Info Section -->
                    <div class="p-3 mt-3 text-white rounded-lg bg-white/10">
                        <h3 class="mb-2 font-semibold">System Overview</h3>
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                            <div>
                                <p class="flex items-center">
                                    <x-icon name="o-users" class="w-4 h-4 mr-2" />
                                    <span class="text-white/80">Total Users:</span>
                                    <span class="ml-2">{{ $stats['total_users'] }}</span>
                                </p>
                                <p class="flex items-center">
                                    <x-icon name="o-academic-cap" class="w-4 h-4 mr-2" />
                                    <span class="text-white/80">Active Courses:</span>
                                    <span class="ml-2">{{ $stats['total_courses'] }}</span>
                                </p>
                                <p class="flex items-center">
                                    <x-icon name="o-currency-dollar" class="w-4 h-4 mr-2" />
                                    <span class="text-white/80">Total Revenue:</span>
                                    <span class="ml-2">${{ number_format($stats['total_revenue'], 2) }}</span>
                                </p>
                            </div>
                            <div>
                                <p class="flex items-center">
                                    <x-icon name="o-user-circle" class="w-4 h-4 mr-2" />
                                    <span class="text-white/80">Teachers:</span>
                                    <span class="ml-2">{{ $stats['total_teachers'] }}</span>
                                </p>
                                <p class="flex items-center">
                                    <x-icon name="o-calendar" class="w-4 h-4 mr-2" />
                                    <span class="text-white/80">Learning Sessions:</span>
                                    <span class="ml-2">{{ $stats['total_sessions'] }}</span>
                                </p>
                                <p class="flex items-center">
                                    <x-icon name="o-exclamation-circle" class="w-4 h-4 mr-2" />
                                    <span class="text-white/80">Pending Verifications:</span>
                                    <span class="ml-2">{{ $stats['pending_verifications'] }}</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-4">
                        <a href="{{ route('admin.users.index') }}" class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30">
                            <x-icon name="o-users" class="w-4 h-4 mr-1" />
                            Manage Users
                        </a>
                        <a href="{{ route('admin.settings.general') }}" class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30">
                            <x-icon name="o-cog" class="w-4 h-4 mr-1" />
                            System Settings
                        </a>
                        <a href="{{ route('admin.support.tickets.index') }}" class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30">
                            <x-icon name="o-ticket" class="w-4 h-4 mr-1" />
                            Support Tickets
                        </a>
                    </div>
                </div>
                <div class="hidden p-6 md:block">
                    <img src="{{ asset('images/admin-dashboard-illustration.svg') }}" alt="Admin Dashboard" class="h-32" onerror="this.src='https://via.placeholder.com/150'">
                </div>
            </div>
        </div>

        <!-- Quick Stats Overview -->
        <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-2 lg:grid-cols-4">
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <x-icon name="o-users" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Total Users</div>
                    <div class="stat-value text-primary">{{ $stats['total_users'] }}</div>
                    <div class="stat-desc">{{ $stats['total_parents'] }} Parents, {{ $stats['total_teachers'] }} Teachers</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <x-icon name="o-academic-cap" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Active Courses</div>
                    <div class="stat-value text-secondary">{{ $stats['total_courses'] }}</div>
                    <div class="stat-desc">Educational content</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-accent">
                        <x-icon name="o-calendar" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Learning Sessions</div>
                    <div class="stat-value text-accent">{{ $stats['total_sessions'] }}</div>
                    <div class="stat-desc">Teaching interactions</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-success">
                        <x-icon name="o-currency-dollar" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Total Revenue</div>
                    <div class="stat-value text-success">${{ number_format($stats['total_revenue'], 0) }}</div>
                    <div class="stat-desc">Platform earnings</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column (Main Content) -->
            <div class="space-y-6 lg:col-span-2">
                <!-- User Management -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">User Management</h2>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-primary">View All Users</a>
                        </div>

                        <!-- Filters -->
                        <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-3">
                            <select class="w-full select select-bordered" wire:model.live="roleFilter">
                                <option value="">All Roles</option>
                                <option value="teacher">Teachers</option>
                                <option value="parent">Parents</option>
                                <option value="client">Clients</option>
                                <option value="admin">Admins</option>
                            </select>

                            <select class="w-full select select-bordered" wire:model.live="statusFilter">
                                <option value="">All Statuses</option>
                                <option value="verified">Verified/Completed</option>
                                <option value="pending">Pending Verification</option>
                            </select>

                            <div class="relative">
                                <input
                                    type="text"
                                    placeholder="Search users..."
                                    class="w-full input input-bordered"
                                    wire:model.live.debounce.300ms="searchQuery"
                                />
                                <x-icon name="o-magnifying-glass" class="absolute w-5 h-5 transform -translate-y-1/2 right-3 top-1/2 text-base-content/50" />
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="table w-full table-zebra">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Joined Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->users as $userItem)
                                    <tr>
                                        <td>
                                            <div class="flex items-center space-x-3">
                                                <div class="avatar placeholder">
                                                    <div class="w-8 rounded-full bg-neutral-focus text-neutral-content">
                                                        <span>{{ substr($userItem->name, 0, 1) }}</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-bold">{{ $userItem->name }}</div>
                                                    <div class="text-sm opacity-50">{{ $userItem->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{
                                                $userItem->role === 'admin' ? 'badge-primary' :
                                                ($userItem->role === 'teacher' ? 'badge-secondary' :
                                                ($userItem->role === 'parent' ? 'badge-accent' : 'badge-info'))
                                            }}">
                                                {{ ucfirst($userItem->role) }}
                                            </span>
                                        </td>
                                        <td>{{ $this->formatDate($userItem->created_at) }}</td>
                                        <td>
                                            @if($userItem->role === 'teacher' && $userItem->teacherProfile)
                                            <span class="badge {{ $this->getStatusClass($userItem->teacherProfile->status) }}">
                                                    {{ ucfirst($userItem->teacherProfile->status) }}
                                                </span>
                                            @elseif($userItem->role === 'parent' && $userItem->parentProfile)
                                                <span class="badge {{ $userItem->parentProfile->has_completed_profile ? 'badge-success' : 'badge-warning' }}">
                                                    {{ $userItem->parentProfile->has_completed_profile ? 'Complete' : 'Incomplete' }}
                                                </span>
                                            @elseif($userItem->role === 'client' && $userItem->clientProfile)
                                                <span class="badge {{ $this->getStatusClass($userItem->clientProfile->status) }}">
                                                    {{ ucfirst($userItem->clientProfile->status) }}
                                                </span>
                                            @elseif($userItem->role === 'admin')
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-ghost">Unknown</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="dropdown dropdown-end">
                                                <div tabindex="0" role="button" class="btn btn-ghost btn-xs">
                                                    <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                                </div>
                                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                    <li><a href="{{ route('admin.users.show', $userItem->id) }}">View Details</a></li>
                                                    <li><a href="{{ route('admin.users.edit', $userItem->id) }}">Edit User</a></li>
                                                    @if($userItem->role === 'teacher' && $userItem->teacherProfile && $userItem->teacherProfile->status === 'submitted')
                                                        <li><a class="text-success">Verify Teacher</a></li>
                                                    @endif
                                                    <li><a class="text-error">Deactivate</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $this->users->links() }}
                        </div>
                    </div>
                </div>

                <!-- Analytics Overview -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Analytics Overview</h2>
                            <a href="{{ route('admin.analytics.dashboard') }}" class="btn btn-sm btn-outline">View Details</a>
                        </div>

                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <!-- User Signups Chart -->
                            <div class="shadow-sm card bg-base-200">
                                <div class="card-body">
                                    <h3 class="text-base card-title">New User Signups</h3>
                                    <div class="flex items-end h-40 gap-2">
                                        @foreach($userSignupsData as $item)
                                        <div class="flex flex-col items-center">
                                            <div class="w-10 transition-all rounded-t bg-primary" style="height: {{ min(120, $item['count'] * 10) }}px;"></div>
                                            <div class="mt-1 text-xs">{{ $item['month'] }}</div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Revenue Chart -->
                            <div class="shadow-sm card bg-base-200">
                                <div class="card-body">
                                    <h3 class="text-base card-title">Monthly Revenue</h3>
                                    <div class="flex items-end h-40 gap-2">
                                        @foreach($revenueData as $item)
                                        <div class="flex flex-col items-center">
                                            <div class="w-10 transition-all rounded-t bg-success" style="height: {{ min(120, $item['amount'] / 100) }}px;"></div>
                                            <div class="mt-1 text-xs">{{ $item['month'] }}</div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Session Distribution -->
                        <div class="mt-6">
                            <h3 class="mb-3 text-lg font-medium">Session Distribution</h3>
                            <div class="p-4 rounded-lg shadow-sm bg-base-200">
                                <div class="flex gap-3">
                                    @foreach($sessionData as $item)
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $item['type'] }}</div>
                                        <div class="text-2xl font-bold">{{ $item['count'] }}</div>
                                        <div class="h-2 mt-2 rounded-full bg-base-300">
                                            <div class="h-full rounded-full {{
                                                $item['type'] === 'Completed' ? 'bg-success' :
                                                ($item['type'] === 'Scheduled' ? 'bg-info' : 'bg-error')
                                            }}" style="width: {{ min(100, $item['count'] / 5) }}%"></div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Recent Activity</h2>
                            <a href="{{ route('admin.monitoring.activity') }}" class="btn btn-sm btn-outline">View All</a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="table w-full">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Details</th>
                                        <th>Date & Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->recentActivities as $activity)
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $activity['color'] }}">
                                                    <x-icon name="{{ $activity['icon'] }}" class="w-4 h-4" />
                                                </div>
                                                <span>{{ $activity['title'] }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $activity['description'] }}</td>
                                        <td>{{ $activity['time'] }}</td>
                                        <td>
                                            <div class="dropdown dropdown-end">
                                                <div tabindex="0" role="button" class="btn btn-ghost btn-xs">
                                                    <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                                </div>
                                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                    <li><a>View Details</a></li>
                                                    <li><a>Take Action</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column (Sidebar) -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Quick Actions</h2>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                                <x-icon name="o-user-plus" class="w-4 h-4 mr-2" />
                                Add User
                            </a>
                            <a href="{{ route('admin.users.verifications.index') }}" class="btn btn-accent">
                                <x-icon name="o-check-badge" class="w-4 h-4 mr-2" />
                                Verifications
                            </a>
                            <a href="{{ route('admin.communications.notifications.create') }}" class="btn btn-info">
                                <x-icon name="o-bell" class="w-4 h-4 mr-2" />
                                Send Notice
                            </a>
                            <a href="{{ route('admin.finance.reports.index') }}" class="btn btn-warning">
                                <x-icon name="o-document-chart-bar" class="w-4 h-4 mr-2" />
                                Reports
                            </a>
                            <a href="{{ route('admin.settings.backup') }}" class="btn btn-success">
                                <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-2" />
                                Backup
                            </a>
                            <a href="{{ route('admin.settings.logs') }}" class="btn btn-neutral">
                                <x-icon name="o-bug-ant" class="w-4 h-4 mr-2" />
                                Logs
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Pending Verifications -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Pending Verifications</h2>
                        @if($stats['pending_verifications'] > 0)
                            <div class="my-2">
                                <div class="alert alert-warning">
                                    <x-icon name="o-exclamation-triangle" class="w-5 h-5" />
                                    <span>{{ $stats['pending_verifications'] }} teachers awaiting verification</span>
                                </div>
                            </div>
                            <div class="my-1 divider"></div>
                            <ul class="space-y-2">
                                @foreach(User::where('role', 'teacher')->whereHas('teacherProfile', function($q) { $q->where('status', 'submitted'); })->take(3)->get() as $teacher)
                                <li class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">{{ $teacher->name }}</div>
                                        <div class="text-xs opacity-70">Submitted: {{ Carbon::parse($teacher->teacherProfile->updated_at)->diffForHumans() }}</div>
                                    </div>
                                    <a href="{{ route('admin.users.verifications.show', $teacher->id) }}" class="btn btn-xs btn-primary">Review</a>
                                </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="alert alert-success">
                                <x-icon name="o-check-circle" class="w-5 h-5" />
                                <span>No pending verifications</span>
                            </div>
                        @endif
                        <div class="justify-end mt-2 card-actions">
                            <a href="{{ route('admin.users.verifications.index') }}" class="btn btn-sm btn-ghost">View All</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Support Tickets -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Recent Support Tickets</h2>
                        @if(count($recentTickets) > 0)
                            <div class="mt-2 space-y-3">
                                @foreach($recentTickets as $ticket)
                                <div class="flex items-start justify-between p-3 rounded-lg bg-base-200">
                                    <div>
                                        <div class="font-medium">{{ $ticket->title }}</div>
                                        <div class="text-xs opacity-70">From: {{ $ticket->user->name }}</div>
                                        <div class="text-xs opacity-70">{{ Carbon::parse($ticket->created_at)->diffForHumans() }}</div>
                                    </div>
                                    <div class="badge {{ $getStatusClass($ticket->status) }}">
                                        {{ str_replace('_', ' ', ucfirst($ticket->status)) }}
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="alert">
                                <span>No recent support tickets</span>
                            </div>
                        @endif
                        <div class="justify-end mt-2 card-actions">
                            <a href="{{ route('admin.support.tickets.index') }}" class="btn btn-sm btn-ghost">View All Tickets</a>
                        </div>
                    </div>
                </div>

                <!-- System Health -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">System Health</h2>
                        <div class="mt-2 space-y-2">
                            <div class="flex items-center justify-between">
                                <span>Server Load</span>
                                <div class="radial-progress text-success" style="--value:25; --size:2rem;">25%</div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Database</span>
                                <div class="radial-progress text-success" style="--value:18; --size:2rem;">18%</div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Storage</span>
                                <div class="radial-progress text-warning" style="--value:68; --size:2rem;">68%</div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Memory</span>
                                <div class="radial-progress text-info" style="--value:42; --size:2rem;">42%</div>
                            </div>
                        </div>
                        <div class="my-2 divider"></div>
                        <div class="alert alert-success">
                            <x-icon name="o-check-circle" class="w-5 h-5" />
                            <span>All systems operational</span>
                        </div>
                        <div class="justify-end mt-2 card-actions">
                            <a href="{{ route('admin.monitoring.health') }}" class="btn btn-sm btn-ghost">View Details</a>
                        </div>
                    </div>
                </div>

                <!-- Recent Invoices -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">Recent Payments</h2>
                        @if(count($recentInvoices) > 0)
                            <div class="mt-2 space-y-2">
                                @foreach($recentInvoices as $invoice)
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">Invoice #{{ $invoice->invoice_number ?? $invoice->id }}</div>
                                        <div class="text-xs opacity-70">{{ $invoice->user->name }}</div>
                                    </div>
                                    <div>
                                        <div class="font-bold">${{ number_format($invoice->amount, 2) }}</div>
                                        <div class="badge badge-sm {{ $getStatusClass($invoice->status) }}">{{ ucfirst($invoice->status) }}</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="alert">
                                <span>No recent invoices</span>
                            </div>
                        @endif
                        <div class="justify-end mt-2 card-actions">
                            <a href="{{ route('admin.finance.invoices.index') }}" class="btn btn-sm btn-ghost">View All Invoices</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
