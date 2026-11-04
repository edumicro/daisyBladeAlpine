<?php

/*
 * The model of a select is observed for as long as the control lives (rule 3 of modelBinding.js), and `depends-on` follows the
 * VALUE of the control it depends on, not its `change` event.
 */

it('remote select: a model that changes AFTER the load to a value the options do not offer is shown', function () {
    $page = visit(browserPage('model-late-unknown', <<<'BLADE'
        <div id="scope" x-data="{ form: { city: '1' } }"><form>
            <x-dbl::form.select name="city" load-url="/options" x-model="form.city" />
        </form></div>
        BLADE));

    readPage($page, "document.querySelectorAll('select[name=city] option').length", fn ($count) => $count >= 2);
    $page->script("Alpine.\$data(document.querySelector('#scope')).form.city = '9'");
    pause($page);

    expect($page->script("document.querySelector('select[name=city]').value"))->toBe('9');
});

it('remote select: a model that changes after the load to an offered value selects it, and emptied it leaves nothing chosen', function () {
    $page = visit(browserPage('model-late-known', <<<'BLADE'
        <div id="scope" x-data="{ form: { city: '1' } }"><form>
            <x-dbl::form.select name="city" load-url="/options" placeholder="--" x-model="form.city" />
        </form></div>
        BLADE));

    readPage($page, "document.querySelectorAll('select[name=city] option').length", fn ($count) => $count >= 3);
    $page->script("Alpine.\$data(document.querySelector('#scope')).form.city = '2'");
    $second = readPage($page, "document.querySelector('select[name=city]').value", fn ($value) => $value === '2');
    $page->script("Alpine.\$data(document.querySelector('#scope')).form.city = ''");
    $emptied = readPage($page, "document.querySelector('select[name=city]').value", fn ($value) => $value === '');

    expect($second)->toBe('2')
        ->and($emptied)->toBe('');
});

it('remote select multiple: model [] and value ["1","2"] -> the model receives both', function () {
    $page = visit(browserPage('model-multiple', <<<'BLADE'
        <div id="scope" x-data="{ form: { c: [] } }"><form>
            <x-dbl::form.select name="c" multiple load-url="/options" :value="['1','2']" x-model="form.c" />
        </form></div>
        BLADE));

    readPage($page, "document.querySelectorAll('select[name=\"c[]\"] option').length", fn ($count) => $count >= 2);
    pause($page);

    expect($page->script("JSON.stringify(Alpine.\$data(document.querySelector('#scope')).form.c)"))->toBe('["1","2"]');
});

it('remote select: a numeric model 2 selects the option "2"; a null model takes the value of the view', function () {
    $page = visit(browserPage('model-numbers', <<<'BLADE'
        <div id="scope" x-data="{ form: { a: 2, b: null } }"><form>
            <x-dbl::form.select name="a" load-url="/options" x-model="form.a" />
            <x-dbl::form.select name="b" load-url="/options" placeholder="--" value="1" x-model="form.b" />
        </form></div>
        BLADE));

    readPage($page, "document.querySelectorAll('select[name=a] option').length", fn ($count) => $count >= 2);
    pause($page);

    expect($page->script("document.querySelector('select[name=a]').value"))->toBe('2')
        ->and($page->script("document.querySelector('select[name=b]').value"))->toBe('1')
        ->and($page->script("Alpine.\$data(document.querySelector('#scope')).form.b"))->toBe('1');
});

it('searchable select: the model changed from outside shows the label of its option', function () {
    $page = visit(browserPage('model-searchable', <<<'BLADE'
        <div id="scope" x-data="{ form: { city: '1' } }"><form>
            <x-dbl::form.select name="city" searchable :options="['1' => 'One', '2' => 'Two']" x-model="form.city" />
        </form></div>
        BLADE));

    pause($page);
    $page->script("Alpine.\$data(document.querySelector('#scope')).form.city = '2'");
    $shown = readPage($page, "document.querySelector('input[type=text]').value", fn ($value) => $value === 'Two');

    expect($shown)->toBe('Two');
});

it('depends-on: a parent changed by its MODEL (no change event) reloads the dependent select with the new value, once', function () {
    $page = visit(browserPage('depends-model', <<<'BLADE'
        <div id="scope" x-data="{ form: { country: '', region: '' } }"><form>
            <x-dbl::form.select name="country" :options="['ES' => 'Spain', 'FR' => 'France']" placeholder="--" x-model="form.country" />
            <x-dbl::form.select name="region" load-url="/options" depends-on="country" x-model="form.region" />
        </form></div>
        BLADE));

    pause($page);
    $page->script("Alpine.\$data(document.querySelector('#scope')).form.country = 'ES'");
    pause($page, 0.8);

    expect($page->script("window.__net.log.filter(url => url.includes('country=ES')).length"))->toBe(1);
});

it('depends-on: the user choosing in a parent with a model reloads once, not twice (the change event and the model say the same)', function () {
    $page = visit(browserPage('depends-user-model', <<<'BLADE'
        <div id="scope" x-data="{ form: { country: '', region: '' } }"><form>
            <x-dbl::form.select name="country" :options="['ES' => 'Spain', 'FR' => 'France']" placeholder="--" x-model="form.country" />
            <x-dbl::form.select name="region" load-url="/options" depends-on="country" x-model="form.region" />
        </form></div>
        BLADE));

    pause($page);
    setField($page, 'select[name=country]', 'FR', 'change');
    pause($page, 0.8);

    expect($page->script("window.__net.log.filter(url => url.includes('country=FR')).length"))->toBe(1);
});

it('depends-on: a parent WITHOUT a model reloads when the user changes it, once', function () {
    $page = visit(browserPage('depends-no-model', <<<'BLADE'
        <form>
            <x-dbl::form.select name="country" :options="['ES' => 'Spain', 'FR' => 'France']" placeholder="--" />
            <x-dbl::form.select name="region" load-url="/options" depends-on="country" />
        </form>
        BLADE));

    pause($page);
    setField($page, 'select[name=country]', 'ES', 'change');
    pause($page, 0.8);

    expect($page->script("window.__net.log.filter(url => url.includes('country=ES')).length"))->toBe(1);
});

it('depends-on: a change of the parent made while the first load is running is not lost', function () {
    $page = visit(browserPage('depends-during-load', <<<'BLADE'
        <div id="scope" x-data="{ form: { country: '', region: '' } }"><form>
            <x-dbl::form.select name="country" :options="['ES' => 'Spain', 'FR' => 'France']" placeholder="--" x-model="form.country" />
            <x-dbl::form.select name="region" load-url="/options" depends-on="country" x-model="form.region" />
        </form></div>
        BLADE));

    pause($page);
    $page->script("Alpine.\$data(document.querySelector('#scope')).form.country = 'FR'");
    $page->script("Alpine.\$data(document.querySelector('#scope')).form.country = 'ES'");
    pause($page, 0.8);

    expect($page->script("window.__net.log.at(-1)"))->toContain('country=ES');
});
