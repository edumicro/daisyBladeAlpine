@props([
    'id'    => null,
    'title' => null,
    'size'  => 'md',   // sm | md | lg | xl | full
])

@php
$sizeClass = match($size) {
    'sm'   => 'max-w-sm',
    'lg'   => 'max-w-2xl',
    'xl'   => 'max-w-4xl',
    'full' => 'max-w-full',
    default => 'max-w-lg',
};
@endphp

<div
    @if($id) id="{{ $id }}" @endif
    x-data="dbModal()"
    @if($id)
        @open-modal.window="if ($event.detail.id === '{{ $id }}') show()"
        @close-modal.window="if ($event.detail.id === '{{ $id }}') hide()"
    @endif
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    @keydown.escape.window="hide()"
>
    {{-- Backdrop --}}
    <div
        class="fixed inset-0 bg-black/50"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="hide()"
    ></div>

    {{-- Panel --}}
    <div
        class="modal-box relative {{ $sizeClass }} w-full"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.stop
    >
        <button
            type="button"
            class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2"
            @click="hide()"
        >✕</button>

        @if($title)
            <h3 class="font-bold text-lg mb-4">{{ $title }}</h3>
        @endif

        {{ $slot }}
    </div>
</div>
