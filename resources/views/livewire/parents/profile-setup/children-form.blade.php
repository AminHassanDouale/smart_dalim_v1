<?php

use function Livewire\Volt\{state, computed, rules, uses};
use App\Models\ParentProfile;
use App\Models\Children;
use App\Models\User;
use App\Models\Subject;

state([
    'children' => [],
    'availableTeachers' => [],
    'availableSubjects' => [],
    'showDeleteModal' => false,
    'childIndexToDelete' => null,
    'initialized' => false,
    'availableTimes' => [
        'morning' => ['08:00', '09:00', '10:00', '11:00'],
        'afternoon' => ['13:00', '14:00', '15:00', '16:00'],
        'evening' => ['17:00', '18:00', '19:00', '20:00']
    ]
]);

rules([
    'children.*.name' => ['required', 'string', 'max:255'],
    'children.*.age' => ['required', 'integer', 'min:4', 'max:18'],
    'children.*.teacher_id' => ['required', 'exists:users,id'],
    'children.*.available_times' => ['required', 'array', 'min:1'],
    'children.*.selected_subjects' => ['required', 'array', 'min:1'],
]);

$initializeChildren = function () {
    if (!$this->initialized) {
        $this->loadChildren();
        $this->initialized = true;
    }
};

$loadChildren = function () {
    $profile = auth()->user()->parentProfile;
    $this->children = []; // Reset children array

    $existingChildren = Children::where('parent_profile_id', $profile->id)->get();

    if ($existingChildren->count() > 0) {
        foreach ($existingChildren as $child) {
            $this->children[] = [
                'id' => $child->id,
                'name' => $child->name,
                'age' => $child->age,
                'teacher_id' => $child->teacher_id,
                'available_times' => $child->available_times ?? [],
                'selected_subjects' => $child->subjects->pluck('id')->toArray(),
            ];
        }
    } else {
        for ($i = 0; $i < $profile->number_of_children; $i++) {
            $this->children[] = [
                'id' => null,
                'name' => '',
                'age' => '',
                'teacher_id' => '',
                'available_times' => [],
                'selected_subjects' => [],
            ];
        }
    }

    $this->availableTeachers = User::where('role', 'teacher')
        ->select('id', 'name')
        ->get()
        ->toArray();

    $this->availableSubjects = Subject::select('id', 'name')
        ->get()
        ->toArray();
};

$addChild = function () {
    $this->children[] = [
        'id' => null,
        'name' => '',
        'age' => '',
        'teacher_id' => '',
        'available_times' => [],
        'selected_subjects' => [],
    ];
};

$confirmDelete = function ($index) {
    if (count($this->children) > 1) {
        $this->childIndexToDelete = $index;
        $this->showDeleteModal = true;
    }
};

$removeChild = function () {
    if ($this->childIndexToDelete !== null && count($this->children) > 1) {
        $childId = $this->children[$this->childIndexToDelete]['id'] ?? null;

        if ($childId) {
            Children::find($childId)->delete();
        }

        unset($this->children[$this->childIndexToDelete]);
        $this->children = array_values($this->children);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Child removed successfully'
        ]);

        $this->showDeleteModal = false;
        $this->childIndexToDelete = null;
    }
};

$cancelDelete = function () {
    $this->showDeleteModal = false;
    $this->childIndexToDelete = null;
};

$toggleTime = function ($childIndex, $time) {
    if (!isset($this->children[$childIndex]['available_times'])) {
        $this->children[$childIndex]['available_times'] = [];
    }

    $times = $this->children[$childIndex]['available_times'];

    if (in_array($time, $times)) {
        $times = array_diff($times, [$time]);
    } else {
        $times[] = $time;
    }

    $this->children[$childIndex]['available_times'] = array_values($times);
};

$isTimeSelected = fn ($childIndex, $time) =>
    isset($this->children[$childIndex]['available_times']) &&
    in_array($time, $this->children[$childIndex]['available_times']);

$saveChildren = function () {
    $this->validate();

    try {
        $profile = auth()->user()->parentProfile;

        foreach ($this->children as $childData) {
            $child = Children::updateOrCreate(
                ['id' => $childData['id'] ?? null],
                [
                    'parent_profile_id' => $profile->id,
                    'name' => $childData['name'],
                    'age' => $childData['age'],
                    'teacher_id' => $childData['teacher_id'],
                    'available_times' => $childData['available_times'],
                ]
            );

            $child->subjects()->sync($childData['selected_subjects']);
        }

        $profile->update([
            'number_of_children' => count($this->children)
        ]);

        return $this->redirect(route('parents.dashboard'), navigate: true);
    } catch (\Exception $e) {
        $this->dispatch('notify', [
            'type' => 'error',
            'message' => 'Error saving children: ' . $e->getMessage()
        ]);
    }
};

