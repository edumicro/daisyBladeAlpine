<?php

use Edumicro\DaisyBlade\Geo\GeoResult;
use Edumicro\DaisyBlade\Geo\Geocoder;
use Edumicro\DaisyBlade\Geo\GeocoderManager;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('daisyblade.geo.nominatim.min_interval', 0);
    config()->set('daisyblade.geo.nominatim.user_agent', 'TestApp/1.0');
});

it('cartociudad strips the JSONP wrapper and resolves coordinates with findJsonp', function () {
    Http::fake([
        '*candidatesJsonp*' => Http::response('callback([{"id":"1","type":"poblacion","address":"Alfafar","poblacion":"Alfafar","province":"València/Valencia","lat":0.0,"lng":0.0}])'),
        '*findJsonp*' => Http::response('callback({"address":"Alfafar","poblacion":"Alfafar","province":"València/Valencia","lat":39.418,"lng":-0.386});'),
    ]);

    $results = app(GeocoderManager::class)->search('Alfafar', 3);

    expect($results)->toHaveCount(1)
        ->and($results[0])->toBeInstanceOf(GeoResult::class)
        ->and($results[0]->label)->toBe('Alfafar, València/Valencia')
        ->and($results[0]->lat)->toBe(39.418)
        ->and($results[0]->lng)->toBe(-0.386);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'candidatesJsonp') && $r['q'] === 'Alfafar' && $r['limit'] == 3);
});

it('cartociudad reverse accepts plain JSON and JSONP', function () {
    Http::fake(['*reverseGeocode*' => Http::response('{"tip_via":"CALLE","address":"MAESTRO BARRACHINA","portalNumber":46,"poblacion":"Alfafar","lat":39.4192,"lng":-0.3912}')]);

    $r = app(Geocoder::class)->reverse(39.4192, -0.3913);

    expect($r->label)->toBe('CALLE MAESTRO BARRACHINA 46, Alfafar')->and($r->lat)->toBe(39.4192);
    Http::assertSent(fn ($req) => $req['lon'] == -0.3913 && $req['lat'] == 39.4192);
});

it('cartociudad returns an empty list when nothing matches', function () {
    Http::fake(['*' => Http::response('callback([])')]);

    expect(app(GeocoderManager::class)->search('zzzz'))->toBe([]);
});

it('nominatim sends the configured User-Agent', function () {
    config()->set('daisyblade.geo.driver', 'nominatim');
    Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([
        ['display_name' => 'Valencia, España', 'lat' => '39.47', 'lon' => '-0.37'],
    ])]);

    $r = app(GeocoderManager::class)->search('Valencia');

    expect($r[0]->label)->toBe('Valencia, España')->and($r[0]->lng)->toBe(-0.37);
    Http::assertSent(fn ($req) => $req->header('User-Agent') === ['TestApp/1.0'] && $req['format'] === 'jsonv2');
});

it('nominatim reverse returns null on error payload', function () {
    config()->set('daisyblade.geo.driver', 'nominatim');
    Http::fake(['*' => Http::response(['error' => 'Unable to geocode'])]);

    expect(app(GeocoderManager::class)->reverse(0.0, 0.0))->toBeNull();
});

it('google geocodes and reverse geocodes with the key', function () {
    config()->set('daisyblade.geo.driver', 'google');
    config()->set('daisyblade.geo.google.key', 'SECRET');
    Http::fake(['maps.googleapis.com/*' => Http::response([
        'status' => 'OK',
        'results' => [['formatted_address' => 'Calle Mayor 1, Alfafar', 'geometry' => ['location' => ['lat' => 39.41, 'lng' => -0.38]]]],
    ])]);

    $manager = app(GeocoderManager::class);

    expect($manager->search('Calle Mayor 1')[0]->label)->toBe('Calle Mayor 1, Alfafar')
        ->and($manager->reverse(39.41, -0.38)->lat)->toBe(39.41);
    Http::assertSent(fn ($req) => $req['key'] === 'SECRET');
});

it('google without key fails fast', function () {
    config()->set('daisyblade.geo.driver', 'google');
    config()->set('daisyblade.geo.google.key', '');

    app(GeocoderManager::class)->search('x');
})->throws(InvalidArgumentException::class);

it('manager with an unknown driver throws', function () {
    config()->set('daisyblade.geo.driver', 'bing');

    app(GeocoderManager::class)->search('x');
})->throws(InvalidArgumentException::class, 'bing');

it('GeocodeController returns JSON and validates input', function () {
    Http::fake(['*' => Http::response('callback([{"address":"Alfafar","lat":39.4,"lng":-0.3}])')]);
    $route = fn () => app('router')->get('/geocode', Edumicro\DaisyBlade\Http\Controllers\GeocodeController::class);
    $route();

    $this->getJson('/geocode?q=Alfafar')->assertOk()->assertJsonPath('data.0.lat', 39.4);
    $this->getJson('/geocode')->assertStatus(422);
});
