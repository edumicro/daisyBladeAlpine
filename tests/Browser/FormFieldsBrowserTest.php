<?php

/*
 * Small form components in the browser: repeater, textarea counter, kv-editor, auto-form, filters.
 * Findings: ronda 1 RP1, RP2, RP3, TA, TA0, KV, AF1, FC · ronda 2 TA3, TA4.
 */

// ── Repeater ───────────────────────────────────────────────────────────────

it('repeater: the x-on:input of the caller runs when something is typed', function () {
    $page = visit(browserPage('repeater-listener', <<<'BLADE'
        <x-dbl::form.repeater name="r" :fields="[['key' => 'a', 'type' => 'text']]" x-on:input="window.typed = (window.typed || 0) + 1" />
        BLADE));

    $page->typeSlowly('input[type=text]', 'ab', 20);

    expect($page->script('window.typed ?? 0'))->toBe(2);
});

it('repeater (alpine mode): the rows are poured into the scope of the caller', function () {
    $page = visit(browserPage('repeater-alpine-mode', <<<'BLADE'
        <div x-data="{ data: {} }">
            <x-dbl::form.repeater name="r" mode="alpine" :fields="[['key' => 'a', 'type' => 'text']]" />
            <span id="mirror" x-text="JSON.stringify(data)"></span>
        </div>
        BLADE));

    $page->typeSlowly('input[type=text]', 'ab', 20);

    expect(readPage($page, "document.getElementById('mirror').textContent", fn ($v) => str_contains($v, 'ab')))->toContain('ab');
});

it('repeater: attrs.class is added to the classes of the control', function () {
    $page = visit(browserPage('repeater-attrs-class', <<<'BLADE'
        <x-dbl::form.repeater name="r" :fields="[['key' => 'a', 'type' => 'text', 'attrs' => ['class' => 'font-mono']]]" />
        BLADE));

    $classes = $page->script("document.querySelector('input').className");

    expect($classes)->toContain('font-mono')->toContain('input-sm')->toContain('w-full');
});

// ── Textarea counter ───────────────────────────────────────────────────────

it('textarea: the counter counts what is typed (hello + " world" = 11/20)', function () {
    $page = visit(browserPage('textarea-typing', '<x-dbl::form.textarea name="bio" label="Bio" max-length="20" value="hello" />'));

    $page->typeSlowly('textarea[name=bio]', ' world', 10);

    expect($page->script("document.querySelector('label span.text-xs').textContent.trim()"))->toBe('11/20');
});

it('textarea: with an initial value the counter starts at 5/20', function () {
    $page = visit(browserPage('textarea-initial', '<x-dbl::form.textarea name="bio" label="Bio" max-length="20" value="hello" />'));

    expect($page->script("document.querySelector('label span.text-xs').textContent.trim()"))->toBe('5/20');
});

it('textarea: the model changed from outside moves the counter (11/20)', function () {
    $page = visit(browserPage('textarea-model-outside', <<<'BLADE'
        <div x-data="{ form: { bio: '' } }">
            <x-dbl::form.textarea name="bio" label="Bio" max-length="20" x-model="form.bio" />
            <button id="set" type="button" @click="form.bio = 'hello world'">set</button>
        </div>
        BLADE));

    $page->click('#set');

    expect(readPage($page, "document.querySelector('label span.text-xs').textContent.trim()", fn ($v) => $v === '11/20'))->toBe('11/20');
});

it('textarea: a model that starts with "abc" makes the counter say 3/20', function () {
    $page = visit(browserPage('textarea-model-start', <<<'BLADE'
        <div x-data="{ form: { bio: 'abc' } }"><x-dbl::form.textarea name="bio" label="Bio" max-length="20" x-model="form.bio" /></div>
        BLADE));

    expect(readPage($page, "document.querySelector('label span.text-xs').textContent.trim()", fn ($v) => $v === '3/20'))->toBe('3/20');
});

// ── kv-editor ──────────────────────────────────────────────────────────────

it('kv-editor: a true stays true, a false stays false and a number stays a number', function () {
    $page = visit(browserPage('kv-editor-bool', <<<'BLADE'
        <form><x-dbl::form.kv-editor name="cfg" :value="['active' => true, 'off' => false, 'n' => 3]" /></form>
        BLADE));

    $value = json_decode($page->script("document.querySelector('input[name=cfg]').value"), true);

    expect($value)->toBe(['active' => true, 'off' => false, 'n' => 3]);
});

// ── auto-form ──────────────────────────────────────────────────────────────

it('auto-form: the default of each field is shown', function () {
    $page = visit(browserPage('auto-form-defaults', <<<'BLADE'
        <x-dbl::sections.auto-form submit-url="/save" :fields="['name' => ['type' => 'text', 'default' => 'Ana'], 'ok' => ['type' => 'checkbox', 'default' => true]]" />
        BLADE));

    expect($page->script("document.querySelector('input[type=text]').value"))->toBe('Ana')
        ->and($page->script("document.querySelector('input[type=checkbox]').checked"))->toBeTrue();
});

// ── filters ────────────────────────────────────────────────────────────────

it('filters: "Clear all" empties the select, the text and the checkbox', function () {
    $page = visit(browserPage('filters-clear-all', <<<'BLADE'
        <x-dbl::form.filters :filters="[['key' => 'st', 'type' => 'select', 'options' => ['a' => 'A']], ['key' => 'q', 'type' => 'text'], ['key' => 'on', 'type' => 'boolean']]" />
        BLADE));

    // The controls live inside a closed dropdown, so they are driven by events, as the browser would send them.
    $page->script("(() => { const s = document.querySelector('select'); s.value = 'a'; s.dispatchEvent(new Event('change', { bubbles: true })) })()");
    $page->script("(() => { const t = document.querySelector('input[type=text]'); t.value = 'abc'; t.dispatchEvent(new Event('input', { bubbles: true })) })()");
    pause($page, 0.6);
    $page->script("document.querySelector('input[type=checkbox]').click()");
    clickText($page, 'Clear all');

    $after = readPage($page, "({ select: document.querySelector('select').value, text: document.querySelector('input[type=text]').value, on: document.querySelector('input[type=checkbox]').checked })", fn ($v) => $v['text'] === '');

    expect($after)->toBe(['select' => '', 'text' => '', 'on' => false]);
});
