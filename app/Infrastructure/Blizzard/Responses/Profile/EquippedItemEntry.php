<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * Un objet porté, réduit à ce que l'onglet équipement affiche.
 */
final readonly class EquippedItemEntry
{
    public function __construct(
        public ?string $slotType,
        public ?string $slotName,
        public ?int $itemId,
        public ?string $name,
        public ?string $qualityType,
        public ?int $itemLevel,
    ) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        $slot = $responsePayload->optionalObject('slot');

        return new self(
            slotType: $slot?->optionalString('type'),
            slotName: $slot?->optionalString('name'),
            itemId: $responsePayload->optionalObject('item')?->optionalInt('id'),
            name: $responsePayload->optionalString('name'),
            qualityType: $responsePayload->optionalObject('quality')?->optionalString('type'),
            itemLevel: $responsePayload->optionalObject('level')?->optionalInt('value'),
        );
    }
}
