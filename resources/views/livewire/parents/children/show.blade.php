<?php

namespace App\Livewire\Parents\Children;

use Livewire\Volt\Component;
use App\Models\Children;
use App\Models\LearningSession;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    public $user;
    public $parentProfile;
    public $child;

    // Tab states
    public $activeTab = 'overview';

    // Chart data
    public $subjectProgressData = [];
    public $sessionAttendanceData = [];
    public $assessmentScoresData = [];

    // Modal states
    public $showDeleteModal = false;

    // Upcoming sessions
    public $upcomingSessions = [];

    // Recent activity
    public $recentActivity = [];

    // Learning materials
    public $learningMaterials = [];

    // Assessment submissions
    public $assessmentSubmissions = [];

    public function mount($child)
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        // Get child data
        $this->child = Children::where('id', $child)
            ->where('parent_profile_id', $this->parentProfile->id)
            ->with(['subjects', 'teacher', 'learningSessions', 'assessmentSubmissions', 'assessments'])
            ->firstOrFail();

        $this->loadData();
    }

    protected function loadData()
    {
        $this->loadChartData();
        $this->loadUpcomingSessions();
        $this->loadRecentActivity();
        $this->loadLearningMaterials();
        $this->loadAssessmentSubmissions();
    }

    protected function loadChartData()
    {
        // Subject progress data (in a real app, fetch this from database)
        $subjects = $this->child->subjects ?? [];
        $this->subjectProgressData = [];

        foreach ($subjects as $subject) {
            $this->subjectProgressData[] = [
                'name' => $subject->name,
                'progress' => rand(40, 95) // Placeholder - in real app calculate actual progress
            ];
        }

        // If no subjects, add some mock data
        if (count($this->subjectProgressData) === 0) {
            $this->subjectProgressData = [
                ['name' => 'Mathematics', 'progress' => rand(40, 95)],
                ['name' => 'Science', 'progress' => rand(40, 95)],
                ['name' => 'English', 'progress' => rand(40, 95)],
                ['name' => 'History', 'progress' => rand(40, 95)]
            ];
        }

        // Session attendance data (last 6 months)
        $this->sessionAttendanceData = [];
        $currentMonth = Carbon::now();

        for ($i = 5; $i >= 0; $i--) {
            $month = $currentMonth->copy()->subMonths($i);
            $this->sessionAttendanceData[] = [
                'month' => $month->format('M'),
                'sessions' => rand(2, 8) // Placeholder - in real app fetch actual attendance
            ];
        }

        // Assessment scores data (last 5 assessments)
        $this->assessmentScoresData = [];

        for ($i = 1; $i <= 5; $i++) {
            $this->assessmentScoresData[] = [
                'name' => "Test {$i}",
                'score' => rand(60, 100)
            ];
        }
    }

    protected function loadUpcomingSessions()
    {
        // In a real app, fetch from database
        $this->upcomingSessions = [];

        // Mock data
        $subjects = ['Mathematics', 'Science', 'English', 'History', 'Programming'];
        $teachers = ['Sarah Johnson', 'Michael Chen', 'Emily Rodriguez', 'David Wilson'];

        for ($i = 0; $i < 3; $i++) {
            $startDate = Carbon::now()->addDays(rand(1, 14))->setHour(rand(9, 17));
            $endDate = $startDate->copy()->addHours(rand(1, 2));

            $this->upcomingSessions[] = [
                'id' => $i + 1,
                'subject' => $subjects[array_rand($subjects)],
                'teacher' => $teachers[array_rand($teachers)],
                'start_time' => $startDate,
                'end_time' => $endDate,
                'location' => rand(0, 1) ? 'Online' : 'In-Person',
                'status' => 'scheduled'
            ];
        }
    }

    protected function loadRecentActivity()
    {
        // In a real app, fetch from database
        $this->recentActivity = [];

        // Activity types
        $types = [
            'session_completed' => [
                'icon' => 'o-check-circle',
                'color' => 'bg-green-100 text-green-600'
            ],
            'assessment_submitted' => [
                'icon' => 'o-document-check',
                'color' => 'bg-blue-100 text-blue-600'
            ],
            'homework_assigned' => [
                'icon' => 'o-clipboard-document-list',
                'color' => 'bg-yellow-100 text-yellow-600'
            ],
            'material_downloaded' => [
                'icon' => 'o-arrow-down-tray',
                'color' => 'bg-purple-100 text-purple-600'
            ],
            'teacher_feedback' => [
                'icon' => 'o-chat-bubble-left-right',
                'color' => 'bg-indigo-100 text-indigo-600'
            ],
        ];

        $subjects = ['Mathematics', 'Science', 'English', 'History', 'Programming'];

        // Generate 5 recent activities
        for ($i = 0; $i < 5; $i++) {
            $type = array_rand($types);
            $subject = $subjects[array_rand($subjects)];
            $daysAgo = $i * rand(1, 3);

            $description = match($type) {
                'session_completed' => "{$this->child->name} completed a {$subject} session",
                'assessment_submitted' => "{$this->child->name} submitted a {$subject} assessment",
                'homework_assigned' => "Homework assigned for {$subject}",
                'material_downloaded' => "Learning materials downloaded for {$subject}",
                'teacher_feedback' => "Teacher provided feedback on {$subject} progress",
                default => "Activity related to {$subject}"
            };

            $this->recentActivity[] = [
                'type' => $type,
                'description' => $description,
                'subject' => $subject,
                'date' => Carbon::now()->subDays($daysAgo),
                'icon' => $types[$type]['icon'],
                'color' => $types[$type]['color']
            ];
        }
    }

    protected function loadLearningMaterials()
    {
        // In a real app, fetch from database
        $this->learningMaterials = [];

        // Mock data
        $types = ['document', 'pdf', 'video', 'presentation', 'worksheet'];
        $subjects = ['Mathematics', 'Science', 'English', 'History', 'Programming'];

        for ($i = 0; $i < 5; $i++) {
            $type = $types[array_rand($types)];
            $subject = $subjects[array_rand($subjects)];

            $this->learningMaterials[] = [
                'id' => $i + 1,
                'title' => "{$subject} " . match($type) {
                    'document' => 'Notes',
                    'pdf' => 'Textbook Chapter',
                    'video' => 'Tutorial',
                    'presentation' => 'Slides',
                    'worksheet' => 'Exercises',
                    default => 'Material'
                },
                'type' => $type,
                'subject' => $subject,
                'date_added' => Carbon::now()->subDays(rand(1, 30)),
                'size' => rand(1, 10) . match(rand(0, 2)) {
                    0 => ' MB',
                    1 => ' KB',
                    2 => ' pages'
                },
                'downloaded' => rand(0, 1)
            ];
        }
    }

    protected function loadAssessmentSubmissions()
    {
        // In a real app, fetch from database
        $this->assessmentSubmissions = [];

        // Mock data
        $subjects = ['Mathematics', 'Science', 'English', 'History', 'Programming'];
        $types = ['Quiz', 'Test', 'Exam', 'Assignment'];

        for ($i = 0; $i < 5; $i++) {
            $type = $types[array_rand($types)];
            $subject = $subjects[array_rand($subjects)];
            $score = rand(60, 100);
            $daysAgo = rand(5, 60);

            $this->assessmentSubmissions[] = [
                'id' => $i + 1,
                'title' => "{$subject} {$type} " . ($i + 1),
                'type' => strtolower($type),
                'subject' => $subject,
                'submission_date' => Carbon::now()->subDays($daysAgo),
                'score' => $score,
                'total' => 100,
                'status' => $score >= 70 ? 'passed' : 'needs_improvement',
                'feedback' => rand(0, 1) ? 'Good work overall, focus on improving in areas X and Y.' : null
            ];
        }

        // Sort by date (newest first)
        usort($this->assessmentSubmissions, function($a, $b) {
            return $b['submission_date']->timestamp - $a['submission_date']->timestamp;
        });
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function confirmDelete()
    {
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
    }

    public function deleteChild()
    {
        // Delete the child
        $this->child->delete();

        session()->flash('message', 'Child removed successfully.');
        return redirect()->route('parents.children.index');
    }

    public function getSubjectIcon($subject)
    {
        return match(strtolower($subject)) {
            'mathematics' => 'o-calculator',
            'science' => 'o-beaker',
            'english' => 'o-book-open',
            'history' => 'o-clock',
            'programming' => 'o-code-bracket',
            'art' => 'o-paint-brush',
            'music' => 'o-musical-note',
            'physical education' => 'o-trophy',
            default => 'o-academic-cap'
        };
    }

    public function getMaterialIcon($type)
    {
        return match($type) {
            'document' => 'o-document-text',
            'pdf' => 'o-document',
            'video' => 'o-play',
            'presentation' => 'o-presentation-chart-bar',
            'worksheet' => 'o-clipboard-document-list',
            default => 'o-document'
        };
    }

    public function getAssessmentTypeClass($type)
    {
        return match($type) {
            'quiz' => 'badge-info',
            'test' => 'badge-warning',
            'exam' => 'badge-error',
            'assignment' => 'badge-success',
            default => 'badge-neutral'
        };
    }

    public function getAssessmentStatusClass($status)
    {
        return match($status) {
            'passed' => 'badge-success',
            'needs_improvement' => 'badge-warning',
            'failed' => 'badge-error',
            default => 'badge-neutral'
        };
    }

    public function getProgressColor($progress)
    {
        if ($progress >= 75) {
            return 'bg-success';
        } elseif ($progress >= 50) {
            return 'bg-info';
        } elseif ($progress >= 25) {
            return 'bg-warning';
        } else {
            return 'bg-error';
        }
    }

    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    public function formatTime($date)
    {
        return Carbon::parse($date)->format('h:i A');
    }

    public function getRelativeDate($date)
    {
        return Carbon::parse($date)->diffForHumans();
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('parents.children.index') }}" class="px-2 btn btn-ghost btn-sm">
                        <x-icon name="o-arrow-left" class="w-5 h-5" />
                    </a>
                    <h1 class="text-3xl font-bold">{{ $child->name }}</h1>
                </div>
                <p class="mt-1 text-base-content/70">Child Profile & Learning Progress</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('parents.children.edit', $child) }}" class="gap-2 btn btn-outline">
                    <x-icon name="o-pencil-square" class="w-4 h-4" />
                    <span>Edit Profile</span>
                </a>
                <a href="{{ route('parents.sessions.requests', ['child_id' => $child->id]) }}" class="gap-2 btn btn-primary">
                    <x-icon name="o-calendar-plus" class="w-4 h-4" />
                    <span>Schedule Session</span>
                </a>
            </div>
        </div>

        @if(session()->has('message'))
            <div class="mb-6 alert alert-success">
                <x-icon name="o-check-circle" class="w-6 h-6" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Left Column (Sidebar) -->
            <div class="space-y-6 lg:col-span-1">
                <!-- Profile Card -->
                <div class="shadow-xl card bg-base-100">
                    <div class="items-center text-center card-body">
                        <div class="avatar">
                            <div class="w-24 h-24 rounded-full bg-base-300">
                                @if($child->photo)
                                    <img src="{{ Storage::url($child->photo) }}" alt="{{ $child->name }}" />
                                @else
                                    <div class="flex items-center justify-center w-full h-full text-3xl font-bold text-base-content/30">
                                        {{ substr($child->name, 0, 1) }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        <h2 class="mt-2 card-title">{{ $child->name }}</h2>
                        <div class="flex flex-wrap justify-center gap-1 mt-1">
                            <div class="badge badge-ghost">{{ $child->age ?? '?' }} years</div>
                            <div class="capitalize badge badge-ghost">{{ $child->gender ?? 'Not specified' }}</div>
                        </div>

                        <div class="mt-3 mb-3 divider"></div>

                        <div class="w-full space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="opacity-70">Grade:</span>
                                <span class="font-medium">{{ $child->grade ?? 'Not specified' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="opacity-70">School:</span>
                                <span class="font-medium">{{ $child->school_name ?? 'Not specified' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="opacity-70">Teacher:</span>
                                <span class="font-medium">{{ $child->teacher->name ?? 'Not assigned' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="opacity-70">Added:</span>
                                <span class="font-medium">{{ $formatDate($child->created_at) }}</span>
                            </div>
                        </div>

                        <div class="justify-center mt-4 card-actions">
                            <button onclick="document.getElementById('action-menu').showModal()" class="btn btn-outline btn-block btn-sm">
                                <x-icon name="o-ellipsis-horizontal" class="w-4 h-4 mr-2" />
                                More Actions
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Subject Progress Card -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="text-lg card-title">Subject Progress</h3>

                        <div class="mt-4 space-y-4">
                            @foreach($subjectProgressData as $subject)
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-2">
                                            <x-icon name="{{ $getSubjectIcon($subject['name']) }}" class="w-4 h-4 opacity-70" />
                                            <span class="text-sm">{{ $subject['name'] }}</span>
                                        </div>
                                        <span class="text-sm font-medium">{{ $subject['progress'] }}%</span>
                                    </div>
                                    <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                        <div class="h-full {{ $getProgressColor($subject['progress']) }}" style="width: {{ $subject['progress'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="justify-center mt-4 card-actions">
                            <a href="{{ route('parents.progress.child', $child->id) }}" class="btn btn-sm btn-outline btn-block">
                                <x-icon name="o-chart-bar" class="w-4 h-4 mr-2" />
                                View Detailed Progress
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Learning Style Card -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h3 class="text-lg card-title">Learning Profile</h3>

                        <div class="mt-2 mb-2 divider"></div>

                        <div class="space-y-4">
                            <!-- Learning Style -->
                            <div>
                                <h4 class="mb-2 text-sm font-medium">Learning Style</h4>
                                <div class="badge badge-lg">{{ $child->learning_style ?: 'Not specified' }}</div>
                            </div>

                            <!-- Interests -->
                            <div>
                                <h4 class="mb-2 text-sm font-medium">Interests</h4>
                                @if($child->interests && count($child->interests) > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($child->interests as $interest)
                                            <div class="badge badge-primary badge-outline">{{ ucfirst($interest) }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm opacity-70">No interests specified</p>
                                @endif
                            </div>

                            <!-- Preferred Times -->
                            <div>
                                <h4 class="mb-2 text-sm font-medium">Available Times</h4>
                                @if($child->available_times && count($child->available_times) > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($child->available_times as $time)
                                            <div class="badge badge-outline">{{ ucfirst(str_replace('_', ' ', $time)) }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm opacity-70">No preferred times specified</p>
                                @endif
                            </div>

                            <!-- Special Needs -->
                            @if($child->special_needs)
                                <div>
                                    <h4 class="mb-2 text-sm font-medium">Special Needs</h4>
                                    <p class="text-sm opacity-70">{{ $child->special_needs }}</p>
                                </div>
                            @endif

                            <!-- Allergies -->
                            @if($child->allergies)
                                <div>
                                    <h4 class="mb-2 text-sm font-medium">Allergies/Medical</h4>
                                    <p class="text-sm opacity-70">{{ $child->allergies }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="space-y-6 lg:col-span-3">
                <!-- Tabs -->
                <div class="tabs tabs-boxed">
                    <a wire:click="setActiveTab('overview')" class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}">Overview</a>
                    <a wire:click="setActiveTab('sessions')" class="tab {{ $activeTab === 'sessions' ? 'tab-active' : '' }}">Sessions</a>
                    <a wire:click="setActiveTab('assessments')" class="tab {{ $activeTab === 'assessments' ? 'tab-active' : '' }}">Assessments</a>
                    <a wire:click="setActiveTab('materials')" class="tab {{ $activeTab === 'materials' ? 'tab-active' : '' }}">Materials</a>
                </div>

                <!-- Overview Tab -->
                <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
                    <!-- Progress Overview Card -->
                    <div class="mb-6 shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h3 class="card-title">Progress Overview</h3>

                            <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-3">
                                <div>
                                    <div class="text-center stat-title">Overall Progress</div>
                                    @php
                                        $overallProgress = count($subjectProgressData) > 0
                                            ? array_sum(array_column($subjectProgressData, 'progress')) / count($subjectProgressData)
                                            : 0;
                                    @endphp
                                    <div class="mx-auto radial-progress text-primary" style="--value:{{ round($overallProgress) }}; --size:8rem;">
                                        <span class="text-xl">{{ round($overallProgress) }}%</span>
                                    </div>
                                </div>

                                <div>
                                    <div class="text-center stat-title">Sessions This Month</div>
                                    <div class="flex items-end justify-center h-24">
                                        @foreach($sessionAttendanceData as $index => $month)
                                            <div class="flex flex-col items-center mx-1">
                                                <div class="w-10 mb-1 text-xs text-center">{{ $month['sessions'] }}</div>
                                                <div style="height: {{ min(80, $month['sessions'] * 10) }}px" class="w-6 rounded-t bg-primary"></div>
                                                <div class="w-10 mt-1 text-xs text-center">{{ $month['month'] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <div class="text-center stat-title">Assessment Scores</div>
                                    <div class="flex items-end justify-center h-24">
                                        @foreach($assessmentScoresData as $index => $assessment)
                                            <div class="flex flex-col items-center mx-1">
                                                <div class="w-10 mb-1 text-xs text-center">{{ $assessment['score'] }}%</div>
                                                <div style="height: {{ min(80, $assessment['score'] * 0.8) }}px" class="w-6 {{ $assessment['score'] >= 70 ? 'bg-success' : ($assessment['score'] >= 60 ? 'bg-warning' : 'bg-error') }} rounded-t"></div>
                                                <div class="w-10 mt-1 text-xs text-center truncate">{{ $assessment['name'] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Sessions -->
                    <div class="mb-6 shadow-xl card bg-base-100">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="card-title">Upcoming Sessions</h3>
                                <a href="{{ route('parents.sessions.index', ['child_id' => $child->id]) }}" class="btn btn-sm btn-ghost">
                                    View All
                                </a>
                            </div>

                            @if(count($upcomingSessions) > 0)
                                <div class="overflow-x-auto">
                                    <table class="table w-full table-zebra">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Date & Time</th>
                                                <th>Teacher</th>
                                                <th>Location</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($upcomingSessions as $session)
                                                <tr>
                                                    <td class="font-medium">{{ $session['subject'] }}</td>
                                                    <td>
                                                        <div class="flex flex-col">
                                                            <span>{{ $formatDate($session['start_time']) }}</span>
                                                            <span class="text-xs opacity-70">{{ $formatTime($session['start_time']) }} - {{ $formatTime($session['end_time']) }}</span>
                                                        </div>
                                                    </td>
                                                    <td>{{ $session['teacher'] }}</td>
                                                    <td>
                                                        <div class="badge {{ $session['location'] === 'Online' ? 'badge-info' : 'badge-success' }}">
                                                            {{ $session['location'] }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="flex gap-1">
                                                            <button class="btn btn-ghost btn-xs">Details</button>
                                                            @if($session['location'] === 'Online')
                                                                <button class="btn btn-primary btn-xs">Join</button>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="py-8 text-center">
                                    <x-icon name="o-calendar" class="w-12 h-12 mx-auto mb-3 text-base-content/30" />
                                    <h3 class="text-lg font-medium">No upcoming sessions</h3>
                                    <p class="mt-1 text-base-content/70">Schedule a session to continue learning</p>
                                    <a href="{{ route('parents.sessions.requests', ['child_id' => $child->id]) }}" class="mt-4 btn btn-primary">
                                        <x-icon name="o-calendar-plus" class="w-4 h-4 mr-2" />
                                        Schedule Session
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h3 class="mb-4 card-title">Recent Activity</h3>

                            @if(count($recentActivity) > 0)
                                <div class="space-y-4">
                                    @foreach($recentActivity as $activity)
                                        <div class="flex gap-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $activity['color'] }}">
                                                    <x-icon name="{{ $activity['icon'] }}" class="w-5 h-5" />
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-medium">{{ $activity['description'] }}</div>
                                                <div class="text-sm opacity-70">{{ $activity['subject'] }}</div>
                                                <div class="mt-1 text-xs opacity-50">{{ $getRelativeDate($activity['date']) }}</div>
                                            </div>
                                        </div>
                                        @if(!$loop->last)
                                            <div class="h-4 ml-5 border-l-2 border-dashed border-base-300"></div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="py-8 text-center">
                                    <x-icon name="o-clock-rewind" class="w-12 h-12 mx-auto mb-3 text-base-content/30" />
                                    <h3 class="text-lg font-medium">No recent activity</h3>
                                    <p class="mt-1 text-base-content/70">Activity will appear here as your child progresses</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Sessions Tab -->
                <div class="{{ $activeTab === 'sessions' ? 'block' : 'hidden' }}">
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="card-title">Learning Sessions</h3>
                                <a href="{{ route('parents.sessions.requests', ['child_id' => $child->id]) }}" class="btn btn-primary btn-sm">
                                    <x-icon name="o-calendar-plus" class="w-4 h-4 mr-2" />
                                    Schedule New
                                </a>
                            </div>

                            <!-- Session Calendar will go here (placeholder) -->
                            <div class="p-8 mb-6 text-center rounded-lg bg-base-200">
                                <h3 class="text-lg font-medium">Session Calendar</h3>
                                <p class="mt-1 text-base-content/70">View and manage all scheduled sessions</p>
                                <p class="mt-4 text-sm opacity-50">Calendar component would be displayed here</p>
                            </div>

                            <!-- Recent/Upcoming Sessions -->
                            <div class="space-y-4">
                                <h4 class="font-medium">Recent & Upcoming Sessions</h4>

                                <div class="overflow-x-auto">
                                    <table class="table w-full table-zebra">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Date & Time</th>
                                                <th>Teacher</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($upcomingSessions as $session)
                                                <tr>
                                                    <td class="font-medium">{{ $session['subject'] }}</td>
                                                    <td>
                                                        <div class="flex flex-col">
                                                            <span>{{ $formatDate($session['start_time']) }}</span>
                                                            <span class="text-xs opacity-70">{{ $formatTime($session['start_time']) }} - {{ $formatTime($session['end_time']) }}</span>
                                                        </div>
                                                    </td>
                                                    <td>{{ $session['teacher'] }}</td>
                                                    <td>
                                                        <div class="badge badge-info">
                                                            Upcoming
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown dropdown-end">
                                                            <div tabindex="0" role="button" class="btn btn-ghost btn-xs">
                                                                <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                                            </div>
                                                            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                                <li><a>View Details</a></li>
                                                                <li><a>Reschedule</a></li>
                                                                <li><a class="text-error">Cancel Session</a></li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach

                                            <!-- Add some past sessions as examples -->
                                            <tr>
                                                <td class="font-medium">Mathematics</td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <span>{{ $formatDate(Carbon::now()->subDays(2)) }}</span>
                                                        <span class="text-xs opacity-70">10:00 AM - 11:30 AM</span>
                                                    </div>
                                                </td>
                                                <td>Sarah Johnson</td>
                                                <td>
                                                    <div class="badge badge-success">
                                                        Completed
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="dropdown dropdown-end">
                                                        <div tabindex="0" role="button" class="btn btn-ghost btn-xs">
                                                            <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                                        </div>
                                                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                            <li><a>View Details</a></li>
                                                            <li><a>View Recording</a></li>
                                                            <li><a>View Notes</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="font-medium">Science</td>
                                                <td>
                                                    <div class="flex flex-col">
                                                        <span>{{ $formatDate(Carbon::now()->subDays(5)) }}</span>
                                                        <span class="text-xs opacity-70">2:00 PM - 3:30 PM</span>
                                                    </div>
                                                </td>
                                                <td>Michael Chen</td>
                                                <td>
                                                    <div class="badge badge-success">
                                                        Completed
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="dropdown dropdown-end">
                                                        <div tabindex="0" role="button" class="btn btn-ghost btn-xs">
                                                            <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                                        </div>
                                                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                            <li><a>View Details</a></li>
                                                            <li><a>View Recording</a></li>
                                                            <li><a>View Notes</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex justify-center mt-4">
                                    <a href="{{ route('parents.sessions.index', ['child_id' => $child->id]) }}" class="btn btn-outline btn-sm">
                                        View All Sessions
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assessments Tab -->
                <div class="{{ $activeTab === 'assessments' ? 'block' : 'hidden' }}">
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h3 class="mb-6 card-title">Assessments & Results</h3>

                            <!-- Assessment Summary -->
                            <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-3">
                                <div class="rounded-lg stat bg-base-200">
                                    <div class="stat-figure text-primary">
                                        <x-icon name="o-clipboard-document-check" class="w-8 h-8" />
                                    </div>
                                    <div class="stat-title">Completed</div>
                                    <div class="stat-value text-primary">{{ count($assessmentSubmissions) }}</div>
                                    <div class="stat-desc">Total assessments</div>
                                </div>

                                <div class="rounded-lg stat bg-base-200">
                                    <div class="stat-figure text-success">
                                        <x-icon name="o-trophy" class="w-8 h-8" />
                                    </div>
                                    <div class="stat-title">Average Score</div>
                                    @php
                                        $avgScore = count($assessmentSubmissions) > 0
                                            ? array_sum(array_column($assessmentSubmissions, 'score')) / count($assessmentSubmissions)
                                            : 0;
                                    @endphp
                                    <div class="stat-value text-success">{{ round($avgScore) }}%</div>
                                    <div class="stat-desc">Across all subjects</div>
                                </div>

                                <div class="rounded-lg stat bg-base-200">
                                    <div class="stat-figure text-info">
                                        <x-icon name="o-document-magnifying-glass" class="w-8 h-8" />
                                    </div>
                                    <div class="stat-title">Upcoming</div>
                                    <div class="stat-value text-info">{{ rand(0, 3) }}</div>
                                    <div class="stat-desc">Scheduled assessments</div>
                                </div>
                            </div>

                            <!-- Assessment List -->
                            <div class="space-y-4">
                                <h4 class="font-medium">Recent Assessments</h4>

                                @if(count($assessmentSubmissions) > 0)
                                    <div class="overflow-x-auto">
                                        <table class="table w-full table-zebra">
                                            <thead>
                                                <tr>
                                                    <th>Assessment</th>
                                                    <th>Type</th>
                                                    <th>Date</th>
                                                    <th>Score</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($assessmentSubmissions as $submission)
                                                    <tr>
                                                        <td class="font-medium">{{ $submission['title'] }}</td>
                                                        <td>
                                                            <div class="badge {{ $getAssessmentTypeClass($submission['type']) }}">
                                                                {{ ucfirst($submission['type']) }}
                                                            </div>
                                                        </td>
                                                        <td>{{ $formatDate($submission['submission_date']) }}</td>
                                                        <td>
                                                            <div class="radial-progress text-{{ $submission['score'] >= 70 ? 'success' : ($submission['score'] >= 60 ? 'warning' : 'error') }}" style="--value:{{ $submission['score'] }}; --size: 2rem;">
                                                                <span class="text-xs">{{ $submission['score'] }}%</span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="badge {{ $getAssessmentStatusClass($submission['status']) }}">
                                                                {{ ucfirst(str_replace('_', ' ', $submission['status'])) }}
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-ghost btn-xs">View Details</button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="py-8 text-center">
                                        <x-icon name="o-clipboard-document-check" class="w-12 h-12 mx-auto mb-3 text-base-content/30" />
                                        <h3 class="text-lg font-medium">No assessments yet</h3>
                                        <p class="mt-1 text-base-content/70">Assessments will appear here after completion</p>
                                    </div>
                                @endif

                                <div class="flex justify-center mt-4">
                                    <a href="{{ route('parents.assessments.index', ['child_id' => $child->id]) }}" class="btn btn-outline btn-sm">
                                        View All Assessments
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Materials Tab -->
                <div class="{{ $activeTab === 'materials' ? 'block' : 'hidden' }}">
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h3 class="mb-6 card-title">Learning Materials</h3>

                            <div class="flex justify-between mb-4">
                                <div class="join">
                                    <button class="btn join-item btn-sm btn-active">All</button>
                                    <button class="btn join-item btn-sm">Documents</button>
                                    <button class="btn join-item btn-sm">Videos</button>
                                    <button class="btn join-item btn-sm">Worksheets</button>
                                </div>

                                <div>
                                    <div class="join">
                                        <input class="w-48 input input-bordered input-sm join-item" placeholder="Search materials..."/>
                                        <button class="btn btn-sm join-item">Search</button>
                                    </div>
                                </div>
                            </div>

                            @if(count($learningMaterials) > 0)
                                <div class="overflow-x-auto">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Material</th>
                                                <th>Subject</th>
                                                <th>Added</th>
                                                <th>Size</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($learningMaterials as $material)
                                                <tr>
                                                    <td>
                                                        <div class="flex items-center gap-3">
                                                            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-base-200">
                                                                <x-icon name="{{ $getMaterialIcon($material['type']) }}" class="w-5 h-5" />
                                                            </div>
                                                            <div>
                                                                <div class="font-medium">{{ $material['title'] }}</div>
                                                                <div class="text-xs capitalize opacity-70">{{ $material['type'] }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>{{ $material['subject'] }}</td>
                                                    <td>{{ $getRelativeDate($material['date_added']) }}</td>
                                                    <td>{{ $material['size'] }}</td>
                                                    <td>
                                                        <div class="flex gap-1">
                                                            <button class="btn btn-ghost btn-xs">Preview</button>
                                                            <button class="btn btn-primary btn-xs">
                                                                <x-icon name="o-arrow-down-tray" class="w-3 h-3 mr-1" />
                                                                Download
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="py-8 text-center">
                                    <x-icon name="o-document-text" class="w-12 h-12 mx-auto mb-3 text-base-content/30" />
                                    <h3 class="text-lg font-medium">No materials available</h3>
                                    <p class="mt-1 text-base-content/70">Learning materials will appear here as they become available</p>
                                </div>
                            @endif

                            <div class="flex justify-center mt-4">
                                <a href="{{ route('parents.materials.index', ['child_id' => $child->id]) }}" class="btn btn-outline btn-sm">
                                    View All Materials
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Menu Modal -->
    <dialog id="action-menu" class="modal">
        <div class="modal-box">
            <form method="dialog">
                <button class="absolute btn btn-sm btn-circle btn-ghost right-2 top-2">✕</button>
            </form>
            <h3 class="mb-4 text-lg font-bold">Child Options</h3>

            <div class="space-y-2">
                <a href="{{ route('parents.children.edit', $child) }}" class="justify-start btn btn-outline btn-block">
                    <x-icon name="o-pencil-square" class="w-5 h-5 mr-3" />
                    Edit Profile
                </a>

                <a href="{{ route('parents.progress.child', $child->id) }}" class="justify-start btn btn-outline btn-block">
                    <x-icon name="o-chart-bar" class="w-5 h-5 mr-3" />
                    View Progress Report
                </a>

                <a href="{{ route('parents.sessions.requests', ['child_id' => $child->id]) }}" class="justify-start btn btn-outline btn-block">
                    <x-icon name="o-calendar-plus" class="w-5 h-5 mr-3" />
                    Schedule New Session
                </a>

                <a href="{{ route('parents.sessions.index', ['child_id' => $child->id]) }}" class="justify-start btn btn-outline btn-block">
                    <x-icon name="o-calendar" class="w-5 h-5 mr-3" />
                    View All Sessions
                </a>

                <a href="{{ route('parents.assessments.index', ['child_id' => $child->id]) }}" class="justify-start btn btn-outline btn-block">
                    <x-icon name="o-clipboard-document-list" class="w-5 h-5 mr-3" />
                    View All Assessments
                </a>

                <a href="{{ route('parents.materials.index', ['child_id' => $child->id]) }}" class="justify-start btn btn-outline btn-block">
                    <x-icon name="o-book-open" class="w-5 h-5 mr-3" />
                    Learning Materials
                </a>

                <button wire:click="confirmDelete" class="justify-start mt-6 btn btn-error btn-outline btn-block">
                    <x-icon name="o-trash" class="w-5 h-5 mr-3" />
                    Remove Child
                </button>
            </div>
        </div>
        <div class="modal-backdrop">
            <form method="dialog">
                <button>close</button>
            </form>
        </div>
    </dialog>

    <!-- Delete Confirmation Modal -->
    <dialog class="modal {{ $showDeleteModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold text-error">Remove Child</h3>
            <p class="py-4">Are you sure you want to remove {{ $child->name }} from your account? This action will remove all associated data including:</p>

            <ul class="py-2 space-y-1 list-disc list-inside">
                <li>Profile information</li>
                <li>Session history and records</li>
                <li>Assessment results</li>
                <li>Progress tracking data</li>
            </ul>

            <p class="py-2 text-error">This action cannot be undone.</p>

            <div class="modal-action">
                <button wire:click="cancelDelete" class="btn">Cancel</button>
                <button wire:click="deleteChild" class="btn btn-error">Remove Child</button>
            </div>
        </div>
        <div class="modal-backdrop" wire:click="cancelDelete"></div>
    </dialog>
</div>
