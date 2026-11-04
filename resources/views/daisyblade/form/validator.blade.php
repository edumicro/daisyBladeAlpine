@props([
    'field'   => '',
    'message' => '',
])

{{-- The message of a field is painted in one place for every kind of field (form/field-error.blade.php). --}}
<x-dbl::form.field-error :name="$field" :message="$message" {{ $attributes }} />
