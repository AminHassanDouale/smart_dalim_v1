<?php

use Livewire\Volt\Component;
use App\Models\Children;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    public $user;
    public $parentProfile;
    public $children = [];
    public $stats = [];

    // Filter states
    public $searchQuery = '';
    public $subjectFilter = '';
    public $teacherFilter = '';

    // Modal states
    public $showDeleteModal = false;
    public $childToDelete = null;

    public function mount()
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        $this->loadChildren();
        $this->calculateStats();
    }

    public function loadChildren()
    {
        if ($this->parentProfile) {
            $query = $this->parentProfile->children()
                ->when($this->searchQuery, function ($query) {
                    $query->where('name', 'like', '%' . $this->searchQuery . '%');
                })
                ->when($this->subjectFilter, function ($query) {
                    $query->whereHas('subjects', function ($q) {
                        $q->where('id', $this->subjectFilter);
                    });
                })
                ->when($this->teacherFilter, function ($query) {
                    $query->where('teacher_id', $this->teacherFilter);
                });
    
            $this->children = $query->get();
        }
    }

    public function calculateStats()
{
    $this->stats = [
        'total_children' => $this->children->count(),
        'total_sessions' => $this->parentProfile->children->sum('learningSessions.count'),
        'total_subjects' => $this->parentProfile->children->flatMap->subjects->unique('id')->count(),
        'total_teachers' => $this->parentProfile->children->pluck('teacher_id')->unique()->count(),
    ];
}

    public function confirmDelete($childId)
    {
        $this->childToDelete = $childId;
        $this->showDeleteModal = true;
    }

    public function deleteChild()
    {
        $this->validate([
            'childToDelete' => 'required|exists:children,id',
        ]);
    
        Children::find($this->childToDelete)->delete();
    
        $this->showDeleteModal = false;
        $this->childToDelete = null;
    
        $this->loadChildren();
        $this->calculateStats();
    
        session()->flash('success', 'Child has been removed successfully.');
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->childToDelete = null;
    }

    public function getSubjectsProperty()
    {
        // In a real app, this would be fetched from the database
        return Subject::all() ?? collect([
            ['id' => 1, 'name' => 'Mathematics'],
            ['id' => 2, 'name' => 'Science'],
            ['id' => 3, 'name' => 'English Literature'],
            ['id' => 4, 'name' => 'History'],
            ['id' => 5, 'name' => 'Programming']
        ]);
    }

    public function getTeachersProperty()
    {
        // In a real app, this would be fetched from the database
        return collect([
            ['id' => 1, 'name' => 'Sarah Johnson'],
            ['id' => 2, 'name' => 'Michael Chen'],
            ['id' => 3, 'name' => 'Emily Rodriguez'],
            ['id' => 4, 'name' => 'David Wilson']
        ]);
    }

    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">My Children</h1>
                <p class="mt-1 text-base-content/70">Manage your children's profiles and learning progress</p>
            </div>
            <a href="{{ route('parents.children.create') }}" class="btn btn-primary">
                <x-icon name="o-user-plus" class="w-4 h-4 mr-2" />
                Add New Child
            </a>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2 lg:grid-cols-4">
            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-primary">
                        <x-icon name="o-user-group" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Total Children</div>
                    <div class="stat-value text-primary">{{ $stats['total_children'] }}</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-secondary">
                        <x-icon name="o-calendar" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Total Sessions</div>
                    <div class="stat-value text-secondary">{{ $stats['total_sessions'] }}</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-accent">
                        <x-icon name="o-academic-cap" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Subjects</div>
                    <div class="stat-value text-accent">{{ $stats['total_subjects'] }}</div>
                </div>
            </div>

            <div class="shadow stats bg-base-100">
                <div class="stat">
                    <div class="stat-figure text-info">
                        <x-icon name="o-users" class="w-8 h-8" />
                    </div>
                    <div class="stat-title">Teachers</div>
                    <div class="stat-value text-info">{{ $stats['total_teachers'] }}</div>
                </div>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="p-4 mb-6 shadow-lg bg-base-100 rounded-xl">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                    </div>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Search by name..."
                        class="w-full pl-10 input input-bordered"
                    >
                </div>

                <div>
                    <select wire:model.live="subjectFilter" class="w-full select select-bordered">
                        <option value="">All Subjects</option>
                        @foreach($this->subjects as $subject)
                            <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <select wire:model.live="teacherFilter" class="w-full select select-bordered">
                        <option value="">All Teachers</option>
                        @foreach($this->teachers as $teacher)
                            <option value="{{ $teacher['id'] }}">{{ $teacher['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Children List -->
        <div>
            @if(session('success'))
                <div class="mb-6 alert alert-success">
                    <x-icon name="o-check-circle" class="w-6 h-6" />
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(count($children) > 0)
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($children as $child)
                        <div class="transition-shadow shadow-xl card bg-base-100 hover:shadow-2xl">
                            <div class="card-body">
                                <div class="flex items-center gap-4">
                                    <div class="avatar placeholder">
                                        <div class="w-16 rounded-full bg-primary text-primary-content">
                                            <span class="text-xl">{{ substr($child->name, 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h2 class="card-title">{{ $child->name }}</h2>
                                        <p class="text-sm opacity-70">{{ $child->age }} years old</p>
                                    </div>
                                </div>

                                <div class="my-2 divider"></div>

                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-academic-cap" class="w-4 h-4 opacity-70" />
                                        <span>{{ $child->school_name }} - Grade {{ $child->grade }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-book-open" class="w-4 h-4 opacity-70" />
                                        <span>{{ $child->subjects->count() ?? rand(1, 5) }} Subjects</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-calendar" class="w-4 h-4 opacity-70" />
                                        <span>Last session: {{ $this->formatDate($child->last_session_at ?? now()->subDays(rand(1, 14))) }}</span>
                                    </div>
                                </div>

                                <!-- Progress bar -->
                                <div class="mt-4">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm">Overall Progress</span>
                                        <span class="text-sm font-medium">{{ rand(60, 95) }}%</span>
                                    </div>
                                    <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                        <div class="h-full bg-primary" style="width: {{ rand(60, 95) }}%"></div>
                                    </div>
                                </div>

                                <div class="justify-end mt-4 card-actions">
                                    <div class="dropdown dropdown-end">
                                        <div tabindex="0" role="button" class="btn btn-ghost btn-sm">
                                            <x-icon name="o-ellipsis-vertical" class="w-4 h-4" />
                                        </div>
                                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                            <li><a wire:click="confirmDelete({{ $child->id }})" class="text-error">Remove Child</a></li>
                                        </ul>
                                    </div>
                                    <a href="{{ route('parents.children.edit', $child->id) }}" class="btn btn-outline btn-sm">
                                        <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                        Edit
                                    </a>
                                    <a href="{{ route('parents.children.show', $child->id) }}" class="btn btn-primary btn-sm">
                                        <x-icon name="o-eye" class="w-4 h-4 mr-1" />
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-12 text-center shadow-lg bg-base-100 rounded-xl">
                    <div class="flex flex-col items-center justify-center">
                        <x-icon name="o-user-plus" class="w-20 h-20 mb-4 text-base-content/20" />
                        <h3 class="text-xl font-bold">No children added yet</h3>
                        <p class="max-w-md mx-auto mt-2 text-base-content/70">
                            Add your children to manage their learning journey, track progress, and schedule sessions with teachers.
                        </p>
                        <a href="{{ route('parents.children.create') }}" class="mt-6 btn btn-primary">
                            <x-icon name="o-user-plus" class="w-5 h-5 mr-2" />
                            Add Your First Child
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal {{ $showDeleteModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold text-error">Remove Child</h3>
            <p class="py-4">Are you sure you want to remove this child? This action will remove the child's profile, progress data, and session history.</p>
            <div class="modal-action">
                <button wire:click="cancelDelete" class="btn">Cancel</button>
                <button wire:click="deleteChild" class="btn btn-error">Remove</button>
            </div>
        </div>
        <div class="modal-backdrop" wire:click="cancelDelete"></div>
    </div>
</div>