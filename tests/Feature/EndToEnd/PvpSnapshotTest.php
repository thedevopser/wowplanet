<?php

declare(strict_types=1);

use App\Application\Services\PvpLeaderboardService;
use App\Application\Services\PvpProfileService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Sortie complète des deux services PvP, figée : l'onglet du profil et la page de classements
 * doivent rendre exactement les mêmes données d'une version à l'autre.
 *
 * @param  array<string, array<string, mixed>>  $responses
 */
function fakePvpApi(array $responses): void
{
    $mock = test()->mock(BlizzardApiClient::class);
    $mock->shouldReceive('getRegion')->andReturn('eu');
    $mock->shouldReceive('getCurrentPvpSeasonId')->andReturn(40);

    $resolve = function (string $endpoint) use ($responses): ?array {
        foreach ($responses as $pattern => $data) {
            if (str_ends_with($endpoint, $pattern)) {
                return $data;
            }
        }

        return null;
    };

    $mock->shouldReceive('get')->andReturnUsing(fn (string $endpoint): array => $resolve($endpoint) ?? []);
    $mock->shouldReceive('getAsync')->andReturnUsing(function (string $endpoint) use ($resolve): FulfilledPromise|RejectedPromise {
        $data = $resolve($endpoint);

        return $data === null
            ? new RejectedPromise(new RuntimeException('Bad request'))
            : new FulfilledPromise(new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR)));
    });
}

function pvpSnapshot(mixed $value): string
{
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

beforeEach(function (): void {
    Cache::flush();
});

test('the pvp tab payload is unchanged', function (): void {
    Cache::put('pvp_tier:11', ['name' => 'Rival', 'icon_url' => 'https://render.test/rival.jpg'], 60);

    $bracket = fn (array $overrides): array => array_merge([
        'season' => ['id' => 40],
        'season_match_statistics' => ['played' => 30, 'won' => 20, 'lost' => 10],
        'weekly_match_statistics' => ['played' => 3, 'won' => 1, 'lost' => 2],
    ], $overrides);
    $href = fn (string $slug): array => ['href' => 'https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/pvp-bracket/'.$slug.'?namespace=profile-eu'];

    fakePvpApi([
        '/pvp-summary' => [
            'honor_level' => 120,
            'honorable_kills' => 4567,
            'pvp_map_statistics' => [
                ['match_statistics' => ['played' => 10, 'won' => 6, 'lost' => 4]],
                ['match_statistics' => ['played' => 5, 'won' => 1, 'lost' => 4]],
                ['world_map' => ['id' => 1]],
            ],
            'brackets' => [
                $href('3v3'), $href('2v2'), $href('rbg'), $href('3v3'),
                $href('shuffle-shaman-enhancement'), $href('shuffle-shaman-restoration'),
                $href('blitz-shaman-elemental'), $href('arena-skirmish'), $href('broken'),
                ['name' => 'no href'],
            ],
        ],
        '/pvp-bracket/3v3' => $bracket(['rating' => 1842, 'tier' => ['id' => 12]]),
        '/pvp-bracket/2v2' => $bracket(['rating' => 2100, 'season' => ['id' => 39], 'tier' => ['id' => 12]]),
        '/pvp-bracket/rbg' => ['season' => ['id' => 40], 'rating' => 0, 'season_match_statistics' => ['played' => 0]],
        '/pvp-bracket/shuffle-shaman-enhancement' => $bracket(['rating' => 1600, 'tier' => ['id' => 11], 'specialization' => ['name' => 'Amélioration']]),
        '/pvp-bracket/shuffle-shaman-restoration' => $bracket(['rating' => 1750, 'tier' => ['id' => 13], 'weekly_match_statistics' => null]),
        '/pvp-bracket/blitz-shaman-elemental' => $bracket(['rating' => 0, 'season_match_statistics' => ['played' => 7, 'won' => 7, 'lost' => 0]]),
        '/pvp-bracket/arena-skirmish' => $bracket(['rating' => 1234, 'tier' => ['id' => 99]]),
        'data/wow/pvp-tier/12' => ['name' => 'Duelliste'],
        'data/wow/media/pvp-tier/12' => ['assets' => [['key' => 'icon', 'value' => 'https://render.test/duelist.jpg']]],
        'data/wow/pvp-tier/13' => ['name' => 'Combattant'],
        'data/wow/media/pvp-tier/13' => ['assets' => [['key' => 'other', 'value' => 'https://render.test/other.jpg']]],
        'data/wow/pvp-tier/99' => [],
        'data/wow/media/pvp-tier/99' => [],
    ]);

    $pvp = resolve(PvpProfileService::class)->getForCharacter('Hyjal', 'Thrall');

    expect(pvpSnapshot(['pvp' => $pvp, 'tiers' => [Cache::get('pvp_tier:12'), Cache::get('pvp_tier:13'), Cache::get('pvp_tier:99')]]))->toMatchSnapshot();
});

test('the pvp leaderboard payload is unchanged', function (): void {
    $entry = fn (int $rank, string $name, string $realm, int $rating): array => [
        'character' => ['name' => $name, 'realm' => ['slug' => $realm]],
        'faction' => ['type' => $rank % 2 === 0 ? 'ALLIANCE' : 'HORDE'],
        'rank' => $rank,
        'rating' => $rating,
        'season_match_statistics' => ['played' => 100, 'won' => 60 + $rank, 'lost' => 40 - $rank],
    ];

    $entries = array_map(fn (int $rank): array => $entry($rank, 'Joueur'.$rank, $rank % 3 === 0 ? 'tarren-mill' : 'hyjal', 3000 - $rank), range(1, 60));
    $entries[] = ['rank' => 61];
    $entries[] = ['character' => ['name' => 'Sansroyaume'], 'rank' => '62', 'rating' => '1500'];

    fakePvpApi([
        'pvp-leaderboard/index' => ['leaderboards' => [
            ['name' => '3v3'], ['name' => '2v2'], ['name' => 'rbg'], ['name' => ''], ['id' => 1],
            ['name' => 'shuffle-shaman-restoration'], ['name' => 'shuffle-overall'], ['name' => 'blitz-overall'], ['name' => 'arena-skirmish'],
        ]],
        'pvp-leaderboard/3v3' => ['entries' => $entries],
    ]);

    $pvpLeaderboardService = resolve(PvpLeaderboardService::class);

    expect(pvpSnapshot([
        'brackets' => $pvpLeaderboardService->availableBrackets(),
        'page1' => $pvpLeaderboardService->leaderboard('3v3'),
        'page2' => $pvpLeaderboardService->leaderboard('3v3', 2),
        'search' => $pvpLeaderboardService->leaderboard('3v3', 1, 'tarren'),
        'unknown' => $pvpLeaderboardService->leaderboard('does-not-exist'),
        'cached' => Cache::get('pvp_leaderboard:40:3v3'),
    ]))->toMatchSnapshot();
});
