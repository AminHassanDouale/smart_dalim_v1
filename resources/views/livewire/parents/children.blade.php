<?php

use App\Models\Children;
use App\Models\ParentProfile;
use App\Models\User;
use App\Models\Subject;
use Livewire\Volt\Component;

new class extends Component {
    public $showAddModal = false;
    public $children = [];
    public $teachers = [];
    public $subjects = [];
    public $newChild = [
        'name' => '',
        'teacher_id' => null,
        'subject_ids' => [],
        'age' => null,
        'gender' => null,
        'school_name' => '', // Added school_name field
        'available_times' => []
    ];

    public function mount()
    {
        $this->loadChildren();
        $this->loadTeachers();
        $this->loadSubjects();
    }

    public function loadChildren()
    {
        $this->children = auth()->user()->parentProfile->children()
            ->with(['teacher', 'subjects', 'learningSessions'])
            ->get();
    }

    public function loadTeachers()
    {
        $this->teachers = User::where('role', 'teacher')
            ->with(['teacherProfile', 'subjects'])
            ->get();
    }

    public function loadSubjects()
    {
        $this->subjects = Subject::all();
    }

    public function openAddModal()
    {
        $this->showAddModal = true;
        $this->resetNewChild();
    }

    public function resetNewChild()
    {
        $this->newChild = [
            'name' => '',
            'teacher_id' => null,
            'subject_ids' => [],
            'age' => null,
            'gender' => null,
            'school_name' => '',
            'grade' => '', // Add this
            'available_times' => []

        ];
    }

    public function addChild()
    {
        $this->validate([
            'newChild.name' => 'required|string|max:255',
            'newChild.teacher_id' => 'nullable|exists:users,id',
            'newChild.subject_ids' => 'required|array|min:1',
            'newChild.age' => 'required|integer|min:1|max:18',
            'newChild.gender' => 'required|in:male,female,other',
            'newChild.school_name' => 'required|string|max:255',
            'newChild.grade' => 'required|string|max:50', // Add this
            'newChild.available_times' => 'required|array|min:1'
        
        ]);

        try {
            \DB::beginTransaction();

            $availableTimes = collect($this->newChild['available_times'])
                ->filter()
                ->mapWithKeys(function ($value) {
                    return [$value => true];
                })
                ->toArray();

            $childData = [
                'name' => $this->newChild['name'],
                'teacher_id' => $this->newChild['teacher_id'],
                'age' => $this->newChild['age'],
                'gender' => $this->newChild['gender'],
                'school_name' => $this->newChild['school_name'],
                'grade' => $this->newChild['grade'], // Add this
                'available_times' => $availableTimes,
                'parent_profile_id' => auth()->user()->parentProfile->id
            ];

            // Add debug logging
            logger()->info('Child data being saved:', $childData);

            // Create child record
            $child = Children::create($childData);

            // Attach subjects
            $child->subjects()->attach($this->newChild['subject_ids']);

            // Update parent profile children count
            auth()->user()->parentProfile->increment('number_of_children');

            \DB::commit();

            $this->loadChildren();
            $this->showAddModal = false;
            $this->resetNewChild();

            $this->dispatch('toast', [
                'message' => 'Child added successfully',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            logger()->error('Error adding child: ' . $e->getMessage());
            logger()->error('Error trace: ' . $e->getTraceAsString());
            $this->dispatch('toast', [
                'message' => 'Error adding child: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    public function saveChildSettings($childId)
    {
        // Implement the logic to save child settings
        try {
            $child = Children::findOrFail($childId);
            
            // Check if the child belongs to the current parent
            if ($child->parent_profile_id !== auth()->user()->parentProfile->id) {
                throw new \Exception('You do not have permission to edit this child');
            }
            
            // Update child data here based on the UI selections
            // This would need to be expanded based on what data is being edited
            
            $this->dispatch('toast', [
                'message' => 'Child settings updated successfully',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            logger()->error('Error saving child settings: ' . $e->getMessage());
            $this->dispatch('toast', [
                'message' => 'Error saving child settings: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    public function deleteChild($childId)
    {
        try {
            $parentProfile = auth()->user()->parentProfile;

            if (!$parentProfile->children()->where('id', $childId)->exists()) {
                throw new \Exception('Child not found');
            }

            \DB::beginTransaction();

            Children::destroy($childId);
            $parentProfile->decrement('number_of_children');

            \DB::commit();

            $this->loadChildren();

            $this->dispatch('toast', [
                'message' => 'Child removed successfully',
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            $this->dispatch('toast', [
                'message' => 'Error removing child. Please try again.',
                'type' => 'error'
            ]);
        }
    }
}; ?>

<div>
    <!-- Header with Add Child button -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-900">Children Management</h2>
        <x-button
            positive
            label="Add Child"
            icon="m-user-plus"
            wire:click="openAddModal"
        />
    </div>

    <!-- Children List -->
    <div class="mt-6 space-y-6">
        @if($children->isEmpty())
            <div class="py-12 text-center">
                <x-icon name="m-user-group" class="w-12 h-12 mx-auto text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900">No children added</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by adding a child.</p>
            </div>
        @endif

        @foreach($children as $child)
            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-6">
                    <div class="flex flex-col space-y-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">{{ $child->name }}</h3>
                            <p class="text-sm text-gray-500">Age: {{ $child->age }} years</p>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-medium text-gray-700">Subjects</p>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                @foreach($subjects as $subject)
                                    <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <x-checkbox
                                            :checked="$child->subjects->contains($subject->id)"
                                            name="subject_ids[]"
                                            value="{{ $subject->id }}"
                                        />
                                        <span class="ml-2 text-sm">{{ $subject->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-medium text-gray-700">Teacher (Optional)</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach($teachers as $teacher)
                                    <div class="relative flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <div class="flex-1 min-w-0">
                                            <label class="font-medium text-gray-700">
                                                <input
                                                    type="radio"
                                                    name="teacher_id"
                                                    value="{{ $teacher->id }}"
                                                    @checked($child->teacher_id == $teacher->id)
                                                    class="hidden"
                                                >
                                                <span class="block text-sm font-medium">{{ $teacher->name }}</span>
                                                @if($teacher->subjects->isNotEmpty())
                                                    <div class="flex flex-wrap gap-1 mt-2">
                                                        @foreach($teacher->subjects as $subject)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                {{ $subject->name }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </label>
                                        </div>
                                        <div class="flex items-center h-5 ml-3">
                                            <div class="flex items-center justify-center w-5 h-5 border-2 border-gray-300 rounded-full {{ $child->teacher_id == $teacher->id ? 'border-blue-500 bg-blue-50' : '' }}">
                                                @if($child->teacher_id == $teacher->id)
                                                    <div class="w-2.5 h-2.5 bg-blue-500 rounded-full"></div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-between pt-4">
                            <x-button
                                primary
                                label="Save Changes"
                                wire:click="saveChildSettings({{ $child->id }})"
                            />

                            <x-button
                                negative
                                label="Remove"
                                wire:confirm="Are you sure you want to remove this child?"
                                wire:click="deleteChild({{ $child->id }})"
                            />
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Add Child Modal -->
    <x-modal wire:model="showAddModal" max-width="2xl">
        <x-card title="Add New Child">
            <x-form wire:submit="addChild">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="col-span-2">
                        <x-input
                            label="Full Name"
                            wire:model="newChild.name"
                            placeholder="Enter child's full name"
                            required
                        />
                    </div>
    
                    <div>
                        <x-input
                            type="number"
                            label="Age"
                            wire:model="newChild.age"
                            min="1"
                            max="18"
                            required
                        />
                    </div>
                    
                    <!-- School Name -->
                    <div>
                        <x-input
                            label="School Name"
                            wire:model="newChild.school_name"
                            placeholder="Enter school name"
                            required
                        />
                    </div>
    
                    <!-- Gender Selection -->
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-700">Gender</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input 
                                    type="radio" 
                                    wire:model="newChild.gender" 
                                    value="male" 
                                    class="text-blue-600 border-gray-300 focus:ring-blue-500 h-4 w-4"
                                >
                                <span class="ml-2">Male</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input 
                                    type="radio" 
                                    wire:model="newChild.gender" 
                                    value="female" 
                                    class="text-blue-600 border-gray-300 focus:ring-blue-500 h-4 w-4"
                                >
                                <span class="ml-2">Female</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input 
                                    type="radio" 
                                    wire:model="newChild.gender" 
                                    value="other" 
                                    class="text-blue-600 border-gray-300 focus:ring-blue-500 h-4 w-4"
                                >
                                <span class="ml-2">Other</span>
                            </label>
                        </div>
                        @error('newChild.gender')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-input
                    label="Grade/Class"
                    wire:model="newChild.grade"
                    placeholder="Enter grade or class"
                    required
                />
    
                    <!-- Subject Selection -->
                    <div class="col-span-2">
                        <label class="block mb-2 text-sm font-medium text-gray-700">Select Subjects</label>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            @foreach($subjects as $subject)
                                <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <x-checkbox
                                        wire:model="newChild.subject_ids"
                                        value="{{ $subject->id }}"
                                    />
                                    <span class="ml-2 text-sm">{{ $subject->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('newChild.subject_ids')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
    
                    <!-- Teacher Selection -->
                    <div class="col-span-2 mt-4">
                        <label class="block mb-2 text-sm font-medium text-gray-700">Select Teacher (Optional)</label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach($teachers as $teacher)
                                <div
                                    class="relative flex items-start p-4 border rounded-lg cursor-pointer hover:bg-gray-50 {{ $newChild['teacher_id'] == $teacher->id ? 'ring-2 ring-blue-500' : '' }}"
                                    wire:click="$set('newChild.teacher_id', {{ $teacher->id }})"
                                >
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input
                                                type="radio"
                                                wire:model="newChild.teacher_id"
                                                value="{{ $teacher->id }}"
                                                class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div class="ml-3">
                                            <span class="block text-sm font-medium text-gray-700">{{ $teacher->name }}</span>
                                            @if($teacher->subjects->isNotEmpty())
                                                <div class="flex flex-wrap gap-1 mt-2">
                                                    @foreach($teacher->subjects as $subject)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{ $subject->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('newChild.teacher_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
    
                    <!-- Available Times Selection -->
                    <div class="col-span-2">
                        <label class="block mb-2 text-sm font-medium text-gray-700">Available Times</label>
                        <div class="grid grid-cols-2 gap-4">
                            @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                                <div class="flex items-center space-x-2">
                                    <x-checkbox
                                        wire:model="newChild.available_times"
                                        value="{{ strtolower($day) }}"
                                        label="{{ $day }}"
                                    />
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
    
                <div class="flex justify-end gap-x-4 mt-6">
                    <x-button
                        flat
                        label="Cancel"
                        x-on:click="close"
                    />
                    <x-button
                        type="submit"
                        primary
                        label="Add Child"
                        spinner="addChild"
                    />
                </div>
            </x-form>
        </x-card>
    </x-modal>
</div>
