<?php

/*
 * form.rich-text with the REAL Tiptap (a development dependency, bundled by tests/Browser/Support/helpers.php::tiptapBundle()).
 * Before, no browser test ever started the editor: `new Editor` was `undefined` in every page.
 */

it('rich text: what the user types goes to the hidden field, as HTML, and announces input and change', function () {
    $page = visit(browserPage('rich-basic', '<form><x-dbl::form.rich-text name="body" /></form>', ['modules' => ['rich-text']]));
    pause($page, 0.5);
    $page->script("window.__heard = []; for (const type of ['input', 'change']) document.addEventListener(type, event => window.__heard.push(type + ':' + event.target.name), true)");

    typeInEditor($page, 'Hello');
    $value = readPage($page, "document.querySelector('input[name=body]').value", fn ($v) => str_contains((string) $v, 'Hello'));

    expect($value)->toBe('<p>Hello</p>')
        ->and($page->script('window.__heard'))->toContain('input:body')->toContain('change:body');
});

it('rich text: the starting HTML of the value is shown in the editor', function () {
    $page = visit(browserPage('rich-value', '<form><x-dbl::form.rich-text name="body" value="<p>Saved <strong>text</strong></p>" /></form>', ['modules' => ['rich-text']]));

    expect(readPage($page, "document.querySelector('.dbl-rich-text-content').innerHTML", fn ($v) => str_contains((string) $v, 'Saved')))->toContain('<strong>text</strong>');
});

it('rich text with x-model: it follows the model of the caller, in both directions, with the rule of the other controls', function () {
    $page = visit(browserPage('rich-model', '<div x-data="{ form: { body: \'\' } }"><form><x-dbl::form.rich-text name="body" value="<p>From the view</p>" x-model="form.body" /></form><output id="m" x-text="form.body"></output></div>', ['modules' => ['rich-text']]));

    // Start: the model is empty, so the view's HTML is shown and the model RECEIVES it.
    $started = readPage($page, "document.querySelector('#m').textContent", fn ($v) => str_contains((string) $v, 'From the view'));
    // The user types: the model follows.
    typeInEditor($page, ' more');
    $typed = readPage($page, "document.querySelector('#m').textContent", fn ($v) => str_contains((string) $v, 'more'));
    // The page changes the model (a record loaded by fetch, a "clear" button): the editor shows it.
    $page->script("Alpine.\$data(document.querySelector('#m').parentElement).form.body = '<p>Loaded</p>'");
    $shown = readPage($page, "document.querySelector('.dbl-rich-text-content').innerHTML", fn ($v) => str_contains((string) $v, 'Loaded'));

    expect($started)->toBe('<p>From the view</p>')
        ->and($typed)->toContain('more')
        ->and($shown)->toBe('<p>Loaded</p>')
        ->and($page->script("document.querySelector('input[name=body]').value"))->toBe('<p>Loaded</p>');
});

it('rich text with x-model: a model that starts with HTML wins over the value of the view', function () {
    $page = visit(browserPage('rich-model-wins', '<div x-data="{ form: { body: \'<p>From the model</p>\' } }"><form><x-dbl::form.rich-text name="body" value="<p>From the view</p>" x-model="form.body" /></form></div>', ['modules' => ['rich-text']]));

    expect(readPage($page, "document.querySelector('.dbl-rich-text-content').innerHTML", fn ($v) => str_contains((string) $v, 'model')))->toBe('<p>From the model</p>');
});

it('rich text required: an empty editor stops the submit and says why; writing something lets it go', function () {
    $page = visit(browserPage('rich-required', '<form id="f"><x-dbl::form.rich-text name="body" label="Body" required /><button type="submit" id="go">go</button></form>', ['modules' => ['rich-text']]));
    pause($page, 0.5);
    // A listener added AFTER the component's own sees whether the component already stopped the submit.
    $page->script("document.querySelector('#f').addEventListener('submit', e => { window.__prevented = e.defaultPrevented; e.preventDefault() })");

    $page->script("document.querySelector('#go').click()");
    pause($page, 0.3);
    $stopped = $page->script('window.__prevented');
    $message = $page->script("[...document.querySelectorAll('[role=alert]')].filter(p => p.offsetParent !== null).map(p => p.textContent.trim()).join('|')");

    typeInEditor($page, 'x');
    $page->script("document.querySelector('#go').click()");
    pause($page, 0.3);

    expect($stopped)->toBeTrue()
        ->and($message)->toContain('required')
        ->and($page->script('window.__prevented'))->toBeFalse();
});

it('rich text max-length: the counter counts text and not tags, and a text over the limit stops the submit', function () {
    $page = visit(browserPage('rich-max', '<form id="f"><x-dbl::form.rich-text name="body" label="Body" :max-length="5" /><button type="submit" id="go">go</button></form>', ['modules' => ['rich-text']]));
    pause($page, 0.5);
    $page->script("document.querySelector('#f').addEventListener('submit', e => { window.__prevented = e.defaultPrevented; e.preventDefault() })");

    typeInEditor($page, 'abc');
    $counter = readPage($page, "document.querySelector('label span.text-xs')?.textContent", fn ($v) => $v === '3/5');
    $page->script("document.querySelector('#go').click()");
    pause($page, 0.3);
    $okSubmit = $page->script('window.__prevented');

    typeInEditor($page, 'defg');
    readPage($page, "document.querySelector('label span.text-xs')?.textContent", fn ($v) => $v === '7/5');
    $page->script("document.querySelector('#go').click()");
    pause($page, 0.3);

    expect($counter)->toBe('3/5')
        ->and($okSubmit)->toBeFalse()
        ->and($page->script('window.__prevented'))->toBeTrue()
        ->and($page->script("document.querySelector('label span.text-xs').className"))->toContain('text-error');
});
