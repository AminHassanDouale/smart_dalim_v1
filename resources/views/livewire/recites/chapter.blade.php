<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public ?string $selectedLanguage = 'en';
    public ?int $chapterId = null;
    public array $chapterInfo = [];
    public array $chapterDetails = [];
    public array $verses = [];
    public array $tajweedVerses = [];
    public bool $loading = true;
    public bool $showTajweed = false;
    public ?string $error = null;
    public array $reciters = [];
    public ?int $selectedReciter = 7;
    public ?array $audioFile = null;

    public function mount($id)
    {
        $this->chapterId = (int) $id;

        if ($this->chapterId < 1 || $this->chapterId > 114) {
            return $this->redirect(route('recites.index'));
        }

        $this->fetchReciters();
        $this->fetchChapterInfo();
        $this->fetchChapterDetails();
        $this->fetchVerses();
        $this->fetchTajweedVerses();
        $this->fetchAudio();
    }

    protected function fetchReciters()
    {
        try {
            $this->reciters = Cache::remember(
                "quran_reciters_{$this->selectedLanguage}",
                3600,
                function () {
                    $response = Http::accept('application/json')
                        ->get('https://api.quran.com/api/v4/resources/recitations', [
                            'language' => $this->selectedLanguage
                        ]);

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch reciters');
                    }

                    return collect($response->json()['recitations'])
                        ->map(function ($reciter) {
                            return [
                                'id' => $reciter['id'],
                                'name' => $reciter['reciter_name'],
                                'style' => $reciter['style'] ?? null,
                            ];
                        })
                        ->toArray();
                }
            );
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the reciters.';
            $this->reciters = [];
        }
    }

    protected function fetchChapterInfo()
    {
        try {
            $this->loading = true;

            $this->chapterInfo = Cache::remember(
                "chapter_info_{$this->chapterId}_{$this->selectedLanguage}",
                3600,
                function () {
                    $response = Http::accept('application/json')
                        ->get("https://api.quran.com/api/v4/chapters/{$this->chapterId}", [
                            'language' => $this->selectedLanguage
                        ]);

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch chapter information');
                    }

                    return $response->json()['chapter'];
                }
            );

            $this->error = null;
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the chapter information.';
            $this->chapterInfo = [];
        } finally {
            $this->loading = false;
        }
    }

    protected function fetchChapterDetails()
    {
        try {
            $this->chapterDetails = Cache::remember(
                "chapter_details_{$this->chapterId}_{$this->selectedLanguage}",
                3600,
                function () {
                    $response = Http::accept('application/json')
                        ->get("https://api.quran.com/api/v4/chapters/{$this->chapterId}/info", [
                            'language' => $this->selectedLanguage
                        ]);

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch chapter details');
                    }

                    return $response->json()['chapter_info'];
                }
            );
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the chapter details.';
            $this->chapterDetails = [];
        }
    }

    protected function fetchVerses()
    {
        try {
            $this->verses = Cache::remember(
                "chapter_verses_{$this->chapterId}_{$this->selectedLanguage}",
                3600,
                function () {
                    $response = Http::accept('application/json')
                        ->get("https://api.quran.com/api/v4/verses/by_chapter/{$this->chapterId}", [
                            'language' => $this->selectedLanguage,
                            'words' => true,
                            'translations' => 131, // English translation
                            'audio' => $this->selectedReciter,
                            'fields' => implode(',', [
                                'text_uthmani',
                                'text_uthmani_simple',
                                'text_imlaei',
                                'text_imlaei_simple',
                                'text_indopak',
                                'text_uthmani_tajweed',
                                'juz_number',
                                'hizb_number',
                                'rub_number',
                                'sajdah_type',
                                'sajdah_number',
                                'page_number',
                                'image_url'
                            ]),
                            'word_fields' => implode(',', [
                                'translation',
                                'transliteration',
                                'audio_url'
                            ]),
                            'per_page' => 50
                        ]);

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch verses');
                    }

                    $data = $response->json();
                    return $data['verses'];
                }
            );
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the verses.';
            $this->verses = [];
        }
    }

    protected function fetchTajweedVerses()
    {
        try {
            $this->tajweedVerses = Cache::remember(
                "chapter_tajweed_{$this->chapterId}",
                3600,
                function () {
                    $response = Http::accept('application/json')
                        ->get('https://api.quran.com/api/v4/quran/verses/uthmani_tajweed', [
                            'chapter_number' => $this->chapterId
                        ]);

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch tajweed verses');
                    }

                    return collect($response->json()['verses'])
                        ->keyBy('verse_key')
                        ->toArray();
                }
            );
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the tajweed verses.';
            $this->tajweedVerses = [];
        }
    }

    protected function fetchAudio()
    {
        try {
            $this->audioFile = Cache::remember(
                "chapter_audio_{$this->chapterId}_{$this->selectedReciter}",
                3600,
                function () {
                    $response = Http::accept('application/json')
                        ->get("https://api.quran.com/api/v4/chapter_recitations/{$this->selectedReciter}/{$this->chapterId}");

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch audio');
                    }

                    return $response->json()['audio_file'];
                }
            );
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the audio.';
            $this->audioFile = null;
        }
    }

    public function updatedSelectedLanguage($value)
    {
        $this->selectedLanguage = $value;
        $this->fetchChapterInfo();
        $this->fetchChapterDetails();
        $this->fetchVerses();
    }

    public function updatedSelectedReciter($value)
    {
        $this->selectedReciter = $value;
        $this->fetchAudio();
    }

    public function toggleTajweed()
    {
        $this->showTajweed = !$this->showTajweed;
    }

    public function backToChapters()
    {
        return $this->redirect(route('recites.index'));
    }
    public function viewAllVerses()
{
    if (!empty($this->chapterInfo['pages'])) {
        return $this->redirect(route('recites.chapter.page', [
            'chapter' => $this->chapterId,
            'page' => $this->chapterInfo['pages'][0]
        ]));
    }
}

    public function with(): array
    {
        return [
            'languages' => [
                ['id' => 'en', 'name' => 'English'],
                ['id' => 'ar', 'name' => 'Arabic'],
                ['id' => 'ur', 'name' => 'Urdu'],
                ['id' => 'id', 'name' => 'Indonesian'],
                ['id' => 'tr', 'name' => 'Turkish'],
            ],
        ];
    }
}; ?>


