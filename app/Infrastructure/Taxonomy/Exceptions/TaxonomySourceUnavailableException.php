<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy\Exceptions;

use App\Infrastructure\Blizzard\Responses\Exceptions\BlizzardContractException;
use RuntimeException;

/**
 * La source dont la taxonomie s'amorce est absente, illisible ou vide.
 *
 * Levée plutôt que tolérée : un amorçage qui ne trouve rien et se tait laisserait croire
 * que la taxonomie est à jour alors qu'elle est restée vide.
 */
final class TaxonomySourceUnavailableException extends RuntimeException
{
    public static function for(string $filename): self
    {
        return new self(sprintf(
            'Aucune entrée curée lisible dans %s : fichier absent, illisible ou vide.',
            $filename,
        ));
    }

    public static function malformed(BlizzardContractException $blizzardContractException): self
    {
        return new self('Export curé mal formé : '.$blizzardContractException->getMessage(), 0, $blizzardContractException);
    }

    public static function forEntity(string $entity, string $filename): self
    {
        return new self(sprintf(
            'Aucune entrée pour la collection « %s » dans %s.',
            $entity,
            $filename,
        ));
    }
}