?>

<div wire:init="initializeChildren" class="max-w-4xl mx-auto">
    @if($initialized)
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold">Step 2: Children Information</h2>
            <x-button
                label="Add Another Child"
                icon="o-plus"
                wire:click="addChild"
                class="btn-secondary"
            />
        </div>

        <!-- Delete Confirmation Modal -->
        <x-modal
            wire:model="showDeleteModal"
            title="Confirm Delete"
            subtitle="Are you sure you want to remove this child?"
            separator
        >
            <div>
                @if($childIndexToDelete !== null && isset($children[$childIndexToDelete]))
                    <p>You are about to remove {{ $children[$childIndexToDelete]['name'] ?: 'this child' }} from your profile. This action cannot be undone.</p>
                @endif
            </div>

            <x-slot:actions>
                <x-button label="Cancel" wire:click="cancelDelete" />
                <x-button
                    label="Delete"
                    class="btn-error"
                    wire:click="removeChild"
                />
            </x-slot:actions>
        </x-modal>

        <x-form wire:submit="saveChildren">
            <div class="space-y-8">
                @foreach($children as $index => $child)
                    <div class="p-6 space-y-4 rounded-lg bg-gray-50">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Child {{ $index + 1 }}</h3>
                            @if(count($children) > 1)
                                <x-button
                                    icon="o-x-mark"
                                    wire:click="confirmDelete({{ $index }})"
                                    class="text-red-600 btn-ghost"
                                    size="sm"
                                />
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <x-input
                                label="Name"
                                wire:model="children.{{ $index }}.name"
                                required
                            />

                            <x-input
                                type="number"
                                label="Age"
                                wire:model="children.{{ $index }}.age"
                                min="4"
                                max="18"
                                required
                            />
                        </div>

                        <!-- Subject Selection -->
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-gray-700">Select Subjects</label>
                            <div class="grid grid-cols-2 gap-4">
                                @foreach($availableSubjects as $subject)
                                    <div class="p-3 border rounded-lg {{ in_array($subject['id'], $children[$index]['selected_subjects'] ?? []) ? 'border-primary-500 bg-primary-50' : 'border-gray-200' }}">
                                        <x-checkbox
                                            label="{{ $subject['name'] }}"
                                            wire:model="children.{{ $index }}.selected_subjects"
                                            value="{{ $subject['id'] }}"
                                        />
                                    </div>
                                @endforeach
                            </div>
                            @error("children.{$index}.selected_subjects")
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Teacher Assignment -->
                        <x-select
                            label="Assign Teacher"
                            wire:model="children.{{ $index }}.teacher_id"
                            :options="$availableTeachers"
                            option-label="name"
                            option-value="id"
                            placeholder="Select a teacher"
                            required
                        />

                        <!-- Available Times -->
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-gray-700">Available Times</label>
                            <div class="space-y-4">
                                @foreach($availableTimes as $period => $times)
                                    <div>
                                        <h4 class="mb-2 text-sm font-medium capitalize">{{ $period }}</h4>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($times as $time)
                                                <button
                                                    type="button"
                                                    wire:click="toggleTime({{ $index }}, '{{ $time }}')"
                                                    class="px-4 py-2 text-sm rounded-lg transition-colors duration-200 {{ $this->isTimeSelected($index, $time)
                                                    ? 'bg-green-500 text-black hover:bg-green-600'
                                                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-green-600' }}"
                                                >
                                                    {{ $time }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error("children.{$index}.available_times")
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endforeach

                <x-slot:actions>
                    <div class="flex justify-between w-full">
                        <x-button
                            label="Previous Step"
                            icon="o-arrow-left"
                            wire:click="$dispatch('previous-step')"
                            class="btn-secondary"
                        />
                        <x-button
                            label="Complete Setup"
                            type="submit"
                            icon="o-check"
                            class="btn-primary"
                            spinner="saveChildren"
                        />
                    </div>
                </x-slot:actions>
            </div>
        </x-form>
    @else
        <div class="flex items-center justify-center h-32">
            <div class="text-gray-500">Loading...</div>
        </div>
    @endif
</div>
