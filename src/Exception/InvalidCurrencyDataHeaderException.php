<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class InvalidCurrencyDataHeaderException extends RuntimeException
{
    public static function missingHeader(): self
    {
        return new self('Format invalide: le header CT_BASE64_V1: est manquant');
    }

    public static function invalidHeader(string $foundHeader): self
    {
        return new self(sprintf('Format invalide: header "%s" non supporté', $foundHeader));
    }
}
