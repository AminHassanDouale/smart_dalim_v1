<?php

namespace App\Livewire\Parents\Progress;

use Livewire\Volt\Component;
use App\Models\Children;
use App\Models\LearningSession;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    // User and profile data
    public $user;
    public $parentProfile;

    // Child data
    public $child;
    public $childId;

    // Time range filter
    public $timeRange = 'last_3_months'; // Options: 'last_month', 'last_3_months', 'last_6_months', 'last_year', 'custom'
    public $customStartDate = null;
    public $customEndDate = null;

    // Subject filter
    public $selectedSubject = null;
    public $availableSubjects = [];

    // Active section tabs
    public $activeTab = 'overview'; // 'overview', 'subjects', 'skills', 'attendance', 'performance'
    public $activeSubTab = null;

    // Progress data
    public $overallProgress = 0;
    public $attendanceRate = 0;
    public $assessmentScores = [];
    public $subjectProgress = [];
    public $skillMastery = [];
    public $strengthsWeaknesses = [];
    public $learningGoals = [];
    public $teacherFeedback = [];
    public $progressTrend = [];
    public $sessionHistory = [];
    public $upcomingSessions = [];
    public $recentAssessments = [];

    // Subject specific data
    public $selectedSubjectData = null;

    // Comparison data
    public $comparisonData = [
        'averageScore' => 0,
        'classAverage' => 0,
        'percentile' => 0,
        'rank' => 0
    ];

    // Performance metrics
    public $performanceMetrics = [
        'punctuality' => 0,
        'participation' => 0,
        'completion' => 0,
        'attentiveness' => 0,
        'collaboration' => 0
    ];

    // Chart data
    public $chartData = [
        'subjects' => [],
        'attendance' => [],
        'assessments' => [],
        'progressTrend' => [],
        'skills' => [],
        'subjectBreakdown' => []
    ];

    // Modal states
    public $showSessionDetailsModal = false;
    public $showAssessmentDetailsModal = false;
    public $showSkillDetailsModal = false;
    public $showGoalDetailsModal = false;
    public $showFeedbackModal = false;

    // Selected item for modals
    public $selectedSession = null;
    public $selectedAssessment = null;
    public $selectedSkill = null;
    public $selectedGoal = null;
    public $selectedFeedback = null;

    // Print/Export options
    public $reportType = 'comprehensive'; // 'comprehensive', 'academic', 'behavioral', 'attendance'

    public function mount($child)
    {
        $this->childId = $child;
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        if (!$this->parentProfile) {
            return redirect()->route('parents.profile-setup');
        }

        // Load child data with relationships
        $this->loadChild();

        // Set date range defaults
        $this->customStartDate = Carbon::now()->subMonths(3)->format('Y-m-d');
        $this->customEndDate = Carbon::now()->format('Y-m-d');

        // Load data
        $this->loadSubjects();
        $this->loadProgressData();
    }

    private function loadChild()
    {
        $this->child = Children::where('id', $this->childId)
            ->where('parent_profile_id', $this->parentProfile->id)
            ->with(['subjects', 'learningSessions.subject', 'learningSessions.teacher',
                   'assessmentSubmissions.assessment', 'teacher'])
            ->firstOrFail();
    }

    private function loadSubjects()
    {
        $this->availableSubjects = $this->child->subjects->toArray();
    }

    private function getDateRange()
    {
        $endDate = Carbon::now();

        switch ($this->timeRange) {
            case 'last_month':
                $startDate = Carbon::now()->subMonth();
                break;
            case 'last_3_months':
                $startDate = Carbon::now()->subMonths(3);
                break;
            case 'last_6_months':
                $startDate = Carbon::now()->subMonths(6);
                break;
            case 'last_year':
                $startDate = Carbon::now()->subYear();
                break;
            case 'custom':
                $startDate = Carbon::parse($this->customStartDate);
                $endDate = Carbon::parse($this->customEndDate);
                break;
            default:
                $startDate = Carbon::now()->subMonths(3);
        }

        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }

    private function loadProgressData()
    {
        // Filter data based on date range and subject
        $dateRange = $this->getDateRange();

        // Filter sessions
        $sessions = $this->child->learningSessions->filter(function($session) use ($dateRange) {
            $sessionDate = Carbon::parse($session->start_time);
            $inDateRange = $sessionDate->between($dateRange['start'], $dateRange['end']);

            if ($this->selectedSubject && $inDateRange) {
                return $session->subject_id == $this->selectedSubject;
            }

            return $inDateRange;
        });

        // Filter assessments
        $assessments = $this->child->assessmentSubmissions->filter(function($assessment) use ($dateRange) {
            $assessmentDate = Carbon::parse($assessment->created_at);
            $inDateRange = $assessmentDate->between($dateRange['start'], $dateRange['end']);

            if ($this->selectedSubject && $inDateRange && isset($assessment->assessment->subject_id)) {
                return $assessment->assessment->subject_id == $this->selectedSubject;
            }

            return $inDateRange;
        });

        // Calculate overall progress
        $this->calculateOverallProgress($sessions, $assessments);

        // Calculate attendance rate
        $this->calculateAttendanceRate($sessions);

        // Prepare assessment scores
        $this->prepareAssessmentScores($assessments);

        // Prepare subject progress
        $this->prepareSubjectProgress($sessions, $assessments);

        // Prepare skill mastery data
        $this->prepareSkillMasteryData($sessions, $assessments);

        // Prepare strengths and weaknesses
        $this->prepareStrengthsWeaknesses();

        // Prepare learning goals
        $this->prepareLearningGoals();

        // Prepare teacher feedback
        $this->prepareTeacherFeedback($sessions);

        // Prepare progress trend
        $this->prepareProgressTrend($sessions, $assessments);

        // Prepare session history
        $this->prepareSessionHistory($sessions);

        // Prepare upcoming sessions
        $this->prepareUpcomingSessions();

        // Prepare recent assessments
        $this->prepareRecentAssessments($assessments);

        // Prepare comparison data
        $this->prepareComparisonData($assessments);

        // Prepare performance metrics
        $this->preparePerformanceMetrics($sessions);

        // Prepare chart data
        $this->prepareChartData($sessions, $assessments);

        // If a subject is selected, prepare subject-specific data
        if ($this->selectedSubject) {
            $this->prepareSelectedSubjectData($sessions, $assessments);
        } else {
            $this->selectedSubjectData = null;
        }
    }

    private function calculateOverallProgress($sessions, $assessments)
    {
        // Calculate progress based on sessions attended and assessment scores
        $totalSessions = $sessions->count();
        $attendedSessions = $sessions->where('attended', true)->count();

        $sessionProgress = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

        $assessmentScores = $assessments->pluck('score')->filter();
        $assessmentProgress = $assessmentScores->count() > 0 ? $assessmentScores->avg() : 0;

        // Weight: 40% attendance, 60% assessment scores
        $this->overallProgress = ($sessionProgress * 0.4) + ($assessmentProgress * 0.6);
        $this->overallProgress = round(min(100, max(0, $this->overallProgress)));
    }

    private function calculateAttendanceRate($sessions)
    {
        $totalSessions = $sessions->count();
        $attendedSessions = $sessions->where('attended', true)->count();

        $this->attendanceRate = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100) : 0;
    }

    private function prepareAssessmentScores($assessments)
    {
        // Group assessments by subject
        $bySubject = $assessments->groupBy(function($assessment) {
            return $assessment->assessment->subject->name ?? 'Unknown';
        });

        $this->assessmentScores = [];

        foreach ($bySubject as $subject => $items) {
            $scores = $items->pluck('score')->filter();
            $avgScore = $scores->count() > 0 ? round($scores->avg(), 1) : 0;

            $this->assessmentScores[] = [
                'subject' => $subject,
                'average_score' => $avgScore,
                'count' => $items->count(),
                'highest' => $scores->count() > 0 ? $scores->max() : 0,
                'lowest' => $scores->count() > 0 ? $scores->min() : 0,
                'grade' => $this->getGradeFromScore($avgScore)['grade'],
                'grade_class' => $this->getGradeFromScore($avgScore)['class']
            ];
        }

        // Sort by average score (descending)
        usort($this->assessmentScores, function($a, $b) {
            return $b['average_score'] <=> $a['average_score'];
        });
    }

    private function prepareSubjectProgress($sessions, $assessments)
    {
        $this->subjectProgress = [];

        foreach ($this->child->subjects as $subject) {
            // Filter sessions for this subject
            $subjectSessions = $sessions->filter(function($session) use ($subject) {
                return $session->subject_id == $subject->id;
            });

            // Filter assessments for this subject
            $subjectAssessments = $assessments->filter(function($assessment) use ($subject) {
                return isset($assessment->assessment->subject_id) &&
                       $assessment->assessment->subject_id == $subject->id;
            });

            // Calculate metrics
            $totalSessions = $subjectSessions->count();
            $attendedSessions = $subjectSessions->where('attended', true)->count();
            $attendanceRate = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

            $scores = $subjectAssessments->pluck('score')->filter();
            $avgScore = $scores->count() > 0 ? $scores->avg() : 0;

            // Calculate overall progress for this subject
            $progress = ($attendanceRate * 0.4) + ($avgScore * 0.6);
            $progress = round(min(100, max(0, $progress)));

            $this->subjectProgress[] = [
                'id' => $subject->id,
                'name' => $subject->name,
                'progress' => $progress,
                'total_sessions' => $totalSessions,
                'attended_sessions' => $attendedSessions,
                'attendance_rate' => round($attendanceRate),
                'assessments_count' => $subjectAssessments->count(),
                'average_score' => round($avgScore, 1),
                'grade' => $this->getGradeFromScore($avgScore)['grade'],
                'grade_class' => $this->getGradeFromScore($avgScore)['class'],
                'progress_class' => $this->getProgressColor($progress),
                'growth' => rand(-5, 15) // Mock data for growth - would be calculated from historical data
            ];
        }

        // Sort by progress (descending)
        usort($this->subjectProgress, function($a, $b) {
            return $b['progress'] <=> $a['progress'];
        });
    }

    private function prepareSkillMasteryData($sessions, $assessments)
    {
        // In a real application, this would be calculated from detailed assessment results
        // For now, we'll use mock data with some randomness but keeping it consistent
        $skillCategories = [
            'Critical Thinking' => ['Analysis', 'Evaluation', 'Problem Solving', 'Decision Making'],
            'Communication' => ['Reading', 'Writing', 'Speaking', 'Listening'],
            'Mathematics' => ['Arithmetic', 'Algebra', 'Geometry', 'Data Analysis'],
            'Science' => ['Scientific Method', 'Investigation', 'Experimentation', 'Observation'],
            'Study Skills' => ['Organization', 'Time Management', 'Note Taking', 'Research']
        ];

        $this->skillMastery = [];

        foreach ($skillCategories as $category => $skills) {
            $categorySkills = [];

            foreach ($skills as $skill) {
                // Generate a skill level between 40-95, with bias toward the overall progress
                $bias = $this->overallProgress / 100;
                $randomFactor = 0.3; // Scale of randomness (0-1)

                $baseLevel = 40 + (55 * $bias); // 40-95 range based on overall progress
                $randomVariation = (rand(-10, 10) * $randomFactor);
                $level = round(min(95, max(40, $baseLevel + $randomVariation)));

                $growth = rand(-3, 8); // Random growth for demo

                $categorySkills[] = [
                    'name' => $skill,
                    'level' => $level,
                    'growth' => $growth,
                    'class' => $this->getProgressColor($level),
                    'recommendations' => $this->generateSkillRecommendations($skill, $level)
                ];
            }

            $this->skillMastery[] = [
                'category' => $category,
                'skills' => $categorySkills,
                'average_level' => round(collect($categorySkills)->avg('level')),
                'total_skills' => count($categorySkills)
            ];
        }
    }

    private function generateSkillRecommendations($skill, $level)
    {
        // Generate mock recommendations based on skill level
        $recommendations = [];

        if ($level < 60) {
            $recommendations[] = [
                'type' => 'Practice',
                'description' => "Additional practice exercises focusing on $skill fundamentals",
                'resource' => "Basic $skill Workbook"
            ];
            $recommendations[] = [
                'type' => 'Tutorial',
                'description' => "$skill foundations video series",
                'resource' => "Understanding $skill Basics"
            ];
        } elseif ($level < 75) {
            $recommendations[] = [
                'type' => 'Practice',
                'description' => "Intermediate $skill exercises to build confidence",
                'resource' => "Intermediate $skill Workbook"
            ];
            $recommendations[] = [
                'type' => 'Activity',
                'description' => "Guided practice with feedback on $skill application",
                'resource' => "$skill Application Workshop"
            ];
        } else {
            $recommendations[] = [
                'type' => 'Challenge',
                'description' => "Advanced $skill challenges to push mastery further",
                'resource' => "Advanced $skill Mastery Guide"
            ];
            $recommendations[] = [
                'type' => 'Project',
                'description' => "Self-directed project applying $skill in real-world scenarios",
                'resource' => "$skill Innovation Project"
            ];
        }

        return $recommendations;
    }

    private function prepareStrengthsWeaknesses()
    {
        // Identify top 3 strengths (highest skill levels)
        $allSkills = collect($this->skillMastery)->flatMap(function($category) {
            return collect($category['skills'])->map(function($skill) use ($category) {
                return [
                    'category' => $category['category'],
                    'name' => $skill['name'],
                    'level' => $skill['level']
                ];
            });
        });

        $strengths = $allSkills->sortByDesc('level')->take(3)->values()->toArray();
        $weaknesses = $allSkills->sortBy('level')->take(3)->values()->toArray();

        $this->strengthsWeaknesses = [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses
        ];
    }

    private function prepareLearningGoals()
    {
        // In a real application, this would be fetched from the database
        // For now, we'll use mock data
        $this->learningGoals = [
            [
                'id' => 1,
                'title' => 'Improve Reading Comprehension',
                'description' => 'Enhance ability to understand and analyze complex texts',
                'subject' => 'English',
                'target_date' => Carbon::now()->addWeeks(6)->format('Y-m-d'),
                'progress' => 65,
                'progress_class' => $this->getProgressColor(65),
                'created_at' => Carbon::now()->subWeeks(4)->format('Y-m-d'),
                'milestones' => [
                    ['title' => 'Complete initial assessment', 'completed' => true],
                    ['title' => 'Read 5 challenging books', 'completed' => true],
                    ['title' => 'Practice summarization techniques', 'completed' => true],
                    ['title' => 'Complete mid-point assessment', 'completed' => false],
                    ['title' => 'Write analytical responses', 'completed' => false]
                ]
            ],
            [
                'id' => 2,
                'title' => 'Master Algebraic Equations',
                'description' => 'Develop proficiency in solving multi-step equations',
                'subject' => 'Mathematics',
                'target_date' => Carbon::now()->addWeeks(8)->format('Y-m-d'),
                'progress' => 40,
                'progress_class' => $this->getProgressColor(40),
                'created_at' => Carbon::now()->subWeeks(3)->format('Y-m-d'),
                'milestones' => [
                    ['title' => 'Complete fundamentals review', 'completed' => true],
                    ['title' => 'Practice basic equations', 'completed' => true],
                    ['title' => 'Learn equation transformation techniques', 'completed' => false],
                    ['title' => 'Practice multi-step equations', 'completed' => false],
                    ['title' => 'Complete final assessment', 'completed' => false]
                ]
            ],
            [
                'id' => 3,
                'title' => 'Develop Scientific Inquiry Skills',
                'description' => 'Improve hypothesis formation and experimental design',
                'subject' => 'Science',
                'target_date' => Carbon::now()->addWeeks(10)->format('Y-m-d'),
                'progress' => 25,
                'progress_class' => $this->getProgressColor(25),
                'created_at' => Carbon::now()->subWeeks(2)->format('Y-m-d'),
                'milestones' => [
                    ['title' => 'Learn scientific method principles', 'completed' => true],
                    ['title' => 'Practice hypothesis formation', 'completed' => false],
                    ['title' => 'Design simple experiments', 'completed' => false],
                    ['title' => 'Conduct experiments and record data', 'completed' => false],
                    ['title' => 'Analyze results and draw conclusions', 'completed' => false]
                ]
            ]
        ];
    }

    private function prepareTeacherFeedback($sessions)
    {
        // In a real application, this would come from teacher notes on sessions
        // For demo purposes, we'll generate mock feedback
        $this->teacherFeedback = [];

        $recentSessions = $sessions->sortByDesc('start_time')->take(5);

        foreach ($recentSessions as $index => $session) {
            if (rand(0, 10) > 3) { // 70% chance of having feedback
                $feedbackTypes = ['positive', 'constructive', 'general'];
                $feedbackType = $feedbackTypes[array_rand($feedbackTypes)];

                $feedback = [
                    'id' => $index + 1,
                    'session_id' => $session->id,
                    'date' => Carbon::parse($session->start_time)->format('Y-m-d'),
                    'teacher_name' => $session->teacher->name ?? 'Unknown Teacher',
                    'subject' => $session->subject->name ?? 'Unknown Subject',
                    'type' => $feedbackType
                ];

                switch ($feedbackType) {
                    case 'positive':
                        $feedback['content'] = "Excellent participation today. Shows strong understanding of " .
                                              ($session->subject->name ?? 'the subject') .
                                              " concepts and actively contributed to discussions.";
                        $feedback['class'] = 'text-success';
                        break;
                    case 'constructive':
                        $feedback['content'] = "While making progress, needs additional practice with " .
                                              strtolower($session->subject->name ?? 'subject') .
                                              " fundamentals. Would benefit from more focused study on key concepts.";
                        $feedback['class'] = 'text-warning';
                        break;
                    default:
                        $feedback['content'] = "Completed today's session focused on " .
                                              strtolower($session->subject->name ?? 'subject') .
                                              " principles. Demonstrated good effort throughout the lesson.";
                        $feedback['class'] = 'text-info';
                }

                $this->teacherFeedback[] = $feedback;
            }
        }
    }

    private function prepareProgressTrend($sessions, $assessments)
    {
        // Generate monthly progress data
        $dateRange = $this->getDateRange();
        $startMonth = $dateRange['start']->startOfMonth();
        $endMonth = $dateRange['end']->startOfMonth();

        $monthlyData = [];
        $currentMonth = $startMonth->copy();

        while ($currentMonth->lte($endMonth)) {
            $monthKey = $currentMonth->format('Y-m');
            $monthLabel = $currentMonth->format('M Y');

            $monthSessions = $sessions->filter(function($session) use ($currentMonth) {
                $sessionDate = Carbon::parse($session->start_time);
                return $sessionDate->month == $currentMonth->month &&
                       $sessionDate->year == $currentMonth->year;
            });

            $monthAssessments = $assessments->filter(function($assessment) use ($currentMonth) {
                $assessmentDate = Carbon::parse($assessment->created_at);
                return $assessmentDate->month == $currentMonth->month &&
                       $assessmentDate->year == $currentMonth->year;
            });

            // Calculate monthly progress
            $totalSessions = $monthSessions->count();
            $attendedSessions = $monthSessions->where('attended', true)->count();
            $attendanceRate = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

            $scores = $monthAssessments->pluck('score')->filter();
            $avgScore = $scores->count() > 0 ? $scores->avg() : 0;

            // Weight: 40% attendance, 60% assessment scores
            $progress = ($attendanceRate * 0.4) + ($avgScore * 0.6);
            $progress = round(min(100, max(0, $progress)));

            // If no sessions or assessments, use last month's progress or a default
            if ($totalSessions == 0 && $scores->count() == 0) {
                $progress = isset($monthlyData[$currentMonth->copy()->subMonth()->format('Y-m')])
                          ? $monthlyData[$currentMonth->copy()->subMonth()->format('Y-m')]['progress']
                          : 50;
            }

            $monthlyData[$monthKey] = [
                'month' => $monthLabel,
                'progress' => $progress,
                'attendance_rate' => round($attendanceRate),
                'average_score' => round($avgScore),
                'total_sessions' => $totalSessions,
                'total_assessments' => $scores->count()
            ];

            $currentMonth->addMonth();
        }

        $this->progressTrend = array_values($monthlyData);
    }

    private function prepareSessionHistory($sessions)
    {
        $this->sessionHistory = $sessions->sortByDesc('start_time')->values()->toArray();
    }

    private function prepareUpcomingSessions()
    {
        // Get upcoming sessions for this child
        $this->upcomingSessions = $this->child->learningSessions()
            ->where('start_time', '>', Carbon::now())
            ->where('status', 'scheduled')
            ->with(['subject', 'teacher'])
            ->orderBy('start_time', 'asc')
            ->take(5)
            ->get()
            ->toArray();
    }

    private function prepareRecentAssessments($assessments)
    {
        $this->recentAssessments = $assessments->sortByDesc('created_at')->take(5)->values()->toArray();
    }

    private function prepareComparisonData($assessments)
    {
        // In a real application, this would come from class-wide data
        // For demo purposes, we'll use mock data

        // Calculate the child's average assessment score
        $scores = collect($assessments)->pluck('score')->filter();
        $averageScore = $scores->count() > 0 ? round($scores->avg(), 1) : 0;

        // Generate mock class average (slightly lower than the child's score for positive framing)
        $classAverage = max(60, min(95, $averageScore - rand(3, 8)));

        // Calculate percentile (mock data)
        $percentile = min(99, max(50, $averageScore - $classAverage + rand(70, 85)));

        // Calculate rank (mock data)
        $rank = $percentile >= 90 ? rand(1, 3) : ($percentile >= 75 ? rand(3, 8) : rand(8, 15));

        $this->comparisonData = [
            'averageScore' => $averageScore,
            'classAverage' => $classAverage,
            'percentile' => $percentile,
            'rank' => $rank,
            'totalStudents' => rand(20, 30),
            'compareClass' => $averageScore > $classAverage ? 'text-success' : 'text-error'
        ];
    }

    private function preparePerformanceMetrics($sessions)
    {
        // In a real application, these would be calculated from detailed session data
        // For demo purposes, we'll use mock data that correlates with overall progress

        $baseLevel = $this->overallProgress;
        $randomFactor = 10; // Range of random variation

        $this->performanceMetrics = [
            'punctuality' => min(100, max(0, $baseLevel + rand(-$randomFactor, $randomFactor))),
            'participation' => min(100, max(0, $baseLevel + rand(-$randomFactor, $randomFactor))),
            'completion' => min(100, max(0, $baseLevel + rand(-$randomFactor, $randomFactor))),
            'attentiveness' => min(100, max(0, $baseLevel + rand(-$randomFactor, $randomFactor))),
            'collaboration' => min(100, max(0, $baseLevel + rand(-$randomFactor, $randomFactor)))
        ];
    }

    private function prepareChartData($sessions, $assessments)
    {
        // Prepare subject data for charts
        $this->chartData['subjects'] = collect($this->subjectProgress)->map(function($subject) {
            return [
                'name' => $subject['name'],
                'progress' => $subject['progress']
            ];
        })->toArray();

        // Prepare attendance data by month
        $attendanceByMonth = [];
        $dateRange = $this->getDateRange();
        $startMonth = $dateRange['start']->startOfMonth();
        $endMonth = $dateRange['end']->startOfMonth();

        $currentMonth = $startMonth->copy();
        while ($currentMonth->lte($endMonth)) {
            $monthKey = $currentMonth->format('Y-m');
            $monthLabel = $currentMonth->format('M Y');

            $monthSessions = $sessions->filter(function($session) use ($currentMonth) {
                $sessionDate = Carbon::parse($session->start_time);
                return $sessionDate->month == $currentMonth->month &&
                       $sessionDate->year == $currentMonth->year;
            });

            $attendanceByMonth[] = [
                'month' => $monthLabel,
                'attended' => $monthSessions->where('attended', true)->count(),
                'missed' => $monthSessions->where('attended', false)->count()
            ];

            $currentMonth->addMonth();
        }

        $this->chartData['attendance'] = $attendanceByMonth;

        // Prepare assessment scores data
        $this->chartData['assessments'] = collect($assessments)->sortBy('created_at')->map(function($assessment) {
            return [
                'date' => Carbon::parse($assessment->created_at)->format('M d'),
                'score' => $assessment->score,
                'subject' => $assessment->assessment->subject->name ?? 'Unknown'
            ];
        })->toArray();

        // Prepare progress trend data
        $this->chartData['progressTrend'] = $this->progressTrend;

        // Prepare skills radar data
// Prepare skills radar data
$this->chartData['skills'] = collect($this->skillMastery)->flatMap(function($category) {
    return collect($category['skills'])->map(function($skill) use ($category) {
        return [
            'category' => $category['category'],
            'skill' => $skill['name'],
            'level' => $skill['level']
        ];
    });
})->toArray();

// Prepare subject breakdown data
$this->chartData['subjectBreakdown'] = [];
foreach ($this->subjectProgress as $subject) {
    $this->chartData['subjectBreakdown'][] = [
        'name' => $subject['name'],
        'attendance' => $subject['attendance_rate'],
        'score' => $subject['average_score'],
        'progress' => $subject['progress']
    ];
}
}

