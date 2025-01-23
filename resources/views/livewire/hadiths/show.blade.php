<?php

use function Livewire\Volt\{state, mount};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

state([
    'edition' => '',
    'loading' => false,
    'error' => null,
    'apiBase' => 'https://cdn.jsdelivr.net/gh/fawazahmed0/hadith-api@1',
    'metadata' => null,
    'hadiths' => [],
    'currentPage' => 1,
    'perPage' => 20,
    'currentSection' => null,
    'direction' => 'ltr'
]);

$mount = function($edition) {
    $this->edition = $edition;
    $this->fetchHadiths();
};

$fetchHadiths = function() {
    $this->loading = true;
    $this->error = null;

    try {
        // Try to get from cache first
        $data = Cache::remember("hadith_edition_{$this->edition}", 3600, function () {
            // Try minified version first
            try {
                $response = Http::get("{$this->apiBase}/editions/{$this->edition}.min.json");
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
                logger()->warning('Failed to fetch minified edition, trying regular JSON');
            }

            // Fallback to regular JSON
            $response = Http::get("{$this->apiBase}/editions/{$this->edition}.json");
            if ($response->failed()) {
                throw new Exception('Failed to fetch hadiths');
            }

            return $response->json();
        });

        $this->metadata = $data['metadata'];
        $this->hadiths = collect($data['hadiths']);
        $this->direction = str_contains($this->edition, 'ara') ? 'rtl' : 'ltr';

    } catch (\Exception $e) {
        $this->error = $e->getMessage();
        logger()->error('Hadith API Error: ' . $e->getMessage());
    } finally {
        $this->loading = false;
    }
};

$getPaginatedHadiths = function() {
    $offset = ($this->currentPage - 1) * $this->perPage;
    return $this->hadiths->slice($offset, $this->perPage);
};

$getTotalPages = function() {
    return ceil($this->hadiths->count() / $this->perPage);
};

$nextPage = function() {
    if ($this->currentPage < $this->getTotalPages()) {
        $this->currentPage++;
    }
};

$prevPage = function() {
    if ($this->currentPage > 1) {
        $this->currentPage--;
    }
};

$setSection = function($sectionNumber) {
    $this->currentSection = $sectionNumber;
};

?><div class="py-6">
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">
                {{ $metadata['name'] ?? 'Hadiths' }}
            </h1>
            @if ($direction === 'rtl')
                <p class="mt-2 text-sm text-gray-500">Arabic Text</p>
            @endif
        </div>

        <!-- Error Message -->
        @if ($error)
            <div class="p-4 mb-6 border-l-4 border-red-400 bg-red-50">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Loading State -->
        @if ($loading)
            <div class="flex items-center justify-center py-12">
                <div class="w-8 h-8 border-b-2 border-gray-900 rounded-full animate-spin"></div>
            </div>
        @endif

        <!-- Sections List -->
    </div>
