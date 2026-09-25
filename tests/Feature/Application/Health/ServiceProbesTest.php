<?php

declare(strict_types=1);

use App\Application\Health\ServiceProbes;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Redis;

/**
 * @return array<string, array{service: string, status: string, issue: string|null, detail: string|null}>
 */
function probesByService(): array
{
    $probes = [];

    foreach (resolve(ServiceProbes::class)->probe() as $probe) {
        $probes[$probe['service']] = $probe;
    }

    return $probes;
}

test('a reachable PostgreSQL and every Redis index are reported healthy', function (): void {
    $probes = probesByService();

    expect(array_keys($probes))->toBe(['postgresql', 'redis:default', 'redis:cache', 'redis:queue', 'redis:imports', 'redis:budget', 'redis:session'])
        ->and(array_unique(array_column($probes, 'status')))->toBe(['ok'])
        ->and($probes['postgresql']['issue'])->toBeNull();
});

test('an unreachable Redis index is reported without hiding the others', function (): void {
    // Le gestionnaire Redis copie sa configuration à sa construction : on le reconstruit.
    config(['database.redis.budget.port' => 1, 'database.redis.budget.max_retries' => 0]);
    $this->app->forgetInstance('redis');
    Redis::clearResolvedInstance('redis');

    $probes = probesByService();

    expect($probes['redis:budget'])->toMatchArray(['status' => 'unavailable', 'issue' => 'Redis (budget) injoignable.'])
        ->and($probes['redis:budget']['detail'])->not->toBeNull()
        ->and($probes['redis:cache']['status'])->toBe('ok')
        ->and($probes['postgresql']['status'])->toBe('ok');
});

test('an unreachable PostgreSQL is reported as such', function (): void {
    $mock = Mockery::mock(ConnectionInterface::class);
    $mock->shouldReceive('select')->andThrow(new RuntimeException('connection refused'));

    $resolver = Mockery::mock(ConnectionResolverInterface::class);
    $resolver->shouldReceive('connection')->andReturn($mock);

    $this->app->when(ServiceProbes::class)->needs(ConnectionResolverInterface::class)->give(fn () => $resolver);

    expect(probesByService()['postgresql'])->toBe([
        'service' => 'postgresql',
        'status' => 'unavailable',
        'issue' => 'PostgreSQL injoignable.',
        'detail' => 'connection refused',
    ]);
});
