@props([
    'text'         => '',
    'position'     => 'top',
    'color'        => 'info',   // primary | secondary | accent | neutral | info | success | warning | error
    'icon'         => 'heroicon-o-question-mark-circle',
    'display'      => 'icon',
    'triggerClass' => '',
])

@php
$posClass   = match($position) { 'bottom'=>'tooltip-bottom','left'=>'tooltip-left','right'=>'tooltip-right', default=>'tooltip-top' };
// The color is a token; the class names are written out in full so Tailwind can find them.
// A value that is not listed gets the default look.
$colorClass = match($color) {
    'neutral' => 'text-neutral',
    'primary' => 'text-primary',
    'secondary' => 'text-secondary',
    'accent' => 'text-accent',
    'success' => 'text-success',
    'warning' => 'text-warning',
    'error' => 'text-error',
    default => 'text-info',
};
@endphp

<div {{ $attributes->merge(['class' => "tooltip {$posClass}"]) }} data-tip="{{ $text }}">
    @if($slot->isNotEmpty())
        {{ $slot }}
    @elseif($display === 'icon')
        <x-dynamic-component :component="$icon" class="w-5 h-5 cursor-help {{ $colorClass }} {{ $triggerClass }}" />
    @elseif($display === 'text')
        <span class="cursor-help underline underline-offset-2 {{ $colorClass }} {{ $triggerClass }}">{{ $text }}</span>
    @else
        <div class="flex items-center gap-1 cursor-help {{ $triggerClass }}">
            <x-dynamic-component :component="$icon" class="w-4 h-4 {{ $colorClass }}" />
            <span class="text-sm underline underline-offset-2 {{ $colorClass }}">{{ $text }}</span>
        </div>
    @endif
</div>
