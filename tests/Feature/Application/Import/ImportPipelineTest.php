<?php

declare(strict_types=1);

use App\Application\Import\CurrentImport;
use App\Application\Import\ImportControl;
use App\Application\Import\ImportLog;
use App\Application\Import\ImportPipeline;
use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportRequest;
use App\Application\Import\ImportRunState;
use App\Application\Import\ImportSignal;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStepStatus;
use App\Application\Import\ImportWaitReason;
use App\Application\Services\DatabaseQueryService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Models\ImportHistoryEntry;
use App\Models\ImportHistoryStep;
use App\Models\WowImportState;
use App\Models\WowPet;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    seedCollectionTaxonomy();
    $this->importerMock = $this->mock(BlizzardBatchImporter::class);
    $this->apiClientMock = $this->mock(BlizzardApiClient::class);
    $this->apiClientMock->shouldReceive('currentBuild')->andReturn('12.1.0_68914')->byDefault();
});

function pipeline(): ImportPipeline
{
    return resolve(ImportPipeline::class);
}

function journal(string $jobId): string
{
    return implode(PHP_EOL, resolve(ImportLog::class)->since($jobId, 0)->lines);
}

test('beginning an import publishes a pending step per stage', function (): void {
    $importRun = pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console');

    expect($importRun->steps)->toHaveCount(2)
        ->and((new ImportProgressStore)->find('job-1'))->toEqual($importRun);
});

test('a stage already imported for this build is skipped rather than redone', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    $importRun = pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console');

    expect($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Skipped)
        ->and($importRun->step(ImportStage::Decor)->status)->toBe(ImportStepStatus::Pending);
});

test('forcing an import redoes the stages the build gate would have skipped', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    $importRun = pipeline()->begin('job-1', [ImportStage::Pets], force: true, trigger: 'console');

    expect($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Pending);
});

test('an import with nothing left to do is complete as soon as it begins', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    expect(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console')->status())
        ->toBe(ImportRunState::Completed);
});

test('advancing runs the first stage left and publishes what it did', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();
    $this->importerMock->shouldNotReceive('importDecor');

    $importRun = pipeline()->advance(
        pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console'),
        full: false,
        limit: null,
    );

    expect($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed)
        ->and((new ImportProgressStore)->find('job-1')?->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('a completed stage is remembered for this build, so a restart does not redo it', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(WowImportState::query()->where('entity', 'pets')->where('build', '12.1.0_68914')->exists())->toBeTrue();
});

test('a failed stage is not remembered, so the next run tries it again', function (): void {
    $this->importerMock->shouldReceive('importPets')->once()->andThrow(new RuntimeException('pet index unavailable'));

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(WowImportState::query()->where('entity', 'pets')->exists())->toBeFalse();
});

test('a failed stage does not stop the ones after it', function (): void {
    $this->importerMock->shouldReceive('importPets')->once()->andThrow(new RuntimeException('pet index unavailable'));
    $this->importerMock->shouldReceive('importDecor')->once();

    $run = pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console');
    $run = pipeline()->advance($run, false, null);
    $run = pipeline()->advance($run, false, null);

    expect($run->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Failed)
        ->and($run->step(ImportStage::Decor)->status)->toBe(ImportStepStatus::Completed)
        ->and($run->status())->toBe(ImportRunState::Failed);
});

test('advancing a finished import runs nothing more', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);
    $again = pipeline()->advance($importRun, false, null);

    expect($again)->toEqual($importRun);
});

test('each pass publishes the hourly budget consumed', function (): void {
    $this->importerMock->shouldReceive('importPets')->once()->andReturnUsing(function (): void {
        resolve(HourlyBudgetGuard::class)->consume(1_200);
    });

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect($importRun->budgetUsed)->toBe(1_200);
});

test('an import running under an unknown build is not skipped by the gate', function (): void {
    $this->apiClientMock->shouldReceive('currentBuild')->andReturn(null);
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);
    $this->importerMock->shouldReceive('importPets')->once();

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('the stage being run is named in the tracking while it runs', function (): void {
    $seen = null;
    $this->importerMock->shouldReceive('importPets')->once()->andReturnUsing(function () use (&$seen): void {
        $seen = (new ImportProgressStore)->find('job-1')?->currentStage();
    });

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect($seen)->toBe(ImportStage::Pets);
});

test('a stage does not start while the reserved hourly ceiling is spent', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(30_000);

    $this->importerMock->shouldNotReceive('importPets');

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect($importRun->wait?->reason)->toBe(ImportWaitReason::HourlyBudget)
        ->and($importRun->wait->seconds)->toBeGreaterThan(0)
        ->and($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Pending);
});

