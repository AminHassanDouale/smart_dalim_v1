<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\LearningSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $teacher;
    public $filter = 'pending'; // default filter: pending, approved, rejected
    public $search = '';
    public $dateRange = '';
    public $perPage = 10;

    // Modals
    public $showViewModal = false;
    public $showApproveModal = false;
    public $showRejectModal = false;
    public $currentRequest = null;
    public $rejectionReason = '';

    public function mount()
    {
        $this->teacher = Auth::user();
    }

    public function getSessionRequestsProperty()
    {
        $query = LearningSession::query()
            ->where('teacher_id', $this->teacher->id);

        // Apply filters
        if ($this->filter === 'pending') {
            $query->where('status', 'requested');
        } elseif ($this->filter === 'approved') {
            $query->where('status', 'confirmed');
        } elseif ($this->filter === 'rejected') {
            $query->where('status', 'rejected');
        }

        // Apply search
        if (!empty($this->search)) {
            $query->whereHas('student', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            })->orWhere('title', 'like', '%' . $this->search . '%');
        }

        // Apply date range
        if (!empty($this->dateRange)) {
            $dates = explode(' to ', $this->dateRange);
            if (count($dates) === 2) {
                $query->whereBetween('date', [$dates[0], $dates[1]]);
            }
        }

        return $query->with(['student', 'course'])
            ->latest()
            ->paginate($this->perPage);
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDateRange()
    {
        $this->resetPage();
    }

    // View request details
    public function viewRequest($id)
    {
        $this->currentRequest = LearningSession::with(['student', 'course'])->findOrFail($id);
        $this->showViewModal = true;
    }

    // Approve request
    public function openApproveModal($id)
    {
        $this->currentRequest = LearningSession::with(['student', 'course'])->findOrFail($id);
        $this->showApproveModal = true;
    }

    public function approveRequest()
    {
        $this->currentRequest->status = 'confirmed';
        $this->currentRequest->save();

        // Send notification to student (you would implement this)
        // Notification::send($this->currentRequest->student, new SessionRequestApproved($this->currentRequest));

        $this->showApproveModal = false;
        $this->currentRequest = null;

        session()->flash('message', 'Session request approved successfully.');
        session()->flash('alert-type', 'success');
    }

    // Reject request
    public function openRejectModal($id)
    {
        $this->currentRequest = LearningSession::with(['student', 'course'])->findOrFail($id);
        $this->showRejectModal = true;
        $this->rejectionReason = '';
    }

    public function rejectRequest()
    {
        $this->validate([
            'rejectionReason' => 'required|min:10',
        ]);

        $this->currentRequest->status = 'rejected';
        $this->currentRequest->rejection_reason = $this->rejectionReason;
        $this->currentRequest->save();

        // Send notification to student (you would implement this)
        // Notification::send($this->currentRequest->student, new SessionRequestRejected($this->currentRequest));

        $this->showRejectModal = false;
        $this->currentRequest = null;
        $this->rejectionReason = '';

        session()->flash('message', 'Session request rejected.');
        session()->flash('alert-type', 'warning');
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->currentRequest = null;
    }

    public function closeApproveModal()
    {
        $this->showApproveModal = false;
        $this->currentRequest = null;
    }

    public function closeRejectModal()
    {
        $this->showRejectModal = false;
        $this->currentRequest = null;
        $this->rejectionReason = '';
    }
};
?>

