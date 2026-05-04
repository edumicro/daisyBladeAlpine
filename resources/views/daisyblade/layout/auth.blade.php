@props([
    'title' => null,
    'theme' => 'light',
])

<!DOCTYPE html>
<html data-theme="{{ $theme }}" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="antialiased min-h-screen bg-base-200 flex items-center justify-center p-4">

    <x-db::feedback.toast position="end" vertical="top" />

    <div class="card bg-base-100 w-full max-w-md shadow-xl">
        <div class="card-body">

            @isset($logo)
                <div class="flex justify-center mb-6">{{ $logo }}</div>
            @else
                <div class="flex justify-center mb-6">
                    <a href="{{ url('/') }}" class="text-2xl font-extrabold tracking-tight">
                        {{ config('app.name') }}
                    </a>
                </div>
            @endisset

            {{ $slot }}

        </div>
    </div>

    @stack('scripts')
</body>
</html>
