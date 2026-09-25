<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Exceptions;

class UnexpectedFieldTypeException extends BlizzardContractException
{
    public static function at(string $path, string $endpoint, string $expected, mixed $actual): self
    {
        return new self(sprintf(
            'Field [%s] in the response of [%s] should be of type %s, %s given.',
            $path,
            $endpoint,
            $expected,
            get_debug_type($actual),
        ));
    }
}