<div>
    <div class="container px-4 py-8 mx-auto">
        <h1 class="mb-6 text-2xl font-bold">Session Requests</h1>

        <!-- Filters and Search -->
        <div class="flex flex-col gap-4 mb-6 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-wrap gap-2">
                <button
                    wire:click="setFilter('pending')"
                    class="btn {{ $filter === 'pending' ? 'btn-primary' : 'btn-outline' }}"
                >
                    Pending
                </button>
                <button
                    wire:click="setFilter('approved')"
                    class="btn {{ $filter === 'approved' ? 'btn-primary' : 'btn-outline' }}"
                >
                    Approved
                </button>
                <button
                    wire:click="setFilter('rejected')"
                    class="btn {{ $filter === 'rejected' ? 'btn-primary' : 'btn-outline' }}"
                >
                    Rejected
                </button>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <div class="form-control">
                    <input
                        type="text"
                        wire:model.debounce.300ms="search"
                        placeholder="Search..."
                        class="input input-bordered"
                    />
                </div>

                <div class="form-control">
                    <input
                        type="text"
                        wire:model.debounce.300ms="dateRange"
                        placeholder="Date range"
                        class="input input-bordered"
                    />
                </div>

                <div class="form-control">
                    <select wire:model="perPage" class="select select-bordered">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Session Requests Table -->
        <div class="overflow-x-auto bg-white rounded-lg shadow-md">
            <table class="table w-full table-zebra">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Requested On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->sessionRequests as $request)
                        <tr>
                            <td>
                                <div class="flex items-center space-x-3">
                                    <div class="avatar placeholder">
                                        <div class="w-10 rounded-full bg-neutral text-neutral-content">
                                            <span>{{ substr($request->student->name, 0, 1) }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $request->student->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $request->student->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $request->course->name }}</td>
                            <td>
                                {{ \Carbon\Carbon::parse($request->date)->format('M d, Y') }}<br>
                                <span class="text-sm text-gray-500">
                                    {{ \Carbon\Carbon::parse($request->start_time)->format('h:i A') }} -
                                    {{ \Carbon\Carbon::parse($request->end_time)->format('h:i A') }}
                                </span>
                            </td>
                            <td>
                                @if($request->status === 'requested')
                                    <span class="badge badge-info">Pending</span>
                                @elseif($request->status === 'confirmed')
                                    <span class="badge badge-success">Approved</span>
                                @elseif($request->status === 'rejected')
                                    <span class="badge badge-error">Rejected</span>
                                @endif
                            </td>
                            <td>{{ $request->created_at->diffForHumans() }}</td>
                            <td>
                                <div class="flex gap-2">
                                    <button
                                        wire:click="viewRequest({{ $request->id }})"
                                        class="btn btn-sm btn-outline btn-circle"
                                    >
                                        <x-icon name="o-eye" class="w-4 h-4" />
                                    </button>

                                    @if($request->status === 'requested')
                                        <button
                                            wire:click="openApproveModal({{ $request->id }})"
                                            class="btn btn-sm btn-success btn-circle"
                                        >
                                            <x-icon name="o-check" class="w-4 h-4" />
                                        </button>

                                        <button
                                            wire:click="openRejectModal({{ $request->id }})"
                                            class="btn btn-sm btn-error btn-circle"
                                        >
                                            <x-icon name="o-x-mark" class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center">
                                No session requests found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $this->sessionRequests->links() }}
        </div>

        <!-- View Request Modal -->
        <x-modal wire:model="showViewModal">
            @if($currentRequest)
                <div class="p-6">
                    <h3 class="mb-4 text-xl font-semibold">Session Request Details</h3>

                    <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
                        <div>
                            <h4 class="font-medium text-gray-700">Student</h4>
                            <div class="flex items-center mt-2">
                                <div class="mr-3 avatar placeholder">
                                    <div class="w-10 rounded-full bg-neutral text-neutral-content">
                                        <span>{{ substr($currentRequest->student->name, 0, 1) }}</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium">{{ $currentRequest->student->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $currentRequest->student->email }}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-700">Course</h4>
                            <p class="mt-2">{{ $currentRequest->course->name }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
                        <div>
                            <h4 class="font-medium text-gray-700">Date</h4>
                            <p class="mt-2">{{ \Carbon\Carbon::parse($currentRequest->date)->format('M d, Y') }}</p>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-700">Time</h4>
                            <p class="mt-2">
                                {{ \Carbon\Carbon::parse($currentRequest->start_time)->format('h:i A') }} -
                                {{ \Carbon\Carbon::parse($currentRequest->end_time)->format('h:i A') }}
                            </p>
                        </div>
                    </div>

                    @if($currentRequest->message)
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-700">Student's Message</h4>
                            <div class="p-3 mt-2 rounded-lg bg-gray-50">
                                {{ $currentRequest->message }}
                            </div>
                        </div>
                    @endif

                    @if($currentRequest->status === 'rejected' && $currentRequest->rejection_reason)
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-700">Rejection Reason</h4>
                            <div class="p-3 mt-2 text-red-800 rounded-lg bg-red-50">
                                {{ $currentRequest->rejection_reason }}
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end mt-6">
                        <button wire:click="closeViewModal" class="btn">Close</button>

                        @if($currentRequest->status === 'requested')
                            <button
                                wire:click="openApproveModal({{ $currentRequest->id }})"
                                class="ml-2 btn btn-success"
                            >
                                Approve
                            </button>

                            <button
                                wire:click="openRejectModal({{ $currentRequest->id }})"
                                class="ml-2 btn btn-error"
                            >
                                Reject
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </x-modal>

        <!-- Approve Modal -->
        <x-modal wire:model="showApproveModal">
            @if($currentRequest)
                <div class="p-6">
                    <h3 class="mb-4 text-lg font-semibold">Approve Session Request</h3>

                    <p class="mb-4">
                        Are you sure you want to approve this session request from
                        <span class="font-medium">{{ $currentRequest->student->name }}</span>?
                    </p>

                    <div class="p-4 mb-4 rounded-lg bg-gray-50">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-gray-500">Course</p>
                                <p class="font-medium">{{ $currentRequest->course->name }}</p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Date & Time</p>
                                <p class="font-medium">
                                    {{ \Carbon\Carbon::parse($currentRequest->date)->format('M d, Y') }},
                                    {{ \Carbon\Carbon::parse($currentRequest->start_time)->format('h:i A') }} -
                                    {{ \Carbon\Carbon::parse($currentRequest->end_time)->format('h:i A') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 mb-4 border-l-4 border-yellow-400 bg-yellow-50">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-yellow-400" />
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    Approving this request will add it to your scheduled sessions.
                                    The student will be notified of your approval.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button wire:click="closeApproveModal" class="btn btn-ghost">
                            Cancel
                        </button>
                        <button wire:click="approveRequest" class="btn btn-success">
                            Approve Request
                        </button>
                    </div>
                </div>
            @endif
        </x-modal>

        <!-- Reject Modal -->
        <x-modal wire:model="showRejectModal">
            @if($currentRequest)
                <div class="p-6">
                    <h3 class="mb-4 text-lg font-semibold">Reject Session Request</h3>

                    <p class="mb-4">
                        Are you sure you want to reject this session request from
                        <span class="font-medium">{{ $currentRequest->student->name }}</span>?
                    </p>

                    <div class="p-4 mb-4 rounded-lg bg-gray-50">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-gray-500">Course</p>
                                <p class="font-medium">{{ $currentRequest->course->name }}</p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Date & Time</p>
                                <p class="font-medium">
                                    {{ \Carbon\Carbon::parse($currentRequest->date)->format('M d, Y') }},
                                    {{ \Carbon\Carbon::parse($currentRequest->start_time)->format('h:i A') }} -
                                    {{ \Carbon\Carbon::parse($currentRequest->end_time)->format('h:i A') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 form-control">
                        <label class="label">
                            <span class="label-text">Reason for Rejection</span>
                        </label>
                        <textarea
                            wire:model.defer="rejectionReason"
                            class="h-24 textarea textarea-bordered"
                            placeholder="Please provide a reason for rejecting this session request..."
                        ></textarea>
                        @error('rejectionReason')
                            <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button wire:click="closeRejectModal" class="btn btn-ghost">
                            Cancel
                        </button>
                        <button wire:click="rejectRequest" class="btn btn-error">
                            Reject Request
                        </button>
                    </div>
                </div>
            @endif
        </x-modal>
    </div>
</div>
