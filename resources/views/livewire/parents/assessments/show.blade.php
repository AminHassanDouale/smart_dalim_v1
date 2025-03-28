<?php

use Livewire\Volt\Component;
use App\Models\Assessment;
use App\Models\Children;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $assessmentId;
    public $childId;
    
    public $assessment;
    public $child;
    public $pivotData;
    public $submission;
    
    public function mount($id, $childId)
    {
        $this->assessmentId = $id;
        $this->childId = $childId;
        
        // Get the authenticated user (parent)
        $user = Auth::user();
        
        // Verify the child belongs to the parent
        $this->child = $user->children()->findOrFail($this->childId);
        
        // Get the assessment with related data
        $this->assessment = Assessment::with(['course', 'subject', 'teacherProfile', 'questions'])
            ->findOrFail($this->assessmentId);
        
        // Verify the child is assigned to this assessment
        $this->pivotData = $this->assessment->children()
            ->where('children.id', $this->child->id)
            ->firstOrFail()
            ->pivot;
        
        // Get submission if exists
        $this->submission = $this->assessment->submissions()
            ->where('children_id', $this->child->id)
            ->first();
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-800">
                {{ __('Assessment Details') }}
            </h2>
            <a href="{{ route('parents.assessments.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-300 active:bg-gray-400 focus:outline-none focus:border-gray-500 focus:shadow-outline-gray transition ease-in-out duration-150">
                &larr; Back to Assessments
            </a>
        </div>

        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
            <div class="mb-8">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">{{ $assessment->title }}</h1>
                        <p class="text-gray-600 mt-1">{{ $assessment->description }}</p>
                        
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                {{ $assessment::$types[$assessment->type] ?? $assessment->type }}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $assessment->subject->name ?? 'No Subject' }}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                @if($pivotData->status == 'completed' || $pivotData->status == 'graded')
                                    bg-green-100 text-green-800
                                @elseif($pivotData->status == 'in_progress')
                                    bg-blue-100 text-blue-800
                                @else
                                    bg-yellow-100 text-yellow-800
                                @endif
                            ">
                                Status: {{ ucfirst($pivotData->status) }}
                            </span>
                        </div>
                    </div>
                    
                    @if($pivotData->status == 'completed' || $pivotData->status == 'graded')
                        <div class="bg-gray-100 p-4 rounded-lg text-center">
                            <p class="text-sm text-gray-500">Score</p>
                            <p class="text-3xl font-bold 
                                @if($assessment->passing_points && $pivotData->score >= $assessment->passing_points)
                                    text-green-600
                                @elseif($assessment->passing_points && $pivotData->score < $assessment->passing_points)
                                    text-red-600
                                @else
                                    text-gray-800
                                @endif
                            ">
                                {{ $pivotData->score ?? 'N/A' }} / {{ $assessment->total_points }}
                            </p>
                            @if($assessment->passing_points)
                                <p class="text-xs text-gray-500 mt-1">
                                    Passing score: {{ $assessment->passing_points }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-medium text-gray-700 mb-2">Assessment Information</h3>
                        <div class="space-y-2">
                            <div>
                                <span class="text-sm text-gray-500">Teacher:</span>
                                <p>{{ $assessment->teacherProfile->user->name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Course:</span>
                                <p>{{ $assessment->course->name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Total Points:</span>
                                <p>{{ $assessment->total_points }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Passing Points:</span>
                                <p>{{ $assessment->passing_points ?? 'Not specified' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-medium text-gray-700 mb-2">Time Information</h3>
                        <div class="space-y-2">
                            <div>
                                <span class="text-sm text-gray-500">Start Date:</span>
                                <p>{{ $assessment->start_date ? $assessment->start_date->format('M d, Y h:i A') : 'Not specified' }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Due Date:</span>
                                <p>
                                    {{ $assessment->due_date ? $assessment->due_date->format('M d, Y h:i A') : 'No due date' }}
                                    @if($assessment->due_date && $assessment->due_date->isPast())
                                        <span class="text-red-500 text-xs">(Past due)</span>
                                    @endif
                                </p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Time Limit:</span>
                                <p>{{ $assessment->formatted_time_limit }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-medium text-gray-700 mb-2">Child's Progress</h3>
                        <div class="space-y-2">
                            <div>
                                <span class="text-sm text-gray-500">Student:</span>
                                <p>{{ $child->name }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Status:</span>
                                <p>{{ ucfirst($pivotData->status) }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Started:</span>
                                <p>{{ $pivotData->start_time ? $pivotData->start_time->format('M d, Y h:i A') : 'Not started yet' }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Completed:</span>
                                <p>{{ $pivotData->end_time ? $pivotData->end_time->format('M d, Y h:i A') : 'Not completed yet' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                @if($assessment->instructions)
                    <div class="mb-8">
                        <h3 class="font-medium text-gray-700 mb-3">Instructions</h3>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            {{ $assessment->instructions }}
                        </div>
                    </div>
                @endif

                @if($submission && in_array($pivotData->status, ['completed', 'graded']))
                    <div class="mb-8">
                        <h3 class="font-medium text-gray-700 mb-3">Submission Details</h3>
                        
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                            <div class="px-4 py-5 sm:p-6">
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Started At</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $submission->start_time ? $submission->start_time->format('M d, Y h:i A') : 'N/A' }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Completed At</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $submission->end_time ? $submission->end_time->format('M d, Y h:i A') : 'N/A' }}</dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Duration</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            @if($submission->start_time && $submission->end_time)
                                                {{ $submission->duration }} minutes
                                            @else
                                                N/A
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            <span class="px-2 py-1 rounded text-xs
                                                @if($submission->status == 'completed' || $submission->status == 'graded')
                                                    bg-green-100 text-green-800
                                                @elseif($submission->status == 'in_progress')
                                                    bg-blue-100 text-blue-800
                                                @elseif($submission->status == 'late')
                                                    bg-red-100 text-red-800
                                                @else
                                                    bg-yellow-100 text-yellow-800
                                                @endif
                                            ">
                                                {{ ucfirst($submission->status) }}
                                            </span>
                                            
                                            @if($submission->isLate())
                                                <span class="ml-2 text-red-500">Late submission</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                        
                        @if($submission->isGraded() && $submission->feedback)
                            <div class="mt-4">
                                <h4 class="font-medium text-gray-700 mb-2">Teacher Feedback</h4>
                                <div class="bg-white border border-gray-200 rounded-lg p-4">
                                    @if(is_array($submission->feedback) && isset($submission->feedback['general']))
                                        <p>{{ $submission->feedback['general'] }}</p>
                                    @elseif(is_string($submission->feedback))
                                        <p>{{ $submission->feedback }}</p>
                                    @else
                                        <p class="text-gray-500">No general feedback provided.</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if(count($assessment->questions) > 0 && $submission && in_array($pivotData->status, ['completed', 'graded']))
                    <div>
                        <h3 class="font-medium text-gray-700 mb-3">Questions & Answers</h3>
                        
                        <div class="space-y-6">
                            @foreach($assessment->questions as $index => $question)
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-4 py-5 sm:p-6">
                                        <div class="flex justify-between">
                                            <h4 class="text-lg font-medium text-gray-900">Question {{ $index + 1 }}</h4>
                                            <span class="text-sm text-gray-500">{{ $question->points }} points</span>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <p class="text-gray-900">{{ $question->question }}</p>
                                            
                                            @if($question->isMultipleChoice() && $question->options)
                                                <div class="mt-4 space-y-2">
                                                    @foreach($question->options as $option)
                                                        <div class="flex items-start">
                                                            <div class="flex items-center h-5">
                                                                <input type="radio" disabled
                                                                    @if(isset($submission->answers[$question->id]) && $submission->answers[$question->id] == $option)
                                                                        checked
                                                                    @endif
                                                                    class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                                                >
                                                            </div>
                                                            <div class="ml-3 text-sm">
                                                                <label class="font-medium text-gray-700">{{ $option }}</label>
                                                            </div>
                                                            
                                                            @if($submission->isGraded() && isset($question->correct_answer) && $question->correct_answer == $option)
                                                                <div class="ml-2 text-green-500">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif($question->isTrueFalse())
                                                <div class="mt-4 space-y-2">
                                                    <div class="flex items-start">
                                                        <div class="flex items-center h-5">
                                                            <input type="radio" disabled
                                                                @if(isset($submission->answers[$question->id]) && $submission->answers[$question->id] === true)
                                                                    checked
                                                                @endif
                                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                                            >
                                                        </div>
                                                        <div class="ml-3 text-sm">
                                                            <label class="font-medium text-gray-700">True</label>
                                                        </div>
                                                        @if($submission->isGraded() && isset($question->correct_answer) && $question->correct_answer === true)
                                                            <div class="ml-2 text-green-500">
                                                                <svg xmlns="http://www.w3.org /2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-start">
                                                        <div class="flex items-center h-5">
                                                            <input type="radio" disabled
                                                                @if(isset($submission->answers[$question->id]) && $submission->answers[$question->id] === false)
                                                                    checked
                                                                @endif
                                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                                            >
                                                        </div>
                                                        <div class="ml-3 text-sm">
                                                            <label class="font-medium text-gray-700">False</label>
                                                        </div>
                                                        @if($submission->isGraded() && isset($question->correct_answer) && $question->correct_answer === false)
                                                            <div class="ml-2 text-green-500">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <div class="mt-4">
                                                    @if(isset($submission->answers[$question->id]))
                                                        <div class="p-3 bg-gray-50 rounded">
                                                            <p class="text-sm font-medium text-gray-500 mb-2">Student's Answer:</p>
                                                            <p class="text-gray-900">{{ $submission->answers[$question->id] }}</p>
                                                        </div>
                                                        
                                                        @if($submission->isGraded() && isset($question->correct_answer))
                                                            <div class="p-3 mt-2 bg-green-50 rounded">
                                                                <p class="text-sm font-medium text-green-700 mb-2">Correct Answer:</p>
                                                                <p class="text-green-900">{{ $question->correct_answer }}</p>
                                                            </div>
                                                        @endif
                                                    @else
                                                        <p class="text-gray-500">No answer provided.</p>
                                                    @endif
                                                </div>
                                            @endif
                                            
                                            @if($submission->isGraded() && isset($submission->feedback) && is_array($submission->feedback) && isset($submission->feedback[$question->id]))
                                                <div class="mt-4 p-3 bg-blue-50 rounded">
                                                    <p class="text-sm font-medium text-blue-700 mb-1">Teacher Feedback:</p>
                                                    <p class="text-blue-900">{{ $submission->feedback[$question->id] }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>