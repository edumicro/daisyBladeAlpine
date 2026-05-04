@props([
    'title'          => '',
    'label'          => '',
    'content'        => '',
    'open'           => false,
    'icon'           => '',
    'iconSide'       => 'left',
    'variant'        => 'default',
    'class'          => '',
    'containerClass' => '',
])

@php
$contentClass = match($variant) {
    'primary'   => 'bg-primary/10',
    'secondary' => 'bg-secondary/10',
    'accent'    => 'bg-accent/10',
    'info'      => 'bg-info/10',
    'success'   => 'bg-success/10',
    'warning'   => 'bg-warning/10',
    'error'     => 'bg-error/10',
    default     => 'bg-base-200/50',
};
@endphp

<div {{ $attributes->merge(['class' => "collapse collapse-arrow border border-base-300 bg-base-100 {$containerClass}"]) }}>
    <input type="checkbox" {{ $open ? 'checked' : '' }} />

    <div class="collapse-title flex items-center gap-2 font-medium {{ $class }}">
        @if($icon && $iconSide === 'left')
            <x-dynamic-component :component="$icon" class="w-5 h-5 shrink-0" />
        @endif
        <span>{{ $title ?: $label }}</span>
        @if($icon && $iconSide === 'right')
            <x-dynamic-component :component="$icon" class="w-5 h-5 shrink-0 ml-auto" />
        @endif
    </div>

    <div class="collapse-content {{ $contentClass }}">
        <div class="py-2">
            @if($content) {!! $content !!} @else {{ $slot }} @endif
        </div>
    </div>
</div>
