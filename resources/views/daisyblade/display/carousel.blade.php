@props([
    'items'          => [],
    'showControls'   => true,
    'showIndicators' => true,
    'class'          => '',
    'containerClass' => '',
])

<div x-data="{ active: 0, total: {{ count($items) }} }">
    <div class="carousel carousel-center max-w-4xl mx-auto rounded-box bg-neutral p-4 {{ $containerClass }}">
        @forelse($items as $index => $item)
            <div
                class="carousel-item w-full relative {{ $class }}"
                x-show="active === {{ $index }}"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
            >
                @if(isset($item['image']))
                    <img src="{{ $item['image'] }}" alt="{{ $item['alt'] ?? 'Slide ' . ($index+1) }}" class="w-full h-96 object-cover rounded-lg" />
                @elseif(isset($item['content']))
                    <div class="w-full h-96 flex items-center justify-center bg-base-200 rounded-lg">
                        <div class="text-center">{!! $item['content'] !!}</div>
                    </div>
                @endif

                @if(isset($item['title']))
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4 rounded-b-lg">
                        <h3 class="text-white font-bold">{{ $item['title'] }}</h3>
                        @if(isset($item['description']))
                            <p class="text-white/80 text-sm">{{ $item['description'] }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="w-full h-96 flex items-center justify-center bg-base-200 rounded-lg">
                <span class="text-base-content/50">{{ __('No items') }}</span>
            </div>
        @endforelse
    </div>

    @if($showControls && !empty($items))
        <div class="flex justify-center gap-2 mt-4">
            <button type="button" @click="active = (active - 1 + total) % total" class="btn btn-circle btn-sm btn-outline">❮</button>

            @if($showIndicators)
                @foreach($items as $index => $item)
                    <button
                        type="button"
                        @click="active = {{ $index }}"
                        :class="active === {{ $index }} ? 'btn-primary' : 'btn-outline'"
                        class="btn btn-xs"
                    >{{ $index + 1 }}</button>
                @endforeach
            @endif

            <button type="button" @click="active = (active + 1) % total" class="btn btn-circle btn-sm btn-outline">❯</button>
        </div>
    @endif
</div>
