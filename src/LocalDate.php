<?php

declare(strict_types=1);

namespace GlobalPrayerTimes;

use InvalidArgumentException;

final readonly class LocalDate
{
    private function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
    }

    public static function fromString(string $date): self
    {
        if (
            preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $date, $matches) !== 1
            || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
        ) {
            throw new InvalidArgumentException('Date must be a valid Gregorian date in YYYY-MM-DD format.');
        }

        return new self((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    public function toString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }
}
