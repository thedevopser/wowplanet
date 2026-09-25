<?php

declare(strict_types=1);

use App\Application\Services\PlayableNameService;
use App\Application\Services\PvpLeaderboardService;
use App\Domain\Services\PvpBracketClassifier;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use Illuminate\Support\Facades\Cache;

/**
 * Index de classes et de spécialisations FR/EN, tels que les sert l'API statique.
 * Les tests qui ne les fournissent pas exercent le repli sur le slug anglais.
 *
 * @return array<string, array<string, mixed>> "motif@locale" => corps
 */
function playableIndexResponses(): array
{
    return [
        'playable-class/index@en_US' => ['classes' => [
            ['id' => 5, 'name' => 'Priest'],
            ['id' => 11, 'name' => 'Druid'],
        ]],
        'playable-specialization/index@en_US' => ['character_specializations' => [
            ['id' => 258, 'name' => 'Shadow'],
            ['id' => 103, 'name' => 'Feral'],
        ]],
        'playable-class/index@fr_FR' => ['classes' => [
            ['id' => 5, 'name' => 'Prêtre'],
            ['id' => 11, 'name' => 'Druide'],
        ]],
        'playable-specialization/index@fr_FR' => ['character_specializations' => [
            ['id' => 258, 'name' => 'Ombre'],
            ['id' => 103, 'name' => 'Farouche'],
        ]],
    ];
}

function leaderboardEntry(int $rank, string $name, string $realm = 'hyjal', string $faction = 'HORDE'): array
{
    return [
        'character' => ['name' => $name, 'id' => $rank, 'realm' => ['slug' => $realm, 'id' => 1]],
        'faction' => ['type' => $faction],
        'rank' => $rank,
        'rating' => 3000 - $rank,
        'season_match_statistics' => ['played' => 100, 'won' => 70, 'lost' => 30],
    ];
}

function leaderboardService(): PvpLeaderboardService
{
    $blizzardApiClient = resolve(BlizzardApiClient::class);

    return new PvpLeaderboardService($blizzardApiClient, new PvpBracketClassifier, new PlayableNameService($blizzardApiClient));
}

/**
 * Les clés sont des motifs d'endpoint, éventuellement suffixés « @locale » quand
 * la réponse dépend de la langue demandée.
 *
 * @param  array<string, array<string, mixed>>  $responses
 */
function mockLeaderboardClient(array $responses, int $seasonId = 40): \Mockery\MockInterface
{
    $mock = test()->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentPvpSeasonId');
    $seasonExp->andReturn($seasonId);

    /** @var \Mockery\Expectation $regionExp */
    $regionExp = $mock->shouldReceive('getRegion');
    $regionExp->andReturn('eu');

    /** @var \Mockery\Expectation $getExp */
    $getExp = $mock->shouldReceive('get');
    $getExp->andReturnUsing(function (string $endpoint, array $query = []) use ($responses): array {
        $locale = is_string($query['locale'] ?? null) ? $query['locale'] : 'fr_FR';

        foreach ($responses as $key => $data) {
            [$pattern, $wantedLocale] = array_pad(explode('@', (string) $key, 2), 2, null);

            if (! str_contains($endpoint, (string) $pattern)) {
                continue;
            }

            if ($wantedLocale !== null && $wantedLocale !== $locale) {
                continue;
            }

            throw_if($data === ['__throw__'], RuntimeException::class, 'Blizzard is down');

            return $data;
        }

        return [];
    });

    return $mock;
}

beforeEach(function (): void {
    Cache::flush();
});

test('availableBrackets groups and labels the official index', function (): void {
    mockLeaderboardClient([
        'pvp-leaderboard/index' => [
            'leaderboards' => [
                ['name' => 'shuffle-priest-shadow'],
                ['name' => '3v3'],
                ['name' => 'rbg'],
                ['name' => '2v2'],
            ],
        ],
    ]);

    $groups = leaderboardService()->availableBrackets();

    expect(array_column($groups, 'key'))->toBe(['arena', 'rbg', 'shuffle'])
        ->and($groups[0]['label'])->toBe('Arène')
        ->and(array_column($groups[0]['brackets'], 'slug'))->toBe(['2v2', '3v3'])
        ->and($groups[0]['brackets'][0]['label'])->toBe('Arène 2c2')
        ->and($groups[2]['brackets'][0]['label'])->toBe('Mêlée solo — Priest Shadow');
});

test('availableBrackets lists the overall leaderboard first and labels specs in french', function (): void {
    mockLeaderboardClient(array_merge(playableIndexResponses(), [
        'pvp-leaderboard/index' => [
            'leaderboards' => [
                ['name' => 'shuffle-priest-shadow'],
                ['name' => 'shuffle-druid-feral'],
                ['name' => 'shuffle-overall'],
            ],
        ],
    ]));

    $shuffle = leaderboardService()->availableBrackets()[0];

    expect(array_column($shuffle['brackets'], 'short'))
        ->toBe(['Toutes spés', 'Druide · Farouche', 'Prêtre · Ombre'])
        ->and($shuffle['brackets'][0]['label'])->toBe('Mêlée solo — Toutes spés')
        ->and($shuffle['brackets'][2]['label'])->toBe('Mêlée solo — Prêtre · Ombre');
});

test('availableBrackets falls back to the english slug when the indexes are unavailable', function (): void {
    mockLeaderboardClient([
        'pvp-leaderboard/index' => ['leaderboards' => [['name' => 'shuffle-priest-shadow']]],
    ]);

    expect(leaderboardService()->availableBrackets()[0]['brackets'][0]['short'])
        ->toBe('Priest Shadow');
});

