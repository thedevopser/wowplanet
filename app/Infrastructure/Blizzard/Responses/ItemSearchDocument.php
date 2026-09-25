<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

/**
 * Un document de `data/wow/search/item`, réduit à ce que le catalogue exploite.
 *
 * Ces documents portent déjà les apparences liées, la qualité, le media et la classe
 * d'objet : c'est ce qui permet de reconstruire la garde-robe par balayage plutôt que
 * par un appel unitaire par apparence. Une recherche renvoyant toutes les locales, un
 * document décodé pèse plusieurs kilo-octets : il est réduit à cet objet dès l'arrivée,
 * jamais conservé sous sa forme brute.
 */
final readonly class ItemSearchDocument
{
    /** Qualités de l'API → OverallQualityID numérique historique de la base. */
    private const QUALITY_RANKS = [
        'POOR' => 0,
        'COMMON' => 1,
        'UNCOMMON' => 2,
        'RARE' => 3,
        'EPIC' => 4,
        'LEGENDARY' => 5,
        'ARTIFACT' => 6,
        'HEIRLOOM' => 7,
    ];

    private const DEFAULT_QUALITY = 1;

    /**
     * @param  list<int>  $appearanceIds
     */
    public function __construct(
        public int $id,
        public ?string $nameFr,
        public int $quality,
        public ?int $mediaId,
        public ?string $categoryFr,
        public array $appearanceIds,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $name = $responsePayload->optionalObject('name');

        $appearanceIds = [];
        foreach ($responsePayload->objectList('appearances') as $appearance) {
            $appearanceIds[] = $appearance->requiredInt('id');
        }

        return new self(
            id: $responsePayload->requiredInt('id'),
            nameFr: TrimmedText::firstNonEmpty($name?->optionalString('fr_FR'), $name?->optionalString('en_US')),
            quality: self::QUALITY_RANKS[$responsePayload->optionalObject('quality')?->optionalString('type') ?? ''] ?? self::DEFAULT_QUALITY,
            mediaId: $responsePayload->optionalObject('media')?->optionalInt('id'),
            categoryFr: TrimmedText::firstNonEmpty($responsePayload->optionalObject('item_class')?->optionalObject('name')?->optionalString('fr_FR')),
            appearanceIds: $appearanceIds,
        );
    }
}
