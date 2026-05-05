@props([
    'title'       => '',
    'subtitle'    => '',
    'body'        => '',
    'image'       => '',
    'imageAlt'    => 'Card Image',
    'actions'     => [],
    'shadow'      => true,
    'compact'     => false,
    'background'  => 'base-100',
    'borderColor' => '',
    'hoverable'   => false,
    'glass'       => false,
    'class'       => '',
])

<div {{ $attributes->merge(['class' => implode(' ', array_filter([
    "card bg-{$background}",
    $shadow    ? 'shadow-md' : '',
    $hoverable ? 'hover:shadow-lg transition-shadow cursor-pointer' : '',
    $glass     ? 'glass' : '',
    $compact   ? 'card-compact' : '',
    $borderColor ? "border border-{$borderColor}" : '',
]))]) }}>

    @if($image)
        <figure class="relative overflow-hidden">
            <img src="{{ $image }}" alt="{{ $imageAlt }}" class="w-full h-64 object-cover" />
        </figure>
    @endif

    <div class="card-body {{ $class }}">
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
                    <a href="{{ $action['url'] ?? '#' }}" class="btn {{ $action['variant'] ?? 'btn-primary' }} btn-sm">
                        @if(isset($action['icon']))
                            <x-dynamic-component :component="$action['icon']" class="w-4 h-4" />
                        @endif
                        {{ $action['label'] ?? 'Action' }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
