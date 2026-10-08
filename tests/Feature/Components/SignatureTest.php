<?php

use Illuminate\Support\Facades\Blade;

it('signature renders canvas and hidden input with name', function () {
    $html = Blade::render('<x-dbl::form.signature name="firma" />');
    expect($html)->toContain('<canvas')->toContain('type="hidden"')->toContain('name="firma"')
        ->toContain('dbSignature(');
});

it('signature renders label, signer and description', function () {
    $html = Blade::render('<x-dbl::form.signature name="f" label="Firma" signer="Secretaría: Ana López" description="Acta 3" />');
    expect($html)->toContain('Firma')->toContain('Secretaría: Ana López')->toContain('Acta 3');
});

it('signature has big clear and undo buttons and the guide text', function () {
    app()->setLocale('es');
    $html = Blade::render('<x-dbl::form.signature name="f" />');
    expect($html)->toContain('btn-lg')->toContain('Borrar')->toContain('Deshacer')->toContain('Firme aquí');
});

it('signature is always white with dark ink, even in dark mode', function () {
    $html = Blade::render('<x-dbl::form.signature name="f" />');
    expect($html)->toContain('bg-white');
});

it('signature passes required and height', function () {
    $html = Blade::render('<x-dbl::form.signature name="f" :required="true" :height="300" />');
    expect($html)->toContain('u0022required\u0022:true')->toContain('height: 300px');
});

it('signature is accessible', function () {
    app()->setLocale('es');
    $html = Blade::render('<x-dbl::form.signature name="f" />');
    expect($html)->toContain('role="img"')->toContain('aria-describedby')->toContain('sr-only')
        ->toContain('dedo');
});

it('signature texts are translated', function () {
    app()->setLocale('en');
    $html = Blade::render('<x-dbl::form.signature name="f" />');
    expect($html)->toContain('Sign here')->toContain('Undo');
});

it('signature module has valid syntax', function () {
    exec('node --check '.escapeshellarg(dirname(__DIR__, 3).'/resources/js/components/signature.js').' 2>&1', $out, $code);
    expect($code)->toBe(0, implode("\n", $out));
});
