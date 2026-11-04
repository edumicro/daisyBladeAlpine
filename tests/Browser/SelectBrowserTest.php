<?php

/*
 * form.select in a real browser: remote, searchable and native, all against a v-model of the user.
 * Findings: fable-daisy3 XM1, XM2, SH, SI, SI2, NS · ronda 1 SSX, SSY, SSZ, SSE · ronda 2 SS4, SSK (RS1 is the same walk as XM2).
 */

// ── Remote select (load-url) ────────────────────────────────────────────────

it('remote select: shows the model value once the options arrive (x-model with a value)', function () {
    $page = visit(browserPage('select-remote-model', <<<'BLADE'
        <div id="scope" x-data="{ form: { city: '2' } }"><form>
            <x-dbl::form.select name="city" load-url="/options" placeholder="--" x-model="form.city" />
        </form></div>
        BLADE));

    $shown = readPage($page, "document.querySelector('select[name=city]').value", fn ($v) => $v === '2');

    expect($shown)->toBe('2');
});

it('remote select: with value="2" and an empty model, the select shows 2 and the model receives it', function () {
    $page = visit(browserPage('select-remote-value', <<<'BLADE'
        <div id="scope" x-data="{ form: { city: '' } }"><form>
            <x-dbl::form.select name="city" load-url="/options" placeholder="--" value="2" x-model="form.city" />
        </form></div>
        BLADE));

    $shown = readPage($page, "document.querySelector('select[name=city]').value", fn ($v) => $v === '2');
    $model = $page->script(alpineData('#scope', 'form.city'));

    expect($shown)->toBe('2')->and($model)->toBe('2');
});

it('remote select: the user ::disabled and ::data-err read THEIR scope, not the names the factory also uses', function () {
    $page = visit(browserPage('select-shared-names', <<<'BLADE'
        <div x-data="{ loading: true, error: 'outer', options: [9] }">
            <x-dbl::form.select name="city" load-url="/options" ::disabled="loading" ::data-err="error" ::data-n="options.length" />
        </div>
        BLADE));

    pause($page);

    expect($page->script("document.querySelector('select').disabled"))->toBeTrue()
        ->and($page->script("document.querySelector('select').getAttribute('data-err')"))->toBe('outer');
});

it('remote select: a saved value that the options do not offer is kept, not erased and not replaced by the first option', function () {
    $page = visit(browserPage('select-remote-unknown', <<<'BLADE'
        <div id="scope" x-data="{ form: { city: '9' } }"><form>
            <x-dbl::form.select name="city" load-url="/options" x-model="form.city" :options="['9' => 'Nine']" value="9" />
        </form></div>
        BLADE));

    readPage($page, "document.querySelectorAll('select[name=city] option').length", fn ($v) => $v >= 3);

    expect($page->script("document.querySelector('select[name=city]').value"))->toBe('9')
        ->and($page->script("document.querySelector('select[name=city] option:checked').textContent"))->toBe('Nine')
        ->and($page->script(alpineData('#scope', 'form.city')))->toBe('9');
});

// ── Searchable select: the user's own listeners ────────────────────────────

it('searchable select: the user @input and @focus are called (@ spelling)', function () {
    $page = visit(browserPage('select-search-at', <<<'BLADE'
        <x-dbl::form.select name="city" searchable :options="['1' => 'One', '2' => 'Two']"
            @input="window.typed = (window.typed ?? 0) + 1" @focus="window.focused = (window.focused ?? 0) + 1" />
        BLADE));

    $page->typeSlowly('input[type=text]', 'On', 20);

    expect($page->script('window.focused ?? 0'))->toBe(1)
        ->and($page->script('window.typed ?? 0'))->toBe(2);
});

it('searchable select: the user x-on:input and x-on:focus are called (x-on: spelling)', function () {
    $page = visit(browserPage('select-search-xon', <<<'BLADE'
        <x-dbl::form.select name="city" searchable :options="['1' => 'One', '2' => 'Two']"
            x-on:input="window.typed = (window.typed ?? 0) + 1" x-on:focus="window.focused = (window.focused ?? 0) + 1" />
        BLADE));

    $page->typeSlowly('input[type=text]', 'On', 20);

    expect($page->script('window.focused ?? 0'))->toBe(1)
        ->and($page->script('window.typed ?? 0'))->toBe(2);
});

it('searchable select: an @input on the surrounding <form> hears what is typed and chosen', function () {
    $page = visit(browserPage('select-search-form-input', <<<'BLADE'
        <form id="f" x-data @input="window.formInputs = (window.formInputs || 0) + 1">
            <x-dbl::form.select name="c" searchable :options="['1' => 'One', '2' => 'Two']" />
        </form>
        BLADE));

    $page->typeSlowly('input[type=text]', 'On', 20);
    clickText($page, 'One');

    expect($page->script('window.formInputs ?? 0'))->toBeGreaterThanOrEqual(2);
});

