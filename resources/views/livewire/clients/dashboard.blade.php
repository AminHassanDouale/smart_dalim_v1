<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $clientProfile;
    public $stats = [
        'pending_requests' => 0,
        'active_projects' => 0,
        'completed_projects' => 0,
        'upcoming_sessions' => 0
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->clientProfile = $this->user->clientProfile;

        // In a real application, you would fetch these stats from your database
        // For now, we'll use dummy data
        $this->stats = [
            'pending_requests' => rand(1, 5),
            'active_projects' => rand(2, 8),
            'completed_projects' => rand(5, 20),
            'upcoming_sessions' => rand(1, 3)
        ];
    }

    // Method to format date for display
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // For the activity timeline (dummy data for demo)
    public function getRecentActivityProperty()
    {
        return [
            [
                'type' => 'project_created',
                'title' => 'New project created',
                'description' => 'Website redesign project has been created',
                'date' => Carbon::now()->subDays(2)->toDateTimeString(),
                'icon' => 'o-document-plus',
                'color' => 'bg-blue-100 text-blue-600'
            ],
            [
                'type' => 'session_scheduled',
                'title' => 'Consultation session scheduled',
                'description' => 'Upcoming consultation with John Doe on '.Carbon::now()->addDays(3)->format('M d'),
                'date' => Carbon::now()->subDays(3)->toDateTimeString(),
                'icon' => 'o-calendar',
                'color' => 'bg-purple-100 text-purple-600'
            ],
            [
                'type' => 'payment_received',
                'title' => 'Payment received',
                'description' => 'Payment for Mobile App Development received',
                'date' => Carbon::now()->subDays(5)->toDateTimeString(),
                'icon' => 'o-credit-card',
                'color' => 'bg-green-100 text-green-600'
            ],
            [
                'type' => 'project_completed',
                'title' => 'Project completed',
                'description' => 'E-commerce Integration project marked as completed',
                'date' => Carbon::now()->subDays(10)->toDateTimeString(),
                'icon' => 'o-check-circle',
                'color' => 'bg-green-100 text-green-600'
            ],
        ];
    }

    // For the upcoming sessions (dummy data for demo)
    public function getUpcomingSessionsProperty()
    {
        return [
            [
                'id' => 1,
                'title' => 'Project Kickoff Meeting',
                'teacher' => 'Sarah Johnson',
                'date' => Carbon::now()->addDays(2)->format('Y-m-d'),
                'time' => '10:00 AM - 11:30 AM',
                'status' => 'confirmed'
            ],
            [
                'id' => 2,
                'title' => 'Technical Consultation',
                'teacher' => 'Michael Chen',
                'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'time' => '2:00 PM - 3:00 PM',
                'status' => 'pending'
            ],
            [
                'id' => 3,
                'title' => 'Design Review',
                'teacher' => 'Emily Rodriguez',
                'date' => Carbon::now()->addDays(7)->format('Y-m-d'),
                'time' => '11:00 AM - 12:00 PM',
                'status' => 'confirmed'
            ],
        ];
    }

    // For the active projects (dummy data for demo)
    public function getActiveProjectsProperty()
    {
        return [
            [
                'id' => 1,
                'name' => 'Website Redesign',
                'progress' => 65,
                'status' => 'in_progress',
                'start_date' => Carbon::now()->subDays(15)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
                'team' => ['John D.', 'Sarah K.', 'Michael R.']
            ],
            [
                'id' => 2,
                'name' => 'Mobile App Development',
                'progress' => 35,
                'status' => 'in_progress',
                'start_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(40)->format('Y-m-d'),
                'team' => ['Alex W.', 'Emma L.']
            ],
            [
                'id' => 3,
                'name' => 'Marketing Campaign',
                'progress' => 90,
                'status' => 'review',
                'start_date' => Carbon::now()->subDays(25)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'team' => ['David S.', 'Lisa T.', 'Brian K.', 'Michelle P.']
            ],
        ];
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Welcome Banner -->
        <div class="mb-8 overflow-hidden text-white shadow-lg bg-gradient-to-r from-primary to-primary-focus rounded-xl">
            <div class="flex flex-col items-center md:flex-row">
                <div class="flex-1 p-6 md:p-8">
                    <h1 class="mb-2 text-3xl font-bold">Welcome back, {{ $user->name }}!</h1>
                    <p class="mb-4 text-white/80">
                        {{ $clientProfile && $clientProfile->company_name ? 'Managing ' . $clientProfile->company_name : 'Dashboard' }}
                    </p>

                    <div class="flex flex-wrap gap-2 mt-4">
                        <a href="{{ route('clients.profile') }}" class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30">
                            <x-icon name="o-user" class="w-4 h-4 mr-1" />
                            View Profile
                        </a>
                        <a href="{{ route('clients.session-requests') }}" class="text-white border-none btn btn-sm bg-white/20 hover:bg-white/30">
                            <x-icon name="o-calendar" class="w-4 h-4 mr-1" />
                            Schedule Session
                        </a>
                    </div>
                </div>
                <div class="hidden p-6 md:block">
                    <img src="{{ asset('images/dashboard-illustration.svg') }}" alt="Dashboard" class="h-32">
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-2 lg:grid-cols-4">
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <x-icon name="o-clipboard-document-check" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Pending Requests</div>
                    <div class="stat-value text-primary">{{ $stats['pending_requests'] }}</div>
                    <div class="stat-desc">Awaiting response</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <x-icon name="o-rocket-launch" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Active Projects</div>
                    <div class="stat-value text-secondary">{{ $stats['active_projects'] }}</div>
                    <div class="stat-desc">In progress</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-success">
                        <x-icon name="o-check-badge" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Completed Projects</div>
                    <div class="stat-value text-success">{{ $stats['completed_projects'] }}</div>
                    <div class="stat-desc">Successfully delivered</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-info">
                        <x-icon name="o-clock" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Upcoming Sessions</div>
                    <div class="stat-value text-info">{{ $stats['upcoming_sessions'] }}</div>
                    <div class="stat-desc">Scheduled in calendar</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column (Active Projects) -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Active Projects -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Active Projects</h2>
                            <a href="#" class="btn btn-sm btn-outline">View All</a>
                        </div>
                        <div class="space-y-4">
                            @foreach($this->activeProjects as $project)
                                <div class="shadow-sm card bg-base-200">
                                    <div class="p-4 card-body">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h3 class="text-lg font-semibold">{{ $project['name'] }}</h3>
                                                <div class="mt-1 text-sm opacity-70">
                                                    <span>{{ $this->formatDate($project['start_date']) }} - {{ $this->formatDate($project['end_date']) }}</span>
                                                </div>
                                            </div>
                                            <div class="badge {{ $project['status'] === 'review' ? 'badge-warning' : 'badge-info' }}">
                                                {{ $project['status'] === 'review' ? 'In Review' : 'In Progress' }}
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-sm">Progress</span>
                                                <span class="text-sm font-medium">{{ $project['progress'] }}%</span>
                                            </div>
                                            <div class="h-2 overflow-hidden rounded-full bg-base-300">
                                                <div class="h-full {{ $project['progress'] > 75 ? 'bg-success' : ($project['progress'] > 40 ? 'bg-info' : 'bg-primary') }}" style="width: {{ $project['progress'] }}%"></div>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between mt-4">
                                            <div class="flex -space-x-2">
                                                @foreach(array_slice($project['team'], 0, 3) as $index => $member)
                                                    <div class="avatar placeholder tooltip" data-tip="{{ $member }}">
                                                        <div class="w-8 h-8 rounded-full bg-neutral-focus text-neutral-content">
                                                            <span>{{ substr($member, 0, 1) }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                                @if(count($project['team']) > 3)
                                                    <div class="avatar placeholder">
                                                        <div class="w-8 h-8 rounded-full bg-base-300">
                                                            <span>+{{ count($project['team']) - 3 }}</span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <a href="#" class="btn btn-sm btn-ghost">Details</a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Upcoming Sessions -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Upcoming Sessions</h2>
                            <a href="#" class="btn btn-sm btn-outline">View Calendar</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Session</th>
                                        <th>Date & Time</th>
                                        <th>With</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->upcomingSessions as $session)
                                        <tr>
                                            <td class="font-medium">{{ $session['title'] }}</td>
                                            <td>
                                                <div class="flex flex-col">
                                                    <span>{{ $this->formatDate($session['date']) }}</span>
                                                    <span class="text-xs opacity-70">{{ $session['time'] }}</span>
                                                </div>
                                            </td>
                                            <td>{{ $session['teacher'] }}</td>
                                            <td>
                                                <div class="badge {{ $session['status'] === 'confirmed' ? 'badge-success' : 'badge-warning' }}">
                                                    {{ ucfirst($session['status']) }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="dropdown dropdown-end">
                                                    <div tabindex="0" role="button" class="btn btn-ghost btn-xs">
                                                        <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                                    </div>
                                                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                        <li><a>Join Meeting</a></li>
                                                        <li><a>Reschedule</a></li>
                                                        <li><a class="text-error">Cancel</a></li>
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

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Profile Card -->
                @if($clientProfile)
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex flex-col items-center text-center">
                            @if($clientProfile->logo)
                                <div class="mb-4 avatar">
                                    <div class="w-24 h-24 rounded-xl">
                                        <img src="{{ Storage::url($clientProfile->logo) }}" alt="{{ $clientProfile->company_name }}" />
                                    </div>
                                </div>
                            @else
                                <div class="mb-4 avatar placeholder">
                                    <div class="w-24 h-24 bg-neutral-focus text-neutral-content rounded-xl">
                                        <span class="text-3xl">{{ substr($clientProfile->company_name ?? $user->name, 0, 1) }}</span>
                                    </div>
                                </div>
                            @endif

                            <h3 class="text-xl font-bold">{{ $clientProfile->company_name }}</h3>
                            <p class="text-sm opacity-70">{{ $clientProfile->industry }}</p>

                            <div class="mt-2 badge badge-outline">{{ $clientProfile->company_size }} employees</div>

                            @if($clientProfile->status)
                                <div class="badge {{
                                    $clientProfile->status === 'approved' ? 'badge-success' :
                                    ($clientProfile->status === 'pending' ? 'badge-warning' : 'badge-error')
                                }} mt-2">
                                    {{ ucfirst($clientProfile->status) }}
                                </div>
                            @endif

                            <div class="divider"></div>

                            <div class="w-full space-y-2">
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-user" class="w-4 h-4 opacity-70" />
                                    <span>{{ $user->name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-briefcase" class="w-4 h-4 opacity-70" />
                                    <span>{{ $clientProfile->position }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-envelope" class="w-4 h-4 opacity-70" />
                                    <span>{{ $user->email }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-phone" class="w-4 h-4 opacity-70" />
                                    <span>{{ $clientProfile->phone }}</span>
                                </div>
                            </div>

                            <div class="justify-center w-full mt-4 card-actions">
                                <a href="{{ route('clients.profile') }}" class="btn btn-primary btn-sm">View Profile</a>
                                <a href="{{ route('clients.profile.edit', $user) }}" class="btn btn-outline btn-sm">Edit</a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Recent Activity -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Recent Activity</h2>
                        <div class="space-y-4">
                            @foreach($this->recentActivity as $activity)
                                <div class="flex gap-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $activity['color'] }}">
                                            <x-icon name="{{ $activity['icon'] }}" class="w-5 h-5" />
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $activity['title'] }}</div>
                                        <div class="text-sm opacity-70">{{ $activity['description'] }}</div>
                                        <div class="mt-1 text-xs opacity-50">{{ $this->formatDate($activity['date']) }}</div>
                                    </div>
                                </div>
                                @if(!$loop->last)
                                    <div class="h-4 ml-5 border-l-2 border-dashed border-base-300"></div>
                                @endif
                            @endforeach
                        </div>
                        <div class="justify-center mt-4 card-actions">
                            <a href="#" class="btn btn-ghost btn-sm">View All Activity</a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Quick Actions</h2>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="#" class="btn btn-outline">
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                New Request
                            </a>
                            <a href="#" class="btn btn-outline">
                                <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 mr-2" />
                                Support
                            </a>
                            <a href="#" class="btn btn-outline">
                                <x-icon name="o-document-chart-bar" class="w-4 h-4 mr-2" />
                                Reports
                            </a>
                            <a href="#" class="btn btn-outline">
                                <x-icon name="o-credit-card" class="w-4 h-4 mr-2" />
                                Payments
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
