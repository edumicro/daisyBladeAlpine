@props([
    'label'          => '',
    'icon'           => '',
    'iconSide'       => 'left',
    'vertical'       => false,
    'class'          => '',
    'containerClass' => '',
])

<div class="w-full {{ $containerClass }}">
    <div {{ $attributes->merge(['class' => trim('divider ' . ($vertical ? 'divider-vertical ' : '') . $class)]) }}>
        @if($icon && $iconSide === 'left')
            <x-dynamic-component :component="$icon" class="h-4 w-4" />
        @endif

        @if($label)
            <span>{{ $label }}</span>
        @elseif($slot->isNotEmpty())
            <span>{{ $slot }}</span>
        @endif

        @if($icon && $iconSide === 'right')
            <x-dynamic-component :component="$icon" class="h-4 w-4" />
        @endif
    </div>
</div>
