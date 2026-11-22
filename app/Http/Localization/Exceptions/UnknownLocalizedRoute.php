<?php

declare(strict_types=1);

namespace App\Http\Localization\Exceptions;

use LogicException;

final class UnknownLocalizedRoute extends LogicException
{
    public static function named(string $name): self
    {
        return new self(sprintf('No localized route is named "%s".', $name));
    }
}
