<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterDecorResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterMountsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterPetsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function collectionPayload(array $decoded): ResponsePayload
{
    return ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/collections', $decoded);
}

test('collected mounts are read from their mount reference', function (): void {
    expect(CharacterMountsResponse::fromPayload(collectionPayload(['mounts' => [['mount' => ['id' => 200]], ['mount' => ['id' => 201]]]]))->mountIds)
        ->toBe([200, 201]);
});

test('collected pets are read from their species, not from the pet instance', function (): void {
    expect(CharacterPetsResponse::fromPayload(collectionPayload(['pets' => [['id' => 9_999, 'species' => ['id' => 300]]]]))->speciesIds)
        ->toBe([300]);
});

test('collected decor is read from the decor_collected list', function (): void {
    expect(CharacterDecorResponse::fromPayload(collectionPayload(['decor_collected' => [['decor' => ['id' => 500]]]]))->decorIds)
        ->toBe([500]);
});

test('an empty or missing collection is an empty list', function (): void {
    expect(CharacterMountsResponse::fromPayload(collectionPayload([]))->mountIds)->toBe([])
        ->and(CharacterPetsResponse::fromPayload(collectionPayload([]))->speciesIds)->toBe([])
        ->and(CharacterDecorResponse::fromPayload(collectionPayload([]))->decorIds)->toBe([]);
});

test('a collected mount without its reference breaks the contract', function (): void {
    CharacterMountsResponse::fromPayload(collectionPayload(['mounts' => [['is_favorite' => true]]]));
})->throws(MissingFieldException::class, 'mounts.0.mount');
