<?php

declare(strict_types=1);

namespace GlobalPrayerTimes;

use InvalidArgumentException;

final readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if (!is_finite($latitude) || $latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException('Latitude must be a finite value from -90 to 90 degrees.');
        }

        if (!is_finite($longitude) || $longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException('Longitude must be a finite value from -180 to 180 degrees.');
        }
    }
}
