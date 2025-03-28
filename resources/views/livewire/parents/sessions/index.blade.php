<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\LearningSession;
use App\Models\Children;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $children = [];
    public $selectedChild = null;
    public $selectedSubject = null;
    public $dateRange = 'upcoming';
    public $startDate = null;
    public $endDate = null;
    public $statusFilter = '';
    public $searchQuery = '';
    public $sortBy = 'date';
    public $sortDir = 'desc';
    public $viewMode = 'list'; // 'list', 'calendar', 'grid'

    // For modals
    public $showSessionDetailModal = false;
    public $selectedSession = null;
    public $showRescheduleModal = false;
    public $rescheduleDate = '';
    public $rescheduleTime = '';

    // For calendar view
    public $calendarMonth;
    public $calendarYear;
    public $events = [];

    protected $queryString = [
        'selectedChild' => ['except' => null],
        'selectedSubject' => ['except' => null],
        'dateRange' => ['except' => 'upcoming'],
        'statusFilter' => ['except' => ''],
        'viewMode' => ['except' => 'list'],
        'searchQuery' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount()
    {
        $this->user = Auth::user();

        // Get all children for this parent
        $this->children = $this->user->parentProfile->children()->get();

        // Set default selected child if available
        if ($this->children->count() > 0 && !$this->selectedChild) {
            $this->selectedChild = $this->children->first()->id;
        }

        // Set date range based on selection
        $this->updateDateRange();

        // Set current month and year for calendar
        $this->calendarMonth = Carbon::now()->month;
        $this->calendarYear = Carbon::now()->year;

        // Refresh calendar events
        if ($this->viewMode === 'calendar') {
            $this->refreshCalendarEvents();
        }
    }

    public function updated($field)
    {
        if (in_array($field, ['selectedChild', 'selectedSubject', 'dateRange', 'statusFilter', 'searchQuery', 'sortBy', 'sortDir'])) {
            $this->resetPage();
        }

        if ($field === 'dateRange') {
            $this->updateDateRange();
        }

        if ($field === 'viewMode' && $this->viewMode === 'calendar') {
            $this->refreshCalendarEvents();
        }
    }

    public function updateDateRange()
    {
        $today = Carbon::today();

        switch ($this->dateRange) {
            case 'upcoming':
                $this->startDate = $today->format('Y-m-d');
                $this->endDate = $today->copy()->addMonths(3)->format('Y-m-d');
                break;
            case 'past':
                $this->startDate = $today->copy()->subMonths(3)->format('Y-m-d');
                $this->endDate = $today->copy()->subDay()->format('Y-m-d');
                break;
            case 'today':
                $this->startDate = $today->format('Y-m-d');
                $this->endDate = $today->format('Y-m-d');
                break;
            case 'week':
                $this->startDate = $today->copy()->startOfWeek()->format('Y-m-d');
                $this->endDate = $today->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->startDate = $today->copy()->startOfMonth()->format('Y-m-d');
                $this->endDate = $today->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'all':
                $this->startDate = '2000-01-01';
                $this->endDate = '2100-12-31';
                break;
            case 'custom':
                // Custom date range is set by the user, so don't update it
                break;
            default:
                $this->startDate = $today->format('Y-m-d');
                $this->endDate = $today->copy()->addMonths(3)->format('Y-m-d');
        }

        if ($this->viewMode === 'calendar') {
            $this->refreshCalendarEvents();
        }
    }

    public function setCustomDateRange($start, $end)
    {
        $this->dateRange = 'custom';
        $this->startDate = $start;
        $this->endDate = $end;
        $this->resetPage();

        if ($this->viewMode === 'calendar') {
            $this->refreshCalendarEvents();
        }
    }

    public function changeViewMode($mode)
    {
        $this->viewMode = $mode;

        if ($this->viewMode === 'calendar') {
            $this->refreshCalendarEvents();
        }
    }

    public function nextMonth()
    {
        if ($this->calendarMonth == 12) {
            $this->calendarMonth = 1;
            $this->calendarYear++;
        } else {
            $this->calendarMonth++;
        }

        $this->refreshCalendarEvents();
    }

    public function prevMonth()
    {
        if ($this->calendarMonth == 1) {
            $this->calendarMonth = 12;
            $this->calendarYear--;
        } else {
            $this->calendarMonth--;
        }

        $this->refreshCalendarEvents();
    }

    public function refreshCalendarEvents()
    {
        if (!$this->selectedChild) {
            $this->events = [];
            return;
        }

        $startOfMonth = Carbon::createFromDate($this->calendarYear, $this->calendarMonth, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($this->calendarYear, $this->calendarMonth, 1)->endOfMonth();

        $query = LearningSession::where('children_id', $this->selectedChild)
            ->whereBetween('start_time', [$startOfMonth, $endOfMonth])
            ->with(['teacher', 'subject']);

        if ($this->selectedSubject) {
            $query->where('subject_id', $this->selectedSubject);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $sessions = $query->get();

        $this->events = $sessions->map(function($session) {
            return [
                'id' => $session->id,
                'title' => $session->subject->name,
                'start' => Carbon::parse($session->start_time)->format('Y-m-d H:i:s'),
                'end' => Carbon::parse($session->end_time)->format('Y-m-d H:i:s'),
                'teacher' => $session->teacher->name,
                'status' => $session->status,
                'color' => $this->getStatusColor($session->status),
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'location' => $session->location ?? 'Online',
                    'attended' => $session->attended ?? false,
                    'score' => $session->performance_score ?? null,
                ]
            ];
        })->toArray();
    }

    public function getStatusColor($status)
    {
        switch ($status) {
            case 'scheduled':
                return '#6419E6'; // Primary color
            case 'completed':
                return '#36D399'; // Success color
            case 'cancelled':
                return '#F87272'; // Error color
            default:
                return '#65C3C8'; // Info color
        }
    }

    public function viewSessionDetail($sessionId)
    {
        $this->selectedSession = LearningSession::with(['teacher', 'subject', 'course'])
            ->findOrFail($sessionId);
        $this->showSessionDetailModal = true;
    }

    public function closeSessionDetailModal()
    {
        $this->showSessionDetailModal = false;
        $this->selectedSession = null;
    }

    public function openRescheduleModal($sessionId)
    {
        $this->selectedSession = LearningSession::with(['teacher', 'subject'])
            ->findOrFail($sessionId);

        // Set default reschedule date and time
        $startTime = Carbon::parse($this->selectedSession->start_time);
        $this->rescheduleDate = $startTime->format('Y-m-d');
        $this->rescheduleTime = $startTime->format('H:i');

        $this->showRescheduleModal = true;
    }

    public function closeRescheduleModal()
    {
        $this->showRescheduleModal = false;
        $this->selectedSession = null;
        $this->rescheduleDate = '';
        $this->rescheduleTime = '';
    }

    public function rescheduleSession()
    {
        // Validate inputs
        $this->validate([
            'rescheduleDate' => 'required|date|after_or_equal:today',
            'rescheduleTime' => 'required',
        ]);

        if (!$this->selectedSession) {
            return;
        }

        try {
            // Calculate new start and end times
            $newStartTime = Carbon::parse($this->rescheduleDate . ' ' . $this->rescheduleTime);
            $duration = Carbon::parse($this->selectedSession->start_time)
                ->diffInMinutes(Carbon::parse($this->selectedSession->end_time));
            $newEndTime = $newStartTime->copy()->addMinutes($duration);

            // In a real application, you might want to check for conflicts here

            // Update the session
            $this->selectedSession->update([
                'start_time' => $newStartTime,
                'end_time' => $newEndTime,
                'status' => 'scheduled' // Reset status if it was cancelled
            ]);

            // Close the modal
            $this->closeRescheduleModal();

            // Show success toast
            $this->toast(
                type: 'success',
                title: 'Session Rescheduled',
                description: 'The session has been successfully rescheduled.',
                position: 'toast-bottom toast-end',
                icon: 'o-calendar',
                css: 'alert-success',
                timeout: 3000
            );

            // Refresh events for calendar view
            if ($this->viewMode === 'calendar') {
                $this->refreshCalendarEvents();
            }
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Rescheduling Failed',
                description: 'There was an error rescheduling the session. Please try again.',
                position: 'toast-bottom toast-end',
                icon: 'o-exclamation-triangle',
                css: 'alert-error',
                timeout: 3000
            );
        }
    }

    public function cancelSession($sessionId)
    {
        try {
            $session = LearningSession::findOrFail($sessionId);
            $session->update([
                'status' => 'cancelled'
            ]);

            // Show success toast
            $this->toast(
                type: 'success',
                title: 'Session Cancelled',
                description: 'The session has been successfully cancelled.',
                position: 'toast-bottom toast-end',
                icon: 'o-x-circle',
                css: 'alert-success',
                timeout: 3000
            );

            // Refresh events for calendar view
            if ($this->viewMode === 'calendar') {
                $this->refreshCalendarEvents();
            }
        } catch (\Exception $e) {
            $this->toast(
                type: 'error',
                title: 'Cancellation Failed',
                description: 'There was an error cancelling the session. Please try again.',
                position: 'toast-bottom toast-end',
                icon: 'o-exclamation-triangle',
                css: 'alert-error',
                timeout: 3000
            );
        }
    }

    public function confirmCancelSession($sessionId)
    {
        $this->toast(
            type: 'warning',
            title: 'Confirm Cancellation',
            description: 'Are you sure you want to cancel this session? This cannot be undone.',
            position: 'toast-bottom toast-end',
            icon: 'o-exclamation-triangle',
            css: 'alert-warning',
            timeout: false,
            action: [
                'label' => 'Yes, Cancel',
                'onClick' => "Livewire.dispatch('cancelSession', { sessionId: $sessionId })"
            ]
        );
    }

    public function getSessionsProperty()
    {
        if (!$this->selectedChild) {
            return collect();
        }

        $query = LearningSession::where('children_id', $this->selectedChild)
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ])
            ->with(['teacher', 'subject']);

        if ($this->selectedSubject) {
            $query->where('subject_id', $this->selectedSubject);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->searchQuery) {
            $query->where(function($q) {
                $q->whereHas('teacher', function($q) {
                    $q->where('name', 'like', '%' . $this->searchQuery . '%');
                })
                ->orWhereHas('subject', function($q) {
                    $q->where('name', 'like', '%' . $this->searchQuery . '%');
                })
                ->orWhere('location', 'like', '%' . $this->searchQuery . '%')
                ->orWhere('notes', 'like', '%' . $this->searchQuery . '%');
            });
        }

        // Apply sorting
        if ($this->sortBy === 'date') {
            $query->orderBy('start_time', $this->sortDir);
        } elseif ($this->sortBy === 'subject') {
            $query->join('subjects', 'learning_sessions.subject_id', '=', 'subjects.id')
                ->orderBy('subjects.name', $this->sortDir)
                ->select('learning_sessions.*');
        } elseif ($this->sortBy === 'teacher') {
            $query->join('users', 'learning_sessions.teacher_id', '=', 'users.id')
                ->orderBy('users.name', $this->sortDir)
                ->select('learning_sessions.*');
        } elseif ($this->sortBy === 'status') {
            $query->orderBy('status', $this->sortDir);
        }

        return $query->paginate(10);
    }

    public function getSelectedChildDataProperty()
    {
        if (!$this->selectedChild) {
            return null;
        }

        return $this->children->firstWhere('id', $this->selectedChild);
    }

    public function getSessionStatsProperty()
    {
        if (!$this->selectedChild) {
            return [
                'total' => 0,
                'upcoming' => 0,
                'completed' => 0,
                'cancelled' => 0
            ];
        }

        $query = LearningSession::where('children_id', $this->selectedChild)
            ->whereBetween('start_time', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ]);

        if ($this->selectedSubject) {
            $query->where('subject_id', $this->selectedSubject);
        }

        $total = $query->count();
        $upcoming = $query->where('status', 'scheduled')
            ->where('start_time', '>', now())
            ->count();
        $completed = $query->where('status', 'completed')->count();
        $cancelled = $query->where('status', 'cancelled')->count();

        return [
            'total' => $total,
            'upcoming' => $upcoming,
            'completed' => $completed,
            'cancelled' => $cancelled
        ];
    }

    public function getSubjectsProperty()
    {
        if (!$this->selectedChild) {
            return collect();
        }

        return Children::find($this->selectedChild)->subjects;
    }

    public function getNextSessionProperty()
    {
        if (!$this->selectedChild) {
            return null;
        }

        return LearningSession::where('children_id', $this->selectedChild)
            ->where('status', 'scheduled')
            ->where('start_time', '>', now())
            ->with(['teacher', 'subject'])
            ->orderBy('start_time', 'asc')
            ->first();
    }

    public function getCalendarDaysProperty()
    {
        $startOfMonth = Carbon::createFromDate($this->calendarYear, $this->calendarMonth, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($this->calendarYear, $this->calendarMonth, 1)->endOfMonth();

        // Get the weekday of the first day (0 = Sunday, 6 = Saturday)
        $firstDayOfWeekIndex = $startOfMonth->dayOfWeek;

        // Adjust to make Monday the first day (0 = Monday, 6 = Sunday)
        $firstDayOfWeekIndex = ($firstDayOfWeekIndex === 0) ? 6 : $firstDayOfWeekIndex - 1;

        // Calculate the number of days in the month
        $daysInMonth = $endOfMonth->day;

        // Calculate the number of days to display from the previous month
        $daysFromPreviousMonth = $firstDayOfWeekIndex;

        // Calculate the start date for the calendar (including days from previous month)
        $calendarStart = $startOfMonth->copy()->subDays($daysFromPreviousMonth);

        // Calculate the number of days to display from the next month
        $daysFromNextMonth = 42 - $daysInMonth - $daysFromPreviousMonth;

        // Prepare the calendar days array
        $days = [];

        // Add days from previous month
        for ($i = 0; $i < $daysFromPreviousMonth; $i++) {
            $date = $calendarStart->copy()->addDays($i);
            $days[] = [
                'date' => $date,
                'day' => $date->day,
                'isCurrentMonth' => false,
                'isToday' => $date->isToday(),
                'hasEvents' => $this->hasEventsOnDate($date),
                'events' => $this->getEventsForDate($date),
            ];
        }

        // Add days from current month
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = Carbon::createFromDate($this->calendarYear, $this->calendarMonth, $i);
            $days[] = [
                'date' => $date,
                'day' => $i,
                'isCurrentMonth' => true,
                'isToday' => $date->isToday(),
                'hasEvents' => $this->hasEventsOnDate($date),
                'events' => $this->getEventsForDate($date),
            ];
        }

        // Add days from next month
        $nextMonthStart = $endOfMonth->copy()->addDay();
        for ($i = 0; $i < $daysFromNextMonth; $i++) {
            $date = $nextMonthStart->copy()->addDays($i);
            $days[] = [
                'date' => $date,
                'day' => $date->day,
                'isCurrentMonth' => false,
                'isToday' => $date->isToday(),
                'hasEvents' => $this->hasEventsOnDate($date),
                'events' => $this->getEventsForDate($date),
            ];
        }

        // Group days into weeks
        $weeks = array_chunk($days, 7);

        return $weeks;
    }

    private function hasEventsOnDate($date)
    {
        if (empty($this->events)) {
            return false;
        }

        $dateString = $date->format('Y-m-d');

        foreach ($this->events as $event) {
            $eventStart = Carbon::parse($event['start'])->format('Y-m-d');
            if ($eventStart === $dateString) {
                return true;
            }
        }

        return false;
    }

    private function getEventsForDate($date)
    {
        if (empty($this->events)) {
            return [];
        }

        $dateString = $date->format('Y-m-d');
        $eventsOnDate = [];

        foreach ($this->events as $event) {
            $eventStart = Carbon::parse($event['start'])->format('Y-m-d');
            if ($eventStart === $dateString) {
                $eventsOnDate[] = $event;
            }
        }

        return $eventsOnDate;
    }

    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    public function formatDateTime($dateTime)
    {
        return Carbon::parse($dateTime)->format('M d, Y g:i A');
    }

    public function formatTime($dateTime)
    {
        return Carbon::parse($dateTime)->format('g:i A');
    }

    public function formatDuration($startTime, $endTime)
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $durationMinutes = $start->diffInMinutes($end);

        $hours = floor($durationMinutes / 60);
        $minutes = $durationMinutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

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

    // Listen for cancelSession event
    #[On('cancelSession')]
    public function handleCancelSession($data)
    {
        $this->cancelSession($data['sessionId']);
    }
}; ?>

