<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use InvalidArgumentException;

class InvalidWeeklyActivityException extends InvalidArgumentException
{
    public static function unknownRaidDifficulty(string $difficulty): self
    {
        return new self(sprintf('Unknown raid difficulty %s.', $difficulty));
    }

    public static function keyLevelTooLow(int $level, int $lowest): self
    {
        return new self(sprintf('Mythic+ key level %d is under %d.', $level, $lowest));
    }
}
