<?php

use function Livewire\Volt\{state, mount};
use App\Models\User;

state([
    'teachers' => [],
]);

// Mount component and load initial data
mount(function() {
    try {
        $this->teachers = User::where('role', 'teacher')->paginate(15);
    } catch (\Exception $e) {
        \Log::error('Error loading teachers: ' . $e->getMessage());
        $this->teachers = collect([]);
    }
});

?>

<div>
    <h2 class="mb-4 text-2xl font-semibold">Teacher List</h2>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr>
                    <th class="px-4 py-2 border-b">Name</th>
                    <th class="px-4 py-2 border-b">Email</th>
                </tr>
            </thead>
            <tbody>
                @forelse($teachers as $teacher)
                    <tr>
                        <td class="px-4 py-2 border-b">{{ $teacher->name }}</td>
                        <td class="px-4 py-2 border-b">{{ $teacher->email }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-4 py-2 text-center border-b">No teachers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $teachers->links() }}
    </div>
</div>