<div>
    <x-header separator>
        <nav class="flex">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <button
                        wire:click="backToChapters"
                        class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-indigo-600"
                    >
                        Chapters
                    </button>
                </li>

                <li class="inline-flex items-center">
                    <svg class="w-3 h-3 mx-1 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-500">
                        {{ $chapterInfo['name_simple'] ?? 'Loading...' }}
                    </span>
                </li>
            </ol>
        </nav>

        <x-slot:title>
            {{ $chapterInfo['name_simple'] ?? 'Chapter Details' }}
        </x-slot:title>

        <x-slot:subtitle>
            <div class="flex items-center gap-4">
                <span class="text-gray-500">{{ $chapterInfo['translated_name']['name'] ?? '' }}</span>
                <span class="text-lg font-arabic">{{ $chapterInfo['name_arabic'] ?? '' }}</span>
            </div>
        </x-slot:subtitle>

        <x-slot:actions>
            <div class="flex items-center gap-4">
                <x-button
                    size="sm"
                    :variant="$showTajweed ? 'solid' : 'outline'"
                    color="primary"
                    wire:click="toggleTajweed"
                >
                    <x-icon name="o-book-open" class="w-4 h-4 mr-2" />
                    {{ $showTajweed ? 'Hide Tajweed' : 'Show Tajweed' }}
                </x-button>

                <x-select
                    :options="$reciters"
                    option-label="name"
                    option-value="id"
                    wire:model.live="selectedReciter"
                    class="w-64"
                >
                    <x-slot:prefix>
                        <x-icon name="o-microphone" class="w-5 h-5 text-gray-400" />
                    </x-slot:prefix>
                </x-select>

                <x-select
                    :options="$languages"
                    wire:model.live="selectedLanguage"
                    class="w-40"
                >
                    <x-slot:prefix>
                        <x-icon name="o-language" class="w-5 h-5 text-gray-400" />
                    </x-slot:prefix>
                </x-select>
            </div>

        </x-slot:actions>
    </x-header>

    @if($audioFile)
    <div class="mt-4">
        <x-card>
            <div class="flex items-center justify-between p-4">
                <div class="flex items-center gap-3">
                    <x-icon name="s-musical-note" class="w-5 h-5 text-indigo-600" />
                    <span class="text-sm font-medium text-gray-900">Chapter Audio</span>
                </div>
                <audio controls class="w-full max-w-2xl">
                    <source src="{{ $audioFile['audio_url'] }}" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
            </div>
        </x-card>
    </div>
    @endif

    <div class="mt-6">
        <!-- Loading State -->
        <div wire:loading wire:target="fetchChapterInfo,fetchChapterDetails,fetchVerses">
            <x-card>
                <div class="flex items-center justify-center p-6">
                    <div class="flex items-center gap-2">
                        <x-icon name="o-arrow-path" class="w-5 h-5 animate-spin" />
                        <span>Loading chapter...</span>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Error State -->
        @if($error)
        <x-card>
            <div class="p-4 rounded-md bg-red-50">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <x-icon name="s-x-circle" class="w-5 h-5 text-red-400" />
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">{{ $error }}</h3>
                    </div>
                </div>
            </div>
        </x-card>
        @endif

        <!-- Chapter Information -->
        @if(!empty($chapterInfo))
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Chapter Stats -->
            <div class="space-y-6 lg:col-span-1">
                <x-card>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Revelation Place</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $chapterInfo['revelation_place'] === 'makkah' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                                {{ ucfirst($chapterInfo['revelation_place']) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Verses Count</span>
                            <span class="text-sm font-medium">{{ $chapterInfo['verses_count'] }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Pages</span>
                            <span class="text-sm font-medium">{{ $chapterInfo['pages'][0] }} - {{ $chapterInfo['pages'][1] }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Revelation Order</span>
                            <span class="text-sm font-medium">{{ $chapterInfo['revelation_order'] }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Bismillah Pre-Chapter</span>
                            <span class="text-sm font-medium">{{ $chapterInfo['bismillah_pre'] ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                </x-card>

                <!-- Chapter Details -->
                @if(!empty($chapterDetails))
                <x-card>
                    <div class="prose max-w-none">
                        <h3 class="mb-4 text-lg font-medium text-gray-900">About this Chapter</h3>
                        <div class="text-sm text-gray-600">
                            {!! $chapterDetails['text'] !!}
                        </div>
                        @if(isset($chapterDetails['source']))
                            <p class="mt-4 text-xs text-gray-500">Source: {{ $chapterDetails['source'] }}</p>
                        @endif
                    </div>
                </x-card>
                @endif
            </div>

            <!-- Verses List -->
            <div class="lg:col-span-2">
                <x-card>
                    <div class="space-y-6">
                        @foreach($verses as $verse)
                        <div class="p-4 border-b last:border-b-0">
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium text-indigo-700 bg-indigo-100 rounded-full">
                                    {{ $verse['verse_number'] }}
                                </span>

                                <div class="flex items-center space-x-2">
                                    <button
                                        class="p-1 text-gray-400 transition-colors duration-200 hover:text-indigo-600"
                                        title="Copy Verse"
                                    >
                                        <x-icon name="o-clipboard" class="w-5 h-5" />
                                    </button>
                                    <button
                                        class="p-1 text-gray-400 transition-colors duration-200 hover:text-indigo-600"
                                        title="Share Verse"
                                    >
                                        <x-icon name="o-share" class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-4">
                                @if($showTajweed && isset($tajweedVerses[$verse['verse_key']]))
                                    <p class="text-2xl leading-loose text-right font-arabic" dir="rtl">
                                        {!! $tajweedVerses[$verse['verse_key']]['text_uthmani_tajweed'] !!}
                                    </p>
                                @else
                                    <p class="text-xl text-right font-arabic" dir="rtl">
                                        {{ $verse['text_uthmani'] ?? $verse['text'] ?? '' }}
                                    </p>

                                    @if(isset($verse['translations'][0]['text']))
                                        <p class="text-gray-600">
                                            {{ $verse['translations'][0]['text'] }}
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </div>
                        @endforeach
                        @if(!empty($chapterInfo) && $chapterInfo['verses_count'] > 50)
                        <div class="flex justify-center p-4 border-t">
                            <x-button
                                wire:click="viewAllVerses"
                                color="primary"
                                class="w-full sm:w-auto"
                            >
                                <x-icon name="o-book-open" class="w-4 h-4 mr-2" />
                                View All {{ $chapterInfo['verses_count'] }} Verses Page by Page
                                <span class="ml-1 text-xs">(Pages {{ $chapterInfo['pages'][0] }} - {{ $chapterInfo['pages'][1] }})</span>
                            </x-button>
                        </div>
                    @endif
                    </div>
                </x-card>
            </div>
        </div>
        @endif
    </div>
    <livewire:tafsir />

    <!-- Tajweed CSS -->
    <style>
        .tajweed {
            font-family: 'KFGQPC HAFS Uthmanic Script', Arial;
        }
        .ham_wasl { color: #AAAAAA; }
        .madda_normal { color: #537FFF; }
        .madda_permissible { color: #4050FF; }
        .madda_necessary { color: #000EBC; }
        .qalaqah { color: #DD0008; }
        .madda_obligatory { color: #2144C1; }
        .ikhafa_shafawi { color: #D500B7; }
        .ikhafa { color: #9400A8; }
        .ghunnah { color: #FF7E1E; }
        .idgham_shafawi { color: #58B800; }
        .idgham_ghunnah { color: #419200; }
        .idgham_wo_ghunnah { color: #3B7600; }
        .iqlab { color: #026IBD; }
        .ikhafa_with_iqlab { color: #590099; }
        .ikhafa_with_idgham_ghunnah { color: #8500A8; }
    </style>
</div>
