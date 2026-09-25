<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\MediaIconResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function mediaIcon(array $decoded): MediaIconResponse
{
    return MediaIconResponse::fromPayload(ResponsePayload::forEndpoint('data/wow/media/item/12345', $decoded));
}

test('the icon is the first asset keyed icon', function (): void {
    expect(mediaIcon(['assets' => [
        ['key' => 'other', 'value' => 'https://render.com/other.jpg'],
        ['key' => 'icon', 'value' => 'https://render.com/icon.jpg'],
        ['key' => 'icon', 'value' => 'https://render.com/second-icon.jpg'],
    ]])->iconUrl)->toBe('https://render.com/icon.jpg');
});

test('an icon asset without value is ignored', function (): void {
    expect(mediaIcon(['assets' => [['key' => 'icon'], ['key' => 'icon', 'value' => 'https://render.com/icon.jpg']]])->iconUrl)
        ->toBe('https://render.com/icon.jpg');
});

test('media without an icon asset has no icon', function (): void {
    expect(mediaIcon(['assets' => [['key' => 'other', 'value' => 'https://render.com/other.jpg']]])->iconUrl)->toBeNull()
        ->and(mediaIcon([])->iconUrl)->toBeNull();
});
