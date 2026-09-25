<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy\Exceptions;

use RuntimeException;

/**
 * L'amont curé n'a pas répondu, ou a répondu vide.
 *
 * Un tirage amont est un geste manuel, hors de tout chemin d'import : cette exception n'arrête
 * donc jamais un import. Elle existe pour qu'un amont muet ne laisse pas croire qu'un patch
 * n'a rien apporté.
 */
final class TaxonomyUpstreamUnreachableException extends RuntimeException
{
    public static function status(string $filename, int $status): self
    {
        return new self(sprintf('Téléchargement de %s refusé par l\'amont curé (HTTP %d).', $filename, $status));
    }

    public static function empty(string $filename): self
    {
        return new self(sprintf('Téléchargement de %s vide.', $filename));
    }
}
