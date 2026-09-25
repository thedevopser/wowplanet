<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.blizzard.client_id' => 'test-client-id',
        'services.blizzard.client_secret' => 'test-client-secret',
        'services.blizzard.region' => 'eu',
    ]);
    Cache::flush();
});

// ─── getAccessToken ─────────────────────────────────────────

test('getAccessToken fetches token and caches it', function (): void {
    Http::fake([
        'eu.battle.net/oauth/token' => Http::response([
            'access_token' => 'test-token-abc',
            'token_type' => 'bearer',
            'expires_in' => 86399,
        ]),
    ]);

    $client = new BlizzardApiClient(new Client);

    $token = $client->getAccessToken();

    expect($token)->toBe('test-token-abc');
    expect(Cache::get('blizzard_access_token'))->toBe('test-token-abc');
});

test('getAccessToken returns cached token without HTTP call', function (): void {
    Cache::put('blizzard_access_token', 'cached-token', 3600);
    Http::fake();

    $client = new BlizzardApiClient(new Client);

    expect($client->getAccessToken())->toBe('cached-token');
    Http::assertNothingSent();
});

test('getAccessToken throws on failed response', function (): void {
    Http::fake([
        'eu.battle.net/oauth/token' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $client = new BlizzardApiClient(new Client);

    $client->getAccessToken();
})->throws(RuntimeException::class, 'Failed to fetch Blizzard access token');

// ─── get ────────────────────────────────────────────────────

test('get sends request with bearer token and namespace', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['name' => 'Thrall'])),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $result = $client->get('data/wow/quest/1');

    expect($result)->toBe(['name' => 'Thrall']);
});

test('get uses custom namespace when provided', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['data' => true])),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->get('data/wow/quest/1', ['namespace' => 'static-eu']);

    $request = $history[0]['request'];
    expect($request->getHeaderLine('Battlenet-Namespace'))->toBe('static-eu');
});

// ─── getResponse ────────────────────────────────────────────

test('getResponse returns a typed reader over the decoded body', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['id' => 40, 'name' => 'Saison 40'])),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $responsePayload = $client->getResponse('data/wow/pvp-season/40');

    expect($responsePayload->requiredInt('id'))->toBe(40)
        ->and($responsePayload->requiredString('name'))->toBe('Saison 40');
});

test('getResponse names the endpoint in the contract errors it raises', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['name' => 'Saison 40'])),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->getResponse('data/wow/pvp-season/40')->requiredInt('id');
})->throws(MissingFieldException::class, 'Missing field [id] in the response of [data/wow/pvp-season/40]');

// ─── getWithUserToken ───────────────────────────────────────

test('getWithUserToken uses provided user token', function (): void {
    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['characters' => []])),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $responsePayload = $client->getWithUserToken('profile/user/wow', 'user-oauth-token');

    expect($responsePayload)->toEqual(ResponsePayload::forEndpoint('profile/user/wow', ['characters' => []]));

    $request = $history[0]['request'];
    expect($request->getHeaderLine('Authorization'))->toBe('Bearer user-oauth-token');
    expect($request->getHeaderLine('Battlenet-Namespace'))->toBe('profile-eu');
});

// ─── getBaseOptions ─────────────────────────────────────────

test('getBaseOptions returns static namespace headers', function (): void {
    Cache::put('blizzard_access_token', 'base-token', 3600);

    $client = new BlizzardApiClient(new Client);

    $options = $client->getBaseOptions();

    expect($options['headers']['Authorization'])->toBe('Bearer base-token');
    expect($options['headers']['Battlenet-Namespace'])->toBe('static-eu');
    expect($options['query']['locale'])->toBe('fr_FR');
    expect($options['query']['namespace'])->toBe('static-eu');
});

// ─── getClient ──────────────────────────────────────────────

test('getClient returns injected Guzzle client', function (): void {
    $guzzle = new Client;
    $client = new BlizzardApiClient($guzzle);

    expect($client->getClient())->toBe($guzzle);
});

// ─── getAsync ───────────────────────────────────────────────

