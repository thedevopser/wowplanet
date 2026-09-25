<?php

declare(strict_types=1);

use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Blizzard\AchievementTaxonomy;
use App\Infrastructure\Blizzard\Responses\AchievementCategoryDocument;

/**
 * @param  array<int, string>  $achievements
 */
function category(int $id, string $name, ?int $parentId, array $achievements): AchievementCategoryDocument
{
    return new AchievementCategoryDocument($id, $name, $parentId, $achievements);
}

test('the root category names the achievement and the subcategory dates it', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([
        category(96, 'Quêtes', null, [504 => '100 quêtes achevées']),
        category(15547, 'Midnight', 96, [41802 => 'Reprise des Chants éternels']),
        category(14863, 'Norfendre', 96, [1234 => 'Loremaster of Northrend']),
    ]);

    $placements = collect($achievementTaxonomy->placements())->keyBy('id');

    expect($placements[41802]->name)->toBe('Reprise des Chants éternels')
        ->and($placements[41802]->categoryName)->toBe('Quêtes')
        ->and($placements[41802]->expansionId)->toBe(ExpansionId::MIDNIGHT)
        ->and($placements[1234]->expansionId)->toBe(ExpansionId::WRATH_OF_THE_LICH_KING)
        ->and($placements[504]->categoryName)->toBe('Quêtes')
        ->and($placements[504]->expansionId)->toBe(ExpansionId::UNCLASSIFIED);
});

test('a subcategory tied to no expansion sends its achievements to the unclassified bucket', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([
        category(155, 'Évènements mondiaux', null, []),
        category(156, "Voile d'hiver", 155, [1685 => 'Joyeuses fêtes !']),
    ]);

    $placements = $achievementTaxonomy->placements();

    expect($placements[0]->expansionId)->toBe(ExpansionId::UNCLASSIFIED)
        ->and($placements[0]->categoryName)->toBe('Évènements mondiaux')
        ->and($achievementTaxonomy->unrankedCategories())->toBe(["Évènements mondiaux > Voile d'hiver" => 1]);
});

test('achievements held by a root itself are reported under that root', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([
        category(96, 'Quêtes', null, [504 => '100 quêtes achevées', 505 => '500 quêtes achevées']),
    ]);

    expect($achievementTaxonomy->unrankedCategories())->toBe(['Quêtes' => 2]);
});

test('the expansion is read from the nearest dated ancestor', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([
        category(168, 'Donjons et raids', null, []),
        category(15570, 'Raids de Midnight', 168, []),
        category(99001, 'Première aile', 15570, [70001 => 'Aile ouverte']),
    ]);

    expect($achievementTaxonomy->placements()[0]->expansionId)->toBe(ExpansionId::MIDNIGHT)
        ->and($achievementTaxonomy->placements()[0]->categoryName)->toBe('Donjons et raids');
});

test('a category whose parent is missing stands as its own root', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([
        category(15547, 'Midnight', 96, [41802 => 'Reprise des Chants éternels']),
    ]);

    expect($achievementTaxonomy->placements()[0]->categoryName)->toBe('Midnight')
        ->and($achievementTaxonomy->placements()[0]->expansionId)->toBe(ExpansionId::UNCLASSIFIED);
});

test('a parent cycle does not hang the walk up the tree', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([
        category(1, 'Boucle A', 2, [10 => 'Tourne en rond']),
        category(2, 'Boucle B', 1, []),
    ]);

    expect($achievementTaxonomy->placements())->toHaveCount(1);
});

test('an achievement listed twice takes the dated placement', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([
        category(81, 'Tours de force', null, [9000 => 'Double']),
        category(168, 'Donjons et raids', null, []),
        category(15570, 'Raids de Midnight', 168, [9000 => 'Double']),
    ]);

    $placements = $achievementTaxonomy->placements();

    expect($placements)->toHaveCount(1)
        ->and($placements[0]->expansionId)->toBe(ExpansionId::MIDNIGHT)
        ->and($placements[0]->categoryName)->toBe('Donjons et raids');
});

test('two undated placements of the same achievement are settled by the smallest category', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([
        category(200, 'Seconde', null, [9000 => 'Double']),
        category(81, 'Première', null, [9000 => 'Double']),
    ]);

    expect($achievementTaxonomy->placements()[0]->categoryName)->toBe('Première');
});

test('a place-named category receiving recent content is flagged as going stale', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([
        category(96, 'Quêtes', null, []),
        category(14863, 'Norfendre', 96, [1596 => 'Ancien', 41999 => 'Bien trop récent']),
        category(15081, 'Kalimdor', 96, [5547 => 'Ancien aussi']),
    ]);

    expect($achievementTaxonomy->staleDatingSignals())->toBe(['Quêtes > Norfendre: 41999 Bien trop récent']);
});

test('no category at all is no placement at all', function (): void {
    $achievementTaxonomy = AchievementTaxonomy::fromCategories([]);

    expect($achievementTaxonomy->placements())->toBe([])
        ->and($achievementTaxonomy->unrankedCategories())->toBe([])
        ->and($achievementTaxonomy->staleDatingSignals())->toBe([]);
});
