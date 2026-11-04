<?php

use Illuminate\Support\Facades\Blade;

function searchableTextBox(string $attributes): string
{
    $html = Blade::render('<x-dbl::form.select name="c" searchable '.$attributes.' :options="[\'1\' => \'One\']" />');
    preg_match('/<input[^>]*type="text"[^>]*>/', $html, $box);

    return $box[0];
}

it('a searchable select that is disabled has a disabled text box and no hidden value to send', function () {
    $html = Blade::render('<x-dbl::form.select name="c" searchable disabled :options="[\'1\' => \'One\']" />');
    preg_match('/<input[^>]*type="hidden"[^>]*>/', $html, $hidden);

    expect(searchableTextBox('disabled'))->toContain('disabled')
        ->and($hidden[0])->toContain('disabled');
});

it('a searchable select that is required has a required text box', function () {
    expect(searchableTextBox('required'))->toContain('required');
});

it('a searchable select that is neither has neither', function () {
    expect(searchableTextBox(''))->not->toContain('disabled')->not->toContain('required');
});

// Escape closes the list, and a click outside too: both are done by dblSelectSearch (its listeners on the text box and the window),
// and walked in the browser (SelectBrowserTest). The view only gives the window listener.
it('closes the list of a searchable select with a click anywhere else: the window listener is on the scope', function () {
    $html = Blade::render('<x-dbl::form.select name="c" searchable :options="[\'1\' => \'One\']" />');

    expect($html)->toContain('x-on:click.window="closeWhenOutside($event)"');
});
