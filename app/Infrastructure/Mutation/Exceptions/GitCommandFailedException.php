<?php

declare(strict_types=1);

namespace App\Infrastructure\Mutation\Exceptions;

use RuntimeException;

final class GitCommandFailedException extends RuntimeException
{
    /**
     * @param  list<string>  $command
     */
    public static function for(array $command, string $errorOutput): self
    {
        return new self(sprintf('%s a échoué : %s', implode(' ', $command), trim($errorOutput)));
    }
}
