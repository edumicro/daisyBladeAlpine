<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Geo\Drivers;

use Edumicro\DaisyBlade\Geo\GeoResult;
use Edumicro\DaisyBlade\Geo\Geocoder;
use Illuminate\Support\Facades\Http;

/**
 * CartoCiudad (IGN / Correos). Gratuito y sin clave, solo España.
 *
 * `candidatesJsonp` devuelve candidatos SIN coordenadas (lat/lng = 0); `findJsonp` resuelve
 * cada uno. Las variantes «Jsonp» envuelven el JSON en `callback(...)`, que hay que quitar.
 */
final class CartoCiudadGeocoder implements Geocoder
{
    public function __construct(
        private readonly string $baseUrl = 'https://www.cartociudad.es/geocoder/api/geocoder',
        private readonly int $timeout = 5,
    ) {}

    public function search(string $query, int $limit = 5): array
    {
        $candidates = $this->get('candidatesJsonp', ['q' => $query, 'limit' => $limit]);
        $results = [];

        foreach (array_slice(is_array($candidates) ? $candidates : [], 0, $limit) as $c) {
            if (! is_array($c)) {
                continue;
            }
            // Sin coordenadas en el candidato: se piden a findJsonp con su id y tipo.
            if (empty($c['lat']) || empty($c['lng'])) {
                $c = $this->get('findJsonp', [
                    'q' => $c['address'] ?? $query, 'type' => $c['type'] ?? '', 'id' => $c['id'] ?? '',
                ]);
            }
            if (is_array($c) && ! empty($c['lat']) && ! empty($c['lng'])) {
                $results[] = $this->toResult($c);
            }
        }

        return $results;
    }

    public function reverse(float $lat, float $lng): ?GeoResult
    {
        $r = $this->get('reverseGeocode', ['lon' => $lng, 'lat' => $lat]);

        if (! is_array($r) || empty($r['address'])) {
            return null;
        }

        // reverseGeocode a veces no trae coordenadas propias: se conservan las pedidas.
        $r['lat'] = empty($r['lat']) ? $lat : $r['lat'];
        $r['lng'] = empty($r['lng']) ? $lng : $r['lng'];

        return $this->toResult($r);
    }

    /** @param  array<string, mixed>  $c */
    private function toResult(array $c): GeoResult
    {
        $parts = array_filter([
            trim(($c['tip_via'] ?? '').' '.($c['address'] ?? '').' '.($c['portalNumber'] ?? '')),
            $c['poblacion'] ?? $c['muni'] ?? null,
            $c['province'] ?? null,
        ]);

        return new GeoResult(implode(', ', array_unique($parts)), (float) $c['lat'], (float) $c['lng']);
    }

    /** @param  array<string, mixed>  $params */
    private function get(string $endpoint, array $params): mixed
    {
        $body = Http::timeout($this->timeout)->acceptJson()
            ->get(rtrim($this->baseUrl, '/').'/'.$endpoint, $params)
            ->throw()->body();

        // Quita el envoltorio JSONP `callback( ... );` si lo hay.
        if (preg_match('/^\s*[\w.$]*\((.*)\)\s*;?\s*$/s', $body, $m)) {
            $body = $m[1];
        }

        return json_decode($body, true);
    }
}