test('the reference socle runs even with the Blizzard quota spent, spending none of it', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(30_000);

    Artisan::shouldReceive('call')->once()->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Reference], force: false, trigger: 'console'), false, null);

    expect($importRun->step(ImportStage::Reference)->status)->toBe(ImportStepStatus::Completed);
});

// ─── journal et import courant ──────────────────────────────

test('beginning an import opens its journal and points the panel at it', function (): void {
    pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console');

    expect(resolve(CurrentImport::class)->jobId())->toBe('job-1')
        ->and(journal('job-1'))->toContain('Import démarré — 1 étape : Mascottes.');
});

test('the journal says which stages the build gate spared', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console');

    expect(journal('job-1'))->toContain('Mascottes — déjà à jour pour le build 12.1.0_68914, étape sautée.');
});

test('the journal records a stage from its start to its report', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(journal('job-1'))->toContain('Mascottes — démarrage.')
        ->toContain('Mascottes — terminée');
});

test('the journal says why an import waits instead of going silent', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(30_000);

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(journal('job-1'))->toContain('En attente : plafond horaire atteint, reprise dans');
});

test('the journal carries the reason a stage failed', function (): void {
    $this->importerMock->shouldReceive('importPets')->once()->andThrow(new RuntimeException('API injoignable'));

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(journal('job-1'))->toContain('Mascottes — échec : API injoignable');
});

test('an import that reaches its end stops being the current one', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(resolve(CurrentImport::class)->jobId())->toBeNull();
});

test('an import with stages left keeps being the current one', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console'), false, null);

    expect(resolve(CurrentImport::class)->jobId())->toBe('job-1');
});

test('an import that has nothing left to do at its start stops being the current one', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);

    pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console');

    expect(resolve(CurrentImport::class)->jobId())->toBeNull();
});

test('the journal closes on the final report of the import', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(journal('job-1'))->toContain('Import terminé');
});

test('the final report enters the journal line by line, not as one block', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(resolve(ImportLog::class)->since('job-1', 0)->lines)
        ->each(fn ($line) => $line->not->toContain(PHP_EOL));
});

test('the final report brings no blank line into the journal', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    $blank = array_filter(
        resolve(ImportLog::class)->since('job-1', 0)->lines,
        static fn (string $line): bool => preg_match('/^\[\d{2}:\d{2}:\d{2}\]\s*$/', $line) === 1,
    );

    expect($blank)->toBeEmpty();
});

test('pausing publishes the run as paused and says who asked', function (): void {
    $importRun = pipeline()->interrupt(
        pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'),
        new ImportRequest(ImportSignal::Pause, '12345', now()->getTimestamp()),
    );

    expect($importRun->status())->toBe(ImportRunState::Paused)
        ->and((new ImportProgressStore)->find('job-1')?->status())->toBe(ImportRunState::Paused)
        ->and(journal('job-1'))->toContain('12345');
});

test('a paused run keeps its pointer, the import still being the one the panel follows', function (): void {
    pipeline()->interrupt(
        pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'),
        new ImportRequest(ImportSignal::Pause, '12345', now()->getTimestamp()),
    );

    expect(resolve(CurrentImport::class)->jobId())->toBe('job-1');
});

test('a paused run says what it waits for, so the panel does not read it as stalled', function (): void {
    pipeline()->interrupt(
        pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'),
        new ImportRequest(ImportSignal::Pause, '12345', now()->getTimestamp()),
    );

    expect((new ImportProgressStore)->payload('job-1')['waiting']['reason'])->toBe(ImportWaitReason::Paused->value);
});

test('resuming clears the pause and lets the stages drive again', function (): void {
    $paused = pipeline()->interrupt(
        pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'),
        new ImportRequest(ImportSignal::Pause, '12345', now()->getTimestamp()),
    );

    $importRun = pipeline()->resume($paused);

    expect($importRun->status())->toBe(ImportRunState::Pending)
        ->and($importRun->wait)->toBeNull()
        ->and(journal('job-1'))->toContain('reprise');
});

test('cancelling closes the import, releases the lock and clears the order', function (): void {
    resolve(ImportControl::class)->request('job-1', ImportSignal::Cancel, '12345', now()->getTimestamp());

    $importRun = pipeline()->interrupt(
        pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console'),
        new ImportRequest(ImportSignal::Cancel, '12345', now()->getTimestamp()),
    );

    expect($importRun->status())->toBe(ImportRunState::Cancelled)
        ->and(resolve(CurrentImport::class)->jobId())->toBeNull()
        ->and(resolve(ImportControl::class)->pending('job-1'))->toBeNull();
});

