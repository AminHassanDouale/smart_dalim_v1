<?php
namespace App\Livewire;

use App\Models\LearningSession;
use App\Models\Subject;
use App\Models\User;
use App\Models\Children;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;


    public $showFilters = false;
    public $selectedChild = null;
    public $filters = [
        'status' => '',
        'subject' => '',
        'teacher' => '',
        'attended' => ''
    ];

    public array $sort = [
        'field' => 'start_time',
        'direction' => 'desc'
    ];

    #[Computed]
    public function headers(): array
    {
        return [
            [
                'key' => 'subject',
                'label' => 'Subject',
                'sort' => 'subject_name',
            ],
            [
                'key' => 'teacher',
                'label' => 'Teacher',
                'sort' => 'teacher_name',
            ],
            [
                'key' => 'start_time',
                'label' => 'Start Time',
                'sort' => 'start_time'
            ],
            [
                'key' => 'duration',
                'label' => 'Duration',
            ],
            [
                'key' => 'status',
                'label' => 'Status',
                'sort' => 'status'
            ],
            [
                'key' => 'attended',
                'label' => 'Attendance',
                'sort' => 'attended'
            ]
        ];
    }

    #[Computed]
    public function sessions(): LengthAwarePaginator
    {
        return LearningSession::query()
            ->when($this->selectedChild, fn($query) => $query->where('children_id', $this->selectedChild))
            ->when(!$this->selectedChild, fn($query) => $query->whereIn('children_id', auth()->user()->parentProfile->children->pluck('id')))
            ->when($this->filters['status'], fn($query, $status) => $query->where('status', $status))
            ->when($this->filters['subject'], fn($query, $subject) => $query->whereHas('subject', fn($q) => $q->where('id', $subject)))
            ->when($this->filters['teacher'], fn($query, $teacher) => $query->whereHas('teacher', fn($q) => $q->where('id', $teacher)))
            ->when($this->filters['attended'] !== '', fn($query) => $query->where('attended', $this->filters['attended']))
            ->select('learning_sessions.*')
            ->selectRaw('(SELECT name FROM subjects WHERE id = learning_sessions.subject_id) as subject_name')
            ->selectRaw('(SELECT name FROM users WHERE id = learning_sessions.teacher_id) as teacher_name')
            ->orderBy($this->sort['field'], $this->sort['direction'])
            ->paginate(10)
            ->through(fn($session) => [
                'id' => $session->id,
                'subject' => $session->subject_name,
                'teacher' => $session->teacher_name,
                'start_time' => $session->start_time,
                'duration' => $session->end_time ? Carbon::parse($session->start_time)->diffInHours(Carbon::parse($session->end_time)) : null,
                'status' => $session->status,
                'attended' => $session->attended
            ]);
    }


    #[Computed]
    public function children()
    {
        return Children::where('parent_profile_id', auth()->user()->parentProfile->id)
            ->get()
            ->map(fn($child) => [
                'id' => $child->id,
                'name' => $child->name
            ]);
    }

    public function resetFilters(): void
    {
        $this->reset(['filters', 'selectedChild']);
    }

    public function with(): array
    {
        return [
            'statuses' => [
                ['id' => 'scheduled', 'name' => 'Scheduled'],
                ['id' => 'completed', 'name' => 'Completed'],
                ['id' => 'cancelled', 'name' => 'Cancelled'],
            ],
            'subjects' => Subject::select('id', 'name')->get()
                ->map(fn($subject) => ['id' => $subject->id, 'name' => $subject->name]),
            'teachers' => User::where('role', User::ROLE_TEACHER)
                ->select('id', 'name')
                ->get()
                ->map(fn($teacher) => ['id' => $teacher->id, 'name' => $teacher->name]),
        ];
    }
}
?>

<div>
    <x-header title="Schedule Management" separator>
        <x-slot:actions>
            <x-select
                label="Select Child"
                :options="$this->children"
                wire:model.live="selectedChild"
                placeholder="All Children"
                class="w-48"
            />


            <x-button
                icon="o-funnel"
                class="ml-2"
                wire:click="$toggle('showFilters')"
            >
                Filters
            </x-button>
        </x-slot:actions>
    </x-header>

    <!-- Filter Drawer -->
    <x-drawer wire:model="showFilters" title="Filters">
        <div class="space-y-6 p-4">
            <x-select
                label="Status"
                :options="$statuses"
                wire:model.live="filters.status"
            />

            <x-select
                label="Subject"
                :options="$subjects"
                wire:model.live="filters.subject"
            />

            <x-select
                label="Teacher"
                :options="$teachers"
                wire:model.live="filters.teacher"
            />

            <x-select
                label="Attendance"
                :options="[
                    ['id' => '1', 'name' => 'Attended'],
                    ['id' => '0', 'name' => 'Not Attended']
                ]"
                wire:model.live="filters.attended"
            />

            <x-button
                wire:click="resetFilters"
                class="w-full"
                variant="secondary"
            >
                Reset Filters
            </x-button>
        </div>
    </x-drawer>

    <div class="bg-white rounded-lg shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach($this->headers as $header)
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                wire:click="$set('sort', ['field' => '{{ $header['sort'] ?? $header['key'] }}', 'direction' => '{{ $sort['direction'] === 'asc' ? 'desc' : 'asc' }}'])">
                                <div class="flex items-center space-x-1">
                                    <span>{{ $header['label'] }}</span>
                                    @if(isset($header['sort']))
                                        <span class="text-gray-400">
                                            @if($sort['field'] === ($header['sort'] ?? $header['key']))
                                                {!! $sort['direction'] === 'asc' ? '↑' : '↓' !!}
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->sessions as $session)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $session['subject'] ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                {{ $session['teacher'] ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ Carbon::parse($session['start_time'])->format('M d, Y H:i') }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ $session['duration'] }} hours
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                {{ $session['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                   ($session['status'] === 'cancelled' ? 'bg-red-100 text-red-800' :
                                   'bg-yellow-100 text-yellow-800') }}">
                                {{ ucfirst($session['status']) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                {{ $session['attended'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $session['attended'] ? 'Attended' : 'Not Attended' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($this->headers) }}" class="px-6 py-4 text-center text-gray-500">
                            No sessions found
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-200">
            {{ $this->sessions->links() }}        </div>
    </div>

</div>
