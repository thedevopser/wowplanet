<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Pvp\PvpBracketResponse;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpMatchStatistics;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function typedPvpBracket(array $decoded): PvpBracketResponse
{
    return PvpBracketResponse::fromPayload(ResponsePayload::forEndpoint('profile/wow/character/hyjal/thrall/pvp-bracket/3v3', $decoded));
}

test('a bracket carries its season, rating, tier, specialization and statistics', function (): void {
    $pvpBracketResponse = typedPvpBracket([
        'season' => ['id' => 40],
        'rating' => 1842,
        'tier' => ['id' => 12],
        'specialization' => ['name' => 'Amélioration'],
        'season_match_statistics' => ['played' => 100, 'won' => 55, 'lost' => 45],
        'weekly_match_statistics' => ['played' => 10, 'won' => 6, 'lost' => 4],
    ]);

    expect($pvpBracketResponse->isEmpty)->toBeFalse()
        ->and($pvpBracketResponse->seasonId)->toBe(40)
        ->and($pvpBracketResponse->rating)->toBe(1842)
        ->and($pvpBracketResponse->tierId)->toBe(12)
        ->and($pvpBracketResponse->specializationName)->toBe('Amélioration')
        ->and($pvpBracketResponse->seasonStatistics)->toEqual(new PvpMatchStatistics(100, 55, 45))
        ->and($pvpBracketResponse->weeklyStatistics)->toEqual(new PvpMatchStatistics(10, 6, 4));
});

test('missing statistics and tier count as nothing played', function (): void {
    $pvpBracketResponse = typedPvpBracket(['rating' => '1500', 'weekly_match_statistics' => null]);

    expect($pvpBracketResponse->seasonId)->toBe(0)
        ->and($pvpBracketResponse->rating)->toBe(1500)
        ->and($pvpBracketResponse->tierId)->toBeNull()
        ->and($pvpBracketResponse->specializationName)->toBeNull()
        ->and($pvpBracketResponse->seasonStatistics)->toEqual(new PvpMatchStatistics(0, 0, 0))
        ->and($pvpBracketResponse->weeklyStatistics)->toEqual(new PvpMatchStatistics(0, 0, 0));
});

test('a bracket that could not be fetched is empty', function (): void {
    expect(typedPvpBracket([])->isEmpty)->toBeTrue();
});

test('statistics served as numeric strings or garbage are read like before', function (): void {
    $pvpMatchStatistics = PvpMatchStatistics::fromPayload(ResponsePayload::forEndpoint('x', ['played' => '12', 'won' => 'n/a', 'lost' => 3.0]));

    expect($pvpMatchStatistics)->toEqual(new PvpMatchStatistics(12, 0, 3));
});
