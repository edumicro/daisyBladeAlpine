<?php

use Illuminate\Support\Facades\Blade;

/**
 * CHANGELOG [3.0.0]: "En un control de formulario, `id` es el del control y la etiqueta apunta a él". In form.rich-text the
 * label pointed with `for` at `<name>-editor`, a <div>: `for` only works on labelable elements (input, select, textarea,
 * button...), so pressing the label did nothing, and the `id` the caller writes (which lands on the box of the editor)
 * was never what the label pointed at. The editor is not a native control, so it is one of the exceptions of the `id`
 * rule (with signature and upload): the label focuses the editor with a click handler instead.
 */
it('does not point the rich-text label at a <div> with `for`, which could not be labelled', function () {
    $html = Blade::render('<x-dbl::form.rich-text name="body" label="Body" id="mine" />');

    preg_match('/<label\b[^>]*>/', $html, $label);

    expect($label)->not->toBeEmpty()
        ->and($label[0])->not->toContain(' for=');
});

it('focuses the editor when the rich-text label is pressed', function () {
    $html = Blade::render('<x-dbl::form.rich-text name="body" label="Body" />');

    preg_match('/<label\b[^>]*>/', $html, $label);

    // `.prevent`: the label is not a link, there is nothing to follow; and the click must not also do something else.
    expect($label[0])->toContain('x-on:click.prevent="focus()"');
});

it('keeps the id the caller writes on the box of the editor', function () {
    $html = Blade::render('<x-dbl::form.rich-text name="body" label="Body" id="mine" />');

    expect($html)->toContain('id="mine"');
});

it('writes no label when there is no label text', function () {
    expect(Blade::render('<x-dbl::form.rich-text name="body" />'))->not->toContain('<label');
});
