<div class="prose max-w-none">
    <h3 class="mb-4 text-lg font-medium text-gray-900">About this Chapter</h3>

    @if(!empty($chapterDetails['short_text']))
        <div class="mb-4">
            <h4 class="mb-2 text-sm font-medium text-gray-700">Summary</h4>
            <div class="text-sm text-gray-600">
                {!! $chapterDetails['short_text'] !!}
            </div>
        </div>
    @endif

    @if(!empty($chapterDetails['text']))
        <div class="text-sm text-gray-600">
            {!! $chapterDetails['text'] !!}
        </div>
    @endif

    @if(!empty($chapterDetails['source']))
        <p class="mt-4 text-xs text-gray-500">
            Source: {{ $chapterDetails['source'] }}
        </p>
    @endif

    @if(!empty($chapterInfo))
        <div class="mt-6 space-y-4">
            @if(!empty($chapterInfo['name_complex']))
                <div>
                    <span class="text-sm text-gray-500">Name (Complex):</span>
                    <span class="ml-2 text-sm font-medium">{{ $chapterInfo['name_complex'] }}</span>
                </div>
            @endif

            @if(!empty($chapterInfo['name_arabic']))
                <div>
                    <span class="text-sm text-gray-500">Arabic Name:</span>
                    <span class="mr-2 text-lg font-arabic" dir="rtl">{{ $chapterInfo['name_arabic'] }}</span>
                </div>
            @endif

            @if(!empty($chapterInfo['translated_name']['name']))
                <div>
                    <span class="text-sm text-gray-500">Translated Name:</span>
                    <span class="ml-2 text-sm font-medium">{{ $chapterInfo['translated_name']['name'] }}</span>
                </div>
            @endif
        </div>
    @endif

    @if(!empty($chapterDetails['chapter_id']))
        <div class="pt-6 mt-6 border-t border-gray-200">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-500">Chapter ID</span>
                <span class="font-medium">{{ $chapterDetails['chapter_id'] }}</span>
            </div>
        </div>
    @endif
</div>

@if(!empty($chapterInfo['revelation_place']))
    <div class="mt-6 p-4 rounded-lg {{ $chapterInfo['revelation_place'] === 'makkah' ? 'bg-amber-50' : 'bg-green-50' }}">
        <div class="flex items-center">
            <x-icon name="o-map-pin" class="w-5 h-5 {{ $chapterInfo['revelation_place'] === 'makkah' ? 'text-amber-600' : 'text-green-600' }}" />
            <span class="ml-2 text-sm font-medium {{ $chapterInfo['revelation_place'] === 'makkah' ? 'text-amber-800' : 'text-green-800' }}">
                Revealed in {{ ucfirst($chapterInfo['revelation_place']) }}
            </span>
        </div>

        @if($chapterInfo['revelation_order'])
            <div class="mt-2 text-xs {{ $chapterInfo['revelation_place'] === 'makkah' ? 'text-amber-600' : 'text-green-600' }}">
                Revelation Order: {{ $chapterInfo['revelation_order'] }} of 114
            </div>
        @endif
    </div>
@endif

@if(!empty($chapterDetails['id']))
    <div class="mt-6">
        <h4 class="mb-3 text-sm font-medium text-gray-900">Additional Resources</h4>
        <div class="flex flex-wrap gap-2">
            <button
                wire:click="viewAllVerses"
                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                <x-icon name="o-book-open" class="w-4 h-4 mr-2" />
                View Full Chapter
            </button>

            @if($audioFile)
                <button
                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    <x-icon name="o-musical-note" class="w-4 h-4 mr-2" />
                    Listen Audio
                </button>
            @endif
        </div>
    </div>
@endif

<div class="mt-6 text-xs text-gray-500">
    <p>Last updated: {{ now()->format('F j, Y') }}</p>
</div>
