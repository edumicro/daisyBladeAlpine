<?php

use Illuminate\Support\Facades\Route;

/*
 * THE CONTRACT OF A CONTROL WITH JAVASCRIPT (resources/js/modelBinding.js): whatever draws its own interface still behaves toward the
 * page as the native control it replaces. Every control of the catalog is changed the way a user changes it, and in its field with a
 * `name` we look for two things:
 *
 *   1. the native events `input` and `change` arrived, with the field as their target;
 *   2. a `depends-on` that points at that name reloads its select (`name` for one value, `name[]` for a list).
 *
 * To add a control to the contract, add a line to the dataset: its Blade, how a user changes it, and the name of its field.
 */

const PAD_SCRIPT = '<script src="/_assets/node/signature_pad/dist/signature_pad.umd.min.js"></script>';

/** One stroke across the canvas of the signature, with the pointer events SignaturePad listens to. */
function strokeOn($page): void
{
    $page->script(<<<'JS'
        (() => {
            const c = document.querySelector('canvas'); const r = c.getBoundingClientRect()
            const at = (type, x, y) => (type === 'pointerup' ? document : c).dispatchEvent(new PointerEvent(type, { bubbles: true, pointerId: 1, pointerType: 'mouse', isPrimary: true, buttons: type === 'pointerup' ? 0 : 1, clientX: r.left + x, clientY: r.top + y }))
            at('pointerdown', 20, 20); for (let i = 1; i < 12; i++) at('pointermove', 20 + i * 10, 20 + i * 5); at('pointerup', 130, 75)
        })()
        JS);
}

/** What a browser does when the user picks an option of a native <select>: `input`, then `change`, both bubbling. */
function pickOption($page, string $selector, string $value): void
{
    $page->script('(() => { const s = document.querySelector('.json_encode($selector).'); s.value = '.json_encode($value).'; for (const t of ["input", "change"]) s.dispatchEvent(new Event(t, { bubbles: true })) })()');
}

/**
 * The controls of the catalog that hold a value.
 *   blade    the control, written as an application would (its field is called `c`)
 *   modules  optional components it needs
 *   change   what a user does to change it
 *   field    the `name` of the field that must announce the change
 *   param    how a `depends-on="c"` sends it: `c` for one value, `c[]` for a list
 */