it('searchable select: Escape closes the list', function () {
    $page = visit(browserPage('select-search-escape', <<<'BLADE'
        <form id="f" x-data><x-dbl::form.select name="c" searchable :options="['1' => 'One', '2' => 'Two']" /></form>
        BLADE));
    $listIsOpen = "[...document.querySelectorAll('button')].some(b => b.offsetParent !== null && b.textContent.trim() === 'One')";

    $page->click('input[type=text]');
    expect(readPage($page, $listIsOpen))->toBeTrue();

    $page->keys('input[type=text]', 'Escape');

    // Alpine hides the list a moment after the key: wait for it instead of reading at once.
    expect(readPage($page, $listIsOpen, fn ($open) => $open === false))->toBeFalse();
});

// ── Searchable select: the model ───────────────────────────────────────────

it('searchable select: the model is empty while typing and gets the KEY when an option is chosen', function () {
    $page = visit(browserPage('select-search-key', <<<'BLADE'
        <div id="scope" x-data="{ form: { c: '' } }">
            <x-dbl::form.select name="c" searchable :options="['1' => 'One', '2' => 'Two']" x-model="form.c" />
        </div>
        BLADE));

    $page->typeSlowly('input[type=text]', 'On', 20);
    $whileTyping = $page->script(alpineData('#scope', 'form.c'));
    clickText($page, 'One');

    expect($whileTyping)->toBe('')
        ->and($page->script(alpineData('#scope', 'form.c')))->toBe('1')
        ->and($page->script("document.querySelector('input[type=text]').value"))->toBe('One')
        ->and($page->script("document.querySelector('input[type=hidden]').value"))->toBe('1');
});

it('searchable select: erasing the box after choosing empties the hidden input and the model', function () {
    $page = visit(browserPage('select-search-erase', <<<'BLADE'
        <div id="scope" x-data="{ form: { c: '' } }">
            <x-dbl::form.select name="c" searchable :options="['1' => 'One', '2' => 'Two']" x-model="form.c" />
        </div>
        BLADE));

    $page->typeSlowly('input[type=text]', 'On', 20);
    clickText($page, 'One');
    $page->keys('input[type=text]', ['End', 'Backspace', 'Backspace', 'Backspace']);

    expect($page->script("document.querySelector('input[type=text]').value"))->toBe('')
        ->and($page->script("document.querySelector('input[type=hidden]').value"))->toBe('')
        ->and($page->script(alpineData('#scope', 'form.c')))->toBe('');
});

it('searchable select: a model changed from outside shows in the box (2 -> Two, 1 -> One, empty -> empty)', function () {
    $page = visit(browserPage('select-search-outside', <<<'BLADE'
        <div id="scope" x-data="{ form: { c: '2' } }">
            <x-dbl::form.select name="c" searchable :options="['1' => 'One', '2' => 'Two']" x-model="form.c" placeholder="Pick" />
            <button id="set1" type="button" @click="form.c = '1'">set1</button>
            <button id="reset" type="button" @click="form.c = ''">reset</button>
        </div>
        BLADE));
    $box = "({ box: document.querySelector('input[type=text]').value, hidden: document.querySelector('input[type=hidden]').value })";

    $start = $page->script($box);
    $placeholder = $page->script("document.querySelector('input[type=text]').placeholder");
    $page->click('#set1');
    $afterSet = readPage($page, $box, fn ($v) => $v['box'] === 'One');
    $page->click('#reset');
    $afterReset = readPage($page, $box, fn ($v) => $v['box'] === '');

    expect($start)->toBe(['box' => 'Two', 'hidden' => '2'])
        ->and($placeholder)->toBe('Pick')
        ->and($afterSet)->toBe(['box' => 'One', 'hidden' => '1'])
        ->and($afterReset)->toBe(['box' => '', 'hidden' => '']);
});

it('searchable select: edit form with model null and value="2" shows Two and the model receives 2', function () {
    $page = visit(browserPage('select-search-edit', <<<'BLADE'
        <div id="scope" x-data="{ form: { c: null } }">
            <x-dbl::form.select name="c" searchable :options="['1' => 'One', '2' => 'Two']" value="2" x-model="form.c" />
        </div>
        BLADE));

    $model = readPage($page, alpineData('#scope', 'form.c'), fn ($v) => $v === '2');

    expect($model)->toBe('2')
        ->and($page->script("document.querySelector('input[type=text]').value"))->toBe('Two')
        ->and($page->script("document.querySelector('input[type=hidden]').value"))->toBe('2');
});

// ── Native select ──────────────────────────────────────────────────────────

it('native select: dbl-select-changed reaches the parent with { name, value }', function () {
    $page = visit(browserPage('select-native-event', <<<'BLADE'
        <div x-data="{ got: null }" x-on:dbl-select-changed="got = JSON.stringify($event.detail)">
            <x-dbl::form.select name="c" :options="[1 => 'a', 2 => 'b']" />
            <span id="got" x-text="got"></span>
        </div>
        BLADE));

    $page->select('select[name=c]', '2');

    expect(readPage($page, "document.getElementById('got').textContent"))->toBe('{"name":"c","value":"2"}');
});
