<?php

use Illuminate\Support\Facades\Blade;

// ── actions/button ────────────────────────────────────────────────────────────

it('button renders label', function () {
    $html = Blade::render('<x-dbl::actions.button label="Guardar" />');
    expect($html)->toContain('Guardar')->toContain('<button');
});

it('button with variant=btn-secondary has btn-secondary', function () {
    $html = Blade::render('<x-dbl::actions.button label="X" variant="btn-secondary" />');
    expect($html)->toContain('btn-secondary');
});

it('button with size=btn-sm has btn-sm', function () {
    $html = Blade::render('<x-dbl::actions.button label="X" size="btn-sm" />');
    expect($html)->toContain('btn-sm');
});

it('button with loading=true shows loading spinner', function () {
    $html = Blade::render('<x-dbl::actions.button label="X" :loading="true" />');
    expect($html)->toContain('loading');
});

it('button with disabled=true has disabled attribute', function () {
    $html = Blade::render('<x-dbl::actions.button label="X" :disabled="true" />');
    expect($html)->toContain('disabled');
});

it('button with href renders anchor tag', function () {
    $html = Blade::render('<x-dbl::actions.button label="Ir" href="/dashboard" />');
    expect($html)->toContain('<a')->toContain('/dashboard');
});

// ── actions/modal ─────────────────────────────────────────────────────────────

it('modal renders title', function () {
    $html = Blade::render('<x-dbl::actions.modal title="Confirmar acción" />');
    expect($html)->toContain('Confirmar acción');
});

it('modal renders slot content', function () {
    $html = Blade::render('<x-dbl::actions.modal title="T">Contenido del modal</x-dbl::actions.modal>');
    expect($html)->toContain('Contenido del modal');
});

it('modal has x-data with dbModal', function () {
    $html = Blade::render('<x-dbl::actions.modal title="T" />');
    expect($html)->toContain('x-data')->toContain('dbModal');
});

it('modal has no wire: attributes', function () {
    $html = Blade::render('<x-dbl::actions.modal title="T" />');
    expect($html)->not->toContain('wire:');
});

it('modal with closable=false has no close button', function () {
    $html = Blade::render('<x-dbl::actions.modal title="T" :closable="false" />');
    expect($html)->not->toContain('btn-circle');
});

// ── actions/fab ───────────────────────────────────────────────────────────────

it('fab renders btn-circle class', function () {
    $html = Blade::render('<x-dbl::actions.fab />');
    expect($html)->toContain('btn-circle');
});

it('fab with href renders anchor tag', function () {
    $html = Blade::render('<x-dbl::actions.fab href="/create" />');
    expect($html)->toContain('<a')->toContain('/create');
});

it('fab with slot content has x-data for speed-dial', function () {
    $html = Blade::render(
        '<x-dbl::actions.fab><button>Opción 1</button></x-dbl::actions.fab>'
    );
    expect($html)->toContain('x-data');
});

it('fab has no wire: attributes', function () {
    $html = Blade::render('<x-dbl::actions.fab />');
    expect($html)->not->toContain('wire:');
});