test('getAsync sends request with bearer token and default namespace', function (): void {
    Cache::put('blizzard_access_token', 'async-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['async' => true])),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $response = $client->getAsync('data/wow/item-appearance/321')->wait();

    expect($response->getStatusCode())->toBe(200);
    $request = $history[0]['request'];
    expect($request->getHeaderLine('Authorization'))->toBe('Bearer async-token');
    expect($request->getHeaderLine('Battlenet-Namespace'))->toBe('profile-eu');
});

test('getAsync uses custom namespace when provided', function (): void {
    Cache::put('blizzard_access_token', 'async-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, [], '{}'),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->getAsync('data/wow/mount/6', ['namespace' => 'static-eu'])->wait();

    expect($history[0]['request']->getHeaderLine('Battlenet-Namespace'))->toBe('static-eu');
});

test('getAsync preserves query parameters embedded in the endpoint', function (): void {
    Cache::put('blizzard_access_token', 'async-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, [], '{}'),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->getAsync('data/wow/search/media?_pageSize=1000&orderby=id&id=[25,1024]&tags=item', ['namespace' => 'static-eu'])->wait();

    parse_str((string) $history[0]['request']->getUri()->getQuery(), $sent);
    expect($sent['_pageSize'])->toBe('1000')
        ->and($sent['id'])->toBe('[25,1024]')
        ->and($sent['tags'])->toBe('item')
        ->and($sent['locale'])->toBe('fr_FR')
        ->and($sent['namespace'])->toBe('static-eu');
});

test('get preserves query parameters embedded in the endpoint', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, [], '{}'),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->get('data/wow/search/item?_pageSize=500&id=[1,500]', ['namespace' => 'static-eu']);

    parse_str((string) $history[0]['request']->getUri()->getQuery(), $sent);
    expect($sent['_pageSize'])->toBe('500')
        ->and($sent['locale'])->toBe('fr_FR');
});

// ─── getRegion ──────────────────────────────────────────────

test('getRegion returns configured region', function (): void {
    $client = new BlizzardApiClient(new Client);

    expect($client->getRegion())->toBe('eu');
});

// ─── getCurrentMythicSeasonId ───────────────────────────────

test('getCurrentMythicSeasonId fetches and caches the current season', function (): void {
    Cache::put('blizzard_access_token', 'season-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['current_season' => ['id' => 14]])),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    expect($client->getCurrentMythicSeasonId())->toBe(14)
        ->and(Cache::get('blizzard_current_m_plus_season'))->toBe(14);

    // Deuxième appel servi par le cache : aucune requête supplémentaire dans la queue
    expect($client->getCurrentMythicSeasonId())->toBe(14);
});

// ─── getCurrentPvpSeasonId ──────────────────────────────────

test('getCurrentPvpSeasonId fetches and caches the current season', function (): void {
    Cache::put('blizzard_access_token', 'season-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['current_season' => ['id' => 40]])),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    expect($client->getCurrentPvpSeasonId())->toBe(40)
        ->and(Cache::get('blizzard_current_pvp_season'))->toBe(40);

    // Deuxième appel servi par le cache : aucune requête supplémentaire dans la queue
    expect($client->getCurrentPvpSeasonId())->toBe(40);
});

test('getCurrentPvpSeasonId rejects a current season without an id', function (): void {
    Cache::put('blizzard_access_token', 'season-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['current_season' => ['key' => ['href' => 'https://eu.api.blizzard.com/']]])),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->getCurrentPvpSeasonId();
})->throws(MissingFieldException::class, 'Missing field [current_season.id] in the response of [data/wow/pvp-season/index]');

test('getCurrentPvpSeasonId returns 0 when the index has no current season', function (): void {
    Cache::put('blizzard_access_token', 'season-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, [], json_encode(['seasons' => []])),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    expect($client->getCurrentPvpSeasonId())->toBe(0);
});

// ─── build servi par l'API ──────────────────────────────────

test('it records the build served with each response', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, ['battlenet-namespace' => 'static-12.1.0_68914-eu'], '{}'),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    expect($client->lastSeenBuild())->toBeNull();

    $client->get('data/wow/quest/1');

    expect($client->lastSeenBuild())->toBe('12.1.0_68914');
});

test('a response without a versioned namespace leaves the last seen build alone', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $mockHandler = new MockHandler([
        new Response(200, ['battlenet-namespace' => 'static-12.1.0_68914-eu'], '{}'),
        new Response(200, ['battlenet-namespace' => 'profile-eu'], '{}'),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->get('data/wow/quest/1');
    $client->get('profile/user/wow');

    expect($client->lastSeenBuild())->toBe('12.1.0_68914');
});

test('currentBuild probes a lightweight index when nothing has been fetched yet', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, ['battlenet-namespace' => 'static-12.1.0_68914-eu'], '{}'),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    expect($client->currentBuild())->toBe('12.1.0_68914')
        ->and($history)->toHaveCount(1);
});

test('currentBuild reuses the build already seen rather than spending a request', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, ['battlenet-namespace' => 'static-12.1.0_68914-eu'], '{}'),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->get('data/wow/quest/1');

    expect($client->currentBuild())->toBe('12.1.0_68914')
        ->and($history)->toHaveCount(1);
});

