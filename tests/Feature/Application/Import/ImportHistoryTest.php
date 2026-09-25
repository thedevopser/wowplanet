<?php

declare(strict_types=1);

use App\Application\Import\ImportHistory;
use App\Application\Import\ImportRun;
use App\Application\Import\ImportStage;
use App\Application\Import\RowTally;
use App\Models\ImportHistoryEntry;
use App\Models\ImportHistoryStep;
use App\Models\WowMount;

function importHistory(): ImportHistory
{
    return resolve(ImportHistory::class);
}

/**
 * @param  list<ImportStage>  $stages
 */
function historyRun(array $stages = [ImportStage::Mounts, ImportStage::Pets], string $jobId = 'job-1'): ImportRun
{
    return ImportRun::start($jobId, $stages, now()->getTimestamp());
}

test('an import that starts is recorded as running, with who launched it and how', function (): void {
    $importRun = historyRun();
    $importRun = $importRun->withStep($importRun->step(ImportStage::Pets)->skipped());

    importHistory()->open($importRun, force: true, trigger: '12345');

    expect(ImportHistoryEntry::query()->sole())->toMatchArray([
        'job_id' => 'job-1',
        'trigger' => '12345',
        'mode' => 'forced',
        'status' => 'running',
        'finished_at' => null,
    ])
        ->and(ImportHistoryStep::query()->orderBy('id')->pluck('status', 'stage')->all())
        ->toBe(['mounts' => 'pending', 'pets' => 'skipped']);
});

test('an import that ends keeps its report, stage by stage', function (): void {
    WowMount::factory()->count(3)->create();
    $importRun = historyRun([ImportStage::Mounts]);
    importHistory()->open($importRun, force: false, trigger: 'console');

    $finished = $importRun
        ->withStep($importRun->step(ImportStage::Mounts)->started()->finished(new RowTally(2, 1, 0), 5, 1_200))
        ->withBudgetUsed(42);

    importHistory()->close($finished, now()->getTimestamp());

    expect(ImportHistoryEntry::query()->sole())->toMatchArray(['mode' => 'incremental', 'status' => 'completed', 'budget_used' => 42])
        ->and(ImportHistoryEntry::query()->sole()->finished_at)->not->toBeNull()
        ->and(ImportHistoryStep::query()->sole())->toMatchArray([
            'status' => 'completed',
            'created' => 2,
            'updated' => 1,
            'deleted' => 0,
            'api_calls' => 5,
            'duration_ms' => 1_200,
            'rows_after' => 3,
        ]);
});

test('a cancelled import is recorded as cancelled, never as complete', function (): void {
    $importRun = historyRun();
    importHistory()->open($importRun, force: false, trigger: 'console');

    importHistory()->close($importRun->cancelled(now()->getTimestamp()), now()->getTimestamp());

    expect(ImportHistoryEntry::query()->sole()->status)->toBe('cancelled');
});

test('a failed stage is kept with its reason', function (): void {
    $importRun = historyRun([ImportStage::Mounts]);
    importHistory()->open($importRun, force: false, trigger: 'console');

    importHistory()->close($importRun->withStep($importRun->step(ImportStage::Mounts)->failed('index unavailable', 300)), now()->getTimestamp());

    expect(ImportHistoryEntry::query()->sole()->status)->toBe('failed')
        ->and(ImportHistoryStep::query()->sole())->toMatchArray(['status' => 'failed', 'error' => 'index unavailable']);
});

test('the reference socle is closed without a volume, its tables being none of the stage', function (): void {
    $importRun = historyRun([ImportStage::Reference]);
    importHistory()->open($importRun, force: false, trigger: 'console');

    importHistory()->close($importRun, now()->getTimestamp());

    expect(ImportHistoryStep::query()->sole()->rows_after)->toBeNull();
});

test('opening an import purges the reports older than the retention', function (): void {
    ImportHistoryEntry::factory()->create(['job_id' => 'ancient', 'started_at' => now()->subMonths(ImportHistory::RETENTION_MONTHS)->subDay()]);
    ImportHistoryEntry::factory()->create(['job_id' => 'recent', 'started_at' => now()->subMonths(ImportHistory::RETENTION_MONTHS)->addDay()]);
    ImportHistoryStep::query()->create(['job_id' => 'ancient', 'stage' => 'mounts', 'status' => 'completed']);

    importHistory()->open(historyRun(), force: false, trigger: 'console');

    expect(ImportHistoryEntry::query()->orderBy('job_id')->pluck('job_id')->all())->toBe(['job-1', 'recent'])
        ->and(ImportHistoryStep::query()->where('job_id', 'ancient')->count())->toBe(0);
});

test('closing an import that was never opened records nothing', function (): void {
    importHistory()->close(historyRun(), now()->getTimestamp());

    expect(ImportHistoryEntry::query()->count())->toBe(0);
});

test('an archived step knows the import it belongs to', function (): void {
    importHistory()->open(historyRun([ImportStage::Mounts]), force: false, trigger: 'console');

    expect(ImportHistoryStep::query()->sole()->entry->job_id)->toBe('job-1');
});
