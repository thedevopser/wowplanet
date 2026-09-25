<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference\Exceptions;

final class MalformedSourceException extends ReferenceSyncException
{
    public static function withoutHeader(string $source): self
    {
        return new self(sprintf('La table DB2 %s a été servie sans ligne d\'en-tête.', $source));
    }
}
