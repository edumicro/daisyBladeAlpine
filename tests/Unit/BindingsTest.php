<?php

use Edumicro\DaisyBlade\Support\Bindings;

// ── notation ──────────────────────────────────────────────────────────────────

it('renders a nested map as an x-on attribute', function () {
    $html = (string) Bindings::render(['keydown' => ['enter' => ['prevent' => 'add()']]]);

    expect($html)->toBe('x-on:keydown.enter.prevent="add()"');
});

it('accepts dotted keys as the same thing', function () {
    $nested = (string) Bindings::render(['keydown' => ['enter' => ['prevent' => 'add()']]]);
    $dotted = (string) Bindings::render(['keydown.enter.prevent' => 'add()']);

    expect($dotted)->toBe($nested);
});

it('accepts the two notations mixed in one map', function () {
    $html = (string) Bindings::render(['keydown' => ['enter.prevent' => 'add()']]);

    expect($html)->toBe('x-on:keydown.enter.prevent="add()"');
});

it('renders several bindings on the same event', function () {
    $html = (string) Bindings::render([
        'keydown' => ['enter' => 'add()', 'escape' => 'reset()'],
    ]);

    expect($html)
        ->toContain('x-on:keydown.enter="add()"')
        ->toContain('x-on:keydown.escape="reset()"');
});

it('knows nothing about which modifiers exist', function () {
    $html = (string) Bindings::render(['scroll' => ['window' => ['debounce' => ['500ms' => 'go()']]]]);

    expect($html)->toBe('x-on:scroll.window.debounce.500ms="go()"');
});

it('renders nothing for an empty map', function () {
    expect((string) Bindings::render([]))->toBe('');
});

// ── merging: the reason the tree shape exists ─────────────────────────────────

it('keeps defaults the consumer did not mention', function () {
    $html = (string) Bindings::render(
        ['input' => 'notify()', 'keydown' => ['escape' => 'reset()']],
        ['keydown' => ['enter' => 'add()']],
    );

    expect($html)
        ->toContain('x-on:input="notify()"')
        ->toContain('x-on:keydown.escape="reset()"')
        ->toContain('x-on:keydown.enter="add()"');
});

it('lets a consumer leaf replace a default branch instead of piling onto it', function () {
    $html = (string) Bindings::render(
        ['keydown' => ['enter' => ['prevent' => 'add()']]],
        ['keydown' => ['enter' => 'mine()']],
    );

    // The whole point: one binding, the consumer's. Flat dotted keys would leave both,
    // and both would fire.
    expect($html)->toBe('x-on:keydown.enter="mine()"');
});

it('applies override semantics across notations too', function () {
    $html = (string) Bindings::render(
        ['keydown.enter.prevent' => 'add()'],
        ['keydown.enter' => 'mine()'],
    );

    expect($html)->toBe('x-on:keydown.enter="mine()"');
});

it('lets a consumer silence a default', function () {
    $html = (string) Bindings::render(['input' => 'notify()'], ['input' => '']);

    expect($html)->toBe('x-on:input=""');
});

it('lets a consumer compose with the component behaviour', function () {
    $html = (string) Bindings::render(['click' => 'add()'], ['click' => 'add(); mine()']);

    expect($html)->toBe('x-on:click="add(); mine()"');
});

// ── refusals: things that would fail silently ─────────────────────────────────

it('refuses a handler and modifiers on the same node', function () {
    Bindings::render(['keydown' => 'a()', 'keydown.enter' => 'b()']);
})->throws(InvalidArgumentException::class, 'declared twice');

it('refuses the same binding declared twice', function () {
    Bindings::render(['keydown' => ['enter' => 'a()'], 'keydown.enter' => 'b()']);
})->throws(InvalidArgumentException::class, 'declared twice');

it('refuses a PHP closure, which would never reach the browser', function () {
    Bindings::render(['click' => fn () => 'add()']);
})->throws(InvalidArgumentException::class, 'expression string');

it('refuses an event name that would break out of the attribute', function () {
    Bindings::render(['click" onload="evil()' => 'add()']);
})->throws(InvalidArgumentException::class, 'Invalid event segment');

it('refuses an empty modifier', function () {
    Bindings::render(['keydown..enter' => 'add()']);
})->throws(InvalidArgumentException::class, 'Invalid event segment');

// ── escaping ──────────────────────────────────────────────────────────────────

it('escapes quotes in the expression so it cannot close the attribute', function () {
    $html = (string) Bindings::render(['click' => '$dispatch("changed", { rows })']);

    expect($html)
        ->toBe('x-on:click="$dispatch(&quot;changed&quot;, { rows })"')
        ->not->toContain('="$dispatch("');
});

it('does not renumber a segment made only of digits', function () {
    $html = (string) Bindings::render(['keydown' => ['throttle' => ['500' => 'go()']]]);

    expect($html)->toBe('x-on:keydown.throttle.500="go()"');
});
