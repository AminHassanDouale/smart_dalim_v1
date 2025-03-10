<?php

use Livewire\Volt\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $user;
    public $parentProfile;
    public $children = [];

    // Calendar State
    public $currentMonth;
    public $currentYear;
    public $daysInMonth = [];
    public $firstDayOfMonth;
    public $events = [];

    // Event Modal State
    public $showEventModal = false;
    public $selectedDate = null;
    public $selectedEvent = null;
    public $eventDetails = [
        'title' => '',
        'child_id' => '',
        'start_time' => '',
        'end_time' => '',
        'description' => '',
        'type' => '',
        'location' => ''
    ];

    // Filter State
    public $childFilter = '';
    public $typeFilter = '';

    public function mount()
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        if ($this->parentProfile) {
            $this->children = $this->parentProfile->children;
        }

        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;

        $this->generateCalendarDays();
        $this->loadEvents();
    }

    public function generateCalendarDays()
    {
        $this->daysInMonth = [];

        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
        $this->firstDayOfMonth = $date->dayOfWeek;
        $daysInMonth = $date->daysInMonth;

        // Previous month days to display
        $prevMonthDays = [];
        $prevMonth = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->subMonth();
        $daysInPrevMonth = $prevMonth->daysInMonth;

        for ($i = 0; $i < $this->firstDayOfMonth; $i++) {
            $day = $daysInPrevMonth - $this->firstDayOfMonth + $i + 1;
            $prevMonthDays[] = [
                'day' => $day,
                'date' => Carbon::createFromDate($prevMonth->year, $prevMonth->month, $day),
                'current_month' => false,
                'past' => true,
                'today' => false
            ];
        }

        // Current month days
        $currentMonthDays = [];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, $i);
            $currentMonthDays[] = [
                'day' => $i,
                'date' => $date,
                'current_month' => true,
                'past' => $date->isPast() && !$date->isToday(),
                'today' => $date->isToday()
            ];
        }

        // Calculate how many days from next month we need
        $totalDaysDisplayed = count($prevMonthDays) + count($currentMonthDays);
        $remainingDays = 42 - $totalDaysDisplayed; // 6 rows * 7 days

        // Next month days to display
        $nextMonthDays = [];
        $nextMonth = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();

        for ($i = 1; $i <= $remainingDays; $i++) {
            $nextMonthDays[] = [
                'day' => $i,
                'date' => Carbon::createFromDate($nextMonth->year, $nextMonth->month, $i),
                'current_month' => false,
                'past' => false,
                'today' => false
            ];
        }

        $this->daysInMonth = array_merge($prevMonthDays, $currentMonthDays, $nextMonthDays);
    }

    public function loadEvents()
    {
        // In a real app, you would fetch events from the database
        // For now, we'll generate some mock events
        $this->events = $this->generateMockEvents();
    }

    protected function generateMockEvents()
    {
        $events = [];
        $eventTypes = ['session', 'assessment', 'homework', 'meeting'];
        $subjects = ['Mathematics', 'Science', 'English', 'History', 'Programming'];

        // Generate 15-25 random events for the current month
        $numEvents = rand(15, 25);

        for ($i = 0; $i < $numEvents; $i++) {
            $day = rand(1, Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->daysInMonth);
            $hour = rand(9, 18);
            $duration = rand(1, 3);
            $type = $eventTypes[array_rand($eventTypes)];

            // Assign to a random child if we have any
            $childId = $this->children->count() > 0
                ? $this->children[rand(0, $this->children->count() - 1)]->id
                : null;

            $startTime = Carbon::createFromDate($this->currentYear, $this->currentMonth, $day)
                ->setHour($hour)
                ->setMinute(0)
                ->setSecond(0);

            $endTime = (clone $startTime)->addHours($duration);

            // Generate title based on event type
            $title = match($type) {
                'session' => $subjects[array_rand($subjects)] . ' Class',
                'assessment' => $subjects[array_rand($subjects)] . ' Test',
                'homework' => $subjects[array_rand($subjects)] . ' Assignment Due',
                'meeting' => 'Parent-Teacher Meeting',
                default => 'Event'
            };

            $events[] = [
                'id' => $i + 1,
                'title' => $title,
                'child_id' => $childId,
                'child_name' => $childId && $this->children->count() > 0
                    ? $this->children->firstWhere('id', $childId)->name
                    : 'All Children',
                'start_time' => $startTime,
                'end_time' => $endTime,
                'type' => $type,
                'location' => rand(0, 1) ? 'Online' : 'School',
                'description' => 'Description for ' . $title
            ];
        }

        return $events;
    }

    public function getEventsForDay($date)
    {
        $dateString = $date->format('Y-m-d');

        return collect($this->events)->filter(function($event) use ($dateString) {
            $eventDate = Carbon::parse($event['start_time'])->format('Y-m-d');

            // Apply child filter if set
            if ($this->childFilter && $event['child_id'] != $this->childFilter) {
                return false;
            }

            // Apply type filter if set
            if ($this->typeFilter && $event['type'] != $this->typeFilter) {
                return false;
            }

            return $eventDate === $dateString;
        })->all();
    }

    public function previousMonth()
    {
        if ($this->currentMonth == 1) {
            $this->currentMonth = 12;
            $this->currentYear--;
        } else {
            $this->currentMonth--;
        }

        $this->generateCalendarDays();
        $this->loadEvents();
    }

    public function nextMonth()
    {
        if ($this->currentMonth == 12) {
            $this->currentMonth = 1;
            $this->currentYear++;
        } else {
            $this->currentMonth++;
        }

        $this->generateCalendarDays();
        $this->loadEvents();
    }

    public function resetFilters()
    {
        $this->childFilter = '';
        $this->typeFilter = '';
    }

    public function openEventModal($date, $eventId = null)
    {
        $this->selectedDate = Carbon::parse($date);
        $this->showEventModal = true;

        if ($eventId) {
            // Load event details if editing
            $this->selectedEvent = collect($this->events)->firstWhere('id', $eventId);

            $this->eventDetails = [
                'title' => $this->selectedEvent['title'],
                'child_id' => $this->selectedEvent['child_id'],
                'start_time' => Carbon::parse($this->selectedEvent['start_time'])->format('H:i'),
                'end_time' => Carbon::parse($this->selectedEvent['end_time'])->format('H:i'),
                'description' => $this->selectedEvent['description'],
                'type' => $this->selectedEvent['type'],
                'location' => $this->selectedEvent['location']
            ];
        } else {
            // Reset form for new event
            $this->selectedEvent = null;
            $this->eventDetails = [
                'title' => '',
                'child_id' => $this->children->count() > 0 ? $this->children->first()->id : '',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'description' => '',
                'type' => 'session',
                'location' => 'Online'
            ];
        }
    }

    public function closeEventModal()
    {
        $this->showEventModal = false;
        $this->selectedDate = null;
        $this->selectedEvent = null;
    }

    public function saveEvent()
    {
        // In a real app, you would save the event to the database
        // For mock purposes, just display a success message

        $this->closeEventModal();

        // Show success message
        session()->flash('success', 'Event has been saved successfully.');
    }

    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    public function formatTime($time)
    {
        return Carbon::parse($time)->format('h:i A');
    }

    public function getEventTypeClass($type)
    {
        return match($type) {
            'session' => 'bg-primary',
            'assessment' => 'bg-secondary',
            'homework' => 'bg-accent',
            'meeting' => 'bg-info',
            default => 'bg-neutral'
        };
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Calendar</h1>
                <p class="mt-1 text-base-content/70">View and manage your children's learning schedule</p>
            </div>
            <div class="flex gap-2">
                <button onclick="document.getElementById('event-key-modal').showModal()" class="btn btn-outline">
                    <x-icon name="o-information-circle" class="w-4 h-4 mr-2" />
                    Event Key
                </button>
                <a href="{{ route('parents.sessions.requests') }}" class="btn btn-primary">
                    <x-icon name="o-calendar-plus" class="w-4 h-4 mr-2" />
                    Schedule Session
                </a>
            </div>
        </div>

        <!-- Filters and Month Navigation -->
        <div class="p-4 mb-6 shadow-lg bg-base-100 rounded-xl">
            <div class="flex flex-col items-center justify-between gap-4 md:flex-row">
                <!-- Month Navigation -->
                <div class="flex items-center gap-2">
                    <button wire:click="previousMonth" class="btn btn-ghost btn-sm">
                        <x-icon name="o-chevron-left" class="w-5 h-5" />
                    </button>
                    <h2 class="text-xl font-semibold text-center min-w-32">
                        {{ Carbon::createFromDate($currentYear, $currentMonth, 1)->format('F Y') }}
                    </h2>
                    <button wire:click="nextMonth" class="btn btn-ghost btn-sm">
                        <x-icon name="o-chevron-right" class="w-5 h-5" />
                    </button>
                    <button wire:click="$set('currentMonth', {{ now()->month }}); $set('currentYear', {{ now()->year }});" class="ml-2 btn btn-outline btn-sm">
                        Today
                    </button>
                </div>

                <!-- Filters -->
                <div class="flex flex-wrap gap-2">
                    @if(count($children) > 0)
                    <select wire:model.live="childFilter" class="select select-bordered select-sm">
                        <option value="">All Children</option>
                        @foreach($children as $child)
                        <option value="{{ $child->id }}">{{ $child->name }}</option>
                        @endforeach
                    </select>
                    @endif

                    <select wire:model.live="typeFilter" class="select select-bordered select-sm">
                        <option value="">All Events</option>
                        <option value="session">Classes</option>
                        <option value="assessment">Tests</option>
                        <option value="homework">Homework</option>
                        <option value="meeting">Meetings</option>
                    </select>

                    @if($childFilter || $typeFilter)
                    <button wire:click="resetFilters" class="btn btn-ghost btn-sm">
                        <x-icon name="o-x-mark" class="w-4 h-4 mr-1" />
                        Clear Filters
                    </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="overflow-hidden shadow-xl bg-base-100 rounded-xl">
            <!-- Days of Week Headers -->
            <div class="grid grid-cols-7 border-b">
                @foreach(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $dayName)
                <div class="p-2 font-medium text-center">
                    {{ substr($dayName, 0, 3) }}
                </div>
                @endforeach
            </div>

            <!-- Calendar Days Grid -->
            <div class="grid grid-cols-7 grid-rows-6">
                @foreach($daysInMonth as $index => $day)
                <div
                    class="min-h-32 p-1 border-b border-r relative {{ $day['current_month'] ? 'bg-base-100' : 'bg-base-200' }} {{ $day['today'] ? 'ring-2 ring-primary ring-inset' : '' }}"
                    wire:click="openEventModal('{{ $day['date'] }}')"
                >
                    <!-- Day Number -->
                    <div class="flex items-center justify-between">
                        <span class="p-1 text-sm font-semibold {{ $day['current_month'] ? '' : 'opacity-50' }} {{ $day['past'] ? 'text-base-content/50' : '' }}">
                            {{ $day['day'] }}
                        </span>

                        @if($day['today'])
                        <span class="badge badge-primary badge-sm">Today</span>
                        @endif
                    </div>

                    <!-- Events for this day -->
                    <div class="mt-1 space-y-1">
                        @php
                            $dayEvents = $this->getEventsForDay($day['date']);
                        @endphp

                        @foreach(array_slice($dayEvents, 0, 3) as $event)
                            <div
                                class="text-xs p-1 rounded {{ $this->getEventTypeClass($event['type']) }} text-white truncate cursor-pointer"
                                wire:click.stop="openEventModal('{{ $day['date'] }}', {{ $event['id'] }})"
                            >
                                {{ $this->formatTime($event['start_time']) }} - {{ $event['title'] }}
                            </div>
                        @endforeach

                        @if(count($dayEvents) > 3)
                            <div class="p-1 text-xs font-medium text-center">
                                +{{ count($dayEvents) - 3 }} more
                            </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Upcoming Events List -->
        <div class="p-4 mt-8 mb-6 shadow-lg bg-base-100 rounded-xl">
            <h3 class="mb-4 text-xl font-bold">Upcoming Events</h3>

            @php
                $upcomingEvents = collect($this->events)
                    ->filter(function($event) {
                        return Carbon::parse($event['start_time'])->isAfter(now());
                    })
                    ->sortBy('start_time')
                    ->take(5);
            @endphp

            @if($upcomingEvents->count() > 0)
                <div class="space-y-3">
                    @foreach($upcomingEvents as $event)
                        <div class="p-3 transition-colors rounded-lg bg-base-200 hover:bg-base-300">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-8 rounded {{ $this->getEventTypeClass($event['type']) }}"></div>
                                <div class="flex-1">
                                    <div class="font-medium">{{ $event['title'] }}</div>
                                    <div class="text-sm opacity-70">
                                        {{ $this->formatDate($event['start_time']) }} at {{ $this->formatTime($event['start_time']) }}
                                    </div>
                                </div>
                                <div class="badge">{{ $event['child_name'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-4 text-center">
                    <p class="text-base-content/70">No upcoming events</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Event Details Modal -->
    <div class="modal {{ $showEventModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">
                {{ $selectedEvent ? 'Event Details' : 'Add New Event' }}
            </h3>

            @if($selectedDate)
            <p class="mt-1 text-sm opacity-70">
                {{ $this->formatDate($selectedDate) }}
            </p>
            @endif

            <div class="mt-4 space-y-4">
                <!-- Event Form -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Event Title</span>
                    </label>
                    <input
                        type="text"
                        wire:model="eventDetails.title"
                        class="input input-bordered"
                        placeholder="Enter event title"
                    >
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Child</span>
                    </label>
                    <select wire:model="eventDetails.child_id" class="select select-bordered">
                        <option value="">All Children</option>
                        @foreach($children as $child)
                        <option value="{{ $child->id }}">{{ $child->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Start Time</span>
                        </label>
                        <input
                            type="time"
                            wire:model="eventDetails.start_time"
                            class="input input-bordered"
                        >
                    </div>
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">End Time</span>
                        </label>
                        <input
                            type="time"
                            wire:model="eventDetails.end_time"
                            class="input input-bordered"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Event Type</span>
                        </label>
                        <select wire:model="eventDetails.type" class="select select-bordered">
                            <option value="session">Class Session</option>
                            <option value="assessment">Test/Assessment</option>
                            <option value="homework">Homework</option>
                            <option value="meeting">Meeting</option>
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Location</span>
                        </label>
                        <select wire:model="eventDetails.location" class="select select-bordered">
                            <option value="Online">Online</option>
                            <option value="School">School</option>
                            <option value="Home">Home</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Description</span>
                    </label>
                    <textarea
                        wire:model="eventDetails.description"
                        class="h-24 textarea textarea-bordered"
                        placeholder="Enter event details"
                    ></textarea>
                </div>
            </div>

            <div class="modal-action">
                <button wire:click="closeEventModal" class="btn">Cancel</button>
                <button wire:click="saveEvent" class="btn btn-primary">Save Event</button>
            </div>
        </div>
        <div class="modal-backdrop" wire:click="closeEventModal"></div>
    </div>

    <!-- Event Key Modal -->
    <dialog id="event-key-modal" class="modal">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Event Key</h3>
            <div class="mt-4 space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-4 h-4 rounded bg-primary"></div>
                    <span>Class Sessions</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-4 h-4 rounded bg-secondary"></div>
                    <span>Tests & Assessments</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-4 h-4 rounded bg-accent"></div>
                    <span>Homework Deadlines</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-4 h-4 rounded bg-info"></div>
                    <span>Meetings</span>
                </div>
            </div>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn">Close</button>
                </form>
            </div>
        </div>
    </dialog>
</div>
