<?php

namespace App\Livewire\Recites;

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
    public string $searchQuery = '';
    public array $chapterAudioFiles = [];

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
        if ($value) {
            $this->loadingAudio = true;
            $this->fetchChapterAudioFiles($value);
        } else {
            $this->chapterAudioFiles = [];
        }
    }

    protected function fetchChapterAudioFiles($reciterId): void
    {
        try {
            $this->chapterAudioFiles = Cache::remember(
                "chapter_audio_{$reciterId}_{$this->selectedLanguage}",
                3600,
                function () use ($reciterId) {
                    $response = Http::accept('application/json')
                        ->get("https://api.quran.com/api/v4/chapter_recitations/{$reciterId}");

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch audio files');
                    }

                    return collect($response->json()['audio_files'])
                        ->keyBy('chapter_id')
                        ->map(function ($audio) {
                            return [
                                'id' => $audio['id'],
                                'audio_url' => $audio['audio_url'],
                                'file_size' => $audio['file_size'],
                                'format' => $audio['format']
                            ];
                        })
                        ->all();
                }
            );
            $this->error = null;
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the audio files.';
            $this->chapterAudioFiles = [];
        } finally {
            $this->loadingAudio = false;
        }
    }

    public function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    #[Computed]
    public function formattedChapters()
    {
        return collect($this->filteredChapters)->map(function($chapter) {
            $audioFile = $this->chapterAudioFiles[$chapter['id']] ?? null;

            if ($audioFile) {
                $audioFile['formatted_size'] = $this->formatBytes($audioFile['file_size']);
            }

            return array_merge($chapter, [
                'audio' => $audioFile,
                'has_audio' => !is_null($audioFile),
                'pages_range' => implode('-', $chapter['pages']),
            ]);
        })->all();
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

    protected function fetchChapters(): void
    {
        try {
            $this->loading = true;
            $this->chapters = Cache::remember(
                "quran_chapters_{$this->selectedLanguage}",
                3600,
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
            $this->error = 'An error occurred while fetching the chapters.';
            $this->chapters = [];
        } finally {
            $this->loading = false;
        }
    }

    #[Computed]
    public function filteredChapters()
    {
        return collect($this->chapters)
            ->filter(fn ($chapter) =>
                str_contains(strtolower($chapter['name_simple']), strtolower($this->searchQuery)) ||
                str_contains(strtolower($chapter['translated_name']['name']), strtolower($this->searchQuery))
            )
            ->values()
            ->all();
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
}?>

<div>
    @include('livewire.recites.partials.header')
    @include('livewire.recites.partials.chapters-list')
</div>
