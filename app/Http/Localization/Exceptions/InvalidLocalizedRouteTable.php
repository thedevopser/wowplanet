<?php

declare(strict_types=1);

namespace App\Http\Localization\Exceptions;

use LogicException;

final class InvalidLocalizedRouteTable extends LogicException
{
    public static function because(string $reason): self
    {
        return new self('Invalid localized route table: '.$reason);
    }
}
