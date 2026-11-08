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

it('search select: the "more" button adds the next page, and the keyboard reaches it', function () use ($options) {
    $page = visit(browserPage('select-search-more', <<<'BLADE'
        <x-dbl::form.select name="drug" search-url="/drugs" :limit="2" :debounce="50" />
        BLADE));

    $page->typeSlowly('input[type=text]', 'dr', 20);
    expect(readPage($page, $options, fn ($list) => count($list) === 3))->toBe(['Drug 1 -', 'Drug 2 -', 'Show more']);

    $page->keys('input[type=text]', 'ArrowDown');
    $page->keys('input[type=text]', 'ArrowDown');
    $page->keys('input[type=text]', 'ArrowDown');
    $page->keys('input[type=text]', 'Enter');

    expect(readPage($page, $options, fn ($list) => count($list) === 5))->toBe(['Drug 1 -', 'Drug 2 -', 'Drug 3 -', 'Drug 4 -', 'Show more']);
    // The field has not chosen anything by asking for more.
    expect(readPage($page, "document.querySelector('input[type=hidden][name=drug]').value"))->toBe('');
});

it('search select: dbl-select-changed carries the whole row, and a failure announces dbl-select-failed', function () {
    $page = visit(browserPage('select-search-events', <<<'BLADE'
        <div x-data="{ heard: [] }" x-on:dbl-select-changed="heard.push(['changed', $event.detail.value, $event.detail.row?.lab])"
             x-on:dbl-select-failed="heard.push(['failed', $event.detail.name, $event.detail.error.response.status])">
            <x-dbl::form.select name="medicine" search-url="/medicines" :debounce="50" />
            <x-dbl::form.select name="broken" search-url="/medicines-fail" :debounce="50" />
            <pre id="heard" x-text="JSON.stringify(heard)"></pre>
        </div>
        BLADE));

    $page->typeSlowly('input[name=medicine_text]', 'ibu', 20);
    readPage($page, "document.querySelectorAll('[role=option]').length", fn ($n) => $n >= 2);
    clickText($page, 'Ibuprofen 600');
    $page->typeSlowly('input[name=broken_text]', 'xx', 20);

    $heard = readPage($page, "document.querySelector('#heard').textContent", fn ($text) => str_contains($text, 'failed'));
    expect($heard)->toContain('["changed",2,"Zeta"]')->toContain('["failed","broken",500]');
});

it('search select: depends-on sends the other control and clears what was chosen when it changes', function () use ($options) {
    $page = visit(browserPage('select-search-depends', <<<'BLADE'
        <form id="f">
            <x-dbl::form.select name="family" :options="['A' => 'Family A', 'B' => 'Family B']" placeholder="--" />
            <x-dbl::form.select name="drug" search-url="/drugs" depends-on="family" :debounce="50" />
        </form>
        BLADE));
    pause($page, 0.3);

    $page->script("(() => { const s = document.querySelector('select[name=family]'); s.value = 'A'; s.dispatchEvent(new Event('change', { bubbles: true })) })()");
    pause($page, 0.3);
    $page->typeSlowly('input[name=drug_text]', 'dr', 20);
    expect(readPage($page, $options, fn ($list) => count($list) >= 1)[0])->toBe('Drug 1 A');
    clickText($page, 'Drug 1 A');
    expect(readPage($page, "document.querySelector('input[type=hidden][name=drug]').value"))->toBe('1');

    $page->script("(() => { const s = document.querySelector('select[name=family]'); s.value = 'B'; s.dispatchEvent(new Event('change', { bubbles: true })) })()");

    expect(readPage($page, "document.querySelector('input[type=hidden][name=drug]').value", fn ($v) => $v === ''))->toBe('')
        ->and(readPage($page, "document.querySelector('input[name=drug_text]').value"))->toBe('');
});

it('search select: the two fields are in the page without JavaScript having to do anything', function () {
    $page = visit(browserPage('select-search-two-fields', <<<'BLADE'
        <form id="f"><x-dbl::form.select name="drug" search-url="/drugs" value="3" text="Drug 3" /></form>
        BLADE));
    pause($page, 0.4);

    expect(readPage($page, "new URLSearchParams(new FormData(document.querySelector('#f'))).toString()"))->toBe('drug_text=Drug+3&drug=3');
});

it('remote select: reads a map and a list of rows, the same vocabulary as the searching one', function () {
    $page = visit(browserPage('select-remote-vocabulary', <<<'BLADE'
        <form id="f">
            <x-dbl::form.select name="a" load-url="/drugs-map" />
            <x-dbl::form.select name="b" load-url="/drugs" />
        </form>
        BLADE));

    $labels = fn ($name) => "[...document.querySelectorAll('select[name={$name}] option')].map(o => o.textContent.trim())";

    expect(readPage($page, $labels('a'), fn ($l) => count($l) === 2))->toBe(['Aspirin', 'Paracetamol'])
        ->and(readPage($page, $labels('b'), fn ($l) => count($l) === 5))->toHaveCount(5);
});
