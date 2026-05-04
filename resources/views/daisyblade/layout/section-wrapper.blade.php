@props([
    'title'       => '',
    'description' => '',
    'class'       => '',
])

<div class="mb-6 {{ $class }}">
    @if($title)
        <div class="mb-3">
            <h3 class="text-base font-semibold text-base-content">{{ $title }}</h3>
            @if($description)
                <p class="text-sm text-base-content/60 mt-0.5">{{ $description }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
