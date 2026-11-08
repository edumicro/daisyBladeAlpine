<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/*
 * form.select with `search-url`: the searchable select that asks the server what to show while the user types. These tests look at the
 * HTML that is painted (the behaviour in the browser is in tests/js/select-search-server.test.mjs).
 */

beforeEach(function () {
    View::share('errors', new ViewErrorBag);
    $this->withoutVite();
});

/** What the view threw (Laravel wraps it in a ViewException). */
function selectSearchFailure(string $blade): Throwable
{
    try {
        Blade::render($blade);
    } catch (Throwable $failure) {
        while ($failure instanceof Illuminate\View\ViewException && $failure->getPrevious() !== null) {
            $failure = $failure->getPrevious();
        }

        return $failure;
    }

    throw new RuntimeException("It rendered without failing: {$blade}");
}

/** The first tag of the kind in the html. */
function selectSearchTag(string $html, string $pattern): string
{
    preg_match($pattern, $html, $found);

    return $found[0] ?? '';
}

/** The configuration that the view gave to dblSelectSearch, decoded. */
function selectSearchConfig(string $html): array
{
    return xDataConfig($html, 'dblSelectSearch');
}

it('paints two fields from the start: the hidden one sends the key under `name`, the box sends the text under `{name}_text`', function () {
    $html = Blade::render('<x-dbl::form.select name="medicine_id" search-url="/medicines" label="Medicine" />');
    $box = selectSearchTag($html, '/<input[^>]*type="text"[^>]*>/');
    $hidden = selectSearchTag($html, '/<input[^>]*type="hidden"[^>]*>/');

    expect($box)->toContain('name="medicine_id_text"')->toContain('id="medicine_id"')
        ->and($hidden)->toContain('name="medicine_id"')->toContain('value=""')
        ->and($html)->toContain('Medicine');
});

it('names the field of the typed text with `text-name`', function () {
    $html = Blade::render('<x-dbl::form.select name="medicine_id" search-url="/medicines" text-name="medicine_name" />');

    expect(selectSearchTag($html, '/<input[^>]*type="text"[^>]*>/'))->toContain('name="medicine_name"')->not->toContain('medicine_id_text');
});

it('is a combobox for assistive technology', function () {
    $html = Blade::render('<x-dbl::form.select name="m" search-url="/medicines" />');
    $box = selectSearchTag($html, '/<input[^>]*type="text"[^>]*>/');

    expect($box)->toContain('role="combobox"')->toContain('aria-expanded="false"')->toContain('aria-autocomplete="list"')->toContain('autocomplete="off"');
    preg_match('/aria-controls="([^"]+)"/', $box, $controls);
    expect($html)->toContain('id="'.$controls[1].'"')->toContain('role="listbox"')->toContain('role="option"')
        ->and($controls[1])->toStartWith('dbl-select-');
});

it('shows what was already chosen, for a form that comes back with an error', function () {
    $html = Blade::render('<x-dbl::form.select name="m" search-url="/medicines" value="42" text="Ibuprofen 600" />');

    expect(selectSearchTag($html, '/<input[^>]*type="text"[^>]*>/'))->toContain('value="Ibuprofen 600"')
        ->and(selectSearchTag($html, '/<input[^>]*type="hidden"[^>]*>/'))->toContain('name="m"')->toContain('value="42"')
        ->and(selectSearchConfig($html))->toMatchArray(['chosen' => '42', 'search' => 'Ibuprofen 600']);
});

it('without `text` shows the value rather than an empty box over a hidden value', function () {
    $html = Blade::render('<x-dbl::form.select name="m" search-url="/medicines" value="42" />');

    expect(selectSearchTag($html, '/<input[^>]*type="text"[^>]*>/'))->toContain('value="42"');
});

it('gives the JavaScript the configuration in one object, with the defaults', function () {
    $config = selectSearchConfig(Blade::render('<x-dbl::form.select name="m" search-url="/medicines" />'));

    expect($config)->toMatchArray(['searchUrl' => '/medicines', 'queryParam' => 'q', 'limit' => 20, 'minChars' => 2, 'debounce' => 250, 'allowFreeText' => false, 'optionLabel' => 'text', 'dependsOn' => '']);
    expect($config['messages'])->toHaveKeys(['minChars', 'searching', 'empty', 'loadFailed', 'chooseFromList']);
    expect($config['messages']['minChars'])->toContain('2');
});

