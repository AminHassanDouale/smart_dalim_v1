<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\ClientProfile;
use App\Models\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public $user;
    public $clientProfile;
    public $stats = [
        'pending_requests' => 0,
        'active_projects' => 0,
        'completed_projects' => 0,
        'upcoming_sessions' => 0
    ];

    // Profile completion percentage
    public $profileCompletionPercentage = 0;

    // Recent activity
    public $recentActivity = [];

    // Recently accessed courses
    public $recentCourses = [];

    public function mount()
    {
        $this->user = Auth::user();
        $this->clientProfile = $this->user->clientProfile;

        // Calculate profile completion percentage
        $this->calculateProfileCompletion();

        // Get mock stats
        $this->stats = [
            'pending_requests' => rand(1, 3),
            'active_projects' => rand(2, 5),
            'completed_projects' => rand(5, 15),
            'upcoming_sessions' => rand(1, 3)
        ];

        // Mock recent activity
        $this->recentActivity = $this->getMockRecentActivity();

        // Mock recent courses
        $this->recentCourses = $this->getMockRecentCourses();
    }

    protected function calculateProfileCompletion()
    {
        if (!$this->clientProfile) {
            $this->profileCompletionPercentage = 0;
            return;
        }

        $totalFields = 10; // Total number of important profile fields
        $completedFields = 0;

        // Check important fields and increment completedFields for each completed one
        if (!empty($this->clientProfile->company_name)) $completedFields++;
        if (!empty($this->clientProfile->position)) $completedFields++;
        if (!empty($this->clientProfile->phone)) $completedFields++;
        if (!empty($this->clientProfile->website)) $completedFields++;
        if (!empty($this->clientProfile->address)) $completedFields++;
        if (!empty($this->clientProfile->city)) $completedFields++;
        if (!empty($this->clientProfile->country)) $completedFields++;
        if (!empty($this->clientProfile->industry)) $completedFields++;
        if (!empty($this->clientProfile->logo)) $completedFields++;
        if (!empty($this->clientProfile->preferred_services)) $completedFields++;

        $this->profileCompletionPercentage = ($completedFields / $totalFields) * 100;
    }

    // Method to format date for display
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // Method to format datetime for display
    public function formatDateTime($date)
    {
        return Carbon::parse($date)->format('M d, Y h:i A');
    }

    // Method to get relative time for display
    public function getRelativeTime($date)
    {
        return Carbon::parse($date)->diffForHumans();
    }

    protected function getMockRecentActivity()
    {
        return [
            [
                'type' => 'course_enrolled',
                'title' => 'Enrolled in a new course',
                'description' => 'You enrolled in "Advanced Laravel Development"',
                'date' => Carbon::now()->subDays(2)->toDateTimeString(),
                'icon' => 'o-academic-cap',
                'color' => 'bg-blue-100 text-blue-600'
            ],
            [
                'type' => 'session_scheduled',
                'title' => 'Consultation session scheduled',
                'description' => 'Upcoming consultation with Sarah Johnson on '.Carbon::now()->addDays(3)->format('M d'),
                'date' => Carbon::now()->subDays(3)->toDateTimeString(),
                'icon' => 'o-calendar',
                'color' => 'bg-purple-100 text-purple-600'
            ],
            [
                'type' => 'payment_received',
                'title' => 'Payment processed',
                'description' => 'Payment for "UI/UX Design Fundamentals" processed',
                'date' => Carbon::now()->subDays(5)->toDateTimeString(),
                'icon' => 'o-credit-card',
                'color' => 'bg-green-100 text-green-600'
            ],
            [
                'type' => 'profile_updated',
                'title' => 'Profile updated',
                'description' => 'You updated your profile information',
                'date' => Carbon::now()->subDays(7)->toDateTimeString(),
                'icon' => 'o-user',
                'color' => 'bg-amber-100 text-amber-600'
            ],
            [
                'type' => 'course_completed',
                'title' => 'Course completed',
                'description' => 'You completed "React and Redux Masterclass"',
                'date' => Carbon::now()->subDays(12)->toDateTimeString(),
                'icon' => 'o-check-badge',
                'color' => 'bg-green-100 text-green-600'
            ],
        ];
    }

    protected function getMockRecentCourses()
    {
        return [
            [
                'id' => 1,
                'title' => 'Advanced Laravel Development',
                'progress' => 35,
                'thumbnail' => 'course-laravel.jpg',
                'last_accessed' => Carbon::now()->subDays(1)->toDateTimeString()
            ],
            [
                'id' => 3,
                'title' => 'UI/UX Design Fundamentals',
                'progress' => 65,
                'thumbnail' => 'course-uiux.jpg',
                'last_accessed' => Carbon::now()->subDays(3)->toDateTimeString()
            ],
            [
                'id' => 5,
                'title' => 'Flutter App Development',
                'progress' => 10,
                'thumbnail' => 'course-flutter.jpg',
                'last_accessed' => Carbon::now()->subDays(5)->toDateTimeString()
            ]
        ];
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Page Title and Actions -->
        <div class="mb-8">
            <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h1 class="text-3xl font-bold">My Profile</h1>
                    <p class="mt-1 text-base-content/70">Manage your personal and company information</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('clients.dashboard') }}" class="btn btn-outline">
                        <x-icon name="o-home" class="w-4 h-4 mr-2" />
                        Dashboard
                    </a>
                    <a href="{{ route('clients.profile.edit', $user) }}" class="btn btn-primary">
                        <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                        Edit Profile
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left Column - Profile Info -->
            <div class="lg:col-span-1">
                <!-- Profile Card -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex flex-col items-center text-center">
                            @if($clientProfile && $clientProfile->logo)
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

                            <h3 class="text-xl font-bold">{{ $clientProfile->company_name ?? 'Company name not set' }}</h3>
                            <p class="text-sm opacity-70">{{ $clientProfile->industry ?? 'Industry not set' }}</p>

                            <div class="mt-2 badge badge-outline">{{ $clientProfile->company_size ?? 'Size not specified' }} employees</div>

                            @if($clientProfile && $clientProfile->status)
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
                                    <span>{{ $clientProfile->position ?? 'Position not set' }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-envelope" class="w-4 h-4 opacity-70" />
                                    <span>{{ $user->email }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-phone" class="w-4 h-4 opacity-70" />
                                    <span>{{ $clientProfile->phone ?? 'Phone not set' }}</span>
                                </div>
                                @if($clientProfile && $clientProfile->website)
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-globe-alt" class="w-4 h-4 opacity-70" />
                                    <a href="{{ $clientProfile->website }}" target="_blank" class="text-primary hover:underline">{{ $clientProfile->website }}</a>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Completion Card -->
                <div class="mt-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Profile Completion</h3>

                        <div class="mt-2">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm">{{ round($profileCompletionPercentage) }}% Complete</span>
                                <span class="text-sm font-medium">{{ round($profileCompletionPercentage) }}/100</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-base-300">
                                <div class="h-full {{ $profileCompletionPercentage > 75 ? 'bg-success' : ($profileCompletionPercentage > 40 ? 'bg-info' : 'bg-warning') }}" style="width: {{ $profileCompletionPercentage }}%"></div>
                            </div>
                        </div>

                        @if($profileCompletionPercentage < 100)
                            <div class="p-3 mt-4 rounded-lg bg-base-200">
                                <p class="text-sm">Complete your profile to improve your experience and access all features.</p>
                                <a href="{{ route('clients.profile.edit', $user) }}" class="mt-2 btn btn-sm btn-outline">
                                    Complete Now
                                </a>
                            </div>
                        @else
                            <div class="p-3 mt-4 rounded-lg bg-success bg-opacity-10">
                                <p class="text-sm text-success">Your profile is complete! This helps us provide you with the best experience.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Stats Card -->
                <div class="mt-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Activity Overview</h3>

                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div class="p-3 text-center rounded-lg bg-base-200">
                                <div class="text-2xl font-bold">{{ $stats['active_projects'] }}</div>
                                <div class="text-xs opacity-70">Active Projects</div>
                            </div>

                            <div class="p-3 text-center rounded-lg bg-base-200">
                                <div class="text-2xl font-bold">{{ $stats['completed_projects'] }}</div>
                                <div class="text-xs opacity-70">Completed Projects</div>
                            </div>

                            <div class="p-3 text-center rounded-lg bg-base-200">
                                <div class="text-2xl font-bold">{{ $stats['upcoming_sessions'] }}</div>
                                <div class="text-xs opacity-70">Upcoming Sessions</div>
                            </div>

                            <div class="p-3 text-center rounded-lg bg-base-200">
                                <div class="text-2xl font-bold">{{ $stats['pending_requests'] }}</div>
                                <div class="text-xs opacity-70">Pending Requests</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Activity and Details -->
            <div class="lg:col-span-2">
                <!-- Company Details -->
                @if($clientProfile)
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Company Details</h3>

                        <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-2">
                            <div>
                                <h4 class="mb-2 text-sm font-medium">Location</h4>
                                <div class="p-4 rounded-lg bg-base-200">
                                    <div class="flex items-start gap-3">
                                        <x-icon name="o-map-pin" class="w-5 h-5 mt-0.5 opacity-70" />
                                        <div>
                                            <p>{{ $clientProfile->address ?? 'Address not set' }}</p>
                                            <p>{{ $clientProfile->city ?? 'City not set' }}, {{ $clientProfile->country ?? 'Country not set' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="mb-2 text-sm font-medium">Services Interested In</h4>
                                <div class="p-4 rounded-lg bg-base-200">
                                    @if($clientProfile->preferred_services && count($clientProfile->preferred_services) > 0)
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($clientProfile->preferred_services as $service)
                                                <div class="badge badge-primary">{{ ucfirst($service) }}</div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm opacity-70">No services specified</p>
                                    @endif
                                </div>
                            </div>

                            <div>
                                <h4 class="mb-2 text-sm font-medium">Contact Preference</h4>
                                <div class="p-4 rounded-lg bg-base-200">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="{{
                                            $clientProfile->preferred_contact_method === 'email' ? 'o-envelope' :
                                            ($clientProfile->preferred_contact_method === 'phone' ? 'o-phone' :
                                            ($clientProfile->preferred_contact_method === 'whatsapp' ? 'o-chat-bubble-left-right' : 'o-bell'))
                                        }}" class="w-5 h-5 opacity-70" />
                                        <span>{{ ucfirst($clientProfile->preferred_contact_method ?? 'Not specified') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h4 class="mb-2 text-sm font-medium">Additional Information</h4>
                                <div class="p-4 rounded-lg bg-base-200">
                                    @if($clientProfile->notes)
                                        <p class="text-sm">{{ $clientProfile->notes }}</p>
                                    @else
                                        <p class="text-sm opacity-70">No additional information provided</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Recent Courses -->
                <div class="mt-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <h3 class="card-title">Recently Accessed Courses</h3>
                            <a href="{{ route('clients.enrollments') }}" class="btn btn-sm btn-outline">View All</a>
                        </div>

                        @if(count($recentCourses) > 0)
                            <div class="mt-4 space-y-4">
                                @foreach($recentCourses as $course)
                                    <div class="flex items-start gap-4 p-4 rounded-lg bg-base-200">
                                        <img
                                            src="{{ asset('images/' . $course['thumbnail']) }}"
                                            alt="{{ $course['title'] }}"
                                            class="object-cover w-16 rounded-lg h-14"
                                            onerror="this.src='https://placehold.co/600x400?text=Course+Image'"
                                        />
                                        <div class="flex-1">
                                            <h4 class="font-medium">{{ $course['title'] }}</h4>
                                            <div class="flex flex-col gap-1 mt-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-full h-2 rounded-full max-w-24 bg-base-300">
                                                        <div class="h-full {{ $course['progress'] > 75 ? 'bg-success' : ($course['progress'] > 40 ? 'bg-info' : 'bg-primary') }}" style="width: {{ $course['progress'] }}%"></div>
                                                    </div>
                                                    <span class="text-xs">{{ $course['progress'] }}% complete</span>
                                                </div>
                                                <span class="text-xs opacity-70">Last accessed: {{ $this->getRelativeTime($course['last_accessed']) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-6 mt-4 text-center rounded-lg bg-base-200">
                                <p>You haven't accessed any courses yet.</p>
                                <a href="{{ route('clients.courses') }}" class="mt-2 btn btn-sm btn-primary">Browse Courses</a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="mt-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Recent Activity</h3>

                        <div class="mt-4 space-y-4">
                            @foreach($recentActivity as $activity)
                                <div class="flex gap-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $activity['color'] }}">
                                            <x-icon name="{{ $activity['icon'] }}" class="w-5 h-5" />
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium">{{ $activity['title'] }}</div>
                                        <div class="text-sm opacity-70">{{ $activity['description'] }}</div>
                                        <div class="mt-1 text-xs opacity-50">{{ $this->getRelativeTime($activity['date']) }}</div>
                                    </div>
                                </div>
                                @if(!$loop->last)
                                    <div class="h-4 ml-5 border-l-2 border-dashed border-base-300"></div>
                                @endif
                            @endforeach
                        </div>

                        <div class="justify-center mt-4 card-actions">
                            <button class="btn btn-ghost btn-sm">View All Activity</button>
                        </div>
                    </div>
                </div>

                <!-- Documents Section (if applicable) -->
                @if($clientProfile && isset($clientProfile->files) && $clientProfile->files()->count() > 0)
                <div class="mt-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="card-title">Documents</h3>

                        <div class="mt-4 overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Size</th>
                                        <th>Uploaded On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($clientProfile->files()->where('collection', 'client_documents')->get() as $file)
                                        <tr>
                                            <td>{{ $file->original_name }}</td>
                                            <td>{{ number_format($file->size / 1024, 2) }} KB</td>
                                            <td>{{ $this->formatDate($file->created_at) }}</td>
                                            <td>
                                                <a href="{{ Storage::disk('public')->url($file->path) }}"
                                                   download="{{ $file->original_name }}"
                                                   class="btn btn-xs btn-primary">
                                                    <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                                    Download
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Account Settings -->
        <div class="mt-8 shadow-xl card bg-base-100">
            <div class="card-body">
                <h3 class="card-title">Account Settings</h3>

                <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-2">
                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium">Password</h4>
                                <p class="text-sm opacity-70">Update your account password</p>
                            </div>
                            <button class="btn btn-sm">Change Password</button>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium">Email Notifications</h4>
                                <p class="text-sm opacity-70">Manage your email preferences</p>
                            </div>
                            <button class="btn btn-sm">Configure</button>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium">Privacy & Security</h4>
                                <p class="text-sm opacity-70">Review your privacy settings</p>
                            </div>
                            <button class="btn btn-sm">Manage</button>
                        </div>
                    </div>

                    <div class="p-4 rounded-lg bg-base-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium">Billing Information</h4>
                                <p class="text-sm opacity-70">View and update payment details</p>
                            </div>
                            <button class="btn btn-sm">Update</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
