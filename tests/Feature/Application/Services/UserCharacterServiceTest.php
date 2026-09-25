<?php

declare(strict_types=1);

use App\Application\Services\UserCharacterService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

beforeEach(function (): void {
    config(['services.blizzard.region' => 'eu']);
    Cache::flush();
});

// ─── isAuthenticated ────────────────────────────────────────

test('isAuthenticated returns true when session has token', function (): void {
    Session::put('blizzard_user_token', 'some-token');

    $userCharacterService = resolve(UserCharacterService::class);

    expect($userCharacterService->isAuthenticated())->toBeTrue();
});

test('isAuthenticated returns false when no token', function (): void {
    $userCharacterService = resolve(UserCharacterService::class);

    expect($userCharacterService->isAuthenticated())->toBeFalse();
});

// ─── logout ─────────────────────────────────────────────────

test('logout forgets token from session', function (): void {
    Session::put('blizzard_user_token', 'some-token');

    $userCharacterService = resolve(UserCharacterService::class);
    $userCharacterService->logout();

    expect(Session::has('blizzard_user_token'))->toBeFalse();
});

// ─── getUserCharacters ──────────────────────────────────────

test('getUserCharacters returns empty when no token in session', function (): void {
    $userCharacterService = resolve(UserCharacterService::class);

    expect($userCharacterService->getUserCharacters())->toBe([]);
});

test('getUserCharacters parses multi-account response', function (): void {
    Session::put('blizzard_user_token', 'user-token');

    $mock = $this->mock(BlizzardApiClient::class);
    $mock->shouldReceive('getWithUserToken')
        ->with('profile/user/wow', 'user-token')
        ->andReturn(ResponsePayload::forEndpoint('profile/user/wow', [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Thrall',
                            'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                            'level' => 80,
                            'playable_class' => ['id' => 7, 'name' => 'Chaman'],
                            'playable_race' => ['id' => 2, 'name' => 'Orc'],
                            'faction' => ['name' => 'Horde'],
                        ],
                        [
                            'name' => 'Arthas',
                            'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                            'level' => 70,
                            'playable_class' => ['id' => 6, 'name' => 'Chevalier de la mort'],
                            'playable_race' => ['id' => 1, 'name' => 'Humain'],
                            'faction' => ['name' => 'Alliance'],
                        ],
                    ],
                ],
            ],
        ]));

    Http::fake([
        '*/profile/wow/character/hyjal/arthas/character-media*' => Http::response([
            'assets' => [['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/arthas.jpg']],
        ]),
        '*/profile/wow/character/hyjal/thrall/character-media*' => Http::response([
            'assets' => [['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/thrall.jpg']],
        ]),
    ]);

    $userCharacterService = resolve(UserCharacterService::class);
    $characters = $userCharacterService->getUserCharacters();

    expect($characters)->toHaveCount(2);
    // Sorted alphabetically: Arthas before Thrall
    expect($characters[0]['name'])->toBe('Arthas');
    expect($characters[0]['className'])->toBe('Chevalier de la mort');
    expect($characters[0]['faction'])->toBe('Alliance');
    expect($characters[1]['name'])->toBe('Thrall');
    expect($characters[1]['className'])->toBe('Chaman');
});

test('getUserCharacters handles missing avatar gracefully', function (): void {
    Session::put('blizzard_user_token', 'user-token');

    $mock = $this->mock(BlizzardApiClient::class);
    $mock->shouldReceive('getWithUserToken')
        ->andReturn(ResponsePayload::forEndpoint('profile/user/wow', [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Thrall',
                            'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                            'level' => 80,
                            'playable_class' => ['id' => 7, 'name' => 'Chaman'],
                            'playable_race' => ['id' => 2, 'name' => 'Orc'],
                            'faction' => ['name' => 'Horde'],
                        ],
                    ],
                ],
            ],
        ]));

    Http::fake([
        '*character-media*' => Http::response([], 404),
    ]);

    $userCharacterService = resolve(UserCharacterService::class);
    $characters = $userCharacterService->getUserCharacters();

    expect($characters[0]['avatarUrl'])->toBe('');
});

// ─── getClassIcons ──────────────────────────────────────────

