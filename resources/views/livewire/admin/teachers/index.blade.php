<?php

use function Livewire\Volt\{state, computed, mount, boot};
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

// Define state variables
state([
    'teachers' => [], // Changed to array instead of paginated collection
    'search' => '',
    'page' => 1,
    'perPage' => 15,
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'status' => 'active',
    'selectedTeachers' => [],
    'selectAll' => false,
    'filters' => [
        'dateRange' => null,
    ],
    'expandedFilters' => false,
    'roles' => [], // Keep empty roles array to avoid undefined variable error
    'filteredTeachersCount' => 0, // Add as normal state variable instead of computed
    'paginationInfo' => [
        'currentPage' => 1,
        'lastPage' => 1,
        'perPage' => 15,
        'total' => 0,
        'from' => 0,
        'to' => 0,
    ], // Add separate pagination information
]);

// Initialize component
boot(function() {
    // Count teachers
    $this->updateFilteredCount();
});

// Method to update filtered count
$updateFilteredCount = function() {
    $this->filteredTeachersCount = User::where('role', User::ROLE_TEACHER)
        ->when($this->search, function(Builder $query) {
            return $query->where(function($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%")
                  ->orWhere('employee_id', 'like', "%{$this->search}%");
            });
        })
        ->when($this->status !== 'all', function(Builder $query) {
            return $query->where('status', $this->status);
        })
        ->when(isset($this->filters['dateRange']) && $this->filters['dateRange'], function(Builder $query) {
            $dates = explode(' to ', $this->filters['dateRange']);
            if (count($dates) === 2) {
                return $query->whereBetween('created_at', [
                    \Carbon\Carbon::parse($dates[0])->startOfDay(),
                    \Carbon\Carbon::parse($dates[1])->endOfDay(),
                ]);
            }
            return $query;
        })
        ->count();
};

// Mount component and load initial data
mount(function($search = null, $page = 1, $perPage = 15, $sortBy = 'created_at', $sortDirection = 'desc', $status = 'active') {
    $this->search = $search;
    $this->page = $page;
    $this->perPage = $perPage;
    $this->sortBy = $sortBy;
    $this->sortDirection = $sortDirection;
    $this->status = $status;
    
    $this->loadTeachers();
});

// Method to load teachers based on current filters
$loadTeachers = function() {
    $query = $this->getTeachersQuery();
    
    // Get paginated results
    $paginator = $query
        ->orderBy($this->sortBy, $this->sortDirection)
        ->paginate($this->perPage, ['*'], 'page', $this->page);
    
    // Store simple array of teacher data
    $this->teachers = $paginator->items();
    
    // Store pagination information separately
    $this->paginationInfo = [
        'currentPage' => $paginator->currentPage(),
        'lastPage' => $paginator->lastPage(),
        'perPage' => $paginator->perPage(),
        'total' => $paginator->total(),
        'from' => $paginator->firstItem() ?: 0,
        'to' => $paginator->lastItem() ?: 0,
    ];
    
    // Update the filtered count whenever we load teachers
    $this->updateFilteredCount();
};

// Method to get filtered teachers query
$getTeachersQuery = function() {
    $query = User::where('role', User::ROLE_TEACHER)
        ->when($this->search, function(Builder $query) {
            return $query->where(function($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%")
                  ->orWhere('employee_id', 'like', "%{$this->search}%");
            });
        })
        ->when($this->status !== 'all', function(Builder $query) {
            return $query->where('status', $this->status);
        })
        ->when(isset($this->filters['dateRange']) && $this->filters['dateRange'], function(Builder $query) {
            $dates = explode(' to ', $this->filters['dateRange']);
            if (count($dates) === 2) {
                return $query->whereBetween('created_at', [
                    \Carbon\Carbon::parse($dates[0])->startOfDay(),
                    \Carbon\Carbon::parse($dates[1])->endOfDay(),
                ]);
            }
            return $query;
        });
        
    return $query;
};

// Method to apply sort
$applySort = function($column) {
    if ($this->sortBy === $column) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }
    
    $this->loadTeachers();
};

// Method for handling pagination
$goToPage = function($page) {
    $this->page = $page;
    $this->loadTeachers();
};

// Method to toggle selection of all teachers
$toggleSelectAll = function() {
    $this->selectAll = !$this->selectAll;
    
    if ($this->selectAll) {
        $this->selectedTeachers = collect($this->teachers)->pluck('id')->toArray();
    } else {
        $this->selectedTeachers = [];
    }
};

