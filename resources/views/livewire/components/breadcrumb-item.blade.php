@props(['first' => false])

<li class="inline-flex items-center">
    @if(!$first)
        <svg class="w-3 h-3 mx-1 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
        </svg>
    @endif

    <button {{ $attributes->merge([
        'class' => 'inline-flex items-center text-sm font-medium ' .
                  ($first ? 'text-gray-700 hover:text-indigo-600' : 'text-gray-500 hover:text-gray-700')
    ]) }}>
        {{ $slot }}
    </button>
</li>
