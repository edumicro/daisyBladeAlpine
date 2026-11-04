@props([
    'title' => null,
    'theme' => 'light',
    'lang' => str_replace('_', '-', app()->getLocale()),
])

<!DOCTYPE html>
<html {{ $attributes->merge(['data-theme' => $theme, 'lang' => $lang]) }}>
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
{{-- `x-data` on the body: the page-wide scope of the caller. The components do not give their root a scope of their own (it would
     hide the `x-ref` and the data of the caller), so the `@click`, `x-show`... the caller writes on a component need one above. --}}
<body x-data class="antialiased">

    {{-- Global toast notifications — dispatch 'dbl-toast-show' from anywhere --}}
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