private function prepareSelectedSubjectData($sessions, $assessments)
{
    $subject = collect($this->availableSubjects)->firstWhere('id', $this->selectedSubject);

    if (!$subject) {
        $this->selectedSubjectData = null;
        return;
    }

    // Filter sessions for this subject
    $subjectSessions = $sessions->filter(function($session) {
        return $session->subject_id == $this->selectedSubject;
    });

    // Filter assessments for this subject
    $subjectAssessments = $assessments->filter(function($assessment) {
        return isset($assessment->assessment->subject_id) &&
               $assessment->assessment->subject_id == $this->selectedSubject;
    });

    // Get topic breakdown (mock data)
    $topics = $this->getSubjectTopics($subject['name']);

    // Prepare topic mastery data
    $topicMastery = [];
    foreach ($topics as $topic) {
        $topicMastery[] = [
            'name' => $topic,
            'mastery' => rand(40, 95),
            'growth' => rand(-5, 15)
        ];
    }

    // Sort topics by mastery level (descending)
    usort($topicMastery, function($a, $b) {
        return $b['mastery'] <=> $a['mastery'];
    });

    // Get concept connections (mock data)
    $concepts = $this->getSubjectConcepts($subject['name']);

    $this->selectedSubjectData = [
        'id' => $subject['id'],
        'name' => $subject['name'],
        'sessions' => $subjectSessions->toArray(),
        'assessments' => $subjectAssessments->toArray(),
        'topics' => $topicMastery,
        'concepts' => $concepts,
        'resources' => $this->getSubjectResources($subject['name'])
    ];
}

