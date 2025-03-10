<?php

namespace App\Livewire\Parents\Schedule;

use Livewire\Volt\Component;
use App\Models\LearningSession;
use App\Models\Children;
use App\Models\User;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    // User and profile data
    public $user;
    public $parentProfile;

    // Calendar view settings
    public $viewMode = 'week'; // Can be 'day', 'week', 'month'
    public $currentDate;
    public $calendarStartDate;
    public $calendarEndDate;
    public $calendarDays = [];
    public $timeSlots = [];
    public $daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    // Sessions and filtered sessions
    public $allSessions = [];
    public $sessions = [];

    // Filter states
    public $childFilter = '';
    public $subjectFilter = '';
    public $statusFilter = '';
    public $searchQuery = '';
    public $dateRangeStart = '';
    public $dateRangeEnd = '';

    // Selection states
    public $selectedSession = null;
    public $selectedSlot = null;

    // Modal states
    public $showSessionDetailsModal = false;
    public $showRequestSessionModal = false;
    public $showCancelSessionModal = false;

    // Form data for session request
    public $sessionRequestData = [
        'children_id' => '',
        'subject_id' => '',
        'teacher_id' => '',
        'date' => '',
        'start_time' => '',
        'end_time' => '',
        'notes' => '',
        'location' => 'online', // 'online' or 'in-person'
    ];

    // Available options
    public $availableChildren = [];
    public $availableSubjects = [];
    public $availableTeachers = [];

    public function mount()
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        if (!$this->parentProfile) {
            return redirect()->route('parents.profile-setup');
        }

        // Initialize the current date and calendar range
        $this->currentDate = Carbon::today();
        $this->initializeCalendar();

        // Load data
        $this->loadSessions();
        $this->loadChildren();
        $this->loadSubjects();
        $this->loadTeachers();

        // Initialize date ranges for filtering
        $this->dateRangeStart = $this->calendarStartDate->copy()->format('Y-m-d');
        $this->dateRangeEnd = $this->calendarEndDate->copy()->format('Y-m-d');
    }

    private function initializeCalendar()
    {
        $this->calendarDays = [];
        $this->timeSlots = $this->generateTimeSlots('08:00', '20:00', 60); // 8 AM to 8 PM in 1-hour slots

        if ($this->viewMode === 'day') {
            $this->calendarStartDate = $this->currentDate->copy()->startOfDay();
            $this->calendarEndDate = $this->currentDate->copy()->endOfDay();
            $this->calendarDays[] = $this->calendarStartDate->copy();
        } elseif ($this->viewMode === 'week') {
            $this->calendarStartDate = $this->currentDate->copy()->startOfWeek();
            $this->calendarEndDate = $this->currentDate->copy()->endOfWeek();

            $day = $this->calendarStartDate->copy();
            while ($day->lte($this->calendarEndDate)) {
                $this->calendarDays[] = $day->copy();
                $day->addDay();
            }
        } elseif ($this->viewMode === 'month') {
            $this->calendarStartDate = $this->currentDate->copy()->startOfMonth()->startOfWeek();
            $this->calendarEndDate = $this->currentDate->copy()->endOfMonth()->endOfWeek();

            $day = $this->calendarStartDate->copy();
            while ($day->lte($this->calendarEndDate)) {
                $this->calendarDays[] = $day->copy();
                $day->addDay();
            }
        }
    }

    private function generateTimeSlots($start, $end, $intervalMinutes = 30)
    {
        $slots = [];
        $startTime = Carbon::createFromFormat('H:i', $start);
        $endTime = Carbon::createFromFormat('H:i', $end);

        while ($startTime->lt($endTime)) {
            $slots[] = [
                'start' => $startTime->format('H:i'),
                'end' => $startTime->copy()->addMinutes($intervalMinutes)->format('H:i'),
                'label' => $startTime->format('g:i A')
            ];
            $startTime->addMinutes($intervalMinutes);
        }

        return $slots;
    }

    private function loadSessions()
    {
        // Get all the children IDs that belong to this parent
        $childrenIds = $this->parentProfile->children()->pluck('id')->toArray();

        // Load sessions for all of these children
        $this->allSessions = LearningSession::whereIn('children_id', $childrenIds)
            ->with(['teacher', 'children', 'subject'])
            ->where('start_time', '>=', $this->calendarStartDate)
            ->where('start_time', '<=', $this->calendarEndDate->copy()->addDay())
            ->orderBy('start_time')
            ->get()
            ->toArray();

        $this->filterSessions();
    }

    private function loadChildren()
    {
        $this->availableChildren = $this->parentProfile->children()
            ->with('subjects')
            ->get()
            ->toArray();
    }

    private function loadSubjects()
    {
        $this->availableSubjects = Subject::all()->toArray();
    }

    private function loadTeachers()
    {
        // Get all teachers with the teacher role who have a teacher profile
        $this->availableTeachers = User::where('role', 'teacher')
            ->with('teacherProfile.subjects')
            ->get()
            ->map(function($teacher) {
                $subjects = $teacher->teacherProfile ? $teacher->teacherProfile->subjects->pluck('name')->join(', ') : '';
                return [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'subjects' => $subjects
                ];
            })
            ->toArray();
    }

    public function filterSessions()
    {
        $filtered = collect($this->allSessions);

        // Apply child filter
        if (!empty($this->childFilter)) {
            $filtered = $filtered->filter(function($session) {
                return $session['children_id'] == $this->childFilter;
            });
        }

        // Apply subject filter
        if (!empty($this->subjectFilter)) {
            $filtered = $filtered->filter(function($session) {
                return $session['subject_id'] == $this->subjectFilter;
            });
        }

        // Apply status filter
        if (!empty($this->statusFilter)) {
            $filtered = $filtered->filter(function($session) {
                return $session['status'] == $this->statusFilter;
            });
        }

        // Apply search query (could search in child name, teacher name, subject, notes, etc.)
        if (!empty($this->searchQuery)) {
            $query = strtolower($this->searchQuery);
            $filtered = $filtered->filter(function($session) use ($query) {
                return str_contains(strtolower($session['children']['name'] ?? ''), $query) ||
                       str_contains(strtolower($session['teacher']['name'] ?? ''), $query) ||
                       str_contains(strtolower($session['subject']['name'] ?? ''), $query) ||
                       str_contains(strtolower($session['notes'] ?? ''), $query);
            });
        }

        // Apply date range filter
        if (!empty($this->dateRangeStart) && !empty($this->dateRangeEnd)) {
            $startDate = Carbon::parse($this->dateRangeStart)->startOfDay();
            $endDate = Carbon::parse($this->dateRangeEnd)->endOfDay();

            $filtered = $filtered->filter(function($session) use ($startDate, $endDate) {
                $sessionStart = Carbon::parse($session['start_time']);
                return $sessionStart->gte($startDate) && $sessionStart->lte($endDate);
            });
        }

        $this->sessions = $filtered->values()->toArray();
    }

    public function updatedChildFilter()
    {
        $this->filterSessions();
    }

    public function updatedSubjectFilter()
    {
        $this->filterSessions();
    }

    public function updatedStatusFilter()
    {
        $this->filterSessions();
    }

    public function updatedSearchQuery()
    {
        $this->filterSessions();
    }

    public function updatedDateRangeStart()
    {
        $this->filterSessions();
    }

    public function updatedDateRangeEnd()
    {
        $this->filterSessions();
    }

    public function getSessionsForDayAndTime($day, $timeSlot)
    {
        $dayStart = $day->copy()->setTimeFromTimeString($timeSlot['start']);
        $dayEnd = $day->copy()->setTimeFromTimeString($timeSlot['end']);

        return collect($this->sessions)->filter(function($session) use ($dayStart, $dayEnd) {
            $sessionStart = Carbon::parse($session['start_time']);
            $sessionEnd = Carbon::parse($session['end_time']);

            // Check if the session overlaps with this time slot
            return $sessionStart->lt($dayEnd) && $sessionEnd->gt($dayStart);
        })->values()->toArray();
    }

    public function getSessionStatusClass($status)
    {
        return match($status) {
            'scheduled' => 'bg-blue-100 text-blue-800 border-blue-300',
            'completed' => 'bg-green-100 text-green-800 border-green-300',
            'cancelled' => 'bg-red-100 text-red-800 border-red-300',
            default => 'bg-gray-100 text-gray-800 border-gray-300'
        };
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
        $this->initializeCalendar();
        $this->loadSessions();
    }

    public function previousPeriod()
    {
        if ($this->viewMode === 'day') {
            $this->currentDate->subDay();
        } elseif ($this->viewMode === 'week') {
            $this->currentDate->subWeek();
        } elseif ($this->viewMode === 'month') {
            $this->currentDate->subMonth();
        }

        $this->initializeCalendar();
        $this->loadSessions();
    }

    public function nextPeriod()
    {
        if ($this->viewMode === 'day') {
            $this->currentDate->addDay();
        } elseif ($this->viewMode === 'week') {
            $this->currentDate->addWeek();
        } elseif ($this->viewMode === 'month') {
            $this->currentDate->addMonth();
        }

        $this->initializeCalendar();
        $this->loadSessions();
    }

    public function today()
    {
        $this->currentDate = Carbon::today();
        $this->initializeCalendar();
        $this->loadSessions();
    }

    public function selectSession($sessionId)
    {
        $this->selectedSession = collect($this->sessions)->firstWhere('id', $sessionId);
        $this->showSessionDetailsModal = true;
    }

    public function selectSlot($day, $timeSlot)
    {
        $dayObj = Carbon::parse($day);
        $this->selectedSlot = [
            'day' => $dayObj->format('Y-m-d'),
            'day_formatted' => $dayObj->format('l, F j, Y'),
            'start_time' => $timeSlot['start'],
            'end_time' => $timeSlot['end'],
            'start_formatted' => Carbon::createFromFormat('H:i', $timeSlot['start'])->format('g:i A'),
            'end_formatted' => Carbon::createFromFormat('H:i', $timeSlot['end'])->format('g:i A'),
        ];

        // Pre-fill session request form
        $this->sessionRequestData = [
            'children_id' => $this->childFilter ?: '',
            'subject_id' => $this->subjectFilter ?: '',
            'teacher_id' => '',
            'date' => $dayObj->format('Y-m-d'),
            'start_time' => $timeSlot['start'],
            'end_time' => $timeSlot['end'],
            'notes' => '',
            'location' => 'online',
        ];

        $this->showRequestSessionModal = true;
    }

    public function closeSessionDetailsModal()
    {
        $this->showSessionDetailsModal = false;
        $this->selectedSession = null;
    }

    public function closeRequestSessionModal()
    {
        $this->showRequestSessionModal = false;
        $this->selectedSlot = null;
    }

    public function requestCancellation($sessionId)
    {
        $this->selectedSession = collect($this->sessions)->firstWhere('id', $sessionId);
        $this->showCancelSessionModal = true;
    }

    public function closeCancelSessionModal()
    {
        $this->showCancelSessionModal = false;
    }

    public function confirmCancelSession()
    {
        // In a real application, you would update the session status to 'cancelled'
        // and potentially notify the teacher/admin

        // For now, we'll just close the modal and display a success message
        $this->closeCancelSessionModal();
        session()->flash('message', 'Session cancellation requested successfully. Waiting for approval.');
    }

    public function submitSessionRequest()
    {
        $this->validate([
            'sessionRequestData.children_id' => 'required',
            'sessionRequestData.subject_id' => 'required',
            'sessionRequestData.teacher_id' => 'required',
            'sessionRequestData.date' => 'required|date',
            'sessionRequestData.start_time' => 'required',
            'sessionRequestData.end_time' => 'required',
        ]);

        // In a real application, you would create a new session request
        // For now, we'll just close the modal and display a success message
        $this->closeRequestSessionModal();
        session()->flash('message', 'Session request submitted successfully.');
    }

    public function getSessionDuration($session)
    {
        $start = Carbon::parse($session['start_time']);
        $end = Carbon::parse($session['end_time']);

        return $start->diffInMinutes($end) . ' min';
    }

    public function getFormattedTimeRange($session)
    {
        $start = Carbon::parse($session['start_time']);
        $end = Carbon::parse($session['end_time']);

        return $start->format('g:i A') . ' - ' . $end->format('g:i A');
    }

    public function getFormattedDate($session)
    {
        return Carbon::parse($session['start_time'])->format('l, F j, Y');
    }

    public function getIsToday($day)
    {
        return $day->isToday();
    }

    public function getIsPast($day)
    {
        return $day->isPast();
    }

    public function getIsCurrentMonth($day)
    {
        return $day->month === $this->currentDate->month;
    }

    public function getStatusOptions()
    {
        return [
            '' => 'All Statuses',
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Learning Schedule</h1>
                <p class="mt-1 text-base-content/70">Manage and view your children's learning sessions</p>
            </div>
            <a href="{{ route('parents.sessions.requests') }}" class="btn btn-primary">
                <x-icon name="o-calendar-plus" class="w-5 h-5 mr-2" />
                Request New Session
            </a>
        </div>

        @if(session()->has('message'))
            <div class="mb-6 alert alert-success">
                <x-icon name="o-check-circle" class="w-6 h-6" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        <!-- Calendar Controls -->
        <div class="p-4 mb-6 shadow-lg bg-base-100 rounded-xl">
            <div class="flex flex-col justify-between gap-4 lg:flex-row">
                <!-- View Mode & Navigation -->
                <div class="flex flex-col gap-3 md:flex-row">
                    <div class="join">
                        <button
                            wire:click="setViewMode('day')"
                            class="join-item btn {{ $viewMode === 'day' ? 'btn-primary' : 'btn-ghost' }}"
                        >
                            Day
                        </button>
                        <button
                            wire:click="setViewMode('week')"
                            class="join-item btn {{ $viewMode === 'week' ? 'btn-primary' : 'btn-ghost' }}"
                        >
                            Week
                        </button>
                        <button
                            wire:click="setViewMode('month')"
                            class="join-item btn {{ $viewMode === 'month' ? 'btn-primary' : 'btn-ghost' }}"
                        >
                            Month
                        </button>
                    </div>

                    <div class="join">
                        <button wire:click="previousPeriod" class="join-item btn btn-outline">
                            <x-icon name="o-chevron-left" class="w-5 h-5" />
                        </button>
                        <button wire:click="today" class="join-item btn btn-outline">Today</button>
                        <button wire:click="nextPeriod" class="join-item btn btn-outline">
                            <x-icon name="o-chevron-right" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="flex items-center text-lg font-medium">
                        @if($viewMode === 'day')
                            {{ $currentDate->format('F j, Y') }}
                        @elseif($viewMode === 'week')
                            {{ $calendarStartDate->format('M j') }} - {{ $calendarEndDate->format('M j, Y') }}
                        @else
                            {{ $currentDate->format('F Y') }}
                        @endif
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex flex-col gap-3 md:flex-row">
                    <select wire:model.live="childFilter" class="select select-bordered">
                        <option value="">All Children</option>
                        @foreach($availableChildren as $child)
                            <option value="{{ $child['id'] }}">{{ $child['name'] }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="subjectFilter" class="select select-bordered">
                        <option value="">All Subjects</option>
                        @foreach($availableSubjects as $subject)
                            <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="statusFilter" class="select select-bordered">
                        @foreach($this->getStatusOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Calendar View -->
        <div class="mb-6 shadow-lg bg-base-100 rounded-xl">
            <!-- Day/Week View -->
            @if($viewMode === 'day' || $viewMode === 'week')
                <div class="overflow-x-auto">
                    <table class="table w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 w-24 p-2 border-r bg-base-200 border-base-300">Time</th>
                                @foreach($calendarDays as $day)
                                    <th class="border-b border-base-300 p-2 min-w-[180px] {{ $this->getIsToday($day) ? 'bg-primary/10' : '' }} {{ !$this->getIsCurrentMonth($day) ? 'text-base-content/50' : '' }}">
                                        <div class="font-medium">{{ $day->format('D') }}</div>
                                        <div class="{{ $this->getIsToday($day) ? 'bg-primary text-primary-content rounded-full w-8 h-8 flex items-center justify-center mx-auto my-1' : '' }}">
                                            {{ $day->format('j') }}
                                        </div>
                                        <div class="text-xs">{{ $day->format('M Y') }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($timeSlots as $timeSlot)
                                <tr class="border-t border-base-300 hover:bg-base-200/50">
                                    <td class="sticky left-0 z-10 p-2 text-sm border-r border-base-300 bg-base-100">
                                        {{ $timeSlot['label'] }}
                                    </td>
                                    @foreach($calendarDays as $day)
                                        <td
                                            class="border border-base-300 p-1 relative {{ $this->getIsToday($day) ? 'bg-primary/5' : '' }} {{ $this->getIsPast($day) ? 'bg-base-200/50' : '' }}"
                                            wire:click="selectSlot('{{ $day->format('Y-m-d') }}', {{ json_encode($timeSlot) }})"
                                        >
                                            @php
                                                $sessionsInSlot = $this->getSessionsForDayAndTime($day, $timeSlot);
                                            @endphp

                                            @foreach($sessionsInSlot as $session)
                                                <div
                                                    wire:click.stop="selectSession({{ $session['id'] }})"
                                                    class="mb-1 p-1 rounded text-xs border cursor-pointer hover:shadow-md transition-shadow {{ $this->getSessionStatusClass($session['status']) }}"
                                                >
                                                    <div class="font-medium">{{ $session['subject']['name'] ?? 'Subject' }}</div>
                                                    <div>{{ $session['children']['name'] ?? 'Child' }}</div>
                                                    <div>{{ $this->getFormattedTimeRange($session) }}</div>
                                                </div>
                                            @endforeach
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Month View -->
            @if($viewMode === 'month')
                <div class="grid grid-cols-7 gap-1 p-4">
                    <!-- Days of Week Headers -->
                    @foreach($daysOfWeek as $dayName)
                        <div class="p-2 font-medium text-center">
                            {{ substr($dayName, 0, 3) }}
                        </div>
                    @endforeach

                    <!-- Calendar Days -->
                    @foreach($calendarDays as $day)
                        <div
                            class="min-h-[100px] border border-base-300 p-2 overflow-hidden {{ $this->getIsToday($day) ? 'bg-primary/10' : '' }} {{ !$this->getIsCurrentMonth($day) ? 'bg-base-200 text-base-content/50' : '' }}"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="{{ $this->getIsToday($day) ? 'bg-primary text-primary-content rounded-full w-7 h-7 flex items-center justify-center' : '' }}">
                                    {{ $day->format('j') }}
                                </span>
                                @if($this->getIsCurrentMonth($day))
                                    <button
                                        wire:click="selectSlot('{{ $day->format('Y-m-d') }}', {{ json_encode(['start' => '09:00', 'end' => '10:00']) }})"
                                        class="btn btn-xs btn-ghost"
                                    >
                                        <x-icon name="o-plus" class="w-3 h-3" />
                                    </button>
                                @endif
                            </div>

                            @php
                                $dayStart = $day->copy()->startOfDay();
                                $dayEnd = $day->copy()->endOfDay();
                                $daySessions = collect($sessions)->filter(function($session) use ($dayStart, $dayEnd) {
                                    $sessionStart = Carbon::parse($session['start_time']);
                                    return $sessionStart->gte($dayStart) && $sessionStart->lte($dayEnd);
                                })->take(3);
                                $moreCount = collect($sessions)->filter(function($session) use ($dayStart, $dayEnd) {
                                    $sessionStart = Carbon::parse($session['start_time']);
                                    return $sessionStart->gte($dayStart) && $sessionStart->lte($dayEnd);
                                })->count() - 3;
                            @endphp

                            @foreach($daySessions as $session)
                                <div
                                    wire:click.stop="selectSession({{ $session['id'] }})"
                                    class="mb-1 p-1 rounded text-xs border cursor-pointer hover:shadow-md transition-shadow truncate {{ $this->getSessionStatusClass($session['status']) }}"
                                >
                                    <div class="font-medium truncate">{{ $session['subject']['name'] ?? 'Subject' }}</div>
                                    <div class="truncate">{{ $session['children']['name'] ?? 'Child' }}</div>
                                    <div class="truncate">{{ Carbon::parse($session['start_time'])->format('g:i A') }}</div>
                                </div>
                            @endforeach

                            @if($moreCount > 0)
                                <div class="mt-1 text-xs text-center cursor-pointer text-primary" wire:click="setViewMode('day'); $set('currentDate', '{{ $day->format('Y-m-d') }}')">
                                    +{{ $moreCount }} more
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Upcoming Sessions List -->
        <div class="p-4 mb-6 shadow-lg bg-base-100 rounded-xl">
            <h2 class="mb-4 text-xl font-bold">Upcoming Sessions</h2>

            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        placeholder="Search sessions..."
                        wire:model.live.debounce.300ms="searchQuery"
                        class="w-full max-w-xs input input-bordered"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <div class="items-center hidden gap-2 sm:flex">
                        <input
                            type="date"
                            wire:model.live="dateRangeStart"
                            class="input input-bordered input-sm"
                        />
                        <span>to</span>
                        <input
                            type="date"
                            wire:model.live="dateRangeEnd"
                            class="input input-bordered input-sm"
                        />
                    </div>
                </div>
            </div>

            @if(count($sessions) > 0)
                <div class="overflow-x-auto">
                    <table class="table w-full table-zebra">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Child</th>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $session)
                                <tr class="{{ $session['status'] === 'cancelled' ? 'opacity-60' : '' }}">
                                    <td>
                                        <div class="font-medium">{{ Carbon::parse($session['start_time'])->format('M j, Y') }}</div>
                                        <div class="text-sm opacity-70">{{ $this->getFormattedTimeRange($session) }}</div>
                                        <div class="text-xs opacity-70">{{ $this->getSessionDuration($session) }}</div>
                                    </td>
                                    <td>{{ $session['children']['name'] ?? 'Unknown Child' }}</td>
                                    <td>{{ $session['subject']['name'] ?? 'Unknown Subject' }}</td>
                                    <td>{{ $session['teacher']['name'] ?? 'Unknown Teacher' }}</td>
                                    <td>
                                    <div class="badge {{
                                            $session['status'] === 'scheduled' ? 'badge-primary' :
                                            ($session['status'] === 'completed' ? 'badge-success' :
                                            ($session['status'] === 'cancelled' ? 'badge-error' : 'badge-ghost'))
                                        }}">
                                            {{ ucfirst($session['status'] ?? 'Unknown') }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-1">
                                            <button
                                                wire:click="selectSession({{ $session['id'] }})"
                                                class="btn btn-ghost btn-xs"
                                            >
                                                <x-icon name="o-eye" class="w-4 h-4" />
                                            </button>

                                            @if($session['status'] === 'scheduled')
                                                <button
                                                    wire:click="requestCancellation({{ $session['id'] }})"
                                                    class="btn btn-ghost btn-xs text-error"
                                                    title="Cancel Session"
                                                >
                                                    <x-icon name="o-x-mark" class="w-4 h-4" />
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center">
                    <x-icon name="o-calendar" class="w-12 h-12 mx-auto mb-4 text-base-content/30" />
                    <h3 class="text-lg font-medium">No sessions found</h3>
                    <p class="mt-1 opacity-70">No sessions match your current filters or date range.</p>

                    <button
                        wire:click="$set('childFilter', ''); $set('subjectFilter', ''); $set('statusFilter', ''); $set('searchQuery', '')"
                        class="mt-4 btn btn-outline"
                    >
                        Clear Filters
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Session Details Modal -->
    <div class="modal {{ $showSessionDetailsModal ? 'modal-open' : '' }}">
        <div class="max-w-2xl modal-box">
            @if($selectedSession)
                <div class="flex items-start justify-between">
                    <h3 class="text-lg font-bold">Session Details</h3>
                    <button wire:click="closeSessionDetailsModal" class="btn btn-sm btn-circle">
                        <x-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="divider"></div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <h4 class="mb-2 font-semibold">Session Information</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Subject:</span>
                                <span class="font-medium">{{ $selectedSession['subject']['name'] ?? 'Unknown Subject' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Date:</span>
                                <span>{{ $this->getFormattedDate($selectedSession) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Time:</span>
                                <span>{{ $this->getFormattedTimeRange($selectedSession) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Duration:</span>
                                <span>{{ $this->getSessionDuration($selectedSession) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Status:</span>
                                <span class="badge {{
                                    $selectedSession['status'] === 'scheduled' ? 'badge-primary' :
                                    ($selectedSession['status'] === 'completed' ? 'badge-success' :
                                    ($selectedSession['status'] === 'cancelled' ? 'badge-error' : 'badge-ghost'))
                                }}">
                                    {{ ucfirst($selectedSession['status'] ?? 'Unknown') }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Location:</span>
                                <span>{{ ucfirst($selectedSession['location'] ?? 'Online') }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 font-semibold">Participants</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Child:</span>
                                <span class="font-medium">{{ $selectedSession['children']['name'] ?? 'Unknown Child' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Teacher:</span>
                                <span>{{ $selectedSession['teacher']['name'] ?? 'Unknown Teacher' }}</span>
                            </div>
                        </div>

                        <h4 class="mt-6 mb-2 font-semibold">Notes</h4>
                        <div class="p-3 bg-base-200 rounded-lg min-h-[80px]">
                            {{ $selectedSession['notes'] ?? 'No notes provided for this session.' }}
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="flex justify-end gap-2">
                    @if($selectedSession['status'] === 'scheduled')
                        <button
                            wire:click="requestCancellation({{ $selectedSession['id'] }})"
                            class="btn btn-outline btn-error"
                        >
                            <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                            Cancel Session
                        </button>

                        @if($selectedSession['location'] === 'online')
                            <button class="btn btn-primary">
                                <x-icon name="o-video-camera" class="w-4 h-4 mr-2" />
                                Join Session
                            </button>
                        @endif
                    @endif

                    <button wire:click="closeSessionDetailsModal" class="btn">Close</button>
                </div>
            @endif
        </div>
        <div class="modal-backdrop" wire:click="closeSessionDetailsModal"></div>
    </div>

    <!-- Request Session Modal -->
    <div class="modal {{ $showRequestSessionModal ? 'modal-open' : '' }}">
        <div class="max-w-2xl modal-box">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-bold">Request New Session</h3>
                <button wire:click="closeRequestSessionModal" class="btn btn-sm btn-circle">
                    <x-icon name="o-x-mark" class="w-4 h-4" />
                </button>
            </div>

            <div class="divider"></div>

            @if($selectedSlot)
                <div class="p-3 mb-4 rounded-lg bg-base-200">
                    <div class="text-center">
                        <div class="text-lg font-medium">{{ $selectedSlot['day_formatted'] }}</div>
                        <div>{{ $selectedSlot['start_formatted'] }} - {{ $selectedSlot['end_formatted'] }}</div>
                    </div>
                </div>

                <form wire:submit.prevent="submitSessionRequest">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <!-- Select Child -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Child</span>
                                <span class="label-text-alt text-error">*</span>
                            </label>
                            <select
                                wire:model="sessionRequestData.children_id"
                                class="w-full select select-bordered"
                                required
                            >
                                <option value="">Select a child</option>
                                @foreach($availableChildren as $child)
                                    <option value="{{ $child['id'] }}">{{ $child['name'] }}</option>
                                @endforeach
                            </select>
                            @error('sessionRequestData.children_id')
                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Select Subject -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Subject</span>
                                <span class="label-text-alt text-error">*</span>
                            </label>
                            <select
                                wire:model="sessionRequestData.subject_id"
                                class="w-full select select-bordered"
                                required
                            >
                                <option value="">Select a subject</option>
                                @foreach($availableSubjects as $subject)
                                    <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                                @endforeach
                            </select>
                            @error('sessionRequestData.subject_id')
                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Select Teacher -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Teacher</span>
                                <span class="label-text-alt text-error">*</span>
                            </label>
                            <select
                                wire:model="sessionRequestData.teacher_id"
                                class="w-full select select-bordered"
                                required
                            >
                                <option value="">Select a teacher</option>
                                @foreach($availableTeachers as $teacher)
                                    <option value="{{ $teacher['id'] }}">{{ $teacher['name'] }} {{ $teacher['subjects'] ? "(".$teacher['subjects'].")" : "" }}</option>
                                @endforeach
                            </select>
                            @error('sessionRequestData.teacher_id')
                                <span class="mt-1 text-sm text-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Session Location -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Location</span>
                            </label>
                            <div class="flex gap-4">
                                <label class="gap-2 cursor-pointer label">
                                    <input
                                        type="radio"
                                        value="online"
                                        wire:model="sessionRequestData.location"
                                        class="radio radio-primary"
                                    />
                                    <span class="label-text">Online</span>
                                </label>
                                <label class="gap-2 cursor-pointer label">
                                    <input
                                        type="radio"
                                        value="in-person"
                                        wire:model="sessionRequestData.location"
                                        class="radio radio-primary"
                                    />
                                    <span class="label-text">In Person</span>
                                </label>
                            </div>
                        </div>

                        <!-- Session Notes -->
                        <div class="form-control md:col-span-2">
                            <label class="label">
                                <span class="label-text">Session Notes</span>
                            </label>
                            <textarea
                                wire:model="sessionRequestData.notes"
                                class="h-24 textarea textarea-bordered"
                                placeholder="Add any specific requirements, topics to cover, or other notes for the teacher"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <button type="button" wire:click="closeRequestSessionModal" class="btn">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <x-icon name="o-check" class="w-4 h-4 mr-2" />
                            Submit Request
                        </button>
                    </div>
                </form>
            @endif
        </div>
        <div class="modal-backdrop" wire:click="closeRequestSessionModal"></div>
    </div>

    <!-- Cancel Session Modal -->
    <div class="modal {{ $showCancelSessionModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold text-error">Cancel Session</h3>

            @if($selectedSession)
                <p class="py-4">Are you sure you want to cancel this session?</p>

                <div class="p-3 mb-4 rounded-lg bg-base-200">
                    <div><span class="font-medium">Subject:</span> {{ $selectedSession['subject']['name'] ?? 'Unknown Subject' }}</div>
                    <div><span class="font-medium">Date:</span> {{ $this->getFormattedDate($selectedSession) }}</div>
                    <div><span class="font-medium">Time:</span> {{ $this->getFormattedTimeRange($selectedSession) }}</div>
                    <div><span class="font-medium">Child:</span> {{ $selectedSession['children']['name'] ?? 'Unknown Child' }}</div>
                    <div><span class="font-medium">Teacher:</span> {{ $selectedSession['teacher']['name'] ?? 'Unknown Teacher' }}</div>
                </div>

                <div class="alert alert-warning">
                    <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
                    <span>Note: Cancellation policy may apply. Sessions cancelled less than 24 hours before start time may incur a fee.</span>
                </div>
            @endif

            <div class="modal-action">
                <button wire:click="closeCancelSessionModal" class="btn">Nevermind</button>
                <button wire:click="confirmCancelSession" class="btn btn-error">Cancel Session</button>
            </div>
        </div>
        <div class="modal-backdrop" wire:click="closeCancelSessionModal"></div>
    </div>
</div>
