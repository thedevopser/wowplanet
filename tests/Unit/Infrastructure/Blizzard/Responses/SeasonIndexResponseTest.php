<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Blizzard\Responses\SeasonIndexResponse;

/**
 * @param  array<string, mixed>  $decoded
 */
function seasonIndex(array $decoded): SeasonIndexResponse
{
    return SeasonIndexResponse::fromPayload(
        ResponsePayload::forEndpoint('data/wow/pvp-season/index', $decoded),
    );
}

test('a complete index carries the current season id', function (): void {
    $seasonIndexResponse = seasonIndex([
        'seasons' => [['id' => 39], ['id' => 40]],
        'current_season' => ['id' => 40, 'key' => ['href' => 'https://eu.api.blizzard.com/']],
    ]);

    expect($seasonIndexResponse->currentSeasonId)->toBe(40);
});

test('an index without a current season carries no id', function (): void {
    $seasonIndexResponse = seasonIndex(['seasons' => [['id' => 39]]]);

    expect($seasonIndexResponse->currentSeasonId)->toBeNull();
});

test('a current season without an id is rejected', function (): void {
    seasonIndex(['current_season' => ['key' => ['href' => 'https://eu.api.blizzard.com/']]]);
})->throws(MissingFieldException::class, 'Missing field [current_season.id] in the response of [data/wow/pvp-season/index]');
