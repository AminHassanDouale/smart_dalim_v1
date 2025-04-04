<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\ParentProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

new class extends Component {
    use WithPagination;

    // State variables
    public $search = '';
    public $perPage = 10;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';
    public $filters = [
        'status' => '',
        'created_date' => '',
        'has_children' => '',
        'country' => '',
    ];
    public $selectedParents = [];
    public $selectAll = false;
    public $showDeleteModal = false;
    public $showFilterModal = false;
    public $showImportModal = false;
    public $showViewModal = false;
    public $selectedParentDetails = null;
    public $csvFile = null;

    // Get parents with related data, search and filters
    public function getParents() {
        return User::query()
            ->where('role', User::ROLE_PARENT)
            ->with(['parentProfile.children'])
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhereHas('parentProfile', function (Builder $subQuery) {
                            $subQuery->where('phone_number', 'like', '%' . $this->search . '%')
                                // Remove the city and address search if those columns don't exist
                                ->orWhere('address', 'like', '%' . $this->search . '%');
                                // Remove this line: ->orWhere('city', 'like', '%' . $this->search . '%');
                        });
                });
            })
            ->when($this->filters['status'] === 'active', function (Builder $query) {
                $query->whereHas('parentProfile', function (Builder $subQuery) {
                    $subQuery->where('has_completed_profile', true);
                });
            })
            ->when($this->filters['status'] === 'inactive', function (Builder $query) {
                $query->whereHas('parentProfile', function (Builder $subQuery) {
                    $subQuery->where('has_completed_profile', false);
                })
                ->orWhereDoesntHave('parentProfile');
            })
            ->when($this->filters['has_children'] === 'with', function (Builder $query) {
                $query->whereHas('parentProfile.children');
            })
            ->when($this->filters['has_children'] === 'without', function (Builder $query) {
                $query->whereHas('parentProfile', function (Builder $subQuery) {
                    $subQuery->whereDoesntHave('children');
                });
            })
            ->when($this->filters['country'], function (Builder $query, $country) {
                $query->whereHas('parentProfile', function (Builder $subQuery) use ($country) {
                    $subQuery->where('country', $country);
                });
            })
            ->when($this->filters['created_date'], function (Builder $query, $date) {
                $query->whereDate('created_at', $date);
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);
    }

    // Get all countries for filtering
   // Get all countries for filtering
