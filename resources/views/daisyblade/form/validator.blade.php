@props([
    'field'   => '',
    'message' => '',
])

@if($message)
    <p {{ $attributes->merge(['class' => 'text-sm text-error mt-1']) }}>{{ $message }}</p>
@elseif($field && isset($errors))
    @error($field)
        <p {{ $attributes->merge(['class' => 'text-sm text-error mt-1']) }}>{{ $message }}</p>
    @enderror
@endif
