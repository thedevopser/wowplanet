<?php

declare(strict_types=1);

use App\Application\Health\CatalogueVolumetry;
use App\Models\CharacterTask;
use App\Models\WowMount;
use App\Models\WowQuest;

/**
 * @return array<string, array{table: string, family: string, rows: int, active: int|null, without_icon: int|null, status: string, issue: string|null}>
 */
function volumesByTable(): array
{
    $volumes = [];

    foreach (resolve(CatalogueVolumetry::class)->volumes() as $tableVolume) {
        $volumes[$tableVolume->table] = $tableVolume->toArray();
    }

    return $volumes;
}

test('it counts the rows, active rows and iconless rows of a catalogue table', function (): void {
    WowMount::factory()->create(['is_active' => true, 'icon_url' => 'https://example.test/a.jpg']);
    WowMount::factory()->create(['is_active' => true, 'icon_url' => null]);
    WowMount::factory()->create(['is_active' => false, 'icon_url' => '']);

    expect(volumesByTable()['wow_mounts'])->toMatchArray([
        'family' => 'catalogue',
        'rows' => 3,
        'active' => 2,
        'without_icon' => 2,
        'status' => 'ok',
    ]);
});

test('a catalogue table without an icon column has no iconless count', function (): void {
    WowQuest::factory()->create();

    expect(volumesByTable()['wow_quests'])->toMatchArray(['rows' => 1, 'active' => 1, 'without_icon' => null]);
});

test('every catalogue table is measured, recipes included', function (): void {
    expect(array_keys(array_filter(volumesByTable(), static fn (array $volume): bool => $volume['family'] === 'catalogue')))
        ->toBe(['wow_achievements', 'wow_quests', 'wow_professions', 'wow_recipes', 'wow_mounts', 'wow_pets', 'wow_decors', 'wow_appearances']);
});

test('an empty catalogue table is flagged', function (): void {
    expect(volumesByTable()['wow_pets'])->toMatchArray(['rows' => 0, 'status' => 'critical']);
});

test('application tables are counted without any anomaly when empty', function (): void {
    CharacterTask::factory()->create();

    $volumes = volumesByTable();

    expect($volumes['character_tasks'])->toMatchArray(['family' => 'application', 'rows' => 1, 'active' => null])
        ->and($volumes['character_favorites'])->toMatchArray(['rows' => 0, 'status' => 'ok'])
        ->and(array_keys(array_filter($volumes, static fn (array $volume): bool => $volume['family'] === 'application')))
        ->toBe(['users', 'character_tasks', 'character_favorites', 'character_visits', 'cross_character_data']);
});
