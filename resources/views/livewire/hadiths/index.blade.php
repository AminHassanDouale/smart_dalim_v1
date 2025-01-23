<?php

use App\Models\Hadith;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $search_name = '';

    #[Url]
    public string $source = '';

    #[Url]
    public string $chapter = '';

    public array $sortBy = ['column' => 'source', 'direction' => 'desc'];
    public bool $showFilters = false;

    // Add computed property for debugging
    public function getSourcesProperty()
    {
        $sources = Hadith::distinct()->orderBy('source')->pluck('source')->toArray();
        logger('Available sources:', $sources);
        return $sources;
    }

    public function getChaptersProperty()
    {
        $chapters = Hadith::distinct()->orderBy('chapter')->pluck('chapter')->toArray();
        logger('Available chapters:', $chapters);
        return $chapters;
    }

    public function mount()
    {
        logger('Initial mount - Source: ' . $this->source . ', Chapter: ' . $this->chapter);
    }

    public function filterCount(): int
    {
        return ($this->source ? 1 : 0) +
               ($this->chapter ? 1 : 0) +
               ($this->search_name ? 1 : 0);
    }

    public function hadiths()
    {
        logger('Applying filters - Source: ' . $this->source . ', Chapter: ' . $this->chapter);
        $query = Hadith::query();

        if ($this->search_name) {
            $query->where(function($q) {
                $q->where('text_ar', 'like', "%{$this->search_name}%")
                  ->orWhere('text_en', 'like', "%{$this->search_name}%")
                  ->orWhere('hadith_no', 'like', "%{$this->search_name}%");
            });
        }

        if ($this->source) {
            $query->where('source', $this->source);
        }

        if ($this->chapter) {
            $query->where('chapter', $this->chapter);
        }

        // Log the SQL query for debugging
        logger('SQL Query: ' . $query->toSql());
        logger('SQL Bindings:', $query->getBindings());

        return $query->orderBy(...array_values($this->sortBy))
                    ->paginate(10);
    }

    public function headers(): array
    {
        return [
            ['key' => 'source', 'label' => 'Source', 'sortable' => true],
            ['key' => 'chapter', 'label' => 'Chapter', 'sortable' => true],
            ['key' => 'hadith_no', 'label' => '#', 'sortable' => true],
            ['key' => 'text_ar', 'label' => 'Arabic Text', 'sortable' => false],
            ['key' => 'text_en', 'label' => 'English Text', 'sortable' => false],
        ];
    }

    public function updatedSource($value)
    {
        logger('Source updated to: ' . $value);
        $this->resetPage();
    }

    public function updatedChapter($value)
    {
        logger('Chapter updated to: ' . $value);
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->reset(['search_name', 'source', 'chapter']);
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'hadiths' => $this->hadiths(),
            'sources' => $this->sources,
            'chapters' => $this->chapters,
            'filterCount' => $this->filterCount(),
        ];
    }
};
?>

<div>
    <x-header title="Hadith Collection" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input
                placeholder="Search..."
                wire:model.live.debounce="search_name"
                icon="o-magnifying-glass"
                clearable
            />
        </x-slot:middle>

        <x-slot:actions>
            <x-button
                label="Filters"
                icon="o-funnel"
                :badge="$filterCount"
                badge-classes="font-mono"
                @click="$wire.showFilters = true"
                class="bg-base-300"
                responsive
            />
        </x-slot:actions>
    </x-header>

    <x-card>
        @if ($hadiths->count() > 0)
            <x-table :headers="$headers" :rows="$hadiths" :sort-by="$sortBy" with-pagination>
                @scope('cell_text_ar', $hadith)
                    <div class="text-right" dir="rtl">{{ $hadith->text_ar }}</div>
                @endscope

                @scope('cell_text_en', $hadith)
                    {{ $hadith->text_en }}
                @endscope
            </x-table>
        @else
            <div class="flex items-center justify-center gap-10 mx-auto">
                <div class="text-lg font-medium">
                    No hadiths found matching your search criteria.
                </div>
            </div>
        @endif
    </x-card>

    {{-- FILTERS DRAWER --}}
    <x-drawer wire:model="showFilters" title="Filter Hadiths" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5" @keydown.enter="$wire.showFilters = false">
            <x-input
                label="Search Text"
                wire:model.live.debounce="search_name"
                icon="o-magnifying-glass"
                inline
            />

            <x-select
                label="Source"
                wire:model="source"
                :options="$sources"
                placeholder="All Sources"
                inline
            />

            <x-select
                label="Chapter"
                wire:model="chapter"
                :options="$chapters"
                placeholder="All Chapters"
                inline
            />
        </div>

        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
        </x-slot:actions>
    </x-drawer>
</div>
