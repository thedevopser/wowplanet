<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidWeeklyActivityException;

/**
 * Les difficultés de raid, dans les termes que Blizzard envoie, de la plus facile à la plus dure.
 */
enum RaidDifficulty: string
{
    case Lfr = 'LFR';
    case Normal = 'NORMAL';
    case Heroic = 'HEROIC';
    case Mythic = 'MYTHIC';

    public static function fromBlizzard(string $difficulty): self
    {
        return self::tryFrom($difficulty) ?? throw InvalidWeeklyActivityException::unknownRaidDifficulty($difficulty);
    }

    public function rank(): int
    {
        return match ($this) {
            self::Lfr => 0,
            self::Normal => 1,
            self::Heroic => 2,
            self::Mythic => 3,
        };
    }
}
