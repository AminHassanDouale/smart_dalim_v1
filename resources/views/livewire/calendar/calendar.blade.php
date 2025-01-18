<?php

use function Livewire\Volt\{state, computed};
use App\Models\LearningSession;
use Carbon\Carbon;

state([
    'view' => 'week',
    'selectedDate' => null,
    'events' => [],
    'childId' => null
]);

$mount = function($childId = null) {
    $this->childId = $childId;
    $this->selectedDate = now();
    $this->loadEvents();
};

$loadEvents = function() {
    $this->events = LearningSession::query()
        ->when($this->childId, function ($query) {
            return $query->where('children_id', $this->childId);
        })
        ->with(['subject', 'teacher'])
        ->whereBetween('start_time', [
            Carbon::parse($this->selectedDate)->startOfWeek(),
            Carbon::parse($this->selectedDate)->endOfWeek()
        ])
        ->get()
        ->map(function ($session) {
            return [
                'id' => $session->id,
                'subject' => $session->subject->name,
                'teacher' => $session->teacher->name,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'status' => $session->status
            ];
        })
        ->toArray();
};

$previousWeek = function() {
    $this->selectedDate = Carbon::parse($this->selectedDate)->subWeek();
    $this->loadEvents();
};

$nextWeek = function() {
    $this->selectedDate = Carbon::parse($this->selectedDate)->addWeek();
    $this->loadEvents();
};

$today = function() {
    $this->selectedDate = now();
    $this->loadEvents();
};

$timeSlots = computed(function() {
    $slots = [];
    $start = 8; // 8 AM
    $end = 20;  // 8 PM

    for ($hour = $start; $hour <= $end; $hour++) {
        $slots[] = [
            'time' => sprintf('%02d:00', $hour),
            'label' => sprintf('%d:00 %s',
                $hour > 12 ? $hour - 12 : $hour,
                $hour >= 12 ? 'PM' : 'AM'
            )
        ];
    }

    return $slots;
});

$weekDays = computed(function() {
    $startOfWeek = Carbon::parse($this->selectedDate)->startOfWeek();
    $endOfWeek = Carbon::parse($this->selectedDate)->endOfWeek();

    $days = [];
    for ($date = $startOfWeek->copy(); $date->lte($endOfWeek); $date->addDay()) {
        $days[] = [
            'date' => $date->format('Y-m-d'),
            'dayName' => $date->format('D'),
            'dayNumber' => $date->format('d'),
            'isToday' => $date->isToday(),
            'events' => $this->getEventsForDate($date->format('Y-m-d'))
        ];
    }

    return $days;
});

$getEventsForDate = function($date) {
    return collect($this->events)->filter(function ($event) use ($date) {
        return Carbon::parse($event['start_time'])->format('Y-m-d') === $date;
    })->values()->all();
};

?>

<div>
    <div class="flex items-center justify-between mb-4">
        <!-- Calendar Navigation -->
        <div class="flex items-center space-x-4">
            <x-button
                icon="o-chevron-left"
                wire:click="previousWeek"
                class="btn-secondary"
                size="sm"
            />

            <x-button
                label="Today"
                wire:click="today"
                class="btn-secondary"
                size="sm"
            />

            <x-button
                icon="o-chevron-right"
                wire:click="nextWeek"
                class="btn-secondary"
                size="sm"
            />

            <span class="text-lg font-semibold">
                {{ Carbon\Carbon::parse($selectedDate)->format('F Y') }}
            </span>
        </div>

        <!-- View Toggle -->
        <div class="flex space-x-2">
            <x-button
                label="Week"
                class="btn-secondary"
                :active="$view === 'week'"
                wire:click="$set('view', 'week')"
                size="sm"
            />
            <x-button
                label="Month"
                class="btn-secondary"
                :active="$view === 'month'"
                wire:click="$set('view', 'month')"
                size="sm"
            />
        </div>
    </div>

    <!-- Calendar View -->
    <div wire:loading.class="opacity-50">
        @if($view === 'week')
            <div class="bg-white rounded-lg shadow">
                <!-- Calendar Header -->
                <div class="flex items-center justify-between p-4 border-b">
                    <div class="grid w-full grid-cols-8 text-sm">
                        <div class="col-span-1"></div> <!-- Time column -->
                        @foreach($this->weekDays as $day)
                            <div class="col-span-1 text-center">
                                <div class="font-medium">{{ $day['dayName'] }}</div>
                                <div class="@if($day['isToday']) bg-primary-100 text-primary-700 rounded-full w-7 h-7 flex items-center justify-center mx-auto @endif">
                                    {{ $day['dayNumber'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="overflow-auto max-h-[600px]">
                    <div class="grid grid-cols-8 divide-x divide-gray-200">
                        <!-- Time slots -->
                        <div class="col-span-1">
                            @foreach($this->timeSlots as $slot)
                                <div class="h-20 p-2 text-sm text-gray-500 border-b">
                                    {{ $slot['label'] }}
                                </div>
                            @endforeach
                        </div>

                        <!-- Days columns -->
                        @foreach($this->weekDays as $day)
                            <div class="col-span-1">
                                @foreach($this->timeSlots as $slot)
                                    <div class="relative h-20 border-b group">
                                        @foreach($day['events'] as $event)
                                            @if(Carbon\Carbon::parse($event['start_time'])->format('H:i') === $slot['time'])
                                                <div class="absolute inset-x-0 px-2 py-1 mx-1 text-xs border rounded-lg bg-primary-100 border-primary-200">
                                                    <div class="font-medium text-primary-700">
                                                        {{ $event['subject'] }}
                                                    </div>
                                                    <div class="text-primary-600">
                                                        {{ Carbon\Carbon::parse($event['start_time'])->format('g:i A') }}
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <!-- Month view implementation here -->
            <div class="py-4 text-center">
                Month view coming soon...
            </div>
        @endif
    </div>

    <!-- Loading Indicator -->
    <div wire:loading class="fixed top-0 left-0 right-0">
        <div class="h-1 bg-primary-500 animate-pulse"></div>
    </div>
</div>
