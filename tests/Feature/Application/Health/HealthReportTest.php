<?php

declare(strict_types=1);

use App\Application\Health\HealthReport;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Models\ApplicationError;
use App\Models\WowAchievement;
use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use App\Models\WowProfession;
use App\Models\WowQuest;
use App\Models\WowRecipe;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    useRedisQueue();
});

test('it gathers services, quota, queue, volumes and recent errors', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(120);
    ApplicationError::factory()->create(['message' => 'Failed to fetch talents']);

    $snapshot = resolve(HealthReport::class)->snapshot();

    expect(array_keys($snapshot))->toBe(['services', 'quota', 'queue', 'volumes', 'errors'])
        ->and($snapshot['quota'])->toMatchArray(['status' => 'ok', 'used' => 120, 'import_ceiling' => 30_000])
        ->and($snapshot['queue'])->toMatchArray(['status' => 'ok', 'pending' => 0])
        ->and($snapshot['errors']['entries'][0]['message'])->toBe('Failed to fetch talents')
        ->and(array_column($snapshot['services'], 'status'))->each->toBe('ok');
});

test('the volume section names how many catalogue tables are empty', function (): void {
    WowMount::factory()->create();

    $volumes = resolve(HealthReport::class)->snapshot()['volumes'];

    expect($volumes['status'])->toBe('critical')
        ->and($volumes['issue'])->toBe('7 tables du catalogue vides.')
        ->and($volumes['tables'])->toHaveCount(13);
});

test('a volume section with every catalogue table populated is healthy', function (): void {
    foreach ([WowAchievement::class, WowQuest::class, WowProfession::class, WowRecipe::class, WowMount::class, WowPet::class, WowDecor::class, WowAppearance::class] as $model) {
        $model::factory()->create();
    }

    expect(resolve(HealthReport::class)->snapshot()['volumes'])->toMatchArray(['status' => 'ok', 'issue' => null]);
});

test('a section that cannot be measured is reported without taking the page down', function (): void {
    // PostgreSQL rend le DDL transactionnel : la table revient au rollback du test.
    Schema::drop('wow_pets');

    $snapshot = resolve(HealthReport::class)->snapshot();

    expect($snapshot['volumes'])->toMatchArray([
        'status' => 'unavailable',
        'issue' => 'Volumétries : mesure impossible.',
    ])
        ->and($snapshot['volumes']['detail'])->toContain('wow_pets')
        ->and($snapshot['quota']['status'])->toBe('ok');
});

test('a queue that cannot be measured is reported as such', function (): void {
    config(['queue.default' => 'sync']);

    expect(resolve(HealthReport::class)->snapshot()['queue'])->toMatchArray([
        'status' => 'unavailable',
        'issue' => 'Queue : mesure impossible.',
    ]);
});
