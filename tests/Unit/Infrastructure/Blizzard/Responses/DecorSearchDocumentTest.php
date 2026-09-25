<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\DecorSearchDocument;
use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function decorSearchDocument(array $decoded): DecorSearchDocument
{
    return DecorSearchDocument::fromPayload(
        ResponsePayload::forEndpoint('data/wow/search/decor', $decoded),
    );
}

test('a complete document carries the identity of a decor and the item it is bound to', function (): void {
    $decorSearchDocument = decorSearchDocument([
        'id' => 80,
        'name' => ['fr_FR' => 'Foyer orné en pierre', 'en_US' => 'Ornate Stonework Fireplace'],
        'item' => ['id' => 235994, 'name' => ['fr_FR' => 'Foyer orné en pierre']],
    ]);

    expect($decorSearchDocument->id)->toBe(80)
        ->and($decorSearchDocument->nameFr)->toBe('Foyer orné en pierre')
        ->and($decorSearchDocument->itemId)->toBe(235994);
});

test('it falls back to the American name when the French one is missing', function (): void {
    expect(decorSearchDocument(['id' => 80, 'name' => ['en_US' => 'Ornate Stonework Fireplace']])->nameFr)
        ->toBe('Ornate Stonework Fireplace');
});

test('it reads a decor bound to no item, which carries neither icon nor quality', function (): void {
    expect(decorSearchDocument(['id' => 80, 'name' => ['fr_FR' => 'Foyer orné en pierre']])->itemId)->toBeNull();
});

test('it refuses a document without an identifier rather than importing it as zero', function (): void {
    decorSearchDocument(['name' => ['fr_FR' => 'Foyer orné en pierre']]);
})->throws(MissingFieldException::class);
