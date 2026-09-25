<?php

declare(strict_types=1);

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\CharacterProfileService;
use App\Application\Services\CrossCharacterService;
use App\Application\Services\UserCharacterService;
use Inertia\Testing\AssertableInertia as Assert;

function characterPageDTO(): CharacterProfileDTO
{
    return new CharacterProfileDTO(
        name: 'Thrall',
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 620,
        faction: 'Horde',
        avatarUrl: 'https://example.com/avatar.jpg',
        classIconUrl: 'https://example.com/class-icon.jpg',
        collections: [],
        mountsCount: 150,
        petsCount: 80,
    );
}

test('show returns character profile', function (): void {
    $characterProfileDTO = new CharacterProfileDTO(
        name: 'Thrall',
        realm: 'Hyjal',
        race: 'Orc',
        class: 'Chaman',
        classId: 7,
        level: 80,
        ilvl: 620,
        faction: 'Horde',
        avatarUrl: 'https://example.com/avatar.jpg',
        classIconUrl: 'https://example.com/class-icon.jpg',
        collections: [],
        mountsCount: 150,
        petsCount: 80,
    );

    $mock = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getProfile');
    $exp->once()->with('hyjal', 'thrall')->andReturn($characterProfileDTO);

    $this->getJson('/api/character/hyjal/thrall')
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'Thrall',
            'realm' => 'Hyjal',
            'level' => 80,
            'classId' => 7,
            'decorCount' => 0,
        ]);
});

test('show returns 404 when character not found', function (): void {
    $mock = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getProfile');
    $exp->once()->with('hyjal', 'unknown')->andThrow(new Exception('Character not found'));

    $this->getJson('/api/character/hyjal/unknown')
        ->assertNotFound()
        ->assertJsonStructure(['error']);
});

test('page redirects to lowercase url', function (): void {
    $this->get('/character/HYJAL/THRALL')
        ->assertRedirect('/character/hyjal/thrall')
        ->assertStatus(301);
});

test('page renders Inertia component with character and meta', function (): void {
    $auth = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnFalse();

    $profiles = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->once()->with('hyjal', 'thrall')->andReturn(characterPageDTO());

    $this->get('/character/hyjal/thrall')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('CharacterPage')
            ->where('character.name', 'Thrall')
            ->where('realm', 'hyjal')
            ->where('name', 'thrall')
            ->where('meta.ogType', 'profile')
            ->has('meta.jsonLd')
        );
});

test('page returns 404 when profile cannot be fetched', function (): void {
    $auth = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnFalse();

    $profiles = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->once()->andThrow(new Exception('Blizzard API error'));

    $this->get('/character/hyjal/unknown')
        ->assertStatus(404)
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('CharacterPage')
            ->where('character', null)
            ->where('meta.jsonLd', null)
        );
});

test('page tells whether the character belongs to the signed-in user', function (bool $owned): void {
    $auth = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnTrue();

    $cross = $this->mock(CrossCharacterService::class);
    $cross->shouldIgnoreMissing();
    /** @var \Mockery\Expectation $ownsExp */
    $ownsExp = $auth->shouldReceive('ownsCharacter');
    $ownsExp->once()->with('hyjal', 'thrall')->andReturn($owned);

    $profiles = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->andReturn(characterPageDTO());

    $this->get('/character/hyjal/thrall')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('isOwner', $owned));
})->with([true, false]);

test('page never marks a character as owned for a visitor', function (): void {
    $auth = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnFalse();

    $auth->shouldNotReceive('ownsCharacter');

    $profiles = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->andReturn(characterPageDTO());

    $this->get('/character/hyjal/thrall')
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('isOwner', false));
});

test('page merges only an owned character into the account cross-character data', function (bool $owned): void {
    $auth = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnTrue();
    /** @var \Mockery\Expectation $ownsExp */
    $ownsExp = $auth->shouldReceive('ownsCharacter');
    $ownsExp->andReturn($owned);

    $profiles = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->andReturn(characterPageDTO());

    $cross = $this->mock(CrossCharacterService::class);
    /** @var \Mockery\Expectation $mergeExp */
    $mergeExp = $cross->shouldReceive('mergeCurrentCharacter');
    $mergeExp->times($owned ? 1 : 0);

    $this->get('/character/hyjal/thrall')->assertOk();
})->with(['owned character' => true, 'another player character' => false]);

test('show merges only an owned character into the account cross-character data', function (bool $owned): void {
    $auth = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnTrue();
    /** @var \Mockery\Expectation $ownsExp */
    $ownsExp = $auth->shouldReceive('ownsCharacter');
    $ownsExp->with('hyjal', 'thrall')->andReturn($owned);

    $profiles = $this->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->andReturn(characterPageDTO());

    $cross = $this->mock(CrossCharacterService::class);
    /** @var \Mockery\Expectation $mergeExp */
    $mergeExp = $cross->shouldReceive('mergeCurrentCharacter');
    $mergeExp->times($owned ? 1 : 0);

    $this->getJson('/api/character/hyjal/thrall')->assertOk();
})->with(['owned character' => true, 'another player character' => false]);

function stubVisitorSheet(): void
{
    $auth = test()->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $authExp */
    $authExp = $auth->shouldReceive('isAuthenticated');
    $authExp->andReturnFalse();

    $profiles = test()->mock(CharacterProfileService::class);
    /** @var \Mockery\Expectation $profileExp */
    $profileExp = $profiles->shouldReceive('getProfile');
    $profileExp->andReturn(characterPageDTO());
}

test('page opens the overview without segment', function (): void {
    stubVisitorSheet();

    $this->get('/character/hyjal/thrall')
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('section', null)->where('sub', null));
});

test('page opens the requested section and sub-tab', function (string $url, string $section, string $sub): void {
    stubVisitorSheet();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('CharacterPage')
            ->where('section', $section)
            ->where('sub', $sub));
})->with([
    ['/character/hyjal/thrall/collections/montures', 'collections', 'montures'],
    ['/character/hyjal/thrall/endgame', 'endgame', 'mythique-plus'],
    ['/character/hyjal/thrall/progression/metiers', 'progression', 'metiers'],
]);

test('a sub-route keeps the sheet as its canonical page', function (): void {
    stubVisitorSheet();

    $this->get('/character/hyjal/thrall/collections/montures')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('meta.canonicalUrl', rtrim((string) config('app.url'), '/').'/character/hyjal/thrall'));
});

test('an unknown segment renders the not found sheet', function (string $url): void {
    stubVisitorSheet();

    $this->get($url)
        ->assertNotFound()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('CharacterPage')->where('character', null));
})->with(['/character/hyjal/thrall/inventaire', '/character/hyjal/thrall/progression/montures']);

test('an uppercase segment is redirected to its lowercase form', function (): void {
    $this->get('/character/hyjal/thrall/Collections/Montures')
        ->assertStatus(301)
        ->assertRedirect('/character/hyjal/thrall/collections/montures');
});
