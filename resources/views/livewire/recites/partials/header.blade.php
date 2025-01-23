<div class="bg-white shadow">
    <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <!-- Language Selector -->
            <div class="w-full sm:w-auto">
                <x-select
                    wire:model.live="selectedLanguage"
                    :options="$languages"
                    option-label="name"
                    option-value="id"
                    placeholder="Select Language"
                />
            </div>

            <!-- Reciter Selector -->
            <div class="w-full sm:w-auto">
                <x-select
                    wire:model.live="selectedReciter"
                    :options="$reciters"
                    option-label="translated_name"
                    option-value="id"
                    placeholder="Select Reciter"
                />
            </div>

            <!-- Search Box -->
            <div class="w-full sm:w-auto">
                <x-input
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Search chapters..."
                    icon="s-magnifying-glass-circle"
                />
            </div>
        </div>
    </div>
</div>

@if($loading || $loadingAudio)
    <x-progress class="progress-primary h-0.5" indeterminate />
@endif

@if($error)
    <div class="px-4 py-3 mx-auto mt-4 text-red-700 bg-red-100 border border-red-400 rounded-lg max-w-7xl">
        {{ $error }}
    </div>
@endif