test('getClassIcons fetches icons and caches result', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    $mock->shouldReceive('getAccessToken')->andReturn('service-token');

    Http::fake([
        '*/data/wow/media/playable-class/*' => Http::response([
            'assets' => [['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/icon.jpg']],
        ]),
    ]);

    $userCharacterService = resolve(UserCharacterService::class);
    $icons = $userCharacterService->getClassIcons();

    expect($icons)->toHaveCount(13); // 13 classes
    expect($icons[1])->toBe('https://render.worldofwarcraft.com/icon.jpg');
    expect(Cache::has('wow_class_icons'))->toBeTrue();
});

test('getClassIcons returns cached icons without HTTP call', function (): void {
    Cache::put('wow_class_icons', [1 => 'cached-icon'], 86400 * 30);
    Http::fake();

    $userCharacterService = resolve(UserCharacterService::class);
    $icons = $userCharacterService->getClassIcons();

    expect($icons)->toBe([1 => 'cached-icon']);
    Http::assertNothingSent();
});

test('getClassIcons skips failed responses', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    $mock->shouldReceive('getAccessToken')->andReturn('service-token');

    Http::fake([
        '*/data/wow/media/playable-class/1*' => Http::response([
            'assets' => [['key' => 'icon', 'value' => 'https://icon1.jpg']],
        ]),
        '*/data/wow/media/playable-class/*' => Http::response([], 500),
    ]);

    $userCharacterService = resolve(UserCharacterService::class);
    $icons = $userCharacterService->getClassIcons();

    expect($icons)->toHaveKey(1);
    expect($icons[1])->toBe('https://icon1.jpg');
});

// ─── ownsCharacter ──────────────────────────────────────────

function mockAccountCharacters(int $times = 1): void
{
    $mock = test()->mock(BlizzardApiClient::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getWithUserToken');
    $exp->times($times)->with('profile/user/wow', 'user-token')->andReturn(ResponsePayload::forEndpoint('profile/user/wow', [
        'wow_accounts' => [
            ['characters' => [['name' => 'Thrall', 'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal']]]],
            ['characters' => [['name' => 'Jaina', 'realm' => ['name' => 'Kirin Tor', 'slug' => 'kirin-tor']]]],
        ],
    ]));
}

test('ownsCharacter is false for a visitor without calling Blizzard', function (): void {
    $mock = $this->mock(BlizzardApiClient::class);
    $mock->shouldNotReceive('getWithUserToken');

    expect(resolve(UserCharacterService::class)->ownsCharacter('hyjal', 'thrall'))->toBeFalse();
});

test('ownsCharacter recognises a character of any account, whatever its case', function (): void {
    Session::put('blizzard_user_token', 'user-token');
    mockAccountCharacters();

    $userCharacterService = resolve(UserCharacterService::class);

    expect($userCharacterService->ownsCharacter('hyjal', 'thrall'))->toBeTrue()
        ->and($userCharacterService->ownsCharacter('Kirin-Tor', 'JAINA'))->toBeTrue()
        ->and($userCharacterService->ownsCharacter('hyjal', 'arthas'))->toBeFalse();
});

test('ownsCharacter asks Blizzard for the account once per session', function (): void {
    Session::put('blizzard_user_token', 'user-token');
    mockAccountCharacters(1);

    resolve(UserCharacterService::class)->ownsCharacter('hyjal', 'thrall');

    expect(resolve(UserCharacterService::class)->ownsCharacter('hyjal', 'thrall'))->toBeTrue();
});

test('ownsCharacter is false when the account cannot be read', function (): void {
    Session::put('blizzard_user_token', 'user-token');
    $mock = $this->mock(BlizzardApiClient::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getWithUserToken');
    $exp->andThrow(new RuntimeException('Blizzard down'));

    expect(resolve(UserCharacterService::class)->ownsCharacter('hyjal', 'thrall'))->toBeFalse();
});

test('logout forgets the owned characters', function (): void {
    Session::put('blizzard_user_token', 'user-token');
    mockAccountCharacters();

    $userCharacterService = resolve(UserCharacterService::class);
    $userCharacterService->ownsCharacter('hyjal', 'thrall');
    $userCharacterService->logout();

    expect(Session::has(UserCharacterService::OWNED_CHARACTERS_SESSION_KEY))->toBeFalse();
});
