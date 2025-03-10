<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\LearningSession;
use App\Models\Children;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public $session;
    public $teacher;
    public $teacherProfile;

    // Student info
    public $student;

    // Session data
    public $sessionNotes = '';
    public $attendanceStatus = null;
    public $performanceRating = 0;
    public $sessionTopics = [];
    public $sessionFeedback = '';
    public $sessionStatus = '';

    // Materials
    public $existingMaterials = [];
    public $newMaterial = null;
    public $materialName = '';
    public $materialDescription = '';

    // Tabs
    public $activeTab = 'overview';

    // Timeline
    public $timelineEvents = [];

    // Modals
    public $showRescheduleModal = false;
    public $showCancelModal = false;
    public $showStartSessionModal = false;
    public $showCompleteSessionModal = false;
    public $showAddMaterialModal = false;
    public $showConfirmationModal = false;
    public $confirmationAction = '';
    public $confirmationMessage = '';
    public $confirmationData = null;

    // Reschedule form
    public $rescheduleDate = '';
    public $rescheduleStartTime = '';
    public $rescheduleEndTime = '';

    // Cancel form
    public $cancellationReason = '';

    // Progress tracking
    public $progressItems = [];
    public $newProgressItem = '';
    public $newTopic = '';

    // Student's related sessions
    public $relatedSessions = [];
    public $studentCourses = [];


    // Form validation rules
    protected function rules()
    {
        return [
            'sessionNotes' => 'nullable|string',
            'attendanceStatus' => 'nullable|boolean',
            'performanceRating' => 'nullable|integer|min:1|max:5',
            'sessionTopics' => 'nullable|array',
            'sessionFeedback' => 'nullable|string',
            'sessionStatus' => 'required|string|in:scheduled,confirmed,in_progress,completed,cancelled',

            'materialName' => 'required_with:newMaterial|string|max:255',
            'materialDescription' => 'nullable|string',
            'newMaterial' => 'nullable|file|max:10240', // 10MB max

            'rescheduleDate' => 'required_if:showRescheduleModal,true|date|after_or_equal:today',
            'rescheduleStartTime' => 'required_if:showRescheduleModal,true',
            'rescheduleEndTime' => 'required_if:showRescheduleModal,true|after:rescheduleStartTime',

            'cancellationReason' => 'required_if:showCancelModal,true|string|min:5',

            'progressItems.*.title' => 'required|string',
            'progressItems.*.completed' => 'boolean',
            'newProgressItem' => 'nullable|string',
        ];
    }

    public function mount($session)
    {
        $this->teacher = Auth::user();
        $this->teacherProfile = $this->teacher->teacherProfile;

        // In a real app, we would fetch this from the database
        $this->session = $this->getMockSession($session);

        // Set active tab from query parameter if available
        $this->activeTab = request()->has('tab') ? request()->get('tab') : 'overview';

        // Set initial form values
        $this->initializeFormValues();

        // Load student info
        $this->loadStudentInfo();

        // Load related sessions
        $this->loadRelatedSessions();

        // Load student's courses
        $this->loadStudentCourses();

        // Generate timeline
        $this->generateTimeline();
    }

    public function initializeFormValues()
    {
        $this->sessionNotes = $this->session['notes'] ?? '';
        $this->attendanceStatus = $this->session['attended'] ?? null;
        $this->performanceRating = $this->session['performance_score'] ?? 0;
        $this->sessionTopics = $this->session['topics_covered'] ?? [];
        $this->sessionFeedback = $this->session['student_feedback'] ?? '';
        $this->sessionStatus = $this->session['status'] ?? 'scheduled';
        $this->existingMaterials = $this->session['materials'] ?? [];

        // Set reschedule form initial values
        $this->rescheduleDate = $this->session['date'];
        $this->rescheduleStartTime = $this->session['start_time'];
        $this->rescheduleEndTime = $this->session['end_time'];

        // Set progress items
        $this->progressItems = $this->session['progress_items'] ?? [
            ['title' => 'Introduction and review of previous concepts', 'completed' => false],
            ['title' => 'Explanation of new topics', 'completed' => false],
            ['title' => 'Practical exercises', 'completed' => false],
            ['title' => 'Questions and answers', 'completed' => false],
            ['title' => 'Homework assignment', 'completed' => false]
        ];
    }

    public function loadStudentInfo()
    {
        // In a real app, we would fetch this from the database
        $this->student = [
            'id' => $this->session['student_id'],
            'name' => $this->session['student_name'],
            'email' => 'student' . $this->session['student_id'] . '@example.com',
            'avatar' => null,
            'grade' => '10th Grade',
            'last_session_at' => Carbon::now()->subDays(7)->format('Y-m-d'),
            'total_sessions' => 12,
            'attendance_rate' => 92,
            'performance_avg' => 4.2,
            'enrolled_at' => Carbon::now()->subMonths(2)->format('Y-m-d'),
        ];
    }

    public function loadRelatedSessions()
    {
        // In a real app, we would fetch this from the database
        $this->relatedSessions = $this->getMockRelatedSessions();
    }

    public function loadStudentCourses()
    {
        // In a real app, we would fetch this from the database
        $this->studentCourses = [
            [
                'id' => $this->session['course_id'],
                'name' => $this->session['course_name'],
                'progress' => 65,
                'sessions_count' => 8,
                'start_date' => Carbon::now()->subMonths(1)->format('Y-m-d'),
                'end_date' => Carbon::now()->addMonths(2)->format('Y-m-d'),
            ]
        ];
    }

    public function generateTimeline()
    {
        // In a real app, we would fetch this from the database
        $this->timelineEvents = [
            [
                'date' => Carbon::parse($this->session['created_at'])->format('Y-m-d H:i:s'),
                'type' => 'created',
                'title' => 'Session Scheduled',
                'description' => 'Session was scheduled by ' . $this->teacher->name,
                'user' => $this->teacher->name,
                'icon' => 'o-calendar'
            ]
        ];

        // Add more events based on session status
        if ($this->session['status'] === 'confirmed') {
            $this->timelineEvents[] = [
                'date' => Carbon::parse($this->session['created_at'])->addHours(2)->format('Y-m-d H:i:s'),
                'type' => 'confirmed',
                'title' => 'Session Confirmed',
                'description' => 'Student confirmed attendance',
                'user' => $this->session['student_name'],
                'icon' => 'o-check-circle'
            ];
        }

        if ($this->session['status'] === 'completed') {
            $this->timelineEvents[] = [
                'date' => Carbon::parse($this->session['date'] . ' ' . $this->session['end_time'])->format('Y-m-d H:i:s'),
                'type' => 'completed',
                'title' => 'Session Completed',
                'description' => 'Session was marked as completed',
                'user' => $this->teacher->name,
                'icon' => 'o-check'
            ];

            $this->timelineEvents[] = [
                'date' => Carbon::parse($this->session['date'] . ' ' . $this->session['end_time'])->addHours(1)->format('Y-m-d H:i:s'),
                'type' => 'notes',
                'title' => 'Session Notes Added',
                'description' => 'Teacher added notes to the session',
                'user' => $this->teacher->name,
                'icon' => 'o-folder'
            ];
        }

        if ($this->session['status'] === 'cancelled') {
            $this->timelineEvents[] = [
                'date' => Carbon::parse($this->session['updated_at'])->format('Y-m-d H:i:s'),
                'type' => 'cancelled',
                'title' => 'Session Cancelled',
                'description' => 'Session was cancelled by ' . $this->teacher->name,
                'user' => $this->teacher->name,
                'icon' => 'o-x-circle'
            ];
        }

        // Sort events by date
        $this->timelineEvents = collect($this->timelineEvents)
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function saveSessionNotes()
    {
        $this->validate([
            'sessionNotes' => 'nullable|string',
            'attendanceStatus' => 'nullable|boolean',
            'performanceRating' => 'nullable|integer|min:1|max:5',
        ]);

        // In a real app, this would update the database

        $this->toast(
            type: 'success',
            title: 'Session details saved',
            description: 'Session notes, attendance, and rating have been updated.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );
    }

    public function addTopic()
    {
        if (!empty($this->newTopic)) {
            $this->sessionTopics[] = $this->newTopic;
            $this->newTopic = '';
        }
    }

    public function removeTopic($index)
    {
        if (isset($this->sessionTopics[$index])) {
            unset($this->sessionTopics[$index]);
            $this->sessionTopics = array_values($this->sessionTopics);
        }
    }

    public function openRescheduleModal()
    {
        $this->showRescheduleModal = true;
    }

    public function closeRescheduleModal()
    {
        $this->showRescheduleModal = false;
    }

    public function rescheduleSession()
    {
        $this->validate([
            'rescheduleDate' => 'required|date|after_or_equal:today',
            'rescheduleStartTime' => 'required',
            'rescheduleEndTime' => 'required|after:rescheduleStartTime',
        ]);

        // In a real app, this would update the database

        $this->toast(
            type: 'success',
            title: 'Session rescheduled',
            description: 'The session has been rescheduled successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-calendar',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeRescheduleModal();

        // Update the session data
        $this->session['date'] = $this->rescheduleDate;
        $this->session['start_time'] = $this->rescheduleStartTime;
        $this->session['end_time'] = $this->rescheduleEndTime;

        // Update timeline
        $this->timelineEvents[] = [
            'date' => now()->format('Y-m-d H:i:s'),
            'type' => 'rescheduled',
            'title' => 'Session Rescheduled',
            'description' => 'Session was rescheduled by ' . $this->teacher->name,
            'user' => $this->teacher->name,
            'icon' => 'o-calendar'
        ];

        $this->generateTimeline();
    }

    public function openCancelModal()
    {
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
    }

    public function cancelSession()
    {
        $this->validate([
            'cancellationReason' => 'required|string|min:5',
        ]);

        // In a real app, this would update the database

        $this->toast(
            type: 'warning',
            title: 'Session cancelled',
            description: 'The session has been cancelled successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-x-circle',
            css: 'alert-warning',
            timeout: 3000
        );

        $this->closeCancelModal();

        // Update the session data
        $this->session['status'] = 'cancelled';
        $this->sessionStatus = 'cancelled';

        // Update timeline
        $this->timelineEvents[] = [
            'date' => now()->format('Y-m-d H:i:s'),
            'type' => 'cancelled',
            'title' => 'Session Cancelled',
            'description' => 'Session was cancelled by ' . $this->teacher->name . ' - Reason: ' . $this->cancellationReason,
            'user' => $this->teacher->name,
            'icon' => 'o-x-circle'
        ];

        $this->generateTimeline();
    }

    public function openStartSessionModal()
    {
        $this->showStartSessionModal = true;
    }

    public function closeStartSessionModal()
    {
        $this->showStartSessionModal = false;
    }

    public function startSession()
    {
        // In a real app, this would update the database and redirect to a virtual classroom

        $this->toast(
            type: 'success',
            title: 'Session started',
            description: 'Redirecting to virtual classroom...',
            position: 'toast-bottom toast-end',
            icon: 'o-video-camera',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeStartSessionModal();

        // Update the session data
        $this->session['status'] = 'in_progress';
        $this->sessionStatus = 'in_progress';

        // Update timeline
        $this->timelineEvents[] = [
            'date' => now()->format('Y-m-d H:i:s'),
            'type' => 'started',
            'title' => 'Session Started',
            'description' => 'Session was started by ' . $this->teacher->name,
            'user' => $this->teacher->name,
            'icon' => 'o-play'
        ];

        $this->generateTimeline();
    }

    public function openCompleteSessionModal()
    {
        $this->showCompleteSessionModal = true;
    }

    public function closeCompleteSessionModal()
    {
        $this->showCompleteSessionModal = false;
    }

    public function completeSession()
    {
        $this->validate([
            'sessionNotes' => 'nullable|string',
            'attendanceStatus' => 'required|boolean',
            'performanceRating' => 'required|integer|min:1|max:5',
        ]);

        // In a real app, this would update the database

        $this->toast(
            type: 'success',
            title: 'Session completed',
            description: 'The session has been marked as completed.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeCompleteSessionModal();

        // Update the session data
        $this->session['status'] = 'completed';
        $this->sessionStatus = 'completed';
        $this->session['notes'] = $this->sessionNotes;
        $this->session['attended'] = $this->attendanceStatus;
        $this->session['performance_score'] = $this->performanceRating;

        // Update timeline
        $this->timelineEvents[] = [
            'date' => now()->format('Y-m-d H:i:s'),
            'type' => 'completed',
            'title' => 'Session Completed',
            'description' => 'Session was marked as completed by ' . $this->teacher->name,
            'user' => $this->teacher->name,
            'icon' => 'o-check'
        ];

        $this->generateTimeline();
    }

    public function openAddMaterialModal()
    {
        $this->showAddMaterialModal = true;
    }

    public function closeAddMaterialModal()
    {
        $this->showAddMaterialModal = false;
        $this->newMaterial = null;
        $this->materialName = '';
        $this->materialDescription = '';
    }

    public function addMaterial()
    {
        $this->validate([
            'materialName' => 'required|string|max:255',
            'materialDescription' => 'nullable|string',
            'newMaterial' => 'required|file|max:10240', // 10MB max
        ]);

        // In a real app, this would upload the file and update the database

        // Add to existing materials
        $fileExtension = $this->newMaterial->getClientOriginalExtension();
        $this->existingMaterials[] = [
            'id' => count($this->existingMaterials) + 1,
            'name' => $this->materialName,
            'description' => $this->materialDescription,
            'type' => $fileExtension,
            'url' => '#',
            'uploaded_at' => now()->format('Y-m-d H:i:s'),
        ];

        $this->toast(
            type: 'success',
            title: 'Material added',
            description: 'The material has been added successfully.',
            position: 'toast-bottom toast-end',
            icon: 'o-document-plus',
            css: 'alert-success',
            timeout: 3000
        );

        $this->closeAddMaterialModal();

        // Update timeline
        $this->timelineEvents[] = [
            'date' => now()->format('Y-m-d H:i:s'),
            'type' => 'material_added',
            'title' => 'Material Added',
            'description' => 'Material "' . $this->materialName . '" was added by ' . $this->teacher->name,
            'user' => $this->teacher->name,
            'icon' => 'o-document-plus'
        ];

        $this->generateTimeline();
    }

    public function removeMaterial($index)
    {
        // Show confirmation modal
        $this->confirmationAction = 'removeMaterialConfirmed';
        $this->confirmationMessage = 'Are you sure you want to remove this material? This action cannot be undone.';
        $this->confirmationData = $index;
        $this->showConfirmationModal = true;
    }

    public function removeMaterialConfirmed()
    {
        $index = $this->confirmationData;

        if (isset($this->existingMaterials[$index])) {
            // In a real app, this would delete the file and update the database

            $materialName = $this->existingMaterials[$index]['name'];

            // Remove from existing materials
            unset($this->existingMaterials[$index]);
            $this->existingMaterials = array_values($this->existingMaterials);

            $this->toast(
                type: 'success',
                title: 'Material removed',
                description: 'The material has been removed successfully.',
                position: 'toast-bottom toast-end',
                icon: 'o-trash',
                css: 'alert-success',
                timeout: 3000
            );

            // Update timeline
            $this->timelineEvents[] = [
                'date' => now()->format('Y-m-d H:i:s'),
                'type' => 'material_removed',
                'title' => 'Material Removed',
                'description' => 'Material "' . $materialName . '" was removed by ' . $this->teacher->name,
                'user' => $this->teacher->name,
                'icon' => 'o-trash'
            ];

            $this->generateTimeline();
        }

        $this->closeConfirmationModal();
    }

    public function closeConfirmationModal()
    {
        $this->showConfirmationModal = false;
        $this->confirmationAction = '';
        $this->confirmationMessage = '';
        $this->confirmationData = null;
    }

    public function confirmAction()
    {
        if (method_exists($this, $this->confirmationAction)) {
            $this->{$this->confirmationAction}();
        }

        $this->closeConfirmationModal();
    }

    public function addProgressItem()
    {
        if (!empty($this->newProgressItem)) {
            $this->progressItems[] = [
                'title' => $this->newProgressItem,
                'completed' => false
            ];
            $this->newProgressItem = '';
        }
    }

    public function removeProgressItem($index)
    {
        if (isset($this->progressItems[$index])) {
            unset($this->progressItems[$index]);
            $this->progressItems = array_values($this->progressItems);
        }
    }

    public function toggleProgressItem($index)
    {
        if (isset($this->progressItems[$index])) {
            $this->progressItems[$index]['completed'] = !$this->progressItems[$index]['completed'];
        }
    }

    public function saveProgressItems()
    {
        // In a real app, this would update the database

        $this->toast(
            type: 'success',
            title: 'Progress saved',
            description: 'Session progress has been updated.',
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            css: 'alert-success',
            timeout: 3000
        );
    }

    public function getProgressPercentageProperty()
    {
        $total = count($this->progressItems);
        if ($total === 0) {
            return 0;
        }

        $completed = collect($this->progressItems)->where('completed', true)->count();
        return round(($completed / $total) * 100);
    }

    public function formatDateTime($date, $time = null)
    {
        if ($time) {
            return Carbon::parse($date . ' ' . $time)->format('M d, Y h:i A');
        }
        return Carbon::parse($date)->format('M d, Y');
    }

    public function formatTime($time)
    {
        return Carbon::parse($time)->format('h:i A');
    }

    public function getRelativeTime($date, $time = null)
    {
        if ($time) {
            return Carbon::parse($date . ' ' . $time)->diffForHumans();
        }
        return Carbon::parse($date)->diffForHumans();
    }

    public function getDuration($startTime, $endTime)
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        $durationInMinutes = $start->diffInMinutes($end);

        $hours = floor($durationInMinutes / 60);
        $minutes = $durationInMinutes % 60;

        if ($hours > 0) {
            return $hours . 'h ' . ($minutes > 0 ? $minutes . 'm' : '');
        }

        return $minutes . 'm';
    }

    public function getMaterialIcon($type)
    {
        switch (strtolower($type)) {
            case 'pdf':
                return 'jio';
            case 'doc':
            case 'docx':
                return 'o-document';
            case 'xls':
            case 'xlsx':
                return 'o-table-cells';
            case 'ppt':
            case 'pptx':
                return 'o-presentation-chart-bar';
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                return 'o-photo';
            case 'zip':
            case 'rar':
                return 'o-archive-box';
            case 'mp4':
            case 'avi':
            case 'mov':
                return 'o-film';
            case 'mp3':
            case 'wav':
                return 'o-musical-note';
            default:
                return 'o-document';
        }
    }

    public function getStatusBadgeClass($status)
    {
        switch ($status) {
            case 'scheduled':
                return 'badge-info';
            case 'confirmed':
                return 'badge-primary';
            case 'in_progress':
                return 'badge-warning';
            case 'completed':
                return 'badge-success';
            case 'cancelled':
                return 'badge-error';
            default:
                return 'badge-ghost';
        }
    }

    public function getRecordingInfo()
    {
        if ($this->session['status'] === 'completed' && isset($this->session['recording_url'])) {
            return [
                'url' => $this->session['recording_url'],
                'duration' => '1h 30m',
                'size' => '125 MB',
                'created_at' => Carbon::parse($this->session['date'] . ' ' . $this->session['end_time'])->addMinutes(5)->format('Y-m-d H:i:s')
            ];
        }

        return null;
    }

    public function isSessionUpcoming()
    {
        $sessionDateTime = Carbon::parse($this->session['date'] . ' ' . $this->session['start_time']);
        return $sessionDateTime->isFuture();
    }

    public function isSessionInProgress()
    {
        $sessionStartDateTime = Carbon::parse($this->session['date'] . ' ' . $this->session['start_time']);
        $sessionEndDateTime = Carbon::parse($this->session['date'] . ' ' . $this->session['end_time']);
        $now = Carbon::now();

        return $now->between($sessionStartDateTime, $sessionEndDateTime);
    }

    public function isSessionPast()
    {
        $sessionEndDateTime = Carbon::parse($this->session['date'] . ' ' . $this->session['end_time']);
        return $sessionEndDateTime->isPast();
    }

    public function canStartSession()
    {
        $sessionStartDateTime = Carbon::parse($this->session['date'] . ' ' . $this->session['start_time']);
        $now = Carbon::now();

        return ($this->session['status'] === 'scheduled' || $this->session['status'] === 'confirmed') &&
               $now->diffInMinutes($sessionStartDateTime, false) < 15 && // 15 minutes before start time
               $now->diffInMinutes($sessionStartDateTime, false) > -120; // Up to 2 hours after start time
    }

    public function canCompleteSession()
    {
        return ($this->session['status'] === 'in_progress' || $this->isSessionPast()) &&
               $this->session['status'] !== 'completed' &&
               $this->session['status'] !== 'cancelled';
    }

    private function getMockSession($id)
    {
        $now = Carbon::now();
        $sessionDate = $now->copy()->addDays(1);
        $startTime = '10:00:00';
        $endTime = '12:00:00';

        // Simulate a session that is happening now
        if ($id == 5) {
            $sessionDate = $now->copy();
            $startTime = $now->copy()->subHours(1)->format('H:i:s');
            $endTime = $now->copy()->addHours(1)->format('H:i:s');
        }

        // Simulate a completed session
        if ($id == 3) {
            $sessionDate = $now->copy()->subDays(2);
            $status = 'completed';
            $attended = true;
            $performance_score = 4;
            $notes = 'Student showed excellent understanding of the concepts. Was able to complete all the exercises successfully.';
        } else {
            $status = 'scheduled';
            $attended = null;
            $performance_score = null;
            $notes = '';
        }

        return [
            'id' => $id,
            'title' => 'Advanced Laravel Development - Session #' . $id,
            'description' => 'In this session, we will explore advanced Laravel concepts including middleware, service containers, and more. We will focus on practical examples and exercises.',
            'student_id' => 1,
            'student_name' => 'Alex Johnson',
            'course_id' => 1,
            'course_name' => 'Advanced Laravel Development',
            'date' => $sessionDate->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_hours' => 2,
            'status' => $status,
            'attended' => $attended,
            'performance_score' => $performance_score,
            'notes' => $notes,
            'student_feedback' => $id == 3 ? 'Great session! I learned a lot and enjoyed the practical exercises.' : '',
            'topics_covered' => [
                'Middleware',
                'Service Containers',
                'Dependency Injection',
                'Event Broadcasting'
            ],
            'materials' => [
                [
                    'id' => 1,
                    'name' => 'Lesson Slides',
                    'description' => 'Slides covering the main concepts discussed in this session',
                    'type' => 'pdf',
                    'url' => '#',
                    'uploaded_at' => Carbon::now()->subDays(1)->format('Y-m-d H:i:s')
                ],
                [
                    'id' => 2,
                    'name' => 'Exercise Files',
                    'description' => 'Practical exercises to reinforce the concepts',
                    'type' => 'zip',
                    'url' => '#',
                    'uploaded_at' => Carbon::now()->subDays(1)->format('Y-m-d H:i:s')
                ]
            ],
            'progress_items' => $id == 3 ? [
                ['title' => 'Introduction and review of previous concepts', 'completed' => true],
                ['title' => 'Explanation of new topics', 'completed' => true],
                ['title' => 'Practical exercises', 'completed' => true],
                ['title' => 'Questions and answers', 'completed' => true],
                ['title' => 'Homework assignment', 'completed' => true]
            ] : null,
            'created_at' => Carbon::now()->subDays(7)->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'recording_url' => $id == 3 ? 'https://example.com/recordings/session-' . $id : null
        ];
    }

    private function getMockRelatedSessions()
    {
        $now = Carbon::now();
        $sessions = [];

        // Add a few past sessions
        for ($i = 1; $i < 3; $i++) {
            $sessions[] = [
                'id' => $i,
                'title' => 'Advanced Laravel Development - Session #' . $i,
                'date' => $now->copy()->subDays($i * 3)->format('Y-m-d'),
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'status' => 'completed'
            ];
        }

        // Add current session
        $sessions[] = [
            'id' => $this->session['id'],
            'title' => $this->session['title'],
            'date' => $this->session['date'],
            'start_time' => $this->session['start_time'],
            'end_time' => $this->session['end_time'],
            'status' => $this->session['status']
        ];

        // Add a few upcoming sessions
        for ($i = 1; $i < 3; $i++) {
            $sessions[] = [
                'id' => $this->session['id'] + $i,
                'title' => 'Advanced Laravel Development - Session #' . ($this->session['id'] + $i),
                'date' => $now->copy()->addDays($i * 3)->format('Y-m-d'),
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'status' => 'scheduled'
            ];
        }

        return $sessions;
    }

    // Toast helper function
    public function toast($type, $title, $description, $position = 'toast-top toast-end', $icon = null, $css = null, $timeout = 5000)
    {
        $this->dispatch('toast', [
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'position' => $position,
            'icon' => $icon,
            'css' => $css,
            'timeout' => $timeout
        ]);
    }
};
?>

                    <div>
                        <div>
                            <!-- Session Header -->
                            <div class="p-4 mb-6 bg-white rounded-lg shadow-sm">
                                <div class="flex flex-col items-start justify-between mb-4 md:flex-row md:items-center">
                                    <div>
                                        <h1 class="mb-1 text-2xl font-semibold">{{ $session['title'] }}</h1>
                                        <p class="text-gray-600">{{ $this->formatDateTime($session['date'], $session['start_time']) }} - {{ $this->formatTime($session['end_time']) }}</p>
                                    </div>

                                    <div class="flex flex-wrap gap-2 mt-4 md:mt-0">
                                        @if ($session['status'] !== 'cancelled')
                                            @if ($this->canStartSession())
                                            <button wire:click="openStartSessionModal" class="gap-2 btn btn-primary">
                                                <span class="w-5 h-5">📹</span> <!-- Using an emoji instead -->
                                                Start Session
                                            </button>
                                            @endif

                                            @if ($this->canCompleteSession())
                                                <button wire:click="openCompleteSessionModal" class="gap-2 btn btn-success">
                                                    <x-icon name="o-check-circle" class="w-5 h-5" />
                                                    Mark Completed
                                                </button>
                                            @endif

                                            @if ($this->isSessionUpcoming() && $session['status'] !== 'completed')
                                                <div class="dropdown dropdown-end">
                                                    <button class="btn btn-ghost">
                                                        <x-icon name="o-ellipsis-vertical" class="w-5 h-5" />
                                                    </button>
                                                    <ul class="z-10 p-2 shadow dropdown-content menu bg-base-100 rounded-box w-52">
                                                        <li>
                                                            <button wire:click="openRescheduleModal">
                                                                <x-icon name="o-calendar" class="w-5 h-5" />
                                                                Reschedule
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button wire:click="openCancelModal">
                                                                <x-icon name="o-x-circle" class="w-5 h-5" />
                                                                Cancel
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-3 mb-4">
                                    <span class="badge {{ $this->getStatusBadgeClass($session['status']) }} gap-1">
                                        {{ ucfirst(str_replace('_', ' ', $session['status'])) }}
                                    </span>

                                    <span class="gap-1 badge badge-outline">
                                        <x-icon name="o-clock" class="w-4 h-4" />
{{ $this->getDuration($session['start_time'], $session['end_time']) }}                                    </span>

                                    <span class="gap-1 badge badge-outline">
                                        <x-icon name="o-academic-cap" class="w-4 h-4" />
                                        {{ $session['course_name'] }}
                                    </span>
                                </div>

                                <p class="text-gray-600">{{ $session['description'] }}</p>
                            </div>

                            <!-- Tab Navigation -->
                            <div class="mb-6 tabs tabs-bordered">
                                <button wire:click="setActiveTab('overview')" class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}">
                                    <x-icon name="o-home" class="w-5 h-5 mr-2" />
                                    Overview
                                </button>
                                <button wire:click="setActiveTab('materials')" class="tab {{ $activeTab === 'materials' ? 'tab-active' : '' }}">
                                    <x-icon name="o-document-text" class="w-5 h-5 mr-2" />
                                    Materials
                                </button>
                                <button wire:click="setActiveTab('progress')" class="tab {{ $activeTab === 'progress' ? 'tab-active' : '' }}">
                                    <x-icon name="o-check-circle" class="w-5 h-5 mr-2" />
                                    Progress
                                </button>
                                <button wire:click="setActiveTab('timeline')" class="tab {{ $activeTab === 'timeline' ? 'tab-active' : '' }}">
                                    <x-icon name="o-clock" class="w-5 h-5 mr-2" />
                                    Timeline
                                </button>
                            </div>

                            <!-- Tab Content -->
                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                                <!-- Main Content Area -->
                                <div class="lg:col-span-3">
                                    @if ($activeTab === 'overview')
                                        <div class="p-6 mb-6 bg-white rounded-lg shadow-sm">
                                            <h2 class="mb-4 text-xl font-semibold">Session Notes</h2>

                                            <div class="mb-4 form-control">
                                                <textarea
                                                    wire:model="sessionNotes"
                                                    class="h-32 textarea textarea-bordered"
                                                    placeholder="Enter session notes here..."
                                                ></textarea>
                                            </div>

                                            <div class="grid grid-cols-1 gap-6 mb-4 md:grid-cols-2">
                                                <div>
                                                    <h3 class="mb-2 text-lg font-medium">Attendance</h3>
                                                    <div class="flex items-center space-x-4">
                                                        <label class="flex items-center cursor-pointer">
                                                            <input
                                                                type="radio"
                                                                wire:model="attendanceStatus"
                                                                value="1"
                                                                class="radio radio-primary"
                                                            />
                                                            <span class="ml-2">Attended</span>
                                                        </label>
                                                        <label class="flex items-center cursor-pointer">
                                                            <input
                                                                type="radio"
                                                                wire:model="attendanceStatus"
                                                                value="0"
                                                                class="radio radio-primary"
                                                            />
                                                            <span class="ml-2">Missed</span>
                                                        </label>
                                                    </div>
                                                </div>

                                                <div>
                                                    <h3 class="mb-2 text-lg font-medium">Performance Rating</h3>
                                                    <div class="rating">
                                                        @for ($i = 1; $i <= 5; $i++)
                                                            <input
                                                                type="radio"
                                                                wire:model="performanceRating"
                                                                value="{{ $i }}"
                                                                class="bg-orange-400 mask mask-star-2"
                                                            />
                                                        @endfor
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex justify-end">
                                                <button wire:click="saveSessionNotes" class="btn btn-primary">
                                                    Save Notes
                                                </button>
                                            </div>
                                        </div>

                                        <div class="p-6 mb-6 bg-white rounded-lg shadow-sm">
                                            <h2 class="mb-4 text-xl font-semibold">Topics Covered</h2>

                                            @if (count($sessionTopics) > 0)
                                                <div class="flex flex-wrap gap-2 mb-4">
                                                    @foreach ($sessionTopics as $index => $topic)
                                                        <div class="gap-1 badge badge-lg">
                                                            {{ $topic }}
                                                            <button wire:click="removeTopic({{ $index }})" class="ml-1">
                                                                <x-icon name="o-x-mark" class="w-3 h-3" />
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="mb-4 text-gray-500">No topics have been added yet.</p>
                                            @endif

                                            <div class="flex gap-2">
                                                <input
                                                    wire:model="newTopic"
                                                    wire:keydown.enter="addTopic"
                                                    class="flex-1 input input-bordered"
                                                    placeholder="Add a new topic"
                                                />
                                                <button wire:click="addTopic" class="btn btn-primary">
                                                    Add
                                                </button>
                                            </div>
                                        </div>

                                        <div class="p-6 mb-6 bg-white rounded-lg shadow-sm">
                                            <h2 class="mb-4 text-xl font-semibold">Student Feedback</h2>

                                            @if (!empty($sessionFeedback))
                                                <div class="p-4 mb-4 border border-gray-200 rounded-lg bg-gray-50">
                                                    <p class="text-gray-700">{{ $sessionFeedback }}</p>
                                                </div>
                                            @else
                                                <p class="text-gray-500">No feedback has been provided yet.</p>
                                            @endif
                                        </div>

                                        @if ($this->getRecordingInfo())
                                            <div class="p-6 mb-6 bg-white rounded-lg shadow-sm">
                                                <h2 class="mb-4 text-xl font-semibold">Session Recording</h2>

                                                <div class="flex flex-col items-start justify-between p-4 border border-gray-200 rounded-lg sm:flex-row sm:items-center bg-gray-50">
                                                    <div>
                                                        <h3 class="font-medium">Recording {{ $session['id'] }}</h3>
                                                        <p class="text-sm text-gray-500">{{ $this->getRecordingInfo()['duration'] }} • {{ $this->getRecordingInfo()['size'] }}</p>                                                    </div>

                                                    <div class="mt-3 sm:mt-0">
                                                        <a href="{{ $this->getRecordingInfo()['url'] }}" target="_blank" class="gap-1 btn btn-sm btn-primary">
                                                            Watch
                                                        </a>
                                                        <a href="{{ $getRecordingInfo()['url'] }}" download class="gap-1 btn btn-sm btn-ghost">
                                                            <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                                            Download
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <!-- Related Sessions -->
                                        @if (count($relatedSessions) > 0)
                                            <div class="p-6 mb-6 bg-white rounded-lg shadow-sm">
                                                <h2 class="mb-4 text-xl font-semibold">Related Sessions</h2>

                                                <div class="space-y-4">
                                                    @foreach ($relatedSessions as $relatedSession)
                                                        <div class="flex flex-col justify-between p-4 border border-gray-200 rounded-lg sm:flex-row sm:items-center bg-gray-50">
                                                            <div>
                                                                <h3 class="font-medium">{{ $relatedSession['title'] }}</h3>
                                                                <p class="text-sm text-gray-500">
                                                                    {{ $this->formatDateTime($relatedSession['date'], $relatedSession['start_time']) }}
                                                                    • {{$this->getDuration($relatedSession['start_time'], $relatedSession['end_time']) }}
                                                                </p>
                                                            </div>

                                                            <div class="flex items-center mt-3 sm:mt-0">
                                                                <span class="badge {{ $this->getStatusBadgeClass($relatedSession['status']) }} mr-2">
                                                                    {{ ucfirst(str_replace('_', ' ', $relatedSession['status'])) }}
                                                                </span>
                                                                <a href="{{ route('teachers.sessions.show', $relatedSession['id']) }}" class="btn btn-sm btn-ghost">
                                                                    View
                                                                </a>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endif

                                    @if ($activeTab === 'materials')
                                        <div class="p-6 bg-white rounded-lg shadow-sm">
                                            <div class="flex items-center justify-between mb-6">
                                                <h2 class="text-xl font-semibold">Session Materials</h2>
                                                <button wire:click="openAddMaterialModal" class="gap-1 btn btn-primary">
                                                    <x-icon name="o-plus" class="w-5 h-5" />
                                                    Add Material
                                                </button>
                                            </div>

                                            @if (count($existingMaterials) > 0)
                                                <div class="space-y-4">
                                                    @foreach ($existingMaterials as $index => $material)
                                                        <div class="flex flex-col justify-between p-4 border border-gray-200 rounded-lg sm:flex-row sm:items-center bg-gray-50">
                                                            <div class="flex items-start">
                                                                <div class="p-3 mr-4 rounded-lg bg-primary/10">
                                                                    <span class="w-6 h-6 text-primary">{{ $this->getMaterialIcon($material['type']) }}</span>

                                                                </div>
                                                                <div>
                                                                    <h3 class="font-medium">{{ $material['name'] }}</h3>
                                                                    <p class="text-sm text-gray-500">
                                                                        {{ ucfirst($material['type']) }} • {{ $this->getRelativeTime($material['uploaded_at']) }}
                                                                    </p>
                                                                    @if (!empty($material['description']))
                                                                        <p class="mt-1 text-gray-600">{{ $material['description'] }}</p>
                                                                    @endif
                                                                </div>
                                                            </div>

                                                            <div class="flex mt-3 sm:mt-0">
                                                                <a href="{{ $material['url'] }}" target="_blank" class="gap-1 mr-1 btn btn-sm btn-ghost">
                                                                    <x-icon name="o-eye" class="w-4 h-4" />
                                                                    View
                                                                </a>
                                                                <a href="{{ $material['url'] }}" download class="gap-1 mr-1 btn btn-sm btn-ghost">
                                                                    <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                                                                    Download
                                                                </a>
                                                                <button wire:click="removeMaterial({{ $index }})" class="gap-1 btn btn-sm btn-ghost text-error">
                                                                    <x-icon name="o-trash" class="w-4 h-4" />
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="py-12 text-center">
                                                    <div class="inline-flex p-4 mb-4 bg-gray-100 rounded-full">
                                                        <x-icon name="o-eye" class="w-8 h-8 text-gray-500" />
                                                    </div>
                                                    <h3 class="mb-2 text-lg font-medium">No materials yet</h3>
                                                    <p class="mb-4 text-gray-500">Upload course materials, worksheets, or resources for your student.</p>
                                                    <button wire:click="openAddMaterialModal" class="gap-1 btn btn-primary">
                                                        <x-icon name="o-plus" class="w-5 h-5" />
                                                        Add Material
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($activeTab === 'progress')
                                        <div class="p-6 bg-white rounded-lg shadow-sm">
                                            <div class="flex flex-col items-start justify-between mb-6 sm:flex-row sm:items-center">
                                                <div>
                                                    <h2 class="text-xl font-semibold">Session Progress</h2>
                                                    <p class="text-gray-600">Track progress through planned activities</p>
                                                </div>
                                                <div class="mt-2 sm:mt-0">
                                                    <span class="text-lg font-semibold">{{ $this->progressPercentage }}%</span>
                                                    <div class="w-40 h-3 mt-1 bg-gray-200 rounded-full">
                                                        <div class="h-3 rounded-full bg-primary" style="width: {{ $this->progressPercentage }}%"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-6 space-y-4">
                                                @foreach ($progressItems as $index => $item)
                                                    <div class="flex items-start">
                                                        <div class="form-control">
                                                            <label class="flex items-center cursor-pointer">
                                                                <input
                                                                    type="checkbox"
                                                                    wire:model="progressItems.{{ $index }}.completed"
                                                                    wire:change="saveProgressItems"
                                                                    class="mr-3 checkbox checkbox-primary"
                                                                />
                                                                <span class="{{ $item['completed'] ? 'line-through text-gray-500' : 'text-gray-800' }}">
                                                                    {{ $item['title'] }}
                                                                </span>
                                                            </label>
                                                        </div>
                                                        <button wire:click="removeProgressItem({{ $index }})" class="ml-auto btn btn-ghost btn-xs text-error">
                                                            <x-icon name="o-trash" class="w-4 h-4" />
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="flex gap-2">
                                                <input
                                                    wire:model="newProgressItem"
                                                    wire:keydown.enter="addProgressItem"
                                                    class="flex-1 input input-bordered"
                                                    placeholder="Add a new progress item"
                                                />
                                                <button wire:click="addProgressItem" class="btn btn-primary">
                                                    Add
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($activeTab === 'timeline')
                                        <div class="p-6 bg-white rounded-lg shadow-sm">
                                            <h2 class="mb-6 text-xl font-semibold">Session Timeline</h2>

                                            @if (count($timelineEvents) > 0)
                                                <ol class="relative ml-3 border-l border-gray-200">
                                                    @foreach ($timelineEvents as $event)
                                                        <li class="mb-6 ml-6">
                                                            <span class="absolute flex items-center justify-center w-6 h-6 rounded-full -left-3 bg-primary/10">
                                                                <span class="w-3 h-3 text-primary">📄</span>
                                                            </span>
                                                            <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                                                                <div class="flex flex-col justify-between mb-1 sm:flex-row sm:items-center">
                                                                    <h3 class="text-lg font-semibold">{{ $event['title'] }}</h3>
                                                                    <time class="text-sm text-gray-500">{{ $this->getRelativeTime($event['date']) }}</time>                                                                </div>
                                                                <p class="text-gray-600">{{ $event['description'] }}</p>
                                                                <p class="mt-1 text-sm text-gray-500">By {{ $event['user'] }}</p>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ol>
                                            @else
                                                <p class="py-6 text-center text-gray-500">No timeline events found.</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <!-- Sidebar -->
                                <div class="lg:col-span-1">
                                    <!-- Student Information -->
                                    <div class="p-6 mb-6 bg-white rounded-lg shadow-sm">
                                        <h2 class="mb-4 text-lg font-semibold">Student Information</h2>

                                        <div class="flex items-center mb-4">
                                            <div class="mr-4 avatar placeholder">
                                                <div class="w-12 rounded-full bg-neutral text-neutral-content">
                                                    <span>{{ substr($student['name'], 0, 1) }}</span>
                                                </div>
                                            </div>
                                            <div>
                                                <h3 class="font-medium">{{ $student['name'] }}</h3>
                                                <p class="text-sm text-gray-500">{{ $student['email'] }}</p>
                                            </div>
                                        </div>

                                        <div class="my-2 divider"></div>

                                        <div class="space-y-3">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Grade Level:</span>
                                                <span class="font-medium">{{ $student['grade'] }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Last Session:</span>
                                                <span class="font-medium">{{ $this->formatDateTime($student['last_session_at']) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Total Sessions:</span>
                                                <span class="font-medium">{{ $student['total_sessions'] }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Attendance:</span>
                                                <span class="font-medium">{{ $student['attendance_rate'] }}%</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Avg. Performance:</span>
                                                <div class="rating rating-sm">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <input
                                                            type="radio"
                                                            class="bg-orange-400 mask mask-star-2"
                                                            disabled
                                                            {{ $i <= round($student['performance_avg']) ? 'checked' : '' }}
                                                        />
                                                    @endfor
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <a href="{{ route('teachers.students.show', $student['id']) }}" class="btn btn-outline btn-block">
                                                View Full Profile
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Course Information -->
                                    @if (count($studentCourses) > 0)
                                        <div class="p-6 mb-6 bg-white rounded-lg shadow-sm">
                                            <h2 class="mb-4 text-lg font-semibold">Course Information</h2>

                                            @foreach ($studentCourses as $course)
                                                <div class="mb-4">
                                                    <h3 class="mb-1 font-medium">{{ $course['name'] }}</h3>

                                                    <div class="flex justify-between mb-1 text-sm text-gray-600">
                                                        <span>Progress</span>
                                                        <span>{{ $course['progress'] }}%</span>
                                                    </div>
                                                    <div class="w-full h-2 bg-gray-200 rounded-full">
                                                        <div class="h-2 rounded-full bg-primary" style="width: {{ $course['progress'] }}%"></div>
                                                    </div>

                                                    <div class="mt-3 space-y-2 text-sm">
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-600">Sessions Completed:</span>
                                                            <span class="font-medium">{{ $course['sessions_count'] }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-600">Start Date:</span>
                                                            <span class="font-medium">{{ $this->formatDateTime($course['start_date']) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-600">End Date:</span>
                                                            <span class="font-medium">{{ $this->formatDateTime($course['end_date']) }}</span>
                                                        </div>
                                                    </div>

                                                    <div class="mt-3">
                                                        <a href="{{ route('teachers.courses.show', $course['id']) }}" class="btn btn-sm btn-ghost">
                                                            View Course
                                                        </a>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Modals -->
                            <!-- Reschedule Modal -->
                            <x-modal wire:model="showRescheduleModal">
                                <div class="p-6">
                                    <h3 class="mb-4 text-lg font-semibold">Reschedule Session</h3>

                                    <div class="mb-4 form-control">
                                        <label class="label">
                                            <span class="label-text">Date</span>
                                        </label>
                                        <input
                                            type="date"
                                            wire:model="rescheduleDate"
                                            class="input input-bordered"
                                        />
                                        @error('rescheduleDate')
                                            <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                                        @enderror
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">Start Time</span>
                                            </label>
                                            <input
                                                type="time"
                                                wire:model="rescheduleStartTime"
                                                class="input input-bordered"
                                            />
                                            @error('rescheduleStartTime')
                                                <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                                            @enderror
                                        </div>

                                        <div class="form-control">
                                            <label class="label">
                                                <span class="label-text">End Time</span>
                                            </label>
                                            <input
                                                type="time"
                                                wire:model="rescheduleEndTime"
                                                class="input input-bordered"
                                            />
                                            @error('rescheduleEndTime')
                                                <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <button wire:click="closeRescheduleModal" class="btn btn-ghost">
                                            Cancel
                                        </button>
                                        <button wire:click="rescheduleSession" class="btn btn-primary">
                                            Reschedule
                                        </button>
                                    </div>
                                </div>
                            </x-modal>

                            <!-- Cancel Modal -->
                            <x-modal wire:model="showCancelModal">
                                <div class="p-6">
                                    <h3 class="mb-4 text-lg font-semibold">Cancel Session</h3>

                                    <div class="mb-4 form-control">
                                        <label class="label">
                                            <span class="label-text">Reason for Cancellation</span>
                                        </label>
                                        <textarea
                                            wire:model="cancellationReason"
                                            class="h-24 textarea textarea-bordered"
                                            placeholder="Please provide a reason for cancelling this session..."
                                        ></textarea>
                                        @error('cancellationReason')
                                            <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                                        @enderror
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <button wire:click="closeCancelModal" class="btn btn-ghost">
                                            Go Back
                                        </button>
                                        <button wire:click="cancelSession" class="btn btn-error">
                                            Cancel Session
                                        </button>
                                    </div>
                                </div>
                            </x-modal>

                            <!-- Start Session Modal -->
                            <x-modal wire:model="showStartSessionModal">
                                <div class="p-6">
                                    <h3 class="mb-4 text-lg font-semibold">Start Session</h3>

                                    <p class="mb-4">
                                        You are about to start the virtual classroom for your session with {{ $student['name'] }}.
                                        This will notify the student that the session is starting.
                                    </p>

                                    <div class="p-4 mb-4 border-l-4 border-yellow-400 bg-yellow-50">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-yellow-400" />
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-700">
                                                    Make sure your camera and microphone are working properly before joining.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <button wire:click="closeStartSessionModal" class="btn btn-ghost">
                                            Cancel
                                        </button>
                                        <button wire:click="startSession" class="gap-1 btn btn-primary">
                                            <x-icon name="o-video-camera" class="w-4 h-4" />
                                            Join Classroom
                                        </button>
                                    </div>
                                </div>
                            </x-modal>

                            <!-- Complete Session Modal -->
                            <x-modal wire:model="showCompleteSessionModal">
                                <div class="p-6">
                                    <h3 class="mb-4 text-lg font-semibold">Complete Session</h3>

                                    <p class="mb-4">
                                        You are about to mark this session as completed. Please fill in the following details:
                                    </p>

                                    <div class="mb-4 form-control">
                                        <label class="label">
                                            <span class="label-text">Attendance</span>
                                        </label>
                                        <div class="flex items-center space-x-4">
                                            <label class="flex items-center cursor-pointer">
                                                <input
                                                    type="radio"
                                                    wire:model="attendanceStatus"
                                                    value="1"
                                                    class="radio radio-primary"
                                                />
                                                <span class="ml-2">Attended</span>
                                            </label>
                                            <label class="flex items-center cursor-pointer">

                                    <input
                                        type="radio"
                                        wire:model="attendanceStatus"
                                        value="1"
                                        class="radio radio-primary"
                                    />
                                    <span class="ml-2">Attended</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input
                                    type="radio"
                                    wire:model="attendanceStatus"
                                    value="0"
                                    class="radio radio-primary"
                                />
                                <span class="ml-2">Missed</span>
                            </label>
                        </div>
                        @error('attendanceStatus')
                            <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                        @enderror
                    </div>

                    <div class="mb-4 form-control">
                        <label class="label">
                            <span class="label-text">Performance Rating</span>
                        </label>
                        <div class="rating">
                            @for ($i = 1; $i <= 5; $i++)
                                <input
                                    type="radio"
                                    wire:model="performanceRating"
                                    value="{{ $i }}"
                                    class="bg-orange-400 mask mask-star-2"
                                />
                            @endfor
                        </div>
                        @error('performanceRating')
                            <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                        @enderror
                    </div>

                    <div class="mb-4 form-control">
                        <label class="label">
                            <span class="label-text">Session Notes</span>
                        </label>
                        <textarea
                            wire:model="sessionNotes"
                            class="h-24 textarea textarea-bordered"
                            placeholder="Enter your notes about this session..."
                        ></textarea>
                        @error('sessionNotes')
                            <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button wire:click="closeCompleteSessionModal" class="btn btn-ghost">
                            Cancel
                        </button>
                        <button wire:click="completeSession" class="gap-1 btn btn-success">
                            <x-icon name="o-check-circle" class="w-4 h-4" />
                            Complete
                        </button>
                    </div>
                </div>
            </x-modal>

            <!-- Add Material Modal -->
            <x-modal wire:model="showAddMaterialModal">
                <div class="p-6">
                    <h3 class="mb-4 text-lg font-semibold">Add Material</h3>

                    <div class="mb-4 form-control">
                        <label class="label">
                            <span class="label-text">Material Name</span>
                        </label>
                        <input
                            type="text"
                            wire:model="materialName"
                            class="input input-bordered"
                            placeholder="Enter material name"
                        />
                        @error('materialName')
                            <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                        @enderror
                    </div>

                    <div class="mb-4 form-control">
                        <label class="label">
                            <span class="label-text">Description (Optional)</span>
                        </label>
                        <textarea
                            wire:model="materialDescription"
                            class="h-20 textarea textarea-bordered"
                            placeholder="Enter a brief description of this material..."
                        ></textarea>
                        @error('materialDescription')
                            <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                        @enderror
                    </div>

                    <div class="mb-4 form-control">
                        <label class="label">
                            <span class="label-text">File</span>
                        </label>
                        <div class="p-6 text-center border border-gray-300 border-dashed rounded-lg">
                            @if ($newMaterial)
                                <div class="flex items-center justify-center space-x-2">
                                    <span class="w-6 h-6 text-primary">{{ ucfirst($material['type']) }}</span>

                                    <span>{{ $newMaterial->getClientOriginalName() }}</span>
                                    <button wire:click="$set('newMaterial', null)" class="btn btn-ghost btn-xs">
                                        <x-icon name="o-x-mark" class="w-4 h-4" />
                                    </button>
                                </div>
                            @else
                                <label class="flex flex-col items-center cursor-pointer">
                                    <x-icon name="o-arrow-up-tray" class="w-8 h-8 text-gray-400" />
                                    <span class="mt-2 text-sm text-gray-500">Click to upload or drag and drop</span>
                                    <span class="mt-1 text-xs text-gray-400">PDF, DOC, PPT, XLS, JPG, PNG, MP4 (Max. 10MB)</span>
                                    <input type="file" wire:model="newMaterial" class="hidden" />
                                </label>
                            @endif
                        </div>
                        <div wire:loading wire:target="newMaterial" class="mt-2 text-sm text-center text-gray-500">
                            Uploading...
                        </div>
                        @error('newMaterial')
                            <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button wire:click="closeAddMaterialModal" class="btn btn-ghost">
                            Cancel
                        </button>
                        <button wire:click="addMaterial" class="btn btn-primary" wire:loading.attr="disabled" wire:target="newMaterial">
                            Add Material
                        </button>
                    </div>
                </div>
            </x-modal>

            <!-- Confirmation Modal -->
            <x-modal wire:model="showConfirmationModal">
                <div class="p-6">
                    <h3 class="mb-4 text-lg font-semibold">Confirm Action</h3>

                    <p class="mb-6">{{ $confirmationMessage }}</p>

                    <div class="flex justify-end gap-2">
                        <button wire:click="closeConfirmationModal" class="btn btn-ghost">
                            Cancel
                        </button>
                        <button wire:click="confirmAction" class="btn btn-error">
                            Confirm
                        </button>
                    </div>
                </div>
            </x-modal>

            <!-- Toast Notifications Container -->
            <div
                x-data="{
                    toasts: [],
                    add(toast) {
                        this.toasts.push(toast);
                        setTimeout(() => {
                            this.remove(toast);
                        }, toast.timeout || 5000);
                    },
                    remove(toastToRemove) {
                        this.toasts = this.toasts.filter(toast => toast !== toastToRemove);
                    }
                }"
                x-on:toast.window="add($event.detail)"
                class="fixed inset-0 z-50 flex flex-col-reverse items-end justify-start p-4 space-y-4 space-y-reverse overflow-hidden pointer-events-none"
            >
                <template x-for="(toast, index) in toasts" :key="index">
                    <div
                        x-transition:enter="transform ease-out duration-300 transition"
                        x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                        x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        :class="toast.css || 'alert-info'"
                        class="flex items-start max-w-md mb-3 shadow-lg pointer-events-auto alert"
                    >
                        <div class="flex-1">
                            <!-- Icon -->
                            <i x-show="toast.icon" :class="toast.icon"></i>

                            <!-- Content -->
                            <div>
                                <h3 x-text="toast.title" class="font-bold"></h3>
                                <div x-text="toast.description" class="text-sm"></div>
                            </div>
                        </div>

                        <!-- Close button -->
                        <button
                            @click="remove(toast)"
                            class="btn btn-sm btn-ghost"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>
