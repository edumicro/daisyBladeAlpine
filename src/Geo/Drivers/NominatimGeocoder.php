<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Geo\Drivers;

use Edumicro\DaisyBlade\Geo\GeoResult;
use Edumicro\DaisyBlade\Geo\Geocoder;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpClient;

/**
 * Nominatim (OpenStreetMap). La política de uso del servidor público exige un User-Agent que
 * identifique la aplicación y un máximo de 1 petición por segundo: aquí se respeta esperando
 * (`min_interval`, en segundos) desde la última petición. Para más volumen, instancia propia.
 */
final class NominatimGeocoder implements Geocoder
{
    private const LAST_CALL_KEY = 'daisyblade.geo.nominatim.last';

    public function __construct(
        private readonly HttpClient $http,
        private readonly Cache $cache,
        private readonly string $userAgent,
        private readonly string $baseUrl = 'https://nominatim.openstreetmap.org',
        private readonly float $minInterval = 1.0,
        private readonly int $timeout = 5,
    ) {}

    public function search(string $query, int $limit = 5): array
    {
        $rows = $this->get('search', ['q' => $query, 'format' => 'jsonv2', 'limit' => $limit]);

        return array_values(array_map(
            fn (array $r) => new GeoResult((string) $r['display_name'], (float) $r['lat'], (float) $r['lon']),
            array_filter(is_array($rows) ? $rows : [], fn ($r) => isset($r['lat'], $r['lon'], $r['display_name'])),
        ));
    }

    public function reverse(float $lat, float $lng): ?GeoResult
    {
        $r = $this->get('reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2']);

        return is_array($r) && isset($r['display_name'], $r['lat'], $r['lon'])
            ? new GeoResult((string) $r['display_name'], (float) $r['lat'], (float) $r['lon'])
            : null;
    }

    /** @param  array<string, mixed>  $params */
    private function get(string $endpoint, array $params): mixed
    {
        $this->throttle();

        return $this->http->withUserAgent($this->userAgent)->timeout($this->timeout)->acceptJson()
            ->get(rtrim($this->baseUrl, '/').'/'.$endpoint, $params)
            ->throw()->json();
    }

    private function throttle(): void
    {
        if ($this->minInterval <= 0) {
            return;
        }

        $wait = (float) $this->cache->get(self::LAST_CALL_KEY, 0) + $this->minInterval - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        $this->cache->put(self::LAST_CALL_KEY, microtime(true), 60);
    }
}
