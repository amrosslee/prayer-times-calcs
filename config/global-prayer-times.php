<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Calculation Method
    |--------------------------------------------------------------------------
    |
    | Available methods: muslim_world_league, isna, egyptian, umm_al_qura,
    | karachi, jafari, tehran, and custom.
    |
    */
    'method' => 'muslim_world_league',

    /*
    |--------------------------------------------------------------------------
    | Asr Rule
    |--------------------------------------------------------------------------
    |
    | Use "standard" for the standard shadow-length rule or "hanafi" for the
    | Hanafi rule.
    |
    */
    'asr_rule' => 'standard',

    /*
    |--------------------------------------------------------------------------
    | High-Latitude Rule
    |--------------------------------------------------------------------------
    |
    | Available rules: middle_of_the_night, seventh_of_the_night, and
    | twilight_angle.
    |
    */
    'high_latitude_rule' => 'middle_of_the_night',

    /*
    |--------------------------------------------------------------------------
    | Polar Resolution
    |--------------------------------------------------------------------------
    |
    | "unresolved" throws when the sun does not rise or set. Alternatives are
    | nearest_valid_date and nearest_valid_latitude; both are approximations.
    |
    */
    'polar_resolution' => 'unresolved',

    /*
    |--------------------------------------------------------------------------
    | Prayer Adjustments
    |--------------------------------------------------------------------------
    |
    | Optional integer minute offsets, keyed by prayer name. Method defaults
    | (such as a method's Dhuhr offset) are applied in addition to these.
    |
    */
    'adjustments' => [],

    /*
    |--------------------------------------------------------------------------
    | Custom Method Parameters
    |--------------------------------------------------------------------------
    |
    | Used only when "method" is "custom". Set exactly one of isha_angle or
    | isha_interval_minutes. Set maghrib_angle only when required by the method.
    |
    */
    'custom' => [
        'fajr_angle' => 18.0,
        'isha_angle' => 17.0,
        'isha_interval_minutes' => null,
        'maghrib_angle' => null,
    ],
];
