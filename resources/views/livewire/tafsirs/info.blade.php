<?php

use function Livewire\Volt\{state, mount, protect};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

state([
    'tafsirId' => '',
    'tafsir' => null,
    'isLoading' => true,
    'error' => null,
]);

mount(function ($tafsir_id) {
    $this->tafsirId = $tafsir_id;
    $this->fetchTafsirInfo();
});

$fetchTafsirInfo = protect(function () {
    try {
        // Try to get from cache first
        $cacheKey = "tafsir_info_{$this->tafsirId}";

        if (Cache::has($cacheKey)) {
            $this->tafsir = Cache::get($cacheKey);
            $this->isLoading = false;
            return;
        }

        $response = Http::accept('application/json')
            ->timeout(30)
            ->retry(3, 100)
            ->get("https://api.quran.com/api/v4/resources/tafsirs/{$this->tafsirId}/info");

        if (!$response->successful()) {
            throw new \Exception(
                $response->status() === 404
                    ? 'Tafsir information not found.'
                    : "API request failed with status: {$response->status()}"
            );
        }

        $data = $response->json();

        // Check for specific data structure based on API response
        if (!isset($data['tafsir']['info'])) {
            $this->tafsir = [
                'info' => $data['info'] ?? null,
                'name' => $data['name'] ?? null,
                'author' => $data['author'] ?? null
            ];
        } else {
            $this->tafsir = $data['tafsir'];
        }

        // Cache the result for 24 hours
        Cache::put($cacheKey, $this->tafsir, now()->addDay());

    } catch (\Exception $e) {
        $this->error = $e->getMessage();
        logger()->error('Tafsir info fetch failed', [
            'tafsir_id' => $this->tafsirId,
            'error' => $e->getMessage()
        ]);
    } finally {
        $this->isLoading = false;
    }
});

?>

<div class="min-h-screen bg-gray-50">
    <!-- Page Header -->
    <div class="bg-white shadow">
        <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">
                    Tafsir #{{ $tafsirId }}
                </h1>
                <a href="{{ route('tafsirs.index') }}"
                   class="inline-flex items-center gap-x-2 rounded-md bg-white px-3.5 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    <svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 4.158a.75.75 0 11-1.04 1.04l-5.5-5.5a.75.75 0 010-1.08l5.5-5.5a.75.75 0 111.04 1.04L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" />
                    </svg>
                    Back to Tafsirs
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="py-8">
        <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Loading State -->
            @if($isLoading)
            <div class="flex items-center justify-center h-64">
                <div class="w-12 h-12 border-b-2 border-gray-900 rounded-full animate-spin"></div>
            </div>
            @endif

            <!-- Error State -->
            @if($error)
            <div class="p-4 mb-6 rounded-md bg-red-50">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                        <p class="mt-1 text-sm text-red-700">{{ $error }}</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Tafsir Content -->
            @if(!$isLoading && !$error && $tafsir)
            <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="prose max-w-none">
                        <h2 class="mb-4 text-xl font-semibold text-gray-900">
                            {{ $tafsir['name'] ?? 'Tafsir Information' }}
                        </h2>
                        <div class="space-y-4 text-gray-700">
                            {!! nl2br(e($tafsir['info'])) !!}
                        </div>
                        @if(isset($tafsir['author']))
                        <div class="pt-6 mt-6 border-t border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Author</h3>
                            <p class="mt-2 text-gray-600">{{ $tafsir['author'] }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @elseif(!$isLoading && !$error)
            <div class="px-6 py-12 text-center bg-white rounded-lg shadow">
                <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No information available</h3>
                <p class="mt-1 text-sm text-gray-500">We couldn't find any information for this tafsir.</p>
            </div>
            @endif
        </div>
    </div>
</div>
