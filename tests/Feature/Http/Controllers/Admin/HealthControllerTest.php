<?php

declare(strict_types=1);

use App\Jobs\ComputeCrossCharacterJob;
use App\Models\ApplicationError;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    useRedisQueue();
});

test('the health page renders AdminHealthPage for an administrator', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/health')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AdminHealthPage'));
});

test('the health page carries every section of the diagnosis', function (): void {
    ApplicationError::factory()->create(['message' => 'Failed to fetch talents']);
    recordFailedJob();

    $this->withSession(['is_admin' => true])
        ->get('/admin/health')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('health.services.0.service', 'postgresql')
            ->where('health.quota.status', 'ok')
            ->where('health.queue.status', 'warning')
            ->has('health.queue.failed', 1)
            ->where('health.volumes.status', 'critical')
            ->where('health.errors.entries.0.message', 'Failed to fetch talents'));
});

test('a section that cannot be measured does not keep the page from rendering', function (): void {
    Schema::drop('wow_mounts');

    $this->withSession(['is_admin' => true])
        ->get('/admin/health')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('health.volumes.status', 'unavailable')
            ->where('health.quota.status', 'ok'));
});

test('the queue section is measured alone, with the jobs taken and waiting', function (): void {
    $this->travelTo(now()->setTimestamp(1_700_000_000));
    dispatch(new ComputeCrossCharacterJob('job-1', '42', [], 'secret-token', 'Thrall#1234'));
    Queue::connection('redis')->pop('imports');
    dispatch(new ComputeCrossCharacterJob('job-2', '43', [], 'secret-token', 'Jaina#5678'));

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/health/queue')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('pending', 1)
        ->assertJsonPath('reserved', 1)
        ->assertJsonPath('running', [['label' => 'Données des autres personnages', 'account' => 'Thrall#1234', 'since' => 1_700_000_000]])
        ->assertJsonPath('waiting', [['label' => 'Données des autres personnages', 'account' => 'Jaina#5678', 'since' => 1_700_000_000]])
        ->assertJsonMissingPath('volumes')
        ->assertJsonMissingPath('services');
});

test('the queue section never hands out what the job carries', function (): void {
    dispatch(new ComputeCrossCharacterJob('job-1', '42', [['name' => 'Thrall', 'realmSlug' => 'hyjal']], 'secret-token', 'Thrall#1234'));
    Queue::connection('redis')->pop('imports');
    dispatch(new ComputeCrossCharacterJob('job-2', '42', [['name' => 'Thrall', 'realmSlug' => 'hyjal']], 'secret-token', 'Thrall#1234'));

    $content = (string) $this->withSession(['is_admin' => true])->getJson('/api/admin/health/queue')->assertOk()->getContent();

    expect($content)->not->toContain('secret-token')->not->toContain('hyjal')->not->toContain('job-1');
});

test('an unreachable queue is reported as the page reports it, not as a server error', function (): void {
    config(['database.redis.queue.port' => 1]);
    // The Redis manager keeps the configuration it was built with.
    app()->forgetInstance('redis');
    Redis::clearResolvedInstance('redis');

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/health/queue')
        ->assertOk()
        ->assertJsonPath('status', 'unavailable')
        ->assertJsonPath('issue', 'Queue : mesure impossible.')
        ->assertJsonStructure(['detail']);
});

test('the queue section is refused to a visitor who is not an administrator', function (): void {
    $this->withSession(['blizzard_user_token' => 'fake-token'])
        ->getJson('/api/admin/health/queue')
        ->assertForbidden()
        ->assertExactJson(['error' => 'Forbidden']);
});

test('the queue section sits behind the panel throttle and the admin middleware', function (): void {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn (RoutingRoute $routingRoute): bool => $routingRoute->uri() === 'api/admin/health/queue');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:admin', 'admin');
});
