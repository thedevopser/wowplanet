<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class CurrencyDataTooLargeException extends RuntimeException
{
    public static function fromSize(int $sizeBytes, int $maxSizeBytes): self
    {
        $sizeMB = round($sizeBytes / 1048576, 2);
        $maxSizeMB = round($maxSizeBytes / 1048576, 2);

        return new self(sprintf(
            'Données trop volumineuses (%.2f MB). Maximum autorisé: %.2f MB',
            $sizeMB,
            $maxSizeMB
        ));
    }
}
