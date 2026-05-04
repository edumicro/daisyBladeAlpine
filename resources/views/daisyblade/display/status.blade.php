@props([
    'status'   => 'pending',
    'text'     => '',
    'label'    => '',
    'icon'     => '',
    'pulse'    => false,
    'size'     => 'md',
    'position' => 'inline',  // inline | badge | dot
    'class'    => '',
])

@php
$map = [
    'online'     => ['color' => 'badge-success',  'icon' => 'heroicon-o-check-circle',    'label' => 'Online'],
    'offline'    => ['color' => 'badge-neutral',   'icon' => 'heroicon-o-minus-circle',    'label' => 'Offline'],
    'pending'    => ['color' => 'badge-warning',   'icon' => 'heroicon-o-clock',           'label' => 'Pending'],
    'active'     => ['color' => 'badge-success',   'icon' => 'heroicon-o-check-circle',    'label' => 'Active'],
    'inactive'   => ['color' => 'badge-neutral',   'icon' => 'heroicon-o-minus-circle',    'label' => 'Inactive'],
    'processing' => ['color' => 'badge-info',      'icon' => 'heroicon-o-arrow-path',      'label' => 'Processing'],
    'success'    => ['color' => 'badge-success',   'icon' => 'heroicon-o-check-circle',    'label' => 'Success'],
    'error'      => ['color' => 'badge-error',     'icon' => 'heroicon-o-x-circle',        'label' => 'Error'],
];
$info      = $map[$status] ?? $map['pending'];
$colorClass = $info['color'];
$iconName   = $icon ?: $info['icon'];
$displayText = $text ?: $label ?: $info['label'];
$sizeClass  = match($size) { 'xs' => 'badge-xs', 'sm' => 'badge-sm', 'lg' => 'badge-lg', default => '' };
@endphp

@if($position === 'badge')
    <span {{ $attributes->merge(['class' => "badge {$colorClass} {$sizeClass} gap-1 " . ($pulse ? 'animate-pulse ' : '') . $class]) }}>
        <x-dynamic-component :component="$iconName" class="w-3 h-3" />
        {{ $displayText }}
    </span>
@elseif($position === 'dot')
    <div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
        <div class="w-2.5 h-2.5 rounded-full {{ $colorClass }} {{ $pulse ? 'animate-pulse' : '' }}"></div>
        <span class="text-sm {{ $class }}">{{ $displayText }}</span>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
        <span class="inline-block w-2.5 h-2.5 rounded-full {{ $colorClass }} {{ $pulse ? 'animate-pulse' : '' }}"></span>
        <span class="text-sm font-medium {{ $class }}">{{ $displayText }}</span>
    </div>
@endif
