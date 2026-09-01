<?php

use Illuminate\Support\Facades\Blade;

// ── form/input ────────────────────────────────────────────────────────────────

it('input renders with name attribute', function () {
    $html = Blade::render('<x-dbl::form.input name="email" />');
    expect($html)->toContain('name="email"')->toContain('<input');
});

it('input renders with type=email', function () {
    $html = Blade::render('<x-dbl::form.input name="email" type="email" />');
    expect($html)->toContain('type="email"');
});

it('input renders label', function () {
    $html = Blade::render('<x-dbl::form.input name="x" label="Correo electrónico" />');
    expect($html)->toContain('Correo electrónico');
});

it('input with required=true has required attribute', function () {
    $html = Blade::render('<x-dbl::form.input name="x" :required="true" />');
    expect($html)->toContain('required');
});

it('input renders placeholder', function () {
    $html = Blade::render('<x-dbl::form.input name="x" placeholder="Escribe aquí" />');
    expect($html)->toContain('Escribe aquí');
});

it('input with disabled=true has disabled attribute', function () {
    $html = Blade::render('<x-dbl::form.input name="x" :disabled="true" />');
    expect($html)->toContain('disabled');
});

it('input with value prop shows value', function () {
    $html = Blade::render('<x-dbl::form.input name="x" :value="\'valor inicial\'" />');
    expect($html)->toContain('valor inicial');
});

it('input with error prop shows error message and input-error class', function () {
    $html = Blade::render('<x-dbl::form.input name="x" error="Este campo es obligatorio" />');
    expect($html)->toContain('Este campo es obligatorio')->toContain('input-error');
});

it('input with hint prop shows hint text', function () {
    $html = Blade::render('<x-dbl::form.input name="x" hint="Máximo 50 caracteres" />');
    expect($html)->toContain('Máximo 50 caracteres');
});

// ── form/textarea ─────────────────────────────────────────────────────────────

it('textarea renders textarea element with name', function () {
    $html = Blade::render('<x-dbl::form.textarea name="description" />');
    expect($html)->toContain('<textarea')->toContain('name="description"');
});

it('textarea with rows=5 has rows attribute', function () {
    $html = Blade::render('<x-dbl::form.textarea name="x" :rows="5" />');
    expect($html)->toContain('rows="5"');
});

it('textarea renders label', function () {
    $html = Blade::render('<x-dbl::form.textarea name="x" label="Descripción" />');
    expect($html)->toContain('Descripción');
});

// ── form/select ───────────────────────────────────────────────────────────────

it('select renders select element with options', function () {
    $html = Blade::render(
        '<x-dbl::form.select name="category" :options="$opts" />',
        ['opts' => ['1' => 'Cat A', '2' => 'Cat B']]
    );
    expect($html)->toContain('<select')->toContain('Cat A')->toContain('Cat B')->toContain('value="1"');
});

it('select renders placeholder', function () {
    $html = Blade::render('<x-dbl::form.select name="x" :options="[]" placeholder="Selecciona..." />');
    expect($html)->toContain('Selecciona...');
});

it('select with options-url uses Alpine remote factory', function () {
    $html = Blade::render('<x-dbl::form.select name="x" :options="[]" options-url="/api/categories" />');
    expect($html)->toContain('dbSelectRemote')->toContain('/api/categories')->toContain('x-data');
});

it('select with multiple=true has multiple attribute', function () {
    $html = Blade::render('<x-dbl::form.select name="x" :options="[]" :multiple="true" />');
    expect($html)->toContain('multiple');
});

// ── form/checkbox ─────────────────────────────────────────────────────────────

it('checkbox renders input type checkbox with label', function () {
    $html = Blade::render('<x-dbl::form.checkbox name="active" label="Activo" />');
    expect($html)->toContain('type="checkbox"')->toContain('Activo');
});

it('checkbox with checked=true has checked attribute', function () {
    $html = Blade::render('<x-dbl::form.checkbox name="x" :checked="true" />');
    expect($html)->toContain('checked');
});

it('checkbox with disabled=true has disabled attribute', function () {
    $html = Blade::render('<x-dbl::form.checkbox name="x" :disabled="true" />');
    expect($html)->toContain('disabled');
});

// ── form/toggle ───────────────────────────────────────────────────────────────

it('toggle renders toggle class with label', function () {
    $html = Blade::render('<x-dbl::form.toggle name="enabled" label="Habilitado" />');
    expect($html)->toContain('toggle')->toContain('Habilitado');
});

it('toggle with checked=true has checked attribute', function () {
    $html = Blade::render('<x-dbl::form.toggle name="x" :checked="true" />');
    expect($html)->toContain('checked');
});

// ── form/radio ────────────────────────────────────────────────────────────────

it('radio renders two radio inputs with labels', function () {
    $html = Blade::render(
        '<x-dbl::form.radio name="status" :options="$opts" />',
        ['opts' => ['active' => 'Activo', 'inactive' => 'Inactivo']]
    );
    $count = substr_count($html, 'type="radio"');
    expect($count)->toBe(2)->and($html)->toContain('Activo')->toContain('Inactivo');
});

