<?php

declare(strict_types=1);

use App\Application\Services\Progress\AchievementProgressAggregator;
use App\Models\WowAchievement;

test('aggregate groups achievements by expansion and category', function (): void {
    WowAchievement::factory()->create(['id' => 1, 'expansion_id' => 0, 'category_name' => 'Général', 'is_active' => true]);
    WowAchievement::factory()->create(['id' => 2, 'expansion_id' => 0, 'category_name' => 'Quêtes', 'is_active' => true]);
    WowAchievement::factory()->create(['id' => 3, 'expansion_id' => 10, 'category_name' => 'Donjons', 'is_active' => true]);

    $aggregator = new AchievementProgressAggregator;
    $result = $aggregator->aggregate([1, 3]);

    // Classic
    expect($result[0]['total'])->toBe(2);
    expect($result[0]['completed'])->toBe(1);
    expect($result[0]['categories'])->toHaveCount(2);

    // TWW
    expect($result[10]['total'])->toBe(1);
    expect($result[10]['completed'])->toBe(1);
});

test('aggregate skips inactive achievements', function (): void {
    WowAchievement::factory()->create(['id' => 10, 'expansion_id' => 0, 'category_name' => 'Général', 'is_active' => true]);
    WowAchievement::factory()->create(['id' => 11, 'expansion_id' => 0, 'category_name' => 'Général', 'is_active' => false]);

    $aggregator = new AchievementProgressAggregator;
    $result = $aggregator->aggregate([]);

    expect($result[0]['total'])->toBe(1);
});

test('aggregate returns a slot for every expansion plus the unclassified bucket', function (): void {
    $aggregator = new AchievementProgressAggregator;
    $result = $aggregator->aggregate([]);

    expect($result)->toHaveCount(13);
    expect(array_keys($result))->toBe([...range(0, 11), \App\Domain\ValueObjects\ExpansionId::UNCLASSIFIED]);
});

test('aggregate counts the achievements nothing dates instead of dropping them', function (): void {
    WowAchievement::factory()->create(['id' => 1685, 'expansion_id' => \App\Domain\ValueObjects\ExpansionId::UNCLASSIFIED, 'category_name' => 'Évènements mondiaux', 'is_active' => true]);

    $aggregator = new AchievementProgressAggregator;
    $result = $aggregator->aggregate([1685]);

    expect($result[\App\Domain\ValueObjects\ExpansionId::UNCLASSIFIED]['total'])->toBe(1)
        ->and($result[\App\Domain\ValueObjects\ExpansionId::UNCLASSIFIED]['completed'])->toBe(1)
        ->and($result[\App\Domain\ValueObjects\ExpansionId::UNCLASSIFIED]['categories'][0]['name'])->toBe('Évènements mondiaux');
});

test('aggregate marks correct items as completed in category', function (): void {
    WowAchievement::factory()->create(['id' => 100, 'expansion_id' => 9, 'category_name' => 'Exploration', 'is_active' => true]);
    WowAchievement::factory()->create(['id' => 101, 'expansion_id' => 9, 'category_name' => 'Exploration', 'is_active' => true]);

    $aggregator = new AchievementProgressAggregator;
    $result = $aggregator->aggregate([100]);

    $exploration = $result[9]['categories'][0];
    expect($exploration['name'])->toBe('Exploration');
    expect($exploration['total'])->toBe(2);
    expect($exploration['completed'])->toBe(1);

    $completedItem = collect($exploration['items'])->firstWhere('id', 100);
    $incompletedItem = collect($exploration['items'])->firstWhere('id', 101);
    expect($completedItem['is_completed'])->toBeTrue();
    expect($incompletedItem['is_completed'])->toBeFalse();
});

test('aggregate counts in its expansion an achievement without category but lists no category for it', function (): void {
    WowAchievement::factory()->create(['id' => 1, 'expansion_id' => 9, 'category_name' => null, 'is_active' => true]);
    WowAchievement::factory()->create(['id' => 2, 'expansion_id' => 9, 'category_name' => 'Exploration', 'is_active' => true]);

    $result = (new AchievementProgressAggregator)->aggregate([1]);

    expect($result[9]['total'])->toBe(2)
        ->and($result[9]['completed'])->toBe(1)
        ->and(array_column($result[9]['categories'], 'name'))->toBe(['Exploration']);
});

test('aggregate counts the completed achievements of a category', function (): void {
    foreach ([100, 101, 102] as $id) {
        WowAchievement::factory()->create(['id' => $id, 'expansion_id' => 9, 'category_name' => 'Exploration', 'is_active' => true]);
    }

    expect((new AchievementProgressAggregator)->aggregate([100, 102])[9]['categories'][0]['completed'])->toBe(2);
});

test('aggregate hands the front everything it displays for an achievement', function (): void {
    WowAchievement::factory()->create(['id' => 100, 'expansion_id' => 9, 'category_name' => 'Exploration', 'name_fr' => 'Explorateur', 'icon_url' => 'https://render.test/explorateur.jpg', 'is_active' => true]);

    expect((new AchievementProgressAggregator)->aggregate([100])[9]['categories'])->toBe([[
        'name' => 'Exploration',
        'total' => 1,
        'completed' => 1,
        'items' => [['id' => 100, 'name' => 'Explorateur', 'icon_url' => 'https://render.test/explorateur.jpg', 'is_completed' => true]],
    ]]);
});

test('aggregate keeps a numeric category name as text', function (): void {
    WowAchievement::factory()->create(['id' => 1, 'expansion_id' => 9, 'category_name' => '2024', 'is_active' => true]);

    expect((new AchievementProgressAggregator)->aggregate([])[9]['categories'][0]['name'])->toBe('2024');
});
