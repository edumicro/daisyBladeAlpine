<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Geo;

interface Geocoder
{
    /** Busca direcciones o topónimos por texto libre. @return list<GeoResult> */
    public function search(string $query, int $limit = 5): array;

    /** Dirección más cercana a unas coordenadas, o null si no hay ninguna. */
    public function reverse(float $lat, float $lng): ?GeoResult;
}
