<?php

declare(strict_types=1);

namespace GlobalPrayerTimes;

enum Prayer: string
{
    case Fajr = 'fajr';
    case Sunrise = 'sunrise';
    case Dhuhr = 'dhuhr';
    case Asr = 'asr';
    case Maghrib = 'maghrib';
    case Isha = 'isha';
}
