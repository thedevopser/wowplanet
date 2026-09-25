<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Lignes créées, mises à jour et supprimées par une étape d'import.
 *
 * Le décompte se déduit de quatre nombres lus sur la table elle-même, seule autorité
 * sur ce qui a réellement été écrit : les lignes touchées depuis le début de l'étape,
 * celles qui y sont nées, et la cardinalité avant et après. Les importers écrivent tous
 * par `upsert()`, qui entretient les horodatages, et sautent les lignes inchangées
 * avant d'écrire : une ligne touchée est donc une ligne réellement modifiée.
 */
final readonly class RowTally
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $deleted,
    ) {}

    public static function none(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * @param  int  $touched  Lignes présentes dont l'horodatage de mise à jour est postérieur au début de l'étape
     * @param  int  $created  Lignes présentes nées après le début de l'étape
     * @param  int  $rowsBefore  Cardinalité avant l'étape
     * @param  int  $rowsNow  Cardinalité après l'étape
     */
    public static function fromCounts(int $touched, int $created, int $rowsBefore, int $rowsNow): self
    {
        // Un `upsert` pose les deux horodatages : toute ligne née pendant l'étape a
        // forcément été touchée pendant l'étape. L'inverse signalerait une horloge ou
        // une table qui ne portent pas ce qu'on croit.
        throw_if($created > $touched, \InvalidArgumentException::class, sprintf(
            'Cannot have %d rows created and only %d touched.',
            $created,
            $touched,
        ));

        $deleted = $rowsBefore + $created - $rowsNow;

        throw_if($deleted < 0, \InvalidArgumentException::class, sprintf(
            'Table grew from %d to %d rows while creating only %d.',
            $rowsBefore,
            $rowsNow,
            $created,
        ));

        return new self($created, $touched - $created, $deleted);
    }

    public function plus(self $other): self
    {
        return new self(
            $this->created + $other->created,
            $this->updated + $other->updated,
            $this->deleted + $other->deleted,
        );
    }

    public function isEmpty(): bool
    {
        return $this->created === 0 && $this->updated === 0 && $this->deleted === 0;
    }
}
