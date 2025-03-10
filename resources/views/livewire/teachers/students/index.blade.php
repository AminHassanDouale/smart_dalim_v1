<?php

namespace App\Livewire\Teachers\Students;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Children;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningSession;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $teacherProfile;

    // Filter states
    public $search = '';
    public $courseFilter = '';
    public $statusFilter = '';
    public $sortField = 'name';
    public $sortDirection = 'asc';

    // Course list for filter
    public $courses = [];

    // Student view modal
    public $showStudentModal = false;
    public $selectedStudent = null;
    public $studentSessions = [];
    public $studentEnrollments = [];
    public $studentStats = [
        'total_sessions' => 0,
        'attendance_rate' => 0,
        'completed_courses' => 0,
        'avg_progress' => 0,
    ];

    // Message modal
    public $showMessageModal = false;
    public $messageSubject = '';
    public $messageContent = '';
    public $selectedStudentIds = [];
    public $bulkMessage = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'courseFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->teacherProfile = $this->user->teacherProfile;

        // Load courses for filter dropdown
        $this->loadCourses();
    }

    public function loadCourses()
    {
        // In a real app, fetch from database
        // For now, use mock data
        $this->courses = [
            ['id' => 1, 'name' => 'Advanced Laravel Development'],
            ['id' => 2, 'name' => 'React and Redux Masterclass'],
            ['id' => 3, 'name' => 'UI/UX Design Fundamentals'],
            ['id' => 4, 'name' => 'Mobile Development with Flutter'],
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCourseFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function viewStudent($studentId)
    {
        // Find student in our collection
        $student = collect($this->getMockStudents())->firstWhere('id', $studentId);

        if ($student) {
            $this->selectedStudent = $student;

            // Load student sessions
            $this->studentSessions = $this->getMockSessionsForStudent($studentId);

            // Load student enrollments
            $this->studentEnrollments = $this->getMockEnrollmentsForStudent($studentId);

            // Calculate stats
            $this->calculateStudentStats($studentId);

            $this->showStudentModal = true;
        }
    }

    public function calculateStudentStats($studentId)
    {
        $sessions = collect($this->studentSessions);
        $enrollments = collect($this->studentEnrollments);

        // Total sessions
        $total = $sessions->count();

        // Attendance rate
        $attended = $sessions->filter(function($session) {
            return $session['attended'] === true;
        })->count();

        $attendanceRate = $total > 0 ? round(($attended / $total) * 100) : 0;

        // Completed courses
        $completedCourses = $enrollments->filter(function($enrollment) {
            return $enrollment['status'] === 'completed';
        })->count();

        // Average progress
        $avgProgress = $enrollments->avg('progress');

        $this->studentStats = [
            'total_sessions' => $total,
            'attendance_rate' => $attendanceRate,
            'completed_courses' => $completedCourses,
            'avg_progress' => round($avgProgress),
        ];
    }

    public function closeStudentModal()
    {
        $this->showStudentModal = false;
        $this->selectedStudent = null;
        $this->studentSessions = [];
        $this->studentEnrollments = [];
    }

    public function openMessageModal($studentId = null)
    {
        $this->resetMessageForm();

        if ($studentId) {
            $this->selectedStudentIds = [$studentId];
            $this->bulkMessage = false;

            // Get student name for modal title
            $student = collect($this->getMockStudents())->firstWhere('id', $studentId);
            if ($student) {
                $this->messageSubject = 'Message to ' . $student['name'];
            }
        } else {
            // If no student ID provided, show bulk message form
            $this->bulkMessage = true;
            $this->messageSubject = 'Message to multiple students';

            // Pre-select all visible students
            $this->selectedStudentIds = collect($this->getStudentsProperty())
                ->pluck('id')
                ->toArray();
        }

        $this->showMessageModal = true;
    }

    public function resetMessageForm()
    {
        $this->messageSubject = '';
        $this->messageContent = '';
        $this->selectedStudentIds = [];
    }

    public function closeMessageModal()
    {
        $this->showMessageModal = false;
        $this->resetMessageForm();
    }

    public function sendMessage()
    {
        // Validate the form
        $this->validate([
            'messageSubject' => 'required|min:3|max:100',
            'messageContent' => 'required|min:10|max:1000',
            'selectedStudentIds' => 'required|array|min:1',
        ]);

        // In a real app, this would send messages to selected students

        // Show success message
        $this->toast(
            type: 'success',
            title: 'Message sent',
            description: 'Your message has been sent to ' . count($this->selectedStudentIds) . ' student(s).',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeMessageModal();
    }

    public function toggleStudentSelection($studentId)
    {
        if (in_array($studentId, $this->selectedStudentIds)) {
            $this->selectedStudentIds = array_diff($this->selectedStudentIds, [$studentId]);
        } else {
            $this->selectedStudentIds[] = $studentId;
        }
    }

    public function isStudentSelected($studentId)
    {
        return in_array($studentId, $this->selectedStudentIds);
    }

    public function selectAllStudents()
    {
        $this->selectedStudentIds = collect($this->getStudentsProperty())
            ->pluck('id')
            ->toArray();
    }

    public function deselectAllStudents()
    {
        $this->selectedStudentIds = [];
    }

    // Get students with filtering and sorting
    public function getStudentsProperty()
    {
        $students = collect($this->getMockStudents());

        // Apply search filter
        if (!empty($this->search)) {
            $search = strtolower($this->search);
            $students = $students->filter(function($student) use ($search) {
                return str_contains(strtolower($student['name']), $search) ||
                       str_contains(strtolower($student['email']), $search);
            });
        }

        // Apply course filter
        if (!empty($this->courseFilter)) {
            $students = $students->filter(function($student) {
                return in_array($this->courseFilter, $student['enrolled_course_ids']);
            });
        }

        // Apply status filter
        if (!empty($this->statusFilter)) {
            $students = $students->filter(function($student) {
                return $student['status'] === $this->statusFilter;
            });
        }

        // Apply sorting
        $sortField = $this->sortField;
        $sortDirection = $this->sortDirection;

        $students = $students->sort(function($a, $b) use ($sortField, $sortDirection) {
            if ($a[$sortField] == $b[$sortField]) {
                return 0;
            }

            if ($sortDirection === 'asc') {
                return $a[$sortField] > $b[$sortField] ? 1 : -1;
            } else {
                return $a[$sortField] < $b[$sortField] ? 1 : -1;
            }
        });

        return $students->values()->all();
    }

    // Get statistics for student dashboard
    public function getStatsProperty()
    {
        $students = collect($this->getMockStudents());

        return [
            'total' => $students->count(),
            'active' => $students->where('status', 'active')->count(),
            'inactive' => $students->where('status', 'inactive')->count(),
            'new_this_month' => $students->filter(function($student) {
                return Carbon::parse($student['enrolled_at'])->isCurrentMonth();
            })->count(),
            'avg_sessions' => round($students->avg('total_sessions')),
        ];
    }

    // Get appropriate badge color for status
    public function getStatusBadgeClass($status)
    {
        switch ($status) {
            case 'active':
                return 'badge-success';
            case 'inactive':
                return 'badge-error';
            default:
                return 'badge-info';
        }
    }

    // Format date for display
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // Get relative time
    public function getRelativeTime($date)
    {
        return Carbon::parse($date)->diffForHumans();
    }

    // Mock data - would be fetched from database in real app
    private function getMockStudents()
    {
        return [
            [
                'id' => 1,
                'name' => 'Alex Johnson',
                'email' => 'alex.johnson@example.com',
                'avatar' => null,
                'status' => 'active',
                'enrolled_at' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'last_session_at' => Carbon::now()->subDays(3)->format('Y-m-d'),
                'enrolled_course_ids' => [1, 3],
                'enrolled_courses' => ['Advanced Laravel Development', 'UI/UX Design Fundamentals'],
                'total_sessions' => 12,
                'attendance_rate' => 92,
                'progress' => 65,
            ],
            [
                'id' => 2,
                'name' => 'Emma Smith',
                'email' => 'emma.smith@example.com',
                'avatar' => null,
                'status' => 'active',
                'enrolled_at' => Carbon::now()->subDays(60)->format('Y-m-d'),
                'last_session_at' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'enrolled_course_ids' => [2],
                'enrolled_courses' => ['React and Redux Masterclass'],
                'total_sessions' => 8,
                'attendance_rate' => 88,
                'progress' => 45,
            ],
            [
                'id' => 3,
                'name' => 'Michael Brown',
                'email' => 'michael.brown@example.com',
                'avatar' => null,
                'status' => 'active',
                'enrolled_at' => Carbon::now()->subDays(20)->format('Y-m-d'),
                'last_session_at' => Carbon::now()->subDays(2)->format('Y-m-d'),
                'enrolled_course_ids' => [1],
                'enrolled_courses' => ['Advanced Laravel Development'],
                'total_sessions' => 6,
                'attendance_rate' => 100,
                'progress' => 35,
            ],
            [
                'id' => 4,
                'name' => 'Sophia Garcia',
                'email' => 'sophia.garcia@example.com',
                'avatar' => null,
                'status' => 'active',
                'enrolled_at' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'last_session_at' => Carbon::now()->subDays(1)->format('Y-m-d'),
                'enrolled_course_ids' => [1, 2, 3],
                'enrolled_courses' => ['Advanced Laravel Development', 'React and Redux Masterclass', 'UI/UX Design Fundamentals'],
                'total_sessions' => 4,
                'attendance_rate' => 100,
                'progress' => 25,
            ],
            [
                'id' => 5,
                'name' => 'William Martinez',
                'email' => 'william.martinez@example.com',
                'avatar' => null,
                'status' => 'inactive',
                'enrolled_at' => Carbon::now()->subDays(90)->format('Y-m-d'),
                'last_session_at' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'enrolled_course_ids' => [3, 4],
                'enrolled_courses' => ['UI/UX Design Fundamentals', 'Mobile Development with Flutter'],
                'total_sessions' => 15,
                'attendance_rate' => 73,
                'progress' => 80,
            ],
            [
                'id' => 6,
                'name' => 'Olivia Wilson',
                'email' => 'olivia.wilson@example.com',
                'avatar' => null,
                'status' => 'active',
                'enrolled_at' => Carbon::now()->subDays(45)->format('Y-m-d'),
                'last_session_at' => Carbon::now()->subDays(7)->format('Y-m-d'),
                'enrolled_course_ids' => [4],
                'enrolled_courses' => ['Mobile Development with Flutter'],
                'total_sessions' => 9,
                'attendance_rate' => 89,
                'progress' => 55,
            ],
            [
                'id' => 7,
                'name' => 'James Anderson',
                'email' => 'james.anderson@example.com',
                'avatar' => null,
                'status' => 'inactive',
                'enrolled_at' => Carbon::now()->subDays(120)->format('Y-m-d'),
                'last_session_at' => Carbon::now()->subDays(40)->format('Y-m-d'),
                'enrolled_course_ids' => [2, 4],
                'enrolled_courses' => ['React and Redux Masterclass', 'Mobile Development with Flutter'],
                'total_sessions' => 20,
                'attendance_rate' => 85,
                'progress' => 90,
            ],
            [
                'id' => 8,
                'name' => 'Charlotte Thomas',
                'email' => 'charlotte.thomas@example.com',
                'avatar' => null,
                'status' => 'active',
                'enrolled_at' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'last_session_at' => Carbon::now()->subHours(12)->format('Y-m-d'),
                'enrolled_course_ids' => [1, 2],
                'enrolled_courses' => ['Advanced Laravel Development', 'React and Redux Masterclass'],
                'total_sessions' => 2,
                'attendance_rate' => 100,
                'progress' => 10,
            ],
        ];
    }

    private function getMockSessionsForStudent($studentId)
    {
        // Generate some mock sessions for the student
        $sessions = [];
        $count = rand(5, 10);

        for ($i = 0; $i < $count; $i++) {
            $isCompleted = $i < $count - 2; // Make the last 2 sessions upcoming
            $date = $isCompleted ?
                Carbon::now()->subDays(($count - $i) * 5)->format('Y-m-d') :
                Carbon::now()->addDays(($i - $count + 3) * 3)->format('Y-m-d');

            $sessions[] = [
                'id' => $i + 1,
                'title' => 'Session #' . ($i + 1),
                'course_id' => array_rand([1, 2, 3, 4]) + 1,
                'course_name' => $this->courses[array_rand($this->courses)]['name'],
                'date' => $date,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'status' => $isCompleted ? 'completed' : 'scheduled',
                'attended' => $isCompleted ? (rand(0, 10) > 2) : null, // 80% chance of attendance for completed sessions
                'performance_score' => $isCompleted ? rand(1, 5) : null,
            ];
        }

        return $sessions;
    }

    private function getMockEnrollmentsForStudent($studentId)
    {
        // Find student
        $student = collect($this->getMockStudents())->firstWhere('id', $studentId);

        if (!$student) {
            return [];
        }

        $enrollments = [];

        foreach ($student['enrolled_course_ids'] as $index => $courseId) {
            $courseName = $this->courses[array_search($courseId, array_column($this->courses, 'id'))]['name'] ?? 'Unknown Course';

            $enrollments[] = [
                'id' => $index + 1,
                'student_id' => $studentId,
                'course_id' => $courseId,
                'course_name' => $courseName,
                'enrollment_date' => Carbon::now()->subDays(rand(10, 100))->format('Y-m-d'),
                'status' => rand(0, 10) > 8 ? 'completed' : 'active',
                'progress' => $student['progress'],
                'completion_date' => null,
            ];
        }

        return $enrollments;
    }

    // Toast notification helper function
    protected function toast(
        string $type,
        string $title,
        $description = '',
        string $position = 'toast-bottom toast-end',
        string $icon = '',
        string $css = '',
        $timeout = 3000,
        $action = null
    ) {
        $actionJson = $action ? json_encode($action) : 'null';

        $this->js("
            Toaster.{$type}('{$title}', {
                description: '{$description}',
                position: '{$position}',
                icon: '{$icon}',
                css: '{$css}',
                timeout: {$timeout},
                action: {$actionJson}
            });
        ");
    }
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">My Students</h1>
                <p class="mt-1 text-base-content/70">Manage and monitor your students' progress</p>
            </div>
            <div class="flex gap-2">
                <button
                    wire:click="openMessageModal()"
                    class="text-white btn btn-primary"
                >
                    <x-icon name="o-envelope" class="w-4 h-4 mr-2" />
                    Message Students
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-6 mb-8 sm:grid-cols-2 md:grid-cols-5">
            <div class="p-6 shadow-lg rounded-xl bg-base-100">
                <div class="text-4xl font-bold">{{ $this->stats['total'] }}</div>
                <div class="mt-1 text-sm opacity-70">Total Students</div>
            </div>

            <div class="p-6 shadow-lg rounded-xl bg-base-100">
                <div class="text-4xl font-bold text-success">{{ $this->stats['active'] }}</div>
                <div class="mt-1 text-sm opacity-70">Active Students</div>
            </div>

            <div class="p-6 shadow-lg rounded-xl bg-base-100">
                <div class="text-4xl font-bold text-error">{{ $this->stats['inactive'] }}</div>
                <div class="mt-1 text-sm opacity-70">Inactive Students</div>
            </div>

            <div class="p-6 shadow-lg rounded-xl bg-base-100">
                <div class="text-4xl font-bold text-primary">{{ $this->stats['new_this_month'] }}</div>
                <div class="mt-1 text-sm opacity-70">New This Month</div>
            </div>

            <div class="p-6 shadow-lg rounded-xl bg-base-100">
                <div class="text-4xl font-bold text-info">{{ $this->stats['avg_sessions'] }}</div>
                <div class="mt-1 text-sm opacity-70">Avg. Sessions</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="p-4 mb-6 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search students by name or email..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- Course Filter -->
                <div>
                    <select wire:model.live="courseFilter" class="w-full select select-bordered">
                        <option value="">All Courses</option>
                        @foreach($courses as $course)
                            <option value="{{ $course['id'] }}">{{ $course['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <select wire:model.live="statusFilter" class="w-full select select-bordered">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="shadow-xl rounded-xl bg-base-100">
            @if(count($this->students) > 0)
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th class="w-10">
                                    <input
                                        type="checkbox"
                                        class="checkbox"
                                        @if(count($selectedStudentIds) === count($this->students)) checked @endif
                                        wire:click="$toggle('selectedStudentIds', collect($this->students)->pluck('id')->toArray())"
                                    />
                                </th>
                                <th class="cursor-pointer" wire:click="sortBy('name')">
                                    <div class="flex items-center">
                                        Student
                                        @if($sortField === 'name')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th>Courses</th>
                                <th class="cursor-pointer" wire:click="sortBy('enrolled_at')">
                                    <div class="flex items-center">
                                        Enrolled
                                        @if($sortField === 'enrolled_at')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th class="cursor-pointer" wire:click="sortBy('progress')">
                                    <div class="flex items-center">
                                        Progress
                                        @if($sortField === 'progress')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th class="cursor-pointer" wire:click="sortBy('status')">
                                    <div class="flex items-center">
                                        Status
                                        @if($sortField === 'status')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th class="cursor-pointer" wire:click="sortBy('last_session_at')">
                                    <div class="flex items-center">
                                        Last Session
                                        @if($sortField === 'last_session_at')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->students as $student)
                                <tr>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="checkbox"
                                            @if($this->isStudentSelected($student['id'])) checked @endif
                                            wire:click="toggleStudentSelection({{ $student['id'] }})"
                                        />
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar placeholder">
                                                <div class="w-10 h-10 rounded-full bg-neutral-focus text-neutral-content">
                                                    <span>{{ substr($student['name'], 0, 1) }}</span>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-bold">{{ $student['name'] }}</div>
                                                <div class="text-sm opacity-70">{{ $student['email'] }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($student['enrolled_courses'] as $index => $course)
                                                @if($index < 2)
                                                    <div class="badge badge-outline">{{ $course }}</div>
                                                @elseif($index == 2)
                                                    <div class="badge badge-outline">+{{ count($student['enrolled_courses']) - 2 }} more</div>
                                                    @break
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $this->formatDate($student['enrolled_at']) }}</div>
                                        <div class="text-xs opacity-70">{{ $this->getRelativeTime($student['enrolled_at']) }}</div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <progress class="w-16 progress" value="{{ $student['progress'] }}" max="100"></progress>
                                            <span>{{ $student['progress'] }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="badge {{ $this->getStatusBadgeClass($student['status']) }}">
                                            {{ ucfirst($student['status']) }}
                                        </div>
                                    </td>
                                    <td>
                                        @if($student['last_session_at'])
                                            <div>{{ $this->formatDate($student['last_session_at']) }}</div>
                                            <div class="text-xs opacity-70">{{ $this->getRelativeTime($student['last_session_at']) }}</div>
                                        @else
                                            <span class="text-base-content/50">No sessions yet</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <button
                                                wire:click="viewStudent({{ $student['id'] }})"
                                                class="btn btn-sm btn-ghost"
                                            >
                                                <x-icon name="o-eye" class="w-4 h-4" />
                                            </button>
                                            <button
                                                wire:click="openMessageModal({{ $student['id'] }})"
                                                class="btn btn-sm btn-ghost"
                                            >
                                                <x-icon name="o-envelope" class="w-4 h-4" />
                                                </button>
                                            <a
                                                href="{{ route('teachers.students.show', $student['id']) }}"
                                                class="btn btn-sm btn-ghost"
                                            >
                                                <x-icon name="o-chart-bar" class="w-4 h-4" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Bulk Actions -->
                @if(count($selectedStudentIds) > 0)
                    <div class="p-4 bg-base-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-semibold">{{ count($selectedStudentIds) }}</span> students selected
                            </div>
                            <div class="flex gap-2">
                                <button
                                    wire:click="deselectAllStudents"
                                    class="btn btn-sm btn-ghost"
                                >
                                    Deselect All
                                </button>
                                <button
                                    wire:click="openMessageModal()"
                                    class="btn btn-sm btn-primary"
                                >
                                    <x-icon name="o-envelope" class="w-4 h-4 mr-1" />
                                    Message Selected
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <div class="p-8 text-center">
                    <div class="max-w-md mx-auto">
                        <x-icon name="o-users" class="w-16 h-16 mx-auto mb-4 text-base-content/30" />
                        <h3 class="text-lg font-semibold">No students found</h3>
                        <p class="mt-1 text-base-content/70">
                            @if($search || $courseFilter || $statusFilter)
                                Try adjusting your search or filters to find what you're looking for.
                            @else
                                You don't have any students assigned to you yet.
                            @endif
                        </p>

                        @if($search || $courseFilter || $statusFilter)
                            <button
                                wire:click="$set('search', ''); $set('courseFilter', ''); $set('statusFilter', '');"
                                class="mt-4 btn btn-outline"
                            >
                                Clear Filters
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Student Details Modal -->
    @if($showStudentModal && $selectedStudent)
        <div class="modal modal-open">
            <div class="max-w-4xl modal-box">
                <button wire:click="closeStudentModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

                <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                    <!-- Left Column: Student Info -->
                    <div class="md:col-span-1">
                        <div class="flex flex-col items-center mb-6 text-center">
                            <div class="avatar placeholder">
                                <div class="w-24 h-24 rounded-full bg-neutral-focus text-neutral-content">
                                    <span class="text-3xl">{{ substr($selectedStudent['name'], 0, 1) }}</span>
                                </div>
                            </div>
                            <h3 class="mt-3 text-xl font-bold">{{ $selectedStudent['name'] }}</h3>
                            <p class="text-sm opacity-70">{{ $selectedStudent['email'] }}</p>
                            <div class="mt-2 badge {{ $this->getStatusBadgeClass($selectedStudent['status']) }}">
                                {{ ucfirst($selectedStudent['status']) }}
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="p-4 rounded-lg bg-base-200">
                                <h4 class="font-semibold">Stats Overview</h4>
                                <div class="mt-3 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Total Sessions</span>
                                        <span class="font-medium">{{ $this->studentStats['total_sessions'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Attendance Rate</span>
                                        <span class="font-medium">{{ $this->studentStats['attendance_rate'] }}%</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Completed Courses</span>
                                        <span class="font-medium">{{ $this->studentStats['completed_courses'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm">Average Progress</span>
                                        <span class="font-medium">{{ $this->studentStats['avg_progress'] }}%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 rounded-lg bg-base-200">
                                <h4 class="font-semibold">Enrolled Since</h4>
                                <div class="mt-2">
                                    <p>{{ $this->formatDate($selectedStudent['enrolled_at']) }}</p>
                                    <p class="text-sm opacity-70">{{ $this->getRelativeTime($selectedStudent['enrolled_at']) }}</p>
                                </div>
                            </div>

                            <div class="p-4 rounded-lg bg-base-200">
                                <h4 class="font-semibold">Quick Actions</h4>
                                <div class="grid grid-cols-2 gap-2 mt-2">
                                    <button
                                        wire:click="openMessageModal({{ $selectedStudent['id'] }})"
                                        class="btn btn-sm btn-outline"
                                    >
                                        <x-icon name="o-envelope" class="w-4 h-4 mr-1" />
                                        Message
                                    </button>
                                    <a
                                        href="{{ route('teachers.students.show', $selectedStudent['id']) }}"
                                        class="btn btn-sm btn-outline"
                                    >
                                        <x-icon name="o-chart-bar" class="w-4 h-4 mr-1" />
                                        Analytics
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Tabs -->
                    <div class="md:col-span-2">
                        <div role="tablist" class="tabs tabs-bordered">
                            <a role="tab" class="tab tab-active">Enrollments</a>
                            <a role="tab" class="tab">Sessions</a>
                        </div>

                        <div class="p-4">
                            <!-- Enrollments Tab -->
                            <div>
                                <h4 class="mb-4 text-lg font-semibold">Enrolled Courses</h4>

                                @if(count($studentEnrollments) > 0)
                                    <div class="space-y-4">
                                        @foreach($studentEnrollments as $enrollment)
                                            <div class="p-4 border rounded-lg border-base-300">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <h5 class="font-semibold">{{ $enrollment['course_name'] }}</h5>
                                                        <p class="mt-1 text-sm">
                                                            Enrolled: {{ $this->formatDate($enrollment['enrollment_date']) }}
                                                        </p>
                                                    </div>
                                                    <div class="badge {{ $enrollment['status'] === 'completed' ? 'badge-success' : 'badge-info' }}">
                                                        {{ ucfirst($enrollment['status']) }}
                                                    </div>
                                                </div>

                                                <div class="mt-4">
                                                    <div class="flex items-center justify-between mb-1 text-sm">
                                                        <span>Progress</span>
                                                        <span>{{ $enrollment['progress'] }}%</span>
                                                    </div>
                                                    <progress class="w-full progress progress-primary" value="{{ $enrollment['progress'] }}" max="100"></progress>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="p-6 text-center bg-base-200 rounded-xl">
                                        <p>No course enrollments found for this student.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Message Modal -->
    @if($showMessageModal)
        <div class="modal modal-open">
            <div class="modal-box">
                <button wire:click="closeMessageModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

                <h3 class="text-lg font-bold">
                    @if($bulkMessage)
                        Message to Multiple Students
                    @else
                        {{ $messageSubject }}
                    @endif
                </h3>

                <form wire:submit.prevent="sendMessage" class="mt-4">
                    <div class="space-y-4">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Subject</span>
                            </label>
                            <input
                                type="text"
                                wire:model="messageSubject"
                                class="input input-bordered @error('messageSubject') input-error @enderror"
                                placeholder="Message subject"
                            />
                            @error('messageSubject') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Message</span>
                            </label>
                            <textarea
                                wire:model="messageContent"
                                class="h-32 textarea textarea-bordered @error('messageContent') textarea-error @enderror"
                                placeholder="Type your message here..."
                            ></textarea>
                            @error('messageContent') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        @if($bulkMessage)
                            <div class="p-3 rounded-lg bg-base-200">
                                <span class="text-sm">Recipients: <strong>{{ count($selectedStudentIds) }}</strong> students</span>
                            </div>
                        @endif
                    </div>

                    <div class="modal-action">
                        <button
                            type="button"
                            wire:click="closeMessageModal"
                            class="btn btn-ghost"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <x-icon name="o-paper-airplane" class="w-4 h-4 mr-2" />
                            Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
