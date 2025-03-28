<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Homework;
use App\Models\Children;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $children = [];
    public $subjects = [];
    
    // Filters
    public $childFilter = '';
    public $subjectFilter = '';
    public $statusFilter = 'all';
    public $searchQuery = '';
    public $dateRangeFilter = 'all';
    
    // Modal states
    public $showHomeworkModal = false;
    public $selectedHomework = null;
    
    // Sorting
    public $sortField = 'due_date';
    public $sortDirection = 'asc';

    public function mount()
    {
        $this->user = Auth::user();
        $this->loadChildren();
        $this->loadSubjects();
    }

    public function loadChildren()
    {
        if ($this->user->parentProfile) {
            $this->children = $this->user->parentProfile->children()->get();
        }
    }

    public function loadSubjects()
    {
        // Get all subjects from the system
        $this->subjects = Subject::all();
        
        // Or alternatively, get only subjects that are assigned to children's homework:
        // $childrenIds = $this->children->pluck('id')->toArray();
        // $this->subjects = Subject::whereHas('homework', function($query) use ($childrenIds) {
        //     $query->whereIn('child_id', $childrenIds);
        // })->get();
    }

    public function getHomeworkListProperty()
    {
        if (empty($this->children)) {
            return collect();
        }

        $childrenIds = $this->children->pluck('id')->toArray();
        
        $query = Homework::query()
            ->whereIn('child_id', $childrenIds)
            ->with(['child', 'subject', 'teacher']);
            
        // Apply filters
        if ($this->childFilter) {
            $query->where('child_id', $this->childFilter);
        }
        
        if ($this->subjectFilter) {
            $query->where('subject_id', $this->subjectFilter);
        }
        
        if ($this->statusFilter !== 'all') {
            if ($this->statusFilter === 'completed') {
                $query->where('is_completed', true);
            } elseif ($this->statusFilter === 'pending') {
                $query->where('is_completed', false);
            } elseif ($this->statusFilter === 'overdue') {
                $query->where('is_completed', false)
                    ->where('due_date', '<', now());
            } elseif ($this->statusFilter === 'upcoming') {
                $query->where('is_completed', false)
                    ->where('due_date', '>=', now());
            }
        }
        
        // Apply date range filter
        if ($this->dateRangeFilter !== 'all') {
            if ($this->dateRangeFilter === 'today') {
                $query->whereDate('due_date', Carbon::today());
            } elseif ($this->dateRangeFilter === 'this_week') {
                $query->whereBetween('due_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            } elseif ($this->dateRangeFilter === 'next_week') {
                $query->whereBetween('due_date', [Carbon::now()->addWeek()->startOfWeek(), Carbon::now()->addWeek()->endOfWeek()]);
            } elseif ($this->dateRangeFilter === 'this_month') {
                $query->whereBetween('due_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
            }
        }
        
        // Apply search
        if ($this->searchQuery) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('description', 'like', '%' . $this->searchQuery . '%');
            });
        }
        
        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);
        
        return $query->paginate(10);
    }
    
    public function getWeeklyHomeworkProperty()
    {
        if (empty($this->children)) {
            return [];
        }
        
        $childrenIds = $this->children->pluck('id')->toArray();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        
        $weeklyHomework = Homework::whereIn('child_id', $childrenIds)
            ->whereBetween('due_date', [$startOfWeek, $endOfWeek])
            ->orderBy('due_date')
            ->get()
            ->groupBy(function($homework) {
                return Carbon::parse($homework->due_date)->format('Y-m-d');
            });
            
        // Initialize all days of the week
        $result = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $startOfWeek->copy()->addDays($i)->format('Y-m-d');
            $result[$day] = $weeklyHomework->get($day, collect());
        }
        
        return $result;
    }
    
    public function getSubjectStatsProperty()
    {
        if (empty($this->children)) {
            return collect();
        }
        
        $childrenIds = $this->children->pluck('id')->toArray();
        
        $subjectStats = [];
        $allHomework = Homework::whereIn('child_id', $childrenIds)
            ->with('subject')
            ->get();
            
        foreach ($allHomework->groupBy('subject_id') as $subjectId => $homeworkItems) {
            $subject = $homeworkItems->first()->subject;
            if (!$subject) continue;
            
            $total = $homeworkItems->count();
            $completed = $homeworkItems->where('is_completed', true)->count();
            $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
            
            $subjectStats[] = [
                'id' => $subjectId,
                'name' => $subject->name,
                'total' => $total,
                'completed' => $completed,
                'percentage' => $percentage
            ];
        }
        
        return collect($subjectStats)->sortByDesc('total')->take(5);
    }

    public function getHomeworkProgress()
    {
        if (empty($this->children)) {
            return [
                'total' => 0,
                'completed' => 0,
                'pending' => 0,
                'overdue' => 0,
                'percentage' => 0
            ];
        }

        $childrenIds = $this->children->pluck('id')->toArray();
        
        $total = Homework::whereIn('child_id', $childrenIds)->count();
        $completed = Homework::whereIn('child_id', $childrenIds)->where('is_completed', true)->count();
        $pending = Homework::whereIn('child_id', $childrenIds)->where('is_completed', false)->count();
        $overdue = Homework::whereIn('child_id', $childrenIds)
            ->where('is_completed', false)
            ->where('due_date', '<', now())
            ->count();
            
        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
        
        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'percentage' => $percentage
        ];
    }

    public function viewHomework($homeworkId)
    {
        $this->selectedHomework = Homework::with(['child', 'subject', 'teacher', 'attachments'])
            ->findOrFail($homeworkId);
        $this->showHomeworkModal = true;
    }

    public function toggleHomeworkStatus($homeworkId)
    {
        $homework = Homework::findOrFail($homeworkId);
        $homework->is_completed = !$homework->is_completed;
        $homework->completed_at = $homework->is_completed ? now() : null;
        $homework->save();

        $this->dispatch('toast', [
            'message' => $homework->is_completed 
                ? 'Homework marked as completed!' 
                : 'Homework marked as pending!',
            'type' => 'success'
        ]);
        
        // If we have a modal open for this homework, refresh it
        if ($this->selectedHomework && $this->selectedHomework->id === $homeworkId) {
            $this->selectedHomework = Homework::with(['child', 'subject', 'teacher', 'attachments'])
                ->findOrFail($homeworkId);
        }
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function getHomeworkStatusClass($homework)
    {
        if ($homework->is_completed) {
            return 'badge-success';
        } elseif ($homework->due_date < now()) {
            return 'badge-error';
        } elseif (Carbon::parse($homework->due_date)->diffInDays(now()) <= 2) {
            return 'badge-warning';
        } else {
            return 'badge-info';
        }
    }

    public function getHomeworkStatusText($homework)
    {
        if ($homework->is_completed) {
            return 'Completed';
        } elseif ($homework->due_date < now()) {
            return 'Overdue';
        } elseif (Carbon::parse($homework->due_date)->diffInDays(now()) <= 2) {
            return 'Due Soon';
        } else {
            return 'Pending';
        }
    }
    
    public function getDaysRemaining($homework)
    {
        $dueDate = Carbon::parse($homework->due_date);
        if ($homework->is_completed) {
            return 'Completed';
        } elseif ($dueDate->isPast()) {
            $days = $dueDate->diffInDays(now());
            return $days === 0 ? 'Today' : "{$days}d overdue";
        } else {
            $days = $dueDate->diffInDays(now());
            return $days === 0 ? 'Today' : "In {$days}d";
        }
    }

    public function closeModal()
    {
        $this->showHomeworkModal = false;
        $this->selectedHomework = null;
    }
    
    // Reset pagination when filters change
    public function updatedChildFilter() { $this->resetPage(); }
    public function updatedSubjectFilter() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedDateRangeFilter() { $this->resetPage(); }
    public function updatedSearchQuery() { $this->resetPage(); }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="max-w-7xl mx-auto">
        <!-- Header with summary stats -->
        <div class="bg-gradient-to-r from-primary to-secondary text-white rounded-xl shadow-lg overflow-hidden mb-8">
            <div class="p-6 md:p-8">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                    <h1 class="text-3xl font-bold">Homework Dashboard</h1>
                    <div class="mt-2 md:mt-0 flex space-x-2">
                        <a href="{{ route('parents.dashboard') }}" class="btn btn-ghost btn-sm text-white bg-white/10">
                            <x-icon name="o-home" class="w-4 h-4 mr-1" />
                            Dashboard
                        </a>
                        <a href="{{ route('parents.children.index') }}" class="btn btn-ghost btn-sm text-white bg-white/10">
                            <x-icon name="o-user-group" class="w-4 h-4 mr-1" />
                            Children
                        </a>
                    </div>
                </div>
                
                @php
                    $progress = $this->getHomeworkProgress();
                @endphp
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 text-white">
                    <div class="bg-white/20 rounded-lg p-4 flex flex-col items-center">
                        <span class="text-3xl font-bold">{{ $progress['total'] }}</span>
                        <span class="text-white/90 mt-1">Total Assignments</span>
                    </div>
                    <div class="bg-white/20 rounded-lg p-4 flex flex-col items-center">
                        <span class="text-3xl font-bold text-success-content">{{ $progress['completed'] }}</span>
                        <span class="text-white/90 mt-1">Completed</span>
                    </div>
                    <div class="bg-white/20 rounded-lg p-4 flex flex-col items-center">
                        <span class="text-3xl font-bold text-info-content">{{ $progress['pending'] }}</span>
                        <span class="text-white/90 mt-1">Pending</span>
                    </div>
                    <div class="bg-white/20 rounded-lg p-4 flex flex-col items-center">
                        <span class="text-3xl font-bold text-error-content">{{ $progress['overdue'] }}</span>
                        <span class="text-white/90 mt-1">Overdue</span>
                    </div>
                </div>
                
                <div class="mt-6">
                    <div class="flex justify-between mb-2">
                        <span>Completion Progress</span>
                        <span>{{ $progress['percentage'] }}%</span>
                    </div>
                    <div class="w-full bg-white/30 rounded-full h-4">
                        <div class="bg-success h-4 rounded-full transition-all duration-500" style="width: {{ $progress['percentage'] }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content Area (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Filters and Homework Table -->
                <div class="bg-base-100 rounded-xl shadow-xl p-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                        <h2 class="text-xl font-bold mb-2 md:mb-0">Homework Assignments</h2>
                        <div class="flex space-x-2">
                            <select class="select select-bordered select-sm" wire:model.live="statusFilter">
                                <option value="all">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="overdue">Overdue</option>
                                <option value="upcoming">Upcoming</option>
                            </select>
                            <select class="select select-bordered select-sm" wire:model.live="dateRangeFilter">
                                <option value="all">All Time</option>
                                <option value="today">Today</option>
                                <option value="this_week">This Week</option>
                                <option value="next_week">Next Week</option>
                                <option value="this_month">This Month</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                        @if(count($children) > 0)
                        <div>
                            <label class="label">
                                <span class="label-text">Filter by Child</span>
                            </label>
                            <select class="select select-bordered w-full" wire:model.live="childFilter">
                                <option value="">All Children</option>
                                @foreach($children as $child)
                                    <option value="{{ $child->id }}">{{ $child->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        
                        <div>
                            <label class="label">
                                <span class="label-text">Filter by Subject</span>
                            </label>
                            <select class="select select-bordered w-full" wire:model.live="subjectFilter">
                                <option value="">All Subjects</option>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label class="label">
                                <span class="label-text">Search</span>
                            </label>
                            <div class="relative">
                                <input type="text" placeholder="Search homework..." 
                                    class="input input-bordered w-full pl-10" 
                                    wire:model.live.debounce.300ms="searchQuery">
                                <div class="absolute left-3 top-1/2 transform -translate-y-1/2">
                                    <x-icon name="o-magnifying-glass" class="w-4 h-4 text-base-content/60" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Homework Table -->
                    @if($this->homeworkList->isEmpty())
                        <div class="py-12 text-center">
                            <x-icon name="o-document-text" class="w-16 h-16 mx-auto text-base-content/30" />
                            <h3 class="mt-4 text-lg font-medium">No homework found</h3>
                            <p class="mt-1 text-base-content/70">
                                @if($searchQuery || $childFilter || $subjectFilter || $statusFilter !== 'all' || $dateRangeFilter !== 'all')
                                    Try adjusting your filters or search query
                                @else
                                    No homework has been assigned yet
                                @endif
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table table-zebra w-full">
                                <thead>
                                    <tr>
                                        <th class="w-14"></th>
                                        <th wire:click="sortBy('title')" class="cursor-pointer">
                                            <div class="flex items-center">
                                                Title
                                                @if($sortField === 'title')
                                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                                @endif
                                            </div>
                                        </th>
                                        <th>Child & Subject</th>
                                        <th wire:click="sortBy('due_date')" class="cursor-pointer">
                                            <div class="flex items-center">
                                                Due Date
                                                @if($sortField === 'due_date')
                                                    <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                                @endif
                                            </div>
                                        </th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->homeworkList as $homework)
                                        <tr class="{{ $homework->is_completed ? 'bg-base-200/50' : '' }}">
                                            <td>
                                                <label>
                                                    <input type="checkbox" class="checkbox checkbox-accent"
                                                        wire:click="toggleHomeworkStatus({{ $homework->id }})"
                                                        @checked($homework->is_completed) />
                                                </label>
                                            </td>
                                            <td>
                                                <div class="font-medium">{{ $homework->title }}</div>
                                                <div class="text-xs opacity-60 truncate max-w-xs">{{ $homework->description }}</div>
                                            </td>
                                            <td>
                                                <div class="flex items-center space-x-2">
                                                    <div class="avatar placeholder">
                                                        <div class="bg-neutral text-neutral-content rounded-full w-8">
                                                            <span>{{ substr($homework->child?->name ?? 'U', 0, 1) }}</span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium">{{ $homework->child?->name ?? 'Unknown' }}</div>
                                                        <div class="text-xs">
                                                            <span class="badge badge-outline badge-sm">{{ $homework->subject?->name ?? 'General' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="{{ Carbon::parse($homework->due_date)->isPast() && !$homework->is_completed ? 'text-error' : '' }} whitespace-nowrap">
                                                    {{ Carbon::parse($homework->due_date)->format('M d, Y') }}
                                                </div>
                                                <div class="text-xs opacity-70">{{ Carbon::parse($homework->due_date)->format('h:i A') }}</div>
                                                <div class="text-xs {{ $homework->is_completed ? 'text-success' : (Carbon::parse($homework->due_date)->isPast() ? 'text-error' : 'text-info') }}">
                                                    {{ $this->getDaysRemaining($homework) }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="badge {{ $this->getHomeworkStatusClass($homework) }}">
                                                    {{ $this->getHomeworkStatusText($homework) }}
                                                </div>
                                            </td>
                                            <td>
                                                <div class="flex space-x-2">
                                                    <button class="btn btn-ghost btn-sm" wire:click="viewHomework({{ $homework->id }})">
                                                        <x-icon name="o-eye" class="w-4 h-4" />
                                                    </button>
                                                    <a href="{{ route('parents.homework.show', $homework->id) }}" class="btn btn-ghost btn-sm">
                                                        <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4" />
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            {{ $this->homeworkList->links() }}
                        </div>
                    @endif
                </div>
                
                <!-- Weekly Calendar View -->
                <div class="bg-base-100 rounded-xl shadow-xl p-6">
                    <h2 class="text-xl font-bold mb-4">This Week's Homework</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-7 gap-2">
                        @php
                            $weeklyHomework = $this->weeklyHomework;
                            $today = Carbon::now();
                            $weekStart = $today->copy()->startOfWeek();
                        @endphp
                        
                        @for($i = 0; $i < 7; $i++)
                            @php
                                $currentDay = $weekStart->copy()->addDays($i);
                                $isToday = $currentDay->isToday();
                                $dateKey = $currentDay->format('Y-m-d');
                                $dayHomework = $weeklyHomework[$dateKey] ?? collect();
                            @endphp
                            
                            <div class="card {{ $isToday ? 'bg-primary text-primary-content' : 'bg-base-200' }} shadow-sm">
                                <div class="card-body p-3">
                                    <h3 class="text-center font-bold {{ $isToday ? '' : 'text-base-content' }}">
                                        {{ $currentDay->format('D') }}
                                        <span class="block text-lg {{ $isToday ? '' : 'text-base-content' }}">{{ $currentDay->format('d') }}</span>
                                    </h3>
                                    
                                    <div class="divider my-1"></div>
                                    
                                    @if(count($dayHomework) > 0)
                                        <div class="space-y-2">
                                            @foreach($dayHomework as $hw)
                                                <div class="bg-base-100 rounded-lg p-2 text-base-content text-sm hover:bg-base-200 transition-colors cursor-pointer" wire:click="viewHomework({{ $hw->id }})">
                                                    <div class="font-medium truncate">{{ $hw->title }}</div>
                                                    <div class="flex justify-between items-center mt-1">
                                                        <span class="text-xs opacity-70">{{ $hw->child?->name }}</span>
                                                        <div class="badge badge-xs {{ $this->getHomeworkStatusClass($hw) }}"></div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center text-sm {{ $isToday ? '' : 'text-base-content/50' }} py-2">
                                            No homework
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
            
            <!-- Right Sidebar (1/3) -->
            <div class="space-y-6">
                <!-- Quick Actions Card -->
                <div class="bg-base-100 rounded-xl shadow-xl p-6">
                    <h2 class="text-xl font-bold mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <a href="{{ route('parents.calendar') }}" class="btn btn-primary btn-block">
                            <x-icon name="o-calendar" class="w-4 h-4 mr-2" />
                            View Calendar
                        </a>
                        <a href="{{ route('parents.assessments.index') }}" class="btn btn-outline btn-block">
                            <x-icon name="o-academic-cap" class="w-4 h-4 mr-2" />
                            View Assessments
                        </a>
                        <a href="{{ route('parents.materials.index') }}" class="btn btn-outline btn-block">
                            <x-icon name="o-book-open" class="w-4 h-4 mr-2" />
                            Learning Materials
                        </a>
                        <a href="{{ route('parents.messages.index') }}" class="btn btn-outline btn-block">
                            <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 mr-2" />
                            Message Teachers
                        </a>
                    </div>
                </div>
                
                <!-- Homework by Subject Card -->
                <div class="bg-base-100 rounded-xl shadow-xl p-6">
                    <h2 class="text-xl font-bold mb-4">Homework by Subject</h2>
                    
                    @if($this->subjectStats->isEmpty())
                        <div class="py-6 text-center">
                            <x-icon name="o-book-open" class="w-12 h-12 mx-auto text-base-content/30" />
                            <h3 class="mt-2 text-base font-medium">No subjects</h3>
                            <p class="mt-1 text-sm text-base-content/70">No subjects with homework</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($this->subjectStats as $subjectStat)
                                <div class="card bg-base-200">
                                    <div class="card-body p-4">
                                        <div class="flex justify-between items-center">
                                            <h3 class="font-bold">{{ $subjectStat['name'] }}</h3>
                                            <span class="text-sm">{{ $subjectStat['completed'] }}/{{ $subjectStat['total'] }} completed</span>
                                        </div>
                                        <div class="w-full bg-base-300 rounded-full h-2.5 mt-2">
                                            <div class="bg-primary h-2.5 rounded-full transition-all duration-500" style="width: {{ $subjectStat['percentage'] }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                
                <!-- Upcoming Deadlines Card -->
                <div class="bg-base-100 rounded-xl shadow-xl p-6">
                    <h2 class="text-xl font-bold mb-4">Upcoming Deadlines</h2>
                    
                    @php
                        $upcomingHomework = $this->homeworkList
                            ->where('is_completed', false)
                            ->where('due_date', '>=', now())
                            ->sortBy('due_date')
                            ->take(5);
                    @endphp
                    
                    @if($upcomingHomework->isEmpty())
<div class="py-6 text-center">
                            <x-icon name="o-calendar" class="w-12 h-12 mx-auto text-base-content/30" />
                            <h3 class="mt-2 text-lg font-medium">No upcoming deadlines</h3>
                            <p class="mt-1 text-base-content/70">All caught up!</p>
                        </div>
                    @else
                        <div class="divide-y">
                            @foreach($upcomingHomework as $homework)
                                <div class="py-3 {{ $loop->first ? 'pt-0' : '' }} {{ $loop->last ? 'pb-0' : '' }}">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <div class="font-medium">{{ $homework->title }}</div>
                                            <div class="text-sm text-base-content/70">{{ $homework->child?->name }}</div>
                                            <div class="badge badge-sm badge-outline mt-1">{{ $homework->subject?->name }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-medium text-sm">{{ Carbon::parse($homework->due_date)->format('M d') }}</div>
                                            <div class="text-xs text-base-content/70">{{ Carbon::parse($homework->due_date)->format('h:i A') }}</div>
                                            <div class="badge badge-sm {{ $this->getHomeworkStatusClass($homework) }} mt-1">
                                                {{ $this->getDaysRemaining($homework) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-4 text-center">
                            <button class="btn btn-ghost btn-sm" wire:click="$set('statusFilter', 'upcoming')">
                                View All Upcoming
                            </button>
                        </div>
                    @endif
                </div>
                
                <!-- Children's Homework Progress -->
                <div class="bg-base-100 rounded-xl shadow-xl p-6">
                    <h2 class="text-xl font-bold mb-4">Children's Progress</h2>
                    
                    @if($children->isEmpty())
                        <div class="py-6 text-center">
                            <x-icon name="o-user-group" class="w-12 h-12 mx-auto text-base-content/30" />
                            <h3 class="mt-2 text-lg font-medium">No children</h3>
                            <p class="mt-1 text-base-content/70">Add children to track progress</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($children as $child)
                                @php
                                    $childHomework = $this->homeworkList->where('child_id', $child->id);
                                    $total = $childHomework->count();
                                    $completed = $childHomework->where('is_completed', true)->count();
                                    $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;
                                @endphp
                                
                                <div class="card bg-base-200">
                                    <div class="card-body p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="avatar placeholder">
                                                <div class="bg-neutral text-neutral-content rounded-full w-10">
                                                    <span>{{ substr($child->name, 0, 1) }}</span>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <h3 class="font-bold">{{ $child->name }}</h3>
                                                <div class="flex justify-between items-center mt-1">
                                                    <span class="text-xs text-base-content/70">{{ $completed }}/{{ $total }} completed</span>
                                                    <span class="text-xs font-medium">{{ $percentage }}%</span>
                                                </div>
                                                <div class="w-full bg-base-300 rounded-full h-2 mt-1">
                                                    <div class="bg-primary h-2 rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Homework Detail Modal -->
    @if($showHomeworkModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 z-40 flex items-center justify-center p-4">
            <div class="modal-box max-w-3xl w-full bg-base-100">
                <h3 class="font-bold text-xl mb-3">{{ $selectedHomework->title }}</h3>
                <button wire:click="closeModal" class="absolute btn btn-sm btn-circle btn-ghost right-2 top-2">✕</button>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <div class="text-sm text-base-content/70">Due Date</div>
                        <div class="font-medium">
                            {{ Carbon::parse($selectedHomework->due_date)->format('M d, Y - h:i A') }}
                            <span class="badge {{ $this->getHomeworkStatusClass($selectedHomework) }} ml-2">
                                {{ $this->getHomeworkStatusText($selectedHomework) }}
                            </span>
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-sm text-base-content/70">Assigned By</div>
                        <div class="font-medium">{{ $selectedHomework->teacher?->name ?? 'Unknown Teacher' }}</div>
                    </div>
                    
                    <div>
                        <div class="text-sm text-base-content/70">Subject</div>
                        <div class="font-medium">{{ $selectedHomework->subject?->name ?? 'General' }}</div>
                    </div>
                    
                    <div>
                        <div class="text-sm text-base-content/70">For Child</div>
                        <div class="font-medium">{{ $selectedHomework->child?->name ?? 'Unknown' }}</div>
                    </div>
                </div>
                
                <div class="divider"></div>
                
                <div class="mb-4">
                    <div class="text-sm text-base-content/70 mb-1">Description</div>
                    <div class="prose max-w-none bg-base-200 p-4 rounded-lg">
                        {{ $selectedHomework->description }}
                    </div>
                </div>
                
                @if($selectedHomework->attachments && $selectedHomework->attachments->count() > 0)
                    <div class="mb-4">
                        <div class="text-sm text-base-content/70 mb-2">Attachments</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($selectedHomework->attachments as $attachment)
                                <a href="#" class="btn btn-outline btn-sm">
                                    <x-icon name="o-document" class="w-4 h-4 mr-1" />
                                    {{ $attachment->file_name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                
                <div class="divider"></div>
                
                <div class="flex justify-between">
                    @if($selectedHomework->is_completed)
                        <div class="flex items-center text-success">
                            <x-icon name="o-check-circle" class="w-5 h-5 mr-1" />
                            <span>
                                Completed on {{ $selectedHomework->completed_at ? Carbon::parse($selectedHomework->completed_at)->format('M d, Y') : 'N/A' }}
                            </span>
                        </div>
                        <button class="btn btn-ghost btn-sm" wire:click="toggleHomeworkStatus({{ $selectedHomework->id }})">
                            Mark as Pending
                        </button>
                    @else
                        <div></div>
                        <button class="btn btn-primary btn-sm" wire:click="toggleHomeworkStatus({{ $selectedHomework->id }})">
                            Mark as Completed
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>