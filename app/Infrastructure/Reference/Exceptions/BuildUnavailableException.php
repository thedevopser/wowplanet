<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference\Exceptions;

final class BuildUnavailableException extends ReferenceSyncException
{
    public static function unreadable(): self
    {
        return new self("Build LIVE introuvable : wago.tools n'a pas rendu de version pour le produit wow.");
    }
}
