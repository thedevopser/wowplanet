<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Un document de `data/wow/search/mount`, réduit à ce que le catalogue exploite.
 *
 * Le balayage rend les 1 669 montures en quatre fenêtres, avec le même contenu que
 * `data/wow/mount/{id}` : c'est ce qui remplace un appel de détail par monture.
 *
 * Le type de source n'est qu'une valeur d'attente pour une monture que la taxonomie ne
 * range pas encore — douze valeurs contre 170 dans la curation. La faction est présente
 * dans le document et volontairement ignorée : aucune colonne ne la porte.
 */
final readonly class MountSearchDocument
{
    public function __construct(
        public int $id,
        public ?string $nameFr,
        public ?string $sourceType,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $name = $responsePayload->optionalObject('name');

        return new self(
            id: $responsePayload->requiredInt('id'),
            nameFr: TrimmedText::firstNonEmpty($name?->optionalString('fr_FR'), $name?->optionalString('en_US')),
            sourceType: TrimmedText::firstNonEmpty($responsePayload->optionalObject('source')?->optionalString('type')),
        );
    }
}
