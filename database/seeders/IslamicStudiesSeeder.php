<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Subject;
use App\Models\Course;
use App\Models\LearningSession;
use App\Models\Children;
use App\Models\ParentProfile;
use App\Models\TeacherProfile;
use App\Models\ClientProfile;
use App\Models\Material;
use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IslamicStudiesSeeder extends Seeder
{
    /**
     * Islamic studies subject topics
     */
    protected array $islamicTopics = [
        'Quran Recitation',
        'Quran Memorization',
        'Tajweed Rules',
        'Quran Tafsir',
        'Hadith Studies',
        'Fiqh (Islamic Jurisprudence)',
        'Aqeedah (Islamic Creed)',
        'Seerah (Prophetic Biography)',
        'Islamic History',
        'Arabic Language for Quran',
        'Islamic Ethics & Morals',
        'Islamic Arts & Calligraphy'
    ];

    /**
     * Islamic studies levels
     */
    protected array $levels = [
        'Beginner',
        'Intermediate',
        'Advanced',
        'Specialization'
    ];

    /**
     * Quran-specific data
     */
    protected array $quranJuz = [
        'Juz Amma (30)',
        'Juz Tabarak (29)',
        'Juz Al-Dhariyat (27)',
        'Juz Qad Sami\'a (28)',
        'Juz Wa-l-muhsanat (4)',
    ];

    /**
     * Learning outcomes for Islamic courses
     */
    protected array $learningOutcomes = [
        'Read Quran with proper tajweed',
        'Memorize selected surahs with perfect pronunciation',
        'Understand basic Islamic principles',
        'Apply Islamic teachings in daily life',
        'Recognize Arabic letters and simple words',
        'Comprehend historical context of Islamic teachings',
        'Identify key figures in Islamic history',
        'Practice Islamic etiquette and manners',
        'Develop connection with Islamic heritage',
        'Gain confidence in Islamic identity',
        'Build character based on Islamic values',
        'Connect Quranic teachings to contemporary challenges'
    ];

    /**
     * Materials and resources types for Islamic studies
     */
    protected array $materialTypes = [
        'Quran Workbook',
        'Tajweed Guide',
        'Arabic Flashcards',
        'Hadith Collection',
        'Seerah Timeline',
        'Islamic Ethics Handbook',
        'Islamic Art Templates',
        'Islamic History Maps',
        'Fiqh Practical Guide',
        'Dua Collection',
        'Arabic Grammar Worksheets',
        'Quran Stories for Children'
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Islamic Studies subject and subtopics
        $islamicStudies = Subject::create([
            'name' => 'Islamic Studies',
            'slug' => 'islamic-studies',
            'description' => 'Comprehensive Islamic education covering Quran, Hadith, Fiqh, and Islamic history to develop spiritual growth and practical knowledge.'
        ]);

        foreach ($this->islamicTopics as $topic) {
            Subject::create([
                'name' => $topic,
                'slug' => Str::slug($topic),
                'description' => $this->generateIslamicDescription($topic)
            ]);
        }

        // Fetch all subjects for later use
        $allSubjects = Subject::all();
        $islamicSubjects = $allSubjects->filter(function ($subject) {
            return $subject->name === 'Islamic Studies' ||
                   in_array($subject->name, $this->islamicTopics);
        });

        // Create Islamic Studies teachers with specialized profiles
        $islamicTeachers = collect();
        for ($i = 0; $i < 15; $i++) {
            $teacher = User::factory()->create([
                'name' => $this->getIslamicTeacherName(),
                'email' => 'islamic.teacher' . ($i + 1) . '@example.com',
                'role' => User::ROLE_TEACHER
            ]);

            $teacherProfile = TeacherProfile::factory()->create([
                'user_id' => $teacher->id,
                'status' => TeacherProfile::STATUS_VERIFIED
            ]);

            // Attach Islamic subjects (2-5 subjects per teacher)
            $teacher->refresh();
            $teacher->teacherProfile->subjects()->attach(
                $islamicSubjects->random(rand(2, 5))->pluck('id')->toArray()
            );

            $islamicTeachers->push($teacher);
        }

        // Create Muslim families with children
        for ($i = 0; $i < 25; $i++) {
            $parent = User::factory()->create([
                'name' => $this->getMuslimParentName(),
                'email' => 'muslim.parent' . ($i + 1) . '@example.com',
                'role' => User::ROLE_PARENT
            ]);

            $parentProfile = ParentProfile::factory()->create([
                'user_id' => $parent->id,
                'has_completed_profile' => true
            ]);

            // Create 1-4 children per family
            $childrenCount = rand(1, 4);
            for ($c = 0; $c < $childrenCount; $c++) {
                $child = Children::factory()->create([
                    'parent_profile_id' => $parentProfile->id,
                    'name' => $this->getMuslimChildName(),
                    'age' => $age = rand(5, 17),
                    'grade' => $this->getGradeFromAge($age),
                    'gender' => fake()->randomElement(['male', 'female']),
                    'school_name' => $this->getIslamicSchoolName()
                ]);

                // Attach Islamic subjects to child (1-3 subjects)
                $child->subjects()->attach(
                    $islamicSubjects->random(rand(1, 3))->pluck('id')->toArray()
                );
            }
        }

        // Create Islamic educational institutions as clients
        for ($i = 0; $i < 8; $i++) {
            $client = User::factory()->create([
                'name' => $this->getIslamicInstitutionContactName(),
                'email' => 'islamic.institution' . ($i + 1) . '@example.com',
                'role' => User::ROLE_CLIENT
            ]);

            $institutionName = $this->getIslamicInstitutionName();

            // Create client profile for Islamic institution
            ClientProfile::create([
                'user_id' => $client->id,
                'company_name' => $institutionName,
                'whatsapp' => fake()->phoneNumber(),
                'phone' => fake()->phoneNumber(),
                'website' => 'www.' . Str::slug($institutionName) . '.org',
                'position' => fake()->randomElement(['Director', 'Principal', 'Head of Islamic Studies', 'Administrator']),
                'address' => fake()->streetAddress(),
                'city' => fake()->city(),
                'country' => fake()->randomElement(['UAE', 'Saudi Arabia', 'Qatar', 'UK', 'USA', 'Canada', 'Malaysia', 'Indonesia']),
                'industry' => 'Islamic Education',
                'company_size' => fake()->randomElement(['1-10', '11-50', '51-200', '201-500']),
                'preferred_services' => ['Islamic curriculum development', 'Teacher training', 'Educational resources', 'Assessment tools'],
                'preferred_contact_method' => fake()->randomElement(['email', 'phone', 'whatsapp']),
                'notes' => 'Interested in comprehensive Islamic studies curriculum aligned with international standards.',
                'has_completed_profile' => true,
                'status' => ClientProfile::STATUS_APPROVED,
            ]);
        }

        /// Create Islamic Studies Courses
$islamicTeachers->each(function ($teacher) use ($islamicSubjects) {
    // Each teacher creates 2-4 courses
    $courseCount = rand(2, 4);

    for ($i = 0; $i < $courseCount; $i++) {
        $subject = $teacher->teacherProfile->subjects->random();
        $level = fake()->randomElement($this->levels);
        $name = $this->generateIslamicCourseName($subject->name, $level);

        // Create a more unique slug by adding teacher ID and a random string
        $uniqueId = $teacher->id . '-' . Str::random(5);
        $slug = Str::slug($subject->name . '-' . $level . '-' . $uniqueId);

        Course::create([
            'name' => $name,
            'slug' => $slug, // Use the more unique slug here
            'description' => $this->generateIslamicCourseDescription($subject->name, $level),
            'level' => $level,
            'duration' => fake()->randomElement([8, 12, 16, 24, 32]) . ' weeks',
            'price' => fake()->randomElement([199, 249, 299, 349, 399, 449]),
            'status' => 'active',
            'teacher_profile_id' => $teacher->teacherProfile->id,
            'subject_id' => $subject->id,
            'curriculum' => $this->generateIslamicCurriculum($subject->name),
            'prerequisites' => $this->generateIslamicPrerequisites($subject->name, $level),
            'learning_outcomes' => $this->generateIslamicLearningOutcomes($subject->name),
            'max_students' => fake()->randomElement([10, 15, 20, 25]),
            'start_date' => now()->addDays(rand(7, 30)),
            'end_date' => now()->addDays(rand(90, 180))
        ]);
    }
});

        // Create Islamic Studies Materials
        $courses = Course::all();
        $islamicTeachers->each(function ($teacher) use ($courses) {
            // Each teacher creates 5-10 materials
            $materialCount = rand(5, 10);

            for ($i = 0; $i < $materialCount; $i++) {
                $isPublic = fake()->boolean(70);
                $type = fake()->randomElement(['document', 'pdf', 'video', 'audio', 'presentation', 'link']);

                $material = Material::create([
                    'title' => $this->generateIslamicMaterialTitle(),
                    'description' => $this->generateIslamicMaterialDescription(),
                    'teacher_profile_id' => $teacher->teacherProfile->id,
                    'file_name' => 'sample_' . Str::slug(fake()->words(2, true)) . ($type === 'pdf' ? '.pdf' : '.docx'),
                    'file_type' => $type === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'file_size' => rand(100, 5000) * 1024, // Random size between 100KB and 5MB
                    'external_url' => $type === 'link' ? 'https://example.com/islamic-resources/' . rand(1000, 9999) : null,
                    'type' => $type,
                    'is_public' => $isPublic,
                    'is_featured' => fake()->boolean(30),
                ]);

                // Attach material to teacher's subjects (1-2 subjects)
                $material->subjects()->attach(
                    $teacher->teacherProfile->subjects->random(rand(1, 2))->pluck('id')->toArray()
                );

                // Attach to 0-2 courses if applicable
                if (fake()->boolean(70)) {
                    $teacherCourses = $courses->where('teacher_profile_id', $teacher->teacherProfile->id);
                    if ($teacherCourses->count() > 0) {
                        $material->courses()->attach(
                            $teacherCourses->random(min(2, $teacherCourses->count()))->pluck('id')->toArray()
                        );
                    }
                }
            }
        });

        // Create Islamic Studies Assessments
        $courses = Course::all();
        $islamicTeachers->each(function ($teacher) use ($courses, $islamicSubjects) {
            // Each teacher creates 3-6 assessments
            $assessmentCount = rand(3, 6);

            for ($i = 0; $i < $assessmentCount; $i++) {
                $subject = $teacher->teacherProfile->subjects->random();
                $teacherCourses = $courses->where('teacher_profile_id', $teacher->teacherProfile->id)
                                          ->where('subject_id', $subject->id);

                $courseId = null;
                if ($teacherCourses->count() > 0) {
                    $courseId = $teacherCourses->random()->id;
                }

                $totalPoints = fake()->randomElement([20, 25, 30, 40, 50, 100]);

                $assessment = Assessment::create([
                    'title' => $this->generateIslamicAssessmentTitle($subject->name),
                    'description' => $this->generateIslamicAssessmentDescription($subject->name),
                    'type' => fake()->randomElement(['quiz', 'test', 'exam', 'assignment']),
                    'teacher_profile_id' => $teacher->teacherProfile->id,
                    'course_id' => $courseId,
                    'subject_id' => $subject->id,
                    'total_points' => $totalPoints,
                    'passing_points' => round($totalPoints * 0.6),
                    'due_date' => now()->addDays(rand(14, 60)),
                    'start_date' => now()->addDays(rand(1, 10)),
                    'time_limit' => fake()->randomElement([30, 45, 60, 90, 120]),
                    'is_published' => fake()->boolean(80),
                    'instructions' => $this->generateIslamicAssessmentInstructions($subject->name),
                    'status' => fake()->randomElement(['draft', 'published', 'active']),
                ]);

                // Create questions for this assessment
                $this->createIslamicAssessmentQuestions($assessment, $subject->name);

                // Assign assessment to students if it's published
                if ($assessment->is_published) {
                    // Find children studying this subject
                    $children = Children::whereHas('subjects', function ($query) use ($subject) {
                        $query->where('subjects.id', $subject->id);
                    })->get();

                    // Assign to 5-15 random children
                    $assigneeCount = min(rand(5, 15), $children->count());
                    if ($assigneeCount > 0) {
                        $assessment->children()->attach(
                            $children->random($assigneeCount)->pluck('id')->toArray(),
                            [
                                'status' => fake()->randomElement(['not_started', 'in_progress', 'completed']),
                                'start_time' => now()->subDays(rand(1, 5)),
                                'score' => rand(0, 100)
                            ]
                        );
                    }
                }
            }
        });

        // Create Learning Sessions for Islamic Studies
        $children = Children::all();
        $children->each(function ($child) use ($islamicTeachers) {
            // Get subjects that this child is studying
            $childSubjects = $child->subjects;

            if ($childSubjects->count() === 0) {
                return;
            }

            // Find teachers who teach these subjects
            $relevantTeachers = $islamicTeachers->filter(function ($teacher) use ($childSubjects) {
                $teacherSubjectIds = $teacher->teacherProfile->subjects->pluck('id')->toArray();
                $childSubjectIds = $childSubjects->pluck('id')->toArray();
                return count(array_intersect($teacherSubjectIds, $childSubjectIds)) > 0;
            });

            if ($relevantTeachers->count() === 0) {
                return;
            }

            // Pick 1-3 teachers for this child
            $selectedTeachers = $relevantTeachers->random(min(3, $relevantTeachers->count()));

            $selectedTeachers->each(function ($teacher) use ($child, $childSubjects) {
                // Find matching subjects between teacher and child
                $matchingSubjects = $teacher->teacherProfile->subjects->filter(function ($subject) use ($childSubjects) {
                    return $childSubjects->contains('id', $subject->id);
                });

                if ($matchingSubjects->count() === 0) {
                    return;
                }

                $matchingSubjects->each(function ($subject) use ($teacher, $child) {
                    // Create past sessions (3-8 per subject)
                    for ($i = 0; $i < rand(3, 8); $i++) {
                        $startTime = now()->subDays(rand(7, 90))->setHour(rand(8, 19))->setMinute(0);

                        LearningSession::create([
                            'teacher_id' => $teacher->id,
                            'children_id' => $child->id,
                            'subject_id' => $subject->id,
                            'start_time' => $startTime,
                            'end_time' => (clone $startTime)->addMinutes(rand(4, 12) * 15),
                            'status' => LearningSession::STATUS_COMPLETED,
                            'attended' => fake()->boolean(90),
                            'performance_score' => fake()->randomFloat(1, 6.0, 10.0),
                            'location' => fake()->boolean(80) ? 'Online' : 'In-person',
                            'notes' => $this->generateIslamicSessionNotes($subject->name)
                        ]);
                    }

                    // Create upcoming sessions (2-5 per subject)
                    for ($i = 0; $i < rand(2, 5); $i++) {
                        $startTime = now()->addDays(rand(1, 14))->setHour(rand(8, 19))->setMinute(0);

                        LearningSession::create([
                            'teacher_id' => $teacher->id,
                            'children_id' => $child->id,
                            'subject_id' => $subject->id,
                            'start_time' => $startTime,
                            'end_time' => (clone $startTime)->addMinutes(rand(4, 12) * 15),
                            'status' => LearningSession::STATUS_SCHEDULED,
                            'location' => fake()->boolean(80) ? 'Online' : 'In-person',
                            'notes' => $this->generateIslamicSessionPreparationNotes($subject->name)
                        ]);
                    }
                });
            });
        });
    }

    /**
     * Generate Islamic-specific description for subjects
     */
    protected function generateIslamicDescription($topic): string
    {
        $descriptions = [
            'Quran Recitation' => 'Learn to recite the Holy Quran with proper tajweed rules and beautiful melody, focusing on correct pronunciation and rhythm.',
            'Quran Memorization' => 'Systematic approach to memorizing portions of the Holy Quran with emphasis on retention and understanding of memorized verses.',
            'Tajweed Rules' => 'Comprehensive study of the rules governing Quranic recitation, including proper pronunciation, articulation points, and qualities of letters.',
            'Quran Tafsir' => 'In-depth exploration of Quranic exegesis to understand the meaning, context, and application of Quranic verses in daily life.',
            'Hadith Studies' => 'Study of authenticated sayings and actions of Prophet Muhammad (PBUH) with focus on understanding their implications and applications.',
            'Fiqh (Islamic Jurisprudence)' => 'Study of Islamic law and jurisprudence covering worship practices, transactions, and social interactions in accordance with Shariah.',
            'Aqeedah (Islamic Creed)' => 'Exploration of Islamic theology and belief system, focusing on the articles of faith and their significance in a Muslim\'s life.',
            'Seerah (Prophetic Biography)' => 'Comprehensive study of the life, character, and teachings of Prophet Muhammad (PBUH) with lessons for contemporary Muslims.',
            'Islamic History' => 'Exploration of key events, civilizations, and personalities that shaped Islamic civilization from its inception to the modern era.',
            'Arabic Language for Quran' => 'Practical approach to learning Arabic specifically designed to help students understand the Quran in its original language.',
            'Islamic Ethics & Morals' => 'Study of the ethical framework of Islam, focusing on personal development, character building, and social interactions.',
            'Islamic Arts & Calligraphy' => 'Introduction to the rich tradition of Islamic arts, with focus on calligraphy, geometric patterns, and their spiritual significance.'
        ];

        return $descriptions[$topic] ?? 'Comprehensive Islamic education focusing on developing spiritual growth, moral character, and practical knowledge.';
    }

    /**
     * Generate Islamic teacher names
     */
    protected function getIslamicTeacherName(): string
    {
        $maleFirstNames = ['Muhammad', 'Ahmad', 'Omar', 'Ali', 'Ibrahim', 'Yusuf', 'Ismail', 'Abdullah', 'Hamza', 'Hassan', 'Hussein', 'Bilal', 'Zaid', 'Khalid', 'Tariq'];
        $femaleFirstNames = ['Fatima', 'Aisha', 'Khadija', 'Maryam', 'Zaynab', 'Hafsa', 'Asma', 'Sumaya', 'Nusaibah', 'Rabia', 'Aminah', 'Safiya', 'Ruqayyah', 'Hafsah', 'Jamilah'];
        $lastNames = ['Al-Farsi', 'Al-Rahman', 'Abdullah', 'Qureshi', 'Saeed', 'Khan', 'Rahman', 'Siddiqui', 'Malik', 'Ahmed', 'Mustafa', 'Hassan', 'Ibrahim', 'Mahmood', 'Rashid', 'Ali', 'Sheikh', 'Suleiman', 'Saleh', 'Karim'];
        $titles = ['Sh.', 'Ustadh', 'Maulana', 'Imam', 'Dr.', 'Hafiz', 'Qari', 'Mufti', ''];

        $gender = fake()->randomElement(['male', 'female']);
        $firstName = $gender === 'male'
            ? fake()->randomElement($maleFirstNames)
            : fake()->randomElement($femaleFirstNames);

        $lastName = fake()->randomElement($lastNames);
        $title = $gender === 'male' ? fake()->randomElement($titles) : fake()->randomElement(['Dr.', 'Ustadha', 'Hafiza', 'Qaria', '']);

        return ($title ? $title . ' ' : '') . $firstName . ' ' . $lastName;
    }

    /**
     * Generate Muslim parent names
     */
    protected function getMuslimParentName(): string
    {
        $maleFirstNames = ['Muhammad', 'Ahmed', 'Mahmoud', 'Ali', 'Omar', 'Yusuf', 'Ibrahim', 'Ismail', 'Abdullah', 'Samir', 'Tariq', 'Khalid', 'Hassan', 'Hussein', 'Jamal'];
        $femaleFirstNames = ['Fatima', 'Aisha', 'Khadija', 'Maryam', 'Zaynab', 'Leila', 'Noor', 'Amina', 'Safiya', 'Huda', 'Zainab', 'Salma', 'Jamila', 'Farida', 'Samira'];
        $lastNames = ['Abbas', 'Ahmed', 'Ali', 'Faruq', 'Hassan', 'Hussein', 'Ibrahim', 'Khalil', 'Malik', 'Mansour', 'Mustafa', 'Qureshi', 'Rahman', 'Saleh', 'Siddiqui', 'Khan', 'Patel', 'Mahmood', 'Al-Farsi', 'Al-Rahman'];

        $gender = fake()->randomElement(['male', 'female']);
        $firstName = $gender === 'male'
            ? fake()->randomElement($maleFirstNames)
            : fake()->randomElement($femaleFirstNames);

        $lastName = fake()->randomElement($lastNames);

        return $firstName . ' ' . $lastName;
    }

    /**
     * Generate Muslim child names
     */
    protected function getMuslimChildName(): string
    {
        $boyNames = ['Adam', 'Ali', 'Bilal', 'Danyal', 'Eisa', 'Faisal', 'Hamza', 'Ibrahim', 'Idris', 'Ilyas', 'Imran', 'Ismail', 'Kareem', 'Luqman', 'Muhammad', 'Mustafa', 'Omar', 'Rayyan', 'Sami', 'Tariq', 'Umar', 'Yahya', 'Yusuf', 'Zakariya', 'Zaid'];

        $girlNames = ['Aaliyah', 'Amina', 'Aisha', 'Asiya', 'Fatima', 'Hafsa', 'Hajar', 'Halima', 'Hana', 'Iman', 'Jannah', 'Khadija', 'Layla', 'Maryam', 'Naima', 'Noor', 'Rania', 'Ruqayya', 'Safiya', 'Salma', 'Sara', 'Sumaya', 'Zaynab', 'Zainab', 'Zara'];

        $lastNames = ['Abbas', 'Ahmed', 'Ali', 'Faruq', 'Hassan', 'Hussein', 'Ibrahim', 'Khalil', 'Malik', 'Mansour', 'Mustafa', 'Qureshi', 'Rahman', 'Saleh', 'Siddiqui', 'Khan', 'Patel', 'Mahmood', 'Al-Farsi', 'Al-Rahman'];

        $gender = fake()->randomElement(['male', 'female']);
        $firstName = $gender === 'male'
            ? fake()->randomElement($boyNames)
            : fake()->randomElement($girlNames);

        $lastName = fake()->randomElement($lastNames);

        return $firstName . ' ' . $lastName;
    }

    /**
     * Get grade based on age
     */
    protected function getGradeFromAge(int $age): string
    {
        if ($age < 6) return 'Preschool';
        if ($age === 6) return 'Kindergarten';
        if ($age >= 7 && $age <= 18) return 'Grade ' . ($age - 6);
        return 'High School';
    }

    /**
     * Generate Islamic school names
     */
    protected function getIslamicSchoolName(): string
    {
        $prefixes = ['Al-Nur', 'Al-Huda', 'Al-Falah', 'Al-Iman', 'Al-Hidayah', 'Al-Fajr', 'Al-Salam', 'Al-Furqan', 'Badr', 'Crescent', 'Darul Uloom', 'Al-Madinah', 'Al-Risalah', 'Al-Khair', 'Al-Rahma'];

        $suffixes = ['Islamic School', 'Academy', 'Elementary', 'School', 'Institute', 'International School', 'College', 'Educational Center', 'Primary School', 'Islamic Education Center'];

        return fake()->randomElement($prefixes) . ' ' . fake()->randomElement($suffixes);
    }

    /**
     * Generate Islamic institution names
     */
    protected function getIslamicInstitutionName(): string
    {
        $prefixes = ['Al-Azhar', 'Islamic Foundation of', 'Muslim Association of', 'International Islamic', 'Darul Uloom', 'Institute of Islamic', 'Islamic Center of', 'Quranic Studies', 'Center for Islamic', 'Academy of'];

        $suffixes = ['Knowledge', 'Education', 'Learning', 'Studies', 'Research', 'Sciences', 'Heritage', 'Development', 'Excellence'];

        $locations = ['North America', 'America', 'Canada', 'UK', 'Europe', 'London', 'California', 'New York', 'Toronto', 'Manchester', 'Birmingham', 'Global', 'International'];

        $pattern = fake()->randomElement([
            '{prefix} {suffix}',
            '{prefix} {location}',
            '{prefix} {suffix} {location}',
        ]);

        return str_replace(
            ['{prefix}', '{suffix}', '{location}'],
            [
                fake()->randomElement($prefixes),
                fake()->randomElement($suffixes),
                fake()->randomElement($locations)
            ],
            $pattern
        );
    }

    /**
     * Generate Islamic institution contact person name
     */
    protected function getIslamicInstitutionContactName(): string
    {
        $firstNames = ['Muhammad', 'Ahmed', 'Abdullah', 'Ibrahim', 'Yusuf', 'Ali', 'Omar', 'Hassan', 'Hussein', 'Ismail', 'Fatima', 'Aisha', 'Khadija', 'Maryam', 'Zaynab'];

        $lastNames = ['Khan', 'Rahman', 'Ahmed', 'Ali', 'Siddiqui', 'Qureshi', 'Mahmood', 'Malik', 'Sheikh', 'Abdullah', 'Farooq', 'Mustafa', 'Patel', 'Hassan', 'Ibrahim'];

        $titles = ['Dr.', 'Prof.', 'Sheikh', 'Imam', 'Ustadh', 'Mufti', 'Hafiz', 'Alim', ''];

        $title = fake()->randomElement($titles);
        $firstName = fake()->randomElement($firstNames);
        $lastName = fake()->randomElement($lastNames);

        return ($title ? $title . ' ' : '') . $firstName . ' ' . $lastName;
    }

    /**
     * Generate Islamic course name
     */
    protected function generateIslamicCourseName(string $subject, string $level): string
    {
        $coursePatterns = [
            'Quran Recitation' => [
                'Beginner' => ['Introduction to Quran Recitation', 'Basics of Quranic Reading', 'First Steps in Quran Recitation', 'Foundation of Tajweed'],
'Intermediate' => ['Intermediate Quran Recitation', 'Advancing in Tajweed', 'Melodious Quran Recitation', 'Perfecting Quranic Pronunciation'],
                'Advanced' => ['Advanced Tajweed Rules', 'Mastering Quran Recitation', 'Qira\'at Foundations', 'Expert Tajweed Application'],
                'Specialization' => ['Specialized Quranic Recitation', 'Ijazah Program in Recitation', 'Seven Modes of Recitation', 'Quranic Recitation Mastery']
            ],
            'Quran Memorization' => [
                'Beginner' => ['Introduction to Quran Memorization', 'Memorizing Short Surahs', 'Basics of Hifz', 'First Steps in Quran Memorization'],
                'Intermediate' => ['Intermediate Quran Memorization', 'Memorizing Juz Amma', 'Strengthening Memorization Skills', 'Juz Tabarak Memorization'],
                'Advanced' => ['Advanced Quran Memorization', 'Multiple Juz Memorization', 'Retention Techniques for Huffaz', 'Hifz Improvement Program'],
                'Specialization' => ['Complete Quran Memorization Track', 'Huffaz Certification Program', 'Ijazah in Quran Memorization', 'Quran Memorization Mastery']
            ],
            'Tajweed Rules' => [
                'Beginner' => ['Introduction to Tajweed', 'Basic Tajweed Rules', 'Articulation Points of Letters', 'Tajweed for Beginners'],
                'Intermediate' => ['Intermediate Tajweed Rules', 'Practical Tajweed Application', 'Rules of Noon and Meem', 'Tajweed Rule Mastery'],
                'Advanced' => ['Advanced Tajweed Studies', 'Detailed Rules of Tajweed', 'Tajweed Mastery Program', 'Expert Tajweed Application'],
                'Specialization' => ['Tajweed Certification Course', 'Ijazah in Tajweed', 'Teaching Tajweed Methodology', 'Tajweed Expert Certification']
            ],
            'Quran Tafsir' => [
                'Beginner' => ['Introduction to Quran Tafsir', 'Understanding Quranic Verses', 'Basics of Quranic Interpretation', 'Themes of the Quran'],
                'Intermediate' => ['Intermediate Quranic Exegesis', 'Thematic Study of Quran', 'Context and Revelation', 'Tafsir Methodology'],
                'Advanced' => ['Advanced Tafsir Studies', 'Comparative Tafsir Analysis', 'In-depth Quranic Interpretation', 'Scholarly Approaches to Tafsir'],
                'Specialization' => ['Specialized Tafsir Studies', 'Classical Tafsir Works', 'Contemporary Approaches to Tafsir', 'Tafsir Scholar Program']
            ],
            'Hadith Studies' => [
                'Beginner' => ['Introduction to Hadith', 'Understanding Prophetic Traditions', 'Basics of Hadith Literature', 'Forty Hadith for Beginners'],
                'Intermediate' => ['Intermediate Hadith Studies', 'Analysis of Selected Hadith', 'Hadith Terminology and Classifications', 'Sahih Collections Survey'],
                'Advanced' => ['Advanced Hadith Methodology', 'Hadith Authentication', 'Critical Hadith Analysis', 'Comprehensive Hadith Studies'],
                'Specialization' => ['Hadith Specialist Program', 'Sanad and Transmission Studies', 'Comparative Hadith Analysis', 'Hadith Scholar Certification']
            ],
            'Fiqh (Islamic Jurisprudence)' => [
                'Beginner' => ['Introduction to Islamic Fiqh', 'Basics of Islamic Jurisprudence', 'Fiqh of Worship', 'Practical Islamic Rules'],
                'Intermediate' => ['Intermediate Fiqh Studies', 'Comparative Fiqh Analysis', 'Fiqh of Family and Society', 'Applied Islamic Jurisprudence'],
                'Advanced' => ['Advanced Fiqh Methodology', 'Specialized Fiqh Topics', 'Contemporary Fiqh Issues', 'Fiqh of Modern Transactions'],
                'Specialization' => ['Fiqh Specialist Program', 'Ijtihad and Legal Theory', 'Fatwa Methodology', 'Comparative Schools of Law']
            ],
            'Islamic History' => [
                'Beginner' => ['Introduction to Islamic History', 'Early Islamic Era', 'Life of Prophet Muhammad', 'Rightly Guided Caliphs'],
                'Intermediate' => ['Golden Age of Islam', 'Islamic Civilization', 'Muslim Contributions to Science', 'Islamic Dynasties'],
                'Advanced' => ['Advanced Islamic Historical Studies', 'Muslim World History', 'Islam in Modern History', 'Historical Analysis Methods'],
                'Specialization' => ['Specialized Islamic Historical Research', 'Manuscript Studies and Analysis', 'Historical Preservation', 'Islamic Archaeological Studies']
            ],
            'Arabic Language for Quran' => [
                'Beginner' => ['Arabic Alphabet & Pronunciation', 'Quranic Arabic Basics', 'Arabic for Quran Beginners', 'First Steps in Quranic Arabic'],
                'Intermediate' => ['Intermediate Quranic Arabic', 'Arabic Grammar for Quran Reading', 'Vocabulary from the Quran', 'Arabic Morphology Basics'],
                'Advanced' => ['Advanced Quranic Arabic', 'Arabic Rhetoric in Quran', 'Literary Analysis of Quranic Text', 'Quranic Arabic Structures'],
                'Specialization' => ['Quranic Arabic Mastery', 'Arabic Linguistics for Quran Specialists', 'Classical Arabic Text Analysis', 'Quran Translation Methodology']
            ],
            'Aqeedah (Islamic Creed)' => [
                'Beginner' => ['Introduction to Islamic Beliefs', 'Foundations of Aqeedah', 'Pillars of Faith', 'Understanding Tawheed'],
                'Intermediate' => ['Intermediate Aqeedah Studies', 'Divine Names and Attributes', 'Prophets and Messengers in Islam', 'Angels and Unseen Realm'],
                'Advanced' => ['Advanced Islamic Theology', 'Comparative Theology', 'Responding to Misconceptions', 'Contemporary Creedal Challenges'],
                'Specialization' => ['Specialized Creedal Studies', 'Classical Aqeedah Texts', 'Islamic Theological Discourse', 'Defending Islamic Beliefs']
            ],
            'Seerah (Prophetic Biography)' => [
                'Beginner' => ['Introduction to Prophet Muhammad\'s Life', 'Early Meccan Period', 'Hijrah and Establishment of Madinah', 'Battles and Expeditions'],
                'Intermediate' => ['In-depth Seerah Analysis', 'Prophetic Character and Attributes', 'Social Reforms of the Prophet', 'Companions of the Prophet'],
                'Advanced' => ['Advanced Seerah Studies', 'Analytical Biography Approach', 'Prophetic Leadership Model', 'Contemporary Applications of Seerah'],
                'Specialization' => ['Seerah Research Methodology', 'Specialized Studies in Prophetic Traditions', 'Critical Analysis of Seerah Texts', 'Comparative Biographical Studies']
            ],
            'Islamic Ethics & Morals' => [
                'Beginner' => ['Introduction to Islamic Ethics', 'Character Building in Islam', 'Etiquette of the Muslim', 'Islamic Moral Values'],
                'Intermediate' => ['Advanced Islamic Ethics', 'Social Ethics in Islam', 'Business Ethics from Islamic Perspective', 'Family Ethics in Islam'],
                'Advanced' => ['Professional Ethics in Islamic Context', 'Contemporary Ethical Dilemmas', 'Bioethics in Islam', 'Environmental Ethics in Islam'],
                'Specialization' => ['Islamic Ethics in Modern Society', 'Research in Islamic Moral Theory', 'Comparative Ethical Systems', 'Islamic Ethics in Leadership']
            ],
            'Islamic Arts & Calligraphy' => [
                'Beginner' => ['Introduction to Islamic Arts', 'Basics of Arabic Calligraphy', 'Islamic Geometric Patterns', 'Islamic Art Appreciation'],
                'Intermediate' => ['Intermediate Calligraphy Techniques', 'Islamic Arabesque Design', 'Traditional Islamic Illumination', 'Regional Islamic Art Styles'],
                'Advanced' => ['Advanced Calligraphic Scripts', 'Islamic Architectural Design', 'Contemporary Islamic Art Forms', 'Master Classes in Calligraphy'],
                'Specialization' => ['Professional Islamic Art Portfolio', 'Specialized Script Mastery', 'Islamic Art Conservation', 'Research in Islamic Aesthetics']
            ]
        ];

        $defaultPatterns = [
            'Beginner' => ['Introduction to {subject}', 'Foundations of {subject}', 'Basics of {subject}', '{subject} for Beginners'],
            'Intermediate' => ['Intermediate {subject}', 'Advancing in {subject}', 'Deepening {subject} Knowledge', 'Practical {subject}'],
            'Advanced' => ['Advanced {subject}', 'Mastering {subject}', 'Comprehensive {subject}', 'Expert {subject} Training'],
            'Specialization' => ['Specialized {subject}', 'Professional {subject} Certification', '{subject} Mastery Program', 'Expert {subject} Studies']
        ];

        if (isset($coursePatterns[$subject]) && isset($coursePatterns[$subject][$level])) {
            return fake()->randomElement($coursePatterns[$subject][$level]);
        } else {
            $pattern = fake()->randomElement($defaultPatterns[$level]);
            return str_replace('{subject}', $subject, $pattern);
        }
    }

    /**
     * Generate Islamic course description
     */
    protected function generateIslamicCourseDescription(string $subject, string $level): string
    {
        $descriptions = [
            'Quran Recitation' => [
                'Beginner' => 'Learn the fundamentals of proper Quran recitation with correct pronunciation of Arabic letters and basic tajweed rules. Suitable for absolute beginners wanting to start their Quranic journey.',
                'Intermediate' => 'Enhance your Quran recitation skills with more detailed tajweed rules, rhythm, and melodious techniques. For students who already know basic recitation.',
                'Advanced' => 'Perfect your Quranic recitation with advanced tajweed rules, maqamat (melodious patterns), and expert-level pronunciation techniques.',
                'Specialization' => 'A professional-level course for those seeking ijazah (certification) in Quran recitation, covering all qira\'at variations and recitation styles.'
            ],
            'Quran Memorization' => [
                'Beginner' => 'Start your journey of memorizing the Quran with effective techniques, beginning with short surahs from Juz Amma. Includes memorization strategies and regular revision methods.',
                'Intermediate' => 'Progress in your Hifz journey by memorizing longer portions of the Quran with advanced retention techniques and structured revision systems.',
                'Advanced' => 'Comprehensive program for serious Hifz students aiming to memorize multiple juz with perfect retention, including specialized memory techniques.',
                'Specialization' => 'Complete Hifz program designed for full Quran memorization with certification, including ijazah pathway and teaching methodology.'
            ]
        ];

        $defaultDescriptions = [
            'Beginner' => 'An introductory course to {subject}, covering foundational concepts and basic principles. Designed for students with no prior knowledge, this course provides a solid foundation for further studies.',
            'Intermediate' => 'Build upon your basic knowledge of {subject} with more detailed concepts and practical applications. This course bridges fundamental understanding with advanced topics.',
            'Advanced' => 'An in-depth exploration of {subject} for students with strong foundational knowledge. Covers complex topics, detailed analysis, and specialized applications.',
            'Specialization' => 'Expert-level certification program in {subject} for serious students pursuing professional expertise. Includes comprehensive coverage of specialized topics and practical mastery.'
        ];

        if (isset($descriptions[$subject]) && isset($descriptions[$subject][$level])) {
            return $descriptions[$subject][$level];
        } else {
            return str_replace('{subject}', $subject, $defaultDescriptions[$level]);
        }
    }

    /**
     * Generate Islamic curriculum
     */
    protected function generateIslamicCurriculum(string $subject): array
    {
        $standardModules = [
            'Module 1: Introduction and Fundamentals',
            'Module 2: Core Concepts and Principles',
            'Module 3: Practical Applications',
            'Module 4: Advanced Topics',
            'Module 5: Integration and Mastery',
            'Module 6: Assessment and Certification'
        ];

        $curriculumBySubject = [
            'Quran Recitation' => [
                'Module 1: Pronunciation of Arabic Letters (Makharij)',
                'Module 2: Essential Tajweed Rules',
                'Module 3: Rules of Noon and Meem',
                'Module 4: Rules of Madd (Elongation)',
                'Module 5: Stopping and Starting (Waqf and Ibtida)',
                'Module 6: Melodious Recitation Techniques',
                'Module 7: Practical Recitation Sessions',
                'Module 8: Final Assessment and Evaluation'
            ],
            'Quran Memorization' => [
                'Module 1: Memorization Techniques and Methods',
                'Module 2: Short Surah Memorization (Al-Fatiha and Last 10 Surahs)',
                'Module 3: Effective Revision Strategies',
                'Module 4: Memory Enhancement Techniques',
                'Module 5: Building Memorization Stamina',
                'Module 6: Connection with Meaning',
                'Module 7: Practical Memorization Sessions',
                'Module 8: Final Assessment and Certification'
            ],
            'Tajweed Rules' => [
                'Module 1: Introduction to Tajweed Science',
                'Module 2: Articulation Points of Letters',
                'Module 3: Characteristics of Letters',
                'Module 4: Rules of Noon Sakinah and Tanween',
                'Module 5: Rules of Meem Sakinah',
                'Module 6: Rules of Madd (Elongation)',
                'Module 7: Special Letters and Pronunciations',
                'Module 8: Practical Application and Assessment'
            ],
            'Fiqh (Islamic Jurisprudence)' => [
                'Module 1: Introduction to Fiqh and Legal Schools',
                'Module 2: Purification and Prayer',
                'Module 3: Zakat and Charity',
                'Module 4: Fasting and Ramadan',
                'Module 5: Hajj and Umrah',
                'Module 6: Family Law in Islam',
                'Module 7: Business Transactions in Islam',
                'Module 8: Contemporary Fiqh Issues'
            ],
            'Arabic Language for Quran' => [
                'Module 1: Arabic Alphabet and Pronunciation',
                'Module 2: Essential Vocabulary from the Quran',
                'Module 3: Basic Arabic Grammar Rules',
                'Module 4: Noun and Verb Forms in Quran',
                'Module 5: Understanding Quranic Structures',
                'Module 6: Translation Techniques',
                'Module 7: Contextual Understanding',
                'Module 8: Practical Quran Reading with Understanding'
            ]
        ];

        if (isset($curriculumBySubject[$subject])) {
            // Return 5-8 modules from the specialized curriculum
            return collect($curriculumBySubject[$subject])
                ->random(rand(5, 8))
                ->values()
                ->toArray();
        }

        // For other subjects, return 4-6 standard modules
        return collect($standardModules)
            ->random(rand(4, 6))
            ->values()
            ->toArray();
    }

    /**
     * Generate Islamic prerequisites
     */
    protected function generateIslamicPrerequisites(string $subject, string $level): array
    {
        if ($level === 'Beginner') {
            return ['No previous knowledge required', 'Basic literacy', 'Enthusiasm to learn'];
        }

        $prerequisitesBySubject = [
            'Quran Recitation' => [
                'Intermediate' => ['Basic knowledge of Arabic letters', 'Ability to read simple Quranic text', 'Completion of beginner Quran recitation course'],
                'Advanced' => ['Strong foundation in tajweed rules', 'Fluent recitation of Quran with basic tajweed', 'Completion of intermediate recitation course'],
                'Specialization' => ['Advanced tajweed knowledge', 'Proficient Quran recitation skills', 'Minimum 5 juz memorized', 'Recommendation from previous instructor']
            ],
            'Quran Memorization' => [
                'Intermediate' => ['Memorization of at least 10 short surahs', 'Basic tajweed knowledge', 'Regular practice habits'],
                'Advanced' => ['Memorization of at least Juz Amma', 'Strong revision discipline', 'Good tajweed application'],
                'Specialization' => ['Memorization of minimum 5 juz', 'Excellent retention ability', 'Strong tajweed foundation']
            ],
            'Arabic Language for Quran' => [
                'Intermediate' => ['Knowledge of Arabic alphabet', 'Basic vocabulary knowledge', 'Ability to read simple Arabic text'],
                'Advanced' => ['Understanding of basic Arabic grammar', 'Vocabulary of 500+ Arabic words', 'Ability to form simple sentences'],
                'Specialization' => ['Strong foundation in Arabic grammar', 'Reading comprehension skills', 'Basic translation abilities']
            ]
        ];

        $defaultPrerequisites = [
            'Intermediate' => [
                'Completion of beginner level course or equivalent knowledge',
                'Basic understanding of fundamental concepts',
                'Regular practice and commitment'
            ],
            'Advanced' => [
                'Completion of intermediate level course',
                'Strong foundation in core principles',
                'Demonstrated proficiency in basic applications',
                'Consistent practice habits'
            ],
            'Specialization' => [
                'Completion of advanced level course',
                'Exceptional knowledge of the subject',
                'Previous practical experience',
                'Strong commitment to mastery',
                'Recommendation from previous instructor'
            ]
        ];

        if (isset($prerequisitesBySubject[$subject]) && isset($prerequisitesBySubject[$subject][$level])) {
            return $prerequisitesBySubject[$subject][$level];
        }

        return $defaultPrerequisites[$level];
    }

    /**
     * Generate Islamic learning outcomes
     */
    protected function generateIslamicLearningOutcomes(string $subject): array
    {
        $outcomesBySubject = [
            'Quran Recitation' => [
                'Correctly pronounce all Arabic letters from their proper articulation points',
                'Apply essential tajweed rules during recitation',
                'Recognize common recitation mistakes and correct them',
                'Recite selected surahs with proper rhythm and melody',
                'Understand the importance of proper recitation in preserving the Quran',
                'Develop confidence in reciting Quran in front of others',
                'Apply rules of stopping and starting in Quranic recitation'
            ],
            'Quran Memorization' => [
                'Memorize assigned portions of the Quran with perfect accuracy',
                'Develop effective techniques for quick memorization',
                'Establish a consistent revision system to retain memorized portions',
                'Connect with the meaning of memorized verses',
                'Recite memorized portions with proper tajweed',
                'Build mental focus and memory strength',
                'Track progress and set realistic memorization goals'
            ],
            'Tajweed Rules' => [
                'Understand the scientific basis of tajweed rules',
                'Identify the articulation points of all Arabic letters',
                'Apply rules of noon sakinah and tanween correctly',
                'Implement rules of meem sakinah properly',
                'Recognize and apply different types of madd (elongation)',
                'Identify heavy and light letters and pronounce them correctly',
                'Apply rules of waqf (stopping) and ibtida (starting) in recitation'
            ],
            'Fiqh (Islamic Jurisprudence)' => [
                'Understand the sources of Islamic law and their application',
                'Perform acts of worship with proper knowledge of their rules',
                'Apply Islamic principles to contemporary situations',
                'Compare approaches of different legal schools on key issues',
                'Analyze case studies using principles of Islamic jurisprudence',
                'Develop awareness of ethical dimensions in legal rulings',
                'Recognize the flexibility and adaptability within Islamic law'
            ],
            'Arabic Language for Quran' => [
                'Read Quranic text with correct pronunciation',
                'Recognize common vocabulary used in the Quran',
                'Understand basic grammatical structures in Quranic verses',
                'Translate selected verses with proper context',
                'Identify root words and their derivatives in the Quran',
                'Appreciate the linguistic beauty of Quranic expressions',
                'Connect language understanding with deeper comprehension of meaning'
            ]
        ];

        if (isset($outcomesBySubject[$subject])) {
            // Return 4-7 outcomes from the specialized list
            return collect($outcomesBySubject[$subject])
                ->random(rand(4, 7))
                ->values()
                ->toArray();
        }

        // For other subjects, return 4-6 outcomes from the general list
        return collect($this->learningOutcomes)
            ->random(rand(4, 6))
            ->values()
            ->toArray();
    }

    /**
     * Generate Islamic material title
     */
    protected function generateIslamicMaterialTitle(): string
    {
        $titles = [
            'Essential Tajweed Rules Chart',
            'Quran Memorization Tracker',
            'Arabic Letters Pronunciation Guide',
            'Common Quranic Words Vocabulary List',
            'Prophetic Seerah Timeline',
            'Islamic History Maps Collection',
            'Fiqh of Salah Illustrated Guide',
            'Ramadan Worship Planner',
            'Hadith Collection: 40 Hadith Nawawi',
            'Islamic Character Building Workbook',
            'Quran Stories for Children',
            'Arabic Grammar Essentials',
            'Islamic Etiquette Handbook',
            'Dua Collection from Quran and Sunnah',
            'Islamic Civilization Contributions',
            'Prophets in the Quran Reference Guide',
            'Rules of Quran Recitation Visual Aid',
            'Islamic Arts Patterns Template',
            'Muslim Scientists Biography Collection',
            'Islamic Finance Principles Overview'
        ];

        return fake()->randomElement($titles) . ' ' . fake()->optional(0.5, '')->randomElement(['- Level 1', '- Advanced', '- Complete Edition', '- Revised', '- Teacher\'s Edition', '- Student Workbook']);
    }

    /**
     * Generate Islamic material description
     */
    protected function generateIslamicMaterialDescription(): string
    {
        $descriptions = [
            'Comprehensive resource for understanding and applying the essential rules of tajweed in Quran recitation. Perfect for beginners and intermediate students.',
            'A structured guide to help students memorize portions of the Quran with effective techniques and tracking tools for consistent progress.',
            'Detailed explanation of Arabic letters with pronunciation guides, articulation points, and common mistakes to avoid when reading Quran.',
            'Collection of the most frequently occurring words in the Quran with meanings and contextual examples to enhance understanding while reading.',
            'A visual timeline depicting key events in the life of Prophet Muhammad (PBUH) with important lessons and reflections for modern Muslims.',
            'Interactive resource for teaching Islamic jurisprudence in a practical, accessible way. Covers essential fiqh topics with clear explanations and examples.',
            'Beautifully designed collection of daily supplications from authentic sources, organized by situations and times of day for easy reference.',
            'Educational workbook focusing on character development based on Islamic principles, suitable for children and youth with activities and reflections.',
            'Reference guide to the Arabic grammar essentials specifically needed for understanding Quranic language and structure.',
            'Compilation of authentic hadith with explanations, contextual information, and practical applications for contemporary Muslims.'
        ];

        return fake()->randomElement($descriptions);
    }

    /**
     * Generate Islamic assessment title
     */
    protected function generateIslamicAssessmentTitle(string $subject): string
    {
        $titles = [
            'Quran Recitation' => [
                'Tajweed Rules Assessment',
                'Quran Recitation Proficiency Test',
                'Makharij Al-Huroof Evaluation',
                'Surah Recitation Assessment',
                'Melodic Recitation (Tarteel) Evaluation'
            ],
            'Quran Memorization' => [
                'Surah Memorization Test',
                'Juz Amma Recitation Assessment',
                'Quran Memorization Evaluation',
                'Hifz Progress Assessment',
                'Memorization Retention Test'
            ],
            'Tajweed Rules' => [
                'Tajweed Rules Comprehensive Test',
                'Noon and Meem Rules Assessment',
                'Madd Rules Evaluation',
                'Waqf and Ibtida Assessment',
                'Tajweed Application Test'
            ],
            'Arabic Language for Quran' => [
                'Quranic Arabic Vocabulary Test',
                'Arabic Grammar Assessment',
                'Quran Translation Skills Evaluation',
                'Arabic Reading Comprehension Test',
                'Quranic Language Structures Assessment'
            ],
            'Fiqh (Islamic Jurisprudence)' => [
                'Islamic Jurisprudence Assessment',
                'Fiqh of Worship Test',
                'Islamic Transactions Evaluation',
                'Contemporary Fiqh Issues Quiz',
                'Comparative Fiqh Assessment'
            ]
        ];

        $defaultTitles = [
            'Comprehensive Assessment',
            'Mid-Term Evaluation',
            'Final Progress Test',
            'Knowledge Check',
            'Skills Evaluation',
            'Practical Application Assessment',
            'Comprehensive Mastery Test'
        ];

        if (isset($titles[$subject])) {
            return fake()->randomElement($titles[$subject]);
        }

        return $subject . ' ' . fake()->randomElement($defaultTitles);
    }

    /**
     * Generate Islamic assessment description
     */
    protected function generateIslamicAssessmentDescription(string $subject): string
    {
        $descriptions = [
            'Quran Recitation' => 'Comprehensive assessment of Quranic recitation skills, including proper pronunciation, tajweed rules application, and fluency. Students will be evaluated on accuracy, rhythm, and overall quality of recitation.',
            'Quran Memorization' => 'Evaluation of memorization accuracy, retention, and recitation quality of assigned Quranic portions. Students will demonstrate memorization with proper tajweed and understanding of basic meanings.',
            'Tajweed Rules' => 'Assessment of theoretical knowledge and practical application of tajweed rules. Includes identification of rules, explanation of their purpose, and demonstration through practical recitation.',
            'Arabic Language for Quran' => 'Evaluation of Quranic Arabic language skills including vocabulary recognition, basic grammar understanding, and ability to extract meaning from simple Quranic verses.',
            'Fiqh (Islamic Jurisprudence)' => 'Comprehensive assessment of understanding Islamic legal principles, their application to worship and daily situations, and ability to analyze simple cases using fiqh methodology.'
        ];

        if (isset($descriptions[$subject])) {
            return $descriptions[$subject];
        }

        return "Comprehensive assessment of {$subject} knowledge and skills. This evaluation measures understanding of core concepts, practical application abilities, and overall progress in the subject area.";
    }

    /**
     * Generate Islamic assessment instructions
     */
    protected function generateIslamicAssessmentInstructions(string $subject): string
    {
        $instructions = [
            'Quran Recitation' => "1. Prepare the assigned surahs for recitation\n2. Recite with proper tajweed and melody\n3. Pay attention to correct pronunciation of all letters\n4. Apply all relevant tajweed rules\n5. Maintain appropriate pace and rhythm\n6. Be prepared to explain the rules you're applying",
            'Quran Memorization' => "1. Revise the assigned portions thoroughly before assessment\n2. Recite from memory without hesitation or mistakes\n3. Apply proper tajweed rules during recitation\n4. Be prepared to start recitation from random points within memorized portions\n5. Demonstrate understanding of basic meanings",
            'Tajweed Rules' => "1. Study all tajweed rules covered in the course\n2. Be prepared to identify rules in given Quranic verses\n3. Explain the purpose and application of each rule\n4. Demonstrate correct application in recitation\n5. Complete both written and oral components of the assessment",
            'Arabic Language for Quran' => "1. Review all vocabulary and grammar concepts\n2. Practice reading and translating sample Quranic verses\n3. Be prepared to identify word types and grammatical structures\n4. Complete vocabulary, grammar, and comprehension sections\n5. Demonstrate ability to derive basic meanings from Quranic text"
        ];

        if (isset($instructions[$subject])) {
            return $instructions[$subject];
        }

        return "1. Review all course materials thoroughly before the assessment\n2. Answer all questions honestly and to the best of your ability\n3. For practical demonstrations, ensure you've practiced sufficiently\n4. Complete all sections of the assessment\n5. Manage your time wisely to complete all questions\n6. Present your answers clearly and concisely";
    }

    /**
     * Create Islamic assessment questions
     */
    protected function createIslamicAssessmentQuestions($assessment, string $subject): void
    {
        $questionsBySubject = [
            'Quran Recitation' => [
                [
                    'question' => 'What is the correct articulation point (makhraj) for the letter ض?',
                    'type' => 'multiple_choice',
                    'options' => ['The tip of the tongue touching the upper incisors', 'One side of the tongue touching the upper molars', 'The back of the tongue touching the roof of the mouth', 'The middle of the tongue touching the roof of the mouth'],
                    'correct_answer' => 'One side of the tongue touching the upper molars',
                    'points' => 5
                ],
                [
                    'question' => 'Which of the following is a correct application of Ikhfa (Hidden Noon)?',
                    'type' => 'multiple_choice',
                    'options' => ['مِنْ بَعْدِ', 'مَنْ يَقُولُ', 'أَنْهَارٌ', 'مِنْ مَسَدٍ'],
                    'correct_answer' => 'مَنْ يَقُولُ',
                    'points' => 5
                ],
                [
                    'question' => 'Which surah should be recited with a sad tone according to the scholars of recitation?',
                    'type' => 'multiple_choice',
                    'options' => ['Surah Yaseen', 'Surah Rahman', 'Surah Duha', 'Surah Mulk'],
                    'correct_answer' => 'Surah Duha',
                    'points' => 5
                ],
                [
                    'question' => 'Identify and explain all tajweed rules in the verse: وَمَا أَدْرَاكَ مَا يَوْمُ الدِّينِ',
'type' => 'essay',
                    'correct_answer' => null,
                    'points' => 10
                ],
                [
                    'question' => 'True or False: Iqlab occurs when noon saakinah or tanween is followed by the letter meem.',
                    'type' => 'true_false',
                    'options' => ['True', 'False'],
                    'correct_answer' => 'False',
                    'points' => 5
                ]
            ],
            'Quran Memorization' => [
                [
                    'question' => 'Recite Surah Al-Asr from memory with proper tajweed.',
                    'type' => 'essay',
                    'correct_answer' => null,
                    'points' => 10
                ],
                [
                    'question' => 'What comes after the verse: وَالْعَصْرِ',
                    'type' => 'multiple_choice',
                    'options' => ['إِنَّ الْإِنسَانَ لَفِي خُسْرٍ', 'إِنَّا أَعْطَيْنَاكَ الْكَوْثَرَ', 'الرَّحْمَٰنُ عَلَّمَ الْقُرْآنَ', 'وَالشَّمْسِ وَضُحَاهَا'],
                    'correct_answer' => 'إِنَّ الْإِنسَانَ لَفِي خُسْرٍ',
                    'points' => 5
                ],
                [
                    'question' => 'Which of these verses appears in Surah Al-Ikhlas?',
                    'type' => 'multiple_choice',
                    'options' => ['قُلْ أَعُوذُ بِرَبِّ النَّاسِ', 'تَبَّتْ يَدَا أَبِي لَهَبٍ وَتَبَّ', 'لَمْ يَلِدْ وَلَمْ يُولَدْ', 'أَلْهَاكُمُ التَّكَاثُرُ'],
                    'correct_answer' => 'لَمْ يَلِدْ وَلَمْ يُولَدْ',
                    'points' => 5
                ],
                [
                    'question' => 'What is the most effective strategy for maintaining long-term memorization of the Quran?',
                    'type' => 'multiple_choice',
                    'options' => ['Memorize quickly and move to the next portion', 'Regular revision of previously memorized portions', 'Focus only on new portions', 'Memorize only on weekends'],
                    'correct_answer' => 'Regular revision of previously memorized portions',
                    'points' => 5
                ]
            ],
            'Arabic Language for Quran' => [
                [
                    'question' => 'Identify the correct root letters for the word "يَعْلَمُونَ"',
                    'type' => 'multiple_choice',
                    'options' => ['ع ل م', 'ي ع ل', 'ع م ل', 'و ن ي'],
                    'correct_answer' => 'ع ل م',
                    'points' => 5
                ],
                [
                    'question' => 'Translate the following Quranic phrase: "الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ"',
                    'type' => 'essay',
                    'correct_answer' => null,
                    'points' => 10
                ],
                [
                    'question' => 'Which of the following is a verb in the past tense?',
                    'type' => 'multiple_choice',
                    'options' => ['يَقُولُ', 'قَالَ', 'قُلْ', 'يَقُولُونَ'],
                    'correct_answer' => 'قَالَ',
                    'points' => 5
                ]
            ],
            'Fiqh (Islamic Jurisprudence)' => [
                [
                    'question' => 'Which of the following is NOT one of the primary sources of Islamic law?',
                    'type' => 'multiple_choice',
                    'options' => ['Quran', 'Sunnah', 'Ijma (consensus)', 'Fatwa'],
                    'correct_answer' => 'Fatwa',
                    'points' => 5
                ],
                [
                    'question' => 'Explain the concept of Istihsan (juristic preference) and provide an example of its application.',
                    'type' => 'essay',
                    'correct_answer' => null,
                    'points' => 10
                ],
                [
                    'question' => 'True or False: The Hanafi school is the strictest in terms of conditions for prayer validity.',
                    'type' => 'true_false',
                    'options' => ['True', 'False'],
                    'correct_answer' => 'False',
                    'points' => 5
                ]
            ]
        ];

        // Default questions for subjects without specific questions
        $defaultQuestions = [
            [
                'question' => 'What is the main objective of studying ' . $subject . '?',
                'type' => 'essay',
                'correct_answer' => null,
                'points' => 10
            ],
            [
                'question' => 'True or False: ' . $subject . ' is considered one of the core disciplines of Islamic studies.',
                'type' => 'true_false',
                'options' => ['True', 'False'],
                'correct_answer' => 'True',
                'points' => 5
            ],
            [
                'question' => 'Which of the following best describes the importance of ' . $subject . ' in Islamic education?',
                'type' => 'multiple_choice',
                'options' => [
                    'It provides practical guidance for daily life',
                    'It strengthens spiritual connection with Allah',
                    'It preserves Islamic tradition and heritage',
                    'It develops critical thinking and analytical skills'
                ],
                'correct_answer' => 'It preserves Islamic tradition and heritage',
                'points' => 5
            ]
        ];

        // Determine which questions to use
        $questions = isset($questionsBySubject[$subject]) ? $questionsBySubject[$subject] : $defaultQuestions;

        // Add 3-5 random questions to the assessment
        $questionCount = min(count($questions), rand(3, 5));
        $selectedQuestions = array_slice($questions, 0, $questionCount);

        // Create the questions
        foreach ($selectedQuestions as $index => $questionData) {
            AssessmentQuestion::create([
                'assessment_id' => $assessment->id,
                'question' => $questionData['question'],
                'type' => $questionData['type'],
                'options' => isset($questionData['options']) ? $questionData['options'] : null,
                'correct_answer' => $questionData['correct_answer'],
                'points' => $questionData['points'],
                'order' => $index + 1,
            ]);
        }
    }

    /**
     * Generate Islamic session notes
     */
    protected function generateIslamicSessionNotes(string $subject): string
    {
        $notesBySubject = [
            'Quran Recitation' => [
                'Student showed improvement in proper pronunciation of heavy letters. Still needs practice with rules of madd.',
                'Excellent progress in recitation fluency. Successfully applied rules of noon sakinah and tanween correctly.',
                'Worked on surah Al-Fatiha with focus on proper articulation points. Student is showing good progress.',
                'Reviewed tajweed rules for Surah Al-Ikhlas. Student needs more practice with stopping points.',
                'Student demonstrated good melody in recitation. Continue to work on letter pronunciation, especially ض and ظ.'
            ],
            'Quran Memorization' => [
                'Successfully memorized verses 1-5 of Surah Al-Mulk. Revision of previously memorized portions was excellent.',
                'Some difficulty with retaining last week\'s assignment. We reviewed memorization techniques and practiced repetition methods.',
                'Great progress in memorization. Student has completed half of Juz Amma with excellent retention.',
                'Focused on connecting meaning with memorization to improve retention. Student responded well to this approach.',
                'Reviewed previously memorized surahs with only minor errors. Assigned next portion with guidance on revision schedule.'
            ],
            'Tajweed Rules' => [
                'Covered rules of Ikhfa with practical examples. Student is grasping the concept well.',
                'Focused on articulation points of letters. Student needs more practice with throat letters.',
                'Reviewed rules of madd and their application in Surah Rahman. Student shows good understanding.',
                'Student is progressing well in identifying tajweed rules in text. Next session will focus on practical application.',
                'Worked on special pronunciation of Qalqalah letters. Student is showing improvement in recognizing and applying the rule.'
            ],
            'Arabic Language for Quran' => [
                'Introduced new vocabulary from Surah Yasin. Student is building a good Quranic vocabulary base.',
                'Practiced verb forms and their identification in Quranic text. Student needs more practice with past tense verbs.',
                'Student is making good progress in recognizing word patterns. We worked on extracting root letters from derivatives.',
                'Focused on basic sentence structures in the Quran. Student is beginning to understand simple verses without translation.',
                'Reviewed Arabic grammar rules with Quranic examples. Student shows improved understanding of noun cases.'
            ],
            'Fiqh (Islamic Jurisprudence)' => [
                'Covered the fiqh of salah with focus on conditions and nullifiers. Student has a good grasp of the basics.',
                'Discussed different scholarly opinions on contemporary issues. Student shows good analytical thinking.',
                'Reviewed rules of fasting with practical scenarios. Student demonstrates understanding of exceptions and make-up requirements.',
                'Discussed the concept of ijtihad and its application. Student shows interest in understanding legal methodology.',
                'Covered family law topics with focus on marriage contract requirements. Student engaged well with the discussion.'
            ]
        ];

        $defaultNotes = [
            'Student showed good engagement and understanding of the material covered.',
            'Reviewed previous lesson and introduced new concepts. Student is making steady progress.',
            'Student completed assigned homework with good attention to detail. New assignment provided.',
            'Focused on practical application of concepts. Student demonstrates good comprehension.',
            'Addressed questions from previous lesson and clarified misconceptions. Student shows improved understanding.'
        ];

        if (isset($notesBySubject[$subject])) {
            return fake()->randomElement($notesBySubject[$subject]);
        }

        return fake()->randomElement($defaultNotes);
    }

    /**
     * Generate Islamic session preparation notes
     */
    protected function generateIslamicSessionPreparationNotes(string $subject): string
    {
        $notesBySubject = [
            'Quran Recitation' => [
                'Prepare Surah Al-Fatiha and first 5 verses of Surah Al-Baqarah for recitation with tajweed.',
                'Review rules of noon sakinah and tanween for upcoming practice session.',
                'Practice articulation points of heavy letters, especially ض, ص, ط, and ظ.',
                'Prepare to recite last 3 surahs of the Quran with proper tajweed rules.',
                'Review rules of madd and prepare examples from Surah Rahman.'
            ],
            'Quran Memorization' => [
                'Memorize verses 6-10 of Surah Al-Mulk and review previously memorized portion.',
                'Prepare for revision of Surah Al-Qalam and memorization of next 3 verses.',
                'Review memorization techniques and prepare revision schedule for upcoming session.',
                'Focus on strengthening memorization of problematic verses identified in last session.',
                'Prepare to start memorization of a new surah with focus on meaning connection.'
            ],
            'Tajweed Rules' => [
                'Review rules of Idgham for practical application in next session.',
                'Prepare examples of Ikhfa from Juz Amma for discussion.',
                'Practice identifying various tajweed rules in Surah Yasin, verses 1-10.',
                'Review articulation points chart and prepare for oral examination.',
                'Study rules of waqf (stopping) and prepare examples for practice.'
            ],
            'Arabic Language for Quran' => [
                'Review vocabulary list and prepare for quiz on Quranic terms.',
                'Practice identifying verb forms in assigned Quranic verses.',
                'Complete grammar worksheet focusing on pronouns in the Quran.',
                'Prepare to translate simple verses without assistance.',
                'Review root extraction exercises for upcoming session.'
            ],
            'Fiqh (Islamic Jurisprudence)' => [
                'Read assigned chapter on purification and prepare questions for discussion.',
                'Review comparative opinions on prayer matters for case study analysis.',
                'Prepare summary of zakah requirements based on last session\'s discussion.',
                'Research the topic of Islamic contracts for next session\'s discussion.',
                'Review principles of Islamic legal theory for application exercise.'
            ]
        ];

        $defaultNotes = [
            'Please review previous lesson materials and complete assigned homework.',
            'Prepare questions on topics that need clarification for the upcoming session.',
            'Read assigned materials and make notes for discussion.',
            'Practice skills covered in previous sessions for reinforcement.',
            'Prepare for assessment on recently covered topics.'
        ];

        if (isset($notesBySubject[$subject])) {
            return fake()->randomElement($notesBySubject[$subject]);
        }

        return fake()->randomElement($defaultNotes);
    }
}