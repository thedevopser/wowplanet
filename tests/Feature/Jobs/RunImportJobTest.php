<?php

declare(strict_types=1);

use App\Application\Import\CurrentImport;
use App\Application\Import\ImportControl;
use App\Application\Import\ImportLog;
use App\Application\Import\ImportPipeline;
use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportRequest;
use App\Application\Import\ImportSignal;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStepStatus;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Jobs\RunImportJob;
use App\Models\ImportHistoryEntry;
use App\Models\WowImportState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    seedCollectionTaxonomy();
    Sleep::fake();
    Cache::flush();
    Bus::fake();

    $this->apiClientMock = $this->mock(BlizzardApiClient::class);
    $this->apiClientMock->shouldReceive('currentBuild')->andReturn('12.1.0_68914')->byDefault();
});

/**
 * Mocke les 18 index de slots ; ceux listés dans $slots reçoivent leurs apparences.
 *
 * @param  array<string, list<int>>  $slots
 */
function mockJobSlotIndexes(\Mockery\MockInterface $mock, array $slots): void
{
    $allSlots = [
        'HEAD', 'SHOULDER', 'BODY', 'CHEST', 'WAIST', 'LEGS', 'FEET', 'WRIST', 'HAND',
        'CLOAK', 'TABARD', 'WEAPON', 'SHIELD', 'RANGED', 'TWOHWEAPON', 'WEAPONMAINHAND',
        'WEAPONOFFHAND', 'HOLDABLE',
    ];

    foreach ($allSlots as $allSlot) {
        $ids = $slots[$allSlot] ?? [];
        $mock->shouldReceive('get')
            ->with('data/wow/item-appearance/slot/'.$allSlot, \Mockery::any())
            ->andReturn(['appearances' => array_map(fn (int $id): array => ['id' => $id], $ids)]);
    }

    $mock->shouldReceive('get')
        ->withArgs(fn (string $endpoint): bool => str_contains($endpoint, 'orderby=id:desc'))
        ->andReturn(['results' => [['data' => ['id' => 2500]]]]);
}

/**
 * @param  array<string, mixed>  $parameters
 */
function handleImportJob(string $jobId, string $command = 'app:wow-data-import', array $parameters = [], string $trigger = 'administrateur'): void
{
    (new RunImportJob($jobId, $command, $parameters, $trigger))->handle(
        resolve(ImportPipeline::class),
        resolve(ImportProgressStore::class),
        resolve(ImportControl::class),
        resolve(ImportLog::class),
        resolve(CurrentImport::class),
    );
}

test('the job runs the stage it was asked for and completes the import', function (): void {
    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldReceive('importPets')->once();

    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
    Bus::assertNotDispatched(RunImportJob::class);
});

test('an unfinished import re-dispatches itself to carry on', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    mockJobSlotIndexes($this->apiClientMock, ['HEAD' => [321]]);
    $this->apiClientMock->shouldNotReceive('getAsync');

    handleImportJob('job-1', parameters: ['--type' => 'appearances']);

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->jobId === 'job-1');
    expect(Cache::get('admin_import:job-1')['status'])->toBe('running');
});

test('a wardrobe pass stopped by the hourly ceiling says so in the tracking', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(HourlyBudgetGuard::HOURLY_LIMIT);

    mockJobSlotIndexes($this->apiClientMock, ['HEAD' => [321]]);

    handleImportJob('job-1', parameters: ['--type' => 'appearances']);

    expect((new ImportProgressStore)->payload('job-1')['waiting']['reason'])->toBe('hourly_budget');
});

test('an import with nothing left to sweep is marked complete', function (): void {
    mockJobSlotIndexes($this->apiClientMock, []);

    handleImportJob('job-2', parameters: ['--type' => 'appearances']);

    Bus::assertNotDispatched(RunImportJob::class);
    expect(Cache::get('admin_import:job-2')['status'])->toBe('completed');
});

test('a job picked up again resumes the run instead of starting it over', function (): void {
    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldReceive('importPets')->once();

    handleImportJob('job-1', parameters: ['--type' => 'pets']);
    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('a worker restarted with the tracking lost skips what the build gate already holds', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldNotReceive('importPets');

    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Skipped);
});

