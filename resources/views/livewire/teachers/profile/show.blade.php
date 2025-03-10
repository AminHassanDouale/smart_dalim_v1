<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    public $user;
    public $teacherProfile;
    public $subjects = [];

    public function mount()
    {
        $this->user = Auth::user();
        $this->teacherProfile = $this->user->teacherProfile;

        // Load subjects taught by the teacher
        if ($this->teacherProfile) {
            $this->subjects = $this->teacherProfile->subjects->map(function($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'description' => $subject->description,
                ];
            })->toArray();
        }
    }

    // Format dates for display
    public function formatDate($date)
    {
        if (!$date) return 'Not provided';
        return Carbon::parse($date)->format('M d, Y');
    }

    // Get full photo URL
    public function getPhotoUrl()
    {
        if ($this->teacherProfile && $this->teacherProfile->photo) {
            return Storage::url($this->teacherProfile->photo);
        }
        return null;
    }

    // Get verification status for display
    public function getVerificationStatus()
    {
        if (!$this->teacherProfile) return 'Not Started';

        switch ($this->teacherProfile->status) {
            case 'submitted':
                return 'Submitted for Verification';
            case 'checking':
                return 'Under Review';
            case 'verified':
                return 'Verified';
            default:
                return 'Pending';
        }
    }

    // Get status badge color
    public function getStatusBadgeClass()
    {
        if (!$this->teacherProfile) return 'badge-warning';

        switch ($this->teacherProfile->status) {
            case 'submitted':
                return 'badge-info';
            case 'checking':
                return 'badge-warning';
            case 'verified':
                return 'badge-success';
            default:
                return 'badge-warning';
        }
    }
}; ?>

