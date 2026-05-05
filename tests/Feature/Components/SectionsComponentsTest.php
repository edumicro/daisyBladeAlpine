<?php

use Illuminate\Support\Facades\Blade;

// ── sections/wizard ───────────────────────────────────────────────────────────

it('wizard renders step labels', function () {
    $html = Blade::render(
        '<x-dbl::sections.wizard :steps="$steps" action="/x" />',
        ['steps' => [
            ['label' => 'Datos personales', 'schema' => []],
            ['label' => 'Confirmación', 'schema' => []],
        ]]
    );
    expect($html)->toContain('Datos personales')->toContain('Confirmación');
});

it('wizard has x-data with dbWizard', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" action="/x" />');
    expect($html)->toContain('dbWizard');
});

it('wizard includes formId in config', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" action="/x" form-id="registro" />');
    expect($html)->toContain('registro');
});

it('wizard has x-data attribute', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" action="/x" />');
    expect($html)->toContain('x-data');
});

it('wizard has no wire: attributes', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" action="/x" />');
    expect($html)->not->toContain('wire:');
});

it('wizard includes localStorage key construction', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" action="/x" form-id="myform" />');
    expect($html)->toContain('myform');
});

it('wizard with empty steps renders without exception', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" action="/x" />');
    expect($html)->not->toBeEmpty();
});

// ── sections/tabs ─────────────────────────────────────────────────────────────

it('sections tabs renders tab labels', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" />',
        ['tabs' => [
            ['label' => 'Información', 'load-url' => '/tab/1'],
            ['label' => 'Historial', 'load-url' => '/tab/2'],
        ]]
    );
    expect($html)->toContain('Información')->toContain('Historial');
});

it('sections tabs has x-data with dbTabsLazy', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" />',
        ['tabs' => [['label' => 'Tab', 'load-url' => '/x']]]
    );
    expect($html)->toContain('dbTabsLazy');
});

it('sections tabs includes load-urls in config', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" />',
        ['tabs' => [['label' => 'A', 'load-url' => '/api/panel/1']]]
    );
    expect($html)->toContain('/api/panel/1');
});

it('sections tabs has no wire: attributes', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" />',
        ['tabs' => [['label' => 'Tab', 'load-url' => '/x']]]
    );
    expect($html)->not->toContain('wire:');
});

it('sections tabs with active=1 sets initial active tab', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" :active="1" />',
        ['tabs' => [['label' => 'A', 'load-url' => '/x'], ['label' => 'B', 'load-url' => '/y']]]
    );
    expect($html)->toContain('active: 1');
});
