<?php

declare(strict_types=1);

use App\Application\Services\PvpLeaderboardService;
use App\Application\Services\PvpProfileService;
use Inertia\Testing\AssertableInertia as Assert;

function mockLeaderboard(array $overrides = [], array $groups = []): \Mockery\MockInterface
{
    $mock = test()->mock(PvpLeaderboardService::class);

    /** @var \Mockery\Expectation $bracketsExp */
    $bracketsExp = $mock->shouldReceive('availableBrackets');
    $bracketsExp->andReturn($groups);

    /** @var \Mockery\Expectation $boardExp */
    $boardExp = $mock->shouldReceive('leaderboard');
    $boardExp->andReturn(array_merge([
        'bracket' => '3v3',
        'label' => 'Arène 3c3',
        'seasonId' => 40,
        'entries' => [],
        'total' => 0,
        'currentPage' => 1,
        'lastPage' => 1,
        'unavailable' => false,
    ], $overrides));

    return $mock;
}

test('show returns the normalised pvp payload', function (): void {
    $payload = [
        'season_id' => 40,
        'honor_level' => 500,
        'honorable_kills' => 1200,
        'best_rating' => 1842,
        'battlegrounds' => ['played' => 0, 'won' => 0, 'lost' => 0, 'win_rate' => 0.0],
        'groups' => [
            ['key' => 'arena', 'label' => 'Arène', 'brackets' => [['slug' => '3v3', 'rating' => 1842]]],
        ],
    ];

    $mock = $this->mock(PvpProfileService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getForCharacter');
    $exp->once()->with('hyjal', 'thrall')->andReturn($payload);

    $this->getJson('/api/character/hyjal/thrall/pvp')
        ->assertOk()
        ->assertJson(['pvp' => $payload]);
});

test('show returns a null payload for a character without pvp data', function (): void {
    $mock = $this->mock(PvpProfileService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getForCharacter');
    $exp->once()->andReturn(null);

    $this->getJson('/api/character/hyjal/thrall/pvp')
        ->assertOk()
        ->assertExactJson(['pvp' => null]);
});

test('show degrades to a null payload when the api fails', function (): void {
    $mock = $this->mock(PvpProfileService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getForCharacter');
    $exp->once()->andThrow(new RuntimeException('Blizzard is down'));

    $this->getJson('/api/character/hyjal/thrall/pvp')
        ->assertOk()
        ->assertExactJson(['pvp' => null]);
});

test('show lowercases realm and name', function (): void {
    $mock = $this->mock(PvpProfileService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getForCharacter');
    $exp->once()->with('hyjal', 'thrall')->andReturn(null);

    $this->getJson('/api/character/Hyjal/Thrall/pvp')->assertOk();
});

test('leaderboard renders the page with entries and bracket groups', function (): void {
    mockLeaderboard(
        [
            'entries' => [[
                'rank' => 1,
                'name' => 'Thrall',
                'realm' => 'Hyjal',
                'realm_slug' => 'hyjal',
                'faction' => 'HORDE',
                'rating' => 2999,
                'won' => 70,
                'lost' => 30,
            ]],
            'total' => 1,
        ],
        [['key' => 'arena', 'label' => 'Arène', 'brackets' => [['slug' => '3v3', 'label' => 'Arène 3c3']]]],
    );

    $this->get('/classements-pvp')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('PvpLeaderboardPage')
            ->where('bracket', '3v3')
            ->where('total', 1)
            ->has('entries', 1)
            ->where('entries.0.name', 'Thrall')
            ->has('groups', 1)
            ->where('unavailable', false)
            ->where('search', null)
            ->has('meta.title')
        );
});

test('leaderboard passes the requested bracket, page and search to the service', function (): void {
    $mock = $this->mock(PvpLeaderboardService::class);
    /** @var \Mockery\Expectation $bracketsExp */
    $bracketsExp = $mock->shouldReceive('availableBrackets');
    $bracketsExp->andReturn([]);

    /** @var \Mockery\Expectation $boardExp */
    $boardExp = $mock->shouldReceive('leaderboard');
    $boardExp->once()->with('2v2', 3, 'thrall')->andReturn([
        'bracket' => '2v2',
        'label' => 'Arène 2c2',
        'seasonId' => 40,
        'entries' => [],
        'total' => 0,
        'currentPage' => 3,
        'lastPage' => 3,
        'unavailable' => false,
    ]);

    $this->get('/classements-pvp/2v2?page=3&search=thrall')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('PvpLeaderboardPage')
            ->where('bracket', '2v2')
            ->where('search', 'thrall')
        );
});

test('leaderboard renders the unavailable state without failing', function (): void {
    mockLeaderboard(['unavailable' => true]);

    $this->get('/classements-pvp')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('PvpLeaderboardPage')
            ->where('unavailable', true)
            ->has('entries', 0)
        );
});
