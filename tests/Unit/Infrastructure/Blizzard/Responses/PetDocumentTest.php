<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\PetDocument;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function petDocument(array $decoded): PetDocument
{
    return PetDocument::fromPayload(
        ResponsePayload::forEndpoint('data/wow/pet/39', $decoded),
    );
}

test('a complete detail carries everything the pet catalog needs', function (): void {
    $petDocument = petDocument([
        'id' => 39,
        'name' => 'Écureuil mécanique',
        'icon' => 'https://render.worldofwarcraft.com/eu/icons/56/656559.jpg',
        'creature' => ['id' => 2671, 'name' => 'Écureuil mécanique'],
        'source' => ['type' => 'PROFESSION', 'name' => 'Métier'],
    ]);

    expect($petDocument->id)->toBe(39)
        ->and($petDocument->nameFr)->toBe('Écureuil mécanique')
        ->and($petDocument->iconUrl)->toBe('https://render.worldofwarcraft.com/eu/icons/56/656559.jpg')
        ->and($petDocument->creatureId)->toBe(2671)
        ->and($petDocument->sourceType)->toBe('PROFESSION');
});

test('a detail is served in one locale, so the name is read as plain text', function (): void {
    expect(petDocument(['id' => 39, 'name' => "Écureuil mécanique\r\n"])->nameFr)->toBe('Écureuil mécanique');
});

test('it reads a pet bound to no creature, which leaves its Wowhead link to a name search', function (): void {
    expect(petDocument(['id' => 39, 'name' => 'Écureuil'])->creatureId)->toBeNull();
});

test('it reads a pet the API serves without an icon', function (): void {
    expect(petDocument(['id' => 39, 'name' => 'Écureuil', 'icon' => ' '])->iconUrl)->toBeNull();
});

test('it refuses a detail without an identifier rather than importing it as zero', function (): void {
    petDocument(['name' => 'Écureuil']);
})->throws(MissingFieldException::class);
