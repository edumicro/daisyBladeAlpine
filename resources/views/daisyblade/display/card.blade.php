@props([
    'title'       => '',
    'subtitle'    => '',
    'body'        => '',
    'image'       => '',
    'imageAlt'    => null,   // null: the translated default
    'actions'     => [],
    'shadow'      => true,
    'compact'     => false,
    'background'  => 'base-100',   // base-100 | base-200 | base-300 | primary | secondary | accent | neutral | info | success | warning | error
    'hoverable'   => false,
    'glass'       => false,
    'bodyClass'   => '',
])

@php
// The background is a token; the class names are written out in full so Tailwind can find them.
// A border color is not a prop: it goes in `class` (`class="border border-primary"`).
// A value that is not listed gets the default look.
$backgroundClass = match($background) {
    'base-200' => 'bg-base-200 text-base-content',
    'base-300' => 'bg-base-300 text-base-content',
    'neutral' => 'bg-neutral text-neutral-content',
    'primary' => 'bg-primary text-primary-content',
    'secondary' => 'bg-secondary text-secondary-content',
    'accent' => 'bg-accent text-accent-content',
    'info' => 'bg-info text-info-content',
    'success' => 'bg-success text-success-content',
    'warning' => 'bg-warning text-warning-content',
    'error' => 'bg-error text-error-content',
    default => 'bg-base-100 text-base-content',
};
$actionClassOf = fn (?string $color) => match($color) {
    'neutral' => 'btn-neutral',
    'secondary' => 'btn-secondary',
    'accent' => 'btn-accent',
    'info' => 'btn-info',
    'success' => 'btn-success',
    'warning' => 'btn-warning',
    'error' => 'btn-error',
    'ghost' => 'btn-ghost',
    'link' => 'btn-link',
    default => 'btn-primary',
};
@endphp

{{-- `class` NO se declara como prop a propósito. Declararlo lo saca de `$attributes` y hay que
     colocarlo a mano; aquí se colocaba en el `card-body`, así que un `class="mb-6"` —que en
     cualquier otro componente separa la tarjeta de la siguiente— acababa como margen INTERIOR y
     por fuera no se movía nada. Los doce usos que hay en el proyecto son todos intención de fuera
     (`mb-6`, `mt-6 max-w-xl`), o sea que ninguno hacía lo que decía.

     Ahora `class` cae en `$attributes` y Blade lo fusiona en la raíz, como en todos los demás
     componentes. Para el interior está `bodyClass`. --}}

<div {{ $attributes->merge(['class' => implode(' ', array_filter([
    "card {$backgroundClass}",
    $shadow    ? 'shadow-md' : '',
    $hoverable ? 'hover:shadow-lg transition-shadow cursor-pointer' : '',
    $glass     ? 'glass' : '',
    $compact   ? 'card-sm' : '',
]))]) }}>

    @if($image)
        <figure class="relative overflow-hidden">
            <img src="{{ $image }}" alt="{{ $imageAlt ?? __('Card Image') }}" class="w-full h-64 object-cover" />
        </figure>
    @endif

    <div class="card-body {{ $bodyClass }}">
        @if($title)
            <h2 class="card-title">{{ $title }}</h2>
        @endif

        @if($subtitle)
            <p class="text-sm text-base-content/70">{{ $subtitle }}</p>
        @endif

        @if($body)
            <div class="prose prose-sm max-w-none">{!! $body !!}</div>
        @endif

        {{ $slot }}

        @if(!empty($actions))
            <div class="card-actions justify-end gap-2 mt-4">
                @foreach($actions as $action)
                    <a href="{{ $action['url'] ?? '#' }}" class="btn {{ $actionClassOf($action['color'] ?? null) }} btn-sm">
                        @if(isset($action['icon']))
                            <x-dynamic-component :component="$action['icon']" class="w-4 h-4" />
                        @endif
                        {{ $action['label'] ?? __('Action') }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
