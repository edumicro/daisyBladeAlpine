@props([
    'text'         => '',
    'position'     => 'top',
    'color'        => 'info',
    'icon'         => 'heroicon-o-question-mark-circle',
    'display'      => 'icon',
    'class'        => '',
    'tooltipClass' => '',
])

@php
$posClass   = match($position) { 'bottom'=>'tooltip-bottom','left'=>'tooltip-left','right'=>'tooltip-right', default=>'tooltip-top' };
$colorClass = match($color) { 'success'=>'text-success','warning'=>'text-warning','error'=>'text-error', default=>'text-info' };
@endphp

<div class="tooltip {{ $posClass }} {{ $tooltipClass }}" data-tip="{{ $text }}">
    @if($slot->isNotEmpty())
        {{ $slot }}
    @elseif($display === 'icon')
        <x-dynamic-component :component="$icon" class="w-5 h-5 cursor-help {{ $colorClass }} {{ $class }}" />
    @elseif($display === 'text')
        <span class="cursor-help underline underline-offset-2 {{ $colorClass }} {{ $class }}">{{ $text }}</span>
    @else
        <div class="flex items-center gap-1 cursor-help {{ $class }}">
            <x-dynamic-component :component="$icon" class="w-4 h-4 {{ $colorClass }}" />
            <span class="text-sm underline underline-offset-2 {{ $colorClass }}">{{ $text }}</span>
        </div>
    @endif
</div>
