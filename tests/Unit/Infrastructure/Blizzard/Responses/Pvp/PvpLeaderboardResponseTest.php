<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Pvp\PvpLeaderboardIndexResponse;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpLeaderboardResponse;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpMatchStatistics;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpTierResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

test('the leaderboard index lists the named brackets only', function (): void {
    $pvpLeaderboardIndexResponse = PvpLeaderboardIndexResponse::fromPayload(ResponsePayload::forEndpoint('data/wow/pvp-season/40/pvp-leaderboard/index', [
        'leaderboards' => [['name' => '3v3'], ['name' => ''], ['id' => 1], ['name' => 'shuffle-overall']],
    ]));

    expect($pvpLeaderboardIndexResponse->slugs)->toBe(['3v3', 'shuffle-overall']);
});

test('a leaderboard entry keeps the displayed columns', function (): void {
    $pvpLeaderboardResponse = PvpLeaderboardResponse::fromPayload(ResponsePayload::forEndpoint('data/wow/pvp-season/40/pvp-leaderboard/3v3', [
        'entries' => [
            [
                'character' => ['name' => 'Thrall', 'realm' => ['slug' => 'tarren-mill']],
                'faction' => ['type' => 'HORDE'],
                'rank' => 1,
                'rating' => 2999,
                'season_match_statistics' => ['played' => 100, 'won' => 70, 'lost' => 30],
            ],
            ['rank' => '2', 'rating' => '1500'],
        ],
    ]));

    [$first, $second] = $pvpLeaderboardResponse->entries;

    expect($first->rank)->toBe(1)
        ->and($first->characterName)->toBe('Thrall')
        ->and($first->realmSlug)->toBe('tarren-mill')
        ->and($first->factionType)->toBe('HORDE')
        ->and($first->rating)->toBe(2999)
        ->and($first->statistics)->toEqual(new PvpMatchStatistics(100, 70, 30))
        ->and($second->rank)->toBe(2)
        ->and($second->rating)->toBe(1500)
        ->and($second->characterName)->toBeNull()
        ->and($second->realmSlug)->toBeNull();
});

test('an unavailable leaderboard has no entry', function (): void {
    expect(PvpLeaderboardResponse::fromPayload(ResponsePayload::forEndpoint('x', []))->entries)->toBe([]);
});

test('a tier carries its localized name', function (): void {
    expect(PvpTierResponse::fromPayload(ResponsePayload::forEndpoint('data/wow/pvp-tier/12', ['name' => 'Duelliste']))->name)->toBe('Duelliste')
        ->and(PvpTierResponse::fromPayload(ResponsePayload::forEndpoint('data/wow/pvp-tier/12', []))->name)->toBeNull();
});
