<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\LearningSession;
use App\Models\TeacherProfile;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $teacher;
    public $teacherProfile;

    // Display settings
    public $view = 'week';        // 'day', 'week', 'month'
    public $currentDate;
    public $displayDates = [];
    public $timeSlots = [];

    // Availability settings
    public $availableDays = [];
    public $availableTimeStart = '08:00';
    public $availableTimeEnd = '18:00';
    public $breakTimeStart = '12:00';
    public $breakTimeEnd = '13:00';

    // New session modal
    public $showSessionModal = false;
    public $sessionDate;
    public $sessionStartTime;
    public $sessionEndTime;
    public $sessionSubject = '';
    public $sessionCourse = '';
    public $sessionStudent = '';
    public $availableSubjects = [];
    public $availableCourses = [];
    public $availableStudents = [];

    // Session details modal
    public $showSessionDetailsModal = false;
    public $selectedSession = null;

    // Availability management modal
    public $showAvailabilityModal = false;
    public $weekdays = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    // Filtering
    public $subjectFilter = '';
    public $courseFilter = '';
    public $studentFilter = '';
    public $statusFilter = '';

    protected $rules = [
        'sessionDate' => 'required|date|after_or_equal:today',
        'sessionStartTime' => 'required',
        'sessionEndTime' => 'required|after:sessionStartTime',
        'sessionSubject' => 'required',
        'sessionStudent' => 'required',
    ];

    public function mount()
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        // Set current date and initialize the calendar
        $this->currentDate = Carbon::today();
        $this->updateDisplayDates();

        // Initialize availability settings from teacher profile
        $this->initializeAvailability();

        // Generate time slots from 8 AM to 6 PM with 30-minute intervals
        $this->generateTimeSlots();

        // Load options for dropdowns
        $this->loadSubjects();
        $this->loadCourses();
        $this->loadStudents();
    }

    protected function initializeAvailability()
    {
        // In a real app, this would come from the teacher profile
        // For now, default to weekdays 8 AM to 6 PM
        $this->availableDays = [1, 2, 3, 4, 5]; // Monday to Friday

        // For a real app, you would load this from the database
        // $this->availableDays = explode(',', $this->teacherProfile->available_days ?? '1,2,3,4,5');
        // $this->availableTimeStart = $this->teacherProfile->available_time_start ?? '08:00';
        // $this->availableTimeEnd = $this->teacherProfile->available_time_end ?? '18:00';
        // $this->breakTimeStart = $this->teacherProfile->break_time_start ?? '12:00';
        // $this->breakTimeEnd = $this->teacherProfile->break_time_end ?? '13:00';
    }

    protected function generateTimeSlots()
    {
        $this->timeSlots = [];
        $start = Carbon::createFromFormat('H:i', $this->availableTimeStart);
        $end = Carbon::createFromFormat('H:i', $this->availableTimeEnd);

        while ($start < $end) {
            $this->timeSlots[] = $start->format('H:i');
            $start->addMinutes(30);
        }
    }

    protected function updateDisplayDates()
    {
        $this->displayDates = [];

        if ($this->view === 'day') {
            $this->displayDates[] = clone $this->currentDate;
        } else if ($this->view === 'week') {
            // Get the start of the week (Monday)
            $weekStart = clone $this->currentDate;
            $weekStart->startOfWeek(Carbon::MONDAY);

            // Generate 7 days from the start of the week
            for ($i = 0; $i < 7; $i++) {
                $date = clone $weekStart;
                $date->addDays($i);
                $this->displayDates[] = $date;
            }
        } else if ($this->view === 'month') {
            // Get the first day of the month
            $monthStart = clone $this->currentDate;
            $monthStart->startOfMonth();

            // Get the last day of the month
            $monthEnd = clone $this->currentDate;
            $monthEnd->endOfMonth();

            // Get the first Monday before or on the start of the month
            $calendarStart = clone $monthStart;
            if ($calendarStart->dayOfWeek !== Carbon::MONDAY) {
                $calendarStart->previous(Carbon::MONDAY);
            }

            // Get the last Sunday after or on the end of the month
            $calendarEnd = clone $monthEnd;
            if ($calendarEnd->dayOfWeek !== Carbon::SUNDAY) {
                $calendarEnd->next(Carbon::SUNDAY);
            }

            // Generate days from the calendar start to the calendar end
            $current = clone $calendarStart;
            while ($current <= $calendarEnd) {
                $this->displayDates[] = clone $current;
                $current->addDay();
            }
        }
    }

    protected function loadSubjects()
    {
        // In a real app, fetch from database based on teacher profile
        if ($this->teacherProfile) {
            $this->availableSubjects = $this->teacherProfile->subjects()
                ->get()
                ->map(function($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name
                    ];
                })
                ->toArray();
        } else {
            // Fallback
            $this->availableSubjects = [
                ['id' => 1, 'name' => 'Mathematics'],
                ['id' => 2, 'name' => 'Science'],
                ['id' => 3, 'name' => 'English'],
                ['id' => 4, 'name' => 'History']
            ];
        }
    }

    protected function loadCourses()
    {
        // In a real app, fetch from database based on teacher profile
        $this->availableCourses = [
            ['id' => 1, 'name' => 'Advanced Mathematics'],
            ['id' => 2, 'name' => 'Basic Science'],
            ['id' => 3, 'name' => 'English Literature']
        ];
    }

    protected function loadStudents()
    {
        // In a real app, fetch from database based on assigned students
        $this->availableStudents = [
            ['id' => 1, 'name' => 'John Smith'],
            ['id' => 2, 'name' => 'Emily Johnson'],
            ['id' => 3, 'name' => 'Michael Brown']
        ];
    }

    public function getSessionsProperty()
    {
        // In a real app, fetch learning sessions from the database
        // This example simulates sessions for the display period
        $sessions = collect();

        // Get date bounds for the query
        $startDate = reset($this->displayDates);
        $endDate = end($this->displayDates);

        if ($startDate && $endDate) {
            // In a real app, this would be a database query
            // LearningSession::where('teacher_id', $this->teacher->id)
            //    ->whereBetween('start_time', [$startDate->startOfDay(), $endDate->endOfDay()])
            //    ->get();

            // For now, generate some sample sessions
            $sessions = $this->getMockSessions($startDate, $endDate);
        }

        // Apply filters if set
        if ($this->subjectFilter) {
            $sessions = $sessions->filter(function($session) {
                return $session['subject_id'] == $this->subjectFilter;
            });
        }

        if ($this->courseFilter) {
            $sessions = $sessions->filter(function($session) {
                return $session['course_id'] == $this->courseFilter;
            });
        }

        if ($this->studentFilter) {
            $sessions = $sessions->filter(function($session) {
                return $session['student_id'] == $this->studentFilter;
            });
        }

        if ($this->statusFilter) {
            $sessions = $sessions->filter(function($session) {
                return $session['status'] == $this->statusFilter;
            });
        }

        return $sessions;
    }

    private function getMockSessions($startDate, $endDate)
    {
        $sessions = collect();
        $subjects = ['Mathematics', 'Science', 'English', 'History'];
        $students = ['John Smith', 'Emily Johnson', 'Michael Brown', 'Emma Davis'];
        $statuses = ['scheduled', 'completed', 'cancelled'];

        // Generate 10-15 random sessions in the date range
        $numSessions = rand(10, 15);

        for ($i = 0; $i < $numSessions; $i++) {
            $sessionDate = clone $startDate;
            $sessionDate->addDays(rand(0, $endDate->diff($startDate)->days));

            // Only create sessions on weekdays
            if ($sessionDate->isWeekend() && rand(0, 4) > 0) {
                // Less likely to have weekend sessions
                continue;
            }

            $startHour = rand(8, 17);
            $duration = rand(1, 2); // 1 or 2 hours

            $startTime = clone $sessionDate;
            $startTime->setHour($startHour)->setMinute(rand(0, 1) * 30)->setSecond(0);

            $endTime = clone $startTime;
            $endTime->addHours($duration);

            $subjectIndex = rand(0, count($subjects) - 1);
            $studentIndex = rand(0, count($students) - 1);
            $statusIndex = rand(0, count($statuses) - 1);

            // Past sessions are more likely to be completed
            if ($sessionDate < Carbon::today()) {
                $statusIndex = rand(0, 10) > 2 ? 1 : $statusIndex; // 80% chance of being completed
            }

            $sessions->push([
                'id' => $i + 1,
                'teacher_id' => $this->teacher->id,
                'subject_id' => $subjectIndex + 1,
                'subject_name' => $subjects[$subjectIndex],
                'student_id' => $studentIndex + 1,
                'student_name' => $students[$studentIndex],
                'course_id' => rand(1, 3),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $statuses[$statusIndex],
                'title' => $subjects[$subjectIndex] . ' with ' . $students[$studentIndex],
                'location' => 'Online',
                'attended' => $statusIndex === 1 ? (rand(0, 10) > 2) : false,
                'notes' => ''
            ]);
        }

        return $sessions;
    }

    public function setView($view)
    {
        $this->view = $view;
        $this->updateDisplayDates();
    }

    public function previousPeriod()
    {
        if ($this->view === 'day') {
            $this->currentDate->subDay();
        } else if ($this->view === 'week') {
            $this->currentDate->subWeek();
        } else if ($this->view === 'month') {
            $this->currentDate->subMonth();
        }

        $this->updateDisplayDates();
    }

    public function nextPeriod()
    {
        if ($this->view === 'day') {
            $this->currentDate->addDay();
        } else if ($this->view === 'week') {
            $this->currentDate->addWeek();
        } else if ($this->view === 'month') {
            $this->currentDate->addMonth();
        }

        $this->updateDisplayDates();
    }

    public function today()
    {
        $this->currentDate = Carbon::today();
        $this->updateDisplayDates();
    }

    public function openNewSessionModal($date = null, $time = null)
    {
        // Set default date and time if provided
        if ($date) {
            $this->sessionDate = $date;
        } else {
            $this->sessionDate = Carbon::today()->format('Y-m-d');
        }

        if ($time) {
            $this->sessionStartTime = $time;
            // Default session length is 1 hour
            $startTime = Carbon::createFromFormat('H:i', $time);
            $this->sessionEndTime = $startTime->addHour()->format('H:i');
        } else {
            $this->sessionStartTime = '09:00';
            $this->sessionEndTime = '10:00';
        }

        $this->sessionSubject = '';
        $this->sessionCourse = '';
        $this->sessionStudent = '';

        $this->showSessionModal = true;
    }

    public function scheduleSession()
    {
        $this->validate();

        // In a real app, this would create a new session in the database
        // For example:
        // LearningSession::create([
        //     'teacher_id' => $this->teacher->id,
        //     'children_id' => $this->sessionStudent,
        //     'subject_id' => $this->sessionSubject,
        //     'start_time' => Carbon::parse($this->sessionDate . ' ' . $this->sessionStartTime),
        //     'end_time' => Carbon::parse($this->sessionDate . ' ' . $this->sessionEndTime),
        //     'status' => 'scheduled'
        // ]);

        // Show success toast
        $this->toast(
            type: 'success',
            title: 'Session scheduled',
            description: 'New session has been scheduled successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->showSessionModal = false;
    }

    public function viewSessionDetails($sessionId)
    {
        // In a real app, fetch session from database
        // $this->selectedSession = LearningSession::find($sessionId);

        // For now, find the session in our mock data
        $this->selectedSession = $this->sessions->firstWhere('id', $sessionId);

        $this->showSessionDetailsModal = true;
    }

    public function deleteSession()
    {
        if (!$this->selectedSession) {
            return;
        }

        // In a real app, delete or cancel the session
        // LearningSession::find($this->selectedSession['id'])->delete();

        // Show success toast
        $this->toast(
            type: 'success',
            title: 'Session cancelled',
            description: 'The session has been cancelled successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->showSessionDetailsModal = false;
        $this->selectedSession = null;
    }

    public function openAvailabilityModal()
    {
        $this->showAvailabilityModal = true;
    }

    public function saveAvailability()
    {
        // Validate inputs
        $this->validate([
            'availableDays' => 'required|array|min:1',
            'availableTimeStart' => 'required',
            'availableTimeEnd' => 'required|after:availableTimeStart',
            'breakTimeStart' => 'required',
            'breakTimeEnd' => 'required|after:breakTimeStart'
        ]);

        // In a real app, save to teacher profile
        // $this->teacherProfile->update([
        //     'available_days' => implode(',', $this->availableDays),
        //     'available_time_start' => $this->availableTimeStart,
        //     'available_time_end' => $this->availableTimeEnd,
        //     'break_time_start' => $this->breakTimeStart,
        //     'break_time_end' => $this->breakTimeEnd
        // ]);

        // Regenerate time slots based on new availability
        $this->generateTimeSlots();

        // Show success toast
        $this->toast(
            type: 'success',
            title: 'Availability updated',
            description: 'Your availability settings have been updated.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->showAvailabilityModal = false;
    }

    public function isSessionSlot($date, $timeSlot)
    {
        // Check if there's a session at this date and time
        foreach ($this->sessions as $session) {
            $sessionStart = $session['start_time'];
            $sessionEnd = $session['end_time'];

            // Check if the date matches
            if ($sessionStart->toDateString() !== $date->toDateString()) {
                continue;
            }

            // Check if the time slot falls within the session
            $slotStart = Carbon::parse($date->toDateString() . ' ' . $timeSlot);
            $slotEnd = (clone $slotStart)->addMinutes(30);

            if (
                ($slotStart >= $sessionStart && $slotStart < $sessionEnd) ||
                ($slotEnd > $sessionStart && $slotEnd <= $sessionEnd) ||
                ($slotStart <= $sessionStart && $slotEnd >= $sessionEnd)
            ) {
                return $session;
            }
        }

        return false;
    }

    public function isAvailableSlot($date, $timeSlot)
    {
        // Check if the date is in the available days
        if (!in_array($date->dayOfWeek, $this->availableDays)) {
            return false;
        }

        // Check if the time is within available hours
        $slotTime = Carbon::createFromFormat('H:i', $timeSlot);
        $availableStart = Carbon::createFromFormat('H:i', $this->availableTimeStart);
        $availableEnd = Carbon::createFromFormat('H:i', $this->availableTimeEnd);
        $breakStart = Carbon::createFromFormat('H:i', $this->breakTimeStart);
        $breakEnd = Carbon::createFromFormat('H:i', $this->breakTimeEnd);

        // Check if time is within general availability and not during break
        return $slotTime >= $availableStart && $slotTime < $availableEnd &&
               !($slotTime >= $breakStart && $slotTime < $breakEnd);
    }

    public function getFormattedPeriodProperty()
    {
        if ($this->view === 'day') {
            return $this->currentDate->format('F j, Y');
        } else if ($this->view === 'week') {
            $weekStart = reset($this->displayDates);
            $weekEnd = end($this->displayDates);

            if ($weekStart->month === $weekEnd->month) {
                return $weekStart->format('F Y');
            } else if ($weekStart->year === $weekEnd->year) {
                return $weekStart->format('M') . ' - ' . $weekEnd->format('M Y');
            } else {
                return $weekStart->format('M Y') . ' - ' . $weekEnd->format('M Y');
            }
        } else if ($this->view === 'month') {
            return $this->currentDate->format('F Y');
        }

        return '';
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
};
?>
@php
// Make Carbon available for use in the template
// without conflicting with namespace
use Carbon\Carbon as CarbonDate;
@endphp

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">My Teaching Timetable</h1>
                <p class="mt-1 text-base-content/70">Manage your teaching schedule and availability</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    wire:click="openNewSessionModal"
                    class="btn btn-primary"
                >
                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                    Schedule Session
                </button>
                <button
                    wire:click="openAvailabilityModal"
                    class="btn btn-outline"
                >
                    <x-icon name="o-clock" class="w-4 h-4 mr-2" />
                    Manage Availability
                </button>
            </div>
        </div>

        <!-- Calendar Controls -->
        <div class="p-4 mb-6 shadow-lg rounded-xl bg-base-100">
            <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
                <!-- Period Navigation -->
                <div class="flex items-center gap-2">
                    <button wire:click="previousPeriod" class="btn btn-sm btn-ghost">
                        <x-icon name="o-chevron-left" class="w-5 h-5" />
                    </button>
                    <h2 class="text-xl font-bold">{{ $this->formattedPeriod }}</h2>
                    <button wire:click="nextPeriod" class="btn btn-sm btn-ghost">
                        <x-icon name="o-chevron-right" class="w-5 h-5" />
                    </button>
                    <button wire:click="today" class="ml-2 btn btn-sm btn-outline">
                        Today
                    </button>
                </div>

                <!-- View Toggles -->
                <div class="flex items-center gap-2">
                    <div class="grid grid-flow-col gap-1 p-1 rounded-lg bg-base-200">
                        <button
                            wire:click="setView('day')"
                            class="px-3 py-1 rounded-md {{ $view === 'day' ? 'bg-primary text-primary-content' : '' }}"
                        >
                            Day
                        </button>
                        <button
                            wire:click="setView('week')"
                            class="px-3 py-1 rounded-md {{ $view === 'week' ? 'bg-primary text-primary-content' : '' }}"
                        >
                            Week
                        </button>
                        <button
                            wire:click="setView('month')"
                            class="px-3 py-1 rounded-md {{ $view === 'month' ? 'bg-primary text-primary-content' : '' }}"
                        >
                            Month
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-4">
                <div>
                    <select wire:model.live="subjectFilter" class="w-full select select-bordered select-sm">
                        <option value="">All Subjects</option>
                        @foreach($availableSubjects as $subject)
                            <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select wire:model.live="courseFilter" class="w-full select select-bordered select-sm">
                        <option value="">All Courses</option>
                        @foreach($availableCourses as $course)
                            <option value="{{ $course['id'] }}">{{ $course['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select wire:model.live="studentFilter" class="w-full select select-bordered select-sm">
                        <option value="">All Students</option>
                        @foreach($availableStudents as $student)
                            <option value="{{ $student['id'] }}">{{ $student['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <select wire:model.live="statusFilter" class="w-full select select-bordered select-sm">
                        <option value="">All Statuses</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Calendar Display -->
        <div class="shadow-xl card bg-base-100">
            <div class="p-0 card-body">
                @if($view === 'day')
                    <!-- Day View -->
                    <div class="w-full">
                        <div class="flex">
                            <!-- Time labels column -->
                            <div class="w-24 pt-10 pr-2 border-r border-base-300">
                                @foreach($timeSlots as $timeSlot)
                                    <div class="h-20 pr-2 text-sm text-right text-base-content/70">
                                        {{ CarbonDate::createFromFormat('H:i', $timeSlot)->format('g:i A') }}
                                    </div>
                                @endforeach
                            </div>

                            <!-- Day column -->
                            <div class="flex-1">
                                <div class="sticky top-0 z-10 h-10 p-2 font-medium text-center border-b border-base-300 bg-base-100">
                                    {{ $displayDates[0]->format('D, M j') }}
                                </div>

                                @foreach($timeSlots as $timeSlot)
                                    @php
                                        $session = $this->isSessionSlot($displayDates[0], $timeSlot);
                                        $isAvailable = $this->isAvailableSlot($displayDates[0], $timeSlot);
                                    @endphp

                                    <div wire:click="openNewSessionModal('{{ $displayDates[0]->format('Y-m-d') }}', '{{ $timeSlot }}')"
                                        class="h-20 border-b border-base-300 px-2 relative {{ $isAvailable ? 'cursor-pointer hover:bg-base-200' : 'bg-base-200/50' }}"
                                    >
                                        @if($session)
                                            <div wire:click.stop="viewSessionDetails({{ $session['id'] }})"
                                                class="absolute inset-1 rounded-md p-2 {{ $session['status'] === 'completed' ? 'bg-success/20 border border-success/40' : ($session['status'] === 'cancelled' ? 'bg-error/20 border border-error/40' : 'bg-primary/20 border border-primary/40') }}
                                                cursor-pointer hover:shadow-md transition-shadow"
                                            >
                                                <div class="font-medium">{{ $session['title'] }}</div>
                                                <div class="text-xs">
                                                    {{ CarbonDate::parse($session['start_time'])->format('g:i A') }} -
                                                    {{ CarbonDate::parse($session['end_time'])->format('g:i A') }}
                                                </div>
                                                <div class="mt-1 text-xs">{{ $session['location'] }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @elseif($view === 'week')
                    <!-- Week View -->
                    <div class="w-full overflow-x-auto">
                        <div class="flex min-w-[900px]">
                            <!-- Time labels column -->
                            <div class="w-24 pt-10 pr-2 border-r border-base-300">
                                @foreach($timeSlots as $timeSlot)
                                    <div class="h-20 pr-2 text-sm text-right text-base-content/70">
                                        {{ CarbonDate::createFromFormat('H:i', $timeSlot)->format('g:i A') }}
                                    </div>
                                @endforeach
                            </div>

                            <!-- Day columns -->
                            @foreach($displayDates as $date)
                                <div class="flex-1 min-w-[110px]">
                                    <div class="p-2 text-center font-medium border-b border-l border-base-300 h-10 sticky top-0 bg-base-100 z-10
                                        {{ $date->isToday() ? 'bg-primary/10' : '' }}">
                                        {{ $date->format('D') }}
                                        <div class="text-sm {{ $date->isToday() ? 'text-primary font-bold' : 'text-base-content/70' }}">{{ $date->format('j') }}</div>
                                    </div>

                                    @foreach($timeSlots as $timeSlot)
                                        @php
                                            $session = $this->isSessionSlot($date, $timeSlot);
                                            $isAvailable = $this->isAvailableSlot($date, $timeSlot);
                                        @endphp

                                        <div wire:click="openNewSessionModal('{{ $date->format('Y-m-d') }}', '{{ $timeSlot }}')"
                                            class="h-20 border-b border-l border-base-300 px-1 relative {{ $isAvailable ? 'cursor-pointer hover:bg-base-200' : 'bg-base-200/50' }}"
                                        >
                                            @if($session)
                                                <div wire:click.stop="viewSessionDetails({{ $session['id'] }})"
                                                    class="absolute inset-1 rounded-md p-1 {{ $session['status'] === 'completed' ? 'bg-success/20 border border-success/40' : ($session['status'] === 'cancelled' ? 'bg-error/20 border border-error/40' : 'bg-primary/20 border border-primary/40') }}
                                                    cursor-pointer hover:shadow-md transition-shadow text-xs"
                                                >
                                                    <div class="font-medium truncate">{{ $session['title'] }}</div>
                                                    <div>
                                                        {{ CarbonDate::parse($session['start_time'])->format('g:i A') }} -
                                                        {{ CarbonDate::parse($session['end_time'])->format('g:i A') }}
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif($view === 'month')
                    <!-- Month View -->
                    <div class="p-4">
                        <div class="grid grid-cols-7 gap-1">
                            <!-- Day of week headers -->
                            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                                <div class="p-2 font-medium text-center">{{ $dayName }}</div>
                            @endforeach

                            <!-- Calendar days -->
                            @foreach($displayDates as $date)
                                <div class="min-h-24 p-1 border rounded-md {{ $date->month === $currentDate->month ? 'border-base-300' : 'border-base-200 bg-base-200/30' }}
                                    {{ $date->isToday() ? 'border-primary/40 bg-primary/5' : '' }}"
                                >
                                    <div class="flex items-start justify-between">
                                        <span class="font-medium {{ $date->month === $currentDate->month ? '' : 'text-base-content/50' }} {{ $date->isToday() ? 'text-primary' : '' }}">
                                            {{ $date->format('j') }}
                                        </span>

                                        <button
                                            wire:click="openNewSessionModal('{{ $date->format('Y-m-d') }}')"
                                            class="btn btn-xs btn-ghost btn-circle"
                                        >
                                            <x-icon name="o-plus" class="w-3 h-3" />
                                        </button>
                                    </div>

                                    <!-- Session indicators for this day -->
                                    <div class="mt-1 space-y-1">
                                        @php
                                            $daysSessions = $this->sessions->filter(function($session) use ($date) {
                                                return $session['start_time']->toDateString() === $date->toDateString();
                                            })->take(3);
                                            $totalSessions = $this->sessions->filter(function($session) use ($date) {
                                                return $session['start_time']->toDateString() === $date->toDateString();
                                            })->count();
                                        @endphp

                                        @foreach($daysSessions as $session)
                                            <div
                                                wire:click="viewSessionDetails({{ $session['id'] }})"
                                                class="text-xs px-1 py-0.5 rounded-sm cursor-pointer truncate
                                                {{ $session['status'] === 'completed' ? 'bg-success/20 text-success-content' :
                                                  ($session['status'] === 'cancelled' ? 'bg-error/20 text-error-content' :
                                                   'bg-primary/20 text-primary-content') }}"
                                            >
                                                {{ CarbonDate::parse($session['start_time'])->format('g:i A') }} {{ $session['subject_name'] }}
                                            </div>
                                        @endforeach

                                        @if($totalSessions > 3)
                                            <div class="px-1 text-xs text-base-content/70">
                                                + {{ $totalSessions - 3 }} more
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Schedule New Session Modal -->
    <div class="modal {{ $showSessionModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Schedule New Session</h3>

            <form wire:submit.prevent="scheduleSession">
                <div class="grid grid-cols-1 gap-4 mt-4">
                    <!-- Date -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Date</span>
                        </label>
                        <input
                            type="date"
                            wire:model="sessionDate"
                            class="input input-bordered @error('sessionDate') input-error @enderror"
                            min="{{ CarbonDate::today()->format('Y-m-d') }}"
                        />
                        @error('sessionDate') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <!-- Time -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Start Time</span>
                            </label>
                            <input
                                type="time"
                                wire:model="sessionStartTime"
                                class="input input-bordered @error('sessionStartTime') input-error @enderror"
                            />
                            @error('sessionStartTime') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">End Time</span>
                            </label>
                            <input
                                type="time"
                                wire:model="sessionEndTime"
                                class="input input-bordered @error('sessionEndTime') input-error @enderror"
                            />
                            @error('sessionEndTime') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Subject -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Subject</span>
                        </label>
                        <select
                            wire:model="sessionSubject"
                            class="select select-bordered @error('sessionSubject') select-error @enderror"
                        >
                            <option value="">Select a subject</option>
                            @foreach($availableSubjects as $subject)
                                <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                            @endforeach
                        </select>
                        @error('sessionSubject') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <!-- Course (Optional) -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Course (Optional)</span>
                        </label>
                        <select
                            wire:model="sessionCourse"
                            class="select select-bordered"
                        >
                            <option value="">Select a course</option>
                            @foreach($availableCourses as $course)
                                <option value="{{ $course['id'] }}">{{ $course['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Student -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Student</span>
                        </label>
                        <select
                            wire:model="sessionStudent"
                            class="select select-bordered @error('sessionStudent') select-error @enderror"
                        >
                            <option value="">Select a student</option>
                            @foreach($availableStudents as $student)
                                <option value="{{ $student['id'] }}">{{ $student['name'] }}</option>
                            @endforeach
                        </select>
                        @error('sessionStudent') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" wire:click="$set('showSessionModal', false)" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Session Details Modal -->
    <div class="modal {{ $showSessionDetailsModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            @if($selectedSession)
                <h3 class="text-lg font-bold">{{ $selectedSession['title'] }}</h3>

                <div class="py-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <h4 class="text-sm font-semibold text-base-content/70">Date & Time</h4>
                            <p>{{ CarbonDate::parse($selectedSession['start_time'])->format('F j, Y') }}</p>
                            <p>{{ CarbonDate::parse($selectedSession['start_time'])->format('g:i A') }} -
                               {{ CarbonDate::parse($selectedSession['end_time'])->format('g:i A') }}</p>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-base-content/70">Status</h4>
                            <div class="badge {{ $selectedSession['status'] === 'completed' ? 'badge-success' :
                                ($selectedSession['status'] === 'cancelled' ? 'badge-error' : 'badge-primary') }}">
                                {{ ucfirst($selectedSession['status']) }}
                            </div>

                            @if($selectedSession['status'] === 'completed')
                                <div class="mt-2">
                                    <span class="text-sm">Attendance: </span>
                                    <span class="badge {{ $selectedSession['attended'] ? 'badge-success' : 'badge-error' }}">
                                        {{ $selectedSession['attended'] ? 'Present' : 'Absent' }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-base-content/70">Subject</h4>
                            <p>{{ $selectedSession['subject_name'] }}</p>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-base-content/70">Student</h4>
                            <p>{{ $selectedSession['student_name'] }}</p>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-base-content/70">Location</h4>
                            <p>{{ $selectedSession['location'] }}</p>
                        </div>
                    </div>

                    @if($selectedSession['notes'])
                        <div class="mt-4">
                            <h4 class="text-sm font-semibold text-base-content/70">Notes</h4>
                            <p class="mt-1">{{ $selectedSession['notes'] }}</p>
                        </div>
                    @endif
                </div>

                <div class="modal-action">
                    <button wire:click="$set('showSessionDetailsModal', false)" class="btn btn-outline">Close</button>

                    @if($selectedSession['status'] !== 'cancelled' && $selectedSession['status'] !== 'completed')
                        <button wire:click="deleteSession" class="btn btn-error">Cancel Session</button>
                    @endif

                    @if($selectedSession['status'] !== 'completed' && CarbonDate::parse($selectedSession['start_time'])->isPast())
                        <button class="btn btn-success">Mark as Completed</button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Availability Management Modal -->
    <div class="modal {{ $showAvailabilityModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Manage Availability</h3>

            <form wire:submit.prevent="saveAvailability">
                <div class="grid grid-cols-1 gap-4 mt-4">
                    <!-- Available Days -->
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Available Days</span>
                        </label>

                        <div class="grid grid-cols-4 gap-2">
                            @foreach($weekdays as $dayNum => $dayName)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="availableDays"
                                        value="{{ $dayNum }}"
                                        class="checkbox checkbox-sm"
                                    />
                                    <span>{{ $dayName }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('availableDays') <span class="text-sm text-error">{{ $message }}</span> @enderror
                    </div>

                    <!-- Available Hours -->
                    <div>
                        <label class="label">
                            <span class="label-text">Available Hours</span>
                        </label>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text-alt">Start Time</span>
                                </label>
                                <input
                                    type="time"
                                    wire:model="availableTimeStart"
                                    class="input input-bordered @error('availableTimeStart') input-error @enderror"
                                />
                                @error('availableTimeStart') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text-alt">End Time</span>
                                </label>
                                <input
                                    type="time"
                                    wire:model="availableTimeEnd"
                                    class="input input-bordered @error('availableTimeEnd') input-error @enderror"
                                />
                                @error('availableTimeEnd') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Break Time -->
                    <div>
                        <label class="label">
                            <span class="label-text">Break Time</span>
                        </label>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text-alt">Start Time</span>
                                </label>
                                <input
                                    type="time"
                                    wire:model="breakTimeStart"
                                    class="input input-bordered @error('breakTimeStart') input-error @enderror"
                                />
                                @error('breakTimeStart') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text-alt">End Time</span>
                                </label>
                                <input
                                    type="time"
                                    wire:model="breakTimeEnd"
                                    class="input input-bordered @error('breakTimeEnd') input-error @enderror"
                                />
                                @error('breakTimeEnd') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" wire:click="$set('showAvailabilityModal', false)" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Availability</button>
                </div>
            </form>
        </div>
    </div>
</div>
