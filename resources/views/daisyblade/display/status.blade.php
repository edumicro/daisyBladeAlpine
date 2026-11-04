@props([
    'status'   => 'pending',
    'text'     => '',
    'label'    => '',
    'icon'     => '',
    'pulse'    => false,
    'size'     => 'md',   // xs | sm | md | lg | xl
    'position' => 'inline',  // inline | badge | dot
    'textClass' => '',
])

@php
$map = [
    'online'     => ['color' => 'badge-success',  'icon' => 'heroicon-o-check-circle',    'label' => __('Online')],
    'offline'    => ['color' => 'badge-neutral',   'icon' => 'heroicon-o-minus-circle',    'label' => __('Offline')],
    'pending'    => ['color' => 'badge-warning',   'icon' => 'heroicon-o-clock',           'label' => __('Pending')],
    'active'     => ['color' => 'badge-success',   'icon' => 'heroicon-o-check-circle',    'label' => __('Active')],
    'inactive'   => ['color' => 'badge-neutral',   'icon' => 'heroicon-o-minus-circle',    'label' => __('Inactive')],
    'processing' => ['color' => 'badge-info',      'icon' => 'heroicon-o-arrow-path',      'label' => __('Processing')],
    'success'    => ['color' => 'badge-success',   'icon' => 'heroicon-o-check-circle',    'label' => __('Success')],
    'error'      => ['color' => 'badge-error',     'icon' => 'heroicon-o-x-circle',        'label' => __('Error')],
];
$info      = $map[$status] ?? $map['pending'];
$colorClass = $info['color'];
$iconName   = $icon ?: $info['icon'];
$displayText = $text ?: $label ?: $info['label'];
$sizeClass  = match($size) {
    'xs' => 'badge-xs',
    'sm' => 'badge-sm',
    'lg' => 'badge-lg',
    'xl' => 'badge-xl',
    default => 'badge-md',
};
@endphp

@if($position === 'badge')
    <span {{ $attributes->merge(['class' => "badge {$colorClass} {$sizeClass} gap-1 " . ($pulse ? 'animate-pulse' : '')]) }}>
        <x-dynamic-component :component="$iconName" class="w-3 h-3" />
        {{ $displayText }}
    </span>
@elseif($position === 'dot')
    <div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
        <div class="w-2.5 h-2.5 rounded-full {{ $colorClass }} {{ $pulse ? 'animate-pulse' : '' }}"></div>
        <span class="text-sm {{ $textClass }}">{{ $displayText }}</span>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
        <span class="inline-block w-2.5 h-2.5 rounded-full {{ $colorClass }} {{ $pulse ? 'animate-pulse' : '' }}"></span>
        <span class="text-sm font-medium {{ $textClass }}">{{ $displayText }}</span>
    </div>
@endif
