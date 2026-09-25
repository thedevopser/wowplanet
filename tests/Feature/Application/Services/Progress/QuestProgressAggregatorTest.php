<?php

declare(strict_types=1);

use App\Application\Services\Progress\QuestProgressAggregator;
use App\Models\WowQuest;

test('aggregate groups quests by expansion and zone', function (): void {
    WowQuest::factory()->create(['id' => 1, 'expansion_id' => 0, 'zone_name' => 'Durotar', 'is_active' => true]);
    WowQuest::factory()->create(['id' => 2, 'expansion_id' => 0, 'zone_name' => 'Durotar', 'is_active' => true]);
    WowQuest::factory()->create(['id' => 3, 'expansion_id' => 7, 'zone_name' => 'Drustvar', 'is_active' => true]);

    $aggregator = new QuestProgressAggregator;
    $result = $aggregator->aggregate([1, 3], '');

    // Classic expansion
    expect($result[0]['total'])->toBe(2);
    expect($result[0]['completed'])->toBe(1);
    expect($result[0]['zones'])->toHaveCount(1);
    expect($result[0]['zones'][0]['name'])->toBe('Durotar');
    expect($result[0]['zones'][0]['total'])->toBe(2);
    expect($result[0]['zones'][0]['completed'])->toBe(1);

    // BfA expansion
    expect($result[7]['total'])->toBe(1);
    expect($result[7]['completed'])->toBe(1);
});

test('aggregate filters quests by faction', function (): void {
    WowQuest::factory()->create(['id' => 10, 'expansion_id' => 0, 'zone_name' => 'Zone', 'faction' => 'Alliance', 'is_active' => true]);
    WowQuest::factory()->create(['id' => 11, 'expansion_id' => 0, 'zone_name' => 'Zone', 'faction' => 'Horde', 'is_active' => true]);
    WowQuest::factory()->create(['id' => 12, 'expansion_id' => 0, 'zone_name' => 'Zone', 'faction' => null, 'is_active' => true]);

    $aggregator = new QuestProgressAggregator;
    $result = $aggregator->aggregate([], 'Alliance');

    // Alliance sees alliance + neutral quests (2), not Horde
    expect($result[0]['total'])->toBe(2);
});

test('aggregate skips inactive quests', function (): void {
    WowQuest::factory()->create(['id' => 20, 'expansion_id' => 0, 'zone_name' => 'Zone', 'is_active' => true]);
    WowQuest::factory()->create(['id' => 21, 'expansion_id' => 0, 'zone_name' => 'Zone', 'is_active' => false]);

    $aggregator = new QuestProgressAggregator;
    $result = $aggregator->aggregate([], '');

    expect($result[0]['total'])->toBe(1);
});

test('aggregate returns all 12 expansion slots', function (): void {
    $aggregator = new QuestProgressAggregator;
    $result = $aggregator->aggregate([], '');

    expect($result)->toHaveCount(12);
    expect(array_keys($result))->toBe(range(0, 11));
});

test('aggregate counts in its expansion a quest without zone but lists no zone for it', function (): void {
    WowQuest::factory()->create(['id' => 1, 'expansion_id' => 7, 'zone_name' => null, 'is_active' => true]);
    WowQuest::factory()->create(['id' => 2, 'expansion_id' => 7, 'zone_name' => 'Drustvar', 'is_active' => true]);

    $result = (new QuestProgressAggregator)->aggregate([1], '');

    expect(array_column($result[7]['zones'], 'name'))->toBe(['Drustvar'])
        ->and($result[7]['zones'][0]['total'])->toBe(1);
});

test('aggregate hands the front everything it displays for a quest', function (): void {
    WowQuest::factory()->create(['id' => 3, 'expansion_id' => 7, 'zone_name' => 'Drustvar', 'name_fr' => 'La sorcière', 'is_active' => true]);

    expect((new QuestProgressAggregator)->aggregate([3], '')[7]['zones'])->toBe([[
        'name' => 'Drustvar',
        'total' => 1,
        'completed' => 1,
        'items' => [['id' => 3, 'name' => 'La sorcière', 'is_completed' => true]],
    ]]);
});

test('aggregate keeps a numeric zone name as text', function (): void {
    WowQuest::factory()->create(['id' => 1, 'expansion_id' => 7, 'zone_name' => '2024', 'is_active' => true]);

    expect((new QuestProgressAggregator)->aggregate([], '')[7]['zones'][0]['name'])->toBe('2024');
});
