<?php

namespace App\View\Components\Calendar;

use Carbon\Carbon;
use Illuminate\View\Component;

class WeekView extends Component
{
    public $selectedDate;
    public $events;
    public $timeSlots;

    public function __construct($selectedDate = null, $events = [])
    {
        $this->selectedDate = $selectedDate ?? now();
        $this->events = $events;
        $this->timeSlots = $this->generateTimeSlots();
    }

    protected function generateTimeSlots()
    {
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
    }

    public function getWeekDays()
    {
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
    }

    protected function getEventsForDate($date)
    {
        return collect($this->events)->filter(function ($event) use ($date) {
            return Carbon::parse($event['start_time'])->format('Y-m-d') === $date;
        })->values()->all();
    }

    public function render()
    {
        return view('components.calendar.week-view', [
            'days' => $this->getWeekDays()
        ]);
    }
}
