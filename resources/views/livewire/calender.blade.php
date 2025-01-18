<?php

use function Livewire\Volt\{state, computed};
use App\Models\LearningSession;
use Carbon\Carbon;

// Define component state
state([
    'selectedDate' => null,
    'events' => [],
    'childId' => null
]);

// Initialize component
$mount = function($childId = null) {
    $this->childId = $childId;
    $this->selectedDate = now();
    $this->loadEvents();
};

// Load events for the selected week
$loadEvents = function() {
    $startOfWeek = Carbon::parse($this->selectedDate)->startOfWeek();
    $endOfWeek = Carbon::parse($this->selectedDate)->endOfWeek();

    $this->events = LearningSession::query()
        ->where('children_id', $this->childId)
        ->whereBetween('start_time', [$startOfWeek, $endOfWeek])
        ->with(['subject', 'teacher'])
        ->get()
        ->map(function ($session) {
            return [
                'id' => $session->id,
                'title' => $session->subject->name,
                'teacher' => $session->teacher->name,
                'start' => $session->start_time->format('Y-m-d H:i'),
                'end' => $session->end_time->format('Y-m-d H:i'),
                'status' => $session->status,
            ];
        })
        ->toArray();
};

// Navigation functions
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

?>

<div class="bg-white rounded-lg shadow-md">
    <!-- Calendar Header -->
    <div class="p-4 border-b">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <x-button wire:click="previousWeek" icon="o-chevron-left" class="btn-secondary" />
                <x-button wire:click="today" class="btn-secondary">Today</x-button>
                <x-button wire:click="nextWeek" icon="o-chevron-right" class="btn-secondary" />
                <h2 class="text-lg font-semibold">
                    {{ Carbon\Carbon::parse($selectedDate)->format('F Y') }}
                </h2>
            </div>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="overflow-x-auto">
        <div class="grid grid-cols-8 divide-x divide-gray-200">
            <!-- Time Column -->
            <div class="col-span-1">
                @foreach(range(8, 20) as $hour)
                    <div class="h-20 p-2 text-sm text-gray-500 border-b">
                        {{ sprintf('%02d:00', $hour) }}
                    </div>
                @endforeach
            </div>

            <!-- Days Columns -->
            @foreach(range(0, 6) as $dayOffset)
                @php
                    $currentDate = Carbon::parse($selectedDate)->startOfWeek()->addDays($dayOffset);
                    $dayEvents = collect($events)->filter(function($event) use ($currentDate) {
                        return Carbon::parse($event['start'])->isSameDay($currentDate);
                    });
                @endphp
                <div class="col-span-1">
                    <!-- Day Header -->
                    <div class="p-2 text-center border-b">
                        <div class="font-medium">{{ $currentDate->format('D') }}</div>
                        <div class="@if($currentDate->isToday()) bg-primary-100 text-primary-700 rounded-full w-7 h-7 flex items-center justify-center mx-auto @endif">
                            {{ $currentDate->format('d') }}
                        </div>
                    </div>

                    <!-- Time Slots -->
                    @foreach(range(8, 20) as $hour)
                        <div class="relative h-20 border-b">
                            @foreach($dayEvents as $event)
                                @if(Carbon::parse($event['start'])->format('H') == $hour)
                                    <div class="absolute inset-x-0 p-2 m-1 text-xs rounded bg-primary-100">
                                        <div class="font-medium text-primary-700">{{ $event['title'] }}</div>
                                        <div class="text-primary-600">{{ Carbon::parse($event['start'])->format('g:i A') }}</div>
                                        <div class="text-primary-600">{{ $event['teacher'] }}</div>
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