private function getSubjectTopics($subjectName)
{
    // Mock data for subject topics
    $topicsBySubject = [
        'Mathematics' => [
            'Number Systems', 'Algebra', 'Geometry', 'Measurement', 'Data Analysis',
            'Probability', 'Calculus', 'Trigonometry'
        ],
        'English' => [
            'Reading Comprehension', 'Writing', 'Grammar', 'Vocabulary', 'Literature Analysis',
            'Speaking', 'Listening', 'Research Skills'
        ],
        'Science' => [
            'Scientific Method', 'Biology', 'Chemistry', 'Physics', 'Earth Science',
            'Ecology', 'Astronomy', 'Laboratory Skills'
        ],
        'History' => [
            'World History', 'Local History', 'Historical Analysis', 'Geography',
            'Cultural Studies', 'Government', 'Economics', 'Primary Sources'
        ]
    ];

    return $topicsBySubject[$subjectName] ?? ['Topic 1', 'Topic 2', 'Topic 3', 'Topic 4', 'Topic 5'];
}

private function getSubjectConcepts($subjectName)
{
    // Mock data for concept connections
    // In a real app, this would be a more complex structure
    return [
        'core' => $subjectName,
        'related' => [
            ['name' => 'Concept 1', 'strength' => rand(60, 90)],
            ['name' => 'Concept 2', 'strength' => rand(60, 90)],
            ['name' => 'Concept 3', 'strength' => rand(60, 90)],
            ['name' => 'Concept 4', 'strength' => rand(60, 90)],
            ['name' => 'Concept 5', 'strength' => rand(60, 90)]
        ]
    ];
}

private function getSubjectResources($subjectName)
{
    // Mock data for recommended resources
    return [
        ['type' => 'Book', 'title' => "$subjectName Fundamentals", 'difficulty' => 'Beginner'],
        ['type' => 'Video', 'title' => "Introduction to $subjectName Concepts", 'difficulty' => 'Beginner'],
        ['type' => 'Exercise', 'title' => "$subjectName Practice Set 1", 'difficulty' => 'Intermediate'],
        ['type' => 'Project', 'title' => "Applied $subjectName Challenge", 'difficulty' => 'Advanced'],
        ['type' => 'Reference', 'title' => "$subjectName Formula Guide", 'difficulty' => 'All Levels']
    ];
}

public function updatedSelectedChildId()
{
    $this->loadChild();
    $this->loadSubjects();
    $this->loadProgressData();
}

public function updatedTimeRange()
{
    $this->loadProgressData();
}

public function updatedCustomStartDate()
{
    if ($this->timeRange === 'custom') {
        $this->loadProgressData();
    }
}

