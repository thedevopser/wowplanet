<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\MediaSearchDocument;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function mediaSearchDocument(array $decoded): MediaSearchDocument
{
    return MediaSearchDocument::fromPayload(
        ResponsePayload::forEndpoint('data/wow/search/media', $decoded),
    );
}

test('a complete document carries the icon and its file data id', function (): void {
    $mediaSearchDocument = mediaSearchDocument([
        'id' => 19945,
        'assets' => [
            ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg', 'file_data_id' => 132759],
        ],
    ]);

    expect($mediaSearchDocument->id)->toBe(19945)
        ->and($mediaSearchDocument->iconUrl)->toBe('https://render.worldofwarcraft.com/eu/icons/56/a.jpg')
        ->and($mediaSearchDocument->fileDataId)->toBe(132759);
});

test('a document whose assets hold no icon carries none', function (): void {
    $mediaSearchDocument = mediaSearchDocument([
        'id' => 19945,
        'assets' => [['key' => 'model', 'value' => 'https://render.worldofwarcraft.com/eu/models/1.mo3']],
    ]);

    expect($mediaSearchDocument->iconUrl)->toBeNull()
        ->and($mediaSearchDocument->fileDataId)->toBeNull();
});

test('an icon without a file data id carries only its url', function (): void {
    $mediaSearchDocument = mediaSearchDocument([
        'id' => 19945,
        'assets' => [['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/eu/icons/56/a.jpg']],
    ]);

    expect($mediaSearchDocument->iconUrl)->toBe('https://render.worldofwarcraft.com/eu/icons/56/a.jpg')
        ->and($mediaSearchDocument->fileDataId)->toBeNull();
});

test('a media document without an id is rejected', function (): void {
    mediaSearchDocument(['assets' => []]);
})->throws(MissingFieldException::class, 'Missing field [id] in the response of [data/wow/search/media]');
