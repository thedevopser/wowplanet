<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Pvp\PvpSummaryResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function typedPvpSummary(array $decoded): PvpSummaryResponse
{
    return PvpSummaryResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/pvp-summary', $decoded));
}

test('a summary carries honor, battleground statistics and the played brackets', function (): void {
    $pvpSummaryResponse = typedPvpSummary([
        'honor_level' => 120,
        'honorable_kills' => '4567',
        'pvp_map_statistics' => [
            ['match_statistics' => ['played' => 10, 'won' => 6, 'lost' => 4]],
            ['world_map' => ['id' => 1]],
        ],
        'brackets' => [
            ['href' => 'https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/pvp-bracket/3v3?namespace=profile-eu'],
            ['href' => 'https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/pvp-bracket/shuffle-shaman-enhancement'],
            ['href' => 'https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/pvp-bracket/3v3'],
            ['href' => 'https://eu.api.blizzard.com/elsewhere'],
            ['name' => 'no href'],
        ],
    ]);

    expect($pvpSummaryResponse->isEmpty)->toBeFalse()
        ->and($pvpSummaryResponse->honorLevel)->toBe(120)
        ->and($pvpSummaryResponse->honorableKills)->toBe(4567)
        ->and($pvpSummaryResponse->battlegroundStatistics[0]->won)->toBe(6)
        ->and($pvpSummaryResponse->battlegroundStatistics[1]->played)->toBe(0)
        ->and($pvpSummaryResponse->bracketSlugs)->toBe(['3v3', 'shuffle-shaman-enhancement']);
});

test('an empty summary is flagged as such, with nothing played', function (): void {
    $pvpSummaryResponse = typedPvpSummary([]);

    expect($pvpSummaryResponse->isEmpty)->toBeTrue()
        ->and($pvpSummaryResponse->honorLevel)->toBe(0)
        ->and($pvpSummaryResponse->battlegroundStatistics)->toBe([])
        ->and($pvpSummaryResponse->bracketSlugs)->toBe([]);
});