public function getCountries() {
    return [];
}

    // Get total parents count
    public function getTotalParents() {
        return User::where('role', User::ROLE_PARENT)->count();
    }

    // Get active parents count
    public function getActiveParents() {
        return User::where('role', User::ROLE_PARENT)
            ->whereHas('parentProfile', function (Builder $query) {
                $query->where('has_completed_profile', true);
            })
            ->count();
    }

    // Get inactive parents count
    public function getInactiveParents() {
        return User::where('role', User::ROLE_PARENT)
            ->where(function (Builder $query) {
                $query->whereHas('parentProfile', function (Builder $subQuery) {
                    $subQuery->where('has_completed_profile', false);
                })
                ->orWhereDoesntHave('parentProfile');
            })
            ->count();
    }

    // Reset filters
    public function resetFilters() {
        $this->filters = [
            'status' => '',
            'created_date' => '',
            'has_children' => '',
            'country' => '',
        ];
    }

    // Sort function
    public function sortBy($field) {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // Handle bulk selection
    public function updatedSelectAll() {
        if ($this->selectAll) {
            $this->selectedParents = $this->getParents()->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedParents = [];
        }
    }

    // Confirm delete
    public function confirmDelete($id = null) {
        if ($id) {
            $this->selectedParents = [$id];
        }
        $this->showDeleteModal = true;
    }

    // Delete selected parents
    public function deleteSelected() {
        // Get the parent profiles to delete
        $parentProfiles = ParentProfile::whereIn('user_id', $this->selectedParents)->get();
        
        // For each parent profile, delete related children first
        foreach ($parentProfiles as $profile) {
            $profile->children()->delete();
        }
        
        // Delete parent profiles
        ParentProfile::whereIn('user_id', $this->selectedParents)->delete();
        
        // Delete users
        User::whereIn('id', $this->selectedParents)->delete();
        
        $this->selectedParents = [];
        $this->selectAll = false;
        $this->showDeleteModal = false;
        $this->dispatch('parents-deleted');
    }

    // View parent details
    public function viewParentDetails($id) {
        $this->selectedParentDetails = User::with(['parentProfile.children' => function($query) {
            $query->with(['teacher', 'subjects']);
        }])->find($id);
        
        $this->showViewModal = true;
    }

    // Import parents from CSV
    public function importParents() {
        $this->validate([
            'csvFile' => 'required|mimes:csv,txt|max:1024',
        ]);
        
        // Process CSV file (implement your CSV import logic here)
        // ...
        
        $this->showImportModal = false;
        $this->dispatch('parents-imported');
    }

    // Export parents to CSV
    public function exportParents() {
        return response()->streamDownload(function () {
            $csv = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($csv, ['ID', 'Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Country', 'Children', 'Status', 'Created At']);
            
            // Add data
            User::where('role', User::ROLE_PARENT)
                ->with(['parentProfile.children'])
                ->when($this->search, function (Builder $query) {
                    $query->where(function (Builder $query) {
                        $query->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%')
                            ->orWhereHas('parentProfile', function (Builder $subQuery) {
                                $subQuery->where('phone_number', 'like', '%' . $this->search . '%')
                                    ->orWhere('address', 'like', '%' . $this->search . '%')
                                    ->orWhere('city', 'like', '%' . $this->search . '%');
                            });
                    });
                })
                ->when($this->filters['status'], function (Builder $query, $status) {
                    if ($status === 'active') {
                        $query->whereHas('parentProfile', function (Builder $subQuery) {
                            $subQuery->where('has_completed_profile', true);
                        });
                    } else {
                        $query->whereHas('parentProfile', function (Builder $subQuery) {
                            $subQuery->where('has_completed_profile', false);
                        })
                        ->orWhereDoesntHave('parentProfile');
                    }
                })
                ->orderBy($this->sortField, $this->sortDirection)
                ->chunk(100, function ($parents) use ($csv) {
                    foreach ($parents as $parent) {
                        fputcsv($csv, [
                            $parent->id,
                            $parent->name,
                            $parent->email,
                            $parent->parentProfile->phone_number ?? '',
                            $parent->parentProfile->address ?? '',
                            $parent->parentProfile->city ?? '',
                            $parent->parentProfile->state ?? '',
                            $parent->parentProfile->country ?? '',
                            $parent->children->count() ?? 0,
                            $parent->hasCompletedProfile() ? 'Active' : 'Inactive',
                            $parent->created_at->format('Y-m-d H:i:s'),
                        ]);
                    }
                });
            
            fclose($csv);
        }, 'parents-' . now()->format('Y-m-d') . '.csv');
    }
}; ?>

