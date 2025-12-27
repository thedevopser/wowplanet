<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class CurrencyImportRateLimitException extends RuntimeException
{
    public static function limitExceeded(int $maxAttempts, int $timeUntilResetSeconds): self
    {
        $minutes = (int) ceil($timeUntilResetSeconds / 60);

        return new self(sprintf(
            'Limite d\'importation atteinte (%d par heure). Réessayez dans %d minute(s).',
            $maxAttempts,
            $minutes
        ));
    }
}
