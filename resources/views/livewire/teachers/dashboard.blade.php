<?php

// LearningSessionController.php
namespace App\Http\Controllers;

use Livewire\Volt\Component;
use App\Models\LearningSession;
use App\Models\TeacherProfile;
use App\Models\Children;
use App\Models\ParentProfile;
use Livewire\WithPagination;
use Carbon\Carbon;

class LearningSessionController extends Component
{
    use WithPagination;

    public $children;
    public $selectedChild;
    public $teachers;
    public $learningStats = [];
    public $sessions;
    public $showCreateModal = false;

    public $newSession = [
        'children_id' => '',
        'teacher_id' => '',
        'subject_id' => '',
        'start_time' => '',
        'end_time' => '',
        'status' => 'scheduled'
    ];

    public function mount()
    {
        $parentProfile = ParentProfile::where('user_id', auth()->id())->firstOrFail();
        $this->children = $parentProfile->children;
        $this->teachers = TeacherProfile::with(['user', 'subjects'])
            ->where('status', 'verified')
            ->get();

        $this->loadSessions();
        $this->loadStats();
    }

    public function loadSessions()
    {
        $childrenIds = $this->children->pluck('id');
        $this->sessions = LearningSession::with(['teacher.user', 'subject', 'child'])
            ->whereIn('children_id', $childrenIds)
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn($session) => Carbon::parse($session->start_time)->format('Y-m-d'));
    }

    public function loadStats()
    {
        $childrenIds = $this->children->pluck('id');

        $this->learningStats = [
            'total_sessions' => LearningSession::whereIn('children_id', $childrenIds)->count(),
            'completed_sessions' => LearningSession::whereIn('children_id', $childrenIds)
                ->where('status', 'completed')
                ->where('attended', true)
                ->count(),
            'upcoming_sessions' => LearningSession::whereIn('children_id', $childrenIds)
                ->where('start_time', '>', now())
                ->count(),
            'total_subjects' => LearningSession::whereIn('children_id', $childrenIds)
                ->distinct('subject_id')
                ->count(),
            'avg_performance' => round(LearningSession::whereIn('children_id', $childrenIds)
                ->where('performance_score', '>', 0)
                ->avg('performance_score') ?? 0)
        ];
    }

    public function createSession()
    {
        $this->validate([
            'newSession.children_id' => 'required|exists:children,id',
            'newSession.teacher_id' => 'required|exists:teacher_profiles,id',
            'newSession.subject_id' => 'required|exists:subjects,id',
            'newSession.start_time' => 'required|date|after:now',
            'newSession.end_time' => 'required|date|after:newSession.start_time',
        ]);

        $conflicts = LearningSession::where('children_id', $this->newSession['children_id'])
            ->where(function ($query) {
                $query->whereBetween('start_time', [$this->newSession['start_time'], $this->newSession['end_time']])
                    ->orWhereBetween('end_time', [$this->newSession['start_time'], $this->newSession['end_time']]);
            })->exists();

        if ($conflicts) {
            session()->flash('error', 'Time slot conflicts with existing session');
            return;
        }

        LearningSession::create($this->newSession);
        $this->reset('newSession');
        $this->showCreateModal = false;
        $this->loadSessions();
        $this->loadStats();

        session()->flash('success', 'Session scheduled successfully');
    }

    public function cancelSession($sessionId)
    {
        $session = LearningSession::findOrFail($sessionId);
        $session->update(['status' => 'cancelled']);
        $this->loadSessions();
        $this->loadStats();
        session()->flash('success', 'Session cancelled');
    }
}?>

<!-- learning-sessions.blade.php -->
<div>
    <x-header>
        <x-slot name="title">Learning Sessions</x-slot>
        <x-slot name="description">Track and manage your children learning journey</x-slot>
    </x-header>

    <div class="p-6 space-y-6">
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-5">
            <x-stat-card
                title="Total Sessions"
                :value="$learningStats['total_sessions']"
                icon="academic-cap"
                color="blue"
            />
            <x-stat-card
                title="Completed"
                :value="$learningStats['completed_sessions']"
                icon="check-circle"
                color="green"
            />
            <!-- Add other stat cards -->
        </div>

        <!-- Create Session Button -->
        <div>
            <x-button wire:click="$toggle('showCreateModal')" icon="plus">
                Schedule New Session
            </x-button>
        </div>

        <!-- Sessions Calendar -->
        <x-card>
            <x-card-header>
                <h2 class="text-lg font-semibold">Upcoming Sessions</h2>
            </x-card-header>

            <x-card-content>
                @forelse($sessions as $date => $dateSessions)
                    <div class="mb-6">
                        <x-heading level="3" class="mb-4">
                            {{ Carbon\Carbon::parse($date)->format('l, F j, Y') }}
                        </x-heading>
                        <div class="space-y-4">
                            @foreach($dateSessions as $session)
                                <x-session-card :session="$session">
                                    @if($session->status === 'scheduled')
                                        <x-button
                                            wire:click="cancelSession({{ $session->id }})"
                                            variant="danger"
                                            icon="x"
                                            size="sm"
                                        />
                                    @endif
                                </x-session-card>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <x-empty-state>No upcoming sessions scheduled</x-empty-state>
                @endforelse
            </x-card-content>
        </x-card>
    </div>

    <!-- Create Session Modal -->
    <x-modal wire:model="showCreateModal">
        <x-modal-header>Schedule New Session</x-modal-header>

        <x-modal-content>
            <div class="space-y-4">
                <x-select
                    wire:model="newSession.children_id"
                    label="Child"
                    :options="$children->pluck('name', 'id')"
                    placeholder="Select Child"
                />

                <x-select
                    wire:model="newSession.teacher_id"
                    label="Teacher"
                    :options="$teachers->pluck('user.name', 'id')"
                    placeholder="Select Teacher"
                />

                <x-datetime-picker
                    wire:model="newSession.start_time"
                    label="Start Time"
                />

                <x-datetime-picker
                    wire:model="newSession.end_time"
                    label="End Time"
                />
            </div>
        </x-modal-content>

        <x-modal-footer>
            <x-button wire:click="$toggle('showCreateModal')" variant="secondary">
                Cancel
            </x-button>
            <x-button wire:click="createSession" variant="primary">
                Schedule
            </x-button>
        </x-modal-footer>
    </x-modal>
</div>
