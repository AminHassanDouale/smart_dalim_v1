<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    public ?string $selectedLanguage = 'en';
    public array $reciters = [];
    public bool $loading = true;
    public ?string $error = null;

    public function mount(): void
    {
        $this->fetchReciters();
    }

    public function updatedSelectedLanguage($value): void
    {
        $this->selectedLanguage = $value;
        $this->fetchReciters();
    }

    #[Computed]
    public function fetchReciters(): void
    {
        try {
            $this->loading = true;

            // Use cache to store API response for 5 minutes
            $this->reciters = Cache::remember(
                "reciters_{$this->selectedLanguage}",
                300,
                function () {
                    $response = Http::accept('application/json')
                        ->get('https://api.quran.com/api/v4/resources/chapter_reciters', [
                            'language' => $this->selectedLanguage
                        ]);

                    if (!$response->successful()) {
                        throw new \Exception('Failed to fetch reciters');
                    }

                    return $response->json()['reciters'];
                }
            );

            $this->error = null;
        } catch (\Exception $e) {
            $this->error = 'An error occurred while fetching the reciters. Please try again later.';
            $this->reciters = [];
        } finally {
            $this->loading = false;
        }
    }

    public function with(): array
    {
        return [
            'languages' => [
                [
                    'id' => 'en',
                    'name' => 'English',
                ],
                [
                    'id' => 'ar',
                    'name' => 'Arabic',
                ],
                [
                    'id' => 'ur',
                    'name' => 'Urdu',
                ],
                [
                    'id' => 'id',
                    'name' => 'Indonesian',
                ],
                [
                    'id' => 'tr',
                    'name' => 'Turkish',
                ],
            ],
        ];
    }
}; ?>

<div>
    <x-header title="Quranic Reciters" separator>
        <x-slot:actions>
            <x-select
                :options="$languages"
                wire:model.live="selectedLanguage"
                class="w-40"
            />
        </x-slot:actions>
    </x-header>

    <div class="mt-6">
        <!-- Loading State -->
        <div wire:loading wire:target="fetchReciters">
            <x-card>
                <div class="flex items-center justify-center p-6">
                    <div class="flex items-center gap-2">
                        <x-icon name="o-arrow-path" class="w-5 h-5 animate-spin" />
                        <span>Loading reciters...</span>
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

        <!-- Reciters Grid -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.remove>
            @foreach($reciters as $reciter)
            <x-card>
                <div class="space-y-4">
                    <!-- Reciter Info -->
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                {{ $reciter['name'] }}
                            </h3>
                            @if($reciter['arabic_name'])
                            <p class="mt-1 text-sm text-gray-500 font-arabic">
                                {{ $reciter['arabic_name'] }}
                            </p>
                            @endif
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ strtoupper($reciter['format']) }}
                        </span>
                    </div>

                    <!-- File Size -->
                    <div class="flex items-center text-sm text-gray-500">
                        <x-icon name="o-document" class="w-4 h-4 mr-1.5" />
                        <span>{{ number_format($reciter['files_size'] / (1024 * 1024), 2) }} MB</span>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end pt-4">
                        <x-button size="sm" color="primary">
                            <x-icon name="o-play" class="w-4 h-4 mr-2" />
                            Listen Now
                        </x-button>
                    </div>
                </div>
            </x-card>
            @endforeach
        </div>
    </div>
</div>
