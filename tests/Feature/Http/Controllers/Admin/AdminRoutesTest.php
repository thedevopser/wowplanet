<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

dataset('admin pages', [
    'dashboard' => ['/admin'],
    'imports' => ['/admin/imports'],
    'reference' => ['/admin/reference'],
    'health' => ['/admin/health'],
    'history' => ['/admin/history'],
    'taxonomy' => ['/admin/taxonomy'],
    'tools' => ['/admin/tools'],
]);

test('an admin page sends an anonymous visitor back to the home page', function (string $uri): void {
    $this->get($uri)->assertRedirect('/');
})->with('admin pages');

test('an admin page sends an authenticated non-administrator back to the home page', function (string $uri): void {
    $this->withSession(['blizzard_user_token' => 'fake-token'])
        ->get($uri)
        ->assertRedirect('/');
})->with('admin pages');

test('an admin page sits behind the panel throttle and the admin middleware', function (string $uri): void {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $routingRoute): bool => '/'.$routingRoute->uri() === $uri);

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:admin', 'admin');
})->with('admin pages');

test('following an import for a minute at the polling cadence leaves the panel reachable', function (): void {
    browseAsAdministrator($this);

    foreach (range(1, 60) as $poll) {
        $this->getJson('/api/admin/import/current')->assertOk();
    }

    $this->get('/admin/health')->assertOk();
});

test('the pages of the site outside the panel keep their own tighter throttle', function (): void {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $routingRoute): bool => $routingRoute->uri() === 'api/character-tasks' && in_array('GET', $routingRoute->methods(), true));

    expect($route?->gatherMiddleware())->toContain('throttle:authenticated');
});

test('the panel is still throttled beyond its own ceiling', function (): void {
    browseAsAdministrator($this);

    foreach (range(1, 180) as $request) {
        $this->getJson('/api/admin/import/current')->assertOk();
    }

    $this->getJson('/api/admin/import/current')->assertStatus(429);
});

/**
 * Le limiteur compte par session : toutes les requêtes doivent porter la même, comme
 * celles d'un navigateur. Sans cookie — et un appel JSON n'en envoie qu'avec
 * `withCredentials()` —, chacune ouvrirait une session neuve et échapperait au compte.
 */
function browseAsAdministrator(Tests\TestCase $testCase): void
{
    $session = resolve(\Illuminate\Session\SessionManager::class)->driver();
    $session->put('is_admin', true);
    $session->save();

    $testCase->withCredentials()->withCookie((string) config('session.cookie'), $session->getId());
}