<div class="p-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">My Profile</h1>
                <p class="mt-1 text-base-content/70">View and manage your teacher profile information</p>
            </div>
            <div>
                <a href="{{ route('teachers.profile.edit', $user->id) }}" class="btn btn-primary">
                    <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                    Edit Profile
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
            <!-- Left Column: Profile Card -->
            <div class="md:col-span-1">
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <!-- Profile Picture -->
                        <div class="flex flex-col items-center text-center">
                            @if($this->getPhotoUrl())
                                <div class="mb-4 avatar">
                                    <div class="w-32 h-32 rounded-full">
                                        <img src="{{ $this->getPhotoUrl() }}" alt="{{ $user->name }}" />
                                    </div>
                                </div>
                            @else
                                <div class="mb-4 avatar placeholder">
                                    <div class="w-32 h-32 rounded-full bg-neutral-focus text-neutral-content">
                                        <span class="text-5xl">{{ substr($user->name, 0, 1) }}</span>
                                    </div>
                                </div>
                            @endif

                            <h2 class="text-2xl font-bold">{{ $user->name }}</h2>

                            @if($teacherProfile)
                                <div class="mt-1 badge {{ $this->getStatusBadgeClass() }}">
                                    {{ $this->getVerificationStatus() }}
                                </div>
                            @else
                                <div class="mt-1 badge badge-warning">Profile Incomplete</div>
                            @endif

                            <div class="divider"></div>

                            <div class="w-full space-y-3">
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-user" class="w-5 h-5 text-base-content/70" />
                                    <div class="flex-1">
                                        <div class="text-sm text-base-content/70">Username</div>
                                        <div>{{ $user->username ?? 'Not set' }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-icon name="o-envelope" class="w-5 h-5 text-base-content/70" />
                                    <div class="flex-1">
                                        <div class="text-sm text-base-content/70">Email</div>
                                        <div>{{ $user->email }}</div>
                                    </div>
                                </div>

                                @if($teacherProfile)
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-phone" class="w-5 h-5 text-base-content/70" />
                                        <div class="flex-1">
                                            <div class="text-sm text-base-content/70">Phone</div>
                                            <div>{{ $teacherProfile->phone ?? 'Not provided' }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-calendar" class="w-5 h-5 text-base-content/70" />
                                        <div class="flex-1">
                                            <div class="text-sm text-base-content/70">Birth Date</div>
                                            <div>{{ $this->formatDate($teacherProfile->date_of_birth) }}</div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-map-pin" class="w-5 h-5 text-base-content/70" />
                                        <div class="flex-1">
                                            <div class="text-sm text-base-content/70">Birth Place</div>
                                            <div>{{ $teacherProfile->place_of_birth ?? 'Not provided' }}</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Card -->
                @if($teacherProfile)
                    <div class="mt-6 shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h3 class="text-xl font-bold">Contact Information</h3>
                            <div class="my-2 divider"></div>

                            <div class="space-y-3">
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-phone" class="w-5 h-5 text-base-content/70" />
                                    <div class="flex-1">
                                        <div class="text-sm text-base-content/70">Phone Number</div>
                                        <div>{{ $teacherProfile->phone ?? 'Not provided' }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-icon name="o-device-phone-mobile" class="w-5 h-5 text-base-content/70" />
                                    <div class="flex-1">
                                        <div class="text-sm text-base-content/70">WhatsApp</div>
                                        <div>{{ $teacherProfile->whatsapp ?? 'Not provided' }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <x-icon name="o-eye" class="w-5 h-5 text-base-content/70" />
                                    <div class="flex-1">
                                        <div class="text-sm text-base-content/70">Fixed Number</div>
                                        <div>{{ $teacherProfile->fix_number ?? 'Not provided' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column: Main Content -->
            <div class="space-y-6 md:col-span-2">
                <!-- Subjects Card -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold">My Teaching Subjects</h3>

                            <a href="{{ route('teachers.profile.edit', $user->id) }}?tab=subjects" class="btn btn-sm btn-outline">
                                <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                Edit
                            </a>
                        </div>

                        <div class="my-2 divider"></div>

                        @if(count($subjects) > 0)
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach($subjects as $subject)
                                    <div class="p-4 border rounded-lg shadow-sm border-base-200">
                                        <h4 class="text-lg font-semibold">{{ $subject['name'] }}</h4>
                                        @if(isset($subject['description']) && $subject['description'])
                                            <p class="mt-2 text-sm text-base-content/70">{{ $subject['description'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-6 text-center bg-base-200 rounded-xl">
                                <x-icon name="o-academic-cap" class="w-10 h-10 mx-auto mb-2 text-base-content/30" />
                                <p class="text-base-content/70">No subjects added yet.</p>
                                <a href="{{ route('teachers.profile.edit', $user->id) }}?tab=subjects" class="mt-2 btn btn-sm btn-primary">
                                    Add Subjects
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Verification Status -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="text-xl font-bold">Verification Status</h3>
                        <div class="my-2 divider"></div>

                        @if(!$teacherProfile)
                            <div class="p-6 text-center bg-warning bg-opacity-10 rounded-xl">
                                <x-icon name="o-exclamation-triangle" class="w-12 h-12 mx-auto mb-2 text-warning" />
                                <h4 class="mb-2 text-lg font-semibold">Profile Not Completed</h4>
                                <p class="mb-4 text-base-content/70">
                                    Please complete your profile setup to start the verification process.
                                </p>
                                <a href="{{ route('teachers.profile-setup') }}" class="btn btn-warning">
                                    Complete Profile
                                </a>
                            </div>
                        @elseif($teacherProfile->status === 'submitted')
                            <div class="p-6 text-center bg-info bg-opacity-10 rounded-xl">
                                <x-icon name="o-paper-airplane" class="w-12 h-12 mx-auto mb-2 text-info" />
                                <h4 class="mb-2 text-lg font-semibold">Profile Submitted</h4>
                                <p class="text-base-content/70">
                                    Your profile has been submitted for verification.
                                    We ll review your information and update your status soon.
                                </p>
                            </div>
                        @elseif($teacherProfile->status === 'checking')
                            <div class="p-6 text-center bg-warning bg-opacity-10 rounded-xl">
                                <x-icon name="o-clock" class="w-12 h-12 mx-auto mb-2 text-warning" />
                                <h4 class="mb-2 text-lg font-semibold">Under Review</h4>
                                <p class="text-base-content/70">
                                    Your profile is currently being reviewed by our team.
                                    This usually takes 1-2 business days.
                                </p>
                            </div>
                        @elseif($teacherProfile->status === 'verified')
                            <div class="p-6 text-center bg-success bg-opacity-10 rounded-xl">
                                <x-icon name="o-check-badge" class="w-12 h-12 mx-auto mb-2 text-success" />
                                <h4 class="mb-2 text-lg font-semibold">Verified Teacher</h4>
                                <p class="text-base-content/70">
                                    Congratulations! Your profile has been verified.
                                    You can now access all teacher features.
                                </p>
                            </div>
                        @else
                            <div class="p-6 text-center bg-warning bg-opacity-10 rounded-xl">
                                <x-icon name="o-exclamation-triangle" class="w-12 h-12 mx-auto mb-2 text-warning" />
                                <h4 class="mb-2 text-lg font-semibold">Incomplete Verification</h4>
                                <p class="mb-4 text-base-content/70">
                                    Your profile needs additional information to complete verification.
                                </p>
                                <a href="{{ route('teachers.profile.edit', $user->id) }}" class="btn btn-warning">
                                    Update Profile
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Teacher Stats Card -->
                @if($teacherProfile && $teacherProfile->status === 'verified')
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h3 class="text-xl font-bold">Teaching Statistics</h3>
                            <div class="my-2 divider"></div>

                            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                                <div class="p-4 text-center rounded-lg bg-base-200">
                                    <div class="text-3xl font-bold">{{ rand(5, 20) }}</div>
                                    <div class="text-sm text-base-content/70">Students</div>
                                </div>

                                <div class="p-4 text-center rounded-lg bg-base-200">
                                    <div class="text-3xl font-bold">{{ rand(10, 50) }}</div>
                                    <div class="text-sm text-base-content/70">Sessions</div>
                                </div>

                                <div class="p-4 text-center rounded-lg bg-base-200">
                                    <div class="text-3xl font-bold">{{ rand(1, 5) }}</div>
                                    <div class="text-sm text-base-content/70">Courses</div>
                                </div>

                                <div class="p-4 text-center rounded-lg bg-base-200">
                                    <div class="text-3xl font-bold">{{ number_format(rand(40, 100) / 10, 1) }}</div>
                                    <div class="text-sm text-base-content/70">Avg. Rating</div>
                                </div>
                            </div>

                            <div class="mt-4 text-center">
                                <a href="{{ route('teachers.dashboard') }}" class="btn btn-outline">
                                    <x-icon name="o-presentation-chart-line" class="w-4 h-4 mr-2" />
                                    View Detailed Stats
                                </a>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Quick Links -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="text-xl font-bold">Quick Links</h3>
                        <div class="my-2 divider"></div>

                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                            <a href="{{ route('teachers.dashboard') }}" class="btn btn-outline">
                                <x-icon name="o-home" class="w-4 h-4 mr-2" />
                                Dashboard
                            </a>

                            <a href="{{ route('teachers.sessions') }}" class="btn btn-outline">
                                <x-icon name="o-video-camera" class="w-4 h-4 mr-2" />
                                Sessions
                            </a>

                            <a href="{{ route('teachers.courses') }}" class="btn btn-outline">
                                <x-icon name="o-academic-cap" class="w-4 h-4 mr-2" />
                                Courses
                            </a>

                            <a href="{{ route('teachers.students.index') }}" class="btn btn-outline">
                                <x-icon name="o-users" class="w-4 h-4 mr-2" />
                                Students
                            </a>

                            <a href="{{ route('teachers.timetable') }}" class="btn btn-outline">
                                <x-icon name="o-calendar" class="w-4 h-4 mr-2" />
                                Timetable
                            </a>

                            <a href="{{ route('teachers.materials.index') }}" class="btn btn-outline">
                                <x-icon name="o-document-text" class="w-4 h-4 mr-2" />
                                Materials
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
