<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference\Exceptions;

final class TruncatedSourceException extends ReferenceSyncException
{
    public static function collapsed(string $source, int $loaded, int $previous): self
    {
        return new self(sprintf(
            'La table DB2 %s ne rend plus que %d lignes contre %d au dernier chargement : chargement abandonné.',
            $source,
            $loaded,
            $previous,
        ));
    }
}
