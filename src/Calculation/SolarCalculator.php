<?php

declare(strict_types=1);

namespace GlobalPrayerTimes\Calculation;

use DateTimeImmutable;
use DateTimeZone;
use GlobalPrayerTimes\CalculationException;
use GlobalPrayerTimes\CalculationSettings;
use GlobalPrayerTimes\Coordinates;
use GlobalPrayerTimes\LocalDate;
use GlobalPrayerTimes\PolarResolution;
use GlobalPrayerTimes\Prayer;
use LogicException;

/**
 * @phpstan-type SolarCoordinates array{declination: float, rightAscension: float, sidereal: float}
 * @phpstan-type SolarDay array{
 *     solar: SolarCoordinates,
 *     previousSolar: SolarCoordinates,
 *     nextSolar: SolarCoordinates,
 *     approximateTransit: float,
 *     transit: float,
 *     sunrise: float|null,
 *     sunset: float|null
 * }
 * @phpstan-type SolarContext array{
 *     date: LocalDate,
 *     coordinates: Coordinates,
 *     solar: SolarDay,
 *     tomorrowSolar: SolarDay
 * }
 */
final class SolarCalculator
{
    private const SUNRISE_ALTITUDE = -50.0 / 60.0;

    /**
     * @return array<string, DateTimeImmutable>
     */
    public function calculate(
        LocalDate $date,
        Coordinates $coordinates,
        CalculationSettings $settings,
    ): array {
        $context = $this->resolveSolarContext($date, $coordinates, $settings->polarResolution);
        $effectiveDate = $context['date'];
        $effectiveCoordinates = $context['coordinates'];
        $solarDay = $context['solar'];
        $tomorrowSolarDay = $context['tomorrowSolar'];

        $sunrise = $solarDay['sunrise'];
        $sunset = $solarDay['sunset'];
        if ($sunrise === null || $sunset === null || $tomorrowSolarDay['sunrise'] === null) {
            throw new CalculationException('Sunrise or sunset is unavailable after applying polar resolution.');
        }

        $nightDuration = $this->utcTimestamp($this->addDays($effectiveDate, 1), $tomorrowSolarDay['sunrise'])
            - $this->utcTimestamp($effectiveDate, $sunset);
        if ($nightDuration <= 0) {
            throw new CalculationException('The calculated night duration is not positive.');
        }

        $fajr = $this->tryCorrectedHourAngle(
            $solarDay,
            -$settings->fajrAngle,
            $effectiveCoordinates,
            false,
        );
        $fajrNightPortion = $settings->highLatitudeRule->portion($settings->fajrAngle);
        $safeFajr = $sunrise - ($nightDuration * $fajrNightPortion / 3600.0);
        if ($fajr === null || $safeFajr > $fajr) {
            $fajr = $safeFajr;
        }

        $asrShadowLength = $settings->asrRule->value;
        $tangent = abs($effectiveCoordinates->latitude - $solarDay['solar']['declination']);
        $asrAltitude = $this->radiansToDegrees(atan(1.0 / (
            $asrShadowLength + tan($this->degreesToRadians($tangent))
        )));
        $asr = $this->requiredCorrectedHourAngle(
            $solarDay,
            $asrAltitude,
            $effectiveCoordinates,
            true,
            'Asr',
        );

        if ($settings->ishaIntervalMinutes !== null) {
            $isha = $sunset + ($settings->ishaIntervalMinutes / 60.0);
        } elseif ($settings->ishaAngle !== null) {
            $isha = $this->tryCorrectedHourAngle(
                $solarDay,
                -$settings->ishaAngle,
                $effectiveCoordinates,
                true,
            );
            $ishaNightPortion = $settings->highLatitudeRule->portion($settings->ishaAngle);
            $safeIsha = $sunset + ($nightDuration * $ishaNightPortion / 3600.0);
            if ($isha === null || $safeIsha < $isha) {
                $isha = $safeIsha;
            }
        } else {
            throw new LogicException('Calculation settings must provide an Isha angle or interval.');
        }

        $maghrib = $sunset;
        if ($settings->maghribAngle !== null) {
            $angleBasedMaghrib = $this->tryCorrectedHourAngle(
                $solarDay,
                -$settings->maghribAngle,
                $effectiveCoordinates,
                true,
            );

            if ($angleBasedMaghrib !== null && $sunset < $angleBasedMaghrib && $isha > $angleBasedMaghrib) {
                $maghrib = $angleBasedMaghrib;
            }
        }

        $hours = [
            Prayer::Fajr->value => $fajr,
            Prayer::Sunrise->value => $sunrise,
            Prayer::Dhuhr->value => $solarDay['transit'],
            Prayer::Asr->value => $asr,
            Prayer::Maghrib->value => $maghrib,
            Prayer::Isha->value => $isha,
        ];

        $times = [];
        foreach ($hours as $name => $hour) {
            $times[$name] = $this->roundedUtcDateTime($date, $hour);
        }

        return $times;
    }

