@props([
    'collapsible' => true,
    'class'       => '',
])

<aside
    x-data="dbSidebar()"
    x-init="init()"
    :class="open ? 'w-64' : ({{ $collapsible ? 'true' : 'false' }} ? 'w-16' : 'w-64')"
    class="min-h-screen bg-base-200 transition-all duration-300 flex flex-col overflow-hidden {{ $class }}"
>
    @if($collapsible)
        <div class="flex justify-end p-2">
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-square"
                @click="toggle()"
                :title="open ? '{{ __('Collapse') }}' : '{{ __('Expand') }}'"
            >
                <x-heroicon-o-bars-3 class="w-5 h-5" />
            </button>
        </div>
    @endif

    <div class="flex-1 overflow-y-auto overflow-x-hidden">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="p-2 border-t border-base-300">{{ $footer }}</div>
    @endisset
</aside>
