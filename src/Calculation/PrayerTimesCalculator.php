<?php

declare(strict_types=1);

namespace GlobalPrayerTimes\Calculation;

use DateTimeImmutable;
use DateTimeZone;
use GlobalPrayerTimes\CalculationSettings;
use GlobalPrayerTimes\Coordinates;
use GlobalPrayerTimes\LocalDate;
use GlobalPrayerTimes\Prayer;
use GlobalPrayerTimes\PrayerTimes;
use InvalidArgumentException;

final class PrayerTimesCalculator
{
    public function calculate(
        LocalDate $date,
        Coordinates $coordinates,
        string $timeZone,
        CalculationSettings $settings,
    ): PrayerTimes {
        $zone = $this->timeZone($timeZone);
        $astronomicalDate = $this->utcDateContainingLocalNoon($date, $zone);
        $utcTimes = (new SolarCalculator())->calculate($astronomicalDate, $coordinates, $settings);

        $localTimes = [];
        foreach (Prayer::cases() as $prayer) {
            $timestamp = $utcTimes[$prayer->value]->getTimestamp();
            $timestamp += $settings->adjustmentFor($prayer) * 60;
            $localTimes[$prayer->value] = (new DateTimeImmutable('@' . $timestamp))->setTimezone($zone);
        }

        return new PrayerTimes(
            $localTimes[Prayer::Fajr->value],
            $localTimes[Prayer::Sunrise->value],
            $localTimes[Prayer::Dhuhr->value],
            $localTimes[Prayer::Asr->value],
            $localTimes[Prayer::Maghrib->value],
            $localTimes[Prayer::Isha->value],
        );
    }

    private function timeZone(string $timeZone): DateTimeZone
    {
        if (!in_array($timeZone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException('Timezone must be a valid IANA timezone identifier.');
        }

        return new DateTimeZone($timeZone);
    }

    private function utcDateContainingLocalNoon(LocalDate $date, DateTimeZone $timeZone): LocalDate
    {
        $localNoon = new DateTimeImmutable($date->toString() . ' 12:00:00', $timeZone);
        $utcDate = $localNoon->setTimezone(new DateTimeZone('UTC'));

        return LocalDate::fromString($utcDate->format('Y-m-d'));
    }
}
