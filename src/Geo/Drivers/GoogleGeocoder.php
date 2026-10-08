<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Geo\Drivers;

use Edumicro\DaisyBlade\Geo\GeoResult;
use Edumicro\DaisyBlade\Geo\Geocoder;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/** Google Geocoding API (de pago pasado el cupo gratuito). Requiere una clave de servidor. */
final class GoogleGeocoder implements Geocoder
{
    public function __construct(
        private readonly string $key,
        private readonly string $baseUrl = 'https://maps.googleapis.com/maps/api/geocode/json',
        private readonly int $timeout = 5,
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('El geocodificador de Google necesita daisyblade.geo.google.key.');
        }
    }

    public function search(string $query, int $limit = 5): array
    {
        return array_slice($this->get(['address' => $query]), 0, $limit);
    }

    public function reverse(float $lat, float $lng): ?GeoResult
    {
        return $this->get(['latlng' => $lat.','.$lng])[0] ?? null;
    }

    /**
     * @param  array<string, string>  $params
     * @return list<GeoResult>
     */
    private function get(array $params): array
    {
        $json = Http::timeout($this->timeout)->acceptJson()
            ->get($this->baseUrl, $params + ['key' => $this->key])->throw()->json();

        if (($json['status'] ?? '') !== 'OK') {
            return []; // ZERO_RESULTS y compañía: sin resultados, no es un fallo.
        }

        return array_values(array_map(
            fn (array $r) => new GeoResult(
                (string) $r['formatted_address'],
                (float) $r['geometry']['location']['lat'],
                (float) $r['geometry']['location']['lng'],
            ),
            $json['results'],
        ));
    }
}
