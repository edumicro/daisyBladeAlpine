<?php

use Illuminate\Support\Facades\Blade;

function repeater(string $attributes = '', string $fields = "[['key'=>'a','label'=>'A']]"): string
{
    return Blade::render("<x-dbl::form.repeater name=\"items\" :fields=\"{$fields}\" {$attributes} />");
}

// ── defaults ──────────────────────────────────────────────────────────────────

it('renders a row and both buttons', function () {
    $html = repeater();

    expect($html)
        ->toContain('x-for="(row, index) in rows"')
        ->toContain('x-on:click="add()"')
        ->toContain('x-on:click="remove(index)"');
});

it('announces row changes so a parent can react without reaching in', function () {
    $html = repeater();

    expect($html)
        ->toContain('x-on:input="notify()"')
        ->toContain("dbl-repeater-change");
});

// ── events prop ───────────────────────────────────────────────────────────────

it('binds a consumer event on the root', function () {
    $html = repeater(":events=\"['root' => ['keydown' => ['enter' => ['prevent' => 'add()']]]]\"");

    expect($html)->toContain('x-on:keydown.enter.prevent="add()"');
});

it('keeps its own defaults when the consumer binds something else', function () {
    $html = repeater(":events=\"['root' => ['keydown.enter' => 'add()']]\"");

    expect($html)
        ->toContain('x-on:keydown.enter="add()"')
        ->toContain('x-on:input="notify()"');
});

it('lets the consumer replace a default binding rather than stack a second one', function () {
    $html = repeater(":events=\"['add' => ['click' => 'add(); mine()']]\"");

    expect($html)
        ->toContain('x-on:click="add(); mine()"')
        ->not->toContain('x-on:click="add()"');
});

it('binds on the row', function () {
    $html = repeater(":events=\"['row' => ['focusin' => 'track(index)']]\"");

    expect($html)->toContain('x-on:focusin="track(index)"');
});

// ── decimal ───────────────────────────────────────────────────────────────────

it('renders a decimal field as text with a numeric keypad', function () {
    $html = repeater(fields: "[['key'=>'dose','type'=>'decimal']]");

    expect($html)
        ->toContain('inputmode="decimal"')
        ->toContain('type="text"')
        ->not->toContain('type="number"');
});

it('still renders number for the number type', function () {
    $html = repeater(fields: "[['key'=>'qty','type'=>'number']]");

    expect($html)->toContain('type="number"');
});

// ── attrs ─────────────────────────────────────────────────────────────────────

it('renders raw attributes on an input', function () {
    $html = repeater(fields: "[['key'=>'ref','attrs'=>['autocomplete'=>'off','maxlength'=>12]]]");

    expect($html)
        ->toContain('autocomplete="off"')
        ->toContain('maxlength="12"');
});

it('renders raw attributes on a select', function () {
    $html = repeater(fields: "[['key'=>'u','type'=>'select','options'=>['mg'=>'mg'],'attrs'=>['aria-label'=>'Unidad']]]");

    expect($html)->toContain('aria-label="Unidad"');
});

it('lets a field override the inputmode a decimal sets by default', function () {
    $html = repeater(fields: "[['key'=>'dose','type'=>'decimal','attrs'=>['inputmode'=>'numeric']]]");

    expect($html)
        ->toContain('inputmode="numeric"')
        ->not->toContain('inputmode="decimal"');
});

it('refuses an event handler smuggled in through attrs', function () {
    repeater(fields: "[['key'=>'a','attrs'=>['onclick'=>'evil()']]]");
})->throws(Illuminate\View\ViewException::class, 'event handler');

// ── the min/max expressions the buttons guard with ────────────────────────────

it('hides the remove button while at the minimum', function () {
    $html = repeater(':min="1"');

    expect($html)->toContain('x-show="1 === 0 || rows.length > 1"');
});

it('does not reference rows outside the Alpine scope in x-data', function () {
    // `x-data` is evaluated before its own object exists, so a bare `rows` in there is a
    // ReferenceError the moment the short-circuit stops hiding it (min or max > 0).
    $html = repeater(':min="1" :max="5"');

    $xData = str($html)->after('x-data="')->before('"')->toString();

    expect($xData)->not->toContain('canRemove')->not->toContain('canAdd');
});

// ── field defaults ────────────────────────────────────────────────────────────

it('starts the first row from the field defaults', function () {
    $html = repeater(fields: "[['key'=>'unit','type'=>'select','options'=>['mg'=>'mg'],'default'=>'mg']]");

    expect($html)->toContain('rows: JSON.parse(\'[{\u0022unit\u0022:\u0022mg\u0022}]\')');
});

it('gives an added row the same defaults as the first', function () {
    $html = repeater(fields: "[['key'=>'unit','type'=>'select','options'=>['mg'=>'mg'],'default'=>'mg']]");

    // add() pushes this literal, so row two arrives with the unit already chosen.
    expect($html)->toContain('this.rows.push(JSON.parse(\'{\u0022unit\u0022:\u0022mg\u0022}\'))');
});

it('still starts blank when no default is declared', function () {
    $html = repeater(fields: "[['key'=>'a']]");

    expect($html)->toContain('rows: JSON.parse(\'[{\u0022a\u0022:\u0022\u0022}]\')');
});
