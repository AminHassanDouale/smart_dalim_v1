<?php

namespace App\Livewire\Teachers;

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Subject;
use App\Models\Course;
use App\Models\TeacherProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

new class extends Component {
    use WithPagination, WithFileUploads;

    public $teacher;
    public $teacherProfile;

    // Filter properties
    public $search = '';
    public $subjectFilter = '';
    public $levelFilter = '';
    public $sortField = 'updated_at';
    public $sortDirection = 'desc';

    // Subjects and courses
    public $subjects = [];
    public $courses = [];

    // Modal states
    public $showCurriculumModal = false;
    public $showDeleteModal = false;
    public $curriculumItemType = 'module'; // 'module', 'lesson', 'resource'

    // Form properties
    public $selectedCourse = null;
    public $selectedModule = null;
    public $selectedLesson = null;

    public $title = '';
    public $description = '';
    public $content = '';
    public $duration = 60; // in minutes
    public $order = 1;
    public $isRequired = true;
    public $courseId = '';
    public $moduleId = '';
    public $lessonId = '';
    public $itemToDelete = null;

    // Attachment
    public $attachment;
    public $attachmentType = '';

    // Curriculum structure builders
    public $newModules = [];
    public $newLessons = [];

    protected $listeners = ['refreshCurriculum' => '$refresh'];

    protected function rules()
    {
        return [
            'title' => 'required|string|min:3|max:255',
            'description' => 'required|string|min:10',
            'content' => 'nullable|string',
            'duration' => 'required|integer|min:5',
            'order' => 'required|integer|min:1',
            'isRequired' => 'boolean',
            'courseId' => 'required_if:curriculumItemType,module|exists:courses,id',
            'moduleId' => 'required_if:curriculumItemType,lesson|exists:curriculum_modules,id',
            'lessonId' => 'required_if:curriculumItemType,resource|exists:curriculum_lessons,id',
            'attachment' => 'nullable|file|max:10240', // 10MB max
            'attachmentType' => 'required_with:attachment|string|in:document,video,audio,image,link,other',
        ];
    }

    public function mount()
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        // Load subjects
        $this->loadSubjects();

        // Load courses
        $this->loadCourses();
    }

    protected function loadSubjects()
    {
        if ($this->teacherProfile) {
            $this->subjects = $this->teacherProfile->subjects()->get();
        } else {
            $this->subjects = collect();
        }
    }

    protected function loadCourses()
    {
        if ($this->teacherProfile) {
            // In a real app, get courses created by this teacher
            // $this->courses = Course::where('teacher_profile_id', $this->teacherProfile->id)->get();

            // For now, use mock data
            $this->courses = $this->getMockCourses();
        } else {
            $this->courses = collect();
        }
    }

    public function getCurriculumItems()
    {
        $courses = $this->courses;

        // Apply filters
        if ($this->search) {
            $search = '%' . $this->search . '%';
            $courses = $courses->filter(function($course) use ($search) {
                // In a real DB query, we would use LIKE
                return Str::contains(strtolower($course['name']), strtolower($this->search)) ||
                       Str::contains(strtolower($course['description']), strtolower($this->search));
            });
        }

        if ($this->subjectFilter) {
            $courses = $courses->filter(function($course) {
                return $course['subject_id'] == $this->subjectFilter;
            });
        }

        if ($this->levelFilter) {
            $courses = $courses->filter(function($course) {
                return $course['level'] == $this->levelFilter;
            });
        }

        // Sort courses
        $sorted = $courses->sortBy([[$this->sortField, $this->sortDirection === 'asc' ? 'asc' : 'desc']]);

        return $sorted->values();
    }

    private function getMockCourses()
    {
        return collect([
            [
                'id' => 1,
                'name' => 'Advanced Mathematics',
                'description' => 'A comprehensive course for advanced mathematics concepts',
                'level' => 'advanced',
                'duration' => 12, // weeks
                'subject_id' => 1,
                'subject_name' => 'Mathematics',
                'status' => 'active',
                'progress' => 80,
                'created_at' => Carbon::now()->subDays(45),
                'updated_at' => Carbon::now()->subDays(5),
                'curriculum' => $this->getMockModules(1),
            ],
            [
                'id' => 2,
                'name' => 'Basic Science',
                'description' => 'Introduction to scientific principles and methods',
                'level' => 'beginner',
                'duration' => 8, // weeks
                'subject_id' => 2,
                'subject_name' => 'Science',
                'status' => 'active',
                'progress' => 65,
                'created_at' => Carbon::now()->subDays(30),
                'updated_at' => Carbon::now()->subDays(2),
                'curriculum' => $this->getMockModules(2),
            ],
            [
                'id' => 3,
                'name' => 'English Literature',
                'description' => 'Explore classic and contemporary literature',
                'level' => 'intermediate',
                'duration' => 10, // weeks
                'subject_id' => 3,
                'subject_name' => 'English',
                'status' => 'draft',
                'progress' => 40,
                'created_at' => Carbon::now()->subDays(15),
                'updated_at' => Carbon::now()->subDays(1),
                'curriculum' => $this->getMockModules(3),
            ],
        ]);
    }

    private function getMockModules($courseId)
    {
        $moduleCount = rand(3, 5);
        $modules = [];

        for ($i = 1; $i <= $moduleCount; $i++) {
            $modules[] = [
                'id' => $courseId * 100 + $i,
                'title' => "Module $i: " . $this->getModuleTitle($courseId, $i),
                'description' => "This module covers important concepts related to " . $this->getModuleTitle($courseId, $i),
                'order' => $i,
                'is_required' => true,
                'progress' => rand(0, 100),
                'lessons' => $this->getMockLessons($courseId, $i),
            ];
        }

        return $modules;
    }

    private function getMockLessons($courseId, $moduleId)
    {
        $lessonCount = rand(3, 6);
        $lessons = [];

        for ($i = 1; $i <= $lessonCount; $i++) {
            $lessons[] = [
                'id' => $courseId * 1000 + $moduleId * 100 + $i,
                'title' => "Lesson $i: " . $this->getLessonTitle($courseId, $moduleId, $i),
                'description' => "This lesson explains the concepts of " . $this->getLessonTitle($courseId, $moduleId, $i),
                'content' => "Detailed content about " . $this->getLessonTitle($courseId, $moduleId, $i),
                'duration' => rand(30, 120), // minutes
                'order' => $i,
                'is_required' => rand(0, 10) > 2, // 80% chance of being required
                'resources' => $this->getMockResources($courseId, $moduleId, $i),
            ];
        }

        return $lessons;
    }

    private function getMockResources($courseId, $moduleId, $lessonId)
    {
        $resourceCount = rand(1, 4);
        $resources = [];

        $types = ['document', 'video', 'audio', 'image', 'link'];

        for ($i = 1; $i <= $resourceCount; $i++) {
            $type = $types[array_rand($types)];
            $resources[] = [
                'id' => $courseId * 10000 + $moduleId * 1000 + $lessonId * 100 + $i,
                'title' => $this->getResourceTitle($type),
                'description' => "A helpful $type resource for this lesson",
                'type' => $type,
                'url' => "https://example.com/resources/$type/$i",
                'order' => $i,
            ];
        }

        return $resources;
    }

    private function getModuleTitle($courseId, $moduleId)
    {
        $titles = [
            1 => [ // Advanced Mathematics
                'Algebra and Functions',
                'Calculus Foundations',
                'Probability and Statistics',
                'Geometry and Trigonometry',
                'Number Theory',
            ],
            2 => [ // Basic Science
                'Scientific Method',
                'Physics Fundamentals',
                'Chemistry Basics',
                'Biology Introduction',
                'Earth Sciences',
            ],
            3 => [ // English Literature
                'Classic Literature',
                'Poetry Analysis',
                'Modern Fiction',
                'Drama and Theatre',
                'Literary Criticism',
            ]
        ];

        return $titles[$courseId][$moduleId - 1] ?? "Module Title $moduleId";
    }

    private function getLessonTitle($courseId, $moduleId, $lessonId)
    {
        $titles = [
            1 => [ // Advanced Mathematics
                [
                    'Linear Equations', 'Quadratic Functions', 'Polynomial Expressions',
                    'Exponential Functions', 'Logarithmic Functions', 'Function Transformations'
                ],
                [
                    'Limits and Continuity', 'Derivatives', 'Derivative Applications',
                    'Integration', 'Integral Applications', 'Differential Equations'
                ],
                [
                    'Descriptive Statistics', 'Probability Theory', 'Random Variables',
                    'Probability Distributions', 'Hypothesis Testing', 'Regression Analysis'
                ],
                [
                    'Euclidean Geometry', 'Coordinate Geometry', 'Triangle Trigonometry',
                    'Circle Theorems', 'Vector Geometry', 'Non-Euclidean Geometry'
                ],
            ],
            2 => [ // Basic Science
                [
                    'Observations and Hypotheses', 'Experimental Design', 'Data Collection',
                    'Data Analysis', 'Scientific Communication', 'Ethics in Science'
                ],
                [
                    'Motion and Forces', 'Energy and Work', 'Waves and Sound',
                    'Electricity and Magnetism', 'Light and Optics', 'Modern Physics'
                ],
                [
                    'Atomic Structure', 'Periodic Table', 'Chemical Bonds',
                    'Chemical Reactions', 'Acids and Bases', 'Organic Chemistry'
                ],
                [
                    'Cell Structure', 'Genetics', 'Human Anatomy',
                    'Plant Biology', 'Ecology', 'Evolution'
                ],
            ],
            3 => [ // English Literature
                [
                    'Shakespeare\'s Works', 'Victorian Literature', 'Ancient Greek Classics',
                    'Medieval Literature', 'Renaissance Literature', 'Romantic Era Classics'
                ],
                [
                    'Poetic Forms', 'Figurative Language', 'Rhythm and Meter',
                    'Sonnets', 'Modern Poetry', 'Poetry Interpretation'
                ],
                [
                    '20th Century Novels', 'Contemporary Fiction', 'Science Fiction',
                    'Fantasy Literature', 'Detective Fiction', 'Literary Modernism'
                ],
                [
                    'Greek Tragedy', 'Shakespearean Drama', 'Modern Plays',
                    'Theatre History', 'Acting Techniques', 'Script Analysis'
                ],
            ]
        ];

        if (isset($titles[$courseId][$moduleId - 1][$lessonId - 1])) {
            return $titles[$courseId][$moduleId - 1][$lessonId - 1];
        }

        return "Lesson Title $lessonId";
    }

    private function getResourceTitle($type)
    {
        $titles = [
            'document' => [
                'Study Guide', 'Practice Problems', 'Worksheet', 'Reference Sheet',
                'Cheat Sheet', 'Summary Notes', 'Research Paper'
            ],
            'video' => [
                'Tutorial Video', 'Lecture Recording', 'Demonstration', 'Animated Explanation',
                'Step-by-Step Guide', 'Video Walkthrough', 'Expert Interview'
            ],
            'audio' => [
                'Podcast Episode', 'Audio Lecture', 'Recorded Discussion', 'Audio Summary',
                'Pronunciation Guide', 'Language Practice', 'Audio Book Chapter'
            ],
            'image' => [
                'Infographic', 'Diagram', 'Chart', 'Illustration',
                'Photograph', 'Visual Aid', 'Concept Map'
            ],
            'link' => [
                'External Resource', 'Helpful Website', 'Interactive Tool', 'Online Calculator',
                'Quiz Link', 'Research Database', 'Reference Website'
            ],
            'other' => [
                'Supplementary Material', 'Additional Resource', 'Bonus Content', 'Extra Practice',
                'Further Reading', 'Optional Extension', 'Challenge Activity'
            ]
        ];

        $options = $titles[$type] ?? $titles['other'];
        return $options[array_rand($options)];
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

    public function openCurriculumModal($courseId = null, $moduleId = null, $lessonId = null, $itemType = 'module')
    {
        $this->resetValidation();
        $this->resetExcept('teacher', 'teacherProfile', 'subjects', 'courses', 'search', 'subjectFilter', 'levelFilter', 'sortField', 'sortDirection');

        $this->curriculumItemType = $itemType;

        if ($courseId) {
            $this->courseId = $courseId;
            $this->selectedCourse = $this->courses->firstWhere('id', $courseId);
        }

        if ($moduleId) {
            $this->moduleId = $moduleId;
            $module = collect($this->selectedCourse['curriculum'])->firstWhere('id', $moduleId);
            $this->selectedModule = $module;
        }

        if ($lessonId && $this->selectedModule) {
            $this->lessonId = $lessonId;
            $lesson = collect($this->selectedModule['lessons'])->firstWhere('id', $lessonId);
            $this->selectedLesson = $lesson;
        }

        if ($itemType === 'module' && $this->selectedCourse) {
            // Set default order to one more than the highest existing module order
            $maxOrder = collect($this->selectedCourse['curriculum'])->max('order') ?? 0;
            $this->order = $maxOrder + 1;
        } else if ($itemType === 'lesson' && $this->selectedModule) {
            // Set default order to one more than the highest existing lesson order
            $maxOrder = collect($this->selectedModule['lessons'])->max('order') ?? 0;
            $this->order = $maxOrder + 1;
        } else if ($itemType === 'resource' && $this->selectedLesson) {
            // Set default order to one more than the highest existing resource order
            $maxOrder = collect($this->selectedLesson['resources'])->max('order') ?? 0;
            $this->order = $maxOrder + 1;
        }

        $this->showCurriculumModal = true;
    }

    public function openDeleteModal($id, $type = 'module')
    {
        $this->itemToDelete = [
            'id' => $id,
            'type' => $type
        ];

        $this->showDeleteModal = true;
    }

    public function saveCurriculumItem()
    {
        // Validate form fields based on item type
        $rules = [];

        if ($this->curriculumItemType === 'module') {
            $rules = [
                'title' => $this->rules()['title'],
                'description' => $this->rules()['description'],
                'order' => $this->rules()['order'],
                'isRequired' => $this->rules()['isRequired'],
                'courseId' => $this->rules()['courseId'],
            ];
        } else if ($this->curriculumItemType === 'lesson') {
            $rules = [
                'title' => $this->rules()['title'],
                'description' => $this->rules()['description'],
                'content' => $this->rules()['content'],
                'duration' => $this->rules()['duration'],
                'order' => $this->rules()['order'],
                'isRequired' => $this->rules()['isRequired'],
                'moduleId' => $this->rules()['moduleId'],
            ];
        } else if ($this->curriculumItemType === 'resource') {
            $rules = [
                'title' => $this->rules()['title'],
                'description' => $this->rules()['description'],
                'order' => $this->rules()['order'],
                'lessonId' => $this->rules()['lessonId'],
                'attachment' => $this->rules()['attachment'],
                'attachmentType' => $this->rules()['attachmentType'],
            ];
        }

        $this->validate($rules);

        // In a real app, save to database
        // For now, just show success message

        $itemType = ucfirst($this->curriculumItemType);

        $this->toast(
            type: 'success',
            title: $itemType . ' saved',
            description: "The $this->curriculumItemType has been saved successfully.",
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->showCurriculumModal = false;
    }

    public function deleteCurriculumItem()
    {
        if (!$this->itemToDelete) {
            return;
        }

        // In a real app, delete from database
        // For now, just show success message

        $itemType = ucfirst($this->itemToDelete['type']);

        $this->toast(
            type: 'success',
            title: $itemType . ' deleted',
            description: "The " . $this->itemToDelete['type'] . " has been deleted successfully.",
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->showDeleteModal = false;
        $this->itemToDelete = null;
    }

    public function addCurriculumBuilder()
    {
        $this->newModules[] = [
            'title' => '',
            'description' => '',
            'order' => count($this->newModules) + 1,
            'is_required' => true,
            'lessons' => []
        ];
    }

    public function addLessonBuilder($moduleIndex)
    {
        if (!isset($this->newModules[$moduleIndex]['lessons'])) {
            $this->newModules[$moduleIndex]['lessons'] = [];
        }

        $this->newModules[$moduleIndex]['lessons'][] = [
            'title' => '',
            'description' => '',
            'duration' => 60,
            'order' => count($this->newModules[$moduleIndex]['lessons']) + 1,
            'is_required' => true
        ];
    }

    public function removeCurriculumBuilder($index)
    {
        unset($this->newModules[$index]);
        $this->newModules = array_values($this->newModules);
    }

    public function removeLessonBuilder($moduleIndex, $lessonIndex)
    {
        unset($this->newModules[$moduleIndex]['lessons'][$lessonIndex]);
        $this->newModules[$moduleIndex]['lessons'] = array_values($this->newModules[$moduleIndex]['lessons']);
    }

    public function saveCurriculumBuilder()
    {
        // Validate all modules and lessons
        foreach ($this->newModules as $moduleIndex => $module) {
            if (empty($module['title'])) {
                $this->addError("newModules.$moduleIndex.title", 'The module title is required.');
                return;
            }

            if (empty($module['description'])) {
                $this->addError("newModules.$moduleIndex.description", 'The module description is required.');
                return;
            }

            foreach ($module['lessons'] as $lessonIndex => $lesson) {
                if (empty($lesson['title'])) {
                    $this->addError("newModules.$moduleIndex.lessons.$lessonIndex.title", 'The lesson title is required.');
                    return;
                }

                if (empty($lesson['description'])) {
                    $this->addError("newModules.$moduleIndex.lessons.$lessonIndex.description", 'The lesson description is required.');
                    return;
                }
            }
        }

        // In a real app, save to database
        // For now, just show success message

        $this->toast(
            type: 'success',
            title: 'Curriculum saved',
            description: 'The curriculum structure has been saved successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->newModules = [];
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
}; ?>

<div class="p-6">
    <div class="mx-auto max-w-7xl">
        <!-- Header Section -->
        <div class="flex flex-col items-start justify-between gap-4 mb-8 md:flex-row md:items-center">
            <div>
                <h1 class="text-3xl font-bold">Curriculum Management</h1>
                <p class="mt-1 text-base-content/70">Create and organize course content, modules, and lessons</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    onclick="document.getElementById('curriculum-builder-panel').scrollIntoView({behavior: 'smooth'})"
                    class="btn btn-outline"
                >
                    <x-icon name="o-square-3-stack-3d" class="w-4 h-4 mr-2" />
                    Curriculum Builder
                </button>
                <a href="{{ route('teachers.courses.create') }}" class="btn btn-primary">
                    <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                    Create New Course
                </a>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="p-4 mb-6 shadow-lg rounded-xl bg-base-100">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <!-- Search -->
                <div>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <x-icon name="o-magnifying-glass" class="w-5 h-5 text-base-content/50" />
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search courses..."
                            class="w-full pl-10 input input-bordered"
                        >
                    </div>
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

                <!-- Level Filter -->
                <div>
                    <select wire:model.live="levelFilter" class="w-full select select-bordered">
                        <option value="">All Levels</option>
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                        <option value="all">All Levels</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Courses and Curriculum -->
        <div class="mb-8 shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="text-xl font-semibold">My Courses</h2>

                @if(count($this->getCurriculumItems()) > 0)
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th wire:click="sortBy('name')" class="cursor-pointer hover:bg-base-200">
                                        Course Name
                                        @if($sortField === 'name')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="inline w-4 h-4" />
                                        @endif
                                    </th>
                                    <th>Subject</th>
                                    <th wire:click="sortBy('level')" class="cursor-pointer hover:bg-base-200">
                                        Level
                                        @if($sortField === 'level')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="inline w-4 h-4" />
                                        @endif
                                    </th>
                                    <th>Modules</th>
                                    <th wire:click="sortBy('progress')" class="cursor-pointer hover:bg-base-200">
                                        Progress
                                        @if($sortField === 'progress')
                                            <x-icon name="{{ $sortDirection === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="inline w-4 h-4" />
                                        @endif
                                    </th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->getCurriculumItems() as $course)
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $course['name'] }}</div>
                                            <div class="text-xs text-base-content/70">{{ Str::limit($course['description'], 60) }}</div>
                                        </td>
                                        <td>{{ $course['subject_name'] }}</td>
                                        <td>
                                            <div class="badge {{ $course['level'] === 'beginner' ? 'badge-success' : ($course['level'] === 'intermediate' ? 'badge-info' : 'badge-warning') }}">
                                                {{ ucfirst($course['level']) }}
                                            </div>
                                        </td>
                                        <td>{{ count($course['curriculum']) }}</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <progress class="w-20 progress progress-primary" value="{{ $course['progress'] }}" max="100"></progress>
                                                <span>{{ $course['progress'] }}%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex gap-1">
                                                <div class="dropdown dropdown-end">
                                                    <div tabindex="0" role="button" class="btn btn-sm">
                                                        <x-icon name="o-plus" class="w-4 h-4 mr-1" />
                                                        Add
                                                    </div>
                                                    <ul tabindex="0" class="p-2 shadow dropdown-content menu bg-base-100 rounded-box w-52">
                                                        <li><a wire:click="openCurriculumModal({{ $course['id'] }}, null, null, 'module')">Add Module</a></li>
                                                        <li><a href="{{ route('teachers.courses.edit', $course['id']) }}?tab=curriculum">Edit All Curriculum</a></li>
                                                    </ul>
                                                </div>

                                                <a href="{{ route('teachers.courses.show', $course['id']) }}?tab=curriculum" class="btn btn-sm btn-outline">
                                                    <x-icon name="o-eye" class="w-4 h-4 mr-1" />
                                                View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <x-icon name="o-academic-cap" class="w-16 h-16 mb-4 text-base-content/30" />
                            <h3 class="text-xl font-bold">No courses found</h3>
                            <p class="mt-2 text-base-content/70">
                                @if($search || $subjectFilter || $levelFilter)
                                    No courses match your filters. Try adjusting your search criteria.
                                @else
                                    You haven't created any courses yet. Create your first course to get started.
                                @endif
                            </p>
                            <a href="{{ route('teachers.courses.create') }}" class="mt-4 btn btn-primary">
                                <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                                Create Course
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Curriculum Explorer -->
        <div class="mb-8 shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="text-xl font-semibold">Curriculum Explorer</h2>
                <p class="mb-4 text-base-content/70">Expand a course to view and manage its modules and lessons</p>

                <div class="space-y-4">
                    @foreach($this->getCurriculumItems() as $course)
                        <div class="collapse collapse-arrow bg-base-200">
                            <input type="checkbox" />
                            <div class="flex items-center gap-2 font-medium collapse-title">
                                <div class="badge {{ $course['status'] === 'draft' ? 'badge-warning' : 'badge-success' }} badge-sm">
                                    {{ ucfirst($course['status']) }}
                                </div>
                                {{ $course['name'] }} ({{ count($course['curriculum']) }} modules)
                            </div>
                            <div class="collapse-content">
                                <div class="p-2 space-y-2">
                                    @foreach($course['curriculum'] as $moduleIndex => $module)
                                        <div class="border rounded-md collapse collapse-arrow bg-base-100 border-base-300">
                                            <input type="checkbox" />
                                            <div class="flex items-center justify-between collapse-title">
                                                <div>
                                                    <span class="font-medium">{{ $module['title'] }}</span>
                                                    <span class="ml-2 text-xs text-base-content/70">({{ count($module['lessons']) }} lessons)</span>
                                                </div>
                                                <div class="flex gap-1 mr-8">
                                                    <button
                                                        wire:click.stop="openCurriculumModal({{ $course['id'] }}, {{ $module['id'] }}, null, 'lesson')"
                                                        class="btn btn-xs btn-ghost"
                                                    >
                                                        <x-icon name="o-plus" class="w-3 h-3" />
                                                    </button>
                                                    <button
                                                        wire:click.stop="openDeleteModal({{ $module['id'] }}, 'module')"
                                                        class="btn btn-xs btn-ghost text-error"
                                                    >
                                                        <x-icon name="o-trash" class="w-3 h-3" />
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="p-2 collapse-content">
                                                <div class="mb-2 text-sm">{{ $module['description'] }}</div>
                                                <div class="overflow-x-auto">
                                                    <table class="table table-xs">
                                                        <thead>
                                                            <tr>
                                                                <th>Order</th>
                                                                <th>Lesson</th>
                                                                <th>Duration</th>
                                                                <th>Required</th>
                                                                <th>Resources</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($module['lessons'] as $lessonIndex => $lesson)
                                                                <tr>
                                                                    <td>{{ $lesson['order'] }}</td>
                                                                    <td>{{ $lesson['title'] }}</td>
                                                                    <td>{{ $lesson['duration'] }} min</td>
                                                                    <td>
                                                                        @if($lesson['is_required'])
                                                                            <span class="text-success">Yes</span>
                                                                        @else
                                                                            <span class="text-warning">No</span>
                                                                        @endif
                                                                    </td>
                                                                    <td>{{ count($lesson['resources']) }}</td>
                                                                    <td>
                                                                        <div class="flex gap-1">
                                                                            <button
                                                                                wire:click="openCurriculumModal({{ $course['id'] }}, {{ $module['id'] }}, {{ $lesson['id'] }}, 'resource')"
                                                                                class="btn btn-xs btn-ghost"
                                                                            >
                                                                                <x-icon name="o-plus" class="w-3 h-3" />
                                                                            </button>
                                                                            <button
                                                                                wire:click="openDeleteModal({{ $lesson['id'] }}, 'lesson')"
                                                                                class="btn btn-xs btn-ghost text-error"
                                                                            >
                                                                                <x-icon name="o-trash" class="w-3 h-3" />
                                                                            </button>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="flex justify-end">
                                        <button
                                            wire:click="openCurriculumModal({{ $course['id'] }}, null, null, 'module')"
                                            class="btn btn-sm"
                                        >
                                            <x-icon name="o-plus" class="w-4 h-4 mr-1" />
                                            Add Module
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Curriculum Builder -->
        <div id="curriculum-builder-panel" class="mb-8 shadow-xl card bg-base-100">
            <div class="card-body">
                <h2 class="text-xl font-semibold">Curriculum Builder</h2>
                <p class="mb-4 text-base-content/70">Quickly create a complete curriculum structure</p>

                <div class="space-y-4">
                    <div class="flex justify-end">
                        <button
                            wire:click="addCurriculumBuilder"
                            class="btn btn-primary"
                        >
                            <x-icon name="o-plus" class="w-4 h-4 mr-1" />
                            Add Module
                        </button>
                    </div>

                    @foreach($newModules as $moduleIndex => $module)
                        <div class="p-4 border rounded-lg border-base-300 bg-base-200">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium">Module {{ $moduleIndex + 1 }}</h3>
                                <button
                                    wire:click="removeCurriculumBuilder({{ $moduleIndex }})"
                                    class="btn btn-sm btn-ghost text-error"
                                >
                                    <x-icon name="o-x-mark" class="w-4 h-4" />
                                </button>
                            </div>

                            <div class="grid gap-4 mb-4">
                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text">Module Title</span>
                                    </label>
                                    <input
                                        type="text"
                                        wire:model="newModules.{{ $moduleIndex }}.title"
                                        class="input input-bordered @error('newModules.'.$moduleIndex.'.title') input-error @enderror"
                                        placeholder="Enter module title"
                                    />
                                    @error('newModules.'.$moduleIndex.'.title')
                                        <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="form-control">
                                    <label class="label">
                                        <span class="label-text">Module Description</span>
                                    </label>
                                    <textarea
                                        wire:model="newModules.{{ $moduleIndex }}.description"
                                        class="textarea textarea-bordered @error('newModules.'.$moduleIndex.'.description') textarea-error @enderror"
                                        placeholder="Enter module description"
                                    ></textarea>
                                    @error('newModules.'.$moduleIndex.'.description')
                                        <span class="mt-1 text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="flex items-center">
                                    <input
                                        type="checkbox"
                                        wire:model="newModules.{{ $moduleIndex }}.is_required"
                                        class="checkbox checkbox-primary"
                                    />
                                    <span class="ml-2">Required module (students must complete this)</span>
                                </div>
                            </div>

                            <div class="divider">Lessons</div>

                            <div class="space-y-4">
                                @if(isset($module['lessons']) && count($module['lessons']) > 0)
                                    @foreach($module['lessons'] as $lessonIndex => $lesson)
                                        <div class="p-3 border rounded-lg border-base-300 bg-base-100">
                                            <div class="flex items-start justify-between mb-2">
                                                <h4 class="font-medium">Lesson {{ $lessonIndex + 1 }}</h4>
                                                <button
                                                    wire:click="removeLessonBuilder({{ $moduleIndex }}, {{ $lessonIndex }})"
                                                    class="btn btn-xs btn-ghost text-error"
                                                >
                                                    <x-icon name="o-x-mark" class="w-3 h-3" />
                                                </button>
                                            </div>

                                            <div class="grid gap-3">
                                                <div class="form-control">
                                                    <label class="label">
                                                        <span class="label-text">Lesson Title</span>
                                                    </label>
                                                    <input
                                                        type="text"
                                                        wire:model="newModules.{{ $moduleIndex }}.lessons.{{ $lessonIndex }}.title"
                                                        class="input input-sm input-bordered @error('newModules.'.$moduleIndex.'.lessons.'.$lessonIndex.'.title') input-error @enderror"
                                                        placeholder="Enter lesson title"
                                                    />
                                                    @error('newModules.'.$moduleIndex.'.lessons.'.$lessonIndex.'.title')
                                                        <span class="mt-1 text-xs text-error">{{ $message }}</span>
                                                    @enderror
                                                </div>

                                                <div class="form-control">
                                                    <label class="label">
                                                        <span class="label-text">Lesson Description</span>
                                                    </label>
                                                    <textarea
                                                        wire:model="newModules.{{ $moduleIndex }}.lessons.{{ $lessonIndex }}.description"
                                                        class="textarea textarea-sm textarea-bordered @error('newModules.'.$moduleIndex.'.lessons.'.$lessonIndex.'.description') textarea-error @enderror"
                                                        placeholder="Enter lesson description"
                                                    ></textarea>
                                                    @error('newModules.'.$moduleIndex.'.lessons.'.$lessonIndex.'.description')
                                                        <span class="mt-1 text-xs text-error">{{ $message }}</span>
                                                    @enderror
                                                </div>

                                                <div class="flex gap-4">
                                                    <div class="form-control">
                                                        <label class="label">
                                                            <span class="label-text">Duration (minutes)</span>
                                                        </label>
                                                        <input
                                                            type="number"
                                                            wire:model="newModules.{{ $moduleIndex }}.lessons.{{ $lessonIndex }}.duration"
                                                            class="w-24 input input-sm input-bordered"
                                                            min="5"
                                                        />
                                                    </div>

                                                    <div class="form-control">
                                                        <label class="label">
                                                            <span class="label-text">Required</span>
                                                        </label>
                                                        <input
                                                            type="checkbox"
                                                            wire:model="newModules.{{ $moduleIndex }}.lessons.{{ $lessonIndex }}.is_required"
                                                            class="mt-2 checkbox checkbox-sm checkbox-primary"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="p-4 text-center bg-base-200">
                                        <p class="text-base-content/70">No lessons added yet</p>
                                    </div>
                                @endif

                                <button
                                    wire:click="addLessonBuilder({{ $moduleIndex }})"
                                    class="w-full btn btn-sm btn-outline"
                                >
                                    <x-icon name="o-plus" class="w-4 h-4 mr-1" />
                                    Add Lesson
                                </button>
                            </div>
                        </div>
                    @endforeach

                    @if(count($newModules) > 0)
                        <div class="flex justify-end mt-6">
                            <button
                                wire:click="saveCurriculumBuilder"
                                class="btn btn-primary"
                            >
                                <x-icon name="o-check" class="w-4 h-4 mr-1" />
                                Save Curriculum
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Curriculum Item Modal -->
    <div class="modal {{ $showCurriculumModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ ucfirst($curriculumItemType) }} Details</h3>

            <form wire:submit.prevent="saveCurriculumItem">
                <div class="mt-4 space-y-4">
                    @if($curriculumItemType === 'module')
                        <!-- Module Form -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Course</span>
                            </label>
                            <select
                                wire:model="courseId"
                                class="select select-bordered @error('courseId') select-error @enderror"
                                @if($selectedCourse) disabled @endif
                            >
                                <option value="">Select a course</option>
                                @foreach($this->getCurriculumItems() as $course)
                                    <option value="{{ $course['id'] }}">{{ $course['name'] }}</option>
                                @endforeach
                            </select>
                            @error('courseId') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Module Title</span>
                            </label>
                            <input
                                type="text"
                                wire:model="title"
                                class="input input-bordered @error('title') input-error @enderror"
                                placeholder="E.g., Introduction to the Course"
                            />
                            @error('title') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Module Description</span>
                            </label>
                            <textarea
                                wire:model="description"
                                class="textarea textarea-bordered h-24 @error('description') textarea-error @enderror"
                                placeholder="Describe what this module covers"
                            ></textarea>
                            @error('description') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex gap-4">
                            <div class="w-1/3 form-control">
                                <label class="label">
                                    <span class="label-text">Order</span>
                                </label>
                                <input
                                    type="number"
                                    wire:model="order"
                                    class="input input-bordered @error('order') input-error @enderror"
                                    min="1"
                                />
                                @error('order') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="flex items-end mb-2 form-control">
                                <label class="cursor-pointer label">
                                    <span class="mr-2 label-text">Required</span>
                                    <input type="checkbox" wire:model="isRequired" class="checkbox checkbox-primary" />
                                </label>
                            </div>
                        </div>
                    @elseif($curriculumItemType === 'lesson')
                        <!-- Lesson Form -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Module</span>
                            </label>
                            <select
                                wire:model="moduleId"
                                class="select select-bordered @error('moduleId') select-error @enderror"
                                @if($selectedModule) disabled @endif
                            >
                                <option value="">Select a module</option>
                                @if($selectedCourse)
                                    @foreach($selectedCourse['curriculum'] as $module)
                                        <option value="{{ $module['id'] }}">{{ $module['title'] }}</option>
                                    @endforeach
                                @endif
                            </select>
                            @error('moduleId') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Lesson Title</span>
                            </label>
                            <input
                                type="text"
                                wire:model="title"
                                class="input input-bordered @error('title') input-error @enderror"
                                placeholder="E.g., Getting Started with Variables"
                            />
                            @error('title') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Lesson Description</span>
                            </label>
                            <textarea
                                wire:model="description"
                                class="textarea textarea-bordered h-20 @error('description') textarea-error @enderror"
                                placeholder="Brief description of what this lesson covers"
                            ></textarea>
                            @error('description') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Lesson Content</span>
                                <span class="label-text-alt">Optional - add full content or leave blank to add later</span>
                            </label>
                            <textarea
                                wire:model="content"
                                class="h-32 textarea textarea-bordered"
                                placeholder="Full lesson content can be added here or later"
                            ></textarea>
                        </div>

                        <div class="flex gap-4">
                            <div class="w-1/3 form-control">
                                <label class="label">
                                    <span class="label-text">Duration (minutes)</span>
                                </label>
                                <input
                                    type="number"
                                    wire:model="duration"
                                    class="input input-bordered @error('duration') input-error @enderror"
                                    min="5"
                                />
                                @error('duration') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="w-1/3 form-control">
                                <label class="label">
                                    <span class="label-text">Order</span>
                                </label>
                                <input
                                    type="number"
                                    wire:model="order"
                                    class="input input-bordered @error('order') input-error @enderror"
                                    min="1"
                                />
                                @error('order') <span class="text-sm text-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="flex items-end mb-2 form-control">
                                <label class="cursor-pointer label">
                                    <span class="mr-2 label-text">Required</span>
                                    <input type="checkbox" wire:model="isRequired" class="checkbox checkbox-primary" />
                                </label>
                            </div>
                        </div>
                    @elseif($curriculumItemType === 'resource')
                        <!-- Resource Form -->
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Lesson</span>
                            </label>
                            <select
                                wire:model="lessonId"
                                class="select select-bordered @error('lessonId') select-error @enderror"
                                @if($selectedLesson) disabled @endif
                            >
                                <option value="">Select a lesson</option>
                                @if($selectedModule)
                                    @foreach($selectedModule['lessons'] as $lesson)
                                        <option value="{{ $lesson['id'] }}">{{ $lesson['title'] }}</option>
                                    @endforeach
                                @endif
                            </select>
                            @error('lessonId') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Resource Title</span>
                            </label>
                            <input
                                type="text"
                                wire:model="title"
                                class="input input-bordered @error('title') input-error @enderror"
                                placeholder="E.g., Practice Worksheet"
                            />
                            @error('title') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Resource Description</span>
                            </label>
                            <textarea
                                wire:model="description"
                                class="textarea textarea-bordered @error('description') textarea-error @enderror"
                                placeholder="Describe this resource and how students should use it"
                            ></textarea>
                            @error('description') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Resource Type</span>
                            </label>
                            <select
                                wire:model="attachmentType"
                                class="select select-bordered @error('attachmentType') select-error @enderror"
                            >
                                <option value="">Select a type</option>
                                <option value="document">Document</option>
                                <option value="video">Video</option>
                                <option value="audio">Audio</option>
                                <option value="image">Image</option>
                                <option value="link">Link/URL</option>
                                <option value="other">Other</option>
                            </select>
                            @error('attachmentType') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Upload File</span>
                                <span class="label-text-alt">Max 10MB</span>
                            </label>
                            <input
                                type="file"
                                wire:model="attachment"
                                class="file-input file-input-bordered w-full @error('attachment') file-input-error @enderror"
                            />
                            @error('attachment') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="w-1/3 form-control">
                            <label class="label">
                                <span class="label-text">Order</span>
                            </label>
                            <input
                                type="number"
                                wire:model="order"
                                class="input input-bordered @error('order') input-error @enderror"
                                min="1"
                            />
                            @error('order') <span class="text-sm text-error">{{ $message }}</span> @enderror
                        </div>
                    @endif
                </div>

                <div class="modal-action">
                    <button type="button" wire:click="$set('showCurriculumModal', false)" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal {{ $showDeleteModal ? 'modal-open' : '' }}">
        <div class="modal-box">
            <h3 class="text-lg font-bold">Confirm Delete</h3>

            <p class="py-4">
                Are you sure you want to delete this {{ $itemToDelete ? $itemToDelete['type'] : 'item' }}?
                @if($itemToDelete && $itemToDelete['type'] === 'module')
                    This will also delete all lessons and resources within it.
                @elseif($itemToDelete && $itemToDelete['type'] === 'lesson')
                    This will also delete all resources attached to this lesson.
                @endif
                <br><br>
                This action cannot be undone.
            </p>

            <div class="modal-action">
                <button wire:click="$set('showDeleteModal', false)" class="btn btn-outline">Cancel</button>
                <button wire:click="deleteCurriculumItem" class="btn btn-error">
                    <x-icon name="o-trash" class="w-4 h-4 mr-1" />
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>