<div class="p-6 space-y-4">
    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-gray-500 text-sm">Total Parents</h3>
                    <p class="text-2xl font-semibold">{{ $this->getTotalParents() }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-gray-500 text-sm">Active Parents</h3>
                    <p class="text-2xl font-semibold">{{ $this->getActiveParents() }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-gray-500 text-sm">Inactive Parents</h3>
                    <p class="text-2xl font-semibold">{{ $this->getInactiveParents() }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-gray-500 text-sm">With Children</h3>
                    <p class="text-2xl font-semibold">{{ User::where('role', User::ROLE_PARENT)->whereHas('parentProfile.children')->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Actions Bar -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex-1">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input 
                        wire:model.live.debounce.300ms="search" 
                        type="text" 
                        placeholder="Search parents by name, email, phone or address..." 
                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
            </div>
            
            <div class="flex items-center space-x-2">
                <button 
                    wire:click="$set('showFilterModal', true)" 
                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filters
                    </div>
                </button>
                
                <button 
                    wire:click="$set('showImportModal', true)" 
                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Import
                    </div>
                </button>
                
                <button 
                    wire:click="exportParents" 
                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export
                    </div>
                </button>
                
                <a 
                    href="{{ route('admin.parents.create') }}" 
                    class="px-4 py-2 bg-blue-600 rounded-lg text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <div class="flex items-center">
                        <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Parent
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium">Parents</h3>
                
                @if(count($selectedParents) > 0)
                <div class="flex items-center space-x-2">
                    <span class="text-gray-500">{{ count($selectedParents) }} selected</span>
                    <button 
                        wire:click="confirmDelete" 
                        class="px-3 py-1 bg-red-600 rounded text-white text-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                    >
                        Delete Selected
                    </button>
                </div>
                @endif
            </div>
        </div>

        <!-- Parents Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left">
                            <div class="flex items-center">
                                <input 
                                    wire:model.live="selectAll" 
                                    type="checkbox" 
                                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                >
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('name')">
                            <div class="flex items-center">
                                Name
                                @if($sortField === 'name')
                                    @if($sortDirection === 'asc')
                                        <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    @endif
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('email')">
                            <div class="flex items-center">
                                Email
                                @if($sortField === 'email')
                                    @if($sortDirection === 'asc')
                                        <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    @endif
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Phone
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Location
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Children
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                            <div class="flex items-center">
                                Status
                                @if($sortField === 'has_completed_profile')
                                    @if($sortDirection === 'asc')
                                        <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    @endif
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" wire:click="sortBy('created_at')">
                            <div class="flex items-center">
                                Registered
                                @if($sortField === 'created_at')
                                    @if($sortDirection === 'asc')
                                        <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    @else
                                        <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    @endif
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($this->getParents() as $parent)
                    <tr wire:key="{{ $parent->id }}" class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <input 
                                    wire:model.live="selectedParents" 
                                    value="{{ $parent->id }}" 
                                    type="checkbox" 
                                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                >
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-10 w-10 flex-shrink-0">
                                    <img class="h-10 w-10 rounded-full" src="{{ $parent->parentProfile->profile_photo_path ?? 'https://ui-avatars.com/api/?name=' . urlencode($parent->name) . '&color=7F9CF5&background=EBF4FF' }}" alt="{{ $parent->name }}">
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $parent->name }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        ID: {{ $parent->id }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $parent->email }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $parent->parentProfile->phone_number ?? 'Not set' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($parent->parentProfile)
                                <div class="text-sm text-gray-900">{{ $parent->parentProfile->city ?? '' }}{{ $parent->parentProfile->city && $parent->parentProfile->country ? ', ' : '' }}{{ $parent->parentProfile->country ?? '' }}</div>
                                <div class="text-xs text-gray-500">{{ $parent->parentProfile->address ?? 'No address' }}</div>
                            @else
                                <div class="text-sm text-gray-500">No location data</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($parent->parentProfile && $parent->parentProfile->children->count() > 0)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    {{ $parent->parentProfile->children->count() }} {{ Str::plural('child', $parent->parentProfile->children->count()) }}
                                </span>
                            @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                No children
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $parent->hasCompletedProfile() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $parent->hasCompletedProfile() ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $parent->created_at->format('M d, Y') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex justify-end space-x-2">
                            <button wire:click="viewParentDetails('{{ $parent->id }}')" class="text-blue-600 hover:text-blue-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                            <a href="{{ route('admin.parents.edit', $parent->id) }}" class="text-indigo-600 hover:text-indigo-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                            <button wire:click="confirmDelete('{{ $parent->id }}')" class="text-red-600 hover:text-red-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="py-4 px-6">
        {{ $this->getParents()->links() }}
    </div>
</div>

<!-- Filter Modal -->
<div 
    x-data="{ show: @entangle('showFilterModal').live }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 transition-opacity"
            aria-hidden="true"
        >
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
        >
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Filter Parents
                        </h3>
                        
                        <div class="mt-2 space-y-4">
                            <div>
                                <label for="status-filter" class="block text-sm font-medium text-gray-700">Status</label>
                                <select 
                                    wire:model.live="filters.status" 
                                    id="status-filter" 
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                                >
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="has-children-filter" class="block text-sm font-medium text-gray-700">Children</label>
                                <select 
                                    wire:model.live="filters.has_children" 
                                    id="has-children-filter" 
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                                >
                                    <option value="">All Parents</option>
                                    <option value="with">With Children</option>
                                    <option value="without">Without Children</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="country-filter" class="block text-sm font-medium text-gray-700">Country</label>
                                <select 
                                    wire:model.live="filters.country" 
                                    id="country-filter" 
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                                >
                                    <option value="">All Countries</option>
                                    @foreach($this->getCountries() as $country)
                                        <option value="{{ $country }}">{{ $country }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div>
                                <label for="created-date" class="block text-sm font-medium text-gray-700">Registered Date</label>
                                <input 
                                    wire:model.live="filters.created_date" 
                                    type="date" 
                                    id="created-date" 
                                    class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button 
                    wire:click="$set('showFilterModal', false)" 
                    type="button" 
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Apply Filters
                </button>
                <button 
                    wire:click="resetFilters" 
                    type="button" 
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Reset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div 
    x-data="{ show: @entangle('showDeleteModal').live }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 transition-opacity"
            aria-hidden="true"
        >
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
        >
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Delete Parent{{ count($selectedParents) > 1 ? 's' : '' }}
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to delete the selected parent{{ count($selectedParents) > 1 ? 's' : '' }}? This action will also remove any associated children and cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button 
                    wire:click="deleteSelected" 
                    type="button" 
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Delete
                </button>
                <button 
                    wire:click="$set('showDeleteModal', false)" 
                    type="button" 
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div 
    x-data="{ show: @entangle('showImportModal').live }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 transition-opacity"
            aria-hidden="true"
        >
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
        >
            <form wire:submit="importParents">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Import Parents
                            </h3>
                            
                            <div class="mt-2 space-y-4">
                                <div>
                                    <label for="csv-file" class="block text-sm font-medium text-gray-700">CSV File</label>
                                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                        <div class="space-y-1 text-center">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="file-upload" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                    <span>Upload a file</span>
                                                    <input id="file-upload" wire:model="csvFile" name="file-upload" type="file" class="sr-only" accept=".csv">
                                                </label>
                                                <p class="pl-1">or drag and drop</p>
                                            </div>
                                            <p class="text-xs text-gray-500">
                                                CSV up to 1MB
                                            </p>
                                        </div>
                                    </div>
                                    @error('csvFile') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                
                                <div>
                                    <a href="#" class="text-sm text-blue-600 hover:text-blue-500">
                                        Download Sample CSV Template
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button 
                        type="submit" 
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Upload
                    </button>
                    <button 
                        wire:click="$set('showImportModal', false)" 
                        type="button" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Parent Modal -->
<div 
    x-data="{ show: @entangle('showViewModal').live }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 transition-opacity"
            aria-hidden="true"
        >
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full"
        >
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2">
                            Parent Profile
                        </h3>
                        
                        @if($selectedParentDetails)
                        <div class="mt-4">
                            <div class="flex items-center mb-6">
                                <div class="h-16 w-16 flex-shrink-0">
                                    <img class="h-16 w-16 rounded-full" src="{{ $selectedParentDetails->parentProfile->profile_photo_path ?? 'https://ui-avatars.com/api/?name=' . urlencode($selectedParentDetails->name) . '&color=7F9CF5&background=EBF4FF' }}" alt="{{ $selectedParentDetails->name }}">
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-xl font-bold text-gray-900">{{ $selectedParentDetails->name }}</h2>
                                    <p class="text-sm text-gray-500">{{ $selectedParentDetails->email }}</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="text-md font-semibold text-gray-700 mb-2">Contact Information</h4>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <dl class="grid grid-cols-1 gap-3">
                                            <div class="flex flex-col">
                                                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $selectedParentDetails->parentProfile->phone_number ?? 'Not provided' }}</dd>
                                            </div>
                                            <div class="flex flex-col">
                                                <dt class="text-sm font-medium text-gray-500">Address</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $selectedParentDetails->parentProfile->address ?? 'Not provided' }}</dd>
                                            </div>
                                            <div class="flex flex-col">
                                                <dt class="text-sm font-medium text-gray-500">City, State, Country</dt>
                                                <dd class="mt-1 text-sm text-gray-900">
                                                    {{ $selectedParentDetails->parentProfile->city ?? '' }}
                                                    {{ $selectedParentDetails->parentProfile->state ? ', ' . $selectedParentDetails->parentProfile->state : '' }}
                                                    {{ $selectedParentDetails->parentProfile->country ? ', ' . $selectedParentDetails->parentProfile->country : '' }}
                                                    {{ !$selectedParentDetails->parentProfile->city && !$selectedParentDetails->parentProfile->state && !$selectedParentDetails->parentProfile->country ? 'Not provided' : '' }}
                                                </dd>
                                            </div>
                                            <div class="flex flex-col">
                                                <dt class="text-sm font-medium text-gray-500">Registered On</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $selectedParentDetails->created_at->format('F j, Y') }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 class="text-md font-semibold text-gray-700 mb-2">Profile Status</h4>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <dl class="grid grid-cols-1 gap-3">
                                            <div class="flex flex-col">
                                                <dt class="text-sm font-medium text-gray-500">Account Status</dt>
                                                <dd class="mt-1 text-sm">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $selectedParentDetails->hasCompletedProfile() ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                        {{ $selectedParentDetails->hasCompletedProfile() ? 'Active' : 'Incomplete' }}
                                                    </span>
                                                </dd>
                                            </div>
                                            <div class="flex flex-col">
                                                <dt class="text-sm font-medium text-gray-500">Number of Children</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ $selectedParentDetails->parentProfile ? $selectedParentDetails->parentProfile->children->count() : 0 }}</dd>
                                                </div>
                                                <div class="flex flex-col">
                                                <dt class="text-sm font-medium text-gray-500">Newsletter Subscription</dt>
                                                <dd class="mt-1 text-sm text-gray-900">
                                                    {{ $selectedParentDetails->parentProfile && $selectedParentDetails->parentProfile->newsletter_subscription ? 'Subscribed' : 'Not subscribed' }}
                                                </dd>
                                                </div>
                                                <div class="flex flex-col">
                                                <dt class="text-sm font-medium text-gray-500">Preferred Communication</dt>
                                                <dd class="mt-1 text-sm text-gray-900">
                                                    {{ $selectedParentDetails->parentProfile ? $selectedParentDetails->parentProfile->preferred_communication_method ?? 'Not specified' : 'Not specified' }}
                                                </dd>
                                                </div>
                                                </dl>
                                                </div>
                                                </div>
                                                </div>
                                                
                                                @if($selectedParentDetails->parentProfile && $selectedParentDetails->parentProfile->children->count() > 0)
                                                <div class="mt-6">
                                                <h4 class="text-md font-semibold text-gray-700 mb-2">Children ({{ $selectedParentDetails->parentProfile->children->count() }})</h4>
                                                <div class="bg-gray-50 rounded-lg p-4">
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full divide-y divide-gray-200">
                                                        <thead class="bg-gray-100">
                                                            <tr>
                                                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age/Gender</th>
                                                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School/Grade</th>
                                                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                                                <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjects</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="bg-white divide-y divide-gray-200">
                                                            @foreach($selectedParentDetails->parentProfile->children as $child)
                                                            <tr>
                                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $child->name }}</td>
                                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                                    {{ $child->age ?? 'N/A' }} {{ $child->gender ? ' / ' . $child->gender : '' }}
                                                                </td>
                                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                                    {{ $child->school_name ?? 'Not specified' }} {{ $child->grade ? ' / Grade ' . $child->grade : '' }}
                                                                </td>
                                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                                    {{ $child->teacher ? $child->teacher->name : 'Not assigned' }}
                                                                </td>
                                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                                    <div class="flex flex-wrap gap-1">
                                                                        @foreach($child->subjects as $subject)
                                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                                            {{ $subject->name }}
                                                                        </span>
                                                                        @endforeach
                                                                        @if($child->subjects->count() === 0)
                                                                        <span class="text-gray-400">None</span>
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                </div>
                                                </div>
                                                @endif
                                                
                                                @if($selectedParentDetails->parentProfile && $selectedParentDetails->parentProfile->additional_information)
                                                <div class="mt-6">
                                                <h4 class="text-md font-semibold text-gray-700 mb-2">Additional Information</h4>
                                                <div class="bg-gray-50 rounded-lg p-4">
                                                <p class="text-sm text-gray-900">{{ $selectedParentDetails->parentProfile->additional_information }}</p>
                                                </div>
                                                </div>
                                                @endif
                                                </div>
                                                @else
                                                <div class="py-4 text-center text-gray-500">
                                                Loading parent information...
                                                </div>
                                                @endif
                                                </div>
                                                </div>
                                                </div>
                                                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                                <a 
                                                href="{{ $selectedParentDetails ? route('admin.parents.edit', $selectedParentDetails->id) : '#' }}" 
                                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                                                >
                                                Edit
                                                </a>
                                                <button 
                                                wire:click="$set('showViewModal', false)" 
                                                type="button" 
                                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                                >
                                                Close
                                                </button>
                                                </div>
                                                </div>
                                                </div>
                                                </div>