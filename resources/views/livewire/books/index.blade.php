<?php

use App\Models\Book;
use Livewire\Volt\Component;

new class extends Component
{
    public $books;
    public $selectedBook = null;
    public $currentPage = 1;
    public $isAudioPlaying = false;

    public function mount()
    {
        $this->books = Book::all();
    }

    public function selectBook($bookId)
    {
        $this->selectedBook = $this->books->find($bookId);
        $this->currentPage = 1;
    }

    public function nextPage()
    {
        if ($this->selectedBook && $this->currentPage < $this->selectedBook->total_pages) {
            $this->currentPage++;
        }
    }

    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    public function toggleAudio()
    {
        $this->isAudioPlaying = !$this->isAudioPlaying;
    }

    // Add new redirect method
    public function redirectToCreate(): void
    {
        $this->redirect('/books/create');
    }
    public function redirectToView($bookId): void
    {
        $this->redirect("/books/show/{$bookId}");
    }
}
?>
<div>
    <div class="py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <!-- Book Grid -->
        <div class="flex justify-between mb-6">
            <button
                wire:click="redirectToCreate"
                class="flex items-center px-4 py-2 text-white bg-blue-500 rounded-lg hover:bg-blue-600"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Create New Book
            </button>
        </div>
        <div class="grid grid-cols-1 gap-6 mb-8 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach($books as $book)
                <div class="overflow-hidden transition transform bg-white rounded-lg shadow-lg">
                    <div
                        wire:click="selectBook({{ $book->id }})"
                        class="cursor-pointer"
                    >
                        @if($book->cover_image)
                            <img
                                src="{{ asset($book->cover_image) }}"
                                alt="{{ $book->title }}"
                                class="object-cover w-full h-48"
                            >
                        @else
                            <div class="flex items-center justify-center w-full h-48 bg-gray-200">
                                <span class="text-gray-400">No Cover</span>
                            </div>
                        @endif
                        <div class="p-4">
                            <h3 class="mb-2 text-lg font-semibold">{{ $book->title }}</h3>
                            <p class="text-sm text-gray-600">{{ Str::limit($book->description, 100) }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end px-4 pb-4">
                        <button
                        wire:click="redirectToView({{ $book->id }})"
                        class="px-4 py-2 text-sm text-white bg-blue-500 rounded hover:bg-blue-600"
                    >
                        View Details
                    </button>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Book Preview -->
        @if($selectedBook)
            <div class="p-6 bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold">{{ $selectedBook->title }}</h2>
                    <div class="flex gap-4">
                        <button
                            wire:click="redirectToView({{ $selectedBook->id }})"
                            class="px-4 py-2 text-white bg-blue-500 rounded-lg hover:bg-blue-600"
                        >
                            View Full Details
                        </button>
                        @if($selectedBook->audio_url)
                            <button
                                wire:click="toggleAudio"
                                class="p-2 rounded-full {{ $isAudioPlaying ? 'bg-blue-500 text-white' : 'bg-gray-200' }}"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Preview Content -->
                <div class="p-6 mb-6 rounded-lg min-h-96 bg-gray-50">
                    <p class="text-lg leading-relaxed">
                        Page {{ $currentPage }} content goes here...
                    </p>
                </div>

                <!-- Navigation Controls -->
                <div class="flex items-center justify-between">
                    <button
                        wire:click="previousPage"
                        @class([
                            'px-4 py-2 rounded-lg',
                            'bg-blue-500 text-white hover:bg-blue-600' => $currentPage > 1,
                            'bg-gray-200 text-gray-400 cursor-not-allowed' => $currentPage <= 1
                        ])
                        @disabled($currentPage <= 1)
                    >
                        Previous
                    </button>

                    <span class="text-gray-600">
                        Page {{ $currentPage }} of {{ $selectedBook->total_pages }}
                    </span>

                    <button
                        wire:click="nextPage"
                        @class([
                            'px-4 py-2 rounded-lg',
                            'bg-blue-500 text-white hover:bg-blue-600' => $currentPage < $selectedBook->total_pages,
                            'bg-gray-200 text-gray-400 cursor-not-allowed' => $currentPage >= $selectedBook->total_pages
                        ])
                        @disabled($currentPage >= $selectedBook->total_pages)
                    >
                        Next
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
