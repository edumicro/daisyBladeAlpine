@props([
    'position' => 'end',   // start | center | end
    'vertical' => 'top',   // top | middle | bottom
    'duration' => 4000,
])

<div
    x-data="dbToast()"
    @notify.window="add($event.detail.message, $event.detail.type ?? 'info', $event.detail.duration ?? {{ $duration }})"
    class="toast toast-{{ $vertical }} toast-{{ $position }} z-50 pointer-events-none"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="pointer-events-auto"
            :class="{
                'alert': true,
                'alert-success': toast.type === 'success',
                'alert-error':   toast.type === 'error',
                'alert-warning': toast.type === 'warning',
                'alert-info':    toast.type === 'info',
            }"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <span x-text="toast.message"></span>
            <button type="button" class="btn btn-ghost btn-xs" @click="remove(toast.id)">✕</button>
        </div>
    </template>
</div>