test('the report of a cancelled import says interrupted and names who cancelled it', function (): void {
    pipeline()->interrupt(
        pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console'),
        new ImportRequest(ImportSignal::Cancel, '12345', now()->getTimestamp()),
    );

    expect(journal('job-1'))->toContain('Import interrompu')
        ->and(journal('job-1'))->toContain('12345')
        ->and(journal('job-1'))->not->toContain('Import terminé');
});

test('a pause left behind too long is closed as abandoned, and says so', function (): void {
    $importRequest = new ImportRequest(
        ImportSignal::Pause,
        '12345',
        now()->getTimestamp() - ImportRequest::ABANDON_AFTER_S - 1,
    );

    $importRun = pipeline()->interrupt(
        pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'),
        $importRequest,
    );

    expect($importRun->status())->toBe(ImportRunState::Cancelled)
        ->and(journal('job-1'))->toContain('abandonné')
        ->and(resolve(CurrentImport::class)->jobId())->toBeNull();
});

test('an import cancelled mid-stage keeps the stages already done in its report', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    $importRun = pipeline()->advance(
        pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console'),
        false,
        null,
    );

    pipeline()->interrupt($importRun, new ImportRequest(ImportSignal::Cancel, '12345', now()->getTimestamp()));

    expect(journal('job-1'))->toContain('1 étape sur 2');
});

test('an incremental import re-syncs a socle that wago has moved past', function (): void {
    // Le pipeline inscrit pour le socle le build Blizzard du moment, qui ne bouge pas au
    // même rythme que celui de wago. Sans arbitrage par famille, l'étape serait sautée
    // alors que les tables DB2 ont changé.
    WowImportState::query()->create(['entity' => 'reference', 'build' => '12.1.0_68914', 'imported_at' => now()]);
    referenceLoadedOn('12.1.0.69587');
    stubWagoBuild('12.1.0.69875');

    $importRun = pipeline()->begin('job-1', [ImportStage::Reference], force: false, trigger: 'console');

    expect($importRun->step(ImportStage::Reference)->status)->toBe(ImportStepStatus::Pending);
});

test('an incremental import still skips a socle already loaded on the build wago serves', function (): void {
    referenceLoadedOn('12.1.0.69875');
    stubWagoBuild('12.1.0.69875');

    $importRun = pipeline()->begin('job-1', [ImportStage::Reference], force: false, trigger: 'console');

    expect($importRun->step(ImportStage::Reference)->status)->toBe(ImportStepStatus::Skipped);
});

test('a catalogue import does not spend a call asking wago what it serves', function (): void {
    Http::fake();

    pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console');

    Http::assertNothingSent();
});

test('an import that begins opens its history entry with who launched it', function (): void {
    pipeline()->begin('job-1', [ImportStage::Pets], force: true, trigger: '12345');

    expect(ImportHistoryEntry::query()->sole())->toMatchArray(['job_id' => 'job-1', 'trigger' => '12345', 'mode' => 'forced', 'status' => 'running']);
});

test('an import that runs to its end closes its history entry with the report', function (): void {
    $this->importerMock->shouldReceive('importPets')->once();

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(ImportHistoryEntry::query()->sole()->status)->toBe('completed')
        ->and(ImportHistoryEntry::query()->sole()->finished_at)->not->toBeNull()
        ->and(ImportHistoryStep::query()->sole()->status)->toBe('completed');
});

test('a cancelled import closes its history entry as cancelled', function (): void {
    pipeline()->interrupt(
        pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console'),
        new ImportRequest(ImportSignal::Cancel, '12345', now()->getTimestamp()),
    );

    expect(ImportHistoryEntry::query()->sole()->status)->toBe('cancelled');
});

test('an import with nothing left to do at its start is still closed in the history, with its volumes', function (): void {
    WowImportState::query()->create(['entity' => 'pets', 'build' => '12.1.0_68914', 'imported_at' => now()]);
    App\Models\WowPet::factory()->count(2)->create();

    pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console');

    expect(ImportHistoryEntry::query()->sole()->finished_at)->not->toBeNull()
        ->and(ImportHistoryStep::query()->sole())->toMatchArray(['status' => 'skipped', 'rows_after' => 2]);
});

// ─── détail du journal et des garde-fous ────────────────────

test('the journal announces several stages in the plural, in the order requested', function (): void {
    pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: true, trigger: 'console');

    expect(journal('job-1'))->toContain('Import démarré — 2 étapes : Mascottes, Décorations.');
});

test('the journal line of a wait on the ceiling is a full sentence', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(30_000);

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(journal('job-1'))->toMatch('/\] En attente : plafond horaire atteint, reprise dans \d+ s\.$/m');
});

