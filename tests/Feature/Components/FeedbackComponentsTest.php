<?php

use Illuminate\Support\Facades\Blade;

// ── feedback/toast ────────────────────────────────────────────────────────────

it('toast with message shows static alert', function () {
    $html = Blade::render('<x-dbl::feedback.toast message="Guardado correctamente" type="success" />');
    expect($html)->toContain('Guardado correctamente')->toContain('alert-success');
});

it('toast with type=error shows alert-error', function () {
    $html = Blade::render('<x-dbl::feedback.toast message="Error" type="error" />');
    expect($html)->toContain('alert-error');
});

it('toast with message has x-data', function () {
    $html = Blade::render('<x-dbl::feedback.toast message="Hola" />');
    expect($html)->toContain('x-data');
});

it('toast with autoClose=true includes duration in config', function () {
    $html = Blade::render('<x-dbl::feedback.toast message="X" :autoClose="true" :duration="3000" />');
    expect($html)->toContain('3000');
});

it('toast without message uses event-driven mode with dbToast', function () {
    $html = Blade::render('<x-dbl::feedback.toast />');
    expect($html)->toContain('dbToast');
});

it('toast without message listens to notify event', function () {
    $html = Blade::render('<x-dbl::feedback.toast />');
    expect($html)->toContain('notify');
});

// ── feedback/skeleton ─────────────────────────────────────────────────────────

it('skeleton renders skeleton class', function () {
    $html = Blade::render('<x-dbl::feedback.skeleton />');
    expect($html)->toContain('skeleton');
});

it('skeleton with type=text renders multiple lines', function () {
    $html = Blade::render('<x-dbl::feedback.skeleton type="text" :lines="4" />');
    $count = substr_count($html, 'skeleton');
    expect($count)->toBeGreaterThanOrEqual(4);
});

it('skeleton with animated=false has no animate-pulse', function () {
    $html = Blade::render('<x-dbl::feedback.skeleton :animated="false" />');
    expect($html)->not->toContain('animate-pulse');
});

it('skeleton with animated=true has animate-pulse', function () {
    $html = Blade::render('<x-dbl::feedback.skeleton :animated="true" />');
    expect($html)->toContain('animate-pulse');
});

it('skeleton with type=avatar renders rounded-full', function () {
    $html = Blade::render('<x-dbl::feedback.skeleton type="avatar" />');
    expect($html)->toContain('rounded-full');
});

// ── feedback/progress ─────────────────────────────────────────────────────────

it('progress renders progress element', function () {
    $html = Blade::render('<x-dbl::feedback.progress :value="60" />');
    expect($html)->toContain('<progress');
});

it('progress with value=60 contains value attribute', function () {
    $html = Blade::render('<x-dbl::feedback.progress :value="60" />');
    expect($html)->toContain('60');
});

it('progress with label shows label text', function () {
    $html = Blade::render('<x-dbl::feedback.progress :value="40" label="Cargando..." />');
    expect($html)->toContain('Cargando...');
});

it('progress with showPercent=true shows percentage', function () {
    $html = Blade::render('<x-dbl::feedback.progress :value="75" :max="100" :showPercent="true" :label="\'Progreso\'" />');
    expect($html)->toContain('75%');
});

it('progress with color=success has progress-success', function () {
    $html = Blade::render('<x-dbl::feedback.progress :value="50" color="success" />');
    expect($html)->toContain('progress-success');
});

// ── feedback/loading ──────────────────────────────────────────────────────────

it('loading renders loading class', function () {
    $html = Blade::render('<x-dbl::feedback.loading />');
    expect($html)->toContain('loading');
});

it('loading with size=lg has loading-lg', function () {
    $html = Blade::render('<x-dbl::feedback.loading size="lg" />');
    expect($html)->toContain('loading-lg');
});

it('loading with type=dots has loading-dots', function () {
    $html = Blade::render('<x-dbl::feedback.loading type="dots" />');
    expect($html)->toContain('loading-dots');
});

it('loading with type=spinner has loading-spinner', function () {
    $html = Blade::render('<x-dbl::feedback.loading type="spinner" />');
    expect($html)->toContain('loading-spinner');
});

it('loading with message and showMessage=true shows message', function () {
    $html = Blade::render('<x-dbl::feedback.loading message="Cargando datos..." :showMessage="true" />');
    expect($html)->toContain('Cargando datos...');
});
