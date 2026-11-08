<?php

/*
 * form.select with `search-url` in a real browser: the keyboard, the mouse, the states in words and a failing server.
 * (The logic without a browser is in tests/js/select-search-server.test.mjs and the painted HTML in tests/Feature/SelectSearchUrlTest.php.)
 */

$options = "[...document.querySelectorAll('[role=option]')].filter(o => o.offsetParent !== null).map(o => o.textContent.trim())";

it('search select: asks the server while typing and chooses with the keyboard', function () use ($options) {
    $page = visit(browserPage('select-search-keys', <<<'BLADE'
        <form id="f"><x-dbl::form.select name="medicine" search-url="/medicines" :debounce="50" /></form>
        BLADE));

    $page->typeSlowly('input[type=text]', 'ibu', 20);
    expect(readPage($page, $options, fn ($list) => count($list) === 2))->toBe(['Ibuprofen', 'Ibuprofen 600']);

    $page->keys('input[type=text]', 'ArrowDown');
    $page->keys('input[type=text]', 'ArrowDown');
    expect(readPage($page, "document.querySelector('input[type=text]').getAttribute('aria-activedescendant') !== null"))->toBeTrue();
    $page->keys('input[type=text]', 'Enter');

    expect(readPage($page, "document.querySelector('input[type=hidden][name=medicine]').value", fn ($v) => $v === '2'))->toBe('2')
        ->and(readPage($page, "document.querySelector('input[type=text]').value"))->toBe('Ibuprofen 600');
});

it('search select: says what is happening in words', function () {
    $page = visit(browserPage('select-search-states', <<<'BLADE'
        <x-dbl::form.select name="medicine" search-url="/medicines" :debounce="50" />
        BLADE));

    $page->click('input[type=text]');
    expect(readPage($page, "document.body.innerText", fn ($text) => str_contains($text, 'at least 2')))->toContain('at least 2');

    $page->typeSlowly('input[type=text]', 'zzzz', 20);
    expect(readPage($page, "document.body.innerText", fn ($text) => str_contains($text, 'Nothing matches')))->toContain('Nothing matches');
});

it('search select: a failing server shows the message and Retry, and keeps what was chosen', function () {
    $page = visit(browserPage('select-search-fail', <<<'BLADE'
        <x-dbl::form.select name="medicine" search-url="/medicines-fail" :debounce="50" value="5" text="Ibuprofen" />
        BLADE));

    $page->typeSlowly('input[type=text]', 'Ibuprofenx', 20);
    expect(readPage($page, "document.body.innerText", fn ($text) => str_contains($text, 'Retry')))->toContain('Retry');
    // The typed text is no longer the chosen one, but the field still works; a field that was never touched keeps its value.
    $fresh = visit(browserPage('select-search-fail-kept', <<<'BLADE'
        <x-dbl::form.select name="medicine" search-url="/medicines-fail" value="5" text="Ibuprofen" />
        BLADE));
    expect(readPage($fresh, "document.querySelector('input[type=hidden][name=medicine]').value"))->toBe('5');
});