public function updatedCustomEndDate()
{
    if ($this->timeRange === 'custom') {
        $this->loadProgressData();
    }
}

public function updatedSelectedSubject()
{
    $this->loadProgressData();
}

public function updatedActiveTab()
{
    // Reset sub-tab when main tab changes
    $this->activeSubTab = null;
}

public function showSessionDetails($sessionId)
{
    $session = collect($this->sessionHistory)->firstWhere('id', $sessionId);
    if ($session) {
        $this->selectedSession = $session;
        $this->showSessionDetailsModal = true;
    }
}

public function showAssessmentDetails($assessmentId)
{
    $assessment = collect($this->recentAssessments)->firstWhere('id', $assessmentId);
    if ($assessment) {
        $this->selectedAssessment = $assessment;
        $this->showAssessmentDetailsModal = true;
    }
}

public function showSkillDetails($categoryIndex, $skillIndex)
{
    if (isset($this->skillMastery[$categoryIndex]['skills'][$skillIndex])) {
        $this->selectedSkill = [
            'category' => $this->skillMastery[$categoryIndex]['category'],
            'skill' => $this->skillMastery[$categoryIndex]['skills'][$skillIndex]
        ];
        $this->showSkillDetailsModal = true;
    }
}

public function showGoalDetails($goalId)
{
    $goal = collect($this->learningGoals)->firstWhere('id', $goalId);
    if ($goal) {
        $this->selectedGoal = $goal;
        $this->showGoalDetailsModal = true;
    }
}

public function showFeedbackDetails($feedbackId)
{
    $feedback = collect($this->teacherFeedback)->firstWhere('id', $feedbackId);
    if ($feedback) {
        $this->selectedFeedback = $feedback;
        $this->showFeedbackModal = true;
    }
}

public function closeSessionDetailsModal()
{
    $this->showSessionDetailsModal = false;
    $this->selectedSession = null;
}

public function closeAssessmentDetailsModal()
{
    $this->showAssessmentDetailsModal = false;
    $this->selectedAssessment = null;
}

public function closeSkillDetailsModal()
{
    $this->showSkillDetailsModal = false;
    $this->selectedSkill = null;
}

public function closeGoalDetailsModal()
{
    $this->showGoalDetailsModal = false;
    $this->selectedGoal = null;
}

public function closeFeedbackModal()
{
    $this->showFeedbackModal = false;
    $this->selectedFeedback = null;
}

public function downloadProgressReport()
{
    // In a real application, this would generate and download a PDF report
    session()->flash('message', 'Progress report download started. Your report will be ready shortly.');
}

public function getProgressColor($progress)
{
    if ($progress >= 80) {
        return 'bg-success';
    } elseif ($progress >= 60) {
        return 'bg-info';
    } elseif ($progress >= 40) {
        return 'bg-warning';
    } else {
        return 'bg-error';
    }
}

public function getGradeFromScore($score)
{
    if ($score >= 90) {
        return ['grade' => 'A', 'class' => 'text-success'];
    } elseif ($score >= 80) {
        return ['grade' => 'B', 'class' => 'text-success'];
    } elseif ($score >= 70) {
        return ['grade' => 'C', 'class' => 'text-warning'];
    } elseif ($score >= 60) {
        return ['grade' => 'D', 'class' => 'text-warning'];
    } else {
        return ['grade' => 'F', 'class' => 'text-error'];
    }
}

public function getFormattedDate($date)
{
    return Carbon::parse($date)->format('M j, Y');
}

public function getFormattedTime($time)
{
    return Carbon::parse($time)->format('g:i A');
}

