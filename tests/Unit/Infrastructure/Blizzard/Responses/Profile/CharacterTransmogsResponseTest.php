<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Profile\CharacterTransmogsResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function characterTransmogs(array $decoded): CharacterTransmogsResponse
{
    return CharacterTransmogsResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/collections/transmogs', $decoded),
    );
}

test('unlocked appearances are flattened across slots', function (): void {
    $characterTransmogsResponse = characterTransmogs(['slots' => [
        ['slot' => ['type' => 'HEAD'], 'appearances' => [['id' => 321], ['id' => 322]]],
        ['slot' => ['type' => 'CHEST'], 'appearances' => [['id' => 400]]],
    ]]);

    expect($characterTransmogsResponse->appearanceIds)->toBe([321, 322, 400]);
});

test('a slot without appearances and an appearance without id are skipped', function (): void {
    $characterTransmogsResponse = characterTransmogs(['slots' => [
        ['slot' => ['type' => 'HEAD']],
        ['slot' => ['type' => 'CHEST'], 'appearances' => [['name' => 'no id'], ['id' => 400]]],
    ]]);

    expect($characterTransmogsResponse->appearanceIds)->toBe([400]);
});

test('an appearance id served as a numeric string is still read', function (): void {
    expect(characterTransmogs(['slots' => [['appearances' => [['id' => '9002'], ['id' => 'abc'], ['id' => 9003]]]]])->appearanceIds)
        ->toBe([9002, 9003]);
});

test('a character without transmog collection has no appearance', function (): void {
    expect(characterTransmogs([])->appearanceIds)->toBe([]);
});
