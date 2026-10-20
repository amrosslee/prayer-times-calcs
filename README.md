# Global Prayer Times

Framework-independent, offline Islamic prayer-time calculations for PHP. This package is
unreleased; prayer-time conventions vary, and the configurable polar fallbacks are explicit
approximations rather than a guarantee for every locality or scholarly convention.

## Requirements

- PHP 8.3 or newer
- Composer

## Install

The Packagist name is provisional until the publisher/vendor is confirmed:

```sh
composer require amrosslee/global-prayer-times
```

## Supported features

This increment implements:

- Named presets: Muslim World League (18/17 degrees), ISNA (15/15), Egyptian (19.5/17.5),
  Umm al-Qura (18.5 degrees Fajr; Isha 90 minutes after sunset), Karachi (18/18), Jafari
  (16/14 degrees Fajr/Isha; 4 degrees Maghrib), and Tehran (17.7/14; 4.5 degrees Maghrib).
- Custom Fajr, Isha-angle or Isha-interval, and optional Maghrib-angle parameters.
- Standard and Hanafi Asr.
- Per-prayer minute adjustments.
- High-latitude Fajr/Isha rules: middle of the night, seventh of the night, or twilight-angle fraction.
- Polar sunrise/sunset policies: explicitly unresolved, nearest valid date, or nearest valid latitude.
- Explicit Gregorian local date, coordinates, and IANA timezone inputs.
- Immutable result values containing Fajr, sunrise, Dhuhr, Asr, Maghrib, and Isha.

Named presets include method defaults, such as a one-minute Dhuhr adjustment for MWL, ISNA,
Egyptian, and Karachi. Caller-provided prayer adjustments are added to those defaults. Umm al-Qura
uses a fixed 90-minute Isha interval; to follow the documented Ramadan convention, pass an
additional Isha adjustment for the relevant dates.

When sunrise or sunset is absent for the requested date, the default `Unresolved` polar policy
throws `CalculationException`. `NearestValidDate` searches up to 183 days before and after the
requested date, preferring the next valid date at equal distances. `NearestValidLatitude` searches
toward the equator in 0.5-degree steps while keeping longitude fixed. Either policy borrows solar
event times from the resolved date or latitude; those borrowed UTC time-of-day values are applied
to the requested calendar date, so a returned local time can fall on the following or preceding
date after timezone conversion. These are explicit approximation policies, not a claim that a
location at the pole has ordinary daily sunrise and sunset.

The default `MiddleOfTheNight` high-latitude rule bounds Fajr and angle-based Isha by half the
calculated night. `SeventhOfTheNight` uses one seventh; `TwilightAngle` uses the configured twilight
angle divided by 60. A method using an Isha interval (such as Umm al-Qura) calculates Isha from
sunset and that interval instead.

```php
use GlobalPrayerTimes\CalculationSettings;
use GlobalPrayerTimes\HighLatitudeRule;
use GlobalPrayerTimes\PolarResolution;

$settings = CalculationSettings::isna(
    highLatitudeRule: HighLatitudeRule::SeventhOfTheNight,
    polarResolution: PolarResolution::NearestValidDate,
);
```

```php
use GlobalPrayerTimes\Calculation\PrayerTimesCalculator;
use GlobalPrayerTimes\CalculationSettings;
use GlobalPrayerTimes\Coordinates;
use GlobalPrayerTimes\LocalDate;

$times = (new PrayerTimesCalculator())->calculate(
    LocalDate::fromString('2026-10-18'),
    new Coordinates(40.7128, -74.0060),
    'America/New_York',
    CalculationSettings::muslimWorldLeague(),
);

echo $times->fajr->format('H:i');
```

Date is a local Gregorian calendar date in the supplied timezone. The calculator uses local noon to
anchor the astronomical UTC date, which preserves the requested date across the International Date
Line, then returns each `DateTimeImmutable` in the explicitly supplied timezone. The PHP process
timezone is not consulted.

## Algorithm and attribution

The solar-position and event-time implementation is adapted from the Adhan JavaScript library's
Meeus-based calculation core. The inspected upstream project is
[batoulapps/adhan-js](https://github.com/batoulapps/adhan-js), licensed MIT, at development commit
[`46ed34d`](https://github.com/batoulapps/adhan-js/commit/46ed34d64bb396391e9cfef768cb8c6c43032716)
dated 2026-10-12. The port adapts calculations from `SolarCoordinates.ts`, `Astronomical.ts`, and
`SolarTime.ts`; the upstream MIT notice is retained in [LICENSE](LICENSE). This PHP port uses no
runtime dependency and does not copy the JavaScript package into the published package.
The nearest-valid-date and nearest-valid-latitude polar resolution approaches also follow Adhan's
MIT-licensed [`PolarCircleResolution.ts`](https://github.com/batoulapps/adhan-js/blob/46ed34d64bb396391e9cfef768cb8c6c43032716/src/PolarCircleResolution.ts),
with explicit finite search limits and exceptions when a valid substitute cannot be found.

Named method parameters are documented in Adhan's
[Calculation Parameters Guide](https://github.com/batoulapps/adhan-js/blob/46ed34d64bb396391e9cfef768cb8c6c43032716/METHODS.md).
The Jafari parameter values (Fajr 16 degrees, Maghrib 4 degrees, Isha 14 degrees) are from
[PrayTimes v2.5](https://praytimes.org/code/v2/js/PrayTimes.js), which identifies its source as
LGPL-3.0. This package consults the method values only; it does not copy or adapt PrayTimes code.

Reference data provenance and tolerances are recorded in [tests/fixtures/README.md](tests/fixtures/README.md).
Prayer-time conventions and published timetables can differ; a few matching fixtures do not imply
universal religious or astronomical accuracy.

## Development

```sh
composer install
composer test
composer analyse
composer style
```

To use this checkout from a future Laravel application before publishing, add a Composer path
repository in that application's `composer.json` (adjust the relative path as needed):

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../prayer-times-calcs",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "amrosslee/global-prayer-times": "*"
  }
}
```

Then run `composer update amrosslee/global-prayer-times` in the application.

## First release checklist

- Confirm the Packagist vendor/package name and set the correct public Git repository metadata.
- Run `composer validate --strict`, `composer test`, `composer analyse`, and `composer style`.
- Verify the CI matrix succeeds on every supported PHP version.
- Review README usage/limitations, reference-fixture sources, MIT license and upstream attribution.
- Update `CHANGELOG.md`, commit the release, and push a matching semantic-version Git tag such as `v1.0.0`.
- Submit the public repository to Packagist and configure its GitHub webhook.
- Verify a clean application can install the tagged package with Composer.

## Release status

This package is not ready for publication. Remaining work includes broader reference fixtures and
regression coverage across all named methods, timezone transitions, and geographic/date boundaries,
verification of the Packagist vendor name, and a successful PHP 8.3 CI run. The local environment
currently has PHP 8.5 only; a passing PHP 8.5 test run does not substitute for validation on PHP 8.3.
