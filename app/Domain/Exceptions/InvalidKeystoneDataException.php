<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use InvalidArgumentException;

class InvalidKeystoneDataException extends InvalidArgumentException
{
    public static function seasonWithoutDungeon(): self
    {
        return new self('The season has no dungeon.');
    }

    public static function negativeLevel(int $dungeonId, int $level): self
    {
        return new self(sprintf('Keystone level %d of dungeon %d is negative.', $level, $dungeonId));
    }
}
