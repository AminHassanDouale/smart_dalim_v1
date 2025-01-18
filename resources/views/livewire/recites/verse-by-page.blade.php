<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public ?int $page = 1;
    public ?int $chapter = null;
    public array $verses = [];
    public ?string $selectedLanguage = 'en';
    public bool $loading = true;
    public ?string $error = null;
    public array $reciters = [];
    public ?int $selectedReciter = 7;
    public ?array $audioFile = null;
    public bool $showTajweed = false;
    public int $totalPages = 604;

    public function mount($page = 1, $chapter = null)
    {
        $this->page = max(1, min((int) $page, 604));
        $this->chapter = $chapter;
        $this->fetchReciters();
        $this->fetchVerses();
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

            $cacheKey = "verses_page_{$this->page}_{$this->selectedLanguage}_{$this->selectedReciter}";

            $response = Cache::remember($cacheKey, 3600, function () {
                $params = [
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
                        'image_url'
                    ]),
                    'word_fields' => 'translation,transliteration,audio_url'
                ];

                if ($this->chapter) {
                    $params['chapter_number'] = $this->chapter;
                }

                return Http::accept('application/json')
                    ->get("https://api.quran.com/api/v4/verses/by_page/{$this->page}", $params);
            });

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch verses');
            }

            $data = $response->json();
            $this->verses = $data['verses'];
            $this->error = null;

        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the verses.';
            $this->verses = [];
        } finally {
            $this->loading = false;
        }
    }

    public function previousPage()
    {
        if ($this->page > 1) {
            return redirect()->route('recites.page', [
                'page' => $this->page - 1,
                'chapter' => $this->chapter
            ]);
        }
    }

    public function nextPage()
    {
        if ($this->page < $this->totalPages) {
            return redirect()->route('recites.page', [
                'page' => $this->page + 1,
                'chapter' => $this->chapter
            ]);
        }
    }

    public function goToPage($pageNumber)
    {
        $page = max(1, min((int) $pageNumber, 604));
        return redirect()->route('recites.page', [
            'page' => $page,
            'chapter' => $this->chapter
        ]);
    }

    public function toggleTajweed()
    {
        $this->showTajweed = !$this->showTajweed;
    }

    public function updatedSelectedLanguage()
    {
        $this->fetchVerses();
    }

    public function updatedSelectedReciter()
    {
        $this->fetchVerses();
    }
}; ?>

<div>
    <x-header separator>
        <nav class="flex">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a
                        href="{{ route('recites.index') }}"
                        class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-indigo-600"
                    >
                        Chapters
                    </a>
                </li>
                <li class="inline-flex items-center">
                    <svg class="w-3 h-3 mx-1 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                    </svg>
                    <span class="text-sm font-medium text-gray-500">
                        Page {{ $page }}
                    </span>
                </li>
            </ol>
        </nav>

        <x-slot:title>
            Page {{ $page }}
        </x-slot:title>

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
                    :options="[
                        ['id' => 'en', 'name' => 'English'],
                        ['id' => 'ar', 'name' => 'Arabic'],
                        ['id' => 'ur', 'name' => 'Urdu'],
                        ['id' => 'id', 'name' => 'Indonesian'],
                        ['id' => 'tr', 'name' => 'Turkish'],
                    ]"
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
        <div wire:loading wire:target="fetchVerses">
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

        <!-- Verses Content -->
        @if(!empty($verses))
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
                                @if($showTajweed)
                                    <p class="text-2xl leading-loose text-right font-arabic tajweed" dir="rtl">
                                        {!! $verse['text_uthmani_tajweed'] !!}
                                    </p>
                                @else
                                    <p class="text-xl text-right font-arabic" dir="rtl">
                                        {{ $verse['text_uthmani'] ?? $verse['text'] ?? '' }}
                                    </p>
                                @endif

                                @if(isset($verse['translations'][0]['text']))
                                    <p class="text-gray-600">
                                        {{ $verse['translations'][0]['text'] }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination Controls -->
                <div class="flex items-center justify-between px-4 py-3 border-t">
                    <div class="flex items-center">
                        <span class="text-sm text-gray-700">
                            Page {{ $page }} of {{ $totalPages }}
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <x-button
                            wire:click="previousPage"
                            :disabled="$page <= 1"
                            size="sm"
                        >
                            Previous
                        </x-button>

                        <div class="flex items-center space-x-2">
                            <input
                                type="number"
                                min="1"
                                max="{{ $totalPages }}"
                                wire:model.defer="page"
                                wire:keydown.enter="goToPage($event.target.value)"
                                class="w-16 px-2 py-1 text-sm border rounded"
                            >
                        </div>

                        <x-button
                            wire:click="nextPage"
                            :disabled="$page >= $totalPages"
                            size="sm"
                        >
                            Next
                        </x-button>
                    </div>
                </div>
            </x-card>
        @endif
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
