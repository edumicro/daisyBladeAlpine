<?php

use Illuminate\Support\Facades\Blade;

/** Extrae y decodifica el JSON de data-config del componente. */
function mapConfig(string $html): array
{
    preg_match('/data-config="([^"]*)"/', $html, $m);

    return json_decode(html_entity_decode($m[1]), true, flags: JSON_THROW_ON_ERROR);
}

it('map renders with the defaults from config', function () {
    $html = Blade::render('<x-dbl::display.map />');
    $c = mapConfig($html);

    expect($html)->toContain('x-data="dbMap(')->toContain('height: 24rem')
        ->and($c['driver'])->toBe('maplibre')
        ->and($c['base'])->toBe('osm')
        ->and($c['picker'])->toBeFalse()
        ->and($html)->not->toContain('_lat');
});

it('map passes props through as config', function () {
    $html = Blade::render(<<<'BLADE'
        <x-dbl::display.map driver="google" base="ign-pnoa" :center="[39.42, -0.38]" :zoom="12" height="30rem"
            google-key="K" :layers="[['id' => 'ent', 'label' => 'Entidades', 'url' => '/ent.geojson', 'cluster' => true, 'color' => '#f00']]"
            :filters="['layers' => true, 'categories' => false]" />
    BLADE);
    $c = mapConfig($html);

    expect($c['driver'])->toBe('google')
        ->and($c['base'])->toBe('ign-pnoa')
        ->and($c['center'])->toBe([39.42, -0.38])
        ->and($c['zoom'])->toEqual(12)
        ->and($c['googleKey'])->toBe('K')
        ->and($c['filters'])->toBe(['layers' => true, 'categories' => false])
        ->and($c['layers'][0])->toMatchArray(['id' => 'ent', 'url' => '/ent.geojson', 'cluster' => true])
        ->and($html)->toContain('height: 30rem');
});

it('map in picker mode renders the hidden inputs and the search form', function () {
    $html = Blade::render('<x-dbl::display.map picker picker-name="sede" geocode-url="/geocode" :picker-value="[39.4, -0.3]" />');
    $c = mapConfig($html);

    expect($html)->toContain('name="sede_lat"')->toContain('name="sede_lng"')->toContain('role="search"')
        ->and($c['picker'])->toBeTrue()
        ->and($c['geocodeUrl'])->toBe('/geocode')
        ->and($c['pickerValue'])->toBe([39.4, -0.3]);
});

it('map falls back to safe values for unknown driver and unsafe height', function () {
    $html = Blade::render('<x-dbl::display.map driver="bing" height="1px;background:url(x)" />');

    expect(mapConfig($html)['driver'])->toBe('maplibre')->and($html)->toContain('height: 24rem');
});

it('map uses translated texts and aria-labels', function () {
    app()->setLocale('es');
    $html = Blade::render('<x-dbl::display.map picker geocode-url="/g" />');

    expect($html)->toContain('aria-label="Buscar dirección"')->toContain('aria-label="Mapa"');
});

it('map exposes the optional name and listens for the focus event', function () {
    $named = Blade::render('<x-dbl::display.map name="portal" />');
    $anon = Blade::render('<x-dbl::display.map />');

    expect(mapConfig($named)['name'])->toBe('portal')
        ->and(mapConfig($anon)['name'])->toBe('')
        ->and($named)->toContain('x-on:dbl-map-focus.window="onFocusEvent($event.detail)"')
        ->and($named)->not->toContain(' @dbl');
});

it('map keeps layers with per-point color and number untouched in the config', function () {
    $html = Blade::render(<<<'BLADE'
        <x-dbl::display.map name="m" :layers="[['id' => 'a', 'label' => 'A', 'url' => '/a.geojson', 'cluster' => true, 'color' => '#2563eb']]" />
    BLADE);
    $c = mapConfig($html);

    expect($c['layers'][0])->toMatchArray(['id' => 'a', 'cluster' => true, 'color' => '#2563eb'])
        ->and($c['driver'])->toBe('maplibre');
});
