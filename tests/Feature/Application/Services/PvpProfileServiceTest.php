<?php

declare(strict_types=1);

use App\Application\Services\PlayableNameService;
use App\Application\Services\Progress\PvpProgressAggregator;
use App\Application\Services\PvpProfileService;
use App\Domain\Services\PvpBracketClassifier;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;

/**
 * @param  array<string, array<string, mixed>>  $endpointResponses  Motif d'endpoint => corps de réponse
 * @param  list<string>  $requested  Collecteur des endpoints réellement appelés
 */
function mockPvpAsync(\Mockery\MockInterface $mock, array $endpointResponses, array &$requested = []): void
{
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getAsync');
    $exp->andReturnUsing(function (string $endpoint) use ($endpointResponses, &$requested): \GuzzleHttp\Promise\RejectedPromise|\GuzzleHttp\Promise\FulfilledPromise {
        $requested[] = $endpoint;

        foreach ($endpointResponses as $pattern => $data) {
            if (str_contains($endpoint, $pattern)) {
                if ($data === ['__reject__']) {
                    return new RejectedPromise(new RequestException(
                        'Not found',
                        new Request('GET', $endpoint),
                        new Response(404),
                    ));
                }

                return new FulfilledPromise(new Response(200, [], json_encode($data, JSON_THROW_ON_ERROR)));
            }
        }

        return new FulfilledPromise(new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)));
    });
}

function pvpSummaryResponse(array $slugs): array
{
    return [
        'honor_level' => 500,
        'honorable_kills' => 1200,
        'pvp_map_statistics' => [],
        'brackets' => array_map(
            fn (string $slug): array => ['href' => 'https://eu.api.blizzard.com/profile/wow/character/hyjal/thrall/pvp-bracket/'.$slug.'?namespace=profile-eu'],
            $slugs,
        ),
    ];
}

function pvpBracketResponse(int $rating, int $tierId = 0): array
{
    return [
        'bracket' => ['type' => 'ARENA_3v3'],
        'rating' => $rating,
        'season' => ['id' => 40],
        'tier' => $tierId > 0 ? ['id' => $tierId] : [],
        'season_match_statistics' => ['played' => 20, 'won' => 12, 'lost' => 8],
        'weekly_match_statistics' => ['played' => 2, 'won' => 1, 'lost' => 1],
    ];
}

function mockPvpClient(array $endpointResponses, array &$requested = [], int $seasonId = 40): \Mockery\MockInterface
{
    $mock = test()->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $seasonExp */
    $seasonExp = $mock->shouldReceive('getCurrentPvpSeasonId');
    $seasonExp->andReturn($seasonId);

    /** @var \Mockery\Expectation $regionExp */
    $regionExp = $mock->shouldReceive('getRegion');
    $regionExp->andReturn('eu');

    mockPvpAsync($mock, $endpointResponses, $requested);

    return $mock;
}

function pvpService(): PvpProfileService
{
    $blizzardApiClient = resolve(BlizzardApiClient::class);

    return new PvpProfileService(
        $blizzardApiClient,
        new PvpProgressAggregator,
        new PvpBracketClassifier,
        new PlayableNameService($blizzardApiClient),
    );
}

beforeEach(function (): void {
    Cache::flush();
});

test('it fetches only the brackets listed in the summary', function (): void {
    $requested = [];
    $mock = mockPvpClient([
        'pvp-bracket/3v3' => pvpBracketResponse(1842),
        'pvp-bracket/shuffle-priest-shadow' => pvpBracketResponse(1600),
    ], $requested);

    /** @var \Mockery\Expectation $summaryExp */
    $summaryExp = $mock->shouldReceive('get');
    $summaryExp->with('profile/wow/character/hyjal/thrall/pvp-summary')
        ->andReturn(pvpSummaryResponse(['3v3', 'shuffle-priest-shadow']));

    $result = pvpService()->getForCharacter('Hyjal', 'Thrall');

    expect($result)->not->toBeNull()
        ->and($result['best_rating'])->toBe(1842)
        ->and(array_column($result['groups'], 'key'))->toBe(['arena', 'shuffle']);

    $brackets = array_values(array_filter($requested, fn (string $e): bool => str_contains($e, 'pvp-bracket/')));
    expect($brackets)->toHaveCount(2)
        ->and($brackets[0])->toContain('/pvp-bracket/3v3')
        ->and($brackets[1])->toContain('/pvp-bracket/shuffle-priest-shadow');
});

