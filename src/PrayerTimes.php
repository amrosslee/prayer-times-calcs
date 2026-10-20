<?php

declare(strict_types=1);

namespace GlobalPrayerTimes;

use DateTimeImmutable;

final readonly class PrayerTimes
{
    public function __construct(
        public DateTimeImmutable $fajr,
        public DateTimeImmutable $sunrise,
        public DateTimeImmutable $dhuhr,
        public DateTimeImmutable $asr,
        public DateTimeImmutable $maghrib,
        public DateTimeImmutable $isha,
    ) {
    }

    /**
     * @return array<string, DateTimeImmutable>
     */
    public function all(): array
    {
        return [
            Prayer::Fajr->value => $this->fajr,
            Prayer::Sunrise->value => $this->sunrise,
            Prayer::Dhuhr->value => $this->dhuhr,
            Prayer::Asr->value => $this->asr,
            Prayer::Maghrib->value => $this->maghrib,
            Prayer::Isha->value => $this->isha,
        ];
    }
}
