<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $user;
    public $clientProfile;

    // Form state
    public $showRequestForm = false;
    public $courseId = '';
    public $teacherId = '';
    public $sessionTitle = '';
    public $sessionDate = '';
    public $sessionTime = '';
    public $sessionDuration = 1;
    public $sessionNotes = '';
    public $preferredContactMethod = 'email';

    // Filter and sort states
    public $statusFilter = '';
    public $searchQuery = '';
    public $sortBy = 'created_at';

    // Tab state
    public $activeTab = 'pending';

    protected $rules = [
        'courseId' => 'required',
        'teacherId' => 'required',
        'sessionTitle' => 'required|min:5|max:100',
        'sessionDate' => 'required|date|after_or_equal:today',
        'sessionTime' => 'required',
        'sessionDuration' => 'required|numeric|min:0.5|max:4',
        'sessionNotes' => 'nullable|max:500',
        'preferredContactMethod' => 'required|in:email,phone,any'
    ];

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'searchQuery' => ['except' => ''],
        'sortBy' => ['except' => 'created_at'],
        'activeTab' => ['except' => 'pending'],
    ];

    public function mount()
    {
        $this->user = Auth::user();
        $this->clientProfile = $this->user->clientProfile;

        // Set initial session date to tomorrow
        $this->sessionDate = Carbon::tomorrow()->format('Y-m-d');

        // Set initial session time to 10 AM
        $this->sessionTime = '10:00:00';
    }

    public function updatingSearchQuery()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function showForm()
    {
        $this->resetValidation();
        $this->resetForm();
        $this->showRequestForm = true;
    }

    public function hideForm()
    {
        $this->showRequestForm = false;
    }

    public function resetForm()
    {
        $this->courseId = '';
        $this->teacherId = '';
        $this->sessionTitle = '';
        $this->sessionDate = Carbon::tomorrow()->format('Y-m-d');
        $this->sessionTime = '10:00:00';
        $this->sessionDuration = 1;
        $this->sessionNotes = '';
        $this->preferredContactMethod = 'email';
    }

    public function submitRequest()
    {
        $this->validate();

        // In a real app, you would save the request to the database
        // Here, we'll just simulate a successful submission

        $this->toast(
            type: 'success',
            title: 'Session request submitted!',
            description: 'Your request has been sent successfully. We will contact you shortly to confirm.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->hideForm();
        $this->resetForm();
    }

    public function cancelRequest($requestId)
    {
        // In a real app, you would update the database
        $this->toast(
            type: 'warning',
            title: 'Request cancelled',
            description: 'Your session request has been cancelled.',
            position: 'toast-bottom toast-end',
            icon: 'o-x-circle',
            css: 'alert-warning',
            timeout: 3000
        );
    }

    public function editRequest($requestId)
    {
        // Find request in mock data
        $request = collect($this->getAllRequests())->firstWhere('id', $requestId);

        if ($request) {
            $this->courseId = $request['course_id'];
            $this->teacherId = $request['teacher_id'];
            $this->sessionTitle = $request['title'];
            $this->sessionDate = $request['date'];
            $this->sessionTime = $request['time'];
            $this->sessionDuration = $request['duration_hours'];
            $this->sessionNotes = $request['notes'];
            $this->preferredContactMethod = $request['preferred_contact'];

            $this->showRequestForm = true;
        }
    }

    // Get filtered session requests
    public function getRequestsProperty()
    {
        $requests = collect($this->getAllRequests());

        // Filter by tab
        if ($this->activeTab === 'pending') {
            $requests = $requests->filter(function($request) {
                return $request['status'] === 'pending' || $request['status'] === 'under_review';
            });
        } elseif ($this->activeTab === 'approved') {
            $requests = $requests->filter(function($request) {
                return $request['status'] === 'approved';
            });
        } elseif ($this->activeTab === 'rejected') {
            $requests = $requests->filter(function($request) {
                return $request['status'] === 'rejected';
            });
        } elseif ($this->activeTab === 'cancelled') {
            $requests = $requests->filter(function($request) {
                return $request['status'] === 'cancelled';
            });
        }

        // Apply additional filters
        if ($this->statusFilter) {
            $requests = $requests->filter(function($request) {
                return $request['status'] === $this->statusFilter;
            });
        }

        if ($this->searchQuery) {
            $query = strtolower($this->searchQuery);
            $requests = $requests->filter(function($request) use ($query) {
                return str_contains(strtolower($request['title']), $query) ||
                       str_contains(strtolower($request['teacher_name']), $query) ||
                       str_contains(strtolower($request['course_title']), $query);
            });
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'title':
                $requests = $requests->sortBy('title');
                break;
            case 'date':
                $requests = $requests->sortBy('date');
                break;
            case 'status':
                $requests = $requests->sortBy('status');
                break;
            default: // created_at
                $requests = $requests->sortByDesc('created_at');
                break;
        }

        return $requests->values()->all();
    }

    // Get stats for the dashboard
    public function getStatsProperty()
    {
        $allRequests = collect($this->getAllRequests());

        return [
            'total' => $allRequests->count(),
            'pending' => $allRequests->whereIn('status', ['pending', 'under_review'])->count(),
            'approved' => $allRequests->where('status', 'approved')->count(),
            'rejected' => $allRequests->where('status', 'rejected')->count(),
            'cancelled' => $allRequests->where('status', 'cancelled')->count(),
        ];
    }

    // Get courses for dropdown
    public function getCoursesProperty()
    {
        // In a real app, you would fetch courses from the database
        return [
            ['id' => 1, 'title' => 'Advanced Laravel Development'],
            ['id' => 2, 'title' => 'React and Redux Masterclass'],
            ['id' => 3, 'title' => 'UI/UX Design Fundamentals'],
            ['id' => 4, 'title' => 'Digital Marketing Strategy'],
            ['id' => 5, 'title' => 'Flutter App Development'],
            ['id' => 6, 'title' => 'Data Science with Python'],
            ['id' => 7, 'title' => 'Business Analytics Fundamentals'],
            ['id' => 8, 'title' => 'Graphic Design Masterclass'],
        ];
    }

    // Get teachers for dropdown
    public function getTeachersProperty()
    {
        // In a real app, you would fetch teachers from the database
        return [
            ['id' => 101, 'name' => 'Sarah Johnson', 'title' => 'Senior Laravel Developer'],
            ['id' => 102, 'name' => 'Michael Chen', 'title' => 'Frontend Developer & Consultant'],
            ['id' => 103, 'name' => 'Emily Rodriguez', 'title' => 'Senior UX Designer'],
            ['id' => 104, 'name' => 'David Wilson', 'title' => 'Digital Marketing Specialist'],
            ['id' => 105, 'name' => 'Alex Johnson', 'title' => 'Mobile Developer'],
            ['id' => 106, 'name' => 'Lisa Chen', 'title' => 'Data Scientist'],
            ['id' => 107, 'name' => 'Robert Taylor', 'title' => 'Business Analyst'],
            ['id' => 108, 'name' => 'Jessica Park', 'title' => 'Senior Graphic Designer'],
        ];
    }

    // Get available time slots
    public function getTimeSlotsProperty()
    {
        $slots = [];
        $start = Carbon::createFromFormat('H:i', '08:00');
        $end = Carbon::createFromFormat('H:i', '20:00');

        while ($start <= $end) {
            $slots[] = $start->format('H:i:s');
            $start->addMinutes(30);
        }

        return $slots;
    }

    // For formatting dates
    public function formatDate($date)
    {
        return Carbon::parse($date)->format('M d, Y');
    }

    // Method to get relative time
    public function getRelativeTime($date)
    {
        return Carbon::parse($date)->diffForHumans();
    }

    // Mock data for session requests (in a real app, you would fetch from database)
    private function getAllRequests()
    {
        return [
            [
                'id' => 1,
                'title' => 'Laravel Middleware Advanced Tutorial',
                'course_id' => 1,
                'course_title' => 'Advanced Laravel Development',
                'teacher_id' => 101,
                'teacher_name' => 'Sarah Johnson',
                'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'time' => '10:00:00',
                'duration_hours' => 2,
                'notes' => 'I would like to focus on custom middleware development and request lifecycle.',
                'preferred_contact' => 'email',
                'status' => 'approved',
                'created_at' => Carbon::now()->subDays(2)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDay()->toDateTimeString(),
                'admin_notes' => 'Approved, teacher confirmed availability.',
                'rejection_reason' => null
            ],
            [
                'id' => 2,
                'title' => 'React Hooks Deep Dive',
                'course_id' => 2,
                'course_title' => 'React and Redux Masterclass',
                'teacher_id' => 102,
                'teacher_name' => 'Michael Chen',
                'date' => Carbon::now()->addDays(7)->format('Y-m-d'),
                'time' => '14:00:00',
                'duration_hours' => 1.5,
                'notes' => 'I need help understanding useCallback, useMemo, and custom hooks.',
                'preferred_contact' => 'phone',
                'status' => 'pending',
                'created_at' => Carbon::now()->subDays(1)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDays(1)->toDateTimeString(),
                'admin_notes' => null,
                'rejection_reason' => null
            ],
            [
                'id' => 3,
                'title' => 'UI Design Portfolio Review',
                'course_id' => 3,
                'course_title' => 'UI/UX Design Fundamentals',
                'teacher_id' => 103,
                'teacher_name' => 'Emily Rodriguez',
                'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'time' => '11:00:00',
                'duration_hours' => 1,
                'notes' => 'I would like feedback on my portfolio before applying for jobs.',
                'preferred_contact' => 'email',
                'status' => 'under_review',
                'created_at' => Carbon::now()->subHours(12)->toDateTimeString(),
                'updated_at' => Carbon::now()->subHours(6)->toDateTimeString(),
                'admin_notes' => 'Checking teacher availability.',
                'rejection_reason' => null
            ],
            [
                'id' => 4,
                'title' => 'Social Media Marketing Plan Review',
                'course_id' => 4,
                'course_title' => 'Digital Marketing Strategy',
                'teacher_id' => 104,
                'teacher_name' => 'David Wilson',
                'date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'time' => '15:30:00',
                'duration_hours' => 1,
                'notes' => 'Need help optimizing my social media marketing plan for my small business.',
                'preferred_contact' => 'any',
                'status' => 'rejected',
                'created_at' => Carbon::now()->subDays(5)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDays(3)->toDateTimeString(),
                'admin_notes' => 'Teacher unavailable on requested date.',
                'rejection_reason' => 'The instructor is unavailable on the requested date. Please try selecting an alternative date or a different instructor.'
            ],
            [
                'id' => 5,
                'title' => 'Flutter State Management Help',
                'course_id' => 5,
                'course_title' => 'Flutter App Development',
                'teacher_id' => 105,
                'teacher_name' => 'Alex Johnson',
                'date' => Carbon::now()->addDays(8)->format('Y-m-d'),
                'time' => '13:00:00',
                'duration_hours' => 2,
                'notes' => 'Need guidance on implementing Provider pattern in my app.',
                'preferred_contact' => 'email',
                'status' => 'cancelled',
                'created_at' => Carbon::now()->subDays(6)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDays(4)->toDateTimeString(),
                'admin_notes' => 'Cancelled by client.',
                'rejection_reason' => null
            ],
            [
                'id' => 6,
                'title' => 'Data Visualization with Python',
                'course_id' => 6,
                'course_title' => 'Data Science with Python',
                'teacher_id' => 106,
                'teacher_name' => 'Lisa Chen',
                'date' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'time' => '10:00:00',
                'duration_hours' => 2,
                'notes' => 'Need help creating interactive visualizations with Plotly and Dash.',
                'preferred_contact' => 'phone',
                'status' => 'approved',
                'created_at' => Carbon::now()->subDays(10)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDays(8)->toDateTimeString(),
                'admin_notes' => 'Approved, scheduled in calendar.',
                'rejection_reason' => null
            ],
            [
                'id' => 7,
                'title' => 'Business Analytics Project Review',
                'course_id' => 7,
                'course_title' => 'Business Analytics Fundamentals',
                'teacher_id' => 107,
                'teacher_name' => 'Robert Taylor',
                'date' => Carbon::now()->addDays(12)->format('Y-m-d'),
                'time' => '09:00:00',
                'duration_hours' => 1.5,
                'notes' => 'Would like feedback on my final project analyzing market trends.',
                'preferred_contact' => 'email',
                'status' => 'pending',
                'created_at' => Carbon::now()->subDays(3)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDays(3)->toDateTimeString(),
                'admin_notes' => null,
                'rejection_reason' => null
            ],
            [
                'id' => 8,
                'title' => 'Logo Design Critique',
                'course_id' => 8,
                'course_title' => 'Graphic Design Masterclass',
                'teacher_id' => 108,
                'teacher_name' => 'Jessica Park',
                'date' => Carbon::now()->addDays(6)->format('Y-m-d'),
                'time' => '14:00:00',
                'duration_hours' => 1,
                'notes' => 'Need feedback on my logo designs for a client project.',
                'preferred_contact' => 'email',
                'status' => 'under_review',
                'created_at' => Carbon::now()->subDays(1)->toDateTimeString(),
                'updated_at' => Carbon::now()->subHours(12)->toDateTimeString(),
                'admin_notes' => 'Checking teacher schedule.',
                'rejection_reason' => null
            ]
        ];
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
    public function formatTime($time)
{
    return Carbon::parse($time)->format('h:i A');
}

public function getTodayFormatted()
{
    return Carbon::now()->format('Y-m-d');
}
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Session Requests</h1>
                <p class="mt-1 text-base-content/70">Request one-on-one sessions with our instructors</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('clients.sessions') }}" class="btn btn-outline">
                    <x-icon name="o-video-camera" class="w-4 h-4 mr-2" />
                    My Sessions
                </a>
                <button
                    wire:click="showForm"
                    class="btn btn-primary"
                >
                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                    New Request
                </button>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="p-4 mb-8 shadow-lg rounded-xl bg-base-100 sm:p-6">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['total'] }}</div>
                    <div class="text-xs opacity-70">Total Requests</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['pending'] }}</div>
                    <div class="text-xs opacity-70">Pending</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['approved'] }}</div>
                    <div class="text-xs opacity-70">Approved</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['rejected'] }}</div>
                    <div class="text-xs opacity-70">Rejected</div>
                </div>

                <div class="p-3 text-center rounded-lg bg-base-200 md:col-span-1">
                    <div class="text-2xl font-bold">{{ $this->stats['cancelled'] }}</div>
                    <div class="text-xs opacity-70">Cancelled</div>
                </div>
            </div>
        </div>

        <!-- Session Request Form Modal -->
        <div class="modal {{ $showRequestForm ? 'modal-open' : '' }}">
            <div class="max-w-3xl modal-box">
                <h3 class="text-2xl font-bold">Request a Session</h3>
                <p class="mb-6 text-base-content/70">Fill out the form below to request a one-on-one session with one of our instructors.</p>

                <form wire:submit.prevent="submitRequest">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Course Selection -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Course</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <select
                                wire:model="courseId"
                                class="select select-bordered @error('courseId') select-error @enderror"
                            >
                                <option value="">Select a course</option>
                                @foreach($this->courses as $course)
                                    <option value="{{ $course['id'] }}">{{ $course['title'] }}</option>
                                @endforeach
                            </select>
                            @error('courseId')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <!-- Teacher Selection -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Instructor</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <select
                                wire:model="teacherId"
                                class="select select-bordered @error('teacherId') select-error @enderror"
                            >
                                <option value="">Select an instructor</option>
                                @foreach($this->teachers as $teacher)
                                    <option value="{{ $teacher['id'] }}">{{ $teacher['name'] }} - {{ $teacher['title'] }}</option>
                                @endforeach
                            </select>
                            @error('teacherId')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <!-- Session Title -->
                        <div class="form-control md:col-span-2">
                            <label class="label">
                                <span class="label-text">Session Title</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="text"
                                wire:model="sessionTitle"
                                placeholder="e.g., React Hooks Deep Dive"
                                class="input input-bordered @error('sessionTitle') input-error @enderror"
                            />
                            @error('sessionTitle')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <!-- Session Date -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Preferred Date</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <input
                                type="date"
                                wire:model="sessionDate"
                                min="{{ $this->getTodayFormatted() }}"
                                class="input input-bordered @error('sessionDate') input-error @enderror"
                            />
                            @error('sessionDate')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <!-- Session Time -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Preferred Time</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <select
                                wire:model="sessionTime"
                                class="select select-bordered @error('sessionTime') select-error @enderror"
                            >
                                <option value="">Select a time</option>
                                @foreach($this->timeSlots as $timeSlot)
                                    <option value="{{ $timeSlot }}">{{ \Carbon\Carbon::parse($timeSlot)->format('h:i A') }}</option>
                                @endforeach
                            </select>
                            @error('sessionTime')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <!-- Session Duration -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Duration (hours)</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <select
                                wire:model="sessionDuration"
                                class="select select-bordered @error('sessionDuration') select-error @enderror"
                            >
                                <option value="0.5">0.5 hour</option>
                                <option value="1">1 hour</option>
                                <option value="1.5">1.5 hours</option>
                                <option value="2">2 hours</option>
                                <option value="2.5">2.5 hours</option>
                                <option value="3">3 hours</option>
                                <option value="4">4 hours</option>
                            </select>
                            @error('sessionDuration')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <!-- Preferred Contact Method -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Preferred Contact Method</span>
                                <span class="label-text-alt text-error">Required</span>
                            </label>
                            <select
                                wire:model="preferredContactMethod"
                                class="select select-bordered @error('preferredContactMethod') select-error @enderror"
                            >
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                                <option value="any">Either Email or Phone</option>
                            </select>
                            @error('preferredContactMethod')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <!-- Session Notes -->
                        <div class="form-control md:col-span-2">
                            <label class="label">
                                <span class="label-text">Session Notes (optional)</span>
                                <span class="label-text-alt">{{ strlen($sessionNotes) }}/500</span>
                            </label>
                            <textarea
                                wire:model="sessionNotes"
                                placeholder="Describe what you would like to cover in this session..."
                                class="h-24 resize-none textarea textarea-bordered @error('sessionNotes') textarea-error @enderror"
                            ></textarea>
                            @error('sessionNotes')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>
                    </div>

                    <div class="p-4 mt-6 text-sm rounded-lg bg-info/10 text-info">
                        <div class="flex items-start gap-2">
                            <x-icon name="o-information-circle" class="w-5 h-5 mt-0.5" />
                            <div>
                                <p>Your request will be reviewed and confirmed based on instructor availability.</p>
                                <p class="mt-1">Once approved, you'll receive a confirmation email with session details.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-6 modal-action">
                        <button type="button" wire:click="hideForm" class="btn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabs & Search/Filter Section -->
        <div class="mb-4">
            <div class="tabs tabs-boxed">
                <a
                    wire:click="setActiveTab('pending')"
                    class="tab {{ $activeTab === 'pending' ? 'tab-active' : '' }}"
                >
                    Pending
                </a>
                <a
                    wire:click="setActiveTab('approved')"
                    class="tab {{ $activeTab === 'approved' ? 'tab-active' : '' }}"
                >
                    Approved
                </a>
                <a
                    wire:click="setActiveTab('rejected')"
                    class="tab {{ $activeTab === 'rejected' ? 'tab-active' : '' }}"
                >
                    Rejected
                </a>
                <a
                    wire:click="setActiveTab('cancelled')"
                    class="tab {{ $activeTab === 'cancelled' ? 'tab-active' : '' }}"
                >
                    Cancelled
                </a>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="p-4 mb-6 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Search -->
                <div class="lg:col-span-1">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="searchQuery"
                            placeholder="Search requests..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <select wire:model.live="statusFilter" class="w-full select select-bordered">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <select wire:model.live="sortBy" class="w-full select select-bordered">
                        <option value="created_at">Date Created (Newest First)</option>
                        <option value="title">Title (A-Z)</option>
                        <option value="date">Session Date</option>
                        <option value="status">Status</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Request List -->
        <div class="shadow-xl rounded-xl bg-base-100">
            @if(count($this->requests) > 0)
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 gap-6">
                        @foreach($this->requests as $request)
                            <div class="overflow-hidden transition-all border rounded-lg shadow-sm hover:shadow-md border-base-200">
                                <div class="grid grid-cols-1 lg:grid-cols-4">
                                    <!-- Left Section with Basic Info -->
                                    <div class="p-5 lg:col-span-3">
                                        <div class="flex flex-col h-full">
                                            <div class="flex items-start justify-between mb-3">
                                                <div>
                                                    <h3 class="text-lg font-bold">{{ $request['title'] }}</h3>
                                                    <p class="text-sm text-base-content/70">{{ $request['course_title'] }}</p>
                                                </div>
                                                <div class="badge {{
                                                    $request['status'] === 'approved' ? 'badge-success' :
                                                    ($request['status'] === 'pending' ? 'badge-info' :
                                                    ($request['status'] === 'under_review' ? 'badge-warning' :
                                                    ($request['status'] === 'rejected' ? 'badge-error' : 'badge-neutral')))
                                                }}">
                                                    {{ ucfirst(str_replace('_', ' ', $request['status'])) }}
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div class="flex-1 space-y-3">
                                                    <!-- Teacher Info -->
                                                    <div class="flex items-center gap-2">
                                                        <div class="avatar placeholder">
                                                            <div class="w-8 h-8 rounded-full bg-neutral-focus text-neutral-content">
                                                                <span>{{ substr($request['teacher_name'], 0, 1) }}</span>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-medium">{{ $request['teacher_name'] }}</div>
                                                            <div class="text-xs text-base-content/70">Instructor</div>
                                                        </div>
                                                    </div>

                                                    <!-- Date & Time -->
                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="o-calendar" class="w-5 h-5 text-base-content/70" />
                                                        <div class="text-sm">{{ $this->formatDate($request['date']) }}</div>
                                                    </div>

                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="o-clock" class="w-5 h-5 text-base-content/70" />
                                                        <div class="text-sm">
                                                           {{ \Carbon\Carbon::parse($request['time'])->format('h:i A') }}
                                                            <span class="ml-1 text-xs text-base-content/70">({{ $request['duration_hours'] }} hours)</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex-1 space-y-3">
                                                    <!-- Request Details -->
                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="o-document-text" class="w-5 h-5 text-base-content/70" />
                                                        <div class="text-sm">
                                                            <span class="font-medium">Notes:</span>
                                                            <span class="line-clamp-1">{{ $request['notes'] ?: 'No notes provided' }}</span>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="o-envelope" class="w-5 h-5 text-base-content/70" />
                                                        <div class="text-sm">
                                                            <span class="font-medium">Preferred contact:</span>
                                                            <span>{{ ucfirst($request['preferred_contact']) }}</span>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-2">
                                                        <x-icon name="o-eye" class="w-5 h-5 text-base-content/70" />
                                                        <div class="text-sm">
                                                            <span class="font-medium">Requested:</span>
                                                            <span>{{ $this->getRelativeTime($request['created_at']) }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Request Actions -->
                                            <div class="flex flex-wrap gap-2 mt-4 md:justify-end">
                                                @if($request['status'] === 'pending' || $request['status'] === 'under_review')
                                                    <button
                                                        wire:click="editRequest({{ $request['id'] }})"
                                                        class="btn btn-outline btn-sm"
                                                    >
                                                        <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                                                        Edit
                                                    </button>

                                                    <button
                                                        wire:click="cancelRequest({{ $request['id'] }})"
                                                        class="btn btn-outline btn-error btn-sm"
                                                    >
                                                        <x-icon name="o-x-mark" class="w-4 h-4 mr-1" />
                                                        Cancel
                                                    </button>
                                                @elseif($request['status'] === 'approved')
                                                    <a href="{{ route('clients.sessions') }}" class="btn btn-primary btn-sm">
                                                        <x-icon name="o-video-camera" class="w-4 h-4 mr-1" />
                                                        View Session
                                                    </a>
                                                @elseif($request['status'] === 'rejected')
                                                    <button
                                                        wire:click="editRequest({{ $request['id'] }})"
                                                        class="btn btn-outline btn-sm"
                                                    >
                                                        <x-icon name="o-arrow-path" class="w-4 h-4 mr-1" />
                                                        Try Again
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Section with Status -->
                                    <div class="p-5 border-t lg:border-t-0 lg:border-l border-base-200">
                                        <div class="flex flex-col justify-between h-full">
                                            <div>
                                                @if($request['status'] === 'approved')
                                                    <div class="text-center">
                                                        <div class="inline-flex items-center justify-center w-16 h-16 mb-3 rounded-full bg-success bg-opacity-20">
                                                            <x-icon name="o-check-circle" class="w-8 h-8 text-success" />
                                                        </div>
                                                        <h4 class="text-lg font-medium">Approved</h4>
                                                        <p class="mt-1 text-sm text-base-content/70">Session scheduled</p>
                                                    </div>
                                                @elseif($request['status'] === 'pending')
                                                    <div class="text-center">
                                                        <div class="inline-flex items-center justify-center w-16 h-16 mb-3 rounded-full bg-info bg-opacity-20">
                                                            <x-icon name="o-clock" class="w-8 h-8 text-info" />
                                                        </div>
                                                        <h4 class="text-lg font-medium">Pending</h4>
                                                        <p class="mt-1 text-sm text-base-content/70">Awaiting review</p>
                                                    </div>
                                                @elseif($request['status'] === 'under_review')
                                                    <div class="text-center">
                                                        <div class="inline-flex items-center justify-center w-16 h-16 mb-3 rounded-full bg-warning bg-opacity-20">
                                                            <x-icon name="o-clock" class="w-8 h-8 text-warning" />
                                                        </div>
                                                        <h4 class="text-lg font-medium">Under Review</h4>
                                                        <p class="mt-1 text-sm text-base-content/70">Being processed</p>
                                                    </div>
                                                @elseif($request['status'] === 'rejected')
                                                    <div class="text-center">
                                                        <div class="inline-flex items-center justify-center w-16 h-16 mb-3 rounded-full bg-error bg-opacity-20">
                                                            <x-icon name="o-x-circle" class="w-8 h-8 text-error" />
                                                        </div>
                                                        <h4 class="text-lg font-medium">Rejected</h4>
                                                        <p class="mt-1 text-sm text-base-content/70">Not approved</p>
                                                    </div>
                                                @elseif($request['status'] === 'cancelled')
                                                    <div class="text-center">
                                                        <div class="inline-flex items-center justify-center w-16 h-16 mb-3 rounded-full bg-neutral bg-opacity-20">
                                                            <x-icon name="o-x-circle" class="w-8 h-8 text-neutral" />
                                                        </div>
                                                        <h4 class="text-lg font-medium">Cancelled</h4>
                                                        <p class="mt-1 text-sm text-base-content/70">Request cancelled</p>
                                                    </div>
                                                @endif
                                            </div>

                                            @if($request['status'] === 'rejected' && $request['rejection_reason'])
                                                <div class="p-3 mt-4 text-sm border rounded-lg border-error text-error bg-error/5">
                                                    <div class="font-medium">Reason for rejection:</div>
                                                    <p class="mt-1">{{ $request['rejection_reason'] }}</p>
                                                </div>
                                            @endif

                                            @if($request['status'] === 'approved' && $request['admin_notes'])
                                                <div class="p-3 mt-4 text-sm border rounded-lg border-success text-success bg-success/5">
                                                    <div class="font-medium">Additional information:</div>
                                                    <p class="mt-1">{{ $request['admin_notes'] }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                        <x-icon name="o-document-text" class="w-16 h-16 mb-4 text-base-content/30" />
                        <h3 class="text-xl font-bold">No requests found</h3>
                        <p class="mt-2 text-base-content/70">
                            @if($searchQuery || $statusFilter)
                                Try adjusting your search or filters
                            @else
                                You don't have any {{ $activeTab }} session requests yet
                            @endif
                        </p>

                        @if($searchQuery || $statusFilter)
                            <button
                                wire:click="$set('searchQuery', ''); $set('statusFilter', '');"
                                class="mt-4 btn btn-outline"
                            >
                                Clear Filters
                            </button>
                        @else
                            <button
                                wire:click="showForm"
                                class="mt-4 btn btn-primary"
                            >
                                Request a Session
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- How it Works Section -->
        <div class="p-8 mt-12 shadow-xl rounded-xl bg-base-200">
            <h2 class="mb-6 text-2xl font-bold text-center">How Session Requests Work</h2>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-4">
                <div class="flex flex-col items-center p-4 text-center">
                    <div class="p-3 mb-4 rounded-full bg-primary bg-opacity-20">
                        <x-icon name="o-pencil-square" class="w-8 h-8 text-primary" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">1. Submit Request</h3>
                    <p class="text-sm text-base-content/70">Fill out the session request form with your preferred course, instructor, date, and time.</p>
                </div>

                <div class="flex flex-col items-center p-4 text-center">
                    <div class="p-3 mb-4 rounded-full bg-secondary bg-opacity-20">
                        <x-icon name="o-clock" class="w-8 h-8 text-secondary" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">2. Review Process</h3>
                    <p class="text-sm text-base-content/70">Our team reviews your request and checks instructor availability.</p>
                </div>

                <div class="flex flex-col items-center p-4 text-center">
                    <div class="p-3 mb-4 rounded-full bg-accent bg-opacity-20">
                        <x-icon name="o-check-circle" class="w-8 h-8 text-accent" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">3. Confirmation</h3>
                    <p class="text-sm text-base-content/70">Once approved, you ll receive a confirmation with the session details.</p>
                </div>

                <div class="flex flex-col items-center p-4 text-center">
                    <div class="p-3 mb-4 rounded-full bg-success bg-opacity-20">
                        <x-icon name="o-video-camera" class="w-8 h-8 text-success" />
                    </div>
                    <h3 class="mb-2 text-lg font-semibold">4. Join Session</h3>
                    <p class="text-sm text-base-content/70">At the scheduled time, join your session through the My Sessions page.</p>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="p-6 mt-12 mb-6 shadow-xl rounded-xl bg-base-100">
            <h2 class="mb-6 text-2xl font-bold text-center">Frequently Asked Questions</h2>

            <div class="w-full join join-vertical">
                <div class="border collapse collapse-arrow join-item border-base-300">
                    <input type="radio" name="faq-accordion" checked />
                    <div class="text-lg font-medium collapse-title">
                        How far in advance should I request a session?
                    </div>
                    <div class="collapse-content">
                        <p>We recommend requesting sessions at least 48 hours in advance to ensure instructor availability. For urgent requests, please contact our support team directly.</p>
                    </div>
                </div>

                <div class="border collapse collapse-arrow join-item border-base-300">
                    <input type="radio" name="faq-accordion" />
                    <div class="text-lg font-medium collapse-title">
                        What happens if my request is rejected?
                    </div>
                    <div class="collapse-content">
                        <p>If your request is rejected, we ll provide a reason. You can submit a new request with different parameters like date, time, or instructor that might better accommodate your needs.</p>
                    </div>
                </div>

                <div class="border collapse collapse-arrow join-item border-base-300">
                    <input type="radio" name="faq-accordion" />
                    <div class="text-lg font-medium collapse-title">
                        Can I reschedule an approved session?
                    </div>
                    <div class="collapse-content">
                        <p>Yes, you can reschedule an approved session up to 24 hours before the scheduled time. Simply visit the My Sessions page and usethe reschedule option for that session.</p>
                    </div>
                </div>

                <div class="border collapse collapse-arrow join-item border-base-300">
                    <input type="radio" name="faq-accordion" />
                    <div class="text-lg font-medium collapse-title">
                        How long does it take to get my request approved?
                    </div>
                    <div class="collapse-content">
                        <p>Most requests are processed within 24 hours. You ll receive a notification as soon as your request has been reviewed.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
