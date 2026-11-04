<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

/**
 * What the view threw. Laravel wraps any exception of a view in a ViewException (with the file of the view added to the
 * message), so the one the view threw is the first of the chain that is not that wrapper.
 */
function renderFailure(string $blade): Throwable
{
    try {
        Blade::render($blade);
    } catch (Throwable $failure) {
        while ($failure instanceof ViewException && $failure->getPrevious() !== null) {
            $failure = $failure->getPrevious();
        }

        return $failure;
    }

    throw new RuntimeException("It rendered without failing: {$blade}");
}

/**
 * Migrating without aliases: a 2.x name (`action=`, `options-url=`) is not a prop any more, so it lands in
 * `$attributes` as a plain HTML attribute and the component runs with an EMPTY URL. The cheapest warning that is not
 * an alias: a component whose URL is the reason it exists refuses to render without it, and the message names the
 * property that is missing (and the name it had in 2.x, when it was another one).
 *
 * What happened before (Chrome):
 *   <x-dbl::sections.auto-form action="/save">   POSTed to the current page, got its HTML with a 200, and showed
 *                                                 the success message: success = true, no error anywhere.
 *   <x-dbl::display.data-table options-url="/rows"> GETted the current page and showed "Could not load the data" +
 *                                                 Retry, as if the server had failed.
 */
it('refuses to render a component whose required URL is empty, so a 2.x name is noticed at once', function () {
    $this->withoutVite();
    $cases = [
        '<x-dbl::sections.auto-form action="/save" :fields="[]" />',
        '<x-dbl::sections.wizard action="/save" :steps="[]" />',
        '<x-dbl::display.data-table options-url="/rows" :columns="[]" />',   // the name of the select, copied
        '<x-dbl::display.resource-details />',
        '<x-dbl::display.calendar />',
        '<x-dbl::form.upload action="/files" />',
        '<x-dbl::import.spreadsheet action="/import" />',
    ];

    foreach ($cases as $blade) {
        expect(renderFailure($blade))->toBeInstanceOf(InvalidArgumentException::class, $blade);
    }
});

it('says which property is missing, and the name it had in 2.x when it had another one', function () {
    $this->withoutVite();

    expect(renderFailure('<x-dbl::sections.auto-form action="/save" :fields="[]" />')->getMessage())
        ->toBe('<x-dbl::sections.auto-form> needs the `submit-url` property (in 2.x it was called `action`).')
        ->and(renderFailure('<x-dbl::form.upload action="/files" />')->getMessage())
        ->toBe('<x-dbl::form.upload> needs the `upload-url` property (in 2.x it was called `action`).')
        // data-table and resource-details already said load-url in 2.x: there is no old name to point at.
        ->and(renderFailure('<x-dbl::display.data-table options-url="/rows" :columns="[]" />')->getMessage())
        ->toBe('<x-dbl::display.data-table> needs the `load-url` property.');
});

it('still renders an auto-form in form mode without a submit-url: its action is the current page', function () {
    $this->withoutVite();

    expect(Blade::render('<x-dbl::sections.auto-form mode="form" :fields="[]" />'))->toContain('<form');
});

it('still renders the same components when their URL is given', function () {
    $this->withoutVite();

    expect(Blade::render('<x-dbl::sections.auto-form submit-url="/save" :fields="[]" />'))->toContain('dblAutoForm(')
        ->and(Blade::render('<x-dbl::display.data-table load-url="/rows" :columns="[]" />'))->toContain('dblDataTable(')
        ->and(Blade::render('<x-dbl::form.upload upload-url="/files" />'))->toContain('dblUpload(')
        ->and(Blade::render('<x-dbl::display.calendar load-url="/events" />'))->toContain('dblCalendar(');
});
