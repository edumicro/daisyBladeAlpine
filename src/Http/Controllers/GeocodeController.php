<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Http\Controllers;

use Edumicro\DaisyBlade\Geo\GeoResult;
use Edumicro\DaisyBlade\Geo\Geocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

/**
 * Endpoint opcional para el buscador del mapa. No se registra solo: la app elige ruta y
 * middleware (ver docs/components/map.md).
 *
 *   GET ?q=calle mayor 1       → {"data": [{label, lat, lng}, …]}
 *   GET ?lat=39.4&lng=-0.38    → {"data": [{label, lat, lng}]}  (geocodificación inversa)
 */
final class GeocodeController
{
    public function __invoke(Request $request, Geocoder $geocoder): JsonResponse
    {
        $input = $request->validate([
            'q'   => ['nullable', 'string', 'min:2', 'max:200', 'required_without:lat'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ]);

        try {
            $results = isset($input['lat'])
                ? array_filter([$geocoder->reverse((float) $input['lat'], (float) $input['lng'])])
                : $geocoder->search($input['q']);
        } catch (RequestException) {
            return response()->json(['message' => 'Geocoder unavailable'], 502);
        }

        return response()->json(['data' => array_map(fn (GeoResult $r) => $r->toArray(), array_values($results))]);
    }
}
