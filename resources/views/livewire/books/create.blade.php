<?php

use App\Models\Book;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

new class extends Component {
    use WithFileUploads;

    public $title = '';
    public $description = '';
    public $coverImage;
    public $pdfFile;
    public $audioFile;
    public $totalPages = 1;

    public function rules()
    {
        return [
            'title' => 'required|min:3|max:255',
            'description' => 'nullable|string',
            'coverImage' => 'nullable|image|max:2048', // 2MB Max
            'pdfFile' => 'required|mimes:pdf', // Removed size limit
            'audioFile' => 'nullable|mimes:mp3,wav|max:10240', // 10MB Max
            'totalPages' => 'required|integer|min:1',
        ];
    }

    public function messages()
    {
        return [
            'coverImage.max' => 'The cover image must not be greater than 2MB.',
            'pdfFile.required' => 'Please upload a PDF file.',
            'pdfFile.mimes' => 'The file must be a PDF.',
            'audioFile.max' => 'The audio file must not be greater than 10MB.',
            'audioFile.mimes' => 'The audio file must be an MP3 or WAV file.',
        ];
    }

    public function save()
    {
        $this->validate();

        $coverImagePath = null;
        $pdfFilePath = null;
        $audioFilePath = null;

        if ($this->coverImage instanceof TemporaryUploadedFile) {
            $coverImagePath = $this->coverImage->store('book-covers', 'public');
        }

        if ($this->pdfFile instanceof TemporaryUploadedFile) {
            $pdfFilePath = $this->pdfFile->store('book-pdfs', 'public');
        }

        if ($this->audioFile instanceof TemporaryUploadedFile) {
            $audioFilePath = $this->audioFile->store('book-audio', 'public');
        }

        Book::create([
            'title' => $this->title,
            'description' => $this->description,
            'cover_image' => $coverImagePath,
            'pdf_file' => $pdfFilePath,
            'audio_url' => $audioFilePath,
            'total_pages' => $this->totalPages,
        ]);

        session()->flash('message', 'Book created successfully!');
        $this->redirect('/books');
    }

    public function redirectToIndex()
    {
        $this->redirect('/books');
    }
}; ?>

<div>
    <div class="py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Create New Book</h1>
                    <button
                        wire:click="redirectToIndex"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200"
                    >
                        Back to Books
                    </button>
                </div>

                <form wire:submit="save" class="space-y-6">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                        <input
                            type="text"
                            id="title"
                            wire:model="title"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        @error('title')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea
                            id="description"
                            wire:model="description"
                            rows="4"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        ></textarea>
                        @error('description')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Total Pages -->
                    <div>
                        <label for="totalPages" class="block text-sm font-medium text-gray-700">Total Pages</label>
                        <input
                            type="number"
                            id="totalPages"
                            wire:model="totalPages"
                            min="1"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        @error('totalPages')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- PDF File Upload -->
                    <div>
                        <label for="pdfFile" class="block text-sm font-medium text-gray-700">PDF Book File</label>
                        <div class="flex items-center mt-1">
                            <input
                                type="file"
                                id="pdfFile"
                                wire:model="pdfFile"
                                accept="application/pdf"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            >
                        </div>
                        <div wire:loading wire:target="pdfFile">Uploading PDF...</div>
                        @error('pdfFile')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                        @if ($pdfFile)
                            <div class="mt-2 text-sm text-green-600">
                                PDF selected: {{ $pdfFile->getClientOriginalName() }}
                            </div>
                        @endif
                    </div>

                    <!-- Cover Image Upload -->
                    <div>
                        <label for="coverImage" class="block text-sm font-medium text-gray-700">Cover Image (Optional)</label>
                        <div class="flex items-center mt-1">
                            <input
                                type="file"
                                id="coverImage"
                                wire:model="coverImage"
                                accept="image/*"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            >
                        </div>
                        <div wire:loading wire:target="coverImage">Uploading cover image...</div>
                        @error('coverImage')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                        @if ($coverImage)
                            <img src="{{ $coverImage->temporaryUrl() }}" class="object-cover w-32 h-32 mt-2 rounded-lg">
                        @endif
                    </div>

                    <!-- Audio File Upload -->
                    <div>
                        <label for="audioFile" class="block text-sm font-medium text-gray-700">Audio Version (Optional)</label>
                        <div class="flex items-center mt-1">
                            <input
                                type="file"
                                id="audioFile"
                                wire:model="audioFile"
                                accept="audio/mp3,audio/wav"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            >
                        </div>
                        <div wire:loading wire:target="audioFile">Uploading audio...</div>
                        @error('audioFile')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                        @if ($audioFile)
                            <div class="mt-2 text-sm text-green-600">
                                Audio file selected: {{ $audioFile->getClientOriginalName() }}
                            </div>
                        @endif
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="px-4 py-2 text-white bg-blue-500 rounded-lg hover:bg-blue-600"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                        >
                            <span wire:loading.remove>Create Book</span>
                            <span wire:loading>Creating...</span>
                        </button>
                    </div>
                </form>

                @if (session()->has('message'))
                    <div class="p-4 mt-4 text-sm text-green-700 bg-green-100 rounded-lg">
                        {{ session('message') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