    /** @return SolarContext */
    private function resolveSolarContext(
        LocalDate $date,
        Coordinates $coordinates,
        PolarResolution $resolution,
    ): array {
        $solar = $this->solarDay($date, $coordinates);
        $tomorrow = $this->addDays($date, 1);
        $tomorrowSolar = $this->solarDay($tomorrow, $coordinates);

        if ($this->hasHorizonEvents($solar) && $this->hasHorizonEvents($tomorrowSolar)) {
            return [
                'date' => $date,
                'coordinates' => $coordinates,
                'solar' => $solar,
                'tomorrowSolar' => $tomorrowSolar,
            ];
        }

        return match ($resolution) {
            PolarResolution::Unresolved => throw new CalculationException(
                'Sunrise or sunset does not occur on this date and location; choose a polar resolution strategy.',
            ),
            PolarResolution::NearestValidDate => $this->nearestValidDateContext($date, $coordinates),
            PolarResolution::NearestValidLatitude => $this->nearestValidLatitudeContext($date, $coordinates),
        };
    }

    /** @return SolarContext */
    private function nearestValidDateContext(LocalDate $date, Coordinates $coordinates): array
    {
        for ($days = 1; $days <= 183; $days++) {
            foreach ([$days, -$days] as $offset) {
                $candidateDate = $this->addDays($date, $offset);
                $solar = $this->solarDay($candidateDate, $coordinates);
                $tomorrowSolar = $this->solarDay($this->addDays($candidateDate, 1), $coordinates);

                if ($this->hasHorizonEvents($solar) && $this->hasHorizonEvents($tomorrowSolar)) {
                    return [
                        'date' => $candidateDate,
                        'coordinates' => $coordinates,
                        'solar' => $solar,
                        'tomorrowSolar' => $tomorrowSolar,
                    ];
                }
            }
        }

        throw new CalculationException('No adjacent date with sunrise and sunset could be found.');
    }

    /** @return SolarContext */
    private function nearestValidLatitudeContext(LocalDate $date, Coordinates $coordinates): array
    {
        $direction = $coordinates->latitude <=> 0.0;
        if ($direction === 0) {
            throw new CalculationException('Polar latitude resolution requires a non-zero latitude.');
        }

        for ($distance = 0.5; $distance <= abs($coordinates->latitude); $distance += 0.5) {
            $latitude = $coordinates->latitude - ($direction * $distance);
            $candidateCoordinates = new Coordinates($latitude, $coordinates->longitude);
            $solar = $this->solarDay($date, $candidateCoordinates);
            $tomorrowSolar = $this->solarDay($this->addDays($date, 1), $candidateCoordinates);

            if ($this->hasHorizonEvents($solar) && $this->hasHorizonEvents($tomorrowSolar)) {
                return [
                    'date' => $date,
                    'coordinates' => $candidateCoordinates,
                    'solar' => $solar,
                    'tomorrowSolar' => $tomorrowSolar,
                ];
            }
        }

        throw new CalculationException('No latitude between this location and the equator has sunrise and sunset.');
    }

    /** @param SolarDay $solarDay */
    private function hasHorizonEvents(array $solarDay): bool
    {
        return $solarDay['sunrise'] !== null && $solarDay['sunset'] !== null;
    }

