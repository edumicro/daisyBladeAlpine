@props([
    'title'           => '',
    'value'           => '0',
    'description'     => '',
    'icon'            => '',
    'unit'            => '',
    'trend'           => '',
    'trendValue'      => '',
    'background'      => 'base-100',   // base-100 | base-200 | base-300 | primary | secondary | accent | neutral | info | success | warning | error
    'shadow'          => true,
])

@php
// The background is a token; the class names are written out in full so Tailwind can find them.
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
@endphp

<div class="stat {{ $backgroundClass }} rounded-box {{ $shadow ? 'shadow-sm border border-base-200' : '' }}">
    @if($icon)
        <div class="stat-figure text-primary">
            <x-dynamic-component :component="$icon" class="w-8 h-8" />
        </div>
    @endif

    <div class="stat-title">{{ $title }}</div>

    <div class="stat-value text-primary">
        {{ $value }}
        @if($unit)
            <span class="text-sm font-normal">{{ $unit }}</span>
        @endif
    </div>

    @if($description || $trend)
        <div class="stat-desc mt-1">
            @if($trend)
                <span @class(['inline-flex items-center gap-1', 'text-success' => $trend === 'up', 'text-error' => $trend !== 'up'])>
                    <x-dbl::components.icon :name="$trend === 'up' ? 'arrow-trending-up' : 'arrow-trending-down'" class="w-4 h-4" />
                    {{ $trendValue }}
                </span>
            @endif
            @if($description)
                <span class="ml-1">{{ $description }}</span>
            @endif
        </div>
    @endif
</div>
