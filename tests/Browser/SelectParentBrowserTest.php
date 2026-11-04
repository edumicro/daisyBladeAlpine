<?php

/*
what `depends-on` follows when the parent is not a native <select>, and the corners of observe().
 */

it('a remote select that depends on a SEARCHABLE select reloads when the user picks an option there (no model)', function () {
    $page = visit(browserPage('real-app-dep-searchable', <<<'BLADE'
        <form>
            <x-dbl::form.select name="country" searchable :options="['ES' => 'Spain', 'FR' => 'France']" />
            <x-dbl::form.select name="region" load-url="/options" depends-on="country" />
        </form>
        BLADE));

    pause($page);
    $page->script("document.querySelector('input[type=text]').focus(); document.querySelector('input[type=text]').click()");
    pause($page, 0.3);
    clickText($page, 'France');
    pause($page, 0.8);

    expect($page->script("window.__net.log.filter(url => url.includes('country=FR')).length"))->toBe(1);
});

it('a remote select that depends on a SEARCHABLE select reloads when the user picks an option there (with a model)', function () {
    $page = visit(browserPage('real-app-dep-searchable-model', <<<'BLADE'
        <div x-data="{ form: { country: '', region: '' } }"><form>
            <x-dbl::form.select name="country" searchable :options="['ES' => 'Spain', 'FR' => 'France']" x-model="form.country" />
            <x-dbl::form.select name="region" load-url="/options" depends-on="country" x-model="form.region" />
        </form></div>
        BLADE));

    pause($page);
    $page->script("document.querySelector('input[type=text]').focus(); document.querySelector('input[type=text]').click()");
    pause($page, 0.3);
    clickText($page, 'France');
    pause($page, 0.8);

    expect($page->script("window.__net.log.filter(url => url.includes('country=FR')).length"))->toBe(1);
});

it('a remote select that depends on a group of RADIOS reloads when the user checks one', function () {
    $page = visit(browserPage('real-app-dep-radio', <<<'BLADE'
        <form>
            <x-dbl::form.radio name="country" :options="['ES' => 'Spain', 'FR' => 'France']" />
            <x-dbl::form.select name="region" load-url="/options" depends-on="country" />
        </form>
        BLADE));

    pause($page);
    $page->script("document.querySelector('input[type=radio][value=FR]').click()");
    pause($page, 0.8);

    expect($page->script("window.__net.log.filter(url => url.includes('country=FR')).length"))->toBe(1);
});

it('a remote select that depends on a TEXT INPUT follows what is typed (after change)', function () {
    $page = visit(browserPage('real-app-dep-input', <<<'BLADE'
        <form>
            <x-dbl::form.input name="zip" />
            <x-dbl::form.select name="city" load-url="/options" depends-on="zip" />
        </form>
        BLADE));

    pause($page);
    setField($page, 'input[name=zip]', '46001', 'change');
    pause($page, 0.8);

    expect($page->script("window.__net.log.filter(url => url.includes('zip=46001')).length"))->toBe(1);
});
