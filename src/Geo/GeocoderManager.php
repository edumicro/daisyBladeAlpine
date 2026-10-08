<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Geo;

use Edumicro\DaisyBlade\Geo\Drivers\CartoCiudadGeocoder;
use Edumicro\DaisyBlade\Geo\Drivers\GoogleGeocoder;
use Edumicro\DaisyBlade\Geo\Drivers\NominatimGeocoder;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Manager;

/**
 * Elige el driver de `daisyblade.geo.driver`; `Geocoder::class` apunta aquí, así que se puede
 * inyectar sin saber cuál hay debajo. Un driver desconocido lanza InvalidArgumentException.
 */
final class GeocoderManager extends Manager implements Geocoder
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('daisyblade.geo.driver', 'cartociudad');
    }

    public function search(string $query, int $limit = 5): array
    {
        return $this->driver()->search($query, $limit);
    }

    public function reverse(float $lat, float $lng): ?GeoResult
    {
        return $this->driver()->reverse($lat, $lng);
    }

    protected function createCartociudadDriver(): Geocoder
    {
        return new CartoCiudadGeocoder(
            $this->container->make(HttpClient::class),
            (string) $this->config->get('daisyblade.geo.cartociudad.url', 'https://www.cartociudad.es/geocoder/api/geocoder'),
            (int) $this->config->get('daisyblade.geo.timeout', 5),
        );
    }

    protected function createNominatimDriver(): Geocoder
    {
        return new NominatimGeocoder(
            $this->container->make(HttpClient::class),
            $this->container->make(Cache::class),
            (string) $this->config->get('daisyblade.geo.nominatim.user_agent', 'DaisyBlade/2 (Laravel)'),
            (string) $this->config->get('daisyblade.geo.nominatim.url', 'https://nominatim.openstreetmap.org'),
            (float) $this->config->get('daisyblade.geo.nominatim.min_interval', 1.0),
            (int) $this->config->get('daisyblade.geo.timeout', 5),
        );
    }

    protected function createGoogleDriver(): Geocoder
    {
        return new GoogleGeocoder(
            $this->container->make(HttpClient::class),
            (string) $this->config->get('daisyblade.geo.google.key', ''),
            timeout: (int) $this->config->get('daisyblade.geo.timeout', 5),
        );
    }
}
