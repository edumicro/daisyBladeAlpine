@props([
    'image'       => '',
    'imageAlt'    => 'Card',
    'title'       => '',
    'description' => '',
    'tags'        => [],
    'icon'        => '',
    'shadow'      => true,
    'class'       => '',
])

<div {{ $attributes->merge(['class' => 'group relative']) }} style="perspective:1000px">
    <div
        class="relative w-full h-96 rounded-xl overflow-hidden {{ $shadow ? 'shadow-lg' : '' }} transition-all duration-500"
        style="transform-style:preserve-3d;transition:transform .6s cubic-bezier(.23,1,.32,1)"
        onmousemove="const r=this.getBoundingClientRect();this.style.transform=`rotateX(${((event.clientY-r.top)/r.height-.5)*20}deg) rotateY(${((event.clientX-r.left)/r.width-.5)*-20}deg) scale(1.02)`"
        onmouseleave="this.style.transform='rotateX(0) rotateY(0) scale(1)'"
    >
        @if($image)
            <img src="{{ $image }}" alt="{{ $imageAlt }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" />
        @else
            <div class="w-full h-full bg-base-200 flex items-center justify-center">
                @if($icon)
                    <x-dynamic-component :component="$icon" class="w-24 h-24 text-base-content/20" />
                @endif
            </div>
        @endif

        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

        <div class="absolute inset-0 flex flex-col justify-end p-6 translate-y-10 group-hover:translate-y-0 transition-transform duration-500 {{ $class }}">
            @if($title)
                <h3 class="text-xl font-bold text-white mb-2">{{ $title }}</h3>
            @endif
            @if($description)
                <p class="text-white/90 text-sm mb-4 line-clamp-2">{{ $description }}</p>
            @endif
            @if(!empty($tags))
                <div class="flex gap-2 flex-wrap">
                    @foreach($tags as $tag)
                        <span class="badge badge-sm badge-primary/80">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
            {{ $slot }}
        </div>
    </div>
</div>
