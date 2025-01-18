<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public ?string $selectedLanguage = 'en';
    public array $chapters = [];
    public array $reciters = [];
    public ?int $selectedReciter = null;
    public array $reciterAudioFiles = [];
    public bool $loading = true;
    public ?string $error = null;
    public bool $loadingAudio = false;

    public function mount(): void
    {
        $this->fetchReciters();
        $this->fetchChapters();
        if ($this->selectedReciter) {
            $this->fetchReciterAudioFiles();
        }
    }

    protected function fetchReciters(): void
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

                    $reciters = collect($response->json()['recitations'])
                        ->map(function ($reciter) {
                            return [
                                'id' => $reciter['id'],
                                'name' => $reciter['reciter_name'],
                                'style' => $reciter['style'] ?? null,
                                'translated_name' => $reciter['translated_name']['name'] ?? $reciter['reciter_name']
                            ];
                        })
                        ->toArray();

                    // Set default reciter if not already set
                    if (!$this->selectedReciter && !empty($reciters)) {
                        $this->selectedReciter = $reciters[0]['id'];
                    }

                    return $reciters;
                }
            );
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the reciters.';
            $this->reciters = [];
        }
    }

    public function updatedSelectedLanguage($value): void
    {
        $this->selectedLanguage = $value;
        $this->fetchReciters();
        $this->fetchChapters();
    }

    public function updatedSelectedReciter($value): void
    {
        $this->selectedReciter = $value;
        $this->fetchReciterAudioFiles();
    }

    protected function fetchReciterAudioFiles(): void
    {
        try {
            $this->loadingAudio = true;
            $this->reciterAudioFiles = Cache::remember(
                "reciter_audio_{$this->selectedReciter}",
                3600,
                function () {
                    $response = Http::accept('application/json')
                        ->get("https://api.quran.com/api/v4/chapter_recitations/{$this->selectedReciter}");

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch audio files');
                    }

                    return collect($response->json()['audio_files'])
                        ->keyBy('chapter_id')
                        ->toArray();
                }
            );
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the audio files.';
            $this->reciterAudioFiles = [];
        } finally {
            $this->loadingAudio = false;
        }
    }

    #[Computed]
    public function fetchChapters(): void
    {
        try {
            $this->loading = true;
            $this->chapters = Cache::remember(
                "quran_chapters_{$this->selectedLanguage}",
                300,
                function () {
                    $response = Http::accept('application/json')
                        ->get('https://api.quran.com/api/v4/chapters', [
                            'language' => $this->selectedLanguage
                        ]);

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch chapters');
                    }

                    return $response->json()['chapters'];
                }
            );

            $this->error = null;
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the chapters. Please try again later.';
            $this->chapters = [];
        } finally {
            $this->loading = false;
        }
    }

    public function navigateToChapter($chapterId): void
    {
        $this->redirect(route('recites.chapter', ['id' => $chapterId]));
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
    <x-header title="Quran Chapters" separator>
        <x-slot:actions>
            <div class="flex items-center gap-4">
                <x-select
                    :options="$reciters"
                    option-label="translated_name"
                    option-value="id"
                    wire:model.live="selectedReciter"
                    class="w-80"
                    :placeholder="$loadingAudio ? 'Loading reciters...' : 'Select reciter'"
                    :disabled="empty($reciters)"
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

    <!-- Rest of the template remains the same -->
    <div class="mt-6">
        <!-- Loading State -->
        <div wire:loading wire:target="fetchChapters">
            <x-card>
                <div class="flex items-center justify-center p-6">
                    <div class="flex items-center gap-2">
                        <x-icon name="o-arrow-path" class="w-5 h-5 animate-spin" />
                        <span>Loading chapters...</span>
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

        <!-- Chapters Grid -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.remove>
            @foreach($chapters as $chapter)
            <x-card>
                <div class="space-y-4">
                    <!-- Chapter Info -->
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="flex items-center justify-center w-8 h-8 text-sm font-medium text-indigo-700 bg-indigo-100 rounded-full">
                                    {{ $chapter['id'] }}
                                </span>
                                <h3 class="text-lg font-medium text-gray-900">
                                    {{ $chapter['name_simple'] }}
                                </h3>
                            </div>
                            <div class="mt-1">
                                <p class="text-sm text-gray-500 font-arabic">
                                    {{ $chapter['name_arabic'] }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    {{ $chapter['translated_name']['name'] }}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $chapter['revelation_place'] === 'makkah' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                            {{ ucfirst($chapter['revelation_place']) }}
                        </span>
                    </div>

                    <!-- Chapter Details -->
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <div class="flex items-center">
                            <x-icon name="o-book-open" class="w-4 h-4 mr-1.5" />
                            <span>{{ $chapter['verses_count'] }} verses</span>
                        </div>
                        <div class="flex items-center">
                            <x-icon name="o-document-text" class="w-4 h-4 mr-1.5" />
                            <span>Pages {{ $chapter['pages'][0] }}-{{ $chapter['pages'][1] }}</span>
                        </div>
                    </div>

                    <!-- Audio Player -->
                    @if(isset($reciterAudioFiles[$chapter['id']]))
                    <div class="pt-2">
                        <audio controls class="w-full">
                            <source src="{{ $reciterAudioFiles[$chapter['id']]['audio_url'] }}" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                    @endif

                    <!-- Actions -->
                    <div class="flex items-center justify-between pt-4">
                        @if(isset($reciterAudioFiles[$chapter['id']]))
                        <a
                            href="{{ $reciterAudioFiles[$chapter['id']]['audio_url'] }}"
                            target="_blank"
                            class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-900"
                        >
                            <x-icon name="o-arrow-down-tray" class="w-4 h-4 mr-1" />
                            Download Audio
                        </a>
                        @endif
                        <x-button
                            size="sm"
                            color="primary"
                            wire:click="navigateToChapter({{ $chapter['id'] }})"
                        >
                            <x-icon name="o-arrow-right" class="w-4 h-4 mr-2" />
                            Read Chapter
                        </x-button>
                    </div>
                </div>
            </x-card>
            @endforeach
        </div>
    </div>
</div>
