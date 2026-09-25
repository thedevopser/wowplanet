<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Profile\CharacterMediaResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function characterMedia(array $decoded): CharacterMediaResponse
{
    return CharacterMediaResponse::fromPayload(
        ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/character-media', $decoded),
    );
}

test('the avatar is the second asset, the inset portrait', function (): void {
    $characterMediaResponse = characterMedia(['assets' => [
        ['key' => 'avatar', 'value' => 'https://render.com/avatar.jpg'],
        ['key' => 'inset', 'value' => 'https://render.com/inset.jpg'],
    ]]);

    expect($characterMediaResponse->avatarUrl)->toBe('https://render.com/inset.jpg');
});

test('the avatar falls back to the first asset when it is the only one', function (): void {
    expect(characterMedia(['assets' => [['key' => 'avatar', 'value' => 'https://render.com/avatar.jpg']]])->avatarUrl)
        ->toBe('https://render.com/avatar.jpg');
});

test('the avatar falls back to the first asset when the second has no value', function (): void {
    expect(characterMedia(['assets' => [['key' => 'avatar', 'value' => 'https://render.com/avatar.jpg'], ['key' => 'inset']]])->avatarUrl)
        ->toBe('https://render.com/avatar.jpg');
});

test('a character without media has no avatar', function (): void {
    expect(characterMedia([])->avatarUrl)->toBeNull();
});
