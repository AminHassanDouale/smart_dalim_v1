<div class="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($this->formattedChapters as $chapter)
            <div wire:key="chapter-{{ $chapter['id'] }}" class="bg-white border rounded-lg shadow-sm">
                <div class="p-4">
                    <!-- Chapter Header -->
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <span class="text-lg font-semibold text-gray-700">{{ $chapter['id'] }}</span>
                            <h3 class="text-lg font-medium">{{ $chapter['name_simple'] }}</h3>
                            <div class="text-sm text-gray-500">{{ $chapter['translated_name']['name'] }}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-arabic">{{ $chapter['name_arabic'] }}</span>
                        </div>
                    </div>

                    <!-- Chapter Info -->
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex justify-between">
                            <span>{{ ucfirst($chapter['revelation_place']) }}</span>
                            <span>{{ $chapter['verses_count'] }} verses</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Pages {{ $chapter['pages_range'] }}</span>
                        </div>
                    </div>

                    <!-- Audio Section -->
                    @if($chapter['has_audio'])
                        <div class="pt-4 mt-4 border-t">
                            <audio
                                class="w-full"
                                controls
                                preload="none"
                            >
                                <source src="{{ $chapter['audio']['audio_url'] }}" type="audio/{{ $chapter['audio']['format'] }}">
                                Your browser does not support the audio element.
                            </audio>
                            <div class="flex items-center justify-between mt-2 text-sm text-gray-500">
                                <span>{{ $this->formatBytes($chapter['audio']['file_size']) }}</span>
                                <a
                                    href="{{ $chapter['audio']['audio_url'] }}"
                                    download
                                    class="inline-flex items-center text-indigo-600 hover:text-indigo-900"
                                >
                                    <x-icon name="m-list-bullet" class="w-4 h-4 mr-1" />
                                    Download
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- Action Button -->
                    <div class="mt-4">
                        <button
                            wire:click="navigateToChapter({{ $chapter['id'] }})"
                            class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <x-icon name="s-book-open" class="w-4 h-4 mr-2" />
                            Read Chapter
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-gray-500 bg-white rounded-lg col-span-full">
                No chapters found matching your criteria
            </div>
        @endforelse
    </div>
</div>
