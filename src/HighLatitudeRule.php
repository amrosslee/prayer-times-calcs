<?php

declare(strict_types=1);

namespace GlobalPrayerTimes;

enum HighLatitudeRule: string
{
    case MiddleOfTheNight = 'middle_of_the_night';
    case SeventhOfTheNight = 'seventh_of_the_night';
    case TwilightAngle = 'twilight_angle';

    public function portion(float $angle): float
    {
        return match ($this) {
            self::MiddleOfTheNight => 0.5,
            self::SeventhOfTheNight => 1.0 / 7.0,
            self::TwilightAngle => $angle / 60.0,
        };
    }
}
