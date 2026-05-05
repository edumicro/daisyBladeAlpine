{{--
    Tipo 3: loads a resource via Axios and renders key-value details.

    @prop loadUrl  — GET endpoint returning { data: { ... } } or plain object
    @prop label    — optional section heading
    @prop fields   — optional field whitelist [ 'name', 'email', ... ] (all if empty)
--}}
@props([
    'loadUrl'        => '',
    'label'          => '',
    'fields'         => [],
    'class'          => '',
    'containerClass' => '',
])

<div
    x-data="dbResourceDetails({ loadUrl: '{{ $loadUrl }}', fields: @js($fields) })"
    x-init="init()"
    {{ $attributes->merge(['class' => $containerClass]) }}
>
    @if($label)
        <h3 class="text-lg font-semibold mb-4">{{ $label }}</h3>
    @endif

    {{-- Loading --}}
    <div x-show="loading" class="flex justify-center py-8">
        <div class="loading loading-spinner loading-lg"></div>
    </div>

    {{-- Error --}}
    <div x-show="error && !loading" x-cloak class="alert alert-error">
        <x-heroicon-o-x-circle class="w-5 h-5" />
        <span x-text="error"></span>
        <button type="button" @click="load()" class="btn btn-ghost btn-xs">{{ __('Retry') }}</button>
    </div>

    {{-- Data --}}
    <div x-show="resource && !loading" x-cloak class="{{ $class }}">
        @if($slot->isNotEmpty())
            {{ $slot }}
        @else
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-0 divide-y divide-base-200">
                <template x-for="[key, value] in Object.entries(displayData)" :key="key">
                    <div class="py-3">
                        <dt class="text-xs font-medium text-base-content/50 uppercase tracking-wide" x-text="key.replace(/_/g, ' ')"></dt>
                        <dd class="mt-0.5 text-sm text-base-content" x-text="value ?? '—'"></dd>
                    </div>
                </template>
            </dl>
        @endif
    </div>
</div>
