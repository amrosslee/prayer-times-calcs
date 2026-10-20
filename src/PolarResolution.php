<?php

declare(strict_types=1);

namespace GlobalPrayerTimes;

enum PolarResolution: string
{
    case Unresolved = 'unresolved';
    case NearestValidDate = 'nearest_valid_date';
    case NearestValidLatitude = 'nearest_valid_latitude';
}