// Method to export teachers
$exportTeachers = function() {
    return redirect()->route('admin.users.teachers.export', [
        'ids' => implode(',', $this->selectedTeachers),
        'search' => $this->search,
        'status' => $this->status,
        'dateRange' => isset($this->filters['dateRange']) ? $this->filters['dateRange'] : null,
    ]);
};

// Method to toggle filters visibility
$toggleFilters = function() {
    $this->expandedFilters = !$this->expandedFilters;
};

// Method to reset filters
$resetFilters = function() {
    $this->search = '';
    $this->status = 'active';
    $this->filters = [
        'dateRange' => null,
    ];
    $this->loadTeachers();
};

// Method to apply filters
$applyFilters = function() {
    $this->page = 1;
    $this->loadTeachers();
};

// Wire:updated event for search
$updatedSearch = function() {
    $this->page = 1;
    $this->loadTeachers();
};

// Wire:updated event for perPage
$updatedPerPage = function() {
    $this->page = 1;
    $this->loadTeachers();
};

// Wire:updated event for status
$updatedStatus = function() {
    $this->page = 1;
    $this->loadTeachers();
};

// Delete teacher method
$deleteTeacher = function($teacherId) {
    $teacher = User::findOrFail($teacherId);
    
    // Add activity log
    activity()
        ->causedBy(auth()->user())
        ->performedOn($teacher)
        ->withProperties(['action' => 'delete'])
        ->log('Deleted teacher account');
        
    $teacher->delete();
    
    session()->flash('success', 'Teacher successfully deleted.');
    $this->loadTeachers();
};

