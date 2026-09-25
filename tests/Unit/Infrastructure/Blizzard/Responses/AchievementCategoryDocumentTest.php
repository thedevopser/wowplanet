<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\AchievementCategoryDocument;
use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function achievementCategoryDocument(array $decoded): AchievementCategoryDocument
{
    return AchievementCategoryDocument::fromPayload(
        ResponsePayload::forEndpoint('data/wow/achievement-category/96', $decoded),
    );
}

test('a root category carries its own achievements and no parent', function (): void {
    $achievementCategoryDocument = achievementCategoryDocument([
        'id' => 96,
        'name' => 'Quêtes',
        'achievements' => [
            ['id' => 504, 'name' => '100 quêtes achevées'],
            ['id' => 505, 'name' => '500 quêtes achevées'],
        ],
        'subcategories' => [['id' => 15081, 'name' => 'Kalimdor']],
        'is_guild_category' => false,
    ]);

    expect($achievementCategoryDocument->id)->toBe(96)
        ->and($achievementCategoryDocument->name)->toBe('Quêtes')
        ->and($achievementCategoryDocument->parentId)->toBeNull()
        ->and($achievementCategoryDocument->achievements)->toBe([504 => '100 quêtes achevées', 505 => '500 quêtes achevées']);
});

test('a subcategory carries the identifier of its parent', function (): void {
    $achievementCategoryDocument = achievementCategoryDocument([
        'id' => 15547,
        'name' => 'Midnight',
        'achievements' => [['id' => 41802, 'name' => 'Reprise des Chants éternels']],
        'parent_category' => ['id' => 96, 'name' => 'Quêtes'],
        'is_guild_category' => false,
    ]);

    expect($achievementCategoryDocument->parentId)->toBe(96)
        ->and($achievementCategoryDocument->achievements)->toBe([41802 => 'Reprise des Chants éternels']);
});

test('a category holding no achievement at all is a valid category', function (): void {
    $achievementCategoryDocument = achievementCategoryDocument([
        'id' => 15301,
        'name' => 'Contenu d’extension',
    ]);

    expect($achievementCategoryDocument->achievements)->toBe([]);
});

test('the whitespace Blizzard leaves around a name is trimmed', function (): void {
    $achievementCategoryDocument = achievementCategoryDocument([
        'id' => 96,
        'name' => '  Quêtes ',
        'achievements' => [['id' => 13503, 'name' => "Zandalar et la manière\r\n"]],
    ]);

    expect($achievementCategoryDocument->name)->toBe('Quêtes')
        ->and($achievementCategoryDocument->achievements)->toBe([13503 => 'Zandalar et la manière']);
});

test('a nameless category breaks the contract', function (): void {
    achievementCategoryDocument(['id' => 96]);
})->throws(MissingFieldException::class);
