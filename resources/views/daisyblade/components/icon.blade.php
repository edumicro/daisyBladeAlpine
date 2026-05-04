@props(['name', 'variant' => 'o'])

<x-dynamic-component :component="'heroicon-' . $variant . '-' . $name" {{ $attributes }} />
