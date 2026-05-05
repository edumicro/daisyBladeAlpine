<?php

use Illuminate\Support\Facades\Blade;

// ── import/spreadsheet ────────────────────────────────────────────────────────

it('spreadsheet has file input element', function () {
    $html = Blade::render('<x-dbl::import.spreadsheet upload-url="/import" />');
    expect($html)->toContain('type="file"');
});

it('spreadsheet includes upload-url in config', function () {
    $html = Blade::render('<x-dbl::import.spreadsheet upload-url="/api/import/products" />');
    expect($html)->toContain('/api/import/products');
});

it('spreadsheet with template-url shows download link', function () {
    $html = Blade::render('<x-dbl::import.spreadsheet upload-url="/import" template-url="/templates/products.xlsx" />');
    expect($html)->toContain('/templates/products.xlsx');
});

it('spreadsheet has x-data with dbSpreadsheetImport', function () {
    $html = Blade::render('<x-dbl::import.spreadsheet upload-url="/import" />');
    expect($html)->toContain('dbSpreadsheetImport')->toContain('x-data');
});

it('spreadsheet has no wire: attributes', function () {
    $html = Blade::render('<x-dbl::import.spreadsheet upload-url="/import" />');
    expect($html)->not->toContain('wire:');
});

it('spreadsheet with chunk-size includes it in config', function () {
    $html = Blade::render('<x-dbl::import.spreadsheet upload-url="/import" :chunk-size="500" />');
    expect($html)->toContain('500');
});

it('spreadsheet with label shows label heading', function () {
    $html = Blade::render('<x-dbl::import.spreadsheet upload-url="/import" label="Importar productos" />');
    expect($html)->toContain('Importar productos');
});
