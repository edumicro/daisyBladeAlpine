<?php

use Illuminate\Support\Facades\Blade;

/**
 * The listeners and bindings of `<x-dbl::form.select load-url="...">` live on its container, which is the Alpine scope,
 * and the <select> carries no Alpine attribute of its own. In 2.x this protected the `x-on:change` and `:disabled` a
 * view passed to the <select>: written twice in the same tag, the browser keeps only the first and the user's
 * vanished without any error. In 3.x everything the caller passes goes to the root (the container), where the
 * attributes of the component have other names (`x-on:change.capture`...), but the rule stays: each attribute once.
 */

it('keeps the x-on:change and :disabled a view passes to the remote select, each attribute written once, on the container', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" x-on:change="mine()" ::disabled="locked" />');

    preg_match('/<div\b[^>]*x-data="dblSelectRemote[^>]*>/s', $html, $match);
    $containerTag = $match[0];

    expect(substr_count($containerTag, ' x-on:change='))->toBe(1)
        ->and($containerTag)->toContain('x-on:change="mine()"')
        ->and($containerTag)->toContain('x-on:change.capture="remember()"')
        ->and(substr_count($containerTag, ':disabled='))->toBe(1)
        ->and($containerTag)->toContain(':disabled="locked"');
});

it('puts no Alpine binding of its own on the remote <select>', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" depends-on="country" />');

    preg_match('/<select\b[^>]*>/s', $html, $match);

    // Neither listeners (x-on:, @) nor bindings (:, x-bind:) nor effects: all of that is on the container.
    expect($match[0])->not->toMatch('/\s(x-on:|x-bind:|x-effect|@|:)[\w.-]*=/');
});

it('still reacts to its own change and to the control it depends on when those listeners live elsewhere', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" depends-on="country" />');

    // Wherever they are, the two listeners must exist exactly once in the component.
    expect(substr_count($html, 'remember()'))->toBe(1)
        ->and(substr_count($html, 'onDependencyChange($event)'))->toBe(1);
});

it('declares the listeners and the state binding on the container, which is the Alpine scope', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" depends-on="country" />');

    preg_match('/<div\b[^>]*x-data="dblSelectRemote[^>]*>/s', $html, $match);
    $containerTag = $match[0];

    expect($containerTag)->toContain('x-on:change.capture="remember()"')
        ->and($containerTag)->toContain('x-on:change.window="onDependencyChange($event)"')
        ->and($containerTag)->toContain('x-effect="syncField()"');
});

it('listens to the window only when the select depends on another control', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" />');

    expect($html)->toContain('x-on:change.capture="remember()"')
        ->and($html)->not->toContain('onDependencyChange');
});
