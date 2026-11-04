<?php

use Illuminate\Support\Facades\Blade;

/**
 * `form.textarea` with `max-length` printed "0/20" and nothing ever changed it: eleven characters typed, still "0/20".
 */
it('has a counter component that counts, not a fixed 0/N', function () {
    $html = Blade::render('<x-dbl::form.textarea name="bio" label="Bio" max-length="20" value="hello" />');

    expect($html)->toContain('x-data="dblCharacterCounter(')
        ->and($html)->toContain('x-text="typed"')
        ->and(xDataConfig($html, 'dblCharacterCounter')['start'])->toBe(5);
});

it('puts the counter BESIDE the textarea, never around it: a scope around it would hide the x-ref and the data of the caller', function () {
    $html = Blade::render('<x-dbl::form.textarea name="bio" label="Bio" max-length="20" x-ref="bio" />');

    // The element with the scope of the counter closes before the textarea opens.
    expect(strpos($html, '</span>', strpos($html, 'dblCharacterCounter')))->toBeLessThan(strpos($html, '<textarea'));
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
