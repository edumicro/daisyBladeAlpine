@props([
    'value'          => 0,
    'max'            => 100,
    'label'          => '',
    'showPercent'    => true,
    'showValue'      => false,
    'size'           => 'md',
    'color'          => 'primary',
    'indeterminate'  => false,
    'class'          => '',
    'containerClass' => '',
])

@php
$sizeClass  = match($size) { 'xs'=>'progress-xs','sm'=>'progress-sm','lg'=>'progress-lg', default=>'progress-md' };
$colorClass = "progress-{$color}";
$pct        = $indeterminate ? 0 : (int)(($value / max(1, $max)) * 100);
@endphp

<div class="w-full {{ $containerClass }}">
    @if($label)
        <div class="flex justify-between items-center mb-2">
            <label class="text-sm font-medium">{{ $label }}</label>
            <div class="text-sm text-base-content/70">
                @if($showPercent) <span class="font-semibold">{{ $pct }}%</span> @endif
                @if($showValue) <span class="text-xs">{{ $value }}/{{ $max }}</span> @endif
            </div>
        </div>
    @endif

    <progress
        class="progress {{ $sizeClass }} {{ $colorClass }} w-full {{ $class }} {{ $indeterminate ? 'progress-indeterminate' : '' }}"
        value="{{ $indeterminate ? null : $value }}"
        max="{{ $max }}"
    ></progress>
</div>
