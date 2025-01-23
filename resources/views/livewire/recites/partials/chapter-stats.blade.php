<div class="space-y-4">
    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Revelation Place</span>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $chapterInfo['revelation_place'] === 'makkah' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
            {{ ucfirst($chapterInfo['revelation_place']) }}
        </span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Verses Count</span>
        <span class="text-sm font-medium">{{ $chapterInfo['verses_count'] }}</span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Pages</span>
        <span class="text-sm font-medium">{{ $chapterInfo['pages'][0] }} - {{ $chapterInfo['pages'][1] }}</span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Revelation Order</span>
        <span class="text-sm font-medium">{{ $chapterInfo['revelation_order'] }}</span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Bismillah Pre-Chapter</span>
        <span class="text-sm font-medium">{{ $chapterInfo['bismillah_pre'] ? 'Yes' : 'No' }}</span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Juz Numbers</span>
        <span class="text-sm font-medium">
            @if(!empty($verses))
                {{ collect($verses)->pluck('juz_number')->unique()->implode(', ') }}
            @endif
        </span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Hizb Numbers</span>
        <span class="text-sm font-medium">
            @if(!empty($verses))
                {{ collect($verses)->pluck('hizb_number')->unique()->implode(', ') }}
            @endif
        </span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Rub Numbers</span>
        <span class="text-sm font-medium">
            @if(!empty($verses))
                {{ collect($verses)->pluck('rub_number')->unique()->implode(', ') }}
            @endif
        </span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Page Range</span>
        <span class="text-sm font-medium">
            @if(!empty($verses))
                {{ collect($verses)->pluck('page_number')->min() }} - {{ collect($verses)->pluck('page_number')->max() }}
            @endif
        </span>
    </div>

    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">Language</span>
        <span class="text-sm font-medium">{{ collect($languages)->where('id', $selectedLanguage)->first()['name'] }}</span>
    </div>
</div>

@if(collect($verses)->contains('sajdah_type'))
<div class="mt-6 p-4 bg-amber-50 rounded-lg">
    <h4 class="text-sm font-medium text-amber-800 mb-2">Sajdah Verses</h4>
    <div class="space-y-2">
        @foreach($verses as $verse)
            @if($verse['sajdah_type'])
                <div class="flex items-center justify-between text-amber-700">
                    <span class="text-sm">Verse {{ $verse['verse_number'] }}</span>
                    <span class="text-xs font-medium px-2 py-1 bg-amber-100 rounded">
                        {{ ucfirst($verse['sajdah_type']) }}
                    </span>
                </div>
            @endif
        @endforeach
    </div>
</div>
@endif

<div class="mt-6">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h4 class="text-sm font-medium text-gray-900 mb-2">Navigation</h4>
            <div class="space-y-2">
                <button wire:click="viewAllVerses"
                        class="w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50 rounded-lg transition-colors duration-200">
                    <span class="flex items-center">
                        <x-icon name="o-book-open" class="w-4 h-4 mr-2" />
                        View All Pages
                    </span>
                </button>

                <button wire:click="backToChapters"
                        class="w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50 rounded-lg transition-colors duration-200">
                    <span class="flex items-center">
                        <x-icon name="o-chevron-left" class="w-4 h-4 mr-2" />
                        All Chapters
                    </span>
                </button>
            </div>
        </div>

        <div>
            <h4 class="text-sm font-medium text-gray-900 mb-2">Display</h4>
            <div class="space-y-2">
                <button wire:click="toggleTajweed"
                        class="w-full px-3 py-2 text-sm text-left hover:bg-gray-50 rounded-lg transition-colors duration-200
                               {{ $showTajweed ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700' }}">
                    <span class="flex items-center">
                        <x-icon name="o-sparkles" class="w-4 h-4 mr-2" />
                        Tajweed View
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
