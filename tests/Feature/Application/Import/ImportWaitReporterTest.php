<?php

declare(strict_types=1);

use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportRun;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportWait;
use App\Application\Import\ImportWaitReason;
use App\Application\Import\ImportWaitReporter;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use Illuminate\Support\Facades\Cache;

function startedRun(ImportProgressStore $importProgressStore): void
{
    $importProgressStore->save(ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp()));
}

test('a reporter that follows nothing publishes nothing', function (): void {
    $store = new ImportProgressStore;
    startedRun($store);

    resolve(ImportWaitReporter::class)->waiting(ImportWait::batch(286));

    expect($store->find('job-1')?->wait)->toBeNull();
});

test('a followed import publishes what it waits on', function (): void {
    $store = new ImportProgressStore;
    startedRun($store);

    $importWaitReporter = resolve(ImportWaitReporter::class);
    $importWaitReporter->follow('job-1');
    $importWaitReporter->waiting(ImportWait::rateLimitBackoff(20));

    expect($store->find('job-1')?->wait?->reason)->toBe(ImportWaitReason::RateLimitBackoff);
});

test('an import back at work stops saying it waits', function (): void {
    $store = new ImportProgressStore;
    startedRun($store);

    $importWaitReporter = resolve(ImportWaitReporter::class);
    $importWaitReporter->follow('job-1');
    $importWaitReporter->waiting(ImportWait::batch(286));
    $importWaitReporter->working();

    expect($store->find('job-1')?->wait)->toBeNull();
});

test('following a job whose tracking has been flushed writes nothing', function (): void {
    $store = new ImportProgressStore;

    $importWaitReporter = resolve(ImportWaitReporter::class);
    $importWaitReporter->follow('job-1');
    $importWaitReporter->waiting(ImportWait::batch(286));

    expect(Cache::has('admin_import:job-1'))->toBeFalse();
});

test('a reporter released goes back to publishing nothing', function (): void {
    $store = new ImportProgressStore;
    startedRun($store);

    $importWaitReporter = resolve(ImportWaitReporter::class);
    $importWaitReporter->follow('job-1');
    $importWaitReporter->release();
    $importWaitReporter->waiting(ImportWait::batch(286));

    expect($store->find('job-1')?->wait)->toBeNull();
});

test('a followed import publishes the budget it has consumed as it goes', function (): void {
    $store = new ImportProgressStore;
    startedRun($store);

    resolve(HourlyBudgetGuard::class)->consume(1_200);

    $importWaitReporter = resolve(ImportWaitReporter::class);
    $importWaitReporter->follow('job-1');
    $importWaitReporter->waiting(ImportWait::batch(286));

    expect($store->find('job-1')?->budgetUsed)->toBe(1_200);
});
