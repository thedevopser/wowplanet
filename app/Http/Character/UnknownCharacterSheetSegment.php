<?php

declare(strict_types=1);

namespace App\Http\Character;

use InvalidArgumentException;

final class UnknownCharacterSheetSegment extends InvalidArgumentException
{
    public static function for(?string $section, ?string $sub): self
    {
        return new self(sprintf('Unknown character sheet view "%s/%s".', $section ?? '', $sub ?? ''));
    }
}
