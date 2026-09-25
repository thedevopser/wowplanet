<?php

declare(strict_types=1);

use App\Application\Services\Progress\CollectionProgressAggregator;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;

test('aggregateMounts returns mount list with completion status and category', function (): void {
    WowMount::factory()->create(['id' => 1, 'name_fr' => 'Loup', 'source' => 'Vendeur', 'category' => 'Classic']);
    WowMount::factory()->create(['id' => 2, 'name_fr' => 'Cheval', 'source' => 'Quête', 'category' => 'The War Within']);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateMounts([1]);

    expect($result)->toHaveCount(2);

    $loup = collect($result)->firstWhere('id', 1);
    $cheval = collect($result)->firstWhere('id', 2);
    expect($loup['is_completed'])->toBeTrue();
    expect($loup['source'])->toBe('Vendeur');
    expect($loup['category'])->toBe('Classic');
    expect($cheval['is_completed'])->toBeFalse();
    expect($cheval['category'])->toBe('The War Within');
});

test('aggregatePets returns pet list with completion status', function (): void {
    WowPet::factory()->create(['id' => 10, 'name_fr' => 'Chat']);
    WowPet::factory()->create(['id' => 11, 'name_fr' => 'Chien']);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregatePets([10, 11]);

    expect($result)->toHaveCount(2);
    expect(collect($result)->every('is_completed', true))->toBeTrue();
});

test('aggregateDecor returns decor list with completion status and category', function (): void {
    WowDecor::factory()->create(['id' => 100, 'name_fr' => 'Statue', 'item_id' => 999, 'category' => 'The War Within', 'source' => 'Quest']);
    WowDecor::factory()->create(['id' => 101, 'name_fr' => 'Torche', 'item_id' => 998, 'category' => 'Midnight', 'source' => 'Vendor']);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateDecor([100]);

    expect($result)->toHaveCount(2);

    $statue = collect($result)->firstWhere('id', 100);
    $torche = collect($result)->firstWhere('id', 101);
    expect($statue['is_completed'])->toBeTrue();
    expect($statue['item_id'])->toBe(999);
    expect($statue['category'])->toBe('The War Within');
    expect($statue['source'])->toBe('Quest');
    expect($torche['is_completed'])->toBeFalse();
    expect($torche['category'])->toBe('Midnight');
    expect($torche['source'])->toBe('Vendor');
});

test('aggregateAppearances returns per-slot completed/total counters', function (): void {
    WowAppearance::factory()->create(['id' => 1, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 2, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 3, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 4, 'slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 5, 'slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 6, 'slot' => 'HEAD', 'category' => 'Armure', 'is_active' => false]);

    $aggregator = new CollectionProgressAggregator;
    // débloqué : 2 têtes actives + 1 tête inactive (ignorée) + 1 id inconnu (ignoré)
    $result = $aggregator->aggregateAppearances([1, 2, 6, 999]);

    $head = collect($result)->firstWhere('slot', 'HEAD');
    $weapon = collect($result)->firstWhere('slot', 'WEAPON');

    expect($head['total'])->toBe(3)
        ->and($head['completed'])->toBe(2)
        ->and($head['category'])->toBe('Armure')
        ->and($weapon['total'])->toBe(2)
        ->and($weapon['completed'])->toBe(0);
});

test('aggregateAppearances emits a single row per slot whatever the raw categories', function (): void {
    // item_class.name brut de Blizzard : un même slot porte des catégories parasites.
    WowAppearance::factory()->create(['id' => 1, 'slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 2, 'slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 3, 'slot' => 'WEAPON', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 4, 'slot' => 'WEAPON', 'category' => 'Quête', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 5, 'slot' => 'WEAPON', 'category' => null, 'is_active' => true]);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateAppearances([1, 2, 3]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['slot'])->toBe('WEAPON')
        ->and($result[0]['total'])->toBe(5)
        ->and($result[0]['completed'])->toBe(3)
        ->and($result[0]['category'])->toBe('Arme');
});

