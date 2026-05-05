@props([
    'key'     => '',
    'keys'    => [],
    'variant' => 'default',
    'size'    => 'md',
    'label'   => '',
    'class'   => '',
])

@php
$keys = is_array($keys) ? $keys : ($keys !== '' && $keys !== [] ? [$keys] : []);
$kbdClass = 'kbd' . match($variant) {
    'primary'   => ' kbd-primary',
    'secondary' => ' kbd-secondary',
    'accent'    => ' kbd-accent',
    default     => '',
} . ' ' . match($size) {
    'xs' => 'kbd-xs',
    'sm' => 'kbd-sm',
    'lg' => 'kbd-lg',
    default => '',
};
@endphp

<div {{ $attributes }}>
    @if($label)
        <span class="text-sm font-medium block mb-1">{{ $label }}</span>
    @endif
    <div class="flex flex-wrap gap-2 items-center">
        @if(!empty($keys))
            @foreach($keys as $k)
                <span class="{{ $kbdClass }} {{ $class }}">{{ $k }}</span>
                @if(!$loop->last)
                    <span class="text-base-content/50">+</span>
                @endif
            @endforeach
        @elseif($key)
            <span class="{{ $kbdClass }} {{ $class }}">{{ $key }}</span>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
