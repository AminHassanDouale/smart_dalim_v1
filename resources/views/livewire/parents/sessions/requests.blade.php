<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Children;
use App\Models\Subject;
use App\Models\User;
use App\Models\LearningSession;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;
    use WithFileUploads;

    public $user;
    public $children = [];

    // Request form fields
    public $selectedChild = null;
    public $selectedSubject = null;
    public $selectedTeacher = null;
    public $sessionType = 'one-time';
    public $sessionDate = '';
    public $sessionTime = '';
    public $sessionDuration = 60;
    public $sessionLocation = 'online';
    public $customLocation = '';
    public $repeatOption = 'no';
    public $repeatFrequency = 'weekly';
    public $repeatUntil = '';
    public $sessionNotes = '';
    public $attachments = [];

    // Request history and filtering
    public $statusFilter = '';
    public $dateRangeFilter = 'all';
    public $searchQuery = '';
    public $sortBy = 'created_at';
    public $sortDir = 'desc';

    // Modals
    public $showSuccessModal = false;
    public $showConfirmationModal = false;
    public $requestToCancel = null;
    public $showRequestDetailModal = false;
    public $selectedRequest = null;

    // Availability slots
    public $availableSlots = [];
    public $recommendedSlots = [];
    public $showRecommendedSlotsModal = false;

    // Filtered options
    public $filteredTeachers = [];
    public $filteredSubjects = [];

    // Request cost estimate
    public $costEstimate = 0;

    // Step tracking for request form
    public $currentStep = 1;
    public $totalSteps = 3;

    // Form view mode
    public $formMode = 'wizard'; // 'wizard' or 'compact'

    public $todayDate;
    
    // Current child ID from query string
    public $childId = null;

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'dateRangeFilter' => ['except' => 'all'],
        'searchQuery' => ['except' => ''],
        'page' => ['except' => 1],
        'childId' => ['except' => null, 'as' => 'child_id'],
    ];

    protected $rules = [
        'selectedChild' => 'required',
        'selectedSubject' => 'required',
        'sessionType' => 'required|in:one-time,recurring,package',
        'sessionDate' => 'required|date|after_or_equal:today',
        'sessionTime' => 'required',
        'sessionDuration' => 'required|integer|min:30|max:180',
        'sessionLocation' => 'required|in:online,home,center,custom',
        'customLocation' => 'required_if:sessionLocation,custom',
        'repeatOption' => 'required_if:sessionType,recurring',
        'repeatFrequency' => 'required_if:repeatOption,yes',
        'repeatUntil' => 'required_if:repeatOption,yes|nullable|date|after:sessionDate',
        'sessionNotes' => 'nullable|string|max:1000',
        'attachments.*' => 'nullable|file|max:10240', // 10MB max
    ];

    protected $messages = [
        'selectedChild.required' => 'Please select a child for the session.',
        'selectedSubject.required' => 'Please select a subject for the session.',
        'sessionDate.required' => 'Please select a date for the session.',
        'sessionDate.after_or_equal' => 'The session date must be today or a future date.',
        'sessionTime.required' => 'Please select a time for the session.',
        'repeatUntil.after' => 'The end date must be after the start date.',
        'customLocation.required_if' => 'Please provide a location for the session.',
    ];

    public function mount()
    {
        $this->user = Auth::user();

        // Get all children for this parent
        $this->children = $this->user->parentProfile->children()->get();

        // Set default selected child if available
        if ($this->childId && $this->children->contains('id', $this->childId)) {
            $this->selectedChild = $this->childId;
        } elseif ($this->children->count() > 0 && !$this->selectedChild) {
            $this->selectedChild = $this->children->first()->id;
        }

        // Set today's date
        $this->todayDate = Carbon::today()->format('Y-m-d');
        $this->sessionDate = Carbon::today()->addDay()->format('Y-m-d');
        $this->repeatUntil = Carbon::today()->addDays(30)->format('Y-m-d');

        // Set default time (9 AM)
        $this->sessionTime = "09:00";

        // Initialize filters for dependent dropdowns
        $this->updateFilteredOptions();
        
        // Initialize cost estimate
        $this->updateCostEstimate();
    }

    public function updated($field)
    {
        // Validate only the field that was updated
        $this->validateOnly($field);

        if (in_array($field, ['selectedChild', 'selectedSubject'])) {
            $this->updateFilteredOptions();
        }

        if (in_array($field, ['sessionDuration', 'sessionType', 'repeatOption', 'repeatFrequency'])) {
            $this->updateCostEstimate();
        }

        if (in_array($field, ['selectedSubject', 'sessionDate', 'sessionTime'])) {
            $this->generateAvailableSlots();
        }
    }

    private function updateFilteredOptions()
    {
        if ($this->selectedChild) {
            $child = $this->children->firstWhere('id', $this->selectedChild);

            if ($child) {
                // Get subjects for the selected child
                $this->filteredSubjects = $child->subjects;

                // Reset selected subject if it's not in the filtered list
                if ($this->selectedSubject && !$this->filteredSubjects->contains('id', $this->selectedSubject)) {
                    $this->selectedSubject = null;
                }

                // Set default subject if none selected and options available
                if (!$this->selectedSubject && $this->filteredSubjects->count() > 0) {
                    $this->selectedSubject = $this->filteredSubjects->first()->id;
                }

                // Get teachers based on subject
                $this->updateFilteredTeachers();
            }
        }
    }

    private function updateFilteredTeachers()
    {
        $this->filteredTeachers = [];

        if ($this->selectedSubject) {
            // Get teachers who teach the selected subject
            $this->filteredTeachers = User::where('role', 'teacher')
                ->whereHas('teacherProfile.subjects', function($query) {
                    $query->where('subjects.id', $this->selectedSubject);
                })
                ->get();
                
            // If no specific teachers are found, get general teachers
            if ($this->filteredTeachers->isEmpty()) {
                $this->filteredTeachers = User::where('role', 'teacher')
                    ->take(3)
                    ->get();
            }
        }
    }

    private function updateCostEstimate()
    {
        // Calculate based on duration and session type
        $baseRate = 40; // $40 per hour
        $hourlyRate = $baseRate * ($this->sessionDuration / 60);

        if ($this->sessionType === 'one-time') {
            $this->costEstimate = $hourlyRate;
        } elseif ($this->sessionType === 'recurring' && $this->repeatOption === 'yes') {
            // Calculate number of sessions based on frequency and duration
            $startDate = Carbon::parse($this->sessionDate);
            $endDate = $this->repeatUntil ? Carbon::parse($this->repeatUntil) : $startDate->copy()->addMonths(1);

            $sessions = 1; // Start with the first session

            if ($this->repeatFrequency === 'weekly') {
                $sessions += $startDate->diffInWeeks($endDate);
            } elseif ($this->repeatFrequency === 'biweekly') {
                $sessions += floor($startDate->diffInWeeks($endDate) / 2);
            } elseif ($this->repeatFrequency === 'monthly') {
                $sessions += $startDate->diffInMonths($endDate);
            }

            $this->costEstimate = $hourlyRate * $sessions;

            // Apply discount for recurring sessions
            $this->costEstimate *= 0.9; // 10% discount
        } elseif ($this->sessionType === 'package') {
            // Package pricing (10 sessions)
            $this->costEstimate = $hourlyRate * 10 * 0.85; // 15% discount
        }

        // Round to 2 decimal places
        $this->costEstimate = round($this->costEstimate, 2);
    }

    private function generateAvailableSlots()
    {
        $this->availableSlots = [];

        if ($this->selectedSubject && $this->sessionDate) {
            $date = Carbon::parse($this->sessionDate);
            
            // In a real app, we would check teacher availability here
            // For now, generate some reasonable time slots
            $slots = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
            
            foreach ($slots as $time) {
                $this->availableSlots[] = [
                    'date' => $date->format('Y-m-d'),
                    'time' => $time,
                    'teacher' => $this->filteredTeachers->isNotEmpty()
                        ? $this->filteredTeachers->random()->name
                        : 'Available Teacher',
                ];
            }
        }

        // Generate up to 3 recommended slots
        $count = min(3, count($this->availableSlots));
        if ($count > 0) {
            $this->recommendedSlots = array_slice($this->availableSlots, 0, $count);
        } else {
            $this->recommendedSlots = [];
        }
    }

    public function selectRecommendedSlot($index)
    {
        if (isset($this->recommendedSlots[$index])) {
            $slot = $this->recommendedSlots[$index];
            $this->sessionDate = $slot['date'];
            $this->sessionTime = $slot['time'];

            // Find teacher by name
            if ($this->filteredTeachers->isNotEmpty()) {
                $teacher = $this->filteredTeachers->firstWhere('name', $slot['teacher']);
                if ($teacher) {
                    $this->selectedTeacher = $teacher->id;
                }
            }
        }
        
        $this->showRecommendedSlotsModal = false;
    }

    public function openRecommendedSlotsModal()
    {
        $this->generateAvailableSlots();
        $this->showRecommendedSlotsModal = true;
    }

    public function closeRecommendedSlotsModal()
    {
        $this->showRecommendedSlotsModal = false;
    }

    public function toggleFormMode()
    {
        $this->formMode = $this->formMode === 'wizard' ? 'compact' : 'wizard';
        $this->currentStep = 1;
    }

    public function nextStep()
    {
        // Validate current step
        $this->validateCurrentStep();

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function validateCurrentStep()
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'selectedChild' => 'required',
                'selectedSubject' => 'required',
                'sessionType' => 'required|in:one-time,recurring,package',
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'sessionDate' => 'required|date|after_or_equal:today',
                'sessionTime' => 'required',
                'sessionDuration' => 'required|integer|min:30|max:180',
                'sessionLocation' => 'required|in:online,home,center,custom',
                'customLocation' => 'required_if:sessionLocation,custom',
                'repeatOption' => 'required',
                'repeatFrequency' => 'required_if:repeatOption,yes',
                'repeatUntil' => 'required_if:repeatOption,yes|nullable|date|after:sessionDate',
            ]);
        }
    }

    public function requestSession()
    {
        // Validate all form fields before submitting
        $this->validate();

        // Update cost estimate
        $this->updateCostEstimate();

        // In a real app, you would create a new LearningSession here
        // For now, just show success message
        $this->showSuccessModal = true;

        // Reset form
        $this->reset([
            'sessionType',
            'sessionTime',
            'sessionDuration',
            'sessionLocation',
            'customLocation',
            'repeatOption',
            'repeatFrequency',
            'repeatUntil',
            'sessionNotes',
            'attachments',
        ]);

        // Set today's date + 1 day
        $this->sessionDate = Carbon::today()->addDay()->format('Y-m-d');

        // Reset to step 1
        $this->currentStep = 1;
    }

    public function viewRequestDetail($requestId)
    {
        // Find the session request
        $session = LearningSession::with(['subject', 'teacher'])
            ->find($requestId);
            
        if ($session) {
            $childName = '';
            foreach ($this->children as $child) {
                if ($child->id == $session->children_id) {
                    $childName = $child->name;
                    break;
                }
            }
            
            $this->selectedRequest = [
                'id' => $session->id,
                'child_name' => $childName,
                'subject' => $session->subject->name,
                'status' => $session->status,
                'created_at' => $session->created_at,
                'date' => $session->start_time->format('Y-m-d'),
                'time' => $session->start_time->format('H:i'),
                'duration' => $session->duration_minutes,
                'location' => $session->location,
                'notes' => $session->notes,
                'teacher' => $session->teacher ? $session->teacher->name : 'Not assigned yet',
                'cost' => $session->cost,
                'type' => $session->recurring ? 'recurring' : 'one-time',
            ];
            
            $this->showRequestDetailModal = true;
        }
    }

    public function closeRequestDetailModal()
    {
        $this->showRequestDetailModal = false;
        $this->selectedRequest = null;
    }

    public function confirmCancelRequest($requestId)
    {
        $this->requestToCancel = $requestId;

        $this->toast(
            type: 'warning',
            title: 'Confirm Cancellation',
            description: 'Are you sure you want to cancel this session request?',
            position: 'toast-bottom toast-end',
            icon: 'o-exclamation-triangle',
            css: 'alert-warning',
            timeout: false,
            action: [
                'label' => 'Yes, Cancel',
                'onClick' => "$wire.cancelRequest($requestId)"
            ]
        );
    }

    public function cancelRequest($requestId)
    {
        // Find and update the session status
        $session = LearningSession::find($requestId);
        if ($session) {
            $session->status = 'cancelled';
            $session->save();
            
            $this->toast(
                type: 'success',
                title: 'Request Cancelled',
                description: 'The session request has been cancelled successfully.',
                position: 'toast-bottom toast-end',
                icon: 'o-check-circle',
                css: 'alert-success',
                timeout: 3000
            );
        }

        $this->requestToCancel = null;
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
    }

    public function getRequestsProperty()
    {
        // Start with a query builder to get all sessions for the parent's children
        $query = LearningSession::whereIn('children_id', $this->children->pluck('id'))
            ->with(['subject', 'teacher']);
            
        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }
        
        // Apply date filter
        if ($this->dateRangeFilter !== 'all') {
            if ($this->dateRangeFilter === 'past') {
                $query->where('start_time', '<', Carbon::now());
            } elseif ($this->dateRangeFilter === 'upcoming') {
                $query->where('start_time', '>', Carbon::now());
            } elseif ($this->dateRangeFilter === 'this-week') {
                $query->whereBetween('start_time', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            } elseif ($this->dateRangeFilter === 'this-month') {
                $query->whereBetween('start_time', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
            }
        }
        
        // Apply search
        if ($this->searchQuery) {
            $searchQuery = '%' . $this->searchQuery . '%';
            $query->where(function($q) use ($searchQuery) {
                $q->whereHas('subject', function($subjectQuery) use ($searchQuery) {
                    $subjectQuery->where('name', 'LIKE', $searchQuery);
                })
                ->orWhereHas('teacher', function($teacherQuery) use ($searchQuery) {
                    $teacherQuery->where('name', 'LIKE', $searchQuery);
                });
            });
        }
        
        // Apply sorting
        if ($this->sortBy === 'date') {
            $query->orderBy('start_time', $this->sortDir);
        } elseif ($this->sortBy === 'created_at') {
            $query->orderBy('created_at', $this->sortDir);
        } elseif ($this->sortBy === 'status') {
            $query->orderBy('status', $this->sortDir);
        }
        
        // Get paginated results
        return $query->paginate(5);
    }

    public function getRequestStatsProperty()
    {
        // Get real session statistics
        $childrenIds = $this->children->pluck('id');
        
        return [
            'pending' => LearningSession::whereIn('children_id', $childrenIds)
                ->where('status', 'pending')
                ->count(),
                
            'approved' => LearningSession::whereIn('children_id', $childrenIds)
                ->where('status', 'approved')
                ->count(),
                
            'completed' => LearningSession::whereIn('children_id', $childrenIds)
                ->where('status', 'completed')
                ->count(),
                
            'cancelled' => LearningSession::whereIn('children_id', $childrenIds)
                ->where('status', 'cancelled')
                ->count(),
        ];
    }

    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    public function formatTime($time)
    {
        return Carbon::parse($time)->format('g:i A');
    }

    public function formatDateTime($dateTime)
    {
        return Carbon::parse($dateTime)->format('M d, Y g:i A');
    }

    protected function toast(
        string $type,
        string $title,
        $description = '',
        string $position = 'toast-bottom toast-end',
        string $icon = '',
        string $css = '',
        $timeout = 3000,
        $action = null
    ) {
        $actionJson = $action ? json_encode($action) : 'null';

        $this->js("
            Toaster.{$type}('{$title}', {
                description: '{$description}',
                position: '{$position}',
                icon: '{$icon}',
                css: '{$css}',
                timeout: {$timeout},
                action: {$actionJson}
            });
        ");
    }
};?>

<div>
    <div class="p-6">
        <div class="mx-auto max-w-7xl">
            <!-- Header Section -->
            <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
                <div>
                    <h1 class="text-3xl font-bold">Session Requests</h1>
                    <p class="mt-1 text-base-content/70">Request new learning sessions for your child</p>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 gap-4 mb-8 md:grid-cols-4">
                <div class="p-6 text-center shadow-lg rounded-xl bg-base-100">
                    <div class="flex flex-col items-center">
                        <div class="p-3 mb-2 rounded-full bg-primary/10">
                            <x-icon name="o-clock" class="w-6 h-6 text-primary" />
                        </div>
                        <div class="text-2xl font-bold">{{ $this->requestStats['pending'] }}</div>
                        <div class="text-sm">Pending Requests</div>
                    </div>
                </div>

                <div class="p-6 text-center shadow-lg rounded-xl bg-base-100">
                    <div class="flex flex-col items-center">
                        <div class="p-3 mb-2 rounded-full bg-info/10">
                            <x-icon name="o-check-circle" class="w-6 h-6 text-info" />
                        </div>
                        <div class="text-2xl font-bold">{{ $this->requestStats['approved'] }}</div>
                        <div class="text-sm">Approved</div>
                    </div>
                </div>

                <div class="p-6 text-center shadow-lg rounded-xl bg-base-100">
                    <div class="flex flex-col items-center">
                        <div class="p-3 mb-2 rounded-full bg-success/10">
                            <x-icon name="o-academic-cap" class="w-6 h-6 text-success" />
                        </div>
                        <div class="text-2xl font-bold">{{ $this->requestStats['completed'] }}</div>
                        <div class="text-sm">Completed</div>
                    </div>
                </div>

                <div class="p-6 text-center shadow-lg rounded-xl bg-base-100">
                    <div class="flex flex-col items-center">
                        <div class="p-3 mb-2 rounded-full bg-error/10">
                            <x-icon name="o-x-circle" class="w-6 h-6 text-error" />
                        </div>
                        <div class="text-2xl font-bold">{{ $this->requestStats['cancelled'] }}</div>
                        <div class="text-sm">Cancelled</div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-5">
                <!-- New Request Form (Left Side) -->
                <div class="lg:col-span-3">
                    <div class="mb-4 overflow-hidden shadow-lg rounded-xl bg-gradient-to-r from-primary to-primary-focus">
                        <div class="p-8 text-primary-content">
                            <h2 class="text-2xl font-bold">Request New Learning Session</h2>
                            <p class="mt-2 opacity-90">Fill out the form below to request a new session for your child.</p>

                            <div class="flex items-center gap-4 mt-4">
                                <button
                                    wire:click="toggleFormMode"
                                    class="text-primary-content bg-primary-content/20 hover:bg-primary-content/30 border-primary-content/10 btn btn-sm"
                                >
                                    <x-icon name="o-arrows-right-left" class="w-4 h-4 mr-2" />
                                    {{ $formMode === 'wizard' ? 'Switch to Compact Form' : 'Switch to Step-by-Step' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            @if($formMode === 'wizard')
                                <!-- Wizard Steps Progress -->
                                <div class="w-full mb-6 steps">
                                    <a class="step {{ $currentStep >= 1 ? 'step-primary' : '' }}">Child & Subject</a>
                                    <a class="step {{ $currentStep >= 2 ? 'step-primary' : '' }}">Schedule</a>
                                    <a class="step {{ $currentStep >= 3 ? 'step-primary' : '' }}">Review</a>
                                </div>
                            @endif

                            <form wire:submit.prevent="requestSession">
                                <!-- Step 1: Child and Subject Selection -->
                                <div class="{{ $formMode === 'wizard' && $currentStep !== 1 ? 'hidden' : '' }}">
                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <!-- Child Selection -->
                                        <div>
                                            <label class="text-sm font-medium">Select Child <span class="text-error">*</span></label>
                                            <select
                                                wire:model="selectedChild"
                                                class="w-full mt-1 select select-bordered"
                                                {{ $formMode === 'wizard' && $currentStep !== 1 ? 'disabled' : '' }}
                                            >
                                                <option value="">Select a child</option>
                                                @foreach($children as $child)
                                                    <option value="{{ $child->id }}">{{ $child->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('selectedChild') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>

                                        <!-- Subject Selection -->
                                        <div>
                                            <label class="text-sm font-medium">Select Subject <span class="text-error">*</span></label>
                                            <select
                                                wire:model="selectedSubject"
                                                class="w-full mt-1 select select-bordered"
                                                {{ $formMode === 'wizard' && $currentStep !== 1 ? 'disabled' : '' }}
                                            >
                                                <option value="">Select a subject</option>
                                                @foreach($filteredSubjects as $subject)
                                                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('selectedSubject') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>

                                        <!-- Session Type -->
                                        <div class="md:col-span-2">
                                            <label class="text-sm font-medium">Session Type <span class="text-error">*</span></label>
                                            <div class="grid grid-cols-1 gap-4 mt-2 md:grid-cols-3">
                                                <div class="border rounded-lg cursor-pointer border-base-300 {{ $sessionType === 'one-time' ? 'bg-primary/10 border-primary' : '' }}"
                                                    wire:click="$set('sessionType', 'one-time')"
                                                >
                                                    <div class="p-4 text-center">
                                                        <x-icon name="o-calendar-days" class="w-6 h-6 mx-auto {{ $sessionType === 'one-time' ? 'text-primary' : '' }}" />
                                                        <div class="mt-2 font-medium">One-time Session</div>
                                                        <div class="mt-1 text-xs">Single session booking</div>
                                                    </div>
                                                </div>
                                                <div class="border rounded-lg cursor-pointer border-base-300 {{ $sessionType === 'recurring' ? 'bg-primary/10 border-primary' : '' }}"
                                                    wire:click="$set('sessionType', 'recurring')"
                                                >
                                                    <div class="p-4 text-center">
                                                        <x-icon name="o-arrows-right-left" class="w-6 h-6 mx-auto {{ $sessionType === 'recurring' ? 'text-primary' : '' }}" />
                                                        <div class="mt-2 font-medium">Recurring Sessions</div>
                                                        <div class="mt-1 text-xs">Weekly or monthly sessions</div>
                                                    </div>
                                                </div>

                                                <div class="border rounded-lg cursor-pointer border-base-300 {{ $sessionType === 'package' ? 'bg-primary/10 border-primary' : '' }}"
                                                    wire:click="$set('sessionType', 'package')"
                                                >
                                                    <div class="p-4 text-center">
                                                        <x-icon name="o-gift" class="w-6 h-6 mx-auto {{ $sessionType === 'package' ? 'text-primary' : '' }}" />
                                                        <div class="mt-2 font-medium">Session Package</div>
                                                        <div class="mt-1 text-xs">Bundle of 10 sessions (15% off)</div>
                                                    </div>
                                                </div>
                                            </div>
                                            @error('sessionType') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    @if($formMode === 'wizard')
                                        <div class="flex justify-end mt-6">
                                            <button type="button" wire:click="nextStep" class="btn btn-primary">
                                                Next
                                                <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <!-- Step 2: Schedule and Location -->
                                <div class="{{ $formMode === 'wizard' && $currentStep !== 2 ? 'hidden' : '' }}">
                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                        <!-- Session Date -->
                                        <div>
                                            <label class="text-sm font-medium">Session Date <span class="text-error">*</span></label>
                                            <input
                                                type="date"
                                                wire:model="sessionDate"
                                                min="{{ $todayDate }}"
                                                class="w-full mt-1 input input-bordered"
                                                {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                            />
                                            @error('sessionDate') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>

                                        <!-- Session Time -->
                                        <div>
                                            <label class="text-sm font-medium">Session Time <span class="text-error">*</span></label>
                                            <div class="flex gap-2">
                                                <input
                                                    type="time"
                                                    wire:model="sessionTime"
                                                    class="w-full mt-1 input input-bordered"
                                                    {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                                />
                                                <button
                                                    type="button"
                                                    wire:click="openRecommendedSlotsModal"
                                                    class="mt-1 btn btn-outline"
                                                    {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                                >
                                                    <x-icon name="o-clock" class="w-4 h-4" />
                                                </button>
                                            </div>
                                            @error('sessionTime') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>

                                        <!-- Session Duration -->
                                        <div>
                                            <label class="text-sm font-medium">Session Duration <span class="text-error">*</span></label>
                                            <select
                                                wire:model="sessionDuration"
                                                class="w-full mt-1 select select-bordered"
                                                {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                            >
                                                <option value="30">30 minutes</option>
                                                <option value="60">1 hour</option>
                                                <option value="90">1.5 hours</option>
                                                <option value="120">2 hours</option>
                                                <option value="180">3 hours</option>
                                            </select>
                                            @error('sessionDuration') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>

                                        <!-- Session Location -->
                                        <div>
                                            <label class="text-sm font-medium">Session Location <span class="text-error">*</span></label>
                                            <select
                                                wire:model="sessionLocation"
                                                class="w-full mt-1 select select-bordered"
                                                {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                            >
                                                <option value="online">Online (Virtual)</option>
                                                <option value="home">Your Home</option>
                                                <option value="center">Learning Center</option>
                                                <option value="custom">Other Location</option>
                                            </select>
                                            @error('sessionLocation') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>

                                        <!-- Custom Location (conditional) -->
                                        @if($sessionLocation === 'custom')
                                        <div class="md:col-span-2">
                                            <label class="text-sm font-medium">Location Details <span class="text-error">*</span></label>
                                            <input
                                                type="text"
                                                wire:model="customLocation"
                                                class="w-full mt-1 input input-bordered"
                                                placeholder="Enter location details"
                                                {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                            />
                                            @error('customLocation') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>
                                        @endif

                                        <!-- Recurring Options (conditional) -->
                                        @if($sessionType === 'recurring')
                                        <div class="md:col-span-2">
                                            <div class="p-4 rounded-lg bg-base-200">
                                                <div class="font-medium">Recurring Options</div>

                                                <div class="mt-2">
                                                    <label class="text-sm font-medium">Repeat Sessions?</label>
                                                    <select
                                                        wire:model="repeatOption"
                                                        class="w-full mt-1 select select-bordered"
                                                        {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                                    >
                                                        <option value="no">No, just a single session</option>
                                                        <option value="yes">Yes, create recurring sessions</option>
                                                    </select>
                                                    @error('repeatOption') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                                </div>

                                                @if($repeatOption === 'yes')
                                                <div class="grid grid-cols-1 gap-4 mt-4 md:grid-cols-2">
                                                    <div>
                                                        <label class="text-sm font-medium">Repeat Frequency</label>
                                                        <select
                                                            wire:model="repeatFrequency"
                                                            class="w-full mt-1 select select-bordered"
                                                            {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                                        >
                                                            <option value="weekly">Weekly</option>
                                                            <option value="biweekly">Every 2 Weeks</option>
                                                            <option value="monthly">Monthly</option>
                                                        </select>
                                                        @error('repeatFrequency') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                                    </div>

                                                    <div>
                                                        <label class="text-sm font-medium">Repeat Until</label>
                                                        <input
                                                            type="date"
                                                            wire:model="repeatUntil"
                                                            min="{{ $sessionDate }}"
                                                            class="w-full mt-1 input input-bordered"
                                                            {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                                        />
                                                        @error('repeatUntil') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                                    </div>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                        @endif

                                        <!-- Additional Notes -->
                                        <div class="md:col-span-2">
                                            <label class="text-sm font-medium">Additional Notes</label>
                                            <textarea
                                                wire:model="sessionNotes"
                                                class="w-full mt-1 textarea textarea-bordered"
                                                rows="3"
                                                placeholder="Any specific requirements or information you'd like to share"
                                                {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                            ></textarea>
                                            @error('sessionNotes') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>

                                        <!-- Attachments -->
                                        <div class="md:col-span-2">
                                            <label class="text-sm font-medium">Attachments</label>
                                            <input
                                                type="file"
                                                wire:model="attachments"
                                                class="w-full mt-1 file-input file-input-bordered"
                                                multiple
                                                {{ $formMode === 'wizard' && $currentStep !== 2 ? 'disabled' : '' }}
                                            />
                                            <div class="mt-1 text-xs opacity-70">Optional: Attach any relevant documents or learning materials</div>
                                            @error('attachments.*') <div class="mt-1 text-sm text-error">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    @if($formMode === 'wizard')
                                        <div class="flex justify-between mt-6">
                                            <button type="button" wire:click="previousStep" class="btn btn-outline">
                                                <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                                Previous
                                            </button>
                                            <button type="button" wire:click="nextStep" class="btn btn-primary">
                                                Next
                                                <x-icon name="o-arrow-right" class="w-4 h-4 ml-2" />
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <!-- Step 3: Review and Submit -->
                                <div class="{{ $formMode === 'wizard' && $currentStep !== 3 ? 'hidden' : '' }}">
                                    <div class="p-4 rounded-lg bg-base-200">
                                        <h3 class="text-lg font-medium">Review Your Request</h3>

                                        <div class="grid grid-cols-1 gap-6 mt-4 md:grid-cols-2">
                                            <div>
                                                <div class="font-medium">Session Details</div>
                                                <div class="mt-2 space-y-2">
                                                    @if($selectedChild)
                                                        <div class="flex justify-between">
                                                            <span class="opacity-70">Child:</span>
                                                            <span class="font-medium">{{ $children->firstWhere('id', $selectedChild)?->name }}</span>
                                                        </div>
                                                    @endif

                                                    @if($selectedSubject)
                                                        <div class="flex justify-between">
                                                            <span class="opacity-70">Subject:</span>
                                                            <span class="font-medium">{{ $filteredSubjects->firstWhere('id', $selectedSubject)?->name }}</span>
                                                        </div>
                                                    @endif

                                                    <div class="flex justify-between">
                                                        <span class="opacity-70">Session Type:</span>
                                                        <span class="font-medium">{{ ucfirst($sessionType) }}</span>
                                                    </div>

                                                    <div class="flex justify-between">
                                                        <span class="opacity-70">Date:</span>
                                                        <span class="font-medium">{{ $sessionDate ? $this->formatDate($sessionDate) : '-' }}</span>
                                                    </div>

                                                    <div class="flex justify-between">
                                                        <span class="opacity-70">Time:</span>
                                                        <span class="font-medium">{{ $sessionTime ? $this->formatTime($sessionTime) : '-' }}</span>
                                                    </div>

                                                    <div class="flex justify-between">
                                                        <span class="opacity-70">Duration:</span>
                                                        <span class="font-medium">
                                                            {{ $sessionDuration === 60 ? '1 hour' : ($sessionDuration . ' minutes') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div>
                                                <div class="font-medium">Additional Information</div>
                                                <div class="mt-2 space-y-2">
                                                    <div class="flex justify-between">
                                                        <span class="opacity-70">Location:</span>
                                                        <span class="font-medium">
                                                            {{
                                                                $sessionLocation === 'online' ? 'Online (Virtual)' :
                                                                ($sessionLocation === 'home' ? 'Your Home' :
                                                                ($sessionLocation === 'center' ? 'Learning Center' : $customLocation))
                                                            }}
                                                        </span>
                                                    </div>

                                                    @if($sessionType === 'recurring' && $repeatOption === 'yes')
                                                        <div class="flex justify-between">
                                                            <span class="opacity-70">Recurring:</span>
                                                            <span class="font-medium">
                                                                {{
                                                                    $repeatFrequency === 'weekly' ? 'Weekly' :
                                                                    ($repeatFrequency === 'biweekly' ? 'Every 2 weeks' : 'Monthly')
                                                                }}
                                                            </span>
                                                        </div>

                                                        <div class="flex justify-between">
                                                            <span class="opacity-70">Until:</span>
                                                            <span class="font-medium">{{ $repeatUntil ? $this->formatDate($repeatUntil) : '-' }}</span>
                                                        </div>
                                                    @endif

                                                    <div class="flex justify-between">
                                                        <span class="opacity-70">Attachments:</span>
                                                        <span class="font-medium">{{ count($attachments) }} files</span>
                                                    </div>

                                                    <div class="flex justify-between">
                                                        <span class="opacity-70">Estimated Cost:</span>
                                                        <span class="font-medium">${{ number_format($costEstimate, 2) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        @if($sessionNotes)
                                            <div class="mt-4">
                                                <div class="font-medium">Notes:</div>
                                                <div class="p-2 mt-1 rounded-lg bg-base-100">
                                                    {{ $sessionNotes }}
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    @if($formMode === 'wizard')
                                        <div class="flex justify-between mt-6">
                                            <button type="button" wire:click="previousStep" class="btn btn-outline">
                                                <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                                                Previous
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                Submit Request
                                                <x-icon name="o-paper-airplane" class="w-4 h-4 ml-2" />
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                @if($formMode === 'compact')
                                    <div class="flex justify-end mt-6">
                                        <button type="submit" class="btn btn-primary">
                                            Submit Request
                                            <x-icon name="o-paper-airplane" class="w-4 h-4 ml-2" />
                                        </button>
                                    </div>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Request History (Right Side) -->
                <div class="lg:col-span-2">
                    <div class="shadow-xl card bg-base-100">
                        <div class="card-body">
                            <h2 class="card-title">Request History</h2>

                            <!-- Filter Controls -->
                            <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                                <!-- Status Filter -->
                                <div>
                                    <select wire:model.live="statusFilter" class="w-full select select-bordered select-sm">
                                        <option value="">All Statuses</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>

                                <!-- Date Range Filter -->
                                <div>
                                    <select wire:model.live="dateRangeFilter" class="w-full select select-bordered select-sm">
                                        <option value="all">All Dates</option>
                                        <option value="upcoming">Upcoming Sessions</option>
                                        <option value="past">Past Sessions</option>
                                        <option value="this-week">This Week</option>
                                        <option value="this-month">This Month</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Search -->
                            <div class="mt-2">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <x-icon name="o-magnifying-glass" class="w-4 h-4 text-base-content/50" />
                                    </div>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="searchQuery"
                                        placeholder="Search requests..."
                                        class="w-full pl-10 input input-bordered input-sm"
                                    >
                                </div>
                            </div>

                            <!-- Requests List -->
                            <div class="mt-4">
                                @if($this->requests->isEmpty())
                                    <div class="p-4 text-center bg-base-200 rounded-box">
                                        <p>No requests found matching your filters.</p>
                                    </div>
                                @else
                                    <div class="space-y-3">
                                        @foreach($this->requests as $request)
                                            <div class="p-3 transition-all border rounded-lg shadow-sm hover:shadow-md border-base-300">
                                                <div class="flex flex-col justify-between md:flex-row md:items-center">
                                                    <div>
                                                        <div class="font-medium">{{ $request['subject'] }}</div>
                                                        <div class="text-sm">{{ $request['child_name'] }}</div>
                                                        <div class="flex items-center gap-3 mt-1">
                                                            <div class="text-xs">
                                                                {{ $this->formatDate($request['date']) }} at {{ $this->formatTime($request['time']) }}
                                                            </div>
                                                            <div class="badge {{
                                                                $request['status'] === 'pending' ? 'badge-warning' :
                                                                ($request['status'] === 'approved' ? 'badge-info' :
                                                                ($request['status'] === 'completed' ? 'badge-success' : 'badge-error'))
                                                            }} badge-sm">
                                                                {{ ucfirst($request['status']) }}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="flex gap-2 mt-3 md:mt-0">
                                                        <button
                                                            wire:click="viewRequestDetail({{ $request['id'] }})"
                                                            class="btn btn-sm btn-outline"
                                                        >
                                                            View
                                                        </button>

                                                        @if($request['status'] === 'pending' || $request['status'] === 'approved')
                                                            <button
                                                                wire:click="confirmCancelRequest({{ $request['id'] }})"
                                                                class="btn btn-sm btn-error btn-outline"
                                                            >
                                                                <x-icon name="o-x-mark" class="w-4 h-4" />
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-4">
                                        {{ $this->requests->links() }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommended Slots Modal -->
    <div class="modal {{ $showRecommendedSlotsModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <button wire:click="closeRecommendedSlotsModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            <h3 class="text-lg font-bold">Recommended Time Slots</h3>
            <p class="py-4">Select from available time slots for {{ $filteredSubjects->firstWhere('id', $selectedSubject)?->name ?? 'this subject' }}.</p>

            @if(count($recommendedSlots) > 0)
                <div class="grid grid-cols-1 gap-3 mt-2 md:grid-cols-2">
                    @foreach($recommendedSlots as $index => $slot)
                        <div
                            wire:click="selectRecommendedSlot({{ $index }})"
                            class="p-3 transition-all border rounded-lg cursor-pointer border-base-300 hover:border-primary hover:bg-primary/5"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-3 rounded-full bg-base-200">
                                    <x-icon name="o-clock" class="w-5 h-5 text-primary" />
                                </div>
                                <div>
                                    <div class="font-medium">{{ $this->formatDate($slot['date']) }}</div>
                                    <div class="text-sm">{{ $this->formatTime($slot['time']) }}</div>
                                    <div class="mt-1 text-xs">with {{ $slot['teacher'] }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-4 text-center bg-base-200 rounded-box">
                    <p>No available slots found. Please try different criteria.</p>
                </div>
            @endif

            <div class="modal-action">
                <button wire:click="closeRecommendedSlotsModal" class="btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal {{ $showSuccessModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Request Submitted Successfully</h3>
            <div class="flex flex-col items-center my-6">
                <div class="p-3 rounded-full bg-success/20">
                    <x-icon name="o-check-circle" class="w-16 h-16 text-success" />
                </div>
                <p class="mt-4 text-center">Your session request has been submitted successfully. You will be notified once it's approved by the teacher.</p>
            </div>
            <div class="modal-action">
                <button wire:click="closeSuccessModal" class="btn btn-primary">OK</button>
            </div>
        </div>
    </div>

    <!-- Request Detail Modal -->
    <div class="modal {{ $showRequestDetailModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <button wire:click="closeRequestDetailModal" class="absolute btn btn-sm btn-circle right-2 top-2">✕</button>

            @if($selectedRequest)
                <h3 class="text-lg font-bold">Request Details</h3>

                <div class="py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xl font-medium">{{ $selectedRequest['subject'] }}</div>
                            <div class="mt-1">for {{ $selectedRequest['child_name'] }}</div>
                        </div>
                        <div class="badge {{
                            $selectedRequest['status'] === 'pending' ? 'badge-warning' :
                            ($selectedRequest['status'] === 'approved' ? 'badge-info' :
                            ($selectedRequest['status'] === 'completed' ? 'badge-success' : 'badge-error'))
                        }}">
                            {{ ucfirst($selectedRequest['status']) }}
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <div class="mb-2">
                                <div class="text-sm font-medium opacity-70">Session Date</div>
                                <div>{{ $this->formatDate($selectedRequest['date']) }}</div>
                            </div>

                            <div class="mb-2">
                                <div class="text-sm font-medium opacity-70">Session Time</div>
                                <div>{{ $this->formatTime($selectedRequest['time']) }}</div>
                            </div>

                            <div class="mb-2">
                                <div class="text-sm font-medium opacity-70">Duration</div>
                                <div>{{ $selectedRequest['duration'] }} minutes</div>
                            </div>

                            <div class="mb-2">
                                <div class="text-sm font-medium opacity-70">Location</div>
                                <div>{{ $selectedRequest['location'] }}</div>
                            </div>
                        </div>

                        <div>
                            <div class="mb-2">
                                <div class="text-sm font-medium opacity-70">Request Date</div>
                                <div>{{ $this->formatDate($selectedRequest['created_at']) }}</div>
                            </div>

                            <div class="mb-2">
                                <div class="text-sm font-medium opacity-70">Session Type</div>
                                <div>{{ ucfirst($selectedRequest['type']) }}</div>
                            </div>

                            <div class="mb-2">
                                <div class="text-sm font-medium opacity-70">Cost</div>
                                <div>${{ number_format($selectedRequest['cost'], 2) }}</div>
                            </div>

                            <div class="mb-2">
                                <div class="text-sm font-medium opacity-70">Teacher</div>
                                <div>{{ $selectedRequest['teacher'] ?? 'Not assigned yet' }}</div>
                            </div>
                        </div>
                    </div>

                    @if($selectedRequest['notes'])
                        <div class="mt-4">
                            <div class="text-sm font-medium opacity-70">Notes</div>
                            <div class="p-3 mt-1 bg-base-200 rounded-box">{{ $selectedRequest['notes'] }}</div>
                        </div>
                    @endif

                    <div class="mt-6">
                        <div class="text-sm font-medium opacity-70">Status Timeline</div>
                        <ul class="mt-2 timeline timeline-vertical">
                            <li>
                               <div class="timeline-start">{{ $this->formatDate($selectedRequest['created_at']) }}</div>
                                <div class="timeline-middle">
                                    <div class="p-1 rounded-full bg-primary">
                                        <div class="w-3 h-3"></div>
                                    </div>
                                </div>
                                <div class="timeline-end timeline-box">Request submitted</div>
                                <hr/>
                            </li>
                            @if($selectedRequest['status'] !== 'pending')
                                <li>
                                    <div class="timeline-start">{{ $this->formatDate($selectedRequest['created_at']->addDays(1)) }}</div>
                                    <div class="timeline-middle">
                                        <div class="p-1 rounded-full bg-info">
                                            <div class="w-3 h-3"></div>
                                        </div>
                                    </div>
                                    <div class="timeline-end timeline-box">Request approved</div>
                                    <hr/>
                                </li>
                            @endif
                            @if($selectedRequest['status'] === 'completed')
                                <li>
                                    <div class="timeline-start">{{ $this->formatDate($selectedRequest['date']) }}</div>
                                    <div class="timeline-middle">
                                        <div class="p-1 rounded-full bg-success">
                                            <div class="w-3 h-3"></div>
                                        </div>
                                    </div>
                                    <div class="timeline-end timeline-box">Session completed</div>
                                </li>
                            @elseif($selectedRequest['status'] === 'cancelled')
                                <li>
                                    <div class="timeline-start">{{ $this->formatDate($selectedRequest['created_at']->addDays(rand(1, 3))) }}</div>
                                    <div class="timeline-middle">
                                        <div class="p-1 rounded-full bg-error">
                                            <div class="w-3 h-3"></div>
                                        </div>
                                    </div>
                                    <div class="timeline-end timeline-box">Request cancelled</div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>

                <div class="modal-action">
                    @if($selectedRequest['status'] === 'pending' || $selectedRequest['status'] === 'approved')
                        <button
                            wire:click="confirmCancelRequest({{ $selectedRequest['id'] }})"
                            class="btn btn-error"
                        >
                            Cancel Request
                        </button>
                    @endif
                    <button wire:click="closeRequestDetailModal" class="btn">Close</button>
                </div>
            @endif
        </div>
    </div>
</div>
