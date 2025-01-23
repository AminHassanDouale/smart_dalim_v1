<?php

namespace App\Livewire\Recites;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public bool $isFilterLoading = false;

    public ?string $selectedLanguage = 'en';
    public ?int $chapterId = null;
    public array $chapterInfo = [];
    public array $verses = [];
    public bool $loading = true;
    public ?string $error = null;

    // Filter properties
    public ?string $searchQuery = '';
    public ?int $selectedPage = null;
    public ?int $selectedVerse = null;
    public array $filteredVerses = [];

    public function mount($id)
    {
        $this->chapterId = (int) $id;
        if ($this->chapterId < 1 || $this->chapterId > 114) {
            return $this->redirect(route('recites.index'));
        }
        $this->fetchChapterInfo();
        $this->fetchVerses();
    }

    #[Computed]
    public function availablePages()
    {
        return collect($this->verses)
            ->pluck('page_number')
            ->unique()
            ->sort()
            ->values();
    }

    #[Computed]
    public function availableVerses()
    {
        return collect($this->verses)
            ->pluck('verse_number')
            ->sort()
            ->values();
    }

    protected function fetchChapterInfo()
    {
        try {
            $this->loading = true;
            $this->chapterInfo = Cache::remember(
                "chapter_info_{$this->chapterId}_{$this->selectedLanguage}",
                3600,
                fn () => Http::accept('application/json')
                    ->get("https://api.quran.com/api/v4/chapters/{$this->chapterId}", [
                        'language' => $this->selectedLanguage
                    ])
                    ->throw()
                    ->json('chapter')
            );
            $this->error = null;
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the chapter information.';
            $this->chapterInfo = [];
        } finally {
            $this->loading = false;
        }
    }

    protected function fetchVerses()
    {
        try {
            $this->verses = Cache::remember(
                "chapter_verses_{$this->chapterId}_{$this->selectedLanguage}",
                3600,
                fn () => Http::accept('application/json')
                    ->get("https://api.quran.com/api/v4/verses/by_chapter/{$this->chapterId}", [
                        'language' => $this->selectedLanguage,
                        'words' => true,
                        'translations' => 131,
                        'fields' => 'text_uthmani,verse_number,page_number',
                        'per_page' => 286
                    ])
                    ->throw()
                    ->json('verses')
            );
            $this->filteredVerses = $this->verses;
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the verses.';
            $this->verses = [];
            $this->filteredVerses = [];
        }
    }

    public function updatedSearchQuery()
    {
        $this->applyFilters();
    }

    public function updatedSelectedPage()
    {
        $this->applyFilters();
    }

    public function updatedSelectedVerse()
    {
        $this->applyFilters();
    }

    protected function applyFilters()
    {
        $this->filteredVerses = collect($this->verses)->filter(function($verse) {
            $matchesSearch = empty($this->searchQuery) ||
                str_contains(strtolower($verse['text_uthmani']), strtolower($this->searchQuery)) ||
                str_contains(strtolower($verse['translations'][0]['text'] ?? ''), strtolower($this->searchQuery));

            $matchesPage = is_null($this->selectedPage) || $verse['page_number'] == $this->selectedPage;
            $matchesVerse = is_null($this->selectedVerse) || $verse['verse_number'] == $this->selectedVerse;

            return $matchesSearch && $matchesPage && $matchesVerse;
        })->all();
    }

    public function resetFilters()
    {
        $this->selectedPage = null;
        $this->selectedVerse = null;
        $this->searchQuery = '';
        $this->filteredVerses = $this->verses;
    }

    public function updating($name, $value)
    {
        if (in_array($name, ['selectedPage', 'selectedVerse', 'searchQuery'])) {
            $this->isFilterLoading = true;
        }
    }

    public function updated($name, $value)
    {
        if (in_array($name, ['selectedPage', 'selectedVerse', 'searchQuery'])) {
            $this->isFilterLoading = false;
        }
    }
}; ?>

