<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Exceptions;

class MissingFieldException extends BlizzardContractException
{
    public static function at(string $path, string $endpoint): self
    {
        return new self(sprintf('Missing field [%s] in the response of [%s].', $path, $endpoint));
    }
}