    /** @return SolarDay */
    private function solarDay(LocalDate $date, Coordinates $coordinates): array
    {
        $julianDay = $this->julianDay($date->year, $date->month, $date->day);
        $solar = $this->solarCoordinates($julianDay);
        $previousSolar = $this->solarCoordinates($julianDay - 1.0);
        $nextSolar = $this->solarCoordinates($julianDay + 1.0);
        $approximateTransit = $this->approximateTransit(
            $coordinates->longitude,
            $solar['sidereal'],
            $solar['rightAscension'],
        );

        $solarDay = [
            'solar' => $solar,
            'previousSolar' => $previousSolar,
            'nextSolar' => $nextSolar,
            'approximateTransit' => $approximateTransit,
            'transit' => $this->correctedTransit(
                $approximateTransit,
                $coordinates->longitude,
                $solar['sidereal'],
                $solar['rightAscension'],
                $previousSolar['rightAscension'],
                $nextSolar['rightAscension'],
            ),
            'sunrise' => null,
            'sunset' => null,
        ];

        $solarDay['sunrise'] = $this->tryCorrectedHourAngle(
            $solarDay,
            self::SUNRISE_ALTITUDE,
            $coordinates,
            false,
        );
        $solarDay['sunset'] = $this->tryCorrectedHourAngle(
            $solarDay,
            self::SUNRISE_ALTITUDE,
            $coordinates,
            true,
        );

        return $solarDay;
    }

    private function addDays(LocalDate $date, int $days): LocalDate
    {
        $utcDate = new DateTimeImmutable($date->toString(), new DateTimeZone('UTC'));
        $updated = $utcDate->modify(sprintf('%+d days', $days));

        return LocalDate::fromString($updated->format('Y-m-d'));
    }

    private function utcTimestamp(LocalDate $date, float $hours): float
    {
        $midnight = new DateTimeImmutable($date->toString() . ' 00:00:00', new DateTimeZone('UTC'));

        return $midnight->getTimestamp() + ($hours * 3600.0);
    }

    /** @return SolarCoordinates */
    private function solarCoordinates(float $julianDay): array
    {
        $century = ($julianDay - 2451545.0) / 36525.0;
        $meanLongitude = $this->unwindAngle(
            280.4664567 + 36000.76983 * $century + 0.0003032 * $century ** 2,
        );
        $meanLunarLongitude = $this->unwindAngle(218.3165 + 481267.8813 * $century);
        $ascendingNode = $this->unwindAngle(
            125.04452 - 1934.136261 * $century + 0.0020708 * $century ** 2 + $century ** 3 / 450000.0,
        );
        $meanAnomaly = $this->unwindAngle(
            357.52911 + 35999.05029 * $century - 0.0001537 * $century ** 2,
        );
        $anomalyRadians = $this->degreesToRadians($meanAnomaly);
        $equationOfCenter =
            (1.914602 - 0.004817 * $century - 0.000014 * $century ** 2) * sin($anomalyRadians)
            + (0.019993 - 0.000101 * $century) * sin(2.0 * $anomalyRadians)
            + 0.000289 * sin(3.0 * $anomalyRadians);
        $omega = 125.04 - 1934.136 * $century;
        $apparentLongitude = $this->unwindAngle(
            $meanLongitude + $equationOfCenter - 0.00569
            - 0.00478 * sin($this->degreesToRadians($omega)),
        );
        $meanObliquity =
            23.439291 - 0.013004167 * $century
            - 0.0000001639 * $century ** 2
            + 0.0000005036 * $century ** 3;
        $apparentObliquity = $meanObliquity + 0.00256 * cos($this->degreesToRadians($omega));
        $longitudeRadians = $this->degreesToRadians($apparentLongitude);
        $obliquityRadians = $this->degreesToRadians($apparentObliquity);
        $declination = $this->radiansToDegrees(asin(sin($obliquityRadians) * sin($longitudeRadians)));
        $rightAscension = $this->unwindAngle($this->radiansToDegrees(atan2(
            cos($obliquityRadians) * sin($longitudeRadians),
            cos($longitudeRadians),
        )));

        $nutationLongitude =
            (-17.2 / 3600.0) * sin($this->degreesToRadians($ascendingNode))
            - (1.32 / 3600.0) * sin(2.0 * $this->degreesToRadians($meanLongitude))
            - (0.23 / 3600.0) * sin(2.0 * $this->degreesToRadians($meanLunarLongitude))
            + (0.21 / 3600.0) * sin(2.0 * $this->degreesToRadians($ascendingNode));
        $nutationObliquity =
            (9.2 / 3600.0) * cos($this->degreesToRadians($ascendingNode))
            + (0.57 / 3600.0) * cos(2.0 * $this->degreesToRadians($meanLongitude))
            + (0.1 / 3600.0) * cos(2.0 * $this->degreesToRadians($meanLunarLongitude))
            - (0.09 / 3600.0) * cos(2.0 * $this->degreesToRadians($ascendingNode));
        $meanSiderealTime = $this->unwindAngle(
            280.46061837
            + 360.98564736629 * ($julianDay - 2451545.0)
            + 0.000387933 * $century ** 2
            - $century ** 3 / 38710000.0,
        );
        $siderealTime = $meanSiderealTime
            + $nutationLongitude * cos($this->degreesToRadians($meanObliquity + $nutationObliquity));

        return [
            'declination' => $declination,
            'rightAscension' => $rightAscension,
            'sidereal' => $siderealTime,
        ];
    }

    private function approximateTransit(
        float $longitude,
        float $siderealTime,
        float $rightAscension,
    ): float {
        $transit = $this->normalizeToScale(
            ($rightAscension - $longitude - $siderealTime) / 360.0,
            1.0,
        );
        $expectedTransit = $this->normalizeToScale((12.0 - $longitude / 15.0) / 24.0, 1.0);

        if ($transit - $expectedTransit > 0.5) {
            return $transit - 1.0;
        }

        if ($expectedTransit - $transit > 0.5) {
            return $transit + 1.0;
        }

        return $transit;
    }

    private function correctedTransit(
        float $approximateTransit,
        float $longitude,
        float $siderealTime,
        float $rightAscension,
        float $previousRightAscension,
        float $nextRightAscension,
    ): float {
        $theta = $this->unwindAngle($siderealTime + 360.985647 * $approximateTransit);
        $rightAscensionAtTransit = $this->unwindAngle($this->interpolateAngle(
            $rightAscension,
            $previousRightAscension,
            $nextRightAscension,
            $approximateTransit,
        ));
        $hourAngle = $this->quadrantShiftAngle(
            $theta + $longitude - $rightAscensionAtTransit,
        );

        return ($approximateTransit - $hourAngle / 360.0) * 24.0;
    }

    /** @param SolarDay $solarDay */
    private function requiredCorrectedHourAngle(
        array $solarDay,
        float $altitude,
        Coordinates $coordinates,
        bool $afterTransit,
        string $prayer,
    ): float {
        $time = $this->tryCorrectedHourAngle($solarDay, $altitude, $coordinates, $afterTransit);
        if ($time === null) {
            throw new CalculationException(sprintf('%s is undefined for this date and location.', $prayer));
        }

        return $time;
    }

    /** @param SolarDay $solarDay */
    private function tryCorrectedHourAngle(
        array $solarDay,
        float $altitude,
        Coordinates $coordinates,
        bool $afterTransit,
    ): ?float {
        $solar = $solarDay['solar'];
        $previousSolar = $solarDay['previousSolar'];
        $nextSolar = $solarDay['nextSolar'];
        $approximateTransit = $solarDay['approximateTransit'];
        $latitudeRadians = $this->degreesToRadians($coordinates->latitude);
        $declinationRadians = $this->degreesToRadians($solar['declination']);
        $denominator = cos($latitudeRadians) * cos($declinationRadians);
        $numerator = sin($this->degreesToRadians($altitude))
            - sin($latitudeRadians) * sin($declinationRadians);
        $ratio = $numerator / $denominator;

        if (!is_finite($ratio) || $ratio < -1.0 || $ratio > 1.0) {
            return null;
        }

        $hourAngleAtEvent = $this->radiansToDegrees(acos($ratio));
        $fractionalDay = $afterTransit
            ? $approximateTransit + $hourAngleAtEvent / 360.0
            : $approximateTransit - $hourAngleAtEvent / 360.0;
        $theta = $this->unwindAngle($solar['sidereal'] + 360.985647 * $fractionalDay);
        $rightAscension = $this->unwindAngle($this->interpolateAngle(
            $solar['rightAscension'],
            $previousSolar['rightAscension'],
            $nextSolar['rightAscension'],
            $fractionalDay,
        ));
        $declination = $this->interpolate(
            $solar['declination'],
            $previousSolar['declination'],
            $nextSolar['declination'],
            $fractionalDay,
        );
        $hourAngle = $theta + $coordinates->longitude - $rightAscension;
        $altitudeAtEvent = $this->radiansToDegrees(asin(
            sin($latitudeRadians) * sin($this->degreesToRadians($declination))
            + cos($latitudeRadians)
                * cos($this->degreesToRadians($declination))
                * cos($this->degreesToRadians($hourAngle)),
        ));
        $correctionDenominator = 360.0
            * cos($this->degreesToRadians($declination))
            * cos($latitudeRadians)
            * sin($this->degreesToRadians($hourAngle));

        if (abs($correctionDenominator) < 1.0e-12) {
            return null;
        }

        $time = ($fractionalDay + ($altitudeAtEvent - $altitude) / $correctionDenominator) * 24.0;

        return is_finite($time) ? $time : null;
    }

    private function roundedUtcDateTime(LocalDate $date, float $hours): DateTimeImmutable
    {
        if (!is_finite($hours)) {
            throw new CalculationException('The astronomical calculation returned a non-finite time.');
        }

        $seconds = (int) floor($hours * 3600.0);
        $instant = new DateTimeImmutable($date->toString() . ' 00:00:00', new DateTimeZone('UTC'));
        $instant = $instant->modify(sprintf('%+d seconds', $seconds));
        $secondWithinMinute = (($instant->getTimestamp() % 60) + 60) % 60;
        $roundingOffset = $secondWithinMinute >= 30 ? 60 - $secondWithinMinute : -$secondWithinMinute;

        return $instant->modify(sprintf('%+d seconds', $roundingOffset));
    }

    private function julianDay(int $year, int $month, int $day): float
    {
        $adjustedYear = $month > 2 ? $year : $year - 1;
        $adjustedMonth = $month > 2 ? $month : $month + 12;
        $century = (int) ($adjustedYear / 100);
        $correction = 2 - $century + (int) ($century / 4);

        return (int) (365.25 * ($adjustedYear + 4716))
            + (int) (30.6001 * ($adjustedMonth + 1))
            + $day + $correction - 1524.5;
    }

    private function interpolate(float $current, float $previous, float $next, float $fraction): float
    {
        $a = $current - $previous;
        $b = $next - $current;

        return $current + ($fraction / 2.0) * ($a + $b + $fraction * ($b - $a));
    }

    private function interpolateAngle(float $current, float $previous, float $next, float $fraction): float
    {
        $a = $this->unwindAngle($current - $previous);
        $b = $this->unwindAngle($next - $current);

        return $current + ($fraction / 2.0) * ($a + $b + $fraction * ($b - $a));
    }

    private function unwindAngle(float $angle): float
    {
        return $this->normalizeToScale($angle, 360.0);
    }

    private function normalizeToScale(float $number, float $maximum): float
    {
        return $number - $maximum * floor($number / $maximum);
    }

    private function quadrantShiftAngle(float $angle): float
    {
        if ($angle >= -180.0 && $angle <= 180.0) {
            return $angle;
        }

        return $angle - 360.0 * round($angle / 360.0);
    }

    private function degreesToRadians(float $degrees): float
    {
        return ($degrees * M_PI) / 180.0;
    }

    private function radiansToDegrees(float $radians): float
    {
        return ($radians * 180.0) / M_PI;
    }
}
