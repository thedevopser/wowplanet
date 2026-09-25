<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Un document de `data/wow/search/decor`, réduit à ce que le catalogue exploite.
 *
 * Le balayage rend les 2 124 décorations en vingt-huit fenêtres, avec le même contenu que
 * `data/wow/decor/{id}`. L'item lié est la seule porte vers l'icône et la qualité d'une
 * décoration : le balayage de media d'items les apporte ensuite, sans appel unitaire.
 */
final readonly class DecorSearchDocument
{
    public function __construct(
        public int $id,
        public ?string $nameFr,
        public ?int $itemId,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $name = $responsePayload->optionalObject('name');

        return new self(
            id: $responsePayload->requiredInt('id'),
            nameFr: TrimmedText::firstNonEmpty($name?->optionalString('fr_FR'), $name?->optionalString('en_US')),
            itemId: $responsePayload->optionalObject('item')?->optionalInt('id'),
        );
    }
}
