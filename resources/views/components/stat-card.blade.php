@props([
    'title',
    'value',
    'icon'
])

<div class="p-4 bg-white rounded-lg shadow">
    <div class="flex items-center">
        <div class="p-3 rounded-full bg-primary-100 text-primary-600">
            <x-icon :name="$icon" class="w-6 h-6" />
        </div>
        <div class="ml-4">
            <h3 class="text-sm text-gray-500">{{ $title }}</h3>
            <p class="text-2xl font-semibold">{{ $value }}</p>
        </div>
    </div>
</div>
