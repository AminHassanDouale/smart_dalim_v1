<?php

use Livewire\Volt\Component;
use App\Models\Assessment;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    // User data
    public $user;
    
    // Filter states
    public $statusFilter = '';
    public $childFilter = '';
    public $typeFilter = '';
    
    // Data containers
    public $childrenWithAssessments = [];
    
    // Assessment statuses and types
    public $statuses = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'graded' => 'Graded'
    ];
    
    public function mount()
{
    $this->user = Auth::user();
    
    // If child_id is in the URL, verify it belongs to this parent first
    if (request()->has('child_id')) {
        $childId = request()->input('child_id');
        
        // Check if this child exists AND belongs to the current user
        $childBelongsToUser = $this->user->parentProfile
            ->children()
            ->where('id', $childId)
            ->exists();
        
        if ($childBelongsToUser) {
            $this->childFilter = $childId;
        } else {
            // Child not found or doesn't belong to this parent - ignore the parameter
            $this->childFilter = '';
            session()->flash('error', 'You can only view assessments for your own children.');
        }
    }
    
    $this->loadAssessments();
}
    
    public function loadAssessments()
    {
        // Get all children belonging to the parent
        $children = $this->user->parentProfile->children;
    
        if (!$children || $children->isEmpty()) {
            $this->childrenWithAssessments = [];
            return;
        }
        
        // Initialize array to collect all assessments
        $this->childrenWithAssessments = [];
        
        foreach ($children as $child) {
            // Skip if we're filtering by child and this isn't the selected one
            if ($this->childFilter && $child->id != $this->childFilter) {
                continue;
            }
            
            // Get all assessments assigned to this child
            $assessments = $child->assessments()
                ->with(['course', 'subject', 'teacherProfile'])
                ->when($this->typeFilter, function ($query) {
                    return $query->where('type', $this->typeFilter);
                })
                ->get();
                
            // Skip if no assessments found
            if ($assessments->isEmpty()) {
                $this->childrenWithAssessments[] = [
                    'child' => $child,
                    'assessments' => collect([])
                ];
                continue;
            }
            
            // Process assessments with safer null checks
            $processedAssessments = $assessments->map(function ($assessment) use ($child) {
                // Get the relationship record with this child
                $relationRecord = $assessment->children()
                    ->where('children.id', $child->id)
                    ->first();
                
                // Skip if no relationship exists
                if (!$relationRecord || !$relationRecord->pivot) {
                    return null;
                }
                
                $pivotData = $relationRecord->pivot;
                
                // Filter by status if needed
                if ($this->statusFilter && $pivotData->status !== $this->statusFilter) {
                    return null;
                }
                
                // Add the pivot data to the assessment
                $assessment->pivot_status = $pivotData->status;
                $assessment->pivot_score = $pivotData->score;
                $assessment->pivot_start_time = $pivotData->start_time;
                $assessment->pivot_end_time = $pivotData->end_time;
                
                return $assessment;
            })
            ->filter() // Remove null values
            ->values(); // Reindex array
            
            $this->childrenWithAssessments[] = [
                'child' => $child,
                'assessments' => $processedAssessments
            ];
        }
    }
    
    public function updatedStatusFilter()
    {
        $this->loadAssessments();
    }
    
    public function updatedChildFilter()
    {
        $this->loadAssessments();
    }
    
    public function updatedTypeFilter()
    {
        $this->loadAssessments();
    }
    
    public function getTypesProperty()
    {
        return Assessment::$types ?? [
            'quiz' => 'Quiz',
            'test' => 'Test',
            'exam' => 'Exam',
            'assignment' => 'Assignment',
            'project' => 'Project',
            'essay' => 'Essay',
            'presentation' => 'Presentation',
            'other' => 'Other',
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-800">
                    {{ __('Children\'s Assessments') }}
                </h2>
            </div>

            @if(session()->has('error'))
    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <!-- Error icon -->
                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700">
                    {{ session('error') }}
                </p>
            </div>
        </div>
    </div>
@endif
            
            <!-- Filters -->
            <div class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="child_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Child') }}</label>
                        <select id="child_id" wire:model.live="childFilter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">{{ __('All Children') }}</option>
                            @if($user && $user->children)
                                @foreach($user->children as $childOption)
                                    <option value="{{ $childOption->id }}">
                                        {{ $childOption->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                        <select id="status" wire:model.live="statusFilter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">{{ __('All Statuses') }}</option>
                            @if(is_array($statuses) || is_object($statuses))
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Type') }}</label>
                        <select id="type" wire:model.live="typeFilter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">{{ __('All Types') }}</option>
                            @if(method_exists($this, 'getTypesProperty') && (is_array($this->types) || is_object($this->types)))
                                @foreach($this->types as $value => $label)
                                    <option value="{{ $value }}">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>
            </div>

            @if(is_array($childrenWithAssessments) && count($childrenWithAssessments) > 0)
                @foreach($childrenWithAssessments as $childData)
                    @if(isset($childData['child']) && isset($childData['assessments']))
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4 bg-gray-100 p-3 rounded">
                                {{ $childData['child']->name ?? 'Unknown Child' }}'s Assessments
                            </h3>

                            @if(is_countable($childData['assessments']) && count($childData['assessments']) > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white">
                                        <thead>
                                            <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                                                <th class="py-3 px-6 text-left">Title</th>
                                                <th class="py-3 px-6 text-left">Type</th>
                                                <th class="py-3 px-6 text-left">Course/Subject</th>
                                                <th class="py-3 px-6 text-left">Due Date</th>
                                                <th class="py-3 px-6 text-left">Status</th>
                                                <th class="py-3 px-6 text-left">Score</th>
                                                <th class="py-3 px-6 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="text-gray-600 text-sm">
                                            @foreach($childData['assessments'] as $assessment)
                                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                                    <td class="py-4 px-6 text-left">
                                                        {{ $assessment->title ?? 'N/A' }}
                                                    </td>
                                                    <td class="py-4 px-6 text-left">
                                                        @if(isset($assessment->type) && method_exists($this, 'getTypesProperty') && isset($this->types[$assessment->type]))
                                                            {{ $this->types[$assessment->type] }}
                                                        @else
                                                            {{ $assessment->type ?? 'N/A' }}
                                                        @endif
                                                    </td>
                                                    <td class="py-4 px-6 text-left">
                                                        {{ $assessment->course->name ?? 'N/A' }}
                                                        <br>
                                                        <span class="text-xs text-gray-500">
                                                            {{ $assessment->subject->name ?? 'N/A' }}
                                                        </span>
                                                    </td>
                                                    <td class="py-4 px-6 text-left">
                                                        @if(isset($assessment->due_date))
                                                            {{ $assessment->due_date->format('M d, Y h:i A') }}
                                                            @if($assessment->due_date->isPast())
                                                                <span class="text-red-500 text-xs">(Past due)</span>
                                                            @endif
                                                        @else
                                                            No due date
                                                        @endif
                                                    </td>
                                                    <td class="py-4 px-6 text-left">
                                                        @if(isset($assessment->pivot_status))
                                                            <span class="px-2 py-1 rounded text-xs
                                                                @if($assessment->pivot_status == 'completed' || $assessment->pivot_status == 'graded')
                                                                    bg-green-100 text-green-800
                                                                @elseif($assessment->pivot_status == 'in_progress')
                                                                    bg-blue-100 text-blue-800
                                                                @else
                                                                    bg-yellow-100 text-yellow-800
                                                                @endif
                                                            ">
                                                                @if(isset($statuses[$assessment->pivot_status]))
                                                                    {{ $statuses[$assessment->pivot_status] }}
                                                                @else
                                                                    {{ $assessment->pivot_status }}
                                                                @endif
                                                            </span>
                                                        @else
                                                            <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-800">
                                                                Unknown
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="py-4 px-6 text-left">
                                                        @if(isset($assessment->pivot_status) && ($assessment->pivot_status == 'completed' || $assessment->pivot_status == 'graded'))
                                                            <span class="font-semibold">
                                                                {{ $assessment->pivot_score ?? 'N/A' }} / {{ $assessment->total_points ?? '?' }}
                                                            </span>
                                                            @if(isset($assessment->passing_points) && isset($assessment->pivot_score) && $assessment->pivot_score >= $assessment->passing_points)
                                                                <span class="text-green-500 ml-2">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </span>
                                                            @endif
                                                        @else
                                                            --
                                                        @endif
                                                    </td>
                                                    <td class="py-4 px-6 text-left">
                                                        @if(isset($assessment->id) && isset($childData['child']->id))
                                                            <a href="{{ route('parents.assessments.show', ['id' => $assessment->id, 'childId' => $childData['child']->id]) }}" 
                                                            class="text-blue-600 hover:text-blue-900">
                                                                View Details
                                                            </a>
                                                        @else
                                                            <span class="text-gray-400">Not available</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                                    <div class="flex">
                                        <div>
                                            <p class="text-yellow-700">
                                                No assessments found for {{ $childData['child']->name ?? 'this child' }}.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                @endforeach
            @else
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div>
                            <p class="text-yellow-700">
                                No children or assessments found.
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>