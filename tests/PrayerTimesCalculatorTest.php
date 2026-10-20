<?php

declare(strict_types=1);

namespace GlobalPrayerTimes\Tests;

use DateTimeZone;
use GlobalPrayerTimes\AsrRule;
use GlobalPrayerTimes\Calculation\PrayerTimesCalculator;
use GlobalPrayerTimes\CalculationException;
use GlobalPrayerTimes\CalculationSettings;
use GlobalPrayerTimes\Coordinates;
use GlobalPrayerTimes\HighLatitudeRule;
use GlobalPrayerTimes\LocalDate;
use GlobalPrayerTimes\PolarResolution;
use GlobalPrayerTimes\Prayer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PrayerTimesCalculatorTest extends TestCase
{
    public function test_calculates_doha_reference_fixture_with_custom_interval_settings(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2016-01-01'),
            new Coordinates(25.283897, 51.528770),
            'Asia/Riyadh',
            CalculationSettings::custom(fajrAngle: 18.0, ishaIntervalMinutes: 90),
        );

        $expected = [
            'fajr' => '04:58',
            'sunrise' => '06:19',
            'dhuhr' => '11:37',
            'asr' => '14:35',
            'maghrib' => '16:55',
            'isha' => '18:25',
        ];

        foreach ($times->all() as $prayer => $time) {
            $referenceTime = new \DateTimeImmutable(
                '2016-01-01 ' . $expected[$prayer],
                new DateTimeZone('Asia/Riyadh'),
            );
            self::assertLessThanOrEqual(
                120,
                abs($time->getTimestamp() - $referenceTime->getTimestamp()),
                $prayer . ' must be within the source fixture tolerance',
            );
            self::assertSame('Asia/Riyadh', $time->getTimezone()->getName());
        }
    }

    public function test_all_requested_named_calculation_methods_are_configured_and_calculable(): void
    {
        $methods = [
            'Muslim World League' => [CalculationSettings::muslimWorldLeague(), 18.0, 17.0, null, null, 1],
            'ISNA' => [CalculationSettings::isna(), 15.0, 15.0, null, null, 1],
            'Egyptian General Authority of Survey' => [
                CalculationSettings::egyptian(),
                19.5,
                17.5,
                null,
                null,
                1,
            ],
            'Umm al-Qura' => [CalculationSettings::ummAlQura(), 18.5, null, 90, null, 0],
            'Karachi' => [CalculationSettings::karachi(), 18.0, 18.0, null, null, 1],
            'Jafari' => [CalculationSettings::jafari(), 16.0, 14.0, null, 4.0, 0],
            'Tehran' => [CalculationSettings::tehran(), 17.7, 14.0, null, 4.5, 0],
        ];

        $calculator = new PrayerTimesCalculator();
        foreach ($methods as $name => [$settings, $fajrAngle, $ishaAngle, $ishaInterval, $maghribAngle, $dhuhrOffset]) {
            self::assertSame($name, $settings->methodName);
            self::assertSame($fajrAngle, $settings->fajrAngle);
            self::assertSame($ishaAngle, $settings->ishaAngle);
            self::assertSame($ishaInterval, $settings->ishaIntervalMinutes);
            self::assertSame($maghribAngle, $settings->maghribAngle);
            self::assertSame($dhuhrOffset, $settings->adjustmentFor(Prayer::Dhuhr));

            $times = $calculator->calculate(
                LocalDate::fromString('2024-09-21'),
                new Coordinates(24.7136, 46.6753),
                'Asia/Riyadh',
                $settings,
            );
            self::assertCount(6, $times->all(), $name);
        }
    }

    public function test_custom_adjustment_is_added_to_method_default_adjustment(): void
    {
        $settings = CalculationSettings::muslimWorldLeague(adjustments: ['dhuhr' => 2]);

        self::assertSame(3, $settings->adjustmentFor(Prayer::Dhuhr));
    }

    public function test_high_latitude_rules_bound_missing_twilight_events(): void
    {
        $calculator = new PrayerTimesCalculator();
        $date = LocalDate::fromString('2026-06-21');
        $coordinates = new Coordinates(59.3293, 18.0686);

        $middle = $calculator->calculate(
            $date,
            $coordinates,
            'Europe/Stockholm',
            CalculationSettings::muslimWorldLeague(highLatitudeRule: HighLatitudeRule::MiddleOfTheNight),
        );
        $seventh = $calculator->calculate(
            $date,
            $coordinates,
            'Europe/Stockholm',
            CalculationSettings::muslimWorldLeague(highLatitudeRule: HighLatitudeRule::SeventhOfTheNight),
        );
        $twilight = $calculator->calculate(
            $date,
            $coordinates,
            'Europe/Stockholm',
            CalculationSettings::muslimWorldLeague(highLatitudeRule: HighLatitudeRule::TwilightAngle),
        );

        self::assertLessThan($middle->sunrise->getTimestamp(), $middle->fajr->getTimestamp());
        self::assertGreaterThan($middle->fajr->getTimestamp(), $twilight->fajr->getTimestamp());
        self::assertGreaterThan($twilight->fajr->getTimestamp(), $seventh->fajr->getTimestamp());
        self::assertGreaterThan($middle->maghrib->getTimestamp(), $middle->isha->getTimestamp());
        self::assertGreaterThan($seventh->isha->getTimestamp(), $twilight->isha->getTimestamp());
        self::assertGreaterThan($twilight->isha->getTimestamp(), $middle->isha->getTimestamp());
    }

    public function test_polar_unresolved_policy_fails_explicitly(): void
    {
        $this->expectException(CalculationException::class);
        (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2026-06-21'),
            new Coordinates(69.6492, 18.9553),
            'Europe/Oslo',
            CalculationSettings::muslimWorldLeague(polarResolution: PolarResolution::Unresolved),
        );
    }

    public function test_nearest_valid_date_resolves_polar_sunrise_and_sunset(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2026-06-21'),
            new Coordinates(69.6492, 18.9553),
            'Europe/Oslo',
            CalculationSettings::muslimWorldLeague(polarResolution: PolarResolution::NearestValidDate),
        );

        self::assertCount(6, $times->all());
        foreach ($times->all() as $time) {
            self::assertSame('Europe/Oslo', $time->getTimezone()->getName());
        }
        self::assertLessThan($times->sunrise->getTimestamp(), $times->fajr->getTimestamp());
        self::assertGreaterThan($times->maghrib->getTimestamp(), $times->isha->getTimestamp());
        self::assertSame('2026-06-21', $times->dhuhr->format('Y-m-d'));
    }

    public function test_nearest_valid_date_matches_upstream_midnight_sun_regression(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2020-06-21'),
            new Coordinates(66.7222444, 17.7189),
            'Europe/Stockholm',
            CalculationSettings::muslimWorldLeague(
                highLatitudeRule: HighLatitudeRule::SeventhOfTheNight,
                polarResolution: PolarResolution::NearestValidDate,
            ),
        );

        $expected = [
            'fajr' => '2020-06-21 00:40',
            'sunrise' => '2020-06-21 00:54',
            'dhuhr' => '2020-06-21 12:55',
            'asr' => '2020-06-21 17:49',
            'maghrib' => '2020-06-21 23:36',
            'isha' => '2020-06-21 23:51',
        ];

        foreach ($times->all() as $prayer => $time) {
            self::assertSame($expected[$prayer], $time->format('Y-m-d H:i'), $prayer);
        }
    }

    public function test_nearest_valid_latitude_resolves_polar_sunrise_and_sunset(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2026-06-21'),
            new Coordinates(69.6492, 18.9553),
            'Europe/Oslo',
            CalculationSettings::muslimWorldLeague(polarResolution: PolarResolution::NearestValidLatitude),
        );

        self::assertCount(6, $times->all());
        foreach ($times->all() as $time) {
            self::assertSame('Europe/Oslo', $time->getTimezone()->getName());
        }
        self::assertLessThan($times->sunrise->getTimestamp(), $times->fajr->getTimestamp());
        self::assertGreaterThan($times->maghrib->getTimestamp(), $times->isha->getTimestamp());
        self::assertSame('2026-06-21', $times->dhuhr->format('Y-m-d'));
    }

    public function test_both_polar_fallbacks_handle_southern_hemisphere_polar_night(): void
    {
        $calculator = new PrayerTimesCalculator();

        foreach ([PolarResolution::NearestValidDate, PolarResolution::NearestValidLatitude] as $resolution) {
            $times = $calculator->calculate(
                LocalDate::fromString('2020-12-21'),
                new Coordinates(-84.996, 0.01013),
                'Antarctica/McMurdo',
                CalculationSettings::muslimWorldLeague(polarResolution: $resolution),
            );

            self::assertCount(6, $times->all(), $resolution->value);
            foreach ($times->all() as $time) {
                self::assertSame('Antarctica/McMurdo', $time->getTimezone()->getName());
            }
        }
    }

    public function test_calculates_umm_al_qura_makkah_reference_fixture(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2016-01-05'),
            new Coordinates(21.427009, 39.828685),
            'Asia/Riyadh',
            CalculationSettings::ummAlQura(),
        );

        $this->assertReferenceTimes($times->all(), [
            'fajr' => '05:38',
            'sunrise' => '07:00',
            'dhuhr' => '12:26',
            'asr' => '15:31',
            'maghrib' => '17:52',
            'isha' => '19:22',
        ], '2016-01-05', 60);
    }

    public function test_calculates_tehran_reference_fixture(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2018-01-01'),
            new Coordinates(35.715298, 51.404343),
            'Asia/Tehran',
            CalculationSettings::tehran(),
        );

        $this->assertReferenceTimes($times->all(), [
            'fajr' => '05:44',
            'sunrise' => '07:14',
            'dhuhr' => '12:08',
            'asr' => '14:44',
            'maghrib' => '17:22',
            'isha' => '18:12',
        ], '2018-01-01', 60);
    }

    public function test_matches_upstream_mwl_reference_case(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2015-12-01'),
            new Coordinates(35.775, -78.6336),
            'America/New_York',
            CalculationSettings::muslimWorldLeague(),
        );

        $expected = [
            'fajr' => '05:35',
            'sunrise' => '07:06',
            'dhuhr' => '12:05',
            'asr' => '14:42',
            'maghrib' => '17:01',
            'isha' => '18:26',
        ];

        foreach ($times->all() as $prayer => $time) {
            self::assertSame($expected[$prayer], $time->format('H:i'), $prayer);
        }
    }

    public function test_matches_upstream_egyptian_reference_case(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2020-01-01'),
            new Coordinates(30.028703, 31.249528),
            'Africa/Cairo',
            CalculationSettings::egyptian(),
        );

        $expected = [
            'fajr' => '05:18',
            'sunrise' => '06:51',
            'dhuhr' => '11:59',
            'asr' => '14:47',
            'maghrib' => '17:06',
            'isha' => '18:29',
        ];

        foreach ($times->all() as $prayer => $time) {
            self::assertSame($expected[$prayer], $time->format('H:i'), $prayer);
        }
    }

    public function test_matches_upstream_isna_hanafi_reference_case(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2015-07-12'),
            new Coordinates(35.775, -78.6336),
            'America/New_York',
            CalculationSettings::isna(AsrRule::Hanafi),
        );

        $expected = [
            'fajr' => '04:42',
            'sunrise' => '06:08',
            'dhuhr' => '13:21',
            'asr' => '18:22',
            'maghrib' => '20:32',
            'isha' => '21:57',
        ];

        foreach ($times->all() as $prayer => $time) {
            self::assertSame($expected[$prayer], $time->format('H:i'), $prayer);
        }
    }

    public function test_calculation_is_deterministic_and_independent_of_server_timezone(): void
    {
        $calculator = new PrayerTimesCalculator();
        $date = LocalDate::fromString('2026-03-08');
        $coordinates = new Coordinates(40.7128, -74.0060);
        $settings = CalculationSettings::muslimWorldLeague();
        $previousTimeZone = date_default_timezone_get();

        try {
            date_default_timezone_set('Pacific/Auckland');
            $first = $calculator->calculate($date, $coordinates, 'America/New_York', $settings);
            date_default_timezone_set('UTC');
            $second = $calculator->calculate($date, $coordinates, 'America/New_York', $settings);
            $dayBefore = $calculator->calculate(
                LocalDate::fromString('2026-03-07'),
                $coordinates,
                'America/New_York',
                $settings,
            );
        } finally {
            date_default_timezone_set($previousTimeZone);
        }

        self::assertSame('-05:00', $dayBefore->fajr->format('P'));
        foreach ($first->all() as $prayer => $time) {
            self::assertSame(
                $time->getTimestamp(),
                $second->all()[$prayer]->getTimestamp(),
                $prayer,
            );
            self::assertSame('-04:00', $time->format('P'));
        }
    }

    public function test_local_date_is_preserved_across_the_international_date_line(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2026-10-19'),
            new Coordinates(1.8721, -157.4278),
            'Pacific/Kiritimati',
            CalculationSettings::muslimWorldLeague(),
        );

        foreach ($times->all() as $time) {
            self::assertSame('2026-10-19', $time->format('Y-m-d'));
            self::assertSame('Pacific/Kiritimati', $time->getTimezone()->getName());
        }
    }

    public function test_daylight_saving_fall_transition_uses_the_requested_timezone_rules(): void
    {
        $times = (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2026-11-01'),
            new Coordinates(40.7128, -74.0060),
            'America/New_York',
            CalculationSettings::muslimWorldLeague(),
        );

        self::assertSame('-05:00', $times->fajr->format('P'));
        self::assertSame('2026-11-01', $times->fajr->format('Y-m-d'));
    }

    public function test_hanafi_asr_occurs_after_standard_asr(): void
    {
        $calculator = new PrayerTimesCalculator();
        $date = LocalDate::fromString('2026-06-21');
        $coordinates = new Coordinates(24.7136, 46.6753);

        $standard = $calculator->calculate(
            $date,
            $coordinates,
            'Asia/Riyadh',
            CalculationSettings::muslimWorldLeague(AsrRule::Standard),
        );
        $hanafi = $calculator->calculate(
            $date,
            $coordinates,
            'Asia/Riyadh',
            CalculationSettings::muslimWorldLeague(AsrRule::Hanafi),
        );

        self::assertGreaterThan($standard->asr->getTimestamp(), $hanafi->asr->getTimestamp());
    }

    public function test_adjustments_are_applied_to_the_selected_prayer_only(): void
    {
        $calculator = new PrayerTimesCalculator();
        $date = LocalDate::fromString('2026-02-03');
        $coordinates = new Coordinates(24.7136, 46.6753);

        $base = $calculator->calculate(
            $date,
            $coordinates,
            'Asia/Riyadh',
            CalculationSettings::muslimWorldLeague(),
        );
        $adjusted = $calculator->calculate(
            $date,
            $coordinates,
            'Asia/Riyadh',
            CalculationSettings::muslimWorldLeague(adjustments: ['fajr' => 2]),
        );

        self::assertSame(120, $adjusted->fajr->getTimestamp() - $base->fajr->getTimestamp());
        self::assertSame($base->sunrise->getTimestamp(), $adjusted->sunrise->getTimestamp());
    }

    public function test_rejects_invalid_coordinates_dates_timezones_and_settings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Coordinates(91.0, 0.0);
    }

    public function test_rejects_invalid_local_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LocalDate::fromString('2026-02-30');
    }

    public function test_rejects_dates_that_are_not_in_iso_calendar_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LocalDate::fromString('2026-2-03');
    }

    public function test_rejects_invalid_longitude(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Coordinates(0.0, 181.0);
    }

    public function test_rejects_non_finite_coordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Coordinates(NAN, 0.0);
    }

    public function test_rejects_non_iana_timezone_identifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2026-02-03'),
            new Coordinates(24.7136, 46.6753),
            '+03:00',
            CalculationSettings::muslimWorldLeague(),
        );
    }

    public function test_rejects_invalid_custom_method_configuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CalculationSettings::custom(fajrAngle: 18.0);
    }

    public function test_rejects_invalid_prayer_adjustment_names_and_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CalculationSettings::muslimWorldLeague(adjustments: ['unknown' => 5]);
    }

    public function test_rejects_non_integer_prayer_adjustments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CalculationSettings::muslimWorldLeague(adjustments: ['fajr' => '5']);
    }

    public function test_nearest_valid_date_reports_when_no_daily_sunrise_can_be_found(): void
    {
        $this->expectException(CalculationException::class);
        (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2026-06-21'),
            new Coordinates(90.0, 0.0),
            'UTC',
            CalculationSettings::muslimWorldLeague(polarResolution: PolarResolution::NearestValidDate),
        );
    }

    public function test_throws_when_solar_events_are_unavailable_without_a_high_latitude_rule(): void
    {
        $this->expectException(CalculationException::class);
        (new PrayerTimesCalculator())->calculate(
            LocalDate::fromString('2026-06-21'),
            new Coordinates(69.6492, 18.9553),
            'Europe/Oslo',
            CalculationSettings::muslimWorldLeague(),
        );
    }

    /**
     * @param array<string, \DateTimeImmutable> $times
     * @param array<string, string> $expected
     */
    private function assertReferenceTimes(array $times, array $expected, string $date, int $toleranceSeconds): void
    {
        foreach ($times as $prayer => $time) {
            $referenceTime = new \DateTimeImmutable(
                $date . ' ' . $expected[$prayer],
                $time->getTimezone(),
            );
            self::assertLessThanOrEqual(
                $toleranceSeconds,
                abs($time->getTimestamp() - $referenceTime->getTimestamp()),
                $prayer . ' must be within the source fixture tolerance',
            );
        }
    }
}
