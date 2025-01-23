<?php

use App\Models\Book;
use Livewire\Volt\Component;
use setasign\Fpdi\Fpdi;

new class extends Component {
    public Book $book;
    public $currentPage = 1;
    public $totalPages = 1;
    public $pdfUrl;
    public $showPageSelector = false;
    public $jumpToPage = 1;

    public function mount($id): void
    {
        $this->book = Book::findOrFail($id);
        $this->pdfUrl = asset('storage/' . $this->book->pdf_file);
        // Get total pages from the book
        $this->totalPages = $this->book->total_pages;
        $this->jumpToPage = $this->currentPage;
    }

    public function nextPage()
    {
        if ($this->currentPage < $this->totalPages) {
            $this->currentPage++;
            $this->jumpToPage = $this->currentPage;
        }
    }

    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
            $this->jumpToPage = $this->currentPage;
        }
    }

    public function togglePageSelector()
    {
        $this->showPageSelector = !$this->showPageSelector;
    }

    public function jumpTo()
    {
        $page = max(1, min($this->jumpToPage, $this->totalPages));
        $this->currentPage = $page;
        $this->showPageSelector = false;
    }

    public function redirectToIndex()
    {
        $this->redirect('/books');
    }
}; ?>

<div class="py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow-xl">
        <!-- Header -->
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $book->title }}</h1>
                    @if($book->description)
                        <p class="mt-2 text-gray-600">{{ $book->description }}</p>
                    @endif
                </div>
                <button
                    wire:click="redirectToIndex"
                    class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200"
                >
                    Back to Books
                </button>
            </div>
        </div>

        <!-- Book Viewer -->
        <div class="p-6">
            <!-- PDF Viewer -->
            <div class="relative mb-6 overflow-hidden bg-gray-100 rounded-lg" style="height: 800px;">
                <iframe
                    src="{{ $pdfUrl }}#page={{ $currentPage }}"
                    class="w-full h-full"
                    type="application/pdf"
                >
                </iframe>

                <!-- Navigation Overlay -->
                <div class="absolute bottom-0 left-0 right-0 p-4 bg-black bg-opacity-50">
                    <div class="flex items-center justify-between text-white">
                        <!-- Previous Button -->
                        <button
                            wire:click="previousPage"
                            class="p-2 rounded-full hover:bg-white hover:bg-opacity-20 transition-colors duration-200
                                  {{ $currentPage <= 1 ? 'opacity-50 cursor-not-allowed' : '' }}"
                            {{ $currentPage <= 1 ? 'disabled' : '' }}
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <!-- Page Navigation -->
                        <div class="flex items-center space-x-4">
                            @if(!$showPageSelector)
                                <button
                                    wire:click="togglePageSelector"
                                    class="text-white hover:underline"
                                >
                                    Page {{ $currentPage }} of {{ $totalPages }}
                                </button>
                            @else
                                <div class="flex items-center space-x-2">
                                    <input
                                        type="number"
                                        wire:model="jumpToPage"
                                        min="1"
                                        max="{{ $totalPages }}"
                                        class="w-20 px-2 py-1 text-black rounded"
                                    >
                                    <span class="text-white">of {{ $totalPages }}</span>
                                    <button
                                        wire:click="jumpTo"
                                        class="px-3 py-1 text-white bg-blue-500 rounded hover:bg-blue-600"
                                    >
                                        Go
                                    </button>
                                </div>
                            @endif
                        </div>

                        <!-- Next Button -->
                        <button
                            wire:click="nextPage"
                            class="p-2 rounded-full hover:bg-white hover:bg-opacity-20 transition-colors duration-200
                                  {{ $currentPage >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' }}"
                            {{ $currentPage >= $totalPages ? 'disabled' : '' }}
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Additional Book Information -->
            <div class="grid grid-cols-1 gap-6 mt-6 md:grid-cols-2">
                <!-- Cover Image -->
                @if($book->cover_image)
                    <div>
                        <h3 class="mb-3 text-lg font-medium text-gray-900">Book Cover</h3>
                        <img
                            src="{{ asset('storage/' . $book->cover_image) }}"
                            alt="{{ $book->title }} cover"
                            class="w-full max-w-sm rounded-lg shadow-md"
                        >
                    </div>
                @endif

                <!-- Audio Player -->
                @if($book->audio_url)
                    <div>
                        <h3 class="mb-3 text-lg font-medium text-gray-900">Audio Version</h3>
                        <audio controls class="w-full">
                            <source src="{{ asset('storage/' . $book->audio_url) }}" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowRight') {
            Livewire.dispatch('nextPage');
        } else if (e.key === 'ArrowLeft') {
            Livewire.dispatch('previousPage');
        }
    });
</script>
