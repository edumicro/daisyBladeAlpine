<?php

use Illuminate\Support\Facades\Blade;

/**
 * The <select> of `<x-dbl::form.select load-url="...">` is the main element of the component, so it receives what the
 * caller writes (`x-on:change`, `:disabled`...) and carries no Alpine attribute of its own: if the template wrote the same
 * attribute on the <select> as well, the HTML would have it twice, and the browser keeps only the FIRST one, so the caller's
 * would vanish without any error.
 *
 * What Alpine needs from the component (the scope, the listeners and the state binding) is written on another element, the
 * one that holds the error message, next to the <select> and not around it (see SelectRemoteScopeTest).
 */

/** The opening tag of the element that carries the x-data of the remote select. */
function remoteSelectScopeTag(string $html): string
{
    preg_match('/<div\b[^>]*x-data="dblSelectRemote[^>]*>/s', $html, $match);

    return $match[0] ?? '';
}

it('keeps the x-on:change and :disabled a view passes to the remote select, each attribute written once, on the <select>', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" x-on:change="mine()" ::disabled="locked" />');

    preg_match('/<select\b[^>]*>/s', $html, $match);
    $selectTag = $match[0];

    expect(substr_count($selectTag, 'x-on:change='))->toBe(1)
        ->and($selectTag)->toContain('x-on:change="mine()"')
        ->and(substr_count($selectTag, ':disabled='))->toBe(1)
        ->and($selectTag)->toContain(':disabled="locked"');

    // And the component keeps its own listener, on its own element, which is another element.
    expect(remoteSelectScopeTag($html))->toContain('x-on:change.window.capture="onChange($event)"')->not->toContain('mine()');
});

it('puts no Alpine binding of its own on the remote <select>', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" depends-on="country" />');

    preg_match('/<select\b[^>]*>/s', $html, $match);

    // Neither listeners (x-on:, @) nor bindings (:, x-bind:) nor effects: all of that is on the element with the scope.
    expect($match[0])->not->toMatch('/\s(x-on:|x-bind:|x-effect|@|:)[\w.-]*=/');
});

it('reacts to its own change and to the control it depends on with one listener, written once', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" depends-on="country" />');

    expect(substr_count($html, 'onChange($event)'))->toBe(1);
});

it('declares the listener and the state binding on the element with the scope', function () {
    $html = Blade::render('<x-dbl::form.select name="city" load-url="/cities" />');

    // `capture`: it runs before any listener of the user, which may stop the event on its way up.
    expect(remoteSelectScopeTag($html))->toContain('x-on:change.window.capture="onChange($event)"')
        ->and(remoteSelectScopeTag($html))->toContain('x-effect="syncField()"');
});

it('listens to the window only in the remote mode', function () {
    $native = Blade::render('<x-dbl::form.select name="city" :options="[\'a\' => \'A\']" depends-on="country" />');
    $searchable = Blade::render('<x-dbl::form.select name="city" searchable :options="[\'a\' => \'A\']" />');

    expect($native)->not->toContain('onChange')->not->toContain('.window')
        ->and($searchable)->not->toContain('onChange')->not->toContain('.window');
});