test('a stage still starts with room for one more call under the ceiling', function (): void {
    resolve(HourlyBudgetGuard::class)->consume(29_999);
    $this->importerMock->shouldReceive('importPets')->once();

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect($importRun->step(ImportStage::Pets)->status)->toBe(ImportStepStatus::Completed);
});

test('an abandoned pause says after how long the lock was released', function (): void {
    $importRequest = new ImportRequest(ImportSignal::Pause, '12345', now()->getTimestamp() - ImportRequest::ABANDON_AFTER_S - 1);

    pipeline()->interrupt(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), $importRequest);

    expect(journal('job-1'))->toContain('Import abandonné : en pause depuis plus de 60 min sans reprise, le verrou est relâché.');
});

test('a wardrobe pass that hands back before its end says how far it got and what it waits for', function (int $offset, string $percent): void {
    $this->importerMock->shouldReceive('importAppearanceChunk')->once()
        ->andReturn(new App\Application\DTOs\AppearanceImportProgress(done: false, offset: $offset, total: 3, secondsUntilBudget: 90));

    $importRun = pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Appearances], force: true, trigger: 'console'), false, null);

    expect(journal('job-1'))->toContain('Garde-robe — '.$percent.' % · reprise à la passe suivante.')
        ->toContain('En attente : plafond horaire atteint, reprise dans 90 s.')
        ->and($importRun->wait?->seconds)->toBe(90);
})->with([
    'a third' => [1, '33'],
    'two thirds' => [2, '67'],
]);

test('the waits the API client reports reach the tracking during a pass, and only during it', function (): void {
    $seenDuringPass = null;
    $this->importerMock->shouldReceive('importPets')->once()->andReturnUsing(function () use (&$seenDuringPass): void {
        resolve(App\Application\Import\ImportWaitReporter::class)->waiting(App\Application\Import\ImportWait::batch(5));
        $seenDuringPass = (new ImportProgressStore)->find('job-1')?->wait;
    });

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: true, trigger: 'console'), false, null);
    resolve(App\Application\Import\ImportWaitReporter::class)->waiting(App\Application\Import\ImportWait::batch(7));

    expect($seenDuringPass?->count)->toBe(5)
        ->and((new ImportProgressStore)->find('job-1')?->wait)->toBeNull();
});

test('a cancelled import is done even with stages left', function (): void {
    $importRun = App\Application\Import\ImportRun::start('job-1', [ImportStage::Pets], 1_000)->cancelled(at: 1_100);

    expect(pipeline()->isDone($importRun))->toBeTrue();
});

/**
 * La barre latérale de la base garde ses compteurs une heure : l'import qui vient de changer
 * le catalogue doit la rafraîchir en se refermant, pas la laisser mentir jusque-là.
 */
function sidebarPets(): int
{
    return resolve(DatabaseQueryService::class)->cachedCounts()['pets'];
}

function importPetsWritesOne(\Mockery\MockInterface $mock): void
{
    $mock->shouldReceive('importPets')->once()->andReturnUsing(function (): void {
        WowPet::factory()->create(['is_active' => true]);
    });
}

test('an import that reaches its end refreshes the database sidebar', function (): void {
    expect(sidebarPets())->toBe(0);
    importPetsWritesOne($this->importerMock);

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console'), false, null);

    expect(sidebarPets())->toBe(1);
});

test('an import with stages left keeps the database sidebar until it ends', function (): void {
    expect(sidebarPets())->toBe(0);
    importPetsWritesOne($this->importerMock);

    pipeline()->advance(pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console'), false, null);

    expect(sidebarPets())->toBe(0);
});

test('a cancelled import refreshes the database sidebar, since the stages already done did write', function (): void {
    $importRun = pipeline()->begin('job-1', [ImportStage::Pets, ImportStage::Decor], force: false, trigger: 'console');
    expect(sidebarPets())->toBe(0);
    WowPet::factory()->create(['is_active' => true]);

    pipeline()->interrupt($importRun, new ImportRequest(ImportSignal::Cancel, '12345', now()->getTimestamp()));

    expect(sidebarPets())->toBe(1);
});

test('a paused import keeps the database sidebar, since it has not ended', function (): void {
    $importRun = pipeline()->begin('job-1', [ImportStage::Pets], force: false, trigger: 'console');
    expect(sidebarPets())->toBe(0);
    WowPet::factory()->create(['is_active' => true]);

    pipeline()->interrupt($importRun, new ImportRequest(ImportSignal::Pause, '12345', now()->getTimestamp()));

    expect(sidebarPets())->toBe(0);
});
