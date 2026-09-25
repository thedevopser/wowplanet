<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference\Exceptions;

final class UnknownTableException extends ReferenceSyncException
{
    /**
     * @param  list<string>  $available
     */
    public static function named(string $requested, array $available): self
    {
        return new self(sprintf('Table DB2 inconnue : %s. Disponibles : %s.', $requested, implode(', ', $available)));
    }
}