<div class="max-w-4xl p-4 mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <h1 class="text-2xl font-semibold">{{ $chapterInfo['name_simple'] ?? 'Chapter' }}</h1>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-600">Translation</span>
            <span class="text-sm text-gray-600">Reading</span>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="p-4 space-y-4 bg-white rounded-lg shadow">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Search Input -->
            <div class="relative">
                <input
                    wire:model.live.debounce.300ms="searchQuery"
                    type="text"
                    placeholder="Search verses..."
                    class="w-full py-2 pl-10 pr-4 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
                <span class="absolute left-3 top-2.5">
                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
            </div>

            <!-- Verse Selector -->
            <div class="relative">
                <select
                    wire:model.live="selectedVerse"
                    class="w-full py-2 pl-4 pr-10 border rounded-lg appearance-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">Select Verse</option>
                    @foreach($this->availableVerses as $verse)
                        <option value="{{ $verse }}">{{ $verse }}</option>
                    @endforeach
                </select>
                <span class="absolute right-3 top-2.5">
                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </span>
            </div>

            <!-- Page Selector -->
            <div class="relative">
                <select
                    wire:model.live="selectedPage"
                    class="w-full py-2 pl-4 pr-10 border rounded-lg appearance-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">Select Page</option>
                    @foreach($this->availablePages as $page)
                        <option value="{{ $page }}">{{ $page }}</option>
                    @endforeach
                </select>
                <span class="absolute right-3 top-2.5">
                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </span>
            </div>
        </div>

        <!-- Active Filters -->
        @if($selectedPage || $selectedVerse || $searchQuery)
            <div class="flex flex-wrap items-center gap-2 pt-4">
                <span class="text-sm text-gray-600">Active filters:</span>
                @if($searchQuery)
                    <span class="inline-flex items-center px-3 py-1 text-sm text-blue-800 bg-blue-100 rounded-full">
                        Search: "{{ $searchQuery }}"
                    </span>
                @endif
                @if($selectedVerse)
                    <span class="inline-flex items-center px-3 py-1 text-sm text-blue-800 bg-blue-100 rounded-full">
                        Verse: {{ $selectedVerse }}
                    </span>
                @endif
                @if($selectedPage)
                    <span class="inline-flex items-center px-3 py-1 text-sm text-blue-800 bg-blue-100 rounded-full">
                        Page: {{ $selectedPage }}
                    </span>
                @endif
                <button
                    wire:click="resetFilters"
                    class="px-3 py-1 text-sm text-gray-600 hover:text-gray-900"
                >
                    Reset
                </button>
            </div>
        @endif
    </div>

    <!-- Loading State -->
    @if($loading || $this->isFilterLoading)
    <x-progress class="progress-primary h-0.5" indeterminate />
@endif
    <!-- Error State -->
    @if($error)
        <div class="p-4 rounded-lg bg-red-50">
            <div class="flex">
                <svg class="w-5 h-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-red-800">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Verses Display -->
    <div class="space-y-4">
        @forelse($filteredVerses as $verse)
        <div class="p-6 space-y-4 bg-white rounded-lg shadow">
            <div class="text-2xl text-right font-arabic">
                {{ $verse['text_uthmani'] }}
            </div>
            <div class="text-gray-600">
                @php
                    $translation = $verse['translations'][0]['text'] ?? '';
                    // Remove footnote superscript tags
                    $translation = preg_replace('/<sup.*?<\/sup>/', '', $translation);
                @endphp
                {!! $translation !!}
            </div>
            <div class="flex items-center justify-between text-sm text-gray-500">
                <span>Verse: {{ $verse['verse_number'] }}</span>
                <span>Page: {{ $verse['page_number'] }}</span>
            </div>
        </div>
    @empty
        <div class="p-6 text-center text-gray-500 bg-white rounded-lg shadow">
            No verses found matching your criteria
        </div>
    @endforelse
    </div>
</div>
