<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\MountSearchDocument;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function mountSearchDocument(array $decoded): MountSearchDocument
{
    return MountSearchDocument::fromPayload(
        ResponsePayload::forEndpoint('data/wow/search/mount', $decoded),
    );
}

test('a complete document carries the identity of a mount', function (): void {
    $mountSearchDocument = mountSearchDocument([
        'id' => 14,
        'name' => ['fr_FR' => 'Loup des bois', 'en_US' => 'Timber Wolf'],
        'source' => ['type' => 'VENDOR', 'name' => ['fr_FR' => 'Vendeur']],
    ]);

    expect($mountSearchDocument->id)->toBe(14)
        ->and($mountSearchDocument->nameFr)->toBe('Loup des bois')
        ->and($mountSearchDocument->sourceType)->toBe('VENDOR');
});

test('it falls back to the American name when the French one is missing', function (): void {
    expect(mountSearchDocument(['id' => 14, 'name' => ['en_US' => 'Timber Wolf']])->nameFr)->toBe('Timber Wolf');
});

test('it trims a name the API returns padded', function (): void {
    expect(mountSearchDocument(['id' => 14, 'name' => ['fr_FR' => "Loup des bois\r\n"]])->nameFr)->toBe('Loup des bois');
});

test('it reads a mount the API ranks under no source at all', function (): void {
    expect(mountSearchDocument(['id' => 14, 'name' => ['fr_FR' => 'Loup des bois']])->sourceType)->toBeNull();
});

test('it refuses a document without an identifier rather than importing it as zero', function (): void {
    mountSearchDocument(['name' => ['fr_FR' => 'Loup des bois']]);
})->throws(MissingFieldException::class);
