{{--
    The error of ONE field, painted in ONE place for every kind of field (text, textarea, select, checkbox, toggle, radio,
    upload, signature...), so that no type can forget it.

    @prop name         — the name of the field, as the server validates it (`email`, `address.city`)
    @prop mode         — 'form'   the page was rendered by the server: the message comes from Laravel's `$errors` bag
                         'alpine' the form is sent with JSON (auto-form, wizard): the message comes from the 422 answer, which the
                                  component keeps in an Alpine object with the shape { field: ['message', ...] }
    @prop message      — a message the caller already has: it wins over everything else
    @prop alpineErrors — in 'alpine' mode, the path of that object in the scope of the form (default `errors`)
--}}
@props([
    'name'         => '',
    'message'      => '',
    'mode'         => 'form',
    'alpineErrors' => 'errors',
])

@php
    // A view rendered without Laravel's web middleware (a console command, a mail, a test) has no `$errors`: it gets an empty bag.
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $classes = 'mt-1 text-xs text-error font-semibold';
@endphp

@if($message)
    <p {{ $attributes->merge(['class' => $classes, 'role' => 'alert']) }}>{{ $message }}</p>
@elseif($name && $mode === 'alpine')
    {{-- The directives go on the wrapper: the element that takes `$attributes` carries none of ours (and keeps its own x-show). --}}
    <div x-cloak x-show="({{ $alpineErrors }}[@js($name)] || []).length">
        <p {{ $attributes->merge(['class' => $classes, 'role' => 'alert']) }}><span x-text="({{ $alpineErrors }}[@js($name)] || [])[0]"></span></p>
    </div>
@elseif($name && $errors->has($name))
    <p {{ $attributes->merge(['class' => $classes, 'role' => 'alert']) }}>{{ $errors->first($name) }}</p>
@endif
