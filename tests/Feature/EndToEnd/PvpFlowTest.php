<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Parcours PvP complet, de l'API Blizzard à la page rendue : aucune table,
 * aucun import — seuls des appels API mis en cache.
 */
beforeEach(function (): void {
    Cache::flush();
});

function fakeBlizzard(array $responses): void
{
    $mock = test()->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $regionExp */
    $regionExp = $mock->shouldReceive('getRegion');
    $regionExp->andReturn('eu');

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentPvpSeasonId');
    $seasonExp->andReturn(40);

    $resolve = function (string $endpoint) use ($responses): array {
        foreach ($responses as $pattern => $data) {
            if (str_contains($endpoint, $pattern)) {
                return $data;
            }
        }

        return [];
    };

    /** @var \Mockery\Expectation $getExp */
    $getExp = $mock->shouldReceive('get');
    $getExp->andReturnUsing($resolve);

    /** @var \Mockery\Expectation $asyncExp */
    $asyncExp = $mock->shouldReceive('getAsync');
    $asyncExp->andReturnUsing(fn (string $endpoint): FulfilledPromise => new FulfilledPromise(
        new Response(200, [], json_encode($resolve($endpoint), JSON_THROW_ON_ERROR)),
    ));
}

test('full pvp profile flow', function (): void {
    fakeBlizzard([
        '/pvp-summary' => [
            'honor_level' => 500,
            'honorable_kills' => 12345,
            'pvp_map_statistics' => [
                ['match_statistics' => ['played' => 10, 'won' => 6, 'lost' => 4]],
            ],
            'brackets' => [
                ['href' => 'https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/pvp-bracket/3v3?namespace=profile-eu'],
                ['href' => 'https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/pvp-bracket/shuffle-shaman-enhancement?namespace=profile-eu'],
            ],
        ],
        '/pvp-bracket/3v3' => [
            'bracket' => ['type' => 'ARENA_3v3'],
            'rating' => 1842,
            'season' => ['id' => 40],
            'tier' => ['id' => 12],
            'season_match_statistics' => ['played' => 100, 'won' => 55, 'lost' => 45],
            'weekly_match_statistics' => ['played' => 10, 'won' => 6, 'lost' => 4],
        ],
        '/pvp-bracket/shuffle-shaman-enhancement' => [
            'bracket' => ['type' => 'SHUFFLE'],
            'rating' => 1600,
            'season' => ['id' => 40],
            'tier' => ['id' => 12],
            'specialization' => ['name' => 'Amélioration'],
            'season_match_statistics' => ['played' => 40, 'won' => 20, 'lost' => 20],
        ],
        'data/wow/media/pvp-tier/12' => ['assets' => [['key' => 'icon', 'value' => 'https://render.test/duelist.jpg']]],
        'data/wow/pvp-tier/12' => ['name' => 'Duelliste'],
    ]);

    $response = $this->getJson('/api/character/hyjal/thrall/pvp')->assertOk();

    $pvp = $response->json('pvp');

    expect($pvp['season_id'])->toBe(40)
        ->and($pvp['best_rating'])->toBe(1842)
        ->and($pvp['honor_level'])->toBe(500)
        ->and($pvp['battlegrounds']['won'])->toBe(6)
        ->and(array_column($pvp['groups'], 'key'))->toBe(['arena', 'shuffle'])
        ->and($pvp['groups'][0]['brackets'][0]['label'])->toBe('Arène 3c3')
        ->and($pvp['groups'][0]['brackets'][0]['tier_name'])->toBe('Duelliste')
        ->and($pvp['groups'][1]['brackets'][0]['label'])->toBe('Mêlée solo — Amélioration');

    // Deuxième visite : servie par le cache, aucune donnée perdue.
    expect($this->getJson('/api/character/hyjal/thrall/pvp')->json('pvp'))->toBe($pvp);
});

test('a character without pvp data degrades to an empty tab', function (): void {
    fakeBlizzard(['/pvp-summary' => ['honor_level' => 0, 'honorable_kills' => 0, 'brackets' => []]]);

    $this->getJson('/api/character/hyjal/nopvp/pvp')
        ->assertOk()
        ->assertExactJson(['pvp' => null]);
});

test('full pvp leaderboard flow', function (): void {
    fakeBlizzard([
        'pvp-leaderboard/index' => ['leaderboards' => [['name' => '2v2'], ['name' => '3v3']]],
        'pvp-leaderboard/3v3' => [
            'entries' => [
                [
                    'character' => ['name' => 'Thrall', 'realm' => ['slug' => 'hyjal']],
                    'faction' => ['type' => 'HORDE'],
                    'rank' => 1,
                    'rating' => 2999,
                    'season_match_statistics' => ['played' => 100, 'won' => 70, 'lost' => 30],
                ],
                [
                    'character' => ['name' => 'Jaina', 'realm' => ['slug' => 'dalaran']],
                    'faction' => ['type' => 'ALLIANCE'],
                    'rank' => 2,
                    'rating' => 2950,
                    'season_match_statistics' => ['played' => 90, 'won' => 60, 'lost' => 30],
                ],
            ],
        ],
    ]);

    $this->get('/classements-pvp')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('PvpLeaderboardPage')
            ->where('bracket', '3v3')
            ->where('seasonId', 40)
            ->where('total', 2)
            ->where('entries.0.name', 'Thrall')
            ->where('entries.0.realm', 'Hyjal')
            ->where('entries.0.rating', 2999)
            ->where('groups.0.key', 'arena')
        );

    // Recherche : le classement est filtré côté serveur sur les entrées en cache.
    $this->get('/classements-pvp/3v3?search=jaina')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('total', 1)
            ->where('entries.0.name', 'Jaina')
            ->where('search', 'jaina')
        );
});
