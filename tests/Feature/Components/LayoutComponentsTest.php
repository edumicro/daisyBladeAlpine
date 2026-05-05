<?php

use Illuminate\Support\Facades\Blade;

beforeEach(function () {
    $this->withoutVite();
});

// ── navigation/sidebar ────────────────────────────────────────────────────────

it('sidebar renders without exception', function () {
    $html = Blade::render('<x-dbl::navigation.sidebar />');
    expect($html)->not->toBeEmpty();
});

it('sidebar has aside element', function () {
    $html = Blade::render('<x-dbl::navigation.sidebar />');
    expect($html)->toContain('<aside');
});

it('sidebar has x-data with dbSidebar', function () {
    $html = Blade::render('<x-dbl::navigation.sidebar />');
    expect($html)->toContain('dbSidebar');
});

it('sidebar has no wire: attributes', function () {
    $html = Blade::render('<x-dbl::navigation.sidebar />');
    expect($html)->not->toContain('wire:');
});

it('sidebar renders slot content', function () {
    $html = Blade::render('<x-dbl::navigation.sidebar>Menú lateral</x-dbl::navigation.sidebar>');
    expect($html)->toContain('Menú lateral');
});

// ── layout/app ────────────────────────────────────────────────────────────────

it('app layout renders html element', function () {
    $html = Blade::render('<x-dbl::layout.app title="Mi App" />');
    expect($html)->toContain('<html');
});

it('app layout renders main element', function () {
    $html = Blade::render('<x-dbl::layout.app />');
    expect($html)->toContain('<main');
});

it('app layout has no wire: attributes', function () {
    $html = Blade::render('<x-dbl::layout.app />');
    expect($html)->not->toContain('wire:')->not->toContain('livewire');
});

it('app layout renders slot content', function () {
    $html = Blade::render('<x-dbl::layout.app>Contenido principal</x-dbl::layout.app>');
    expect($html)->toContain('Contenido principal');
});

it('app layout with title shows title in head', function () {
    $html = Blade::render('<x-dbl::layout.app title="Panel de control" />');
    expect($html)->toContain('Panel de control');
});