<div x-data="{
    highlightedDate: null,
    showEventDetails: false,
    selectedEvent: null,
    selectEvent(event) {
        this.selectedEvent = event;
        this.showEventDetails = true;
    },
    closeEventDetails() {
        this.showEventDetails = false;
        this.selectedEvent = null;
    }
}" class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Learning Sessions</h1>
                <p class="mt-1 text-base-content/70">Manage and track your child's learning sessions</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('parents.sessions.requests') }}" class="btn btn-primary">
                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                    Request New Session
                </a>
            </div>
        </div>

        <!-- Child Selection & Filters -->
        <div class="p-6 mb-8 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Child Select -->
                <div>
                    <label for="childSelect" class="block mb-2 text-sm font-medium">Select Child</label>
                    <select
                        id="childSelect"
                        wire:model.live="selectedChild"
                        class="w-full select select-bordered"
                    >
                        @if($children->isEmpty())
                            <option value="">No children found</option>
                        @else
                            @foreach($children as $child)
                                <option value="{{ $child->id }}">{{ $child->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Subject Select -->
                <div>
                    <label for="subjectSelect" class="block mb-2 text-sm font-medium">Subject (Optional)</label>
                    <select
                        id="subjectSelect"
                        wire:model.live="selectedSubject"
                        class="w-full select select-bordered"
                    >
                        <option value="">All Subjects</option>
                        @foreach($this->subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Range -->
                <div>
                    <label for="dateRangeSelect" class="block mb-2 text-sm font-medium">Date Range</label>
                    <select
                        id="dateRangeSelect"
                        wire:model.live="dateRange"
                        class="w-full select select-bordered"
                    >
                        <option value="upcoming">Upcoming Sessions</option>
                        <option value="past">Past Sessions</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="all">All Sessions</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
            </div>

            @if($dateRange === 'custom')
                <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-2">
                    <div>
                        <label for="startDate" class="block mb-2 text-sm font-medium">Start Date</label>
                        <input
                            type="date"
                            id="startDate"
                            wire:model.live="startDate"
                            class="w-full input input-bordered"
                        />
                    </div>
                    <div>
                        <label for="endDate" class="block mb-2 text-sm font-medium">End Date</label>
                        <input
                            type="date"
                            id="endDate"
                            wire:model.live="endDate"
                            class="w-full input input-bordered"
                        />
                    </div>
                </div>
            @endif

            <!-- Additional Filters -->
            <div class="grid grid-cols-1 gap-6 mt-6 md:grid-cols-3">
                <!-- Status Filter -->
                <div>
                    <label for="statusFilter" class="block mb-2 text-sm font-medium">Status</label>
                    <select
                        id="statusFilter"
                        wire:model.live="statusFilter"
                        class="w-full select select-bordered"
                    >
                        <option value="">All Statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Search -->
                <div>
                    <label for="searchInput" class="block mb-2 text-sm font-medium">Search</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                        </div>
                        <input
                            id="searchInput"
                            type="text"
                            wire:model.live.debounce.300ms="searchQuery"
                            placeholder="Search by teacher, subject..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- View Mode Toggle -->
                <div class="flex items-end">
                    <div class="w-full join">
                        <button
                            wire:click="changeViewMode('list')"
                            class="w-1/3 join-item btn {{ $viewMode === 'list' ? 'btn-active' : '' }}"
                        >
                            <x-icon name="o-list-bullet" class="w-5 h-5" />
                        </button>
                        <button
                            wire:click="changeViewMode('grid')"
                            class="w-1/3 join-item btn {{ $viewMode === 'grid' ? 'btn-active' : '' }}"
                        >
                            <x-icon name="o-squares-2x2" class="w-5 h-5" />
                        </button>
                        <button
                            wire:click="changeViewMode('calendar')"
                            class="w-1/3 join-item btn {{ $viewMode === 'calendar' ? 'btn-active' : '' }}"
                        >
                            <x-icon name="o-calendar" class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Summary -->
        <div class="grid grid-cols-1 gap-6 mb-8 md:grid-cols-4">
            <div class="shadow-lg stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <x-icon name="o-calendar" class="w-8 h-8" />
                    </div>
                   <div class="stat-figure text-primary">
                        <x-icon name="o-calendar" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Total Sessions</div>
                    <div class="stat-value text-primary">{{ $this->sessionStats['total'] }}</div>
                    <div class="stat-desc">Selected period</div>
                </div>
            </div>

            <div class="shadow-lg stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-info">
                        <x-icon name="o-clock" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Upcoming</div>
                    <div class="stat-value text-info">{{ $this->sessionStats['upcoming'] }}</div>
                    <div class="stat-desc">Sessions scheduled</div>
                </div>
            </div>

            <div class="shadow-lg stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-success">
                        <x-icon name="o-check-circle" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Completed</div>
                    <div class="stat-value text-success">{{ $this->sessionStats['completed'] }}</div>
                    <div class="stat-desc">Sessions attended</div>
                </div>
            </div>

            <div class="shadow-lg stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-error">
                        <x-icon name="o-x-circle" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Cancelled</div>
                    <div class="stat-value text-error">{{ $this->sessionStats['cancelled'] }}</div>
                    <div class="stat-desc">Sessions missed</div>
                </div>
            </div>
        </div>

        @if($this->nextSession && $dateRange === 'upcoming')
            <!-- Next Session Card -->
            <div class="p-6 mb-8 shadow-lg rounded-xl bg-base-100">
                <div class="flex flex-col items-start gap-6 md:flex-row md:items-center">
                    <div class="p-3 text-primary bg-primary-content rounded-xl">
                        <x-icon name="o-clock" class="w-10 h-10" />
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold">Next Scheduled Session</h2>

                        <div class="mt-2">
                            <div class="flex flex-wrap gap-x-4 gap-y-1">
                                <div class="font-medium">{{ $this->nextSession->subject->name }}</div>
                                <div>with {{ $this->nextSession->teacher->name }}</div>
                                <div>{{ $this->formatDateTime($this->nextSession->start_time) }}</div>
                                <div>{{ $this->formatDuration($this->nextSession->start_time, $this->nextSession->end_time) }}</div>
                                <div>{{ $this->nextSession->location ?? 'Online' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 ml-auto">
                        <button
                            wire:click="viewSessionDetail({{ $this->nextSession->id }})"
                            class="btn btn-outline"
                        >
                            <x-icon name="o-eye" class="w-4 h-4 mr-1" />
                            View Details
                        </button>

                        <button
                            wire:click="openRescheduleModal({{ $this->nextSession->id }})"
                            class="btn btn-outline"
                        >
                            <x-icon name="o-arrow-path" class="w-4 h-4 mr-1" />
                            Reschedule
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if(!$selectedChild)
            <!-- No Child Selected Message -->
            <div class="p-12 text-center shadow-lg rounded-xl bg-base-100">
                <div class="flex flex-col items-center justify-center">
                    <x-icon name="o-user" class="w-16 h-16 mb-4 text-base-content/30" />
                    <h3 class="text-xl font-bold">No Child Selected</h3>
                    <p class="mt-2 text-base-content/70">
                        Please select a child to view their learning sessions
                    </p>
                </div>
            </div>
        @elseif($this->sessions->isEmpty())
            <!-- No Sessions Message -->
            <div class="p-12 text-center shadow-lg rounded-xl bg-base-100">
                <div class="flex flex-col items-center justify-center">
                    <x-icon name="o-calendar" class="w-16 h-16 mb-4 text-base-content/30" />
                    <h3 class="text-xl font-bold">No Sessions Found</h3>
                    <p class="mt-2 text-base-content/70">
                        @if($searchQuery || $statusFilter || $selectedSubject)
                            No sessions match your current filters. Try adjusting your search criteria.
                        @else
                            No sessions found for the selected date range.
                        @endif
                    </p>
                    @if($searchQuery || $statusFilter || $selectedSubject)
                        <button
                            wire:click="$set('searchQuery', ''); $set('statusFilter', ''); $set('selectedSubject', null);"
                            class="mt-4 btn btn-outline"
                        >
                            Clear Filters
                        </button>
                    @else
                        <a href="{{ route('parents.sessions.requests') }}" class="mt-4 btn btn-primary">
                            Request New Session
                        </a>
                    @endif
                </div>
            </div>
        @else
            <!-- List View -->
            @if($viewMode === 'list')
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title">Session Schedule</h2>

                            <div class="flex items-center">
                                <div class="mr-2 text-sm">Sort by:</div>
                                <div class="join">
                                    <button
                                        wire:click="$set('sortBy', 'date'); $set('sortDir', '{{ $sortBy === 'date' && $sortDir === 'desc' ? 'asc' : 'desc' }}');"
                                        class="join-item btn btn-sm {{ $sortBy === 'date' ? 'btn-active' : '' }}"
                                    >
                                        Date
                                        @if($sortBy === 'date')
                                            <x-icon name="{{ $sortDir === 'desc' ? 'o-arrow-down' : 'o-arrow-up' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </button>
                                    <button
                                        wire:click="$set('sortBy', 'subject'); $set('sortDir', '{{ $sortBy === 'subject' && $sortDir === 'desc' ? 'asc' : 'desc' }}');"
                                        class="join-item btn btn-sm {{ $sortBy === 'subject' ? 'btn-active' : '' }}"
                                    >
                                        Subject
                                        @if($sortBy === 'subject')
                                            <x-icon name="{{ $sortDir === 'desc' ? 'o-arrow-down' : 'o-arrow-up' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </button>
                                    <button
                                        wire:click="$set('sortBy', 'status'); $set('sortDir', '{{ $sortBy === 'status' && $sortDir === 'desc' ? 'asc' : 'desc' }}');"
                                        class="join-item btn btn-sm {{ $sortBy === 'status' ? 'btn-active' : '' }}"
                                    >
                                        Status
                                        @if($sortBy === 'status')
                                            <x-icon name="{{ $sortDir === 'desc' ? 'o-arrow-down' : 'o-arrow-up' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="table table-zebra">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Duration</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->sessions as $session)
                                        <tr class="{{ $session->start_time->isToday() ? 'bg-base-200' : '' }}">
                                            <td>{{ $this->formatDateTime($session->start_time) }}</td>
                                            <td>{{ $session->subject->name }}</td>
                                            <td>{{ $session->teacher->name }}</td>
                                            <td>{{ $this->formatDuration($session->start_time, $session->end_time) }}</td>
                                            <td>{{ $session->location ?? 'Online' }}</td>
                                            <td>
                                                <div class="badge {{
                                                    $session->status === 'completed' ? 'badge-success' :
                                                    ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                                }}">
                                                    {{ ucfirst($session->status) }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="flex gap-1">
                                                    <button
                                                        wire:click="viewSessionDetail({{ $session->id }})"
                                                        class="btn btn-sm btn-ghost btn-circle tooltip"
                                                        data-tip="View Details"
                                                    >
                                                        <x-icon name="o-eye" class="w-4 h-4" />
                                                    </button>

                                                    @if($session->status === 'scheduled')
                                                        <button
                                                            wire:click="openRescheduleModal({{ $session->id }})"
                                                            class="btn btn-sm btn-ghost btn-circle tooltip"
                                                            data-tip="Reschedule"
                                                        >
                                                            <x-icon name="o-arrow-path" class="w-4 h-4" />
                                                        </button>

                                                        <button
                                                            wire:click="confirmCancelSession({{ $session->id }})"
                                                            class="btn btn-sm btn-ghost btn-circle tooltip text-error"
                                                            data-tip="Cancel"
                                                        >
                                                            <x-icon name="o-x-circle" class="w-4 h-4" />
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $this->sessions->links() }}
                        </div>
                    </div>
                </div>
            @endif

            <!-- Grid View -->
            @if($viewMode === 'grid')
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->sessions as $session)
                        <div class="h-full shadow-lg card bg-base-100">
                            <div class="card-body">
                                <div class="flex items-start justify-between">
                                    <h3 class="card-title">{{ $session->subject->name }}</h3>
                                    <div class="badge {{
                                        $session->status === 'completed' ? 'badge-success' :
                                        ($session->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                    }}">
                                        {{ ucfirst($session->status) }}
                                    </div>
                                </div>

                                <div class="my-2 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-calendar" class="w-4 h-4 text-base-content/70" />
                                        <span>{{ $this->formatDateTime($session->start_time) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-clock" class="w-4 h-4 text-base-content/70" />
                                        <span>{{ $this->formatDuration($session->start_time, $session->end_time) }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-user" class="w-4 h-4 text-base-content/70" />
                                        <span>{{ $session->teacher->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-map-pin" class="w-4 h-4 text-base-content/70" />
                                        <span>{{ $session->location ?? 'Online' }}</span>
                                    </div>
                                </div>

                                @if($session->status === 'completed' && $session->performance_score !== null)
                                    <div class="flex items-center justify-between mt-2">
                                        <span class="font-medium">Performance:</span>
                                        <div class="flex items-center gap-1">
                                            <span>{{ number_format($session->performance_score, 1) }}/10</span>
                                            <div class="radial-progress {{
                                                $session->performance_score >= 8 ? 'text-success' :
                                                ($session->performance_score >= 6 ? 'text-warning' : 'text-error')
                                            }}" style="--value:{{ $session->performance_score * 10 }}; --size:1.5rem;"></div>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex justify-end gap-2 mt-4 card-actions">
                                    <button
                                        wire:click="viewSessionDetail({{ $session->id }})"
                                        class="btn btn-sm btn-primary"
                                    >
                                        View Details
                                    </button>

                                    @if($session->status === 'scheduled')
                                        <div class="dropdown dropdown-end">
                                            <button tabindex="0" class="btn btn-sm btn-outline">Actions</button>
                                            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                <li>
                                                    <button wire:click="openRescheduleModal({{ $session->id }})">
                                                        <x-icon name="o-arrow-path" class="w-4 h-4" />
                                                        Reschedule
                                                    </button>
                                                </li>
                                                <li>
                                                    <button
                                                        wire:click="confirmCancelSession({{ $session->id }})"
                                                        class="text-error"
                                                    >
                                                        <x-icon name="o-x-circle" class="w-4 h-4" />
                                                        Cancel Session
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $this->sessions->links() }}
                </div>
            @endif

            <!-- Calendar View -->
            @if($viewMode === 'calendar')
                <div class="p-6 shadow-xl card bg-base-100">
                    <div class="card-body">
                        <!-- Calendar Header -->
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold">
                                {{ Carbon::createFromDate($calendarYear, $calendarMonth, 1)->format('F Y') }}
                            </h2>

                            <div class="flex gap-2">
                                <button wire:click="prevMonth" class="btn btn-sm btn-ghost">
                                    <x-icon name="o-chevron-left" class="w-5 h-5" />
                                </button>
                                <button
                                    wire:click="$set('calendarMonth', {{ now()->month }}); $set('calendarYear', {{ now()->year }});"
                                    class="btn btn-sm btn-ghost"
                                >
                                    Today
                                </button>
                                <button wire:click="nextMonth" class="btn btn-sm btn-ghost">
                                    <x-icon name="o-chevron-right" class="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <!-- Calendar Grid -->
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr>
                                        <th class="p-2 text-center">Mon</th>
                                        <th class="p-2 text-center">Tue</th>
                                        <th class="p-2 text-center">Wed</th>
                                        <th class="p-2 text-center">Thu</th>
                                        <th class="p-2 text-center">Fri</th>
                                        <th class="p-2 text-center">Sat</th>
                                        <th class="p-2 text-center">Sun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->calendarDays as $week)
                                        <tr>
                                            @foreach($week as $day)
                                                <td class="p-1 border border-base-300">
                                                    <div
                                                        @mouseenter="highlightedDate = '{{ $day['date']->format('Y-m-d') }}'"
                                                        @mouseleave="highlightedDate = null"
                                                        class="relative h-24 overflow-hidden {{ $day['isCurrentMonth'] ? '' : 'opacity-40' }} {{ $day['isToday'] ? 'bg-primary/10 rounded' : '' }}"
                                                    >
                                                        <div class="p-1 text-right">
                                                            <span class="{{ $day['isToday'] ? 'rounded-full bg-primary text-primary-content w-6 h-6 inline-flex items-center justify-center' : '' }}">
                                                                {{ $day['day'] }}
                                                            </span>
                                                        </div>

                                                        @if($day['hasEvents'])
                                                            <div class="px-1 pb-1 overflow-y-auto max-h-16">
                                                                @foreach($day['events'] as $event)
                                                                    <div
                                                                        @click="selectEvent({{ json_encode($event) }})"
                                                                        class="mb-1 text-xs truncate rounded cursor-pointer p-0.5"
                                                                        style="background-color: {{ $event['color'] }}; color: {{ $event['textColor'] }};"
                                                                    >
                                                                        {{ $this->formatTime(Carbon::parse($event['start'])) }} - {{ $event['title'] }}
                                                                    </div>
                                                                @endforeach
                                                            </div>

                                                            @if(count($day['events']) > 2)
                                                                <div
                                                                    x-show="highlightedDate === '{{ $day['date']->format('Y-m-d') }}'"
                                                                    class="absolute inset-0 flex items-center justify-center bg-base-100/75"
                                                                >
                                                                    <button
                                                                        @click="$dispatch('show-all-events', { date: '{{ $day['date']->format('Y-m-d') }}' })"
                                                                        class="btn btn-sm btn-primary"
                                                                    >
                                                                        View {{ count($day['events']) }} Events
                                                                    </button>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Calendar Legend -->
                        <div class="flex flex-wrap items-center gap-4 mt-4">
                            <div class="flex items-center gap-1">
                                <div class="w-3 h-3 rounded" style="background-color: #6419E6;"></div>
                                <span class="text-sm">Scheduled</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <div class="w-3 h-3 rounded" style="background-color: #36D399;"></div>
                                <span class="text-sm">Completed</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <div class="w-3 h-3 rounded" style="background-color: #F87272;"></div>
                                <span class="text-sm">Cancelled</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendar Event Detail Popover -->
                <div
                    x-show="showEventDetails"
                    @click.away="closeEventDetails"
                    x-transition
                    class="fixed inset-0 z-50 flex items-center justify-center bg-base-100/60 backdrop-blur-sm"
                >
                    <div class="w-full max-w-md p-6 shadow-xl rounded-xl bg-base-100">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold" x-text="selectedEvent?.title"></h3>
                            <button @click="closeEventDetails" class="btn btn-sm btn-circle">✕</button>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <x-icon name="o-calendar" class="w-4 h-4 text-base-content/70" />
                                <span x-text="new Date(selectedEvent?.start).toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-icon name="o-clock" class="w-4 h-4 text-base-content/70" />
                                <span x-text="new Date(selectedEvent?.start).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'}) + ' - ' + new Date(selectedEvent?.end).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'})"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-icon name="o-user" class="w-4 h-4 text-base-content/70" />
                                <span x-text="selectedEvent?.teacher"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-icon name="o-map-pin" class="w-4 h-4 text-base-content/70" />
                                <span x-text="selectedEvent?.extendedProps?.location"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-icon name="o-information-circle" class="w-4 h-4 text-base-content/70" />
                                <div class="badge" :class="{
                                    'badge-primary': selectedEvent?.status === 'scheduled',
                                    'badge-success': selectedEvent?.status === 'completed',
                                    'badge-error': selectedEvent?.status === 'cancelled'
                                }" x-text="selectedEvent?.status.charAt(0).toUpperCase() + selectedEvent?.status.slice(1)"></div>
                            </div>

                            <template x-if="selectedEvent?.status === 'completed' && selectedEvent?.extendedProps?.score !== null">
                                <div class="flex items-center justify-between mt-2">
                                    <span class="font-medium">Performance:</span>
                                    <div class="flex items-center gap-1">
                                        <span x-text="selectedEvent?.extendedProps?.score + '/10'"></span>
                                        <div class="radial-progress"
                                            :class="{
                                                'text-success': selectedEvent?.extendedProps?.score >= 8,
                                                'text-warning': selectedEvent?.extendedProps?.score >= 6 && selectedEvent?.extendedProps?.score < 8,
                                                'text-error': selectedEvent?.extendedProps?.score < 6
                                            }"
                                            :style="`--value:${selectedEvent?.extendedProps?.score * 10}; --size:1.5rem;`">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-end gap-2 mt-6">
                            <button
                                @click="closeEventDetails(); $wire.viewSessionDetail(selectedEvent.id)"
                                class="btn btn-primary"
                            >
                                View Full Details
                            </button>

                            <template x-if="selectedEvent?.status === 'scheduled'">
                                <div class="join">
                                    <button
                                        @click="closeEventDetails(); $wire.openRescheduleModal(selectedEvent.id)"
                                        class="btn join-item"
                                    >
                                        <x-icon name="o-arrow-path" class="w-4 h-4 mr-1" />
                                        Reschedule
                                    </button>
                                    <button
                                        @click="closeEventDetails(); $wire.confirmCancelSession(selectedEvent.id)"
                                        class="btn join-item btn-error"
                                    >
                                        <x-icon name="o-x-circle" class="w-4 h-4 mr-1" />
                                        Cancel
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <!-- Session Detail Modal -->
    <div class="modal {{ $showSessionDetailModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <button wire:click="closeSessionDetailModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            @if($selectedSession)
                <h3 class="text-lg font-bold">Session Details</h3>

                <div class="py-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <div class="mb-2">
                                <span class="font-bold">Subject:</span>
                                <span>{{ $selectedSession->subject->name }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="font-bold">Teacher:</span>
                                <span>{{ $selectedSession->teacher->name }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="font-bold">Date:</span>
                                <span>{{ $this->formatDate($selectedSession->start_time) }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="font-bold">Child:</span>
                                <span>{{ $this->selectedChildData->name }}</span>
                            </div>
                        </div>

                        <div>
                            <div class="mb-2">
                                <span class="font-bold">Time:</span>
                                <span>{{ Carbon::parse($selectedSession->start_time)->format('g:i A') }} - {{ Carbon::parse($selectedSession->end_time)->format('g:i A') }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="font-bold">Duration:</span>
                                <span>{{ $this->formatDuration($selectedSession->start_time, $selectedSession->end_time) }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="font-bold">Location:</span>
                                <span>{{ $selectedSession->location ?? 'Online' }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="font-bold">Status:</span>
                                <div class="badge {{
                                    $selectedSession->status === 'completed' ? 'badge-success' :
                                    ($selectedSession->status === 'cancelled' ? 'badge-error' : 'badge-info')
                                }}">
                                    {{ ucfirst($selectedSession->status) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($selectedSession->status === 'completed')
                        <div class="p-4 mt-4 border rounded-lg border-base-300">
                            <h4 class="mb-2 font-semibold">Session Results</h4>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
<div>
                                    <div class="mb-2">
                                        <span class="font-medium">Attendance:</span>
                                        <div class="badge {{ $selectedSession->attended ? 'badge-success' : 'badge-error' }}">
                                            {{ $selectedSession->attended ? 'Present' : 'Absent' }}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    @if($selectedSession->performance_score !== null)
                                        <div class="mb-2">
                                            <span class="font-medium">Performance Score:</span>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xl">{{ number_format($selectedSession->performance_score, 1) }}/10</span>
                                                <div class="radial-progress {{
                                                    $selectedSession->performance_score >= 8 ? 'text-success' :
                                                    ($selectedSession->performance_score >= 6 ? 'text-warning' : 'text-error')
                                                }}" style="--value:{{ $selectedSession->performance_score * 10 }}; --size:2rem;">
                                                    {{ round($selectedSession->performance_score * 10) }}%
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($selectedSession->notes)
                        <div class="mt-4">
                            <div class="font-bold">Teacher Notes:</div>
                            <div class="p-3 mt-1 bg-base-200 rounded-box">
                                {{ $selectedSession->notes }}
                            </div>
                        </div>
                    @endif

                    @if($selectedSession->status === 'scheduled')
                        <div class="flex justify-end gap-2 mt-6">
                            <button
                                wire:click="openRescheduleModal({{ $selectedSession->id }})"
                                class="btn btn-outline"
                            >
                                <x-icon name="o-arrow-path" class="w-4 h-4 mr-1" />
                                Reschedule
                            </button>

                            <button
                                wire:click="confirmCancelSession({{ $selectedSession->id }})"
                                class="btn btn-error"
                            >
                                <x-icon name="o-x-circle" class="w-4 h-4 mr-1" />
                                Cancel Session
                            </button>
                        </div>
                    @endif
                </div>

                <div class="modal-action">
                    <button wire:click="closeSessionDetailModal" class="btn">Close</button>
                </div>
            @endif
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div class="modal {{ $showRescheduleModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <button wire:click="closeRescheduleModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            @if($selectedSession)
                <h3 class="text-lg font-bold">Reschedule Session</h3>

                <div class="py-4">
                    <p class="mb-4">
                        Rescheduling <span class="font-medium">{{ $selectedSession->subject->name }}</span> session
                        with <span class="font-medium">{{ $selectedSession->teacher->name }}</span>.
                    </p>

                    <form wire:submit.prevent="rescheduleSession">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="block mb-2 text-sm font-medium">Date</label>
                                <input
                                    type="date"
                                    wire:model="rescheduleDate"
                                    min="{{ now()->format('Y-m-d') }}"
                                    class="w-full input input-bordered"
                                    required
                                />
                                @error('rescheduleDate') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium">Time</label>
                                <input
                                    type="time"
                                    wire:model="rescheduleTime"
                                    class="w-full input input-bordered"
                                    required
                                />
                                @error('rescheduleTime') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="p-3 mt-4 text-sm bg-info-content/10 rounded-box">
                            <div class="flex items-start gap-2">
                                <x-icon name="o-information-circle" class="flex-shrink-0 w-5 h-5 mt-0.5 text-info" />
                                <p>
                                    Rescheduling a session will notify the teacher. They may need to confirm the new time.
                                    The duration of the session will remain the same.
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 mt-6">
                            <button type="button" wire:click="closeRescheduleModal" class="btn">Cancel</button>
                            <button type="submit" class="btn btn-primary">Reschedule Session</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
