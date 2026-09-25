<?php

declare(strict_types=1);

use App\Jobs\ComputeCrossCharacterJob;
use App\Jobs\RunImportJob;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    useRedisQueue();
});

/**
 * @return array<string, string|int|array<string, string|null>|null>
 */
function lastQueuedPayload(): array
{
    $raw = Redis::connection('queue')->lindex('queues:imports', -1);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;

    throw_unless(is_array($decoded), RuntimeException::class, 'Nothing was queued on imports.');

    /** @var array<string, string|int|array<string, string|null>|null> $decoded */
    return $decoded;
}

test('a described job carries its public label and account at the top of its payload', function (): void {
    dispatch(new ComputeCrossCharacterJob('job-1', '42', [], 'secret-token', 'Thrall#1234'));

    expect(lastQueuedPayload()['described'])->toBe(['label' => 'Données des autres personnages', 'account' => 'Thrall#1234']);
});

test('the catalogue import is described without an account', function (): void {
    dispatch(new RunImportJob('job-1', 'app:wow-data-import'));

    expect(lastQueuedPayload()['described'])->toBe(['label' => 'Import du catalogue', 'account' => null]);
});

test('a job that does not describe itself gets no public label', function (): void {
    dispatch(static function (): void {})->onQueue('imports');

    expect(lastQueuedPayload())->not->toHaveKey('described');
});
