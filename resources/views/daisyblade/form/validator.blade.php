@props([
    'field'   => '',
    'message' => '',
    'class'   => '',
])

@if($message)
    <p class="text-sm text-error mt-1 {{ $class }}">{{ $message }}</p>
@elseif($field && isset($errors))
    @error($field)
        <p class="text-sm text-error mt-1 {{ $class }}">{{ $message }}</p>
    @enderror
@endif