// Bulk action method
$bulkAction = function($action) {
    if (empty($this->selectedTeachers)) {
        session()->flash('error', 'No teachers selected.');
        return;
    }
    
    $teachers = User::whereIn('id', $this->selectedTeachers)->get();
    
    switch ($action) {
        case 'delete':
            foreach ($teachers as $teacher) {
                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($teacher)
                    ->withProperties(['action' => 'bulk-delete'])
                    ->log('Bulk deleted teacher account');
                    
                $teacher->delete();
            }
            session()->flash('success', count($this->selectedTeachers) . ' teachers successfully deleted.');
            break;
            
        case 'activate':
            foreach ($teachers as $teacher) {
                $teacher->update(['status' => 'active']);
                
                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($teacher)
                    ->withProperties(['action' => 'bulk-activate'])
                    ->log('Bulk activated teacher account');
            }
            session()->flash('success', count($this->selectedTeachers) . ' teachers successfully activated.');
            break;
            
        case 'deactivate':
            foreach ($teachers as $teacher) {
                $teacher->update(['status' => 'inactive']);
                
                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($teacher)
                    ->withProperties(['action' => 'bulk-deactivate'])
                    ->log('Bulk deactivated teacher account');
            }
            session()->flash('success', count($this->selectedTeachers) . ' teachers successfully deactivated.');
            break;
    }
    
    $this->selectedTeachers = [];
    $this->selectAll = false;
    $this->loadTeachers();
};?>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-900">Teacher Management</h2>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.teachers.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Add Teacher
                </a>
                
                @if(count($selectedTeachers) > 0)
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
                        Bulk Actions ({{ count($selectedTeachers) }})
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-50">
                        <div class="py-1">
                            <button wire:click="bulkAction('activate')" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Activate</button>
                            <button wire:click="bulkAction('deactivate')" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Deactivate</button>
                            <button wire:click="exportTeachers" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Export</button>
                            <button wire:click="bulkAction('delete')" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Delete</button>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif
        
        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif
        
        <!-- Search and Filters -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-3 md:space-y-0 mb-4">
                    <div class="flex-1 md:mr-4">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text" wire:model.debounce.300ms="search" class="pl-10 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Search by name, email, phone or ID...">
                        </div>
                    </div>
                    
                    <div class="flex space-x-2">
                        <select wire:model="status" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                        </select>
                        
                        <select wire:model="perPage" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            <option value="15">15 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                        
                        <button wire:click="toggleFilters" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            Filters
                        </button>
                    </div>
                </div>
                
                <!-- Advanced Filters -->
                <div x-data="{ open: @entangle('expandedFilters') }" x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" class="mt-4 bg-gray-50 p-4 rounded-md">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                            <input type="text" wire:model="filters.dateRange" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="YYYY-MM-DD to YYYY-MM-DD">
                        </div>
                    </div>
                    
                    <div class="mt-4 flex justify-end space-x-3">
                        <button wire:click="resetFilters" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Reset
                        </button>
                        <button wire:click="applyFilters" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Teachers Table -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="text-sm text-gray-500 mb-4">
                    Showing {{ $paginationInfo['from'] }} to {{ $paginationInfo['to'] }} of {{ $filteredTeachersCount }} teachers
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" wire:model="selectAll" wire:click="toggleSelectAll" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="applySort('name')">
                                    <div class="flex items-center">
                                        Name
                                        @if($sortBy === 'name')
                                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                @if($sortDirection === 'asc')
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                @endif
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="applySort('email')">
                                    <div class="flex items-center">
                                        Email
                                        @if($sortBy === 'email')
                                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                @if($sortDirection === 'asc')
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                @endif
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Phone
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="applySort('status')">
                                    <div class="flex items-center">
                                        Status
                                        @if($sortBy === 'status')
                                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                @if($sortDirection === 'asc')
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                @endif
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="applySort('created_at')">
                                    <div class="flex items-center">
                                        Joined
                                        @if($sortBy === 'created_at')
                                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                @if($sortDirection === 'asc')
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                @endif
                                            </svg>
                                        @endif
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($teachers as $teacher)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" wire:model="selectedTeachers" value="{{ $teacher['id'] }}" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full" src="{{ isset($teacher['profile_photo_url']) ? $teacher['profile_photo_url'] : 'https://ui-avatars.com/api/?name='.urlencode($teacher['name']) }}" alt="{{ $teacher['name'] }}">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $teacher['name'] }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    ID: {{ $teacher['employee_id'] ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $teacher['email'] }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $teacher['phone'] ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            @if($teacher['status'] === 'active')
                                                bg-green-100 text-green-800
                                            @elseif($teacher['status'] === 'inactive')
                                                bg-red-100 text-red-800
                                            @else
                                                bg-yellow-100 text-yellow-800
                                            @endif
                                        ">
                                            {{ ucfirst($teacher['status']) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($teacher['created_at'])->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <a href="{{ route('admin.teachers.show', $teacher['id']) }}" class="text-indigo-600 hover:text-indigo-900" title="View">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </a>
                                            <a href="{{ route('admin.teachers.edit', $teacher['id']) }}" class="text-green-600 hover:text-green-900" title="Edit">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                            <button wire:click="deleteTeacher({{ $teacher['id'] }})" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirm('Are you sure you want to delete this teacher?')">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No teachers found matching your criteria.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <!-- Custom Pagination -->
                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium">{{ $paginationInfo['from'] }}</span> to 
                                <span class="font-medium">{{ $paginationInfo['to'] }}</span> of 
                                <span class="font-medium">{{ $paginationInfo['total'] }}</span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <!-- Previous Page Link -->
                                <button 
                                    wire:click="goToPage({{ $paginationInfo['currentPage'] - 1 }})" 
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" 
                                    {{ $paginationInfo['currentPage'] <= 1 ? 'disabled' : '' }}
                                >
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                
                                <!-- Page Number Links -->
                                @for ($i = 1; $i <= $paginationInfo['lastPage']; $i++)
                                    <button 
                                        wire:click="goToPage({{ $i }})" 
                                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium {{ $i === $paginationInfo['currentPage'] ? 'bg-indigo-50 text-indigo-600' : 'text-gray-700 hover:bg-gray-50' }}"
                                    >
                                        {{ $i }}
                                    </button>
                                @endfor
                                
                                <!-- Next Page Link -->
                                <button 
                                    wire:click="goToPage({{ $paginationInfo['currentPage'] + 1 }})" 
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                                    {{ $paginationInfo['currentPage'] >= $paginationInfo['lastPage'] ? 'disabled' : '' }}
                                >
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="text-sm font-medium text-gray-500">Total Teachers</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $filteredTeachersCount }}</div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="text-sm font-medium text-gray-500">Active Teachers</div>
                    <div class="mt-1 text-3xl font-semibold text-green-600">
                        {{ User::where('role', User::ROLE_TEACHER)->where('status', 'active')->count() }}
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="text-sm font-medium text-gray-500">New This Month</div>
                    <div class="mt-1 text-3xl font-semibold text-indigo-600">
                        {{ User::where('role', User::ROLE_TEACHER)->whereMonth('created_at', now()->month)->count() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>