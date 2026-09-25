<?php

declare(strict_types=1);

use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Inertia\Testing\AssertableInertia as Assert;

test('index renders DatabaseIndexPage with counts and sidebar props', function (): void {
    WowMount::factory()->count(2)->create(['is_active' => true]);

    $this->get('/base-de-donnees')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseIndexPage')
            ->where('counts.mounts', 2)
            ->has('subCategories')
            ->has('meta.title')
        );
});

test('mounts renders DatabaseMountsPage with items and categories', function (): void {
    WowMount::factory()->count(3)->create(['category' => 'Volantes', 'is_active' => true]);

    $this->get('/base-de-donnees/montures')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseMountsPage')
            ->where('total', 3)
            ->has('items', 3)
            ->has('categories')
            ->where('category', null)
        );
});

test('achievements renders paginated DatabaseAchievementsPage', function (): void {
    WowAchievement::factory()->count(2)->create(['is_active' => true, 'expansion_id' => 10]);

    $this->get('/base-de-donnees/hauts-faits')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseAchievementsPage')
            ->where('current_page', 1)
            ->has('items', 2)
            ->has('expansions')
        );
});

test('quests renders paginated DatabaseQuestsPage filtered by expansion', function (): void {
    WowQuest::factory()->count(2)->create(['is_active' => true, 'expansion_id' => 10]);

    $this->get('/base-de-donnees/quetes/the-war-within?search=chasse')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseQuestsPage')
            ->where('expansion', 'the-war-within')
            ->where('search', 'chasse')
            ->has('items')
            ->has('expansions')
        );
});

test('pets renders DatabasePetsPage filtered by category', function (): void {
    WowPet::factory()->count(3)->create(['category' => 'Sauvages', 'is_active' => true]);

    $this->get('/base-de-donnees/mascottes/sauvages')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabasePetsPage')
            ->where('category', 'sauvages')
            ->has('items', 3)
            ->has('categories')
        );
});

test('decors renders DatabaseDecorsPage filtered by category', function (): void {
    WowDecor::factory()->count(2)->create(['category' => 'Mobilier', 'is_active' => true]);

    $this->get('/base-de-donnees/decorations/mobilier')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseDecorsPage')
            ->where('category', 'mobilier')
            ->has('items', 2)
            ->has('categories')
        );
});

test('the garde-robe page renders DatabaseTransmogPage with slots and items', function (): void {
    WowAppearance::factory()->count(3)->create(['slot' => 'HEAD', 'category' => 'Armure', 'is_active' => true]);
    WowAppearance::factory()->count(2)->create(['slot' => 'WEAPON', 'category' => 'Arme', 'is_active' => true]);

    $this->get('/base-de-donnees/garde-robe')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseTransmogPage')
            ->has('slots')
            ->has('items', 5)
        );
});

test('the garde-robe page exposes a filtered slot', function (): void {
    WowAppearance::factory()->create(['slot' => 'HEAD', 'name_fr' => 'Casque légendaire', 'item_id' => 12345, 'is_active' => true]);

    $this->get('/base-de-donnees/garde-robe/head')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseTransmogPage')
            ->where('slot', 'head')
            ->has('items', 1)
        );
});

test('the garde-robe page redirects unknown slots', function (): void {
    WowAppearance::factory()->create(['slot' => 'HEAD', 'is_active' => true]);

    $this->get('/base-de-donnees/garde-robe/inexistant')
        ->assertRedirect('/base-de-donnees/garde-robe');
});

test('professions list renders DatabaseProfessionsPage without recipes', function (): void {
    $profession = WowProfession::factory()->create(['name_fr' => 'Alchimie', 'type' => 'primary', 'is_active' => true]);
    WowRecipe::factory()->count(2)->create(['profession_id' => $profession->id, 'is_active' => true]);

    $this->get('/base-de-donnees/professions')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseProfessionsPage')
            ->where('recipes', null)
            ->where('profession', null)
            ->has('professions', 1)
        );
});

test('professions detail exposes recipes for the selected profession', function (): void {
    $profession = WowProfession::factory()->create(['name_fr' => 'Alchimie', 'type' => 'primary', 'is_active' => true]);
    WowRecipe::factory()->count(2)->create(['profession_id' => $profession->id, 'expansion_id' => 10, 'is_active' => true]);

    $this->get('/base-de-donnees/professions/alchimie')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseProfessionsPage')
            ->where('profession', 'alchimie')
            ->has('recipes.items', 2)
        );
});

test('professions detail filters recipes by expansion and search', function (): void {
    $profession = WowProfession::factory()->create(['name_fr' => 'Alchimie', 'type' => 'primary', 'is_active' => true]);
    WowRecipe::factory()->create(['profession_id' => $profession->id, 'expansion_id' => 10, 'name_fr' => 'Potion de soin', 'is_active' => true]);
    WowRecipe::factory()->create(['profession_id' => $profession->id, 'expansion_id' => 9, 'name_fr' => 'Élixir de force', 'is_active' => true]);

    $this->get('/base-de-donnees/professions/alchimie?expansion=the-war-within&search=potion')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseProfessionsPage')
            ->where('expansion', 'the-war-within')
            ->where('search', 'potion')
            ->has('recipes.items', 1)
        );
});

test('mounts filtered by category exposes the category slug', function (): void {
    WowMount::factory()->count(2)->create(['category' => 'Volantes', 'is_active' => true]);
    WowMount::factory()->create(['category' => 'Terrestres', 'is_active' => true]);

    $this->get('/base-de-donnees/montures/volantes')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('DatabaseMountsPage')
            ->where('category', 'volantes')
            ->has('items', 2)
        );
});
