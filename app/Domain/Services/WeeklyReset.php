<?php

declare(strict_types=1);

namespace App\Domain\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * La remise à zéro hebdomadaire des serveurs européens : chaque mercredi à 4 h UTC toute l'année,
 * comme les périodes Mythique+ de Blizzard, soit 5 h à Paris en hiver et 6 h en été.
 */
final readonly class WeeklyReset
{
    private const string TIMEZONE = 'UTC'; // @pest-mutate-ignore

    private const int RESET_WEEKDAY = 3; // @pest-mutate-ignore

    private const int RESET_HOUR = 4; // @pest-mutate-ignore

    private const int DAYS_PER_WEEK = 7; // @pest-mutate-ignore

    private const int MILLISECONDS_PER_SECOND = 1000; // @pest-mutate-ignore

    public function lastResetAt(DateTimeImmutable $now): DateTimeImmutable
    {
        $local = $now->setTimezone(new DateTimeZone(self::TIMEZONE));
        $daysSinceResetDay = ((int) $local->format('N') - self::RESET_WEEKDAY + self::DAYS_PER_WEEK) % self::DAYS_PER_WEEK;
        $reset = $this->atResetHour($local->modify(sprintf('-%d days', $daysSinceResetDay)));

        if ($reset > $local) {
            return $this->atResetHour($reset->modify(sprintf('-%d days', self::DAYS_PER_WEEK)));
        }

        return $reset;
    }

    public function nextResetAt(DateTimeImmutable $now): DateTimeImmutable
    {
        return $this->atResetHour($this->lastResetAt($now)->modify(sprintf('+%d days', self::DAYS_PER_WEEK)));
    }

    /**
     * Blizzard date ses victoires en millisecondes depuis l'époque Unix ; zéro veut dire jamais.
     */
    public function isThisWeek(int $milliseconds, DateTimeImmutable $now): bool
    {
        return intdiv($milliseconds, self::MILLISECONDS_PER_SECOND) >= $this->lastResetAt($now)->getTimestamp();
    }

    private function atResetHour(DateTimeImmutable $day): DateTimeImmutable
    {
        return $day->setTime(self::RESET_HOUR, 0);
    }
}