test('it returns null when the character has no pvp summary', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    /** @var \Mockery\Expectation $summaryExp */
    $summaryExp = $mock->shouldReceive('get');
    $summaryExp->andThrow(new RequestException('Not found', new Request('GET', 'pvp-summary'), new Response(404)));

    expect(pvpService()->getForCharacter('hyjal', 'thrall'))->toBeNull();
});

test('a failing bracket does not invalidate the others', function (): void {
    $mock = mockPvpClient([
        'pvp-bracket/2v2' => ['__reject__'],
        'pvp-bracket/3v3' => pvpBracketResponse(1842),
    ]);

    /** @var \Mockery\Expectation $summaryExp */
    $summaryExp = $mock->shouldReceive('get');
    $summaryExp->andReturn(pvpSummaryResponse(['2v2', '3v3']));

    $result = pvpService()->getForCharacter('hyjal', 'thrall');

    expect(array_column($result['groups'][0]['brackets'], 'slug'))->toBe(['3v3']);
});

test('it resolves tier names and icons, then serves them from cache', function (): void {
    $requested = [];
    $mock = mockPvpClient([
        'pvp-bracket/3v3' => pvpBracketResponse(1842, 12),
        'data/wow/media/pvp-tier/12' => ['assets' => [['key' => 'icon', 'value' => 'https://render.test/duelist.jpg']]],
        'data/wow/pvp-tier/12' => ['id' => 12, 'name' => 'Duelliste'],
    ], $requested);

    /** @var \Mockery\Expectation $summaryExp */
    $summaryExp = $mock->shouldReceive('get');
    $summaryExp->andReturn(pvpSummaryResponse(['3v3']));

    $bracket = pvpService()->getForCharacter('hyjal', 'thrall')['groups'][0]['brackets'][0];

    expect($bracket['tier_name'])->toBe('Duelliste')
        ->and($bracket['tier_icon_url'])->toBe('https://render.test/duelist.jpg')
        ->and(Cache::get('pvp_tier:12'))->toBe(['name' => 'Duelliste', 'icon_url' => 'https://render.test/duelist.jpg']);

    // Deuxième personnage, même palier : plus aucun appel palier.
    Cache::forget('pvp_profile:hyjal:jaina');
    $requested = [];
    pvpService()->getForCharacter('hyjal', 'jaina');

    expect(array_filter($requested, fn (string $e): bool => str_contains($e, 'pvp-tier')))->toBeEmpty();
});

test('it caches the normalised payload per character', function (): void {
    $mock = mockPvpClient(['pvp-bracket/3v3' => pvpBracketResponse(1842)]);

    /** @var \Mockery\Expectation $summaryExp */
    $summaryExp = $mock->shouldReceive('get');
    $summaryExp->once()->andReturn(pvpSummaryResponse(['3v3']));

    $first = pvpService()->getForCharacter('Hyjal', 'Thrall');
    $second = pvpService()->getForCharacter('hyjal', 'thrall');

    expect($second)->toBe($first)
        ->and(Cache::has('pvp_profile:hyjal:thrall'))->toBeTrue();
});

test('it caches the absence of pvp data to avoid refetching', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    /** @var \Mockery\Expectation $summaryExp */
    $summaryExp = $mock->shouldReceive('get');
    $summaryExp->once()->andThrow(new RequestException('Not found', new Request('GET', 'pvp-summary'), new Response(404)));

    expect(pvpService()->getForCharacter('hyjal', 'thrall'))->toBeNull()
        ->and(pvpService()->getForCharacter('hyjal', 'thrall'))->toBeNull();
});

test('it degrades to an unfiltered payload when the season index is unavailable', function (): void {
    $requested = [];
    $mock = mockPvpClient(['pvp-bracket/3v3' => pvpBracketResponse(1842)], $requested, 0);

    /** @var \Mockery\Expectation $summaryExp */
    $summaryExp = $mock->shouldReceive('get');
    $summaryExp->andReturn(pvpSummaryResponse(['3v3']));

    $result = pvpService()->getForCharacter('hyjal', 'thrall');

    expect($result['season_id'])->toBe(40)
        ->and($result['groups'][0]['brackets'])->toHaveCount(1);
});
