<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\ItemSearchDocument;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function itemSearchDocument(array $decoded): ItemSearchDocument
{
    return ItemSearchDocument::fromPayload(
        ResponsePayload::forEndpoint('data/wow/search/item', $decoded),
    );
}

test('a complete document carries everything the wardrobe needs', function (): void {
    $itemSearchDocument = itemSearchDocument([
        'id' => 19945,
        'name' => ['fr_FR' => 'Couvre-œil en écailles de lézard', 'en_US' => 'Lizardscale Eyepatch'],
        'quality' => ['type' => 'EPIC'],
        'media' => ['id' => 132759],
        'item_class' => ['id' => 4, 'name' => ['fr_FR' => 'Armure']],
        'appearances' => [['id' => 321], ['id' => 322]],
    ]);

    expect($itemSearchDocument->id)->toBe(19945)
        ->and($itemSearchDocument->nameFr)->toBe('Couvre-œil en écailles de lézard')
        ->and($itemSearchDocument->quality)->toBe(4)
        ->and($itemSearchDocument->mediaId)->toBe(132759)
        ->and($itemSearchDocument->categoryFr)->toBe('Armure')
        ->and($itemSearchDocument->appearanceIds)->toBe([321, 322]);
});

test('the english name stands in when the french one is missing', function (): void {
    $itemSearchDocument = itemSearchDocument([
        'id' => 10,
        'name' => ['en_US' => 'Lizardscale Eyepatch'],
    ]);

    expect($itemSearchDocument->nameFr)->toBe('Lizardscale Eyepatch');
});

test('a document without any name carries none', function (): void {
    expect(itemSearchDocument(['id' => 10])->nameFr)->toBeNull()
        ->and(itemSearchDocument(['id' => 10, 'name' => ['fr_FR' => '  ']])->nameFr)->toBeNull();
});

test('an absent or unknown quality falls back to common', function (): void {
    expect(itemSearchDocument(['id' => 10])->quality)->toBe(1)
        ->and(itemSearchDocument(['id' => 10, 'quality' => ['type' => 'MYTHIC']])->quality)->toBe(1);
});

test('a document linked to no appearance carries an empty list', function (): void {
    expect(itemSearchDocument(['id' => 10])->appearanceIds)->toBe([])
        ->and(itemSearchDocument(['id' => 10])->mediaId)->toBeNull()
        ->and(itemSearchDocument(['id' => 10])->categoryFr)->toBeNull();
});

test('a document without an id is rejected', function (): void {
    itemSearchDocument(['name' => ['fr_FR' => 'Sans identifiant']]);
})->throws(MissingFieldException::class, 'Missing field [id] in the response of [data/wow/search/item]');
