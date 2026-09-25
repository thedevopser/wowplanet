<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference\Exceptions;

final class DownloadFailedException extends ReferenceSyncException
{
    public static function status(string $source, int $status): self
    {
        return new self(sprintf('Téléchargement de la table DB2 %s refusé par wago.tools (HTTP %d).', $source, $status));
    }

    public static function empty(string $source): self
    {
        return new self(sprintf('Téléchargement de la table DB2 %s vide.', $source));
    }
}
