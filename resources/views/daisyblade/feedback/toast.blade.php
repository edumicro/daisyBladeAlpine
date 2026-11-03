@props([
    'message'   => '',
    'type'      => 'info',
    'autoClose' => false,
    'duration'  => 4000,
    'closable'  => true,
    'position'  => 'end',
    'vertical'  => 'top',
])

@php
// Class names written out in full, not glued together from a prefix and the value: Tailwind finds classes
// by scanning the source for complete names, so one built at run time never reaches the CSS.
// A value that is not listed gets the default look.
$positionClass = match($position) {
    'start' => 'toast-start',
    'center' => 'toast-center',
    'end' => 'toast-end',
    default => 'toast-end',
};
$verticalClass = match($vertical) {
    'top' => 'toast-top',
    'middle' => 'toast-middle',
    'bottom' => 'toast-bottom',
    default => 'toast-top',
};
$typeClass = match($type) {
    'success' => 'alert-success',
    'error' => 'alert-error',
    'warning' => 'alert-warning',
    'info' => 'alert-info',
    default => 'alert-info',
};
@endphp

@if($message)
    {{-- Static single toast --}}
    <div
        x-data="{
            visible: true,
            duration: {{ (int)$duration }},
            init() {
                if ({{ $autoClose ? 'true' : 'false' }}) {
                    setTimeout(() => this.visible = false, this.duration)
                }
            }
        }"
        x-show="visible"
        class="toast {{ $verticalClass }} {{ $positionClass }} z-50"
    >
        <div class="alert {{ $typeClass }}">
            <span>{{ $message }}</span>
            @if($closable)
                <button type="button" @click="visible = false" class="btn btn-ghost btn-xs">✕</button>
            @endif
        </div>
    </div>
@else
    {{-- Event-driven toast container --}}
    <div
        x-data="dbToast()"
        @notify.window="add($event.detail.message, $event.detail.type ?? 'info', $event.detail.duration ?? {{ $duration }})"
        class="toast {{ $verticalClass }} {{ $positionClass }} z-50 pointer-events-none"
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
@endif
