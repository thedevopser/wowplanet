<?php

declare(strict_types=1);

use App\Application\Services\DatabaseQueryService;
use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowMount;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;

beforeEach(function (): void {
    $this->service = resolve(DatabaseQueryService::class);
});

test('quest search ignores the case of the term', function (): void {
    WowQuest::factory()->create(['name_fr' => 'Bouclier des Anciens', 'expansion_id' => 10, 'is_active' => true]);

    expect($this->service->quests(search: 'bouclier')['total'])->toBe(1)
        ->and($this->service->quests(search: 'BOUCLIER')['total'])->toBe(1)
        ->and($this->service->quests(search: 'Bouclier')['total'])->toBe(1);
});

test('achievement search ignores the case of the term', function (): void {
    WowAchievement::factory()->create(['name_fr' => 'Bouclier sans faille', 'expansion_id' => 10, 'is_active' => true]);

    expect($this->service->achievements(search: 'bouclier')['total'])->toBe(1)
        ->and($this->service->achievements(search: 'BOUCLIER')['total'])->toBe(1)
        ->and($this->service->achievements(search: 'Bouclier')['total'])->toBe(1);
});

test('appearance search ignores the case of the term', function (): void {
    WowAppearance::factory()->create(['name_fr' => 'Bouclier de la Garde', 'is_active' => true]);

    expect($this->service->appearances(search: 'bouclier')['total'])->toBe(1)
        ->and($this->service->appearances(search: 'BOUCLIER')['total'])->toBe(1)
        ->and($this->service->appearances(search: 'Bouclier')['total'])->toBe(1);
});

test('recipe search ignores the case of the term', function (): void {
    $profession = WowProfession::factory()->create(['name_fr' => 'Alchimie', 'type' => 'primary', 'is_active' => true]);
    WowRecipe::factory()->create([
        'profession_id' => $profession->id,
        'name_fr' => 'Potion de soin',
        'expansion_id' => 10,
        'is_active' => true,
    ]);

    expect($this->service->professionRecipes('alchimie', search: 'potion')['total'])->toBe(1)
        ->and($this->service->professionRecipes('alchimie', search: 'POTION')['total'])->toBe(1)
        ->and($this->service->professionRecipes('alchimie', search: 'Potion')['total'])->toBe(1);
});

test('search still narrows the results down to the matching term', function (): void {
    WowQuest::factory()->create(['name_fr' => 'Bouclier des Anciens', 'expansion_id' => 10, 'is_active' => true]);
    WowQuest::factory()->create(['name_fr' => 'Épée de lumière', 'expansion_id' => 10, 'is_active' => true]);

    expect($this->service->quests(search: 'bouclier')['total'])->toBe(1)
        ->and($this->service->quests(search: 'lame')['total'])->toBe(0)
        ->and($this->service->quests()['total'])->toBe(2);
});

test('catalogue counts are kept for an hour, then read again', function (): void {
    WowMount::factory()->count(2)->create(['is_active' => true]);

    expect($this->service->cachedCounts()['mounts'])->toBe(2);

    WowMount::factory()->create(['is_active' => true]);
    expect($this->service->cachedCounts()['mounts'])->toBe(2);

    $this->travel(61)->minutes();
    expect($this->service->cachedCounts()['mounts'])->toBe(3);
});

test('sidebar subcategories are kept for an hour, then read again', function (): void {
    WowMount::factory()->create(['is_active' => true, 'category' => 'Legion']);

    expect($this->service->cachedSubcategories()['mounts'])->toBe([['name' => 'Legion', 'slug' => 'legion', 'count' => 1]]);

    WowMount::factory()->create(['is_active' => true, 'category' => 'Legion']);
    expect($this->service->cachedSubcategories()['mounts'][0]['count'])->toBe(1);

    $this->travel(61)->minutes();
    expect($this->service->cachedSubcategories()['mounts'][0]['count'])->toBe(2);
});

test('sidebar subcategories cover every section of the database sidebar', function (): void {
    expect(array_keys($this->service->cachedSubcategories()))
        ->toBe(['mounts', 'achievements', 'quests', 'pets', 'decors', 'appearances', 'professions']);
});

test('forgetting the sidebar makes both its counts and its subcategories read the catalogue again', function (): void {
    WowMount::factory()->create(['is_active' => true, 'category' => 'Legion']);
    $this->service->cachedCounts();
    $this->service->cachedSubcategories();

    WowMount::factory()->create(['is_active' => true, 'category' => 'Legion']);
    $this->service->forgetSidebar();

    expect($this->service->cachedCounts()['mounts'])->toBe(2)
        ->and($this->service->cachedSubcategories()['mounts'][0]['count'])->toBe(2);
});
