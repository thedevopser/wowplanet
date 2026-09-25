<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy\Exceptions;

use RuntimeException;

/**
 * L'instantané versionné de la taxonomie ne respecte pas sa forme attendue.
 *
 * Distincte de `TaxonomySourceUnavailableException`, qui signale un instantané absent ou vide :
 * ici le fichier est là mais son contenu est faux. Un instantané tolérant ferait entrer une
 * ligne muette en base, là où un fichier versionné et curé à la main mérite un refus net.
 */
final class TaxonomySnapshotMalformedException extends RuntimeException
{
    /**
     * @param  list<string>  $expected
     * @param  list<string>  $found
     */
    public static function header(array $expected, array $found): self
    {
        return new self(sprintf(
            "En-tête d'instantané inattendu : %s attendu, %s trouvé.",
            implode(',', $expected),
            implode(',', $found),
        ));
    }

    public static function columnCount(int $line, int $expected, int $found): self
    {
        return new self(sprintf(
            'Ligne %d de l\'instantané : %d colonnes attendues, %d trouvées.',
            $line,
            $expected,
            $found,
        ));
    }

    public static function entity(int $line, string $entity): self
    {
        return new self(sprintf('Ligne %d de l\'instantané : entité inconnue « %s ».', $line, $entity));
    }

    public static function entryId(int $line, string $entryId): self
    {
        return new self(sprintf('Ligne %d de l\'instantané : identifiant non entier « %s ».', $line, $entryId));
    }

    public static function obtainable(int $line, string $obtainable): self
    {
        return new self(sprintf(
            'Ligne %d de l\'instantané : « %s » n\'est ni true ni false.',
            $line,
            $obtainable,
        ));
    }

    public static function writtenEntity(string $entity): self
    {
        return new self(sprintf('Entité inconnue à écrire dans l\'instantané : « %s ».', $entity));
    }
}
