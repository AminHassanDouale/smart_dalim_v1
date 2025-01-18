<div class="bg-white rounded-lg shadow">
    <!-- Calendar Header -->
    <div class="flex items-center justify-between p-4 border-b">
        <div class="grid w-full grid-cols-8 text-sm">
            <div class="col-span-1"></div> <!-- Time column -->
            @foreach($days as $day)
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
                @foreach($timeSlots as $slot)
                    <div class="h-20 p-2 text-sm text-gray-500 border-b">
                        {{ $slot['label'] }}
                    </div>
                @endforeach
            </div>

            <!-- Days columns -->
            @foreach($days as $day)
                <div class="col-span-1">
                    @foreach($timeSlots as $slot)
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
