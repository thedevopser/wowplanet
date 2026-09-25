<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses\Profile;

use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * L'équipement porté, `…/equipment`.
 */
final readonly class CharacterEquipmentResponse
{
    /**
     * @param  list<EquippedItemEntry>  $items
     */
    public function __construct(public array $items) {}

    public static function fromPayload(ResponsePayload $responsePayload): self
    {
        return new self(array_map(EquippedItemEntry::fromPayload(...), $responsePayload->objectList('equipped_items')));
    }

    /**
     * Les objets dont on va chercher l'icône : ceux qui portent un identifiant utilisable.
     *
     * @return list<int>
     */
    public function equippedItemIds(): array
    {
        $itemIds = [];
        foreach ($this->items as $item) {
            if (($item->itemId ?? 0) > 0) {
                $itemIds[] = $item->itemId;
            }
        }

        return $itemIds;
    }
}
