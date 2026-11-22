<?php

declare(strict_types=1);

use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowMount;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Inertia\Testing\AssertableInertia;

test('an english page of the database lists the entries by their english name', function (): void {
    WowMount::factory()->create(['id' => 1, 'name_fr' => 'Aigle', 'name_en' => 'Zebra Eagle', 'is_active' => true]);
    WowMount::factory()->create(['id' => 2, 'name_fr' => 'Zèbre', 'name_en' => 'Alpine Zebra', 'is_active' => true]);

    $this->get('/en/database/mounts')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page
            ->where('items.0.id', 2)
            ->where('items.0.name_en', 'Alpine Zebra')
            ->where('items.1.id', 1)
    );
});

test('a french page of the database keeps the french order', function (): void {
    WowMount::factory()->create(['id' => 1, 'name_fr' => 'Aigle', 'name_en' => 'Zebra Eagle', 'is_active' => true]);
    WowMount::factory()->create(['id' => 2, 'name_fr' => 'Zèbre', 'name_en' => 'Alpine Zebra', 'is_active' => true]);

    $this->get('/base-de-donnees/montures')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->where('items.0.id', 1)
    );
});

test('an entry without an english name falls back to its french name in english order', function (): void {
    WowMount::factory()->create(['id' => 1, 'name_fr' => 'Monture B', 'name_en' => null, 'is_active' => true]);
    WowMount::factory()->create(['id' => 2, 'name_fr' => 'Zèbre', 'name_en' => 'A Mount', 'is_active' => true]);

    $this->get('/en/database/mounts')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->where('items.0.id', 2)->where('items.1.id', 1)
    );
});

test('an english search matches the english names', function (): void {
    WowQuest::factory()->create(['id' => 1, 'name_fr' => 'La tâche', 'name_en' => 'The Chore', 'is_active' => true]);
    WowQuest::factory()->create(['id' => 2, 'name_fr' => 'Autre', 'name_en' => 'Other', 'is_active' => true]);

    $this->get('/en/database/quests?search=chore')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->where('total', 1)->where('items.0.id', 1)
    );
});

test('the english database pages carry english meta and their own canonical url', function (): void {
    $this->get('/en/database/mounts')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page
            ->where('meta.canonicalUrl', url('/en/database/mounts'))
            ->where('meta.title', fn (string $title): bool => str_starts_with($title, 'WoW Mounts'))
    );
});

test('a profession keeps the slug of its french name and shows its english name under the english prefix', function (): void {
    $wowProfession = WowProfession::factory()->create(['id' => 164, 'name_fr' => 'Forge', 'name_en' => 'Blacksmithing', 'type' => 'primary']);
    WowRecipe::factory()->create(['profession_id' => $wowProfession->id, 'is_active' => true]);

    $this->get('/en/database/professions/forge')->assertOk()->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page
            ->where('recipes.profession.name', 'Blacksmithing')
            ->where('meta.title', fn (string $title): bool => str_contains($title, 'Blacksmithing'))
    );
});

test('the wardrobe slots are named in the language of the page', function (): void {
    WowAppearance::factory()->create(['slot' => 'HEAD', 'is_active' => true]);

    $this->get('/en/database/appearances')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->where('slots.0.name', 'Head')
    );
    $this->get('/base-de-donnees/garde-robe')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->where('slots.0.name', 'Tête')
    );
});

test('the sidebar cached for one language is never served to the other', function (): void {
    WowAppearance::factory()->create(['slot' => 'HEAD', 'is_active' => true]);

    $this->get('/base-de-donnees')->assertOk();

    $this->get('/en/database')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->where('subCategories.appearances.0.name', 'Head')
    );
});

test('the database serves the english category of achievements and recipes', function (): void {
    WowAchievement::factory()->create(['id' => 1, 'expansion_id' => 10, 'category_name' => 'Quêtes', 'category_name_en' => 'Quests', 'is_active' => true]);

    $this->get('/en/database/achievements')->assertInertia(
        fn (AssertableInertia $page): AssertableInertia => $page->where('items.0.category_name_en', 'Quests')
    );
});
