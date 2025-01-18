<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            [
                'name' => 'Quran',
                'description' => 'Study of the Holy Quran, including memorization and recitation',
            ],
            [
                'name' => 'Tajwid',
                'description' => 'Rules governing pronunciation during recitation of the Quran',
            ],
            [
                'name' => 'Tafsir',
                'description' => 'Exegesis or interpretation of the Holy Quran',
            ],
            [
                'name' => 'Islamic Studies',
                'description' => 'Comprehensive study of Islamic principles, ethics, and practices',
            ],
            [
                'name' => 'Ilm Hadith',
                'description' => 'Study of Prophetic traditions and narrations',
            ],
        ];

        foreach ($subjects as $subject) {
            Subject::create([
                'name' => $subject['name'],
                'slug' => Str::slug($subject['name']),
                'description' => $subject['description'],
            ]);
        }
    }
}
