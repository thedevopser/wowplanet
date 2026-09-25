<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Infrastructure\Blizzard\Responses\Profile\CharacterEquipmentResponse;
use App\Infrastructure\Blizzard\Responses\Profile\EquippedItemEntry;

/**
 * @phpstan-type EquippedItem array{slot: string, slot_name: string, item_id: int, name: string, item_level: int, quality: string, icon_url: string|null}
 */
class EquipmentAggregator
{
    private const string DEFAULT_QUALITY = 'COMMON';

    /**
     * @param  array<int, string>  $iconMap  Map of itemId => iconUrl from Blizzard media API
     * @return list<EquippedItem>
     */
    public function aggregate(CharacterEquipmentResponse $characterEquipmentResponse, array $iconMap = []): array
    {
        return array_map(
            fn (EquippedItemEntry $equippedItemEntry): array => $this->equippedItem($equippedItemEntry, $iconMap),
            $characterEquipmentResponse->items,
        );
    }

    /**
     * @param  array<int, string>  $iconMap
     * @return EquippedItem
     */
    private function equippedItem(EquippedItemEntry $equippedItemEntry, array $iconMap): array
    {
        $itemId = $equippedItemEntry->itemId ?? 0;

        return [
            'slot' => $equippedItemEntry->slotType ?? '',
            'slot_name' => $equippedItemEntry->slotName ?? '',
            'item_id' => $itemId,
            'name' => $equippedItemEntry->name ?? '',
            'item_level' => $equippedItemEntry->itemLevel ?? 0,
            'quality' => $equippedItemEntry->qualityType ?? self::DEFAULT_QUALITY,
            'icon_url' => $iconMap[$itemId] ?? null,
        ];
    }
}
