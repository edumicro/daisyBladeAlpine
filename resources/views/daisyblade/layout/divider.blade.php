@props([
    'label'          => '',
    'icon'           => '',
    'iconSide'       => 'left',
    'vertical'       => false,
    'dividerClass'   => '',
])

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    <div class="{{ trim('divider ' . ($vertical ? 'divider-vertical ' : '') . $dividerClass) }}">
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
