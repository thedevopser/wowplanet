<?php

declare(strict_types=1);

use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportRequest;
use App\Application\Import\ImportRun;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStep;
use App\Application\Import\ImportWait;
use App\Application\Import\RowTally;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow('2026-09-13 10:00:00');
});

afterEach(function (): void {
    Date::setTestNow();
});

test('a saved run is found again with everything it carried', function (): void {
    $store = new ImportProgressStore;

    $importRun = ImportRun::start('job-1', [ImportStage::Quests, ImportStage::Mounts], now()->getTimestamp())
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(new RowTally(3, 2, 1), apiCalls: 7, durationMs: 900))
        ->withBudgetUsed(1_200);

    $store->save($importRun);

    expect($store->find('job-1'))->toEqual($importRun);
});

test('an unknown job holds no run', function (): void {
    expect((new ImportProgressStore)->find('nobody'))->toBeNull();
});

test('a job tracked by the plain command path holds no run either', function (): void {
    Cache::put('admin_import:job-2', ['status' => 'running', 'output' => null], 60);

    expect((new ImportProgressStore)->find('job-2'))->toBeNull();
});

test('the stored value keeps the status and output the admin panel already reads', function (): void {
    $store = new ImportProgressStore;

    $store->save(ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp()));

    /** @var array{status: string, output: string} $stored */
    $stored = Cache::get('admin_import:job-1');

    expect($stored['status'])->toBe('pending')
        ->and($stored['output'])->toContain('Montures');
});

test('the payload answers the progress endpoint with stage, budget and timings', function (): void {
    $store = new ImportProgressStore;

    $store->save(
        ImportRun::start('job-1', [ImportStage::Quests, ImportStage::Mounts], now()->getTimestamp())
            ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 30_000))
            ->withStep(ImportStep::pending(ImportStage::Mounts)->started())
            ->withBudgetUsed(12_340)
    );

    Date::setTestNow('2026-09-13 10:00:40');

    $payload = $store->payload('job-1');

    expect($payload['status'])->toBe('running')
        ->and($payload['stage'])->toBe('mounts')
        ->and($payload['stage_label'])->toBe('Montures')
        ->and($payload['percent'])->toBe(50)
        ->and($payload['elapsed_seconds'])->toBe(40)
        ->and($payload['eta_seconds'])->toBe(30)
        ->and($payload['budget']['used'])->toBe(12_340)
        ->and($payload['budget']['ceiling'])->toBe(30_000)
        ->and($payload['waiting'])->toBeNull();
});

test('the payload says why the import is waiting', function (): void {
    $store = new ImportProgressStore;

    $store->save(
        ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp())
            ->waitingOn(ImportWait::hourlyBudget(240))
    );

    $payload = $store->payload('job-1');

    expect($payload['waiting']['reason'])->toBe('hourly_budget')
        ->and($payload['waiting']['seconds'])->toBe(240)
        ->and($payload['waiting']['message'])->toContain('plafond horaire');
});

test('the payload details every stage of the report', function (): void {
    $store = new ImportProgressStore;

    $store->save(
        ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp())
            ->withStep(ImportStep::pending(ImportStage::Mounts)->finished(new RowTally(43, 2, 1), apiCalls: 5, durationMs: 1_100))
    );

    $step = $store->payload('job-1')['steps'][0];

    expect($step['stage'])->toBe('mounts')
        ->and($step['label'])->toBe('Montures')
        ->and($step['status'])->toBe('completed')
        ->and($step['created'])->toBe(43)
        ->and($step['updated'])->toBe(2)
        ->and($step['deleted'])->toBe(1)
        ->and($step['api_calls'])->toBe(5)
        ->and($step['duration_ms'])->toBe(1_100)
        ->and($step['error'])->toBeNull();
});

test('a job tracked by the plain command path is answered as it always was', function (): void {
    Cache::put('admin_import:job-2', ['status' => 'completed', 'output' => 'Done.'], 60);

    expect((new ImportProgressStore)->payload('job-2'))
        ->toBe(['status' => 'completed', 'output' => 'Done.']);
});

test('an unknown job is answered as not found', function (): void {
    expect((new ImportProgressStore)->payload('nobody'))
        ->toBe(['status' => 'not_found', 'output' => null]);
});

test('a paused import says since when and how long before it is abandoned', function (): void {
    $store = new ImportProgressStore;
    $store->save(
        ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp())
            ->paused(at: now()->getTimestamp() - 120)
    );

    $payload = $store->payload('job-1');

    expect($payload['status'])->toBe('paused')
        ->and($payload['interrupted_for'])->toBe(120)
        ->and($payload['abandoned_in'])->toBe(ImportRequest::ABANDON_AFTER_S - 120);
});

test('an import nobody interrupted counts no interruption', function (): void {
    $store = new ImportProgressStore;
    $store->save(ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp()));

    $payload = $store->payload('job-1');

    expect($payload['interrupted_for'])->toBeNull()
        ->and($payload['abandoned_in'])->toBeNull();
});

test('a cancelled import counts down to no abandonment, being over already', function (): void {
    $store = new ImportProgressStore;
    $store->save(
        ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp())
            ->cancelled(at: now()->getTimestamp())
    );

    expect($store->payload('job-1')['abandoned_in'])->toBeNull();
});
