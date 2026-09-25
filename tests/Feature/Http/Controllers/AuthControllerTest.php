<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

test('redirect sends to blizzard oauth', function (): void {
    $testResponse = $this->get('/auth/blizzard/redirect');

    $testResponse->assertRedirect();

    $location = $testResponse->headers->get('Location');
    expect($location)->toBeString()
        ->toContain('battle.net/oauth/authorize')
        ->toContain('wow.profile');
});

test('callback rejects invalid state', function (): void {
    $this->get('/auth/blizzard/callback?code=abc&state=invalid')
        ->assertRedirect('/');
});

test('callback exchanges code for token', function (): void {
    Http::fake([
        '*.battle.net/oauth/token' => Http::response([
            'access_token' => 'test-token-123',
            'token_type' => 'bearer',
            'expires_in' => 86399,
        ]),
    ]);

    $state = 'test-state-value';
    $this->withSession(['blizzard_oauth_state' => $state])
        ->get('/auth/blizzard/callback?code=valid-code&state='.$state)
        ->assertRedirect('/mon-compte');
});

function completeBlizzardLogin(Tests\TestCase $testCase, callable|array|null $userInfo): Illuminate\Testing\TestResponse
{
    Http::fake([
        '*.battle.net/oauth/token' => Http::response(['access_token' => 'user-token']),
        '*.battle.net/oauth/userinfo' => is_callable($userInfo) ? $userInfo : Http::response($userInfo ?? [], $userInfo === null ? 500 : 200),
    ]);

    return $testCase->withSession(['blizzard_oauth_state' => 'state-1'])
        ->get('/auth/blizzard/callback?code=valid-code&state=state-1');
}

test('a successful login stores the battle.net identity in session', function (): void {
    config(['services.blizzard.admin_bnet_id' => '999']);

    completeBlizzardLogin($this, ['sub' => '12345', 'battletag' => 'Thrall#1234'])
        ->assertRedirect('/mon-compte')
        ->assertSessionHas('blizzard_user_token', 'user-token')
        ->assertSessionHas('bnet_user_id', '12345')
        ->assertSessionHas('bnet_battletag', 'Thrall#1234')
        ->assertSessionMissing('is_admin');

    Http::assertSent(fn ($request): bool => str_ends_with((string) $request->url(), '/oauth/userinfo')
        && $request->hasHeader('Authorization', 'Bearer user-token'));
});

test('the user whose id is the configured admin id is flagged admin', function (): void {
    config(['services.blizzard.admin_bnet_id' => '12345']);

    completeBlizzardLogin($this, ['sub' => '12345', 'battletag' => 'Thrall#1234'])
        ->assertSessionHas('is_admin', true);
});

test('without a configured admin id, a user info without id grants no admin', function (): void {
    config(['services.blizzard.admin_bnet_id' => '']);

    completeBlizzardLogin($this, ['battletag' => 'Thrall#1234'])
        ->assertSessionHas('bnet_user_id', '')
        ->assertSessionMissing('is_admin');
});

test('malformed user info stores an empty identity and grants no admin', function (): void {
    config(['services.blizzard.admin_bnet_id' => '12345']);

    completeBlizzardLogin($this, ['sub' => 12345, 'battletag' => ['Thrall']])
        ->assertSessionHas('bnet_user_id', '')
        ->assertSessionHas('bnet_battletag', '')
        ->assertSessionMissing('is_admin');
});

test('a failed user info request keeps the user logged in without identity', function (): void {
    config(['services.blizzard.admin_bnet_id' => '']);

    completeBlizzardLogin($this, null)
        ->assertRedirect('/mon-compte')
        ->assertSessionHas('blizzard_user_token', 'user-token')
        ->assertSessionMissing('bnet_user_id')
        ->assertSessionMissing('is_admin');
});

test('an unreachable user info endpoint keeps the user logged in without identity', function (): void {
    completeBlizzardLogin($this, function (): never {
        throw new Illuminate\Http\Client\ConnectionException('Connection timed out');
    })
        ->assertRedirect('/mon-compte')
        ->assertSessionMissing('error')
        ->assertSessionHas('blizzard_user_token', 'user-token')
        ->assertSessionMissing('bnet_user_id');
});

test('an interrupted login tells the user to start it again', function (): void {
    $this->get('/auth/blizzard/callback?code=abc&state=invalid')
        ->assertSessionHas('error', 'La connexion Battle.net a été interrompue. Relancez-la depuis le bouton « Se connecter ».');
});

test('a login refused by battle.net tells the user to retry', function (GuzzleHttp\Promise\PromiseInterface $promise): void {
    Http::fake(['*.battle.net/oauth/token' => $promise]);

    $this->withSession(['blizzard_oauth_state' => 'state-1'])
        ->get('/auth/blizzard/callback?code=valid-code&state=state-1')
        ->assertSessionHas('error', 'Battle.net n’a pas validé la connexion. Réessayez dans un instant.');
})->with([
    'token exchange failure' => fn (): GuzzleHttp\Promise\PromiseInterface => Http::response([], 500),
    'token without access token' => fn (): GuzzleHttp\Promise\PromiseInterface => Http::response(['token_type' => 'bearer']),
]);

test('an unexpected login error tells the user to retry', function (): void {
    Http::fake(['*.battle.net/oauth/token' => fn () => throw new RuntimeException('network down')]);

    $this->withSession(['blizzard_oauth_state' => 'state-1'])
        ->get('/auth/blizzard/callback?code=valid-code&state=state-1')
        ->assertSessionHas('error', 'Une erreur est survenue pendant la connexion à Battle.net. Réessayez dans un instant.');
});

test('a successful login returns to the page asked for before signing in', function (): void {
    session(['url.intended' => '/mon-compte/score']);

    completeBlizzardLogin($this, ['sub' => '12345', 'battletag' => 'Thrall#1234'])
        ->assertRedirect('/mon-compte/score')
        ->assertSessionMissing('url.intended');
});

test('a successful login without a remembered page leads to the account hub', function (): void {
    completeBlizzardLogin($this, ['sub' => '12345', 'battletag' => 'Thrall#1234'])
        ->assertRedirect('/mon-compte');
});

test('a successful login ignores a remembered page outside the site', function (string $intended): void {
    session(['url.intended' => $intended]);

    completeBlizzardLogin($this, ['sub' => '12345', 'battletag' => 'Thrall#1234'])
        ->assertRedirect('/mon-compte');
})->with(['https://evil.example/phish', '//evil.example', '/\\evil.example', 'my-score']);

test('a failed login keeps the remembered page for the next attempt', function (): void {
    $this->withSession(['url.intended' => '/mon-compte/score'])
        ->get('/auth/blizzard/callback?code=abc&state=invalid')
        ->assertRedirect('/')
        ->assertSessionHas('url.intended', '/mon-compte/score');
});