test('aggregateAppearances never counts an unlocked appearance twice', function (): void {
    WowAppearance::factory()->create(['id' => 1, 'slot' => 'WAIST', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 2, 'slot' => 'WAIST', 'category' => 'Quête', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 3, 'slot' => 'SHIELD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 4, 'slot' => 'SHIELD', 'category' => 'Arme', 'is_active' => true]);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateAppearances([1, 2, 3, 4]);

    // La somme des completed doit rester égale au nombre réel d'apparences débloquées.
    expect(array_sum(array_column($result, 'completed')))->toBe(4);

    foreach ($result as $row) {
        expect($row['completed'])->toBeLessThanOrEqual($row['total']);
    }
});

test('aggregateAppearances derives category from the slot map, not from the stored column', function (): void {
    // SHIELD est de l'armure en jeu, même si des lignes portent item_class « Arme ».
    WowAppearance::factory()->create(['id' => 1, 'slot' => 'SHIELD', 'category' => 'Arme', 'is_active' => true]);
    WowAppearance::factory()->create(['id' => 2, 'slot' => 'UNKNOWN_SLOT', 'category' => 'Armure', 'is_active' => true]);

    $aggregator = new CollectionProgressAggregator;
    $result = collect($aggregator->aggregateAppearances([]));

    expect($result->firstWhere('slot', 'SHIELD')['category'])->toBe('Armure')
        ->and($result->firstWhere('slot', 'UNKNOWN_SLOT'))->not->toBeNull()
        ->and($result->firstWhere('slot', 'UNKNOWN_SLOT')['category'])->toBeNull();
});

test('aggregateAppearances returns zero completed when nothing unlocked', function (): void {
    WowAppearance::factory()->create(['slot' => 'HEAD', 'is_active' => true]);

    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateAppearances([]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['completed'])->toBe(0)
        ->and($result[0]['total'])->toBe(1);
});

test('aggregateMounts returns empty array when no mounts exist', function (): void {
    $aggregator = new CollectionProgressAggregator;
    $result = $aggregator->aggregateMounts([1, 2, 3]);

    expect($result)->toBe([]);
});

test('aggregateMounts hands the front everything it displays for a mount', function (): void {
    WowMount::factory()->create(['id' => 1, 'name_fr' => 'Loup', 'source' => 'Vendeur', 'category' => 'Classic', 'source_spell_id' => 580, 'icon_url' => 'https://render.test/loup.jpg']);

    expect((new CollectionProgressAggregator)->aggregateMounts([1]))->toBe([[
        'id' => 1,
        'name' => 'Loup',
        'is_completed' => true,
        'source' => 'Vendeur',
        'category' => 'Classic',
        'wowhead_id' => 580,
        'icon_url' => 'https://render.test/loup.jpg',
    ]]);
});

test('aggregatePets hands the front everything it displays for a pet', function (): void {
    WowPet::factory()->create(['id' => 10, 'name_fr' => 'Chat', 'source' => 'Butin', 'category' => 'Bête', 'creature_id' => 7385, 'icon_url' => 'https://render.test/chat.jpg']);

    expect((new CollectionProgressAggregator)->aggregatePets([]))->toBe([[
        'id' => 10,
        'name' => 'Chat',
        'is_completed' => false,
        'source' => 'Butin',
        'category' => 'Bête',
        'wowhead_id' => 7385,
        'icon_url' => 'https://render.test/chat.jpg',
    ]]);
});

test('aggregateDecor hands the front everything it displays for a decor', function (): void {
    WowDecor::factory()->create(['id' => 100, 'name_fr' => 'Statue', 'item_id' => 999, 'icon_url' => 'https://render.test/statue.jpg', 'category' => 'Midnight', 'source' => 'Quest']);

    expect((new CollectionProgressAggregator)->aggregateDecor([100]))->toBe([[
        'id' => 100,
        'name' => 'Statue',
        'is_completed' => true,
        'item_id' => 999,
        'icon_url' => 'https://render.test/statue.jpg',
        'category' => 'Midnight',
        'source' => 'Quest',
    ]]);
});
