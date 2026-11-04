@props([
    'brand' => null,
])

{{-- The root is the caller's element; the scope of the component is the element inside (`contents`: it takes no room of its own). --}}
<div {{ $attributes->merge(['class' => 'navbar bg-base-100 shadow-sm']) }}>
    <div x-data="dblNavbar()" class="contents">

        <div class="navbar-start">
            @isset($start)
                {{ $start }}
            @elseif($brand)
                <a href="{{ url('/') }}" class="btn btn-ghost text-xl font-bold">{{ $brand }}</a>
            @endisset
        </div>

        @isset($center)
            <div class="navbar-center hidden lg:flex">{{ $center }}</div>
        @endisset

        <div class="navbar-end gap-2">
            @isset($end)
                {{ $end }}
            @endisset

            @isset($mobile)
                <button
                    type="button"
                    class="btn btn-ghost lg:hidden"
                    @click="toggle()"
                    aria-label="{{ __('Menu') }}"
                    :aria-expanded="open"
                >
                    <x-heroicon-o-bars-3 class="w-6 h-6" />
                </button>
            @endisset
        </div>

        @isset($mobile)
            <div
                x-show="open"
                x-cloak
                @click.outside="close()"
                x-transition
                class="absolute top-16 left-0 right-0 z-30 bg-base-100 shadow-lg border-t border-base-200"
            >
                {{ $mobile }}
            </div>
        @endisset

    </div>
</div>
