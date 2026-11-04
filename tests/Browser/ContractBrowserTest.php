<?php

use Illuminate\Support\Facades\Route;

/*
 * The contract with the server in a real browser: an answer is good by its TYPE and its FORM, never by the URL it came from.
 * (docs/contrato.md)
 */

it('a redirect to the login page on a remote select is an error with retry, not an empty list', function () {
    Route::get('/contract-login', fn () => response('<html>login</html>', 200, ['Content-Type' => 'text/html']));
    Route::get('/contract-redirect', fn () => redirect('/contract-login'));
    $page = visit(browserPage('contract-redirect', '<x-dbl::form.select name="c" load-url="/contract-redirect" />'));

    $error = readPage($page, "(() => { const e = document.querySelector('[x-data]:has(> p[role=alert])'); return Alpine.\$data(e).error })()");

    expect($error)->not->toBe('');
});

it('a POST that redirects to a GET of the same path and ends in JSON is accepted, as fetch or XMLHttpRequest would see it', function () {
    Route::post('/contract-same', fn () => redirect('/contract-same'));
    Route::get('/contract-same', fn () => response()->json(['ok' => true]));
    $page = visit(browserPage('contract-same-path', '<div id="x">x</div>'));

    $outcome = $page->script(<<<'JS'
        (async () => {
            const { request } = await import('/_assets/js/http.js')
            try { return JSON.stringify((await request({ method: 'post', url: '/contract-same', data: {} })).data) } catch (e) { return e.reason ?? e.message }
        })()
        JS);

    expect($outcome)->toBe('{"ok":true}');
});

it('an HTML fragment of a lazy tab that looks like JSON ("[Draft] <b>hello</b>") still arrives as the tab content', function () {
    Route::get('/contract-fragment', fn () => response('[Draft] <b>hello</b>', 200, ['Content-Type' => 'text/html']));
    $page = visit(browserPage('contract-fragment', '<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load_url\' => \'/contract-fragment\']]" />'));

    $text = readPage($page, 'document.body.innerText', fn ($value) => str_contains($value, 'hello'));

    expect($text)->toContain('hello');
});

it('a 204 on a remote select is an error with retry, not an empty list', function () {
    Route::get('/contract-204', fn () => response('', 204));
    $page = visit(browserPage('contract-204', '<x-dbl::form.select name="c" load-url="/contract-204" />'));

    $error = readPage($page, "(() => { const e = document.querySelector('[x-data*=dblSelectRemote]'); return Alpine.\$data(e).error })()");

    expect($error)->not->toBe('');
});

it('a lazy tab whose answer is JSON ({"message": ...} with a 200) is an error, not the text of the tab', function () {
    Route::get('/contract-json-tab', fn () => response()->json(['message' => 'Unauthenticated.']));
    $page = visit(browserPage('contract-json-tab', '<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load_url\' => \'/contract-json-tab\']]" />'));

    pause($page, 0.8);

    expect($page->script('document.body.innerText'))->not->toContain('Unauthenticated');
});

it('a lazy tab whose answer is a whole HTML page (the login page) is an error, not the text of the tab', function () {
    Route::get('/contract-page-tab', fn () => response('<!DOCTYPE html><html><body>Sign in to continue</body></html>', 200, ['Content-Type' => 'text/html']));
    $page = visit(browserPage('contract-page-tab', '<x-dbl::sections.tabs :tabs="[[\'label\' => \'A\', \'load_url\' => \'/contract-page-tab\']]" />'));

    pause($page, 0.8);

    expect($page->script('document.body.innerText'))->not->toContain('Sign in to continue');
});
