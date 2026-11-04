@props(['href' => '/'])

<a {{ $attributes->merge(['href' => $href, 'class' => 'font-extrabold tracking-tight']) }}>
    {{ $slot->isEmpty() ? config('app.name') : $slot }}
</a>