test('leaderboard labels the active bracket in french', function (): void {
    mockLeaderboardClient(array_merge(playableIndexResponses(), [
        'pvp-leaderboard/index' => ['leaderboards' => [['name' => 'shuffle-priest-shadow']]],
        'pvp-leaderboard/shuffle-priest-shadow' => ['entries' => [leaderboardEntry(1, 'Thrall')]],
    ]));

    expect(leaderboardService()->leaderboard('shuffle-priest-shadow', 1)['label'])
        ->toBe('Mêlée solo — Prêtre · Ombre');
});

test('availableBrackets is empty when the index is unavailable', function (): void {
    mockLeaderboardClient(['pvp-leaderboard/index' => ['__throw__']]);

    expect(leaderboardService()->availableBrackets())->toBe([]);
});

test('leaderboard returns trimmed and paginated entries', function (): void {
    mockLeaderboardClient([
        'pvp-leaderboard/3v3' => [
            'season' => ['id' => 40],
            'entries' => array_map(
                fn (int $rank): array => leaderboardEntry($rank, 'Joueur'.$rank),
                range(1, 120),
            ),
        ],
    ]);

    $result = leaderboardService()->leaderboard('3v3', 1);

    expect($result['bracket'])->toBe('3v3')
        ->and($result['unavailable'])->toBeFalse()
        ->and($result['total'])->toBe(120)
        ->and($result['currentPage'])->toBe(1)
        ->and($result['lastPage'])->toBe(3)
        ->and($result['entries'])->toHaveCount(50)
        ->and($result['entries'][0])->toBe([
            'rank' => 1,
            'name' => 'Joueur1',
            'realm' => 'Hyjal',
            'realm_slug' => 'hyjal',
            'faction' => 'HORDE',
            'rating' => 2999,
            'won' => 70,
            'lost' => 30,
        ]);
});

test('leaderboard serves the requested page', function (): void {
    mockLeaderboardClient([
        'pvp-leaderboard/3v3' => [
            'entries' => array_map(fn (int $rank): array => leaderboardEntry($rank, 'Joueur'.$rank), range(1, 120)),
        ],
    ]);

    $result = leaderboardService()->leaderboard('3v3', 3);

    expect($result['currentPage'])->toBe(3)
        ->and($result['entries'])->toHaveCount(20)
        ->and($result['entries'][0]['rank'])->toBe(101);
});

test('leaderboard clamps out-of-range pages', function (): void {
    mockLeaderboardClient([
        'pvp-leaderboard/3v3' => ['entries' => [leaderboardEntry(1, 'Solo')]],
    ]);

    expect(leaderboardService()->leaderboard('3v3', 99)['currentPage'])->toBe(1)
        ->and(leaderboardService()->leaderboard('3v3', 0)['currentPage'])->toBe(1);
});

test('leaderboard filters on character name and realm', function (): void {
    mockLeaderboardClient([
        'pvp-leaderboard/3v3' => [
            'entries' => [
                leaderboardEntry(1, 'Thrall', 'hyjal'),
                leaderboardEntry(2, 'Jaina', 'dalaran'),
                leaderboardEntry(3, 'Sylvanas', 'hyjal'),
            ],
        ],
    ]);

    expect(array_column(leaderboardService()->leaderboard('3v3', 1, 'thra')['entries'], 'name'))
        ->toBe(['Thrall']);

    expect(array_column(leaderboardService()->leaderboard('3v3', 1, 'hyjal')['entries'], 'name'))
        ->toBe(['Thrall', 'Sylvanas']);

    $none = leaderboardService()->leaderboard('3v3', 1, 'personne');
    expect($none['entries'])->toBe([])
        ->and($none['total'])->toBe(0)
        ->and($none['lastPage'])->toBe(1);
});

test('leaderboard falls back to the default bracket when the slug is unknown', function (): void {
    mockLeaderboardClient([
        'pvp-leaderboard/index' => ['leaderboards' => [['name' => '3v3'], ['name' => '2v2']]],
        'pvp-leaderboard/3v3' => ['entries' => [leaderboardEntry(1, 'Thrall')]],
    ]);

    expect(leaderboardService()->leaderboard('drop table', 1)['bracket'])->toBe('3v3');
});

test('leaderboard reports unavailability instead of failing', function (): void {
    mockLeaderboardClient([
        'pvp-leaderboard/index' => ['leaderboards' => [['name' => '3v3']]],
        'pvp-leaderboard/3v3' => ['__throw__'],
    ]);

    $result = leaderboardService()->leaderboard('3v3', 1);

    expect($result['unavailable'])->toBeTrue()
        ->and($result['entries'])->toBe([])
        ->and($result['total'])->toBe(0);
});

test('leaderboard is unavailable when the season cannot be resolved', function (): void {
    mockLeaderboardClient(['pvp-leaderboard/3v3' => ['entries' => [leaderboardEntry(1, 'Thrall')]]], 0);

    expect(leaderboardService()->leaderboard('3v3', 1)['unavailable'])->toBeTrue();
});

test('leaderboard caches trimmed entries under the season key', function (): void {
    $mock = mockLeaderboardClient([
        'pvp-leaderboard/3v3' => ['entries' => [leaderboardEntry(1, 'Thrall')]],
    ]);

    leaderboardService()->leaderboard('3v3', 1);
    leaderboardService()->leaderboard('3v3', 1, 'thrall');

    /** @var list<array<string, mixed>> $cached */
    $cached = Cache::get('pvp_leaderboard:40:3v3');

    expect($cached)->toHaveCount(1)
        ->and($cached[0])->toHaveKeys(['rank', 'name', 'realm', 'realm_slug', 'faction', 'rating', 'won', 'lost'])
        ->and($mock)->not->toBeNull();
});
