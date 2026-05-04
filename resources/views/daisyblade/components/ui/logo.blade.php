@props(['href' => '/'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'font-extrabold tracking-tight']) }}>
    {{ $slot->isEmpty() ? config('app.name') : $slot }}
</a>