// ─── requêtes conditionnelles ───────────────────────────────

test('a conditional request sends If-Modified-Since when a date is known', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([
        new Response(200, ['Last-Modified' => 'Wed, 10 Sep 2026 08:00:00 GMT'], '{"id":1}'),
    ]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $payload = $client->getIfModifiedSince('data/wow/mount/index', 'Tue, 09 Sep 2026 08:00:00 GMT');

    expect($payload?->requiredInt('id'))->toBe(1)
        ->and($history[0]['request']->getHeaderLine('If-Modified-Since'))->toBe('Tue, 09 Sep 2026 08:00:00 GMT')
        ->and($client->lastModifiedSeen())->toBe('Wed, 10 Sep 2026 08:00:00 GMT');
});

test('a conditional request without a known date sends no If-Modified-Since', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $history = [];
    $mockHandler = new MockHandler([new Response(200, [], '{"id":1}')]);
    $handlerStack = HandlerStack::create($mockHandler);
    $handlerStack->push(\GuzzleHttp\Middleware::history($history));

    $guzzle = new Client(['handler' => $handlerStack, 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    $client->getIfModifiedSince('data/wow/mount/index', null);

    expect($history[0]['request']->hasHeader('If-Modified-Since'))->toBeFalse();
});

test('a 304 means unchanged, not an error', function (): void {
    Cache::put('blizzard_access_token', 'my-token', 3600);

    $mockHandler = new MockHandler([
        new Response(304, ['battlenet-namespace' => 'static-12.1.0_68914-eu'], ''),
    ]);

    $guzzle = new Client(['handler' => HandlerStack::create($mockHandler), 'base_uri' => 'https://eu.api.blizzard.com/']);
    $client = new BlizzardApiClient($guzzle);

    expect($client->getIfModifiedSince('data/wow/mount/index', 'Tue, 09 Sep 2026 08:00:00 GMT'))->toBeNull()
        ->and($client->lastSeenBuild())->toBe('12.1.0_68914');
});

// ─── saisons relues du cache ────────────────────────────────

// Laravel ne sérialise pas les valeurs numériques : Redis les rend donc en chaîne, là
// où le store array des tests rend l'entier écrit. D'où un TypeError qui n'apparaît
// qu'en dehors de la suite. Ces deux tests reproduisent la relecture telle qu'elle se
// présente en production, sans dépendre du store.

test('a mythic season read back from the cache is still an integer', function (): void {
    Cache::put('blizzard_current_m_plus_season', '14', 3600);

    expect((new BlizzardApiClient(new Client))->getCurrentMythicSeasonId())->toBe(14);
});

test('a pvp season read back from the cache is still an integer', function (): void {
    Cache::put('blizzard_current_pvp_season', '40', 3600);

    expect((new BlizzardApiClient(new Client))->getCurrentPvpSeasonId())->toBe(40);
});
