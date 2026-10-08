<?php

declare(strict_types=1);

namespace Edumicro\DaisyBlade\Geo;

/** Un punto geocodificado: lo único que los tres drivers tienen en común. */
final readonly class GeoResult
{
    public function __construct(
        public string $label,
        public float $lat,
        public float $lng,
    ) {}

    /** @return array{label: string, lat: float, lng: float} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'lat' => $this->lat, 'lng' => $this->lng];
    }
}