it('radio with value=active marks that option checked', function () {
    $html = Blade::render(
        '<x-dbl::form.radio name="x" :options="$opts" :value="\'active\'" />',
        ['opts' => ['active' => 'Activo', 'inactive' => 'Inactivo']]
    );
    // The checked option should come before "inactive" in the HTML
    expect($html)->toContain('checked');
});

// ── form/auto-form ────────────────────────────────────────────────────────────

it('auto-form renders form with schema, factory and action', function () {
    $html = Blade::render(
        '<x-dbl::sections.auto-form :fields="$fields" action="/products" />',
        ['fields' => ['title' => ['type' => 'text', 'label' => 'Título']]]
    );
    expect($html)
        ->toContain('Título')
        ->toContain('dbAutoForm')
        ->toContain('/products')
        ->not->toContain('wire:');
});

it('auto-form with money field renders number input', function () {
    $html = Blade::render(
        '<x-dbl::sections.auto-form :fields="$fields" action="/x" />',
        ['fields' => ['price' => ['type' => 'money', 'label' => 'Precio']]]
    );
    expect($html)->toContain('number');
});

it('auto-form with method=PUT includes method info', function () {
    $html = Blade::render('<x-dbl::sections.auto-form :fields="[]" action="/x" method="PUT" />');
    expect($html)->toContain('PUT');
});

it('auto-form with empty schema renders without exception', function () {
    $html = Blade::render('<x-dbl::sections.auto-form :fields="[]" action="/x" />');
    expect($html)->not->toBeEmpty();
});

// ── form/validator ────────────────────────────────────────────────────────────

it('validator shows message when provided', function () {
    $html = Blade::render('<x-dbl::form.validator field="email" message="El email no es válido" />');
    expect($html)->toContain('El email no es válido');
});

it('validator hides when message is empty', function () {
    $html = Blade::render('<x-dbl::form.validator field="email" message="" />');
    expect(trim($html))->toBeEmpty();
});

// ── form/filter ───────────────────────────────────────────────────────────────

it('filter renders field labels and apply-url', function () {
    $html = Blade::render(
        '<x-dbl::form.filter :fields="$fields" apply-url="/products" />',
        ['fields' => [['name' => 'status', 'type' => 'select', 'label' => 'Estado', 'options' => []]]]
    );
    expect($html)->toContain('Estado')->toContain('/products');
});

// ── form/fields: decimal + attrs (schema-driven, same contract as the repeater) ─

it('fields renders a decimal as text with a numeric keypad', function () {
    $html = Blade::render(
        '<x-dbl::sections.auto-form :fields="$fields" action="/x" />',
        ['fields' => ['dose' => ['type' => 'decimal', 'label' => 'Dosis']]]
    );

    expect($html)->toContain('inputmode="decimal"')->toContain('type="text"');
});

it('fields renders raw attrs on an input', function () {
    $html = Blade::render(
        '<x-dbl::sections.auto-form :fields="$fields" action="/x" />',
        ['fields' => ['ref' => ['attrs' => ['autocomplete' => 'off']]]]
    );

    expect($html)->toContain('autocomplete="off"');
});

it('fields renders raw attrs on a select', function () {
    $html = Blade::render(
        '<x-dbl::sections.auto-form :fields="$fields" action="/x" />',
        ['fields' => ['u' => ['type' => 'select', 'options' => ['mg' => 'mg'], 'attrs' => ['aria-label' => 'Unidad']]]]
    );

    expect($html)->toContain('aria-label="Unidad"');
});

it('fields passes events down to a nested repeater', function () {
    $html = Blade::render(
        '<x-dbl::sections.auto-form :fields="$fields" action="/x" />',
        ['fields' => ['items' => [
            'type'   => 'repeater',
            'fields' => [['key' => 'a']],
            'events' => ['root' => ['keydown.enter' => 'add()']],
        ]]]
    );

    expect($html)->toContain('x-on:keydown.enter="add()"');
});

// ── regresion: el formulario sin valores iniciales enviaba el payload vacio ───
//
// En PHP un array asociativo vacio se serializa como `[]`, no como `{}`. Alpine escribia
// `values.email` sobre ese array sin protestar, pero `JSON.stringify` descarta las propiedades
// con nombre de un array, asi que axios mandaba `[]` y el servidor respondia 422 "campo
// obligatorio". Afectaba a TODOS los formularios de alta, el de login incluido.

it('auto-form seeds values as an object so an empty form still submits its fields', function () {
    $html = Blade::render(
        '<x-dbl::sections.auto-form :fields="$fields" action="/login" />',
        ['fields' => ['email' => ['type' => 'email'], 'password' => ['type' => 'password']]],
    );

    expect($html)->toContain('values: {}')
        ->not->toContain('values: []');
});

it('auto-form keeps the values it is given', function () {
    $html = Blade::render(
        '<x-dbl::sections.auto-form :fields="$fields" :values="$values" action="/x" />',
        ['fields' => ['email' => ['type' => 'email']], 'values' => ['email' => 'ana@example.com']],
    );

    expect($html)->toContain('ana@example.com');
});
