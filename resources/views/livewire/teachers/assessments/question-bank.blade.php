<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\AssessmentQuestion;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    use WithPagination;

    // Filters
    public $search = '';
    public $typeFilter = '';
    public $subjectFilter = '';
    public $difficultyFilter = '';
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'subjectFilter' => ['except' => ''],
        'difficultyFilter' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function mount()
    {
        // Initialize any needed data
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

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingSubjectFilter()
    {
        $this->resetPage();
    }

    public function updatingDifficultyFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'typeFilter', 'subjectFilter', 'difficultyFilter']);
        $this->resetPage();
    }

    public function with(): array
    {
        $teacherProfileId = Auth::user()->teacherProfile->id;

        $query = AssessmentQuestion::query()
            ->whereHas('assessment', function($q) use ($teacherProfileId) {
                $q->where('teacher_profile_id', $teacherProfileId);
            })
            ->with(['assessment', 'assessment.subject']);

        // Apply search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('question', 'like', "%{$this->search}%")
                  ->orWhere('correct_answer', 'like', "%{$this->search}%");
            });
        }

        // Apply filters
        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->subjectFilter) {
            $query->whereHas('assessment', function($q) {
                $q->where('subject_id', $this->subjectFilter);
            });
        }

        if ($this->difficultyFilter) {
            $query->where('difficulty', $this->difficultyFilter);
        }

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        $questions = $query->paginate(10);
        $subjects = Subject::orderBy('name')->get();

        // Question types and difficulty levels
        $questionTypes = [
            'multiple_choice' => 'Multiple Choice',
            'true_false' => 'True/False',
            'short_answer' => 'Short Answer',
            'essay' => 'Essay',
            'matching' => 'Matching',
            'fill_blank' => 'Fill in the Blank'
        ];

        $difficultyLevels = [
            'easy' => 'Easy',
            'medium' => 'Medium',
            'hard' => 'Hard'
        ];

        return [
            'questions' => $questions,
            'subjects' => $subjects,
            'questionTypes' => $questionTypes,
            'difficultyLevels' => $difficultyLevels
        ];
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 md:flex-row md:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('teachers.assessments.index') }}" class="btn btn-circle btn-sm btn-ghost">
                        <x-icon name="o-arrow-left" class="w-5 h-5" />
                    </a>
                    <h1 class="text-3xl font-bold">Question Bank</h1>
                </div>
                <p class="mt-1 text-base-content/70">Access and reuse your assessment questions</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('teachers.assessments.create') }}" class="btn btn-primary">
                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                    Create Assessment
                </a>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="p-4 mb-6 shadow-xl rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <!-- Search -->
                <div>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search questions..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
                </div>

                <!-- Type Filter -->
                <div>
                    <select wire:model.live="typeFilter" class="w-full select select-bordered">
                        <option value="">All Question Types</option>
                        @foreach($questionTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Subject Filter -->
                <div>
                    <select wire:model.live="subjectFilter" class="w-full select select-bordered">
                        <option value="">All Subjects</option>
                        @foreach($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Difficulty Filter -->
                <div>
                    <select wire:model.live="difficultyFilter" class="w-full select select-bordered">
                        <option value="">All Difficulty Levels</option>
                        @foreach($difficultyLevels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($search || $typeFilter || $subjectFilter || $difficultyFilter)
                <div class="flex justify-end mt-3">
                    <button
                        wire:click="clearFilters"
                        class="btn btn-sm btn-ghost"
                    >
                        <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                        Clear Filters
                    </button>
                </div>
            @endif
        </div>

        <!-- Questions Table -->
        <div class="overflow-hidden shadow-xl bg-base-100 rounded-xl">
            @if($questions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table w-full table-zebra">
                        <thead>
                            <tr>
                                <th class="cursor-pointer" wire:click="sortBy('question')">
                                    <div class="flex items-center">
                                        Question
                                        @if($sortField === 'question')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="w-4 h-4 ml-1" />
                                        @endif
                                    </div>
                                </th>
                                <th>Type</th>
                                <th>Assessment</th>
                                <th>Subject</th>
                                <th>Difficulty</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($questions as $question)
                                <tr>
                                    <td>
                                        <div class="max-w-md font-medium">
                                            {{ Str::limit(strip_tags($question->question), 60) }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="badge {{ match($question->type) {
                                            'multiple_choice' => 'badge-primary',
                                            'true_false' => 'badge-secondary',
                                            'short_answer' => 'badge-accent',
                                            'essay' => 'badge-info',
                                            default => 'badge-ghost'
                                        } }}">
                                            {{ $questionTypes[$question->type] ?? ucfirst($question->type) }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-sm">{{ Str::limit($question->assessment->title, 30) }}</span>
                                    </td>
                                    <td>
                                        @if($question->assessment->subject)
                                            <span>{{ $question->assessment->subject->name }}</span>
                                        @else
                                            <span class="text-xs text-base-content/60">Not assigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="badge {{ match($question->difficulty ?? 'medium') {
                                            'easy' => 'badge-success',
                                            'medium' => 'badge-warning',
                                            'hard' => 'badge-error',
                                            default => 'badge-ghost'
                                        } }}">
                                            {{ $difficultyLevels[$question->difficulty] ?? 'Medium' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <a href="#" class="btn btn-sm btn-ghost" title="View">
                                                <x-icon name="o-eye" class="w-4 h-4" />
                                            </a>
                                            <a href="#" class="btn btn-sm btn-ghost" title="Use in New Assessment">
                                                <x-icon name="o-plus" class="w-4 h-4" />
                                            </a>
                                            <a href="#" class="btn btn-sm btn-ghost" title="Duplicate">
                                                <x-icon name="o-document-duplicate" class="w-4 h-4" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t">
                    {{ $questions->links() }}
                </div>
            @else
                <div class="p-12 text-center">
                    <div class="flex flex-col items-center justify-center">
                        <x-icon name="o-question-mark-circle" class="w-20 h-20 mb-4 text-base-content/20" />

                        @if($search || $typeFilter || $subjectFilter || $difficultyFilter)
                            <h3 class="text-xl font-bold">No matching questions</h3>
                            <p class="max-w-md mx-auto mt-2 text-base-content/70">
                                Try adjusting your search filters to find what you're looking for.
                            </p>

                            <button
                                wire:click="clearFilters"
                                class="mt-4 btn btn-outline"
                            >
                                <x-icon name="o-x-mark" class="w-4 h-4 mr-2" />
                                Clear Filters
                            </button>
                        @else
                            <h3 class="text-xl font-bold">No questions in your bank yet</h3>
                            <p class="max-w-md mx-auto mt-2 text-base-content/70">
                                Create assessments with questions to start building your question bank.
                            </p>

                            <a
                                href="{{ route('teachers.assessments.create') }}"
                                class="mt-4 btn btn-primary"
                            >
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Create Assessment
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>