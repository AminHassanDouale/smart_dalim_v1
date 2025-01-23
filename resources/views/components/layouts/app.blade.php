<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/vanilla-calendar-pro@2.9.6/build/vanilla-calendar.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/vanilla-calendar-pro@2.9.6/build/vanilla-calendar.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Traditional+Arabic&display=swap" rel="stylesheet">



    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('/favicon.ico') }}">
    <link rel="mask-icon" href="{{ asset('/favicon.ico') }}" color="#ff2d20">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    {{--  Meta description  --}}
    <meta name="description" content="Orange - Livewire 3 demo built with MaryUI">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200/50">

{{-- The navbar with `sticky` and `full-width` --}}
<x-nav sticky>


    {{-- Right side actions --}}
    <x-slot:actions>

    </x-slot:actions>
</x-nav>

<x-main>
    <x-slot:content>
        {{ $slot }}
    </x-slot:content>
</x-main>

{{-- TOAST AREA --}}
<x-toast />
</body>
</html>
