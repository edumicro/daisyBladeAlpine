@props([
    'title' => null,
    'theme' => 'light',
])

<!DOCTYPE html>
<html {{ $attributes->merge(['data-theme' => $theme, 'lang' => str_replace('_', '-', app()->getLocale())]) }}>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ $title ?? config('app.name') }}</title>
    {{-- Alpine removes x-cloak once it has started. Until then the elements marked with it (46 places in the
         components) must stay hidden, and the package does not ship a stylesheet to say so. --}}
    <style>[x-cloak] { display: none !important; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
{{-- `x-data` on the body: the page-wide scope of the caller (see layout.app). --}}
<body x-data class="antialiased min-h-screen bg-base-200 flex items-center justify-center p-4">

    <x-dbl::feedback.toast position="end" vertical="top" />

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