public function getTimeRangeOptions()
{
    return [
        'last_month' => 'Last Month',
        'last_3_months' => 'Last 3 Months',
        'last_6_months' => 'Last 6 Months',
        'last_year' => 'Last Year',
        'custom' => 'Custom Range'
    ];
}
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="mx-auto max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col items-start justify-between gap-4 mb-6 lg:flex-row">
            <div class="flex flex-col gap-4 md:flex-row md:items-center">
                <div class="avatar">
                    <div class="w-16 h-16 rounded-full bg-primary">
                        @if($child['photo'])
                            <img src="{{ Storage::url($child['photo']) }}" alt="{{ $child['name'] }}" />
                        @else
                            <div class="flex items-center justify-center w-full h-full text-2xl font-bold text-primary-content">
                                {{ substr($child['name'], 0, 1) }}
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('parents.children.index') }}" class="text-sm hover:underline">
                            <x-icon name="o-arrow-left" class="inline w-4 h-4" />
                            Back to Children
                        </a>
                    </div>
                    <h1 class="text-3xl font-bold">{{ $child['name'] }}'s Progress Dashboard</h1>
                    <p class="text-base-content/70">Comprehensive view of learning progress and achievements</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <div class="dropdown dropdown-end">
                    <button class="btn btn-outline">
                        <x-icon name="o-document-arrow-down" class="w-5 h-5 mr-2" />
                        Export
                    </button>
                    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                        <li>
                            <button wire:click="downloadProgressReport">
                                <x-icon name="o-document-text" class="w-4 h-4" />
                                Full Report (PDF)
                            </button>
                        </li>
                        <li>
                            <a href="#">
                                <x-icon name="o-table-cells" class="w-4 h-4" />
                                Export Data (CSV)
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <x-icon name="o-printer" class="w-4 h-4" />
                                Print Report
                            </a>
                        </li>
                    </ul>
                </div>

                <a href="{{ route('parents.children.edit', $child['id']) }}" class="btn btn-ghost btn-sm">
                    <x-icon name="o-pencil-square" class="w-4 h-4 mr-1" />
                    Edit Profile
                </a>

                <a href="{{ route('parents.sessions.requests', ['child_id' => $child['id']]) }}" class="btn btn-primary">
                    <x-icon name="o-calendar-plus" class="w-4 h-4 mr-2" />
                    Schedule Session
                </a>
            </div>
        </div>

        @if(session()->has('message'))
            <div class="mb-6 alert alert-success">
                <x-icon name="o-check-circle" class="w-6 h-6" />
                <span>{{ session('message') }}</span>
            </div>
        @endif

        <!-- Filters and Controls -->
        <div class="p-4 mb-6 shadow-lg bg-base-100 rounded-xl">
            <div class="flex flex-col justify-between gap-4 lg:flex-row">
                <div class="flex flex-col gap-3 md:flex-row">
                    <select
                        wire:model.live="timeRange"
                        class="select select-bordered"
                    >
                        @foreach($this->getTimeRangeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    @if($timeRange === 'custom')
                        <div class="flex items-center gap-2">
                            <input
                                type="date"
                                wire:model.live="customStartDate"
                                class="input input-bordered"
                            />
                            <span>to</span>
                            <input
                                type="date"
                                wire:model.live="customEndDate"
                                class="input input-bordered"
                            />
                        </div>
                    @endif
                </div>

                <div class="flex flex-col gap-3 md:flex-row">
                    <select
                        wire:model.live="selectedSubject"
                        class="select select-bordered"
                    >
                        <option value="">All Subjects</option>
                        @foreach($availableSubjects as $subject)
                            <option value="{{ $subject['id'] }}">{{ $subject['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Main Tabs -->
        <div class="mb-6 tabs tabs-boxed">
            <a
                wire:click="$set('activeTab', 'overview')"
                class="tab {{ $activeTab === 'overview' ? 'tab-active' : '' }}"
            >
                Overview
            </a>
            <a
                wire:click="$set('activeTab', 'subjects')"
                class="tab {{ $activeTab === 'subjects' ? 'tab-active' : '' }}"
            >
                Subjects
            </a>
            <a
                wire:click="$set('activeTab', 'skills')"
                class="tab {{ $activeTab === 'skills' ? 'tab-active' : '' }}"
            >
                Skills
            </a>
            <a
                wire:click="$set('activeTab', 'attendance')"
                class="tab {{ $activeTab === 'attendance' ? 'tab-active' : '' }}"
            >
                Attendance
            </a>
            <a
                wire:click="$set('activeTab', 'performance')"
                class="tab {{ $activeTab === 'performance' ? 'tab-active' : '' }}"
            >
                Performance
            </a>
        </div>

        <!-- Overview Tab -->
        <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
            <!-- Progress Summary Cards -->
            <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2 lg:grid-cols-4">
                <!-- Overall Progress -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold">Overall Progress</h3>
                        <div class="tooltip" data-tip="Combined measure of attendance, assessments, and skill mastery">
                            <x-icon name="o-information-circle" class="w-5 h-5 text-info" />
                        </div>
                    </div>

                    <div class="flex flex-col items-center">
                        <div class="radial-progress text-primary" style="--value:{{ $overallProgress }}; --size:8rem; --thickness: 0.8rem;">
                            <span class="text-2xl font-bold">{{ $overallProgress }}%</span>
                        </div>
                        <div class="mt-4 text-sm">
                            <span class="font-medium">Status:</span>
                            @if($overallProgress >= 80)
                                <span class="text-success">Excellent</span>
                            @elseif($overallProgress >= 60)
                                <span class="text-info">Good</span>
                            @elseif($overallProgress >= 40)
                                <span class="text-warning">Needs Improvement</span>
                            @else
                                <span class="text-error">Requires Attention</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Attendance Rate -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold">Attendance Rate</h3>
                        <div class="tooltip" data-tip="Percentage of scheduled sessions attended">
                            <x-icon name="o-information-circle" class="w-5 h-5 text-info" />
                        </div>
                    </div>

                    <div class="flex flex-col items-center">
                        <div class="radial-progress text-secondary" style="--value:{{ $attendanceRate }}; --size:8rem; --thickness: 0.8rem;">
                            <span class="text-2xl font-bold">{{ $attendanceRate }}%</span>
                        </div>
                        <div class="mt-4 text-sm">
                            <span class="font-medium">Rate:</span>
                            @if($attendanceRate >= 90)
                                <span class="text-success">Excellent</span>
                            @elseif($attendanceRate >= 75)
                                <span class="text-info">Good</span>
                            @elseif($attendanceRate >= 60)
                                <span class="text-warning">Needs Improvement</span>
                            @else
                                <span class="text-error">Requires Attention</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Class Comparison -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold">Class Comparison</h3>
                        <div class="tooltip" data-tip="How your child compares to peers">
                            <x-icon name="o-information-circle" class="w-5 h-5 text-info" />
                        </div>
                    </div>

                    <div class="flex flex-col items-center">
                        <div class="stat-value text-3xl {{ $comparisonData['compareClass'] }}">
                            {{ $comparisonData['percentile'] }}<span class="text-lg">%</span>
                        </div>
                        <div class="stat-title">Percentile Rank</div>
                        <div class="mt-4 text-sm">
                            <div class="flex items-center justify-between gap-4 mb-2">
                                <span>Your Child:</span>
                                <span class="font-medium">{{ $comparisonData['averageScore'] }}%</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span>Class Average:</span>
                                <span class="font-medium">{{ $comparisonData['classAverage'] }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <h3 class="mb-4 text-lg font-bold">Recent Activity</h3>

                    <div class="space-y-3">
                        @if(count($upcomingSessions) > 0)
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-full bg-primary/20">
                                    <x-icon name="o-calendar" class="w-4 h-4 text-primary" />
                                </div>
                                <div class="text-sm">
                                    <span class="font-medium">Next Session:</span>
                                    <div class="text-xs">
                                        {{ $this->getFormattedDate($upcomingSessions[0]['start_time']) }}
                                        ({{ $upcomingSessions[0]['subject']['name'] ?? 'Unknown Subject' }})
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(count($recentAssessments) > 0)
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-full bg-secondary/20">
                                    <x-icon name="o-clipboard-document-check" class="w-4 h-4 text-secondary" />
                                </div>
                                <div class="text-sm">
                                    <span class="font-medium">Latest Assessment:</span>
                                    <div class="text-xs">
                                        {{ $this->getFormattedDate($recentAssessments[0]['created_at']) }}
                                        ({{ $recentAssessments[0]['score'] }}%)
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(count($progressTrend) > 0)
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-full bg-success/20">
                                    <x-icon name="o-chart-bar" class="w-4 h-4 text-success" />
                                </div>
                                <div class="text-sm">
                                    <span class="font-medium">Progress Trend:</span>
                                    @php
                                        $lastMonth = $progressTrend[count($progressTrend) - 1];
                                        $previousMonth = count($progressTrend) >= 2 ? $progressTrend[count($progressTrend) - 2] : null;
                                        $change = $previousMonth ? $lastMonth['progress'] - $previousMonth['progress'] : 0;
                                    @endphp
                                    <div class="text-xs {{ $change >= 0 ? 'text-success' : 'text-error' }}">
                                        {{ $change >= 0 ? '+' : '' }}{{ $change }}% from previous month
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(count($skillMastery) > 0)
                            <div class="flex items-center gap-2">
                                <div class="p-2 rounded-full bg-info/20">
                                    <x-icon name="o-light-bulb" class="w-4 h-4 text-info" />
                                </div>
                                <div class="text-sm">
                                    <span class="font-medium">Top Skill:</span>
                                    @php
                                        $allSkills = collect($skillMastery)->flatMap(function($category) {
                                            return collect($category['skills']);
                                        });
                                        $topSkill = $allSkills->sortByDesc('level')->first();
                                    @endphp
                                    @if($topSkill)
                                        <div class="text-xs">
                                            {{ $topSkill['name'] }} ({{ $topSkill['level'] }}%)
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <a href="{{ route('parents.children.show', $child['id']) }}" class="mt-4 btn btn-ghost btn-sm btn-block">
                        View Child Profile
                    </a>
                </div>
            </div>

            <!-- Progress Over Time Chart -->
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-lg font-bold">Progress Over Time</h3>

                @if(count($progressTrend) > 0)
                    <div class="h-64">
                        <!-- This would typically be a chart component -->
                        <div class="flex items-end h-full">
                            @foreach($progressTrend as $item)
                                <div class="flex flex-col items-center flex-1">
                                    <div class="mb-1 text-xs">{{ $item['progress'] }}%</div>
                                    <div class="w-full max-w-[30px] bg-primary rounded-t" style="height: {{ $item['progress'] }}%;"></div>
                                    <div class="w-full mt-2 text-xs text-center truncate">{{ $item['month'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No progress data available for the selected time period</p>
                    </div>
                @endif
            </div>

            <!-- Subject Progress -->
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold">Subject Progress</h3>
                    <button
                        wire:click="$set('activeTab', 'subjects')"
                        class="btn btn-ghost btn-sm"
                    >
                        View All Subjects
                    </button>
                </div>

                @if(count($subjectProgress) > 0)
                    <div class="space-y-4">
                        @foreach(array_slice($subjectProgress, 0, 4) as $subject)
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="font-medium">{{ $subject['name'] }}</span>
                                    <div class="flex items-center gap-2">
                                        <span>{{ $subject['progress'] }}%</span>
                                        <span class="badge {{ $subject['grade_class'] }}">{{ $subject['grade'] }}</span>
                                    </div>
                                </div>
                                <div class="w-full h-3 overflow-hidden rounded-full bg-base-300">
                                    <div class="h-full {{ $subject['progress_class'] }}" style="width: {{ $subject['progress'] }}%"></div>
                                </div>
                                <div class="flex justify-between mt-1 text-xs opacity-70">
                                    <span>Sessions: {{ $subject['total_sessions'] }}</span>
                                    <span>Attendance: {{ $subject['attendance_rate'] }}%</span>
                                    <span>Avg. Score: {{ $subject['average_score'] }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No subject data available for the selected time period</p>
                    </div>
                @endif
            </div>

            <!-- Strengths & Areas for Improvement -->
            <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
                <!-- Strengths -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <h3 class="mb-4 text-lg font-bold">Strengths</h3>

                    @if(isset($strengthsWeaknesses['strengths']) && count($strengthsWeaknesses['strengths']) > 0)
                        <div class="space-y-4">
                            @foreach($strengthsWeaknesses['strengths'] as $strength)
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center justify-center w-12 h-12 rounded-full bg-success/20">
                                        <x-icon name="o-star" class="w-6 h-6 text-success" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $strength['name'] }}</div>
                                        <div class="text-sm opacity-70">{{ $strength['category'] }}</div>
                                    </div>
                                    <div class="ml-auto">
                                        <div class="radial-progress text-success" style="--value:{{ $strength['level'] }}; --size:3rem; --thickness: 0.4rem;">
                                            <span class="text-xs">{{ $strength['level'] }}%</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No strength data available</p>
                        </div>
                    @endif
                </div>

                <!-- Areas for Improvement -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
<h3 class="mb-4 text-lg font-bold">Areas for Improvement</h3>

                    @if(isset($strengthsWeaknesses['weaknesses']) && count($strengthsWeaknesses['weaknesses']) > 0)
                        <div class="space-y-4">
                            @foreach($strengthsWeaknesses['weaknesses'] as $weakness)
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center justify-center w-12 h-12 rounded-full bg-warning/20">
                                        <x-icon name="o-light-bulb" class="w-6 h-6 text-warning" />
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $weakness['name'] }}</div>
                                        <div class="text-sm opacity-70">{{ $weakness['category'] }}</div>
                                    </div>
                                    <div class="ml-auto">
                                        <div class="radial-progress text-warning" style="--value:{{ $weakness['level'] }}; --size:3rem; --thickness: 0.4rem;">
                                            <span class="text-xs">{{ $weakness['level'] }}%</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No improvement areas identified</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Sessions & Teacher Feedback -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Recent Sessions -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold">Recent Sessions</h3>
                        <a href="{{ route('parents.sessions.index', ['child_id' => $child['id']]) }}" class="btn btn-ghost btn-sm">View All</a>
                    </div>

                    @if(count($sessionHistory) > 0)
                        <div class="overflow-x-auto">
                            <table class="table w-full table-zebra">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_slice($sessionHistory, 0, 5) as $session)
                                        <tr>
                                            <td>{{ $this->getFormattedDate($session['start_time']) }}</td>
                                            <td>{{ $session['subject']['name'] ?? 'Unknown' }}</td>
                                            <td>
                                                <div class="badge {{
                                                    $session['status'] === 'completed' ? 'badge-success' :
                                                    ($session['status'] === 'scheduled' ? 'badge-info' : 'badge-error')
                                                }}">
                                                    {{ ucfirst($session['status']) }}
                                                </div>
                                            </td>
                                            <td>
                                                <button
                                                    wire:click="showSessionDetails({{ $session['id'] }})"
                                                    class="btn btn-ghost btn-xs"
                                                >
                                                    <x-icon name="o-eye" class="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No session data available for the selected time period</p>
                        </div>
                    @endif
                </div>

                <!-- Teacher Feedback -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <h3 class="mb-4 text-lg font-bold">Teacher Feedback</h3>

                    @if(count($teacherFeedback) > 0)
                        <div class="space-y-4">
                            @foreach($teacherFeedback as $feedback)
                                <div class="py-2 pl-4 border-l-4 border-primary">
                                    <div class="flex justify-between">
                                        <div class="font-medium">{{ $feedback['teacher_name'] }}</div>
                                        <div class="text-xs opacity-70">{{ $this->getFormattedDate($feedback['date']) }}</div>
                                    </div>
                                    <div class="mb-1 text-xs opacity-70">{{ $feedback['subject'] }}</div>
                                    <p class="text-sm {{ $feedback['class'] }}">{{ $feedback['content'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No feedback available for the selected time period</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Subjects Tab -->
        <div class="{{ $activeTab === 'subjects' ? 'block' : 'hidden' }}">
            <!-- Subjects Grid -->
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-xl font-bold">Subject Performance</h3>

                @if(count($subjectProgress) > 0)
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($subjectProgress as $subject)
                            <div class="p-6 transition-shadow border rounded-xl hover:shadow-lg">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-bold">{{ $subject['name'] }}</h4>
                                    <div class="badge {{ $subject['grade_class'] }}">{{ $subject['grade'] }}</div>
                                </div>

                                <div class="mb-4 text-center">
                                    <div class="mx-auto radial-progress text-primary" style="--value:{{ $subject['progress'] }}; --size:7rem; --thickness: 0.7rem;">
                                        <span class="text-xl font-bold">{{ $subject['progress'] }}%</span>
                                    </div>
                                </div>

                                <div class="mb-4 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm opacity-70">Sessions Attended:</span>
                                        <span class="font-medium">{{ $subject['attended_sessions'] }}/{{ $subject['total_sessions'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm opacity-70">Attendance Rate:</span>
                                        <span class="font-medium">{{ $subject['attendance_rate'] }}%</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm opacity-70">Assessments:</span>
                                        <span class="font-medium">{{ $subject['assessments_count'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm opacity-70">Average Score:</span>
                                        <span class="font-medium">{{ $subject['average_score'] }}%</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm opacity-70">Growth:</span>
                                        <span class="font-medium {{ $subject['growth'] >= 0 ? 'text-success' : 'text-error' }}">
                                            {{ $subject['growth'] >= 0 ? '+' : '' }}{{ $subject['growth'] }}%
                                        </span>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button
                                        wire:click="$set('selectedSubject', {{ $subject['id'] }})"
                                        class="btn btn-outline btn-sm"
                                    >
                                        View Details
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No subject data available for the selected time period</p>
                    </div>
                @endif
            </div>

            <!-- Selected Subject Detail -->
            @if($selectedSubjectData)
                <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold">{{ $selectedSubjectData['name'] }} Details</h3>
                        <button
                            wire:click="$set('selectedSubject', null)"
                            class="btn btn-ghost btn-sm"
                        >
                            <x-icon name="o-x-mark" class="w-4 h-4 mr-1" />
                            Clear Selection
                        </button>
                    </div>

                    <!-- Topic Mastery -->
                    <div class="mb-6">
                        <h4 class="mb-4 font-medium">Topic Mastery</h4>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach($selectedSubjectData['topics'] as $topic)
                                <div class="p-3 border rounded-lg">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-medium">{{ $topic['name'] }}</span>
                                        <div class="flex items-center gap-2">
                                            <span>{{ $topic['mastery'] }}%</span>
                                            <span class="{{ $topic['growth'] >= 0 ? 'text-success' : 'text-error' }}">
                                                {{ $topic['growth'] >= 0 ? '+' : '' }}{{ $topic['growth'] }}%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                        <div class="h-full {{ $this->getProgressColor($topic['mastery']) }}" style="width: {{ $topic['mastery'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Recent Assessments for Subject -->
                    <div class="mb-6">
                        <h4 class="mb-4 font-medium">Recent Assessments</h4>

                        @if(count($selectedSubjectData['assessments']) > 0)
                            <div class="overflow-x-auto">
                                <table class="table w-full">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Assessment</th>
                                            <th>Score</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(array_slice($selectedSubjectData['assessments'], 0, 5) as $assessment)
                                            <tr>
                                                <td>{{ $this->getFormattedDate($assessment['created_at']) }}</td>
                                                <td>{{ $assessment['assessment']['title'] ?? 'Unknown Assessment' }}</td>
                                                <td>
                                                    @php $grade = $this->getGradeFromScore($assessment['score']); @endphp
                                                    <div class="flex items-center gap-2">
                                                        <span>{{ $assessment['score'] }}%</span>
                                                        <span class="badge {{ $grade['class'] }}">{{ $grade['grade'] }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button
                                                        wire:click="showAssessmentDetails({{ $assessment['id'] }})"
                                                        class="btn btn-ghost btn-xs"
                                                    >
                                                        <x-icon name="o-eye" class="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-4 text-center">
                                <p class="text-base-content/70">No assessments available for this subject</p>
                            </div>
                        @endif
                    </div>

                    <!-- Recommended Resources -->
                    <div>
                        <h4 class="mb-4 font-medium">Recommended Resources</h4>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                            @foreach($selectedSubjectData['resources'] as $resource)
                                <div class="p-3 border rounded-lg">
                                    <div class="flex items-center gap-2 mb-1">
                                        <x-icon name="{{
                                            $resource['type'] === 'Book' ? 'o-book-open' :
                                            ($resource['type'] === 'Video' ? 'o-play' :
                                            ($resource['type'] === 'Exercise' ? 'o-clipboard-document-list' :
                                            ($resource['type'] === 'Project' ? 'o-sparkles' : 'o-document-text')))
                                        }}" class="w-5 h-5 text-primary" />
                                        <span class="font-medium">{{ $resource['title'] }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs opacity-70">
                                        <span>{{ $resource['type'] }}</span>
                                        <span>{{ $resource['difficulty'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Skills Tab -->
        <div class="{{ $activeTab === 'skills' ? 'block' : 'hidden' }}">
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-xl font-bold">Skill Mastery</h3>

                @if(count($skillMastery) > 0)
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        @foreach($skillMastery as $categoryIndex => $category)
                            <div class="p-6 border rounded-xl">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-bold">{{ $category['category'] }}</h4>
                                    <div class="badge badge-primary">{{ $category['average_level'] }}%</div>
                                </div>

                                <div class="space-y-4">
                                    @foreach($category['skills'] as $skillIndex => $skill)
                                        <div>
                                            <div class="flex justify-between mb-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium">{{ $skill['name'] }}</span>
                                                    <span class="{{ $skill['growth'] >= 0 ? 'text-success' : 'text-error' }} text-xs">
                                                        {{ $skill['growth'] >= 0 ? '+' : '' }}{{ $skill['growth'] }}%
                                                    </span>
                                                </div>
                                                <button
                                                    wire:click="showSkillDetails({{ $categoryIndex }}, {{ $skillIndex }})"
                                                    class="btn btn-ghost btn-xs"
                                                >
                                                    <x-icon name="o-information-circle" class="w-4 h-4" />
                                                </button>
                                            </div>
                                            <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                                <div class="h-full {{ $skill['class'] }}" style="width: {{ $skill['level'] }}%"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No skill data available for the selected time period</p>
                    </div>
                @endif
            </div>

            <!-- Learning Goals -->
            <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold">Learning Goals</h3>
                    <button class="btn btn-primary btn-sm">
                        <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                        Add Goal
                    </button>
                </div>

                @if(count($learningGoals) > 0)
                    <div class="space-y-6">
                        @foreach($learningGoals as $goal)
                            <div class="p-4 border rounded-lg">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h4 class="font-bold">{{ $goal['title'] }}</h4>
                                        <div class="text-sm opacity-70">{{ $goal['subject'] }} • Target Date: {{ $this->getFormattedDate($goal['target_date']) }}</div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            wire:click="showGoalDetails({{ $goal['id'] }})"
                                            class="btn btn-ghost btn-xs"
                                        >
                                            <x-icon name="o-eye" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>

                                <p class="mt-2 text-sm">{{ $goal['description'] }}</p>

                                <div class="mt-4">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm">Progress: {{ $goal['progress'] }}%</span>
                                        <span class="text-xs">Created: {{ $this->getFormattedDate($goal['created_at']) }}</span>
                                    </div>
                                    <div class="w-full h-2 overflow-hidden rounded-full bg-base-300">
                                        <div class="h-full {{ $goal['progress_class'] }}" style="width: {{ $goal['progress'] }}%"></div>
                                    </div>
                                </div>

                                @if(isset($goal['milestones']) && count($goal['milestones']) > 0)
                                    <div class="mt-4">
                                        <span class="text-sm font-medium">Milestones:</span>
                                        <div class="grid grid-cols-1 gap-2 mt-2 md:grid-cols-2">
                                            @foreach($goal['milestones'] as $milestone)
                                                <div class="flex items-center gap-2 text-sm">
                                                    <div class="form-control">
                                                        <input
                                                            type="checkbox"
                                                            class="checkbox checkbox-sm checkbox-primary"
                                                            {{ $milestone['completed'] ? 'checked' : '' }}
                                                            disabled
                                                        />
                                                    </div>
                                                    <span class="{{ $milestone['completed'] ? 'line-through opacity-50' : '' }}">
                                                        {{ $milestone['title'] }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No learning goals set yet</p>
                        <button class="mt-4 btn btn-primary">
                            <x-icon name="o-plus" class="w-4 h-4 mr-2" />
                            Create Your First Goal
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Attendance Tab -->
        <div class="{{ $activeTab === 'attendance' ? 'block' : 'hidden' }}">
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-xl font-bold">Attendance Overview</h3>

                <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-3">
                    <div class="shadow stats">
                        <div class="stat">
                            <div class="stat-title">Attendance Rate</div>
                            <div class="stat-value text-primary">{{ $attendanceRate }}%</div>
                            <div class="stat-desc">Overall attendance for selected period</div>
                        </div>
                    </div>

                    <div class="shadow stats">
                        <div class="stat">
                            <div class="stat-title">Sessions Attended</div>
                            <div class="stat-value text-success">
                                @php
                                    $totalSessions = count($sessionHistory);
                                    $attendedSessions = collect($sessionHistory)->where('attended', true)->count();
                                @endphp
                                {{ $attendedSessions }}/{{ $totalSessions }}
                            </div>
                            <div class="stat-desc">Sessions in selected period</div>
                        </div>
                    </div>

                    <div class="shadow stats">
                        <div class="stat">
                            <div class="stat-title">Punctuality</div>
                            <div class="stat-value text-info">{{ $performanceMetrics['punctuality'] }}%</div>
                            <div class="stat-desc">Arriving on time to sessions</div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Attendance Chart -->
                <div class="mb-6">
                    <h4 class="mb-4 font-medium">Monthly Attendance</h4>

                    @if(count($chartData['attendance']) > 0)
                        <div class="h-64">
                            <!-- This would typically be a chart component -->
                            <div class="flex items-end h-full">
                                @foreach($chartData['attendance'] as $month)
                                    <div class="flex flex-col items-center flex-1">
                                        <div class="flex flex-col w-full max-w-[50px] gap-1">
                                            @if($month['missed'] > 0)
                                                <div class="text-xs">{{ $month['missed'] }}</div>
                                                <div class="w-full bg-error" style="height: {{ $month['missed'] * 10 }}px;"></div>
                                            @endif

                                            @if($month['attended'] > 0)
                                                <div class="text-xs">{{ $month['attended'] }}</div>
                                                <div class="w-full bg-success" style="height: {{ $month['attended'] * 10 }}px;"></div>
                                            @endif
                                        </div>
                                        <div class="w-full mt-2 text-xs text-center truncate">{{ $month['month'] }}</div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex justify-center gap-4 mt-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-success"></div>
                                    <span class="text-xs">Attended</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-error"></div>
                                    <span class="text-xs">Missed</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No attendance data available for the selected time period</p>
                        </div>
                    @endif
                </div>

                <!-- Session History -->
                <div>
                    <h4 class="mb-4 font-medium">Session History</h4>

                    @if(count($sessionHistory) > 0)
                        <div class="overflow-x-auto">
                            <table class="table w-full table-zebra">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Status</th>
                                        <th>Attended</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($sessionHistory as $session)
                                        <tr>
                                            <td>
                                                <div>{{ $this->getFormattedDate($session['start_time']) }}</div>
                                                <div class="text-xs opacity-70">
                                                    {{ $this->getFormattedTime($session['start_time']) }} -
                                                    {{ $this->getFormattedTime($session['end_time']) }}
                                                </div>
                                            </td>
                                            <td>{{ $session['subject']['name'] ?? 'Unknown' }}</td>
                                            <td>{{ $session['teacher']['name'] ?? 'Unknown' }}</td>
                                            <td>
                                                <div class="badge {{
                                                    $session['status'] === 'completed' ? 'badge-success' :
                                                    ($session['status'] === 'scheduled' ? 'badge-info' : 'badge-error')
                                                }}">
                                                    {{ ucfirst($session['status']) }}
                                                </div>
                                            </td>
                                            <td>
                                                @if($session['status'] === 'completed')
                                                    <div class="{{ $session['attended'] ? 'text-success' : 'text-error' }}">
                                                        {{ $session['attended'] ? 'Yes' : 'No' }}
                                                    </div>
                                                @else
                                                    <div class="opacity-50">N/A</div>
                                                @endif
                                            </td>
                                            <td>
                                                <button
                                                    wire:click="showSessionDetails({{ $session['id'] }})"
                                                    class="btn btn-ghost btn-xs"
                                                >
                                                    <x-icon name="o-eye" class="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No session data available for the selected time period</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Performance Tab -->
        <div class="{{ $activeTab === 'performance' ? 'block' : 'hidden' }}">
            <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-3">
                <!-- Assessment Performance -->
                <div class="p-6 shadow-lg lg:col-span-2 bg-base-100 rounded-xl">
                    <h3 class="mb-6 text-xl font-bold">Assessment Performance</h3>

                    @if(count($recentAssessments) > 0)
                        <!-- Assessment Score Trend -->
                        <div class="mb-6">
                            <h4 class="mb-4 font-medium">Score Trend</h4>

                            <div class="h-48">
                                <!-- This would typically be a chart component -->
                                <div class="flex items-end h-full gap-1">
                                    @foreach($chartData['assessments'] as $assessment)
                                        <div class="flex flex-col items-center flex-1">
                                            <div class="mb-1 text-xs">{{ $assessment['score'] }}%</div>
                                            <div
                                                class="w-full max-w-[20px] rounded-t {{ $this->getProgressColor($assessment['score']) }}"
                                                style="height: {{ $assessment['score'] }}%;"
                                            ></div>
                                            <div class="w-full mt-2 text-xs text-center truncate">{{ $assessment['date'] }}</div>
                                            <div class="w-full text-xs text-center truncate opacity-70">{{ $assessment['subject'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Recent Assessments Table -->
                        <div>
                            <h4 class="mb-4 font-medium">Recent Assessments</h4>

                            <div class="overflow-x-auto">
                                <table class="table w-full">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Title</th>
                                            <th>Subject</th>
                                            <th>Score</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentAssessments as $assessment)
                                            <tr>
                                                <td>{{ $this->getFormattedDate($assessment['created_at']) }}</td>
                                                <td>{{ $assessment['assessment']['title'] ?? 'Unknown Assessment' }}</td>
                                                <td>{{ $assessment['assessment']['subject']['name'] ?? 'Unknown Subject' }}</td>
                                                <td>
                                                    @php $grade = $this->getGradeFromScore($assessment['score']); @endphp
                                                    <div class="flex items-center gap-2">
                                                        <span>{{ $assessment['score'] }}%</span>
                                                        <span class="badge {{ $grade['class'] }}">{{ $grade['grade'] }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button
                                                        wire:click="showAssessmentDetails({{ $assessment['id'] }})"
                                                        class="btn btn-ghost btn-xs"
                                                    >
                                                        <x-icon name="o-eye" class="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="py-6 text-center">
                            <p class="text-base-content/70">No assessment data available for the selected time period</p>
                        </div>
                    @endif
                </div>

                <!-- Class Comparison -->
                <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                    <h3 class="mb-6 text-xl font-bold">Class Comparison</h3>

                    <div class="flex flex-col items-center">
                        <div class="text-4xl font-bold {{ $comparisonData['compareClass'] }}">
                            {{ $comparisonData['percentile'] }}<span class="text-lg">%</span>
                        </div>
                        <div class="mb-6 text-sm opacity-70">Percentile Rank</div>

                        <div class="flex items-center justify-center w-32 h-32 mb-6 rounded-full bg-primary/20">
                            <div class="text-center">
                                <div class="text-3xl font-bold">{{ $comparisonData['rank'] }}</div>
                                <div class="text-sm">of {{ $comparisonData['totalStudents'] }}</div>
                            </div>
                        </div>

                        <div class="w-full space-y-4">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm">Your Child</span>
                                    <span class="font-medium">{{ $comparisonData['averageScore'] }}%</span>
                                </div>
                                <div class="w-full h-3 overflow-hidden rounded-full bg-base-300">
                                    <div class="h-full bg-primary" style="width: {{ $comparisonData['averageScore'] }}%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm">Class Average</span>
                                    <span class="font-medium">{{ $comparisonData['classAverage'] }}%</span>
                                </div>
                                <div class="w-full h-3 overflow-hidden rounded-full bg-base-300">
                                    <div class="h-full bg-secondary" style="width: {{ $comparisonData['classAverage'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="p-6 mb-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-xl font-bold">Performance Metrics</h3>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-5">
                    <div class="text-center">
                        <div class="mx-auto radial-progress text-info" style="--value:{{ $performanceMetrics['punctuality'] }}; --size:7rem; --thickness: 0.7rem;">
                            <span class="text-xl font-bold">{{ $performanceMetrics['punctuality'] }}%</span>
                        </div>
                        <div class="mt-2 font-medium">Punctuality</div>
                        <div class="text-xs opacity-70">Arriving on time to sessions</div>
                    </div>

                    <div class="text-center">
                        <div class="mx-auto radial-progress text-warning" style="--value:{{ $performanceMetrics['participation'] }}; --size:7rem; --thickness: 0.7rem;">
                            <span class="text-xl font-bold">{{ $performanceMetrics['participation'] }}%</span>
                        </div>
                        <div class="mt-2 font-medium">Participation</div>
                        <div class="text-xs opacity-70">Active engagement in sessions</div>
                    </div>

                    <div class="text-center">
                        <div class="mx-auto radial-progress text-success" style="--value:{{ $performanceMetrics['completion'] }}; --size:7rem; --thickness: 0.7rem;">
                            <span class="text-xl font-bold">{{ $performanceMetrics['completion'] }}%</span>
                        </div>
                        <div class="mt-2 font-medium">Completion</div>
                        <div class="text-xs opacity-70">Assignment completion rate</div>
                    </div>

                    <div class="text-center">
                        <div class="mx-auto radial-progress text-primary" style="--value:{{ $performanceMetrics['attentiveness'] }}; --size:7rem; --thickness: 0.7rem;">
                            <span class="text-xl font-bold">{{ $performanceMetrics['attentiveness'] }}%</span>
                        </div>
                        <div class="mt-2 font-medium">Attentiveness</div>
                        <div class="text-xs opacity-70">Focus during learning sessions</div>
                    </div>

                    <div class="text-center">
                        <div class="mx-auto radial-progress text-secondary" style="--value:{{ $performanceMetrics['collaboration'] }}; --size:7rem; --thickness: 0.7rem;">
                            <span class="text-xl font-bold">{{ $performanceMetrics['collaboration'] }}%</span>
                        </div>
                        <div class="mt-2 font-medium">Collaboration</div>
                        <div class="text-xs opacity-70">Working with others effectively</div>
                    </div>
                </div>
            </div>

            <!-- Teacher Feedback -->
            <div class="p-6 shadow-lg bg-base-100 rounded-xl">
                <h3 class="mb-6 text-xl font-bold">Teacher Feedback</h3>

                @if(count($teacherFeedback) > 0)
                    <div class="space-y-6">
                        @foreach($teacherFeedback as $feedback)
                            <div class="py-2 pl-4 border-l-4 border-primary">
                                <div class="flex items-center justify-between">
                                    <div class="font-medium">{{ $feedback['teacher_name'] }}</div>
                                    <div class="text-sm opacity-70">{{ $this->getFormattedDate($feedback['date']) }}</div>
                                </div>
                                <div class="mb-2 text-sm opacity-70">{{ $feedback['subject'] }}</div>
                                <p class="text-sm {{ $feedback['class'] }}">{{ $feedback['content'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center">
                        <p class="text-base-content/70">No feedback available for the selected time period</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Session Details Modal -->
    <div class="modal {{ $showSessionDetailsModal ? 'modal-open' : '' }}">
        <div class="max-w-2xl modal-box">
            @if($selectedSession)
                <div class="flex items-start justify-between">
                    <h3 class="text-lg font-bold">Session Details</h3>
                    <button wire:click="closeSessionDetailsModal" class="btn btn-sm btn-circle">
                        <x-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="divider"></div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <h4 class="mb-2 font-semibold">Session Information</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Subject:</span>
                                <span class="font-medium">{{ $selectedSession['subject']['name'] ?? 'Unknown Subject' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Date:</span>
                                <span>{{ $this->getFormattedDate($selectedSession['start_time']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Time:</span>
                                <span>{{ $this->getFormattedTime($selectedSession['start_time']) }} - {{ $this->getFormattedTime($selectedSession['end_time']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Teacher:</span>
                                <span>{{ $selectedSession['teacher']['name'] ?? 'Unknown Teacher' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Status:</span>
                                <div class="badge {{
                                    $selectedSession['status'] === 'scheduled' ? 'badge-primary' :
                                    ($selectedSession['status'] === 'completed' ? 'badge-success' : 'badge-error')
                                }}">
                                    {{ ucfirst($selectedSession['status']) }}
                                </div>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Attended:</span>
                                <span class="{{ $selectedSession['attended'] ? 'text-success' : 'text-error' }}">
                                    {{ $selectedSession['attended'] ? 'Yes' : 'No' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 font-semibold">Performance</h4>
                        @if($selectedSession['status'] === 'completed')
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm opacity-70">Score:</span>
                                    <span class="font-medium">{{ $selectedSession['performance_score'] ?? 'Not graded' }}</span>
                                </div>

                                <h4 class="mt-6 mb-2 font-semibold">Notes</h4>
                                <div class="p-3 bg-base-200 rounded-lg min-h-[100px]">
                                    {{ $selectedSession['notes'] ?? 'No notes provided for this session.' }}
                                </div>
                            </div>
                        @else
                            <div class="p-3 rounded-lg bg-base-200">
                                <p>Performance details will be available after the session is completed.</p>
                            </div>

                            <h4 class="mt-6 mb-2 font-semibold">Notes</h4>
                            <div class="p-3 bg-base-200 rounded-lg min-h-[80px]">
                                {{ $selectedSession['notes'] ?? 'No notes provided for this session.' }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="modal-action">
                    <button wire:click="closeSessionDetailsModal" class="btn">Close</button>
                </div>
            @endif
        </div>
        <div class="modal-backdrop" wire:click="closeSessionDetailsModal"></div>
    </div>

    <!-- Assessment Details Modal -->
    <div class="modal {{ $showAssessmentDetailsModal ? 'modal-open' : '' }}">
        <div class="max-w-2xl modal-box">
            @if($selectedAssessment)
                <div class="flex items-start justify-between">
                    <h3 class="text-lg font-bold">Assessment Details</h3>
                    <button wire:click="closeAssessmentDetailsModal" class="btn btn-sm btn-circle">
                        <x-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="divider"></div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <h4 class="mb-2 font-semibold">Assessment Information</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Title:</span>
                                <span class="font-medium">{{ $selectedAssessment['assessment']['title'] ?? 'Unknown Assessment' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Subject:</span>
                                <span>{{ $selectedAssessment['assessment']['subject']['name'] ?? 'Unknown Subject' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Date:</span>
                                <span>{{ $this->getFormattedDate($selectedAssessment['created_at']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Type:</span>
                                <span>{{ ucfirst($selectedAssessment['assessment']['type'] ?? 'Unknown') }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 font-semibold">Results</h4>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Score:</span>
                                <span class="font-medium">{{ $selectedAssessment['score'] }}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Grade:</span>
                                @php $grade = $this->getGradeFromScore($selectedAssessment['score']); @endphp
                                <span class="font-medium {{ $grade['class'] }}">{{ $grade['grade'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm opacity-70">Status:</span>
                                <div class="badge {{
                                    $selectedAssessment['status'] === 'graded' ? 'badge-success' : 'badge-info'
                                }}">
                                    {{ ucfirst($selectedAssessment['status'] ?? 'completed') }}
                                </div>
                            </div>
                        </div>

                        <h4 class="mt-6 mb-2 font-semibold">Feedback</h4>
                        <div class="p-3 bg-base-200 rounded-lg min-h-[80px]">
                            {{ $selectedAssessment['feedback'] ?? 'No feedback provided for this assessment.' }}
                        </div>
                    </div>
                </div>

                <div class="modal-action">
                    <button wire:click="closeAssessmentDetailsModal" class="btn">Close</button>
                </div>
            @endif
        </div>
        <div class="modal-backdrop" wire:click="closeAssessmentDetailsModal"></div>
    </div>

    <!-- Skill Details Modal -->
    <div class="modal {{ $showSkillDetailsModal ? 'modal-open' : '' }}">
        <div class="max-w-2xl modal-box">
            @if($selectedSkill)
                <div class="flex items-start justify-between">
                    <h3 class="text-lg font-bold">Skill Details</h3>
                    <button wire:click="closeSkillDetailsModal" class="btn btn-sm btn-circle">
                        <x-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="divider"></div>

                <div class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold">{{ $selectedSkill['skill']['name'] }}</h3>
                            <p class="text-sm opacity-70">{{ $selectedSkill['category'] }}</p>
                        </div>
                        <div class="text-center">
                            <div class="radial-progress text-primary" style="--value:{{ $selectedSkill['skill']['level'] }}; --size:5rem; --thickness: 0.5rem;">
                                <span class="text-lg font-bold">{{ $selectedSkill['skill']['level'] }}%</span>
                            </div>
                            <div class="text-sm {{ $selectedSkill['skill']['growth'] >= 0 ? 'text-success' : 'text-error' }}">
                                {{ $selectedSkill['skill']['growth'] >= 0 ? '+' : '' }}{{ $selectedSkill['skill']['growth'] }}%
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="mb-4 font-medium">Recommendations</h4>

                    <div class="space-y-4">
                        @foreach($selectedSkill['skill']['recommendations'] as $recommendation)
                            <div class="p-3 border rounded-lg">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-medium">{{ $recommendation['type'] }}</span>
                                    <span class="badge badge-primary">{{ $selectedSkill['skill']['level'] < 60 ? 'Foundational' : ($selectedSkill['skill']['level'] < 75 ? 'Intermediate' : 'Advanced') }}</span>
                                </div>
                                <p class="mb-2 text-sm">{{ $recommendation['description'] }}</p>
                                <div class="text-xs opacity-70">Resource: {{ $recommendation['resource'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="modal-action">
                    <button wire:click="closeSkillDetailsModal" class="btn">Close</button>
                </div>
            @endif
        </div>
        <div class="modal-backdrop" wire:click="closeSkillDetailsModal"></div>
    </div>

    <!-- Goal Details Modal -->
    <div class="modal {{ $showGoalDetailsModal ? 'modal-open' : '' }}">
        <div class="max-w-2xl modal-box">
            @if($selectedGoal)
                <div class="flex items-start justify-between">
                    <h3 class="text-lg font-bold">Goal Details</h3>
                    <button wire:click="closeGoalDetailsModal" class="btn btn-sm btn-circle">
                        <x-icon name="o-x-mark" class="w-4 h-4" />
                    </button>
                </div>

                <div class="divider"></div>

                <div class="mb-6">
                    <h3 class="text-xl font-bold">{{ $selectedGoal['title'] }}</h3>
                    <div class="text-sm opacity-70">{{ $selectedGoal['subject'] }} • Target Date: {{ $this->getFormattedDate($selectedGoal['target_date']) }}</div>
                    <p class="mt-4">{{ $selectedGoal['description'] }}</p>
                </div>

                <div class="mb-6">
                    <div class="flex justify-between mb-1">
                        <span>Progress: {{ $selectedGoal['progress'] }}%</span>
                        <span class="text-sm opacity-70">Created: {{ $this->getFormattedDate($selectedGoal['created_at']) }}</span>
                    </div>
                    <div class="w-full h-3 overflow-hidden rounded-full bg-base-300">
                        <div class="h-full {{ $selectedGoal['progress_class'] }}" style="width: {{ $selectedGoal['progress'] }}%"></div>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="mb-4 font-medium">Milestones</h4>

                    <div class="space-y-3">
                        @foreach($selectedGoal['milestones'] as $index => $milestone)
                            <div class="flex items-start gap-3">
                                <div class="form-control">
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-primary"
                                        {{ $milestone['completed'] ? 'checked' : '' }}
                                        disabled
                                    />
                                </div>
                                <div class="{{ $milestone['completed'] ? 'opacity-50' : '' }}">
                                    <div class="font-medium {{ $milestone['completed'] ? 'line-through' : '' }}">
                                        {{ $milestone['title'] }}
                                    </div>
                                    <div class="text-xs opacity-70">
                                        Step {{ $index + 1 }} of {{ count($selectedGoal['milestones']) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="modal-action">
                    <button class="btn btn-outline">
                        <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                        Edit Goal
                    </button>
                    <button wire:click="closeGoalDetailsModal" class="btn">Close</button>
                </div>
            @endif
        </div>
        <div class="modal-backdrop" wire:click="closeGoalDetailsModal"></div>
    </div>
</div>
