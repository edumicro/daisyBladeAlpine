<?php

use Illuminate\Support\Facades\Blade;

/**
 * `form.textarea` with `max-length` printed "0/20" and nothing ever changed it: eleven characters typed, still "0/20".
 */
it('has a counter that listens to what is typed, not a fixed 0/N', function () {
    $html = Blade::render('<x-dbl::form.textarea name="bio" label="Bio" max-length="20" value="hello" />');

    expect($html)->toContain('x-on:input="dbTypedCharacters = $event.target.value.length"')
        ->and($html)->toContain('x-text="dbTypedCharacters"');
});

it('starts the counter at the characters the value already has', function () {
    $html = Blade::render('<x-dbl::form.textarea name="bio" label="Bio" max-length="20" value="hello" />');

    expect($html)->toContain('dbTypedCharacters: 5');
});

it('leaves the x-on:input of the caller on the textarea alone', function () {
    $html = Blade::render('<x-dbl::form.textarea name="bio" max-length="20" x-on:input="mine()" />');

    // One handler on the textarea (the caller's) and the counter's on the container: two different elements.
    preg_match('/<textarea[^>]*>/s', $html, $textarea);
    expect($textarea[0])->toContain('x-on:input="mine()"');
});

it('writes no Alpine at all without max-length', function () {
    $html = Blade::render('<x-dbl::form.textarea name="bio" label="Bio" />');

    expect($html)->not->toContain('x-data');
});
