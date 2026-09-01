<?php

use Edumicro\DaisyBlade\Support\Attrs;

it('renders attributes', function () {
    $html = (string) Attrs::render(['inputmode' => 'decimal', 'autocomplete' => 'off']);

    expect($html)->toBe('inputmode="decimal" autocomplete="off"');
});

it('renders a dashed name', function () {
    expect((string) Attrs::render(['aria-label' => 'Dosis']))->toBe('aria-label="Dosis"');
});

it('renders true as a valueless attribute and skips false and null', function () {
    $html = (string) Attrs::render(['readonly' => true, 'disabled' => false, 'title' => null]);

    expect($html)->toBe('readonly');
});

it('casts a number to its string form', function () {
    expect((string) Attrs::render(['maxlength' => 12]))->toBe('maxlength="12"');
});

it('renders nothing for an empty array', function () {
    expect((string) Attrs::render([]))->toBe('');
});

it('escapes a quote instead of letting it close the attribute', function () {
    $html = (string) Attrs::render(['title' => 'she said "hola"']);

    expect($html)
        ->toBe('title="she said &quot;hola&quot;"')
        ->not->toContain('="she said "');
});

it('refuses an event handler, which belongs in the events prop', function () {
    Attrs::render(['onclick' => 'evil()']);
})->throws(InvalidArgumentException::class, 'event handler');

it('refuses a name that would break out of the tag', function () {
    Attrs::render(['x onload=evil() y' => '1']);
})->throws(InvalidArgumentException::class, 'Invalid attribute name');
