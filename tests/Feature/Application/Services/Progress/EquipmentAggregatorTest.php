<?php

declare(strict_types=1);

use App\Application\Services\Progress\EquipmentAggregator;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterEquipmentResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

test('aggregate transforms full API response into structured slot array', function (): void {
    $apiResponse = [
        'equipped_items' => [
            [
                'slot' => ['type' => 'HEAD', 'name' => 'Tête'],
                'item' => ['id' => 12345],
                'name' => 'Casque du Néant',
                'quality' => ['type' => 'EPIC', 'name' => 'Épique'],
                'level' => ['value' => 639],
            ],
            [
                'slot' => ['type' => 'MAIN_HAND', 'name' => 'Main droite'],
                'item' => ['id' => 99999],
                'name' => 'Épée légendaire',
                'quality' => ['type' => 'LEGENDARY', 'name' => 'Légendaire'],
                'level' => ['value' => 645],
            ],
        ],
    ];

    $iconMap = [
        12345 => 'https://render.worldofwarcraft.com/icons/56/inv_helm_123.jpg',
        99999 => 'https://render.worldofwarcraft.com/icons/56/inv_sword_456.jpg',
    ];

    $aggregator = new EquipmentAggregator;
    $result = equipmentProgress($apiResponse, $iconMap);

    expect($result)->toHaveCount(2);

    $head = collect($result)->firstWhere('slot', 'HEAD');
    expect($head['slot'])->toBe('HEAD')
        ->and($head['slot_name'])->toBe('Tête')
        ->and($head['item_id'])->toBe(12345)
        ->and($head['name'])->toBe('Casque du Néant')
        ->and($head['item_level'])->toBe(639)
        ->and($head['quality'])->toBe('EPIC')
        ->and($head['icon_url'])->toBe('https://render.worldofwarcraft.com/icons/56/inv_helm_123.jpg');

    $weapon = collect($result)->firstWhere('slot', 'MAIN_HAND');
    expect($weapon['quality'])->toBe('LEGENDARY')
        ->and($weapon['item_level'])->toBe(645);
});

test('aggregate handles empty response', function (): void {
    $aggregator = new EquipmentAggregator;

    expect(equipmentProgress([]))->toBe([])
        ->and(equipmentProgress(['equipped_items' => []]))->toBe([]);
});

test('aggregate handles missing icon gracefully', function (): void {
    $apiResponse = [
        'equipped_items' => [
            [
                'slot' => ['type' => 'SHIRT', 'name' => 'Chemise'],
                'item' => ['id' => 111],
                'name' => 'Chemise simple',
                'quality' => ['type' => 'COMMON', 'name' => 'Commun'],
                'level' => ['value' => 1],
            ],
        ],
    ];

    $aggregator = new EquipmentAggregator;
    $result = equipmentProgress($apiResponse);

    expect($result)->toHaveCount(1)
        ->and($result[0]['icon_url'])->toBeNull()
        ->and($result[0]['quality'])->toBe('COMMON');
});

test('aggregate uses icon map to resolve icon_url', function (): void {
    $apiResponse = [
        'equipped_items' => [
            [
                'slot' => ['type' => 'CHEST', 'name' => 'Torse'],
                'item' => ['id' => 555],
                'name' => 'Plastron',
                'quality' => ['type' => 'RARE', 'name' => 'Rare'],
                'level' => ['value' => 600],
            ],
        ],
    ];

    $iconMap = [555 => 'https://render.worldofwarcraft.com/icons/56/inv_chest_plate.jpg'];

    $aggregator = new EquipmentAggregator;
    $result = equipmentProgress($apiResponse, $iconMap);

    expect($result[0]['icon_url'])->toBe('https://render.worldofwarcraft.com/icons/56/inv_chest_plate.jpg');
});

/**
 * @param  array<string, mixed>  $equipmentResponse
 * @param  array<int, string>  $iconMap
 * @return list<array<string, mixed>>
 */
function equipmentProgress(array $equipmentResponse, array $iconMap = []): array
{
    return (new EquipmentAggregator)->aggregate(
        CharacterEquipmentResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/equipment', $equipmentResponse)),
        $iconMap,
    );
}

test('the equipment payload is unchanged', function (): void {
    $result = equipmentProgress([
        'equipped_items' => [
            ['slot' => ['type' => 'HEAD', 'name' => 'Tête'], 'item' => ['id' => 12345], 'name' => 'Casque', 'quality' => ['type' => 'EPIC'], 'level' => ['value' => 639]],
            ['slot' => ['type' => 'NECK'], 'item' => ['id' => 23456], 'name' => 'Collier'],
            ['name' => 'Rien'],
            [],
        ],
    ], [12345 => 'https://render.test/12345.jpg', 99 => 'https://render.test/unused.jpg']);

    expect(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))->toMatchSnapshot();
});
