@props([
    'title'           => '',
    'value'           => '0',
    'description'     => '',
    'icon'            => '',
    'unit'            => '',
    'trend'           => '',
    'trendValue'      => '',
    'backgroundColor' => 'bg-base-100',
    'shadow'          => true,
])

<div class="stat {{ $backgroundColor }} rounded-box {{ $shadow ? 'shadow-sm border border-base-200' : '' }}">
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
