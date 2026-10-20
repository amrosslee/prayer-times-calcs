<?php

declare(strict_types=1);

namespace GlobalPrayerTimes;

use InvalidArgumentException;

final readonly class CalculationSettings
{
    /**
     * @param array<string, int> $adjustments Prayer-specific adjustments in minutes.
     */
    private function __construct(
        public string $methodName,
        public float $fajrAngle,
        public ?float $ishaAngle,
        public ?int $ishaIntervalMinutes,
        public ?float $maghribAngle,
        public AsrRule $asrRule,
        public HighLatitudeRule $highLatitudeRule,
        public PolarResolution $polarResolution,
        public array $adjustments,
    ) {
    }

    /**
     * @param array<array-key, mixed> $adjustments
     */
    public static function muslimWorldLeague(
        AsrRule $asrRule = AsrRule::Standard,
        array $adjustments = [],
        HighLatitudeRule $highLatitudeRule = HighLatitudeRule::MiddleOfTheNight,
        PolarResolution $polarResolution = PolarResolution::Unresolved,
    ): self {
        return self::create(
            'Muslim World League',
            18.0,
            17.0,
            null,
            null,
            $asrRule,
            $adjustments,
            ['dhuhr' => 1],
            $highLatitudeRule,
            $polarResolution,
        );
    }

    /**
     * Islamic Society of North America.
     *
     * @param array<array-key, mixed> $adjustments
     */
    public static function isna(
        AsrRule $asrRule = AsrRule::Standard,
        array $adjustments = [],
        HighLatitudeRule $highLatitudeRule = HighLatitudeRule::MiddleOfTheNight,
        PolarResolution $polarResolution = PolarResolution::Unresolved,
    ): self {
        return self::create(
            'ISNA',
            15.0,
            15.0,
            null,
            null,
            $asrRule,
            $adjustments,
            ['dhuhr' => 1],
            $highLatitudeRule,
            $polarResolution,
        );
    }

    /**
     * Egyptian General Authority of Survey.
     *
     * @param array<array-key, mixed> $adjustments
     */
    public static function egyptian(
        AsrRule $asrRule = AsrRule::Standard,
        array $adjustments = [],
        HighLatitudeRule $highLatitudeRule = HighLatitudeRule::MiddleOfTheNight,
        PolarResolution $polarResolution = PolarResolution::Unresolved,
    ): self {
        return self::create(
            'Egyptian General Authority of Survey',
            19.5,
            17.5,
            null,
            null,
            $asrRule,
            $adjustments,
            ['dhuhr' => 1],
            $highLatitudeRule,
            $polarResolution,
        );
    }

    /**
     * Umm al-Qura University, Makkah. Add an Isha adjustment during Ramadan if required.
     *
     * @param array<array-key, mixed> $adjustments
     */
    public static function ummAlQura(
        AsrRule $asrRule = AsrRule::Standard,
        array $adjustments = [],
        HighLatitudeRule $highLatitudeRule = HighLatitudeRule::MiddleOfTheNight,
        PolarResolution $polarResolution = PolarResolution::Unresolved,
    ): self {
        return self::create(
            'Umm al-Qura',
            18.5,
            null,
            90,
            null,
            $asrRule,
            $adjustments,
            [],
            $highLatitudeRule,
            $polarResolution,
        );
    }

    /**
     * University of Islamic Sciences, Karachi.
     *
     * @param array<array-key, mixed> $adjustments
     */
    public static function karachi(
        AsrRule $asrRule = AsrRule::Standard,
        array $adjustments = [],
        HighLatitudeRule $highLatitudeRule = HighLatitudeRule::MiddleOfTheNight,
        PolarResolution $polarResolution = PolarResolution::Unresolved,
    ): self {
        return self::create(
            'Karachi',
            18.0,
            18.0,
            null,
            null,
            $asrRule,
            $adjustments,
            ['dhuhr' => 1],
            $highLatitudeRule,
            $polarResolution,
        );
    }

    /**
     * Jafari (Shia Ithna-Ashari) angles published by PrayTimes.
     *
     * @param array<array-key, mixed> $adjustments
     */
    public static function jafari(
        AsrRule $asrRule = AsrRule::Standard,
        array $adjustments = [],
        HighLatitudeRule $highLatitudeRule = HighLatitudeRule::MiddleOfTheNight,
        PolarResolution $polarResolution = PolarResolution::Unresolved,
    ): self {
        return self::create(
            'Jafari',
            16.0,
            14.0,
            null,
            4.0,
            $asrRule,
            $adjustments,
            [],
            $highLatitudeRule,
            $polarResolution,
        );
    }

    /**
     * Institute of Geophysics, University of Tehran.
     *
     * @param array<array-key, mixed> $adjustments
     */
    public static function tehran(
        AsrRule $asrRule = AsrRule::Standard,
        array $adjustments = [],
        HighLatitudeRule $highLatitudeRule = HighLatitudeRule::MiddleOfTheNight,
        PolarResolution $polarResolution = PolarResolution::Unresolved,
    ): self {
        return self::create(
            'Tehran',
            17.7,
            14.0,
            null,
            4.5,
            $asrRule,
            $adjustments,
            [],
            $highLatitudeRule,
            $polarResolution,
        );
    }

    /**
     * Configure angles and/or an interval for a custom calculation method.
     *
     * Exactly one of $ishaAngle and $ishaIntervalMinutes must be provided.
     *
     * @param array<array-key, mixed> $adjustments
     */
    public static function custom(
        float $fajrAngle,
        ?float $ishaAngle = null,
        ?int $ishaIntervalMinutes = null,
        ?float $maghribAngle = null,
        AsrRule $asrRule = AsrRule::Standard,
        array $adjustments = [],
        HighLatitudeRule $highLatitudeRule = HighLatitudeRule::MiddleOfTheNight,
        PolarResolution $polarResolution = PolarResolution::Unresolved,
    ): self {
        return self::create(
            'Custom',
            $fajrAngle,
            $ishaAngle,
            $ishaIntervalMinutes,
            $maghribAngle,
            $asrRule,
            $adjustments,
            [],
            $highLatitudeRule,
            $polarResolution,
        );
    }

    /**
     * @param array<array-key, mixed> $adjustments
     * @param array<string, int> $methodAdjustments
     */
    private static function create(
        string $methodName,
        float $fajrAngle,
        ?float $ishaAngle,
        ?int $ishaIntervalMinutes,
        ?float $maghribAngle,
        AsrRule $asrRule,
        array $adjustments,
        array $methodAdjustments = [],
        HighLatitudeRule $highLatitudeRule = HighLatitudeRule::MiddleOfTheNight,
        PolarResolution $polarResolution = PolarResolution::Unresolved,
    ): self {
        if (!is_finite($fajrAngle) || $fajrAngle <= 0.0 || $fajrAngle > 30.0) {
            throw new InvalidArgumentException('Fajr angle must be greater than 0 and at most 30 degrees.');
        }

        if (($ishaAngle === null) === ($ishaIntervalMinutes === null)) {
            throw new InvalidArgumentException('Provide exactly one of Isha angle or Isha interval.');
        }

        if ($ishaAngle !== null && (!is_finite($ishaAngle) || $ishaAngle <= 0.0 || $ishaAngle > 30.0)) {
            throw new InvalidArgumentException('Isha angle must be greater than 0 and at most 30 degrees.');
        }

        if ($ishaIntervalMinutes !== null && $ishaIntervalMinutes <= 0) {
            throw new InvalidArgumentException('Isha interval must be a positive number of minutes.');
        }

        if (
            $maghribAngle !== null
            && (!is_finite($maghribAngle) || $maghribAngle <= 0.0 || $maghribAngle > 30.0)
        ) {
            throw new InvalidArgumentException('Maghrib angle must be greater than 0 and at most 30 degrees.');
        }

        $validatedAdjustments = self::validateAdjustments($methodAdjustments);
        foreach (self::validateAdjustments($adjustments) as $prayer => $minutes) {
            $validatedAdjustments[$prayer] = ($validatedAdjustments[$prayer] ?? 0) + $minutes;
        }

        return new self(
            $methodName,
            $fajrAngle,
            $ishaAngle,
            $ishaIntervalMinutes,
            $maghribAngle,
            $asrRule,
            $highLatitudeRule,
            $polarResolution,
            $validatedAdjustments,
        );
    }

    /**
     * @param array<array-key, mixed> $adjustments
     * @return array<string, int>
     */
    private static function validateAdjustments(array $adjustments): array
    {
        $validatedAdjustments = [];
        foreach ($adjustments as $prayer => $minutes) {
            if (!is_string($prayer) || Prayer::tryFrom($prayer) === null || !is_int($minutes)) {
                throw new InvalidArgumentException('Adjustments must map known prayer names to integer minutes.');
            }

            $validatedAdjustments[$prayer] = $minutes;
        }

        return $validatedAdjustments;
    }

    public function adjustmentFor(Prayer $prayer): int
    {
        return $this->adjustments[$prayer->value] ?? 0;
    }
}
