<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

enum ReferenceColumnType
{
    case Integer;
    case BigInteger;
    case Text;

    public function isNumeric(): bool
    {
        return $this !== self::Text;
    }
}
