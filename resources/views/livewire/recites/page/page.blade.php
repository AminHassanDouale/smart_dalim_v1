<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Component;

new class extends Component {
    public ?string $selectedLanguage = 'en';
    public ?int $currentPage = 1;
    public ?int $chapterId = null;
    public array $verses = [];
    public array $tajweedVerses = [];
    public array $chapterInfo = [];
    public bool $loading = true;
    public bool $showTajweed = false;
    public ?string $error = null;
    public array $reciters = [];
    public ?int $selectedReciter = 7;
    public ?array $audioFile = null;
    public int $totalPages = 604;
    public array $pageMetadata = [];

    public function mount($page = 1)
    {
        $this->currentPage = (int) $page;

        if ($this->currentPage < 1 || $this->currentPage > $this->totalPages) {
            $this->currentPage = 1;
        }

        $this->fetchReciters();
        $this->fetchVerses();
        $this->fetchTajweedVerses();
        $this->fetchPageMetadata();
    }

    protected function fetchPageMetadata()
    {
        try {
            $this->pageMetadata = Cache::remember(
                "page_metadata_{$this->currentPage}",
                3600,
                function () {
                    $response = Http::accept('application/json')
                        ->get("https://api.quran.com/api/v4/quran/pages/{$this->currentPage}");

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch page metadata');
                    }

                    return $response->json()['page'];
                }
            );
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching page metadata.';
            $this->pageMetadata = [];
        }
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

    protected function fetchVerses()
    {
        try {
            $this->loading = true;
            $pageNumber = max(1, min($this->currentPage, $this->totalPages));

            $response = Cache::remember(
                "verses_page_{$pageNumber}_{$this->selectedLanguage}_{$this->selectedReciter}",
                3600,
                function () use ($pageNumber) {
                    return Http::accept('application/json')
                        ->get("https://api.quran.com/api/v4/verses/by_page/{$pageNumber}", [
                            'language' => $this->selectedLanguage,
                            'words' => true,
                            'translations' => 131,
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
                                'chapter_id'
                            ]),
                            'word_fields' => implode(',', [
                                'translation',
                                'transliteration'
                            ]),
                            'per_page' => 50
                        ]);
                }
            );

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch verses');
            }

            $data = $response->json();
            $this->verses = $data['verses'];

            if (!empty($this->verses)) {
                $this->chapterId = $this->verses[0]['chapter_id'];
                $this->fetchChapterInfo();
            }

            $this->error = null;
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the verses.';
            $this->verses = [];
        } finally {
            $this->loading = false;
        }
    }

    protected function fetchChapterInfo()
    {
        if (!$this->chapterId) return;

        try {
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
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the chapter information.';
            $this->chapterInfo = [];
        }
    }

    protected function fetchTajweedVerses()
    {
        try {
            $this->tajweedVerses = Cache::remember(
                "page_tajweed_{$this->currentPage}",
                3600,
                function () {
                    $response = Http::accept('application/json')
                        ->get('https://api.quran.com/api/v4/quran/verses/uthmani_tajweed', [
                            'page_number' => $this->currentPage
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

    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->goToPage($this->currentPage - 1);
        }
    }

    public function nextPage()
    {
        if ($this->currentPage < $this->totalPages) {
            $this->goToPage($this->currentPage + 1);
        }
    }

    public function goToPage($page)
    {
        $page = (int) $page;
        $page = max(1, min($page, $this->totalPages));

        if ($page !== $this->currentPage) {
            $this->currentPage = $page;
            $this->fetchVerses();
            $this->fetchTajweedVerses();
            $this->fetchPageMetadata();
        }
    }

    public function updatedSelectedLanguage($value)
    {
        $this->selectedLanguage = $value;
        $this->fetchVerses();
        if ($this->chapterId) {
            $this->fetchChapterInfo();
        }
    }

    public function updatedSelectedReciter($value)
    {
        $this->selectedReciter = (int) $value;
        $this->fetchVerses();
    }

    public function toggleTajweed()
    {
        $this->showTajweed = !$this->showTajweed;
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
                        wire:click="backToIndex"
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
                        Page {{ $currentPage }}
                        @if(!empty($chapterInfo))
                            - {{ $chapterInfo['name_simple'] }}
                        @endif
                    </span>
                </li>
            </ol>
        </nav>

        <x-slot:title>
            <div class="flex items-center gap-4">
                <span>Quran Page {{ $currentPage }}</span>
                @if(!empty($chapterInfo))
                    <span class="text-lg font-arabic">{{ $chapterInfo['name_arabic'] }}</span>
                @endif
            </div>
        </x-slot:title>

        <x-slot:subtitle>
            <div class="flex items-center gap-4">
                <span class="text-gray-500">Madani Mushaf</span>
                @if(!empty($pageMetadata))
                    <span class="text-gray-500">Juz {{ $pageMetadata['juz_number'] ?? '' }}</span>
                    <span class="text-gray-500">Hizb {{ $pageMetadata['hizb_number'] ?? '' }}</span>
                    @if($pageMetadata['rub_number'] ?? false)
                        <span class="text-gray-500">Rub {{ $pageMetadata['rub_number'] }}</span>
                    @endif
                @endif
                @if(!empty($chapterInfo))
                    <span class="text-gray-500">{{ $chapterInfo['translated_name']['name'] ?? '' }}</span>
                @endif
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

    <div class="mt-6">
        <!-- Loading State -->
        <div wire:loading wire:target="fetchVerses,goToPage">
            <x-card>
                <div class="flex items-center justify-center p-6">
                    <div class="flex items-center gap-2">
                        <x-icon name="o-arrow-path" class="w-5 h-5 animate-spin" />
                        <span>Loading verses...</span>
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
            <div class="mb-6">
                <x-card>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <span class="text-sm text-gray-500">Chapter</span>
                                <p class="mt-1 text-sm font-medium">{{ $chapterInfo['name_simple'] }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Revelation</span>
                                <p class="mt-1 text-sm font-medium capitalize">{{ $chapterInfo['revelation_place'] }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Chapter Pages</span>
                                <p class="mt-1 text-sm font-medium">{{ $chapterInfo['pages'][0] }} - {{ $chapterInfo['pages'][1] }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Verses</span>
                                <p class="mt-1 text-sm font-medium">{{ $chapterInfo['verses_count'] }}</p>
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>
        @endif

        <!-- Verses Content -->
        <x-card>
            <div class="space-y-6">
                @foreach($verses as $verse)
                <div class="p-4 border-b last:border-b-0">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium text-indigo-700 bg-indigo-100 rounded-full">
                                {{ $verse['verse_number'] }}
                            </span>
                            <span class="text-sm text-gray-500">
                                {{ $verse['verse_key'] }}
                            </span>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button
                                class="p-1 text-gray-400 transition-colors duration-200 hover:text-indigo-600"
                                title="Copy Verse"
                                x-on:click="$clipboard('{{ $verse['text_uthmani'] }}')"
                            >
                                <x-icon name="o-clipboard" class="w-5 h-5" />
                            </button>
                            <button
                                class="p-1 text-gray-400 transition-colors duration-200 hover:text-indigo-600"
                                title="Share Verse"
                                x-on:click="$share({
                                    title: 'Quran Verse {{ $verse['verse_key'] }}',
                                    text: '{{ $verse['text_uthmani'] }}',
                                    url: '{{ url()->current() }}#verse-{{ $verse['verse_key'] }}'
                                })"
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
            </div>

            <!-- Pagination -->
            <div class="flex items-center justify-between px-4 py-3 border-t">
                <div class="flex items-center">
                    <span class="text-sm text-gray-700">
                        Page {{ $currentPage }} of {{ $totalPages }}
                    </span>
                </div>
                <div class="flex items-center space-x-2">
                    <x-button
                        wire:click="previousPage"
                        :disabled="$currentPage <= 1"
                        size="sm"
                    >
                        <x-icon name="o-chevron-left" class="w-4 h-4" />
                        Previous
                    </x-button>

                    <div class="flex items-center space-x-2">
                        <input
                            type="number"
                            wire:model.blur="currentPage"
                            wire:change="goToPage($event.target.value)"
                            class="w-16 px-2 py-1 text-sm border rounded"
                            min="1"
                            max="{{ $totalPages }}"
                        />
                        <span class="text-sm text-gray-600">of {{ $totalPages }}</span>
                    </div>

                    <x-button
                        wire:click="nextPage"
                        :disabled="$currentPage >= $totalPages"
                        size="sm"
                    >
                        Next
                        <x-icon name="o-chevron-right" class="w-4 h-4" />
                    </x-button>
                </div>
            </div>
        </x-card>
    </div>

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
