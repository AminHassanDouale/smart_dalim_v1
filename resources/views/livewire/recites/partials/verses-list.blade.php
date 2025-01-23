<div class="space-y-6">
    @foreach($verses as $verse)
        <div class="p-4 border-b last:border-b-0">
            <div class="flex items-center justify-between mb-2">
                <!-- Verse Number -->
                <div class="flex items-center space-x-3">
                    <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium text-indigo-700 bg-indigo-100 rounded-full">
                        {{ $verse['verse_number'] }}
                    </span>
                    <span class="text-sm text-gray-500">{{ $verse['verse_key'] }}</span>
                </div>

                <!-- Verse Actions -->
                <div class="flex items-center space-x-2">
                    @if(isset($verse['audio_url']))
                        <button class="p-1 text-gray-400 transition-colors duration-200 hover:text-indigo-600" title="Play Audio">
                            <x-icon name="o-musical-note" class="w-5 h-5" />
                        </button>
                    @endif

                    <button
                        class="p-1 text-gray-400 transition-colors duration-200 hover:text-indigo-600"
                        title="Copy Verse"
                        x-data="{ tooltip: 'Copy' }"
                        x-on:click="
                            navigator.clipboard.writeText('{{ $verse['text_uthmani'] ?? $verse['text'] ?? '' }}');
                            tooltip = 'Copied!';
                            setTimeout(() => tooltip = 'Copy', 2000)
                        "
                        x-tooltip="tooltip"
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

            <!-- Verse Text -->
            <div class="space-y-4">
                @if($showTajweed && isset($tajweedVerses[$verse['verse_key']]))
                    <p class="text-2xl leading-loose text-right font-arabic" dir="rtl">
                        {!! $tajweedVerses[$verse['verse_key']]['text_uthmani_tajweed'] !!}
                    </p>
                @else
                    <p class="text-2xl leading-loose text-right font-arabic" dir="rtl">
                        {{ $verse['text_uthmani'] ?? $verse['text'] ?? '' }}
                    </p>
                @endif

                <!-- Translation -->
                @if(isset($verse['translations'][0]['text']))
                    <p class="text-gray-600">
                        {{ $verse['translations'][0]['text'] }}
                    </p>
                @endif
            </div>

            <!-- Verse Metadata -->
            <div class="flex flex-wrap gap-2 mt-4">
                @if(isset($verse['juz_number']))
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        Juz {{ $verse['juz_number'] }}
                    </span>
                @endif

                @if(isset($verse['hizb_number']))
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                        Hizb {{ $verse['hizb_number'] }}
                    </span>
                @endif

                @if(isset($verse['page_number']))
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        Page {{ $verse['page_number'] }}
                    </span>
                @endif

                @if(isset($verse['sajdah_type']) && $verse['sajdah_type'])
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                        Sajdah: {{ ucfirst($verse['sajdah_type']) }}
                    </span>
                @endif
            </div>
        </div>
    @endforeach

    @if(!empty($chapterInfo) && isset($chapterInfo['verses_count']) && $chapterInfo['verses_count'] > 50)
        <div class="flex justify-center p-4 border-t">
            <x-button
                wire:click="viewAllVerses"
                color="primary"
                class="w-full sm:w-auto"
            >
                <x-icon name="o-book-open" class="w-4 h-4 mr-2" />
                View All {{ $chapterInfo['verses_count'] }} Verses Page by Page
                <span class="ml-1 text-xs">
                    @if(isset($chapterInfo['pages'][0]) && isset($chapterInfo['pages'][1]))
                        (Pages {{ $chapterInfo['pages'][0] }} - {{ $chapterInfo['pages'][1] }})
                    @endif
                </span>
            </x-button>
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.directive('tooltip', (el, { expression }, { evaluateLater, effect }) => {
            const getTooltipText = evaluateLater(expression)
            effect(() => {
                getTooltipText(text => {
                    el.setAttribute('title', text)
                })
            })
        })
    })
</script>
@endpush
