@props([
    'message'        => '',
    'type'           => 'info',
    'title'          => '',
    'icon'           => '',
    'closable'       => true,
    'class'          => '',
    'containerClass' => '',
])

@php
$alertClass = match($type) { 'success'=>'alert-success','warning'=>'alert-warning','error'=>'alert-error', default=>'alert-info' };
$defaultIcon = $icon ?: match($type) { 'success'=>'heroicon-o-check-circle','warning'=>'heroicon-o-exclamation-triangle','error'=>'heroicon-o-x-circle', default=>'heroicon-o-information-circle' };
@endphp

<div
    x-data="{ open: true }"
    x-show="open"
    class="alert {{ $alertClass }} {{ $containerClass }}"
>
    <div class="flex items-start gap-3 w-full {{ $class }}">
        <x-dynamic-component :component="$defaultIcon" class="w-6 h-6 flex-shrink-0" />

        <div class="flex-1">
            @if($title)
                <h3 class="font-bold">{{ $title }}</h3>
            @endif
            <div class="text-sm">{{ $message ?: $slot }}</div>
        </div>

        @if($closable)
            <button type="button" @click="open = false" class="btn btn-ghost btn-sm btn-circle">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        @endif
    </div>
</div>
