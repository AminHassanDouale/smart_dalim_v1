<?php
use Livewire\Volt\Component;
use App\Models\{TeacherProfile, Schedule, Course, Children, ParentProfile};
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Mary\Traits\Toast;

new class extends Component {
    use WithPagination, Toast;

    public $children = [];
    public $childrenData = [];
    public $teachers = [];
    public $classes = [];
    public $schedules = [];
    public $showCreateModal = false;
    public $searchTerm = '';
    public $filterDay = '';

    public $newSchedule = [
        'student_id' => '',
        'teacher_id' => '',
        'class_id' => '',
        'day' => '',
        'start_time' => '',
        'end_time' => '',
    ];

    public function mount()
    {
        if (!Auth::check() || !Auth::user()->hasRole('parent')) {
            redirect()->route('login');
        }

        $parentProfile = ParentProfile::where('user_id', Auth::id())->first();
        if (!$parentProfile) {
            redirect()->route('parents.profile-setup');
        }

        $children = Children::with(['teacher', 'subjects'])
            ->where('parent_profile_id', $parentProfile->id)
            ->get();

        foreach ($children as $child) {
            $this->children[$child->id] = $child->name;

            $times = is_string($child->available_times) ?
                json_decode($child->available_times, true) :
                $child->available_times;

            $this->childrenData[$child->id] = [
                'name' => $child->name,
                'available_times' => $times ?? [],
                'teacher_name' => $child->teacher?->name,
                'subjects' => $child->subjects->pluck('name')->toArray()
            ];
        }

        $teacherRecords = TeacherProfile::with(['user'])
            ->where('status', TeacherProfile::STATUS_VERIFIED)
            ->get();
        foreach ($teacherRecords as $teacher) {
            $this->teachers[$teacher->id] = $teacher->user->name;
        }

        $courseRecords = Course::where('status', 'active')->get();
        foreach ($courseRecords as $course) {
            $this->classes[$course->id] = $course->name;
        }

        if (empty($this->children)) {
            $this->toast()->warning('Please add your children first');
            redirect()->route('parents.profile-setup');
        }

        $this->loadSchedules();
    }

    public function loadSchedules()
    {
        $query = Schedule::with(['student', 'teacher.user', 'class'])
            ->whereIn('student_id', array_keys($this->children));

        if ($this->searchTerm) {
            $query->where(function($q) {
                $q->whereHas('student', fn($sq) => $sq->where('name', 'like', "%{$this->searchTerm}%"))
                  ->orWhereHas('teacher.user', fn($sq) => $sq->where('name', 'like', "%{$this->searchTerm}%"))
                  ->orWhereHas('class', fn($sq) => $sq->where('name', 'like', "%{$this->searchTerm}%"));
            });
        }

        if ($this->filterDay) {
            $query->where('day', $this->filterDay);
        }

        $this->schedules = $query->orderBy('day')
                                ->orderBy('start_time')
                                ->get();
    }

    private function hasScheduleConflict()
    {
        return Schedule::where('student_id', $this->newSchedule['student_id'])
            ->where('day', $this->newSchedule['day'])
            ->where(function ($query) {
                $query->whereBetween('start_time', [
                        $this->newSchedule['start_time'],
                        $this->newSchedule['end_time']
                    ])
                    ->orWhereBetween('end_time', [
                        $this->newSchedule['start_time'],
                        $this->newSchedule['end_time']
                    ]);
            })->exists();
    }

    private function isTimeAvailable($childId, $day, $time)
    {
        if (!isset($this->childrenData[$childId]['available_times'][$day])) {
            return false;
        }

        $times = $this->childrenData[$childId]['available_times'][$day];
        if (!is_array($times)) return false;

        foreach ($times as $period) {
            if (isset($period['start'], $period['end'])) {
                if ($time >= $period['start'] && $time <= $period['end']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function createSchedule()
    {
        $this->validate([
            'newSchedule.student_id' => [
                'required',
                'exists:children,id',
                function ($attribute, $value, $fail) {
                    if (!isset($this->children[$value])) {
                        $this->toast()->error('Invalid child selected');
                        $fail('Unauthorized child selection');
                    }
                },
            ],
            'newSchedule.teacher_id' => 'required|exists:teacher_profiles,id',
            'newSchedule.class_id' => 'required|exists:courses,id',
            'newSchedule.day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'newSchedule.start_time' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!$this->isTimeAvailable(
                        $this->newSchedule['student_id'],
                        $this->newSchedule['day'],
                        $value
                    )) {
                        $fail('Selected time is not within available hours');
                    }
                }
            ],
            'newSchedule.end_time' => 'required|after:newSchedule.start_time',
        ]);

        if ($this->hasScheduleConflict()) {
            $this->toast()->error('Time slot conflicts with existing schedule');
            return;
        }

        Schedule::create($this->newSchedule);
        $this->reset('newSchedule', 'showCreateModal');
        $this->loadSchedules();
        $this->toast()->success('Schedule created successfully');
    }

    public function deleteSchedule($scheduleId)
    {
        $schedule = Schedule::findOrFail($scheduleId);
        if (!isset($this->children[$schedule->student_id])) {
            $this->toast()->error('Unauthorized action');
            return;
        }

        $schedule->delete();
        $this->loadSchedules();
        $this->toast()->success('Schedule removed successfully');
    }
}; ?>
<div>
    <x-header title="Learning Schedule Management" separator>
        <x-slot:actions>
            <div class="flex gap-4">
                <x-input
                    wire:model.live="searchTerm"
                    placeholder="Search schedules..."
                    icon="c-magnifying-glass"
                    class="w-64"
                />
                <x-select
                    wire:model.live="filterDay"
                    :options="[
                        '' => 'All Days',
                        'monday' => 'Monday',
                        'tuesday' => 'Tuesday',
                        'wednesday' => 'Wednesday',
                        'thursday' => 'Thursday',
                        'friday' => 'Friday',
                        'saturday' => 'Saturday',
                        'sunday' => 'Sunday'
                    ]"
                    class="w-40"
                />
                <x-button wire:click="$toggle('showCreateModal')" icon="s-plus-circle" color="primary">
                    Create Schedule
                </x-button>
            </div>
        </x-slot:actions>
    </x-header>

    <div class="p-4 space-y-6">
        <x-card title="Student Information" class="bg-white">
            <div class="space-y-4">
                @foreach($childrenData as $childId => $child)
                    <div class="p-4 transition border rounded-lg hover:shadow-md">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">{{ $child['name'] }}</h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    <span class="font-medium">Assigned Teacher:</span>
                                    {{ $child['teacher_name'] ?? 'Not Assigned' }}
                                </p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h4 class="mb-2 text-lg font-semibold text-gray-800">Enrolled Subjects</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach($child['subjects'] ?? [] as $subject)
                                    <x-badge color="success">{{ $subject }}</x-badge>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h4 class="mb-2 text-lg font-semibold text-gray-800">Available Times</h4>
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
                                @if(isset($child['available_times']) && is_array($child['available_times']))
                                    @foreach($child['available_times'] as $day => $times)
                                        @if(is_array($times) && !empty($times))
                                            <div class="p-2 border rounded hover:bg-gray-50">
                                                <div class="font-medium text-indigo-600">{{ ucfirst($day) }}</div>
                                                @foreach($times as $time)
                                                    @if(isset($time['start']) && isset($time['end']))
                                                        <div class="text-sm text-gray-600">
                                                            {{ date('H:i', strtotime($time['start'])) }} -
                                                            {{ date('H:i', strtotime($time['end'])) }}
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card title="Current Learning Schedules" class="bg-white">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-4 font-bold text-left text-gray-900">Student</th>
                            <th class="p-4 font-bold text-left text-gray-900">Teacher</th>
                            <th class="p-4 font-bold text-left text-gray-900">Subject</th>
                            <th class="p-4 font-bold text-left text-gray-900">Day</th>
                            <th class="p-4 font-bold text-left text-gray-900">Time Slot</th>
                            <th class="p-4 font-bold text-left text-gray-900">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($schedules as $schedule)
                            <tr class="hover:bg-gray-50">
                                <td class="p-4">
                                    <x-badge>{{ $schedule->student->name }}</x-badge>
                                </td>
                                <td class="p-4">{{ $schedule->teacher->user->name }}</td>
                                <td class="p-4">{{ $schedule->class->name }}</td>
                                <td class="p-4">
                                    <x-badge color="info">{{ ucfirst($schedule->day) }}</x-badge>
                                </td>
                                <td class="p-4">
                                    {{ date('H:i', strtotime($schedule->start_time)) }} -
                                    {{ date('H:i', strtotime($schedule->end_time)) }}
                                </td>
                                <td class="p-4">
                                    <x-button
                                        wire:click="deleteSchedule({{ $schedule->id }})"
                                        wire:confirm="Are you sure you want to delete this schedule?"
                                        color="red"
                                        icon="trash"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-center text-gray-500">
                                    No schedules found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <x-modal wire:model="showCreateModal">
        <x-card title="Create New Schedule">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <x-select
                        label="Child"
                        wire:model="newSchedule.student_id"
                        :options="$children"
                        placeholder="Select Child"
                    />
                </div>
                <div>
                    <x-select
                        label="Teacher"
                        wire:model="newSchedule.teacher_id"
                        :options="$teachers"
                        placeholder="Select Teacher"
                    />
                </div>
                <div>
                    <x-select
                        label="Class"
                        wire:model="newSchedule.class_id"
                        :options="$classes"
                        placeholder="Select Class"
                    />
                </div>
                <div>
                    <x-select
                        label="Day"
                        wire:model="newSchedule.day"
                        :options="[
                            'monday' => 'Monday',
                            'tuesday' => 'Tuesday',
                            'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday',
                            'friday' => 'Friday',
                            'saturday' => 'Saturday',
                            'sunday' => 'Sunday'
                        ]"
                        placeholder="Select Day"
                    />
                </div>
                <div>
                    <x-datetime
                        label="Start Time"
                        wire:model="newSchedule.start_time"
                        icon="o-calendar"
                        type="time"
                    />
                </div>
                <div>
                    <x-datetime
                        label="End Time"
                        wire:model="newSchedule.end_time"
                        icon="o-calendar"
                        type="time"
                    />
                </div>
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-4">
                    <x-button wire:click="$toggle('showCreateModal')">Cancel</x-button>
                    <x-button wire:click="createSchedule" color="primary">Create</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </x-modal>
</div>