test('forcing the import redoes a stage the build gate holds', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldReceive('importPets')->once();

    handleImportJob('job-1', parameters: ['--type' => 'pets', '--force' => true]);

    expect((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('a plain command still runs through Artisan and publishes its output', function (): void {
    handleImportJob('job-3', 'app:collection-taxonomy-report');

    expect(Cache::get('admin_import:job-3')['status'])->toBe('completed');
});

test('a plain command feeds the journal line by line, not one block at the end', function (): void {
    Artisan::command('test:noisy', function (): void {
        $this->line('Socle de référence — build 12.1.0.69875');
        $this->newLine();
        $this->line('  Faction   868 lignes');
    });

    handleImportJob('job-5', 'test:noisy');

    $lines = resolve(ImportLog::class)->since('job-5', 0)->lines;

    // Les deux lignes de la commande, la ligne vide écartée, et la clôture : sans elle,
    // le panneau afficherait la dernière ligne sans que rien ne dise que c'est fini.
    expect($lines)->toHaveCount(3)
        ->and(implode(PHP_EOL, $lines))->toContain('Faction   868 lignes')
        ->and(end($lines))->toContain('Terminé.');
});

test('a plain command hands the lock back once it is over', function (): void {
    (new CurrentImport)->mark('job-5', now()->getTimestamp());

    handleImportJob('job-5', 'app:collection-taxonomy-report');

    expect(resolve(CurrentImport::class)->jobId())->toBeNull();
});

test('a plain command that throws hands the lock back and says why', function (): void {
    (new CurrentImport)->mark('job-5', now()->getTimestamp());

    handleImportJob('job-5', 'app:does-not-exist');

    expect(Cache::get('admin_import:job-5')['status'])->toBe('failed')
        ->and(resolve(CurrentImport::class)->jobId())->toBeNull()
        ->and(implode(PHP_EOL, resolve(ImportLog::class)->since('job-5', 0)->lines))->toContain('échec');
});

test('a plain command never releases a lock held by another import', function (): void {
    (new CurrentImport)->mark('job-ailleurs', now()->getTimestamp());

    handleImportJob('job-5', 'app:collection-taxonomy-report');

    expect(resolve(CurrentImport::class)->jobId())->toBe('job-ailleurs');
});

test('a plain command that throws is published as a failure', function (): void {
    handleImportJob('job-4', 'app:does-not-exist');

    expect(Cache::get('admin_import:job-4')['status'])->toBe('failed');
});

test('the import job runs on the imports queue', function (): void {
    expect((new RunImportJob('job-1', 'app:wow-data-import'))->queue)->toBe('imports');
});

test('a paused import runs no pass and beats until someone comes back', function (): void {
    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldNotReceive('importPets');

    resolve(ImportControl::class)->request('job-1', ImportSignal::Pause, '12345', now()->getTimestamp());

    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect(Cache::get('admin_import:job-1')['status'])->toBe('paused');
    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->jobId === 'job-1');
});

test('a pause names who asked for it, once and not at every beat', function (): void {
    $this->mock(BlizzardBatchImporter::class)->shouldNotReceive('importPets');

    resolve(ImportControl::class)->request('job-1', ImportSignal::Pause, '12345', now()->getTimestamp());

    handleImportJob('job-1', parameters: ['--type' => 'pets']);
    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    $lines = resolve(ImportLog::class)->since('job-1', 0)->lines;
    $paused = array_filter($lines, static fn (string $line): bool => str_contains($line, 'en pause'));

    expect($paused)->toHaveCount(1)
        ->and(implode(PHP_EOL, $paused))->toContain('12345');
});

test('a resumed import carries on with the stage it had not done', function (): void {
    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldReceive('importPets')->once();

    $importControl = resolve(ImportControl::class);
    $importControl->request('job-1', ImportSignal::Pause, '12345', now()->getTimestamp());
    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    $importControl->clear('job-1');
    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed)
        ->and(Cache::get('admin_import:job-1')['status'])->toBe('completed');
});

test('a cancelled import stops there, releases the lock and does not come back', function (): void {
    $importer = $this->mock(BlizzardBatchImporter::class);
    $importer->shouldNotReceive('importPets');

    resolve(ImportControl::class)->request('job-1', ImportSignal::Cancel, '12345', now()->getTimestamp());

    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect(Cache::get('admin_import:job-1')['status'])->toBe('cancelled')
        ->and(resolve(CurrentImport::class)->jobId())->toBeNull()
        ->and(resolve(ImportControl::class)->pending('job-1'))->toBeNull();
    Bus::assertNotDispatched(RunImportJob::class);
});

test('a pause nobody came back to is abandoned, and frees the panel', function (): void {
    $this->mock(BlizzardBatchImporter::class)->shouldNotReceive('importPets');

    resolve(ImportControl::class)->request(
        'job-1',
        ImportSignal::Pause,
        '12345',
        now()->getTimestamp() - ImportRequest::ABANDON_AFTER_S - 1,
    );

    handleImportJob('job-1', parameters: ['--type' => 'pets']);

    expect(Cache::get('admin_import:job-1')['status'])->toBe('cancelled')
        ->and(resolve(CurrentImport::class)->jobId())->toBeNull()
        ->and(implode(PHP_EOL, resolve(ImportLog::class)->since('job-1', 0)->lines))->toContain('abandonné');
    Bus::assertNotDispatched(RunImportJob::class);
});

test('the administrator who launched the import is recorded in the history', function (): void {
    $this->mock(BlizzardBatchImporter::class)->shouldReceive('importPets')->once();

    handleImportJob('job-1', parameters: ['--type' => 'pets'], trigger: '12345');

    expect(ImportHistoryEntry::query()->sole()->trigger)->toBe('12345');
});

test('a job that hands over to its next pass keeps who launched it', function (): void {
    (new CurrentImport)->mark('job-1', now()->getTimestamp());
    resolve(HourlyBudgetGuard::class)->consume(40_000);

    handleImportJob('job-1', parameters: ['--type' => 'pets'], trigger: '12345');

    Bus::assertDispatched(fn (\App\Jobs\RunImportJob $runImportJob): bool => $runImportJob->trigger === '12345');
});

test('the catalogue import is shown under a readable label and belongs to no account', function (): void {
    expect(new RunImportJob('job-1', 'app:wow-data-import'))
        ->label()->toBe('Import du catalogue')
        ->account()->toBeNull();
});
