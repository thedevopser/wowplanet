<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Un détail de `data/wow/pet/{id}`, réduit à ce que le catalogue exploite.
 *
 * Les mascottes sont la seule des trois collections à n'avoir pas d'endpoint de recherche :
 * `data/wow/search/pet` est en 404, donc le détail reste le seul chemin. Il porte en
 * revanche tout ce dont le catalogue a besoin, icône comprise — mesuré sur les 2 179
 * mascottes de l'index, toutes servies avec une icône et une créature.
 *
 * Un détail est servi dans la seule locale demandée : le nom est du texte, pas une carte
 * de locales comme dans un document de recherche.
 */
final readonly class PetDocument
{
    public function __construct(
        public int $id,
        public ?string $nameFr,
        public ?string $iconUrl,
        public ?int $creatureId,
        public ?string $sourceType,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(
            id: $responsePayload->requiredInt('id'),
            nameFr: TrimmedText::firstNonEmpty($responsePayload->optionalString('name')),
            iconUrl: TrimmedText::firstNonEmpty($responsePayload->optionalString('icon')),
            creatureId: $responsePayload->optionalObject('creature')?->optionalInt('id'),
            sourceType: TrimmedText::firstNonEmpty($responsePayload->optionalObject('source')?->optionalString('type')),
        );
    }
}
