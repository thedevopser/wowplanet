<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\ConnectedRealmIndexResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function connectedRealmIndex(array $decoded): ConnectedRealmIndexResponse
{
    return ConnectedRealmIndexResponse::fromPayload(
        ResponsePayload::forEndpoint('data/wow/connected-realm/index', $decoded),
    );
}

test('the first connected realm is read from its link', function (): void {
    $connectedRealmIndexResponse = connectedRealmIndex([
        'connected_realms' => [
            ['href' => 'https://eu.api.blizzard.com/data/wow/connected-realm/1080?namespace=dynamic-eu'],
            ['href' => 'https://eu.api.blizzard.com/data/wow/connected-realm/1084?namespace=dynamic-eu'],
        ],
    ]);

    expect($connectedRealmIndexResponse->firstConnectedRealmId)->toBe(1080);
});

test('a link that names no connected realm is skipped', function (): void {
    $connectedRealmIndexResponse = connectedRealmIndex([
        'connected_realms' => [
            ['href' => 'https://eu.api.blizzard.com/data/wow/realm/510?namespace=dynamic-eu'],
            [],
            ['href' => 'https://eu.api.blizzard.com/data/wow/connected-realm/1084?namespace=dynamic-eu'],
        ],
    ]);

    expect($connectedRealmIndexResponse->firstConnectedRealmId)->toBe(1084);
});

test('an empty index names no connected realm', function (): void {
    expect(connectedRealmIndex([])->firstConnectedRealmId)->toBeNull();
});
