<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Profile\CharacterEquipmentResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function characterEquipment(array $decoded): CharacterEquipmentResponse
{
    return CharacterEquipmentResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/equipment', $decoded),
    );
}

test('it lists the ids of the equipped items', function (): void {
    expect(characterEquipment(['equipped_items' => [['item' => ['id' => 12345]], ['item' => ['id' => 23456]]]])->equippedItemIds())
        ->toBe([12345, 23456]);
});

test('an equipped entry without a usable item id is skipped', function (): void {
    expect(characterEquipment(['equipped_items' => [['slot' => ['type' => 'HEAD']], ['item' => ['id' => 0]], ['item' => []]]])->equippedItemIds())
        ->toBe([]);
});

test('a character with nothing equipped has no item', function (): void {
    expect(characterEquipment([])->equippedItemIds())->toBe([]);
});

test('every equipped entry is kept with its displayed fields', function (): void {
    $items = characterEquipment(['equipped_items' => [
        ['slot' => ['type' => 'HEAD', 'name' => 'Tête'], 'item' => ['id' => 12345], 'name' => 'Casque', 'quality' => ['type' => 'EPIC'], 'level' => ['value' => 639]],
        [],
    ]])->items;

    expect($items)->toHaveCount(2)
        ->and($items[0]->slotType)->toBe('HEAD')
        ->and($items[0]->slotName)->toBe('Tête')
        ->and($items[0]->itemId)->toBe(12345)
        ->and($items[0]->name)->toBe('Casque')
        ->and($items[0]->qualityType)->toBe('EPIC')
        ->and($items[0]->itemLevel)->toBe(639)
        ->and($items[1]->slotType)->toBeNull()
        ->and($items[1]->itemId)->toBeNull()
        ->and($items[1]->qualityType)->toBeNull()
        ->and($items[1]->itemLevel)->toBeNull();
});
