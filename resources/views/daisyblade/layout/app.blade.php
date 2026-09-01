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
<body class="antialiased">

    {{-- Global toast notifications — dispatch 'notify' from anywhere --}}
    <x-dbl::feedback.toast position="end" vertical="top" />

    <div class="flex min-h-screen">

        @isset($sidebar)
            {{ $sidebar }}
        @endisset

        <div class="flex flex-col flex-1 min-w-0">

            @isset($navbar)
                {{ $navbar }}
            @endisset

            {{-- El lienzo va un tono por debajo de las tarjetas. Sin esto el contenido es
                 `base-100` y las tarjetas también: una tarjeta sobre su mismo color no se ve como
                 tarjeta, solo como texto con sombra, y una pantalla de seis secciones se lee como
                 un único bloque continuo. `layout.auth` ya usaba `base-200` por lo mismo. --}}
            <main class="flex-1 p-6 bg-base-200">
                {{ $slot }}
            </main>

            @isset($footer)
                {{ $footer }}
            @endisset

        </div>
    </div>

    @stack('scripts')
</body>
</html>