dataset('controls', function () {
    $countries = "['ES' => 'Spain', 'FR' => 'France']";

    return [
        'text input' => [[
            'blade' => '<x-dbl::form.input name="c" />',
            'change' => function ($page) { $page->typeSlowly('input[name=c]', 'ab', 20); $page->script('document.activeElement.blur()'); },
            'field' => 'c', 'param' => 'c',
        ]],
        'textarea' => [[
            'blade' => '<x-dbl::form.textarea name="c" />',
            'change' => function ($page) { $page->typeSlowly('textarea[name=c]', 'ab', 20); $page->script('document.activeElement.blur()'); },
            'field' => 'c', 'param' => 'c',
        ]],
        'native select' => [[
            'blade' => "<x-dbl::form.select name=\"c\" :options=\"{$countries}\" placeholder=\"--\" />",
            'change' => fn ($page) => pickOption($page, 'select[name=c]', 'FR'),
            'field' => 'c', 'param' => 'c',
        ]],
        'multiple select' => [[
            'blade' => "<x-dbl::form.select name=\"c\" multiple :options=\"{$countries}\" />",
            'change' => fn ($page) => $page->script("(() => { const s = document.querySelector('select[name=\"c[]\"]'); s.options[1].selected = true; for (const t of ['input', 'change']) s.dispatchEvent(new Event(t, { bubbles: true })) })()"),
            'field' => 'c[]', 'param' => 'c[]',
        ]],
        'remote select' => [[
            'blade' => '<x-dbl::form.select name="c" load-url="/options" placeholder="--" />',
            'change' => function ($page) { readPage($page, "document.querySelectorAll('select[name=c] option').length", fn ($n) => $n >= 3); pickOption($page, 'select[name=c]', '2'); },
            'field' => 'c', 'param' => 'c',
        ]],
        'searchable select' => [[
            'blade' => "<x-dbl::form.select name=\"c\" searchable :options=\"{$countries}\" />",
            'change' => function ($page) { $page->script("document.querySelector('input[type=text]').focus()"); pause($page, 0.2); clickText($page, 'France'); },
            'field' => 'c', 'param' => 'c',
        ]],
        'checkbox' => [[
            'blade' => '<x-dbl::form.checkbox name="c" label="Yes" />',
            'change' => fn ($page) => $page->script("document.querySelector('input[type=checkbox][name=c]').click()"),
            'field' => 'c', 'param' => 'c',
        ]],
        'toggle' => [[
            'blade' => '<x-dbl::form.toggle name="c" label="Yes" />',
            'change' => fn ($page) => $page->script("document.querySelector('input[type=checkbox][name=c]').click()"),
            'field' => 'c', 'param' => 'c',
        ]],
        'radio' => [[
            'blade' => "<x-dbl::form.radio name=\"c\" :options=\"{$countries}\" />",
            'change' => fn ($page) => $page->script("document.querySelector('input[type=radio][value=FR]').click()"),
            'field' => 'c', 'param' => 'c',
        ]],
        'key-value editor' => [[
            'blade' => '<x-dbl::form.kv-editor name="c" />',
            'change' => fn ($page) => $page->typeSlowly('input[placeholder=Field]', 'key', 20),
            'field' => 'c', 'param' => 'c',
        ]],
        'list editor' => [[
            'blade' => '<x-dbl::form.list-editor name="c" />',
            'change' => function ($page) { $page->typeSlowly('input[type=text]', 'tag', 20); $page->keys('input[type=text]', 'Enter'); },
            'field' => 'c', 'param' => 'c',
        ]],
        'repeater' => [[
            'blade' => "<x-dbl::form.repeater name=\"c\" :fields=\"[['key' => 'a', 'label' => 'A', 'type' => 'text']]\" />",
            'change' => function ($page) { $page->typeSlowly('input[name="c[0][a]"]', 'ab', 20); $page->script('document.activeElement.blur()'); },
            'field' => 'c[0][a]', 'param' => 'c[0][a]',
        ]],
        'upload' => [[
            'blade' => '<x-dbl::form.upload name="c" upload-url="/upload-ok" />',
            'modules' => ['upload'],
            'change' => fn ($page) => chooseFile($page, 'input[type=file]', 'f.txt', 'hello'),
            'field' => 'c[]', 'param' => 'c[]',
        ]],
        'signature' => [[
            'blade' => PAD_SCRIPT.'<x-dbl::form.signature name="c" />',
            'modules' => ['signature'],
            'change' => fn ($page) => strokeOn($page),
            'field' => 'c', 'param' => 'c',
        ]],
    ];
});

/** A page with the control and, beside it, a remote select that depends on its name. The page records the events of every field. */
function controlPage(string $slug, array $control): array
{
    Route::post('/upload-ok', fn () => response()->json(['id' => 9, 'name' => 'f.txt', 'url' => '/f.txt', 'size' => 5]));
    $dependsOn = $control['field'] === 'c[0][a]' ? 'c[0][a]' : 'c';

    return [browserPage('contract-'.$slug, <<<BLADE
        <form id="f">
            {$control['blade']}
            <x-dbl::form.select name="child" load-url="/options" depends-on="{$dependsOn}" />
        </form>
        BLADE, ['modules' => $control['modules'] ?? []]), $dependsOn];
}

it('contract: changing the control announces input and change in its field with a name', function (array $control) {
    [$url] = controlPage(md5($control['blade']).'-events', $control);
    $page = visit($url);
    pause($page, 0.4);
    $page->script("window.__heard = []; for (const type of ['input', 'change']) document.addEventListener(type, event => window.__heard.push(type + ':' + event.target.name), true)");

    ($control['change'])($page);

    $field = $control['field'];
    $heard = readPage($page, 'window.__heard', fn ($events) => in_array("input:{$field}", $events, true) && in_array("change:{$field}", $events, true));
    expect($heard)->toContain("input:{$field}")->toContain("change:{$field}");
})->with('controls');

it('contract: a select that depends on the control reloads when the user changes it', function (array $control) {
    [$url] = controlPage(md5($control['blade']).'-depends', $control);
    $page = visit($url);
    pause($page, 0.5);
    $before = $page->script('window.__net.log.length');

    ($control['change'])($page);

    $param = $control['param'];
    $asked = readPage($page, "window.__net.log.slice({$before}).map(decodeURIComponent).filter(url => url.includes('/options?'))", fn ($urls) => collect($urls)->contains(fn ($url) => str_contains($url, $param.'=') && ! str_ends_with($url, $param.'=')));
    expect(collect($asked)->contains(fn ($url) => str_contains($url, $param.'=')))->toBeTrue();
})->with('controls');
