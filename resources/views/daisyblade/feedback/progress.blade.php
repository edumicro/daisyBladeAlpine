@props([
    'value'          => 0,
    'max'            => 100,
    'label'          => '',
    'showPercent'    => true,
    'showValue'      => false,
    'size'           => 'md',   // xs | sm | md | lg | xl
    'color'          => 'primary',
    'indeterminate'  => false,
    'progressClass'  => '',
])

@php
$sizeClass = match($size) {
    'xs' => 'progress-xs',
    'sm' => 'progress-sm',
    'lg' => 'progress-lg',
    'xl' => 'progress-xl',
    default => 'progress-md',
};
// Class names written out in full, not glued together from a prefix and the color: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$colorClass = match($color) {
    'neutral' => 'progress-neutral',
    'primary' => 'progress-primary',
    'secondary' => 'progress-secondary',
    'accent' => 'progress-accent',
    'info' => 'progress-info',
    'success' => 'progress-success',
    'warning' => 'progress-warning',
    'error' => 'progress-error',
    default => 'progress-primary',
};
$pct        = $indeterminate ? 0 : (int)(($value / max(1, $max)) * 100);
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
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
        class="progress {{ $sizeClass }} {{ $colorClass }} w-full {{ $progressClass }} {{ $indeterminate ? 'progress-indeterminate' : '' }}"
        value="{{ $indeterminate ? null : $value }}"
        max="{{ $max }}"
    ></progress>
</div>
