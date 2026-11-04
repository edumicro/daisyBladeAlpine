@props([
    'key'     => '',
    'keys'    => [],
    'color'   => 'neutral',   // neutral | primary | secondary | accent
    'size'    => 'md',   // xs | sm | md | lg | xl
    'label'   => '',
    'kbdClass' => '',
])

@php
$keys = is_array($keys) ? $keys : ($keys !== '' && $keys !== [] ? [$keys] : []);
$kbdClasses = 'kbd' . match($color) {
    'primary'   => ' kbd-primary',
    'secondary' => ' kbd-secondary',
    'accent'    => ' kbd-accent',
    default     => '',
} . ' ' . match($size) {
    'xs' => 'kbd-xs',
    'sm' => 'kbd-sm',
    'lg' => 'kbd-lg',
    'xl' => 'kbd-xl',
    default => 'kbd-md',
};
@endphp

<div {{ $attributes }}>
    @if($label)
        <span class="text-sm font-medium block mb-1">{{ $label }}</span>
    @endif
    <div class="flex flex-wrap gap-2 items-center">
        @if(!empty($keys))
            @foreach($keys as $k)
                <span class="{{ $kbdClasses }} {{ $kbdClass }}">{{ $k }}</span>
                @if(!$loop->last)
                    <span class="text-base-content/50">+</span>
                @endif
            @endforeach
        @elseif($key)
            <span class="{{ $kbdClasses }} {{ $kbdClass }}">{{ $key }}</span>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
