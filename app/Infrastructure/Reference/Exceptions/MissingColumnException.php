<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference\Exceptions;

final class MissingColumnException extends ReferenceSyncException
{
    public static function for(string $source, string $column): self
    {
        return new self(sprintf('La table DB2 %s ne porte plus la colonne %s.', $source, $column));
    }
}
