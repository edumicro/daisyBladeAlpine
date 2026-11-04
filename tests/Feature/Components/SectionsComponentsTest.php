<?php

use Illuminate\Support\Facades\Blade;

// ── sections/wizard ───────────────────────────────────────────────────────────

it('wizard renders step labels', function () {
    $html = Blade::render(
        '<x-dbl::sections.wizard :steps="$steps" submit-url="/x" />',
        ['steps' => [
            ['label' => 'Datos personales', 'schema' => []],
            ['label' => 'Confirmación', 'schema' => []],
        ]]
    );
    expect($html)->toContain('Datos personales')->toContain('Confirmación');
});

it('wizard has x-data with dblWizard', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" submit-url="/x" />');
    expect($html)->toContain('dblWizard');
});

it('wizard includes formId in config', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" submit-url="/x" form-id="registro" />');
    expect($html)->toContain('registro');
});

it('wizard has x-data attribute', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" submit-url="/x" />');
    expect($html)->toContain('x-data');
});

it('wizard has no wire: attributes', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" submit-url="/x" />');
    expect($html)->not->toContain('wire:');
});

it('wizard includes localStorage key construction', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" submit-url="/x" form-id="myform" />');
    expect($html)->toContain('myform');
});

it('wizard with empty steps renders without exception', function () {
    $html = Blade::render('<x-dbl::sections.wizard :steps="[]" submit-url="/x" />');
    expect($html)->not->toBeEmpty();
});

// ── sections/tabs ─────────────────────────────────────────────────────────────

it('sections tabs renders tab labels', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" />',
        ['tabs' => [
            ['label' => 'Información', 'load_url' => '/tab/1'],
            ['label' => 'Historial', 'load_url' => '/tab/2'],
        ]]
    );
    expect($html)->toContain('Información')->toContain('Historial');
});

it('sections tabs has x-data with dblTabsLazy', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" />',
        ['tabs' => [['label' => 'Tab', 'load_url' => '/x']]]
    );
    expect($html)->toContain('dblTabsLazy');
});

it('sections tabs includes load-urls in config', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" />',
        ['tabs' => [['label' => 'A', 'load_url' => '/api/panel/1']]]
    );
    expect($html)->toContain('/api/panel/1');
});

it('sections tabs has no wire: attributes', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" />',
        ['tabs' => [['label' => 'Tab', 'load_url' => '/x']]]
    );
    expect($html)->not->toContain('wire:');
});

it('sections tabs with active=1 sets initial active tab', function () {
    $html = Blade::render(
        '<x-dbl::sections.tabs :tabs="$tabs" :active="1" />',
        ['tabs' => [['label' => 'A', 'load_url' => '/x'], ['label' => 'B', 'load_url' => '/y']]]
    );
    expect($html)->toContain('active: 1');
});
