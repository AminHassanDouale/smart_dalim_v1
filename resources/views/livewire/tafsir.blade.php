<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public $tafsirs = [];
    public $isLoading = false;
    public $error = null;
    public $selectedTafsir = null;

    public function mount()
    {
        $this->fetchTafsirs();
    }

    public function fetchTafsirs()
    {
        try {
            $this->isLoading = true;
            $this->error = null;

            $response = Http::get('https://api.quran.com/api/v4/resources/tafsirs');

            if ($response->successful()) {
                $this->tafsirs = $response->json()['tafsirs'];
            } else {
                $this->error = 'Failed to fetch tafsirs. Please try again later.';
            }
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching tafsirs.';
        } finally {
            $this->isLoading = false;
        }
    }

    public function selectTafsir($id)
    {
        $this->selectedTafsir = collect($this->tafsirs)->firstWhere('id', $id);
    }
}; ?>

<div class="w-full max-w-2xl p-4 mx-auto">
    <div class="mb-6">
        <button
            wire:click="fetchTafsirs"
            class="flex items-center px-4 py-2 space-x-2 text-white rounded-lg bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
        >
            @if($isLoading)
                <svg class="w-5 h-5 text-white animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            @endif
            <span>{{ $isLoading ? 'Loading Tafsirs...' : 'Load Tafsirs' }}</span>
        </button>
    </div>

    @if($error)
        <div class="p-4 mb-6 border-l-4 border-red-400 bg-red-50">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    @if(count($tafsirs) > 0)
        <div class="overflow-hidden bg-white rounded-lg shadow">
            <ul class="divide-y divide-gray-200">
                @foreach($tafsirs as $tafsir)
                    <li class="p-4 cursor-pointer hover:bg-gray-50" wire:click="selectTafsir({{ $tafsir['id'] }})">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">{{ $tafsir['name'] }}</h3>
                                <p class="text-sm text-gray-500">{{ $tafsir['author_name'] }}</p>
                                <p class="text-xs text-gray-400">Language: {{ $tafsir['language_name'] }}</p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($selectedTafsir)
        <div class="fixed inset-0 flex items-center justify-center p-4 bg-gray-500 bg-opacity-75">
            <div class="w-full max-w-lg p-6 bg-white rounded-lg">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">{{ $selectedTafsir['name'] }}</h3>
                    <button wire:click="$set('selectedTafsir', null)" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="space-y-2">
                    <p class="text-sm text-gray-500">Author: {{ $selectedTafsir['author_name'] }}</p>
                    <p class="text-sm text-gray-500">Language: {{ $selectedTafsir['language_name'] }}</p>
                    <p class="text-sm text-gray-500">Slug: {{ $selectedTafsir['slug'] }}</p>
                </div>
            </div>
        </div>
    @endif
</div>