it('passes every prop of the search through', function () {
    $config = selectSearchConfig(Blade::render(<<<'BLADE'
        <x-dbl::form.select name="m" search-url="/medicines" query-param="term" :params="['country_code' => 'ES']" depends-on="country"
            :limit="10" :min-chars="3" :debounce="400" allow-free-text option-label="nombre"
            process-results="response.items" on-select="picked(row)" on-error="failed(status)" />
        BLADE));

    expect($config)->toMatchArray([
        'queryParam' => 'term', 'params' => ['country_code' => 'ES'], 'dependsOn' => 'country', 'limit' => 10, 'minChars' => 3, 'debounce' => 400,
        'allowFreeText' => true, 'optionLabel' => 'nombre', 'processResults' => 'response.items', 'onSelect' => 'picked(row)', 'onError' => 'failed(status)',
    ]);
});

it('listens to the change of the control it depends on only when it searches', function () {
    expect(Blade::render('<x-dbl::form.select name="m" search-url="/m" depends-on="c" />'))->toContain('x-on:change.window.capture="onChange($event)"')
        ->and(Blade::render('<x-dbl::form.select name="m" searchable :options="[1 => \'x\']" />'))->not->toContain('x-on:change.window.capture');
});

it('says its states in words, with a Retry button, and takes the error of the field from field-error', function () {
    $html = Blade::render('<x-dbl::form.select name="m" search-url="/medicines" />');
    expect($html)->toContain('x-text="statusText"')->toContain('Retry')->toContain('retry()');

    View::share('errors', (new ViewErrorBag)->put('default', new Illuminate\Support\MessageBag(['m' => ['The medicine is required.']])));
    $withError = Blade::render('<x-dbl::form.select name="m" search-url="/medicines" />');
    expect($withError)->toContain('The medicine is required.')->toContain('input-error');
});

it('puts the classes where the 3.x convention says', function () {
    $html = Blade::render('<x-dbl::form.select name="m" search-url="/m" class="mine" data-probe="1" wrapper-class="the-box" list-class="the-list" hint="Name or code" size="lg" />');

    expect(selectSearchTag($html, '/<input[^>]*type="text"[^>]*>/'))->toContain('mine')->toContain('data-probe="1"')->toContain('select-lg')
        ->and($html)->toContain('the-box')->toContain('the-list')->toContain('Name or code');
});

it('refuses to ask for the whole list and to search at once: it does not choose one in silence', function () {
    $failure = selectSearchFailure('<x-dbl::form.select name="m" load-url="/all" search-url="/search" />');

    expect($failure)->toBeInstanceOf(InvalidArgumentException::class)->and($failure->getMessage())->toContain('cannot have both `load-url`');
});

it('refuses `multiple` with `search-url`', function () {
    expect(selectSearchFailure('<x-dbl::form.select name="m" search-url="/search" multiple />'))->toBeInstanceOf(InvalidArgumentException::class);
});

it('leaves the other modes as they were: the searchable one has no name on its box, the remote one is still a <select>', function () {
    $searchable = Blade::render('<x-dbl::form.select name="m" searchable :options="[1 => \'x\']" />');
    $remote = Blade::render('<x-dbl::form.select name="m" load-url="/all" />');

    expect(selectSearchTag($searchable, '/<input[^>]*type="text"[^>]*>/'))->not->toContain('name=')
        ->and(selectSearchTag($searchable, '/<input[^>]*type="hidden"[^>]*>/'))->toContain('name="m"')
        ->and($remote)->toContain('<select')->toContain('dblSelectRemote(')->not->toContain('role="combobox"');
});

it('gives the fixed params to the remote select too', function () {
    $html = Blade::render('<x-dbl::form.select name="r" load-url="/regions" :params="[\'country_code\' => \'ES\']" />');

    expect(xDataConfig($html, 'dblSelectRemote')['params'])->toBe(['country_code' => 'ES']);
});
