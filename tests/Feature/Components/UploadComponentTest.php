<?php

use Illuminate\Support\Facades\Blade;

// ── form/upload ───────────────────────────────────────────────────────────────

it('upload renders a file input with label and the dbUpload factory', function () {
    $html = Blade::render('<x-dbl::form.upload name="acta" label="Acta firmada" action="/files" />');

    expect($html)->toContain('type="file"')
        ->toContain('Acta firmada')
        ->toContain('dbUpload(')
        ->toContain('/files')
        ->toContain('aria-live="polite"')
        ->not->toContain('wire:');
});

it('upload maps multiple, accept and capture to attributes', function () {
    $html = Blade::render('<x-dbl::form.upload name="f" action="/files" multiple accept="image/*,.pdf" capture="environment" />');

    expect($html)->toContain('multiple')
        ->toContain('accept="image/*,.pdf"')
        ->toContain('capture="environment"');
});

it('upload omits capture and accept when not given', function () {
    $html = Blade::render('<x-dbl::form.upload name="f" action="/files" />');

    expect($html)->not->toContain('capture=')->not->toContain('accept=');
});

it('upload passes limits and urls to the config', function () {
    app()->setLocale('es');
    $html = Blade::render('<x-dbl::form.upload name="f" action="/files" :max-size="5" :max-files="3" delete-url="/files/{id}" reorder-url="/files/order" />');

    expect($html)->toContain('maxSize')->toContain('maxFiles')
        ->toContain('Máximo 5 MB')->toContain('Hasta 3 ficheros')
        ->toContain('{id}')->toContain('order');
});

it('upload paints existing files in the config', function () {
    $existing = [
        ['id' => 7, 'name' => 'ticket.jpg', 'url' => '/f/7', 'thumb' => '/t/7', 'size' => 2048],
        ['id' => 8, 'name' => 'acta.pdf', 'url' => '/f/8', 'size' => 4096],
    ];
    $html = Blade::render('<x-dbl::form.upload name="f" action="/files" :existing="$existing" />', ['existing' => $existing]);

    expect($html)->toContain('ticket.jpg')->toContain('acta.pdf')->toContain('name="f[]"');
});

it('upload marks required with an asterisk and describes the field', function () {
    $html = Blade::render('<x-dbl::form.upload name="f" label="Fotos" action="/files" required description="Sube las fotos" />');

    expect($html)->toContain('text-error')->toContain('Sube las fotos')->toContain('aria-describedby="f-upload-desc"');
});

it('upload is translated', function () {
    app()->setLocale('en');
    expect(Blade::render('<x-dbl::form.upload name="f" action="/files" />'))->toContain('Choose files');

    app()->setLocale('ca');
    expect(Blade::render('<x-dbl::form.upload name="f" action="/files" />'))->toContain('Tria fitxers');
});

it('upload uses a camera button label when capture is set', function () {
    app()->setLocale('en');
    expect(Blade::render('<x-dbl::form.upload name="f" action="/files" capture="user" />'))->toContain('Take a photo');
});

it('upload javascript is syntactically valid', function () {
    exec('node --check '.escapeshellarg(__DIR__.'/../../../resources/js/components/upload.js').' 2>&1', $out, $code);
    expect($code)->toBe(0, implode("\n", $out));
});
