<?php

use function Livewire\Volt\{state, mount};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

state([
    'tafsirs' => [],
    'loading' => true,
    'error' => null,
    'language' => 'en'
]);

mount(function () {
    try {
        $response = Http::accept('application/json')
            ->get('https://api.quran.com/api/v4/resources/tafsirs', [
                'language' => $this->language
            ]);

        if ($response->successful()) {
            $this->tafsirs = $response->json()['tafsirs'];
        } else {
            $this->error = 'Failed to fetch tafsirs. Please try again later.';
        }
    } catch (\Exception $e) {
        $this->error = 'An error occurred while fetching tafsirs.';
        logger()->error('Tafsir fetch error: ' . $e->getMessage());
    } finally {
        $this->loading = false;
    }
});

?>

<div>
    <!-- Page Header -->
    <div class="bg-white shadow">
        <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-900">Tafsirs</h1>
        </div>
    </div>

    <!-- Main Content -->
    <div class="py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <!-- Loading State -->
        @if($loading)
        <div class="flex items-center justify-center h-32">
            <div class="w-8 h-8 border-b-2 border-gray-900 rounded-full animate-spin"></div>
        </div>
        @endif

        <!-- Error State -->
        @if($error)
        <div class="p-4 mb-4 border-l-4 border-red-400 bg-red-50">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ $error }}</p>
                </div>
            </div>
        </div>
        @endif

        <!-- Tafsirs Grid -->
        @if(!$loading && !$error && count($tafsirs) > 0)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($tafsirs as $tafsir)
            <div class="overflow-hidden transition-shadow duration-200 bg-white rounded-lg shadow hover:shadow-md">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">
                        {{ $tafsir['name'] }}
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">
                            By {{ $tafsir['author_name'] }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500">
                            Language: {{ ucfirst($tafsir['language_name']) }}
                        </p>
                        @if(isset($tafsir['translated_name']))
                        <p class="mt-1 text-sm text-gray-500">
                            Translated: {{ $tafsir['translated_name']['name'] }}
                        </p>
                        @endif
                    </div>
                    <div class="flex items-center mt-4 space-x-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            ID: {{ $tafsir['id'] }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            {{ $tafsir['slug'] }}
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @elseif(!$loading && !$error)
        <div class="py-12 text-center">
            <h3 class="mt-2 text-sm font-medium text-gray-900">No tafsirs found</h3>
            <p class="mt-1 text-sm text-gray-500">Try changing the language parameter or check back later.</p>
        </div>
        @endif
    </div>
</div>
