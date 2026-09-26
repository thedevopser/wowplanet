<?php

declare(strict_types=1);

use App\Application\Import\CurrentImport;
use App\Application\Import\ImportControl;
use App\Application\Import\ImportLog;
use App\Application\Import\ImportProgressStore;
use App\Application\Import\ImportRun;
use App\Application\Import\ImportSignal;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStep;
use App\Application\Import\ImportWait;
use App\Application\Import\RowTally;
use App\Infrastructure\Reference\ReferenceStore;
use App\Jobs\RunImportJob;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowMount;
use App\Models\WowReferenceDownload;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('the progress endpoint answers an unknown job as not found', function (): void {
    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/nobody')
        ->assertOk()
        ->assertJson(['status' => 'not_found']);
});

test('the progress endpoint details the stage, the budget and the timings', function (): void {
    (new ImportProgressStore)->save(
        ImportRun::start('job-1', [ImportStage::Quests, ImportStage::Mounts], now()->getTimestamp())
            ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 12, 900, offset: 1, total: 4))
            ->withBudgetUsed(12_340)
    );

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/job-1')
        ->assertOk()
        ->assertJsonPath('status', 'running')
        ->assertJsonPath('stage', 'quests')
        ->assertJsonPath('stage_label', 'Quêtes')
        ->assertJsonPath('budget.used', 12_340)
        ->assertJsonPath('steps.0.api_calls', 12);
});

test('the progress endpoint says why an import waits', function (): void {
    (new ImportProgressStore)->save(
        ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp())
            ->waitingOn(ImportWait::hourlyBudget(240))
    );

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/job-1')
        ->assertOk()
        ->assertJsonPath('waiting.reason', 'hourly_budget')
        ->assertJsonPath('waiting.seconds', 240);
});

test('the progress endpoint is closed to anyone but an administrator', function (): void {
    $this->getJson('/api/admin/import/job-1')->assertForbidden();
});

test('starting an import on everything queues the whole chain', function (): void {
    Bus::fake();

    $response = $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'all', 'mode' => 'incremental']);

    $response->assertOk()->assertJsonStructure(['jobId']);

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->command === 'app:wow-data-import'
        && $runImportJob->parameters['--type'] === 'all'
        && ! isset($runImportJob->parameters['--force']));
});

test('an import on everything is accepted with the empty selection the panel sends along', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'all', 'stages' => [], 'mode' => 'forced'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->parameters['--type'] === 'all'
        && $runImportJob->parameters['--force'] === true);
});

test('a forced import of a single entity queues that entity alone', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'selection', 'stages' => ['mounts'], 'mode' => 'forced'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->parameters['--type'] === 'mounts'
        && $runImportJob->parameters['--force'] === true);
});

test('the administrator who starts an import is handed to the job as its trigger', function (): void {
    Bus::fake();

    $this->withSession(adminSession())
        ->postJson('/api/admin/import', ['scope' => 'all', 'mode' => 'incremental'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->trigger === '12345');
});

test('starting an import on a selection queues those entities and no others', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'selection', 'stages' => ['mounts', 'quests'], 'mode' => 'incremental'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->parameters['--type'] === 'quests,mounts');
});

test('a forced import carries the flag that makes the build gate step aside', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'all', 'mode' => 'forced'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->parameters['--force'] === true);
});

test('the panel never hands over a command name, whatever it sends', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'all', 'mode' => 'incremental', 'command' => 'app:wow-data-refresh'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->command === 'app:wow-data-import');
});

test('a selection without entities is refused', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'selection', 'stages' => [], 'mode' => 'incremental'])
        ->assertStatus(422);

    Bus::assertNothingDispatched();
});

test('an entity that does not exist is refused', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'selection', 'stages' => ['dragons'], 'mode' => 'incremental'])
        ->assertStatus(422);

    Bus::assertNothingDispatched();
});

test('the reference socle is a stage the patch banner may select, having its own upstream', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'selection', 'stages' => ['reference'], 'mode' => 'incremental'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->parameters['--type'] === 'reference');
});

test('a patch update queues the socle ahead of the entities that depend on it', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'selection', 'stages' => ['mounts', 'reference'], 'mode' => 'incremental'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->parameters['--type'] === 'reference,mounts');
});

test('a forced check reopens the upstream without waiting for the cache to expire', function (): void {
    config(['services.blizzard.client_id' => 'id', 'services.blizzard.client_secret' => 'secret', 'services.blizzard.region' => 'eu']);
    Cache::put('upstream_build:wago', '12.1.0.69587', 3600);
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/build-check')
        ->assertOk()
        ->assertJsonPath('checked', true);

    expect(Cache::get('upstream_build:wago'))->toBe('12.1.0.69875');
});

test('a forced check is closed to anyone but an administrator', function (): void {
    $this->postJson('/api/admin/build-check')->assertForbidden();
});

test('a mode that is neither incremental nor forced is refused', function (): void {
    Bus::fake();

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'all', 'mode' => 'brutal'])
        ->assertStatus(422);

    Bus::assertNothingDispatched();
});

test('the paths the orchestrated import replaced are gone from the console', function (): void {
    expect(Artisan::all())
        ->not->toHaveKey('app:wow-data-refresh')
        ->not->toHaveKey('app:wow-quest-faction-tag');
});

test('an import that starts is already trackable', function (): void {
    Bus::fake();

    $jobId = $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'all', 'mode' => 'incremental'])
        ->json('jobId');

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/'.$jobId)
        ->assertOk()
        ->assertJsonPath('status', 'pending');
});

// ─── un import à la fois ────────────────────────────────────

test('an import that starts takes the lock before the worker has even picked the job up', function (): void {
    Bus::fake();

    $jobId = $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'all', 'mode' => 'incremental'])
        ->json('jobId');

    expect(resolve(CurrentImport::class)->jobId())->toBe($jobId);
});

test('a second import is refused while one is running, and the refusal says which', function (): void {
    Bus::fake();
    resolve(CurrentImport::class)->mark('job-en-cours', now()->subMinutes(4)->getTimestamp());

    $response = $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/import', ['scope' => 'all', 'mode' => 'incremental']);

    $response->assertStatus(409)
        ->assertJsonPath('jobId', 'job-en-cours');

    expect($response->json('message'))->toContain('4 min');

    Bus::assertNothingDispatched();
});

// ─── journal à curseur ──────────────────────────────────────

test('the progress endpoint hands back the journal from the start when no cursor is given', function (): void {
    (new ImportProgressStore)->save(ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp()));
    $importLog = resolve(ImportLog::class);
    $importLog->append('job-1', 'première');
    $importLog->append('job-1', 'deuxième');

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/job-1')
        ->assertOk()
        ->assertJsonPath('log.cursor', 2)
        ->assertJsonPath('log.lines', ['première', 'deuxième']);
});

test('the progress endpoint hands back only what appeared since the cursor', function (): void {
    (new ImportProgressStore)->save(ImportRun::start('job-1', [ImportStage::Mounts], now()->getTimestamp()));
    $importLog = resolve(ImportLog::class);
    $importLog->append('job-1', 'première');
    $importLog->append('job-1', 'deuxième');

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/job-1?cursor=1')
        ->assertOk()
        ->assertJsonPath('log.cursor', 2)
        ->assertJsonPath('log.lines', ['deuxième']);
});

test('the progress endpoint refuses a cursor that is not a whole position', function (): void {
    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/job-1?cursor=trois')
        ->assertStatus(422);
});

test('an unknown job answers an empty journal rather than nothing at all', function (): void {
    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/nobody')
        ->assertOk()
        ->assertJsonPath('status', 'not_found')
        ->assertJsonPath('log.lines', [])
        ->assertJsonPath('log.cursor', 0);
});

// ─── raccrochage d'un import en cours ───────────────────────

test('the panel is told no import is running when none is', function (): void {
    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/current')
        ->assertOk()
        ->assertJsonPath('jobId', null);
});

test('the panel is told which import is running, so it can pick the tracking back up', function (): void {
    resolve(CurrentImport::class)->mark('job-1', now()->getTimestamp());

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/current')
        ->assertOk()
        ->assertJsonPath('jobId', 'job-1');
});

test('the current import endpoint is closed to anyone but an administrator', function (): void {
    $this->getJson('/api/admin/import/current')->assertForbidden();
});

test('an import named current is not read as a job identifier', function (): void {
    resolve(ImportLog::class)->append('current', 'ne devrait jamais être lue');

    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/import/current')
        ->assertOk()
        ->assertJsonMissingPath('log');
});

// ─── les outils du panneau ──────────────────────────────────

test('the status endpoint says whether the application is in maintenance', function (): void {
    $this->withSession(['is_admin' => true])
        ->getJson('/api/admin/status')
        ->assertOk()
        ->assertJson(['maintenance' => false]);
});

test('clearing the caches hands back what the purges said', function (): void {
    Artisan::shouldReceive('call')->times(4)->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('vidé.');

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/clear-cache')
        ->assertOk()
        ->assertJson(['output' => 'vidé.vidé.vidé.vidé.']);
});

test('the maintenance endpoint refuses a request that says nothing', function (): void {
    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/maintenance', [])
        ->assertStatus(422);
});

test('the maintenance endpoint refuses a bypass secret too short to protect anything', function (): void {
    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/maintenance', ['enable' => true, 'secret' => 'court'])
        ->assertStatus(422);
});

test('taking the application down answers with its new state', function (): void {
    Artisan::shouldReceive('call')->once()->with('down', ['--secret' => 'un-secret-assez-long'])->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/maintenance', ['enable' => true, 'secret' => 'un-secret-assez-long'])
        ->assertOk()
        ->assertJsonStructure(['maintenance']);
});

test('an announcement is posted to the webhook of its channel', function (): void {
    config(['services.discord.webhook_changelog' => 'https://discord.test/changelog']);
    Http::fake(['discord.test/*' => Http::response('', 204)]);

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/discord', [
            'channel' => 'changelog',
            'title' => 'Nouvelle version',
            'description' => 'Le score a été revu.',
            'color' => 3447003,
            'fields' => [['name' => 'Score', 'value' => 'Recalculé', 'inline' => false]],
            'footer' => 'WowPlanet',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    Http::assertSent(function (Request $request): bool {
        $embed = $request['embeds'][0];

        return $embed['title'] === 'Nouvelle version'
            && $embed['color'] === 3447003
            && $embed['fields'][0]['name'] === 'Score'
            && $embed['footer']['text'] === 'WowPlanet';
    });
});

test('an announcement without a title or a description is refused', function (): void {
    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/discord', ['channel' => 'changelog'])
        ->assertStatus(422);
});

test('an announcement to a channel that does not exist is refused', function (): void {
    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/discord', ['channel' => 'salon-secret', 'title' => 'Titre', 'description' => 'Corps'])
        ->assertStatus(422);
});

test('an empty footer is not sent as a footer', function (): void {
    config(['services.discord.webhook_changelog' => 'https://discord.test/changelog']);
    Http::fake(['discord.test/*' => Http::response('', 204)]);

    $this->withSession(['is_admin' => true])
        ->postJson('/api/admin/discord', [
            'channel' => 'changelog',
            'title' => 'Titre',
            'description' => 'Corps',
            'footer' => '',
        ])
        ->assertOk();

    Http::assertSent(fn ($request): bool => ! isset($request['embeds'][0]['footer']));
});

test('the tools of the panel are closed to anyone but an administrator', function (): void {
    $this->getJson('/api/admin/status')->assertForbidden();
    $this->postJson('/api/admin/clear-cache')->assertForbidden();
    $this->postJson('/api/admin/maintenance', ['enable' => true])->assertForbidden();
    $this->postJson('/api/admin/discord', ['channel' => 'changelog'])->assertForbidden();
});

/**
 * @return array<string, mixed>
 */
function adminSession(): array
{
    return ['is_admin' => true, 'blizzard_user_token' => 'token', 'bnet_user_id' => '12345'];
}

test('pausing the running import leaves the order for the job to read', function (): void {
    (new CurrentImport)->mark('job-1', now()->getTimestamp());

    $this->withSession(adminSession())
        ->postJson('/api/admin/import/job-1/pause')
        ->assertOk()
        ->assertJsonPath('jobId', 'job-1');

    $pending = resolve(ImportControl::class)->pending('job-1');

    expect($pending?->signal)->toBe(ImportSignal::Pause)
        ->and($pending->actor)->toBe('12345');
});

test('resuming the running import lifts the order', function (): void {
    (new CurrentImport)->mark('job-1', now()->getTimestamp());
    resolve(ImportControl::class)->request('job-1', ImportSignal::Pause, '12345', now()->getTimestamp());

    $this->withSession(adminSession())
        ->postJson('/api/admin/import/job-1/resume')
        ->assertOk();

    expect(resolve(ImportControl::class)->pending('job-1'))->toBeNull();
});

test('cancelling the running import leaves the order for the job to read', function (): void {
    (new CurrentImport)->mark('job-1', now()->getTimestamp());

    $this->withSession(adminSession())
        ->postJson('/api/admin/import/job-1/cancel')
        ->assertOk();

    expect(resolve(ImportControl::class)->pending('job-1')?->signal)->toBe(ImportSignal::Cancel);
});

test('an import that is not the running one cannot be steered', function (string $action): void {
    (new CurrentImport)->mark('job-1', now()->getTimestamp());

    $this->withSession(adminSession())
        ->postJson('/api/admin/import/job-2/'.$action)
        ->assertStatus(409);

    expect(resolve(ImportControl::class)->pending('job-2'))->toBeNull();
})->with(['pause', 'resume', 'cancel']);

test('steering an import when none runs is refused', function (): void {
    $this->withSession(adminSession())
        ->postJson('/api/admin/import/job-1/pause')
        ->assertStatus(409);
});

test('steering an import is closed to anyone but an administrator', function (string $action): void {
    $this->postJson('/api/admin/import/job-1/'.$action)->assertForbidden();
})->with(['pause', 'resume', 'cancel']);

test('who steered the import is written to the audit trail', function (): void {
    (new CurrentImport)->mark('job-1', now()->getTimestamp());

    $this->withSession(adminSession())->postJson('/api/admin/import/job-1/cancel')->assertOk();

    expect(auditTrail())->toBe([[
        'message' => 'Import steered from the admin panel',
        'context' => ['jobId' => 'job-1', 'signal' => 'cancel', 'actor' => '12345'],
    ]]);
});

test('syncing the whole socle queues the sync command', function (): void {
    Bus::fake();

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/sync', ['scope' => 'all'])
        ->assertOk()
        ->assertJsonStructure(['jobId']);

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->command === 'app:wow-reference-sync'
        && $runImportJob->parameters === []);
});

test('syncing one table sends its source name, taken from the server catalogue', function (): void {
    Bus::fake();

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/sync', ['scope' => 'table', 'table' => 'Faction'])
        ->assertOk();

    Bus::assertDispatched(fn (RunImportJob $runImportJob): bool => $runImportJob->parameters === ['--table' => 'Faction']);
});

test('a table name the catalogue does not carry is refused', function (): void {
    Bus::fake();

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/sync', ['scope' => 'table', 'table' => 'wow_ref_faction; DROP TABLE'])
        ->assertStatus(422);

    Bus::assertNothingDispatched();
});

test('syncing one table without naming it is refused', function (): void {
    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/sync', ['scope' => 'table'])
        ->assertStatus(422);
});

test('syncing the socle while an import runs is refused, and says since when', function (): void {
    Bus::fake();
    (new CurrentImport)->mark('job-1', now()->getTimestamp() - 120);

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/sync', ['scope' => 'all'])
        ->assertStatus(409)
        ->assertJsonPath('jobId', 'job-1');

    Bus::assertNothingDispatched();
});

test('importing while the socle is being synced is refused by the same lock', function (): void {
    Bus::fake();

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/sync', ['scope' => 'all'])
        ->assertOk();

    $this->withSession(adminSession())
        ->postJson('/api/admin/import', ['scope' => 'all', 'mode' => 'incremental'])
        ->assertStatus(409);
});

test('syncing the socle is closed to anyone but an administrator', function (): void {
    $this->postJson('/api/admin/reference/sync', ['scope' => 'all'])->assertForbidden();
});

function storedForPurge(string $filename, int $bytes): void
{
    Storage::disk(ReferenceStore::DISK)->put($filename, str_repeat('x', $bytes));
}

test('sweeping the store takes the obsolete and the orphans, and reports what it freed', function (): void {
    Storage::fake(ReferenceStore::DISK);

    $live = WowReferenceDownload::factory()->create(['source_table' => 'Faction', 'build' => '12.1.0.69875', 'downloaded_at' => '2026-09-20 08:55:02']);
    $obsolete = WowReferenceDownload::factory()->create(['source_table' => 'Faction', 'build' => '12.1.0.69587', 'downloaded_at' => '2026-09-12 16:17:46']);
    storedForPurge($live->filename, 100);
    storedForPurge($obsolete->filename, 90);
    storedForPurge('spell_name.csv', 10);

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/purge', ['scope' => 'obsolete'])
        ->assertOk()
        ->assertJson(['files' => 2, 'bytes' => 100]);

    Storage::disk(ReferenceStore::DISK)->assertExists($live->filename);
    Storage::disk(ReferenceStore::DISK)->assertMissing($obsolete->filename);
    Storage::disk(ReferenceStore::DISK)->assertMissing('spell_name.csv');
});

test('a sweep never takes the file in service nor an upstream taxonomy snapshot', function (): void {
    Storage::fake(ReferenceStore::DISK);

    $live = WowReferenceDownload::factory()->create(['source_table' => 'Faction', 'build' => '12.1.0.69875', 'downloaded_at' => '2026-09-20 08:55:02']);
    storedForPurge($live->filename, 100);
    storedForPurge('mounts.json', 10);

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/purge', ['scope' => 'obsolete'])
        ->assertOk()
        ->assertJson(['files' => 0, 'bytes' => 0]);

    Storage::disk(ReferenceStore::DISK)->assertExists($live->filename);
    Storage::disk(ReferenceStore::DISK)->assertExists('mounts.json');
});

test('a named file is removed one by one, even when it is the one in service', function (): void {
    Storage::fake(ReferenceStore::DISK);

    $live = WowReferenceDownload::factory()->create(['source_table' => 'Faction', 'build' => '12.1.0.69875', 'downloaded_at' => '2026-09-20 08:55:02']);
    storedForPurge($live->filename, 100);

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/purge', ['scope' => 'selection', 'files' => [$live->filename]])
        ->assertOk()
        ->assertJson(['files' => 1, 'bytes' => 100]);

    Storage::disk(ReferenceStore::DISK)->assertMissing($live->filename);
});

test('a filename that is not on the disk is refused rather than resolved', function (): void {
    Storage::fake(ReferenceStore::DISK);
    storedForPurge('faction-12.1.0.69875.csv', 100);

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/purge', ['scope' => 'selection', 'files' => ['../../.env']])
        ->assertStatus(422);

    Storage::disk(ReferenceStore::DISK)->assertExists('faction-12.1.0.69875.csv');
});

test('a selection with nothing named is refused', function (): void {
    Storage::fake(ReferenceStore::DISK);

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/purge', ['scope' => 'selection'])
        ->assertStatus(422);
});

test('a scope the server does not know is refused', function (): void {
    Storage::fake(ReferenceStore::DISK);

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/purge', ['scope' => 'everything'])
        ->assertStatus(422);
});

test('purging while an import runs is refused, and says since when', function (): void {
    Storage::fake(ReferenceStore::DISK);
    storedForPurge('spell_name.csv', 10);
    (new CurrentImport)->mark('job-1', now()->getTimestamp() - 120);

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/purge', ['scope' => 'obsolete'])
        ->assertStatus(409)
        ->assertJsonPath('jobId', 'job-1');

    Storage::disk(ReferenceStore::DISK)->assertExists('spell_name.csv');
});

test('a purge records the administrator who asked for it', function (): void {
    Storage::fake(ReferenceStore::DISK);
    storedForPurge('spell_name.csv', 10);

    $this->withSession(adminSession())
        ->postJson('/api/admin/reference/purge', ['scope' => 'obsolete'])
        ->assertOk();

    expect(auditTrail()[0]['context']['actor'] ?? null)->toBe('12345');
});

test('purging the store is closed to anyone but an administrator', function (): void {
    $this->postJson('/api/admin/reference/purge', ['scope' => 'obsolete'])->assertForbidden();
});

function pendingMount(int $id, string $name = 'Loup gris'): void
{
    WowMount::query()->create(['id' => $id, 'name_fr' => $name, 'is_active' => true]);
}

function taxonomyOf(int $entryId): ?WowCollectionTaxonomy
{
    return WowCollectionTaxonomy::query()
        ->where('entity', 'mount')
        ->where('entry_id', $entryId)
        ->first();
}

test('arbitrating files the entries under the category and source it is given', function (): void {
    pendingMount(7);
    pendingMount(8, 'Étalon blanc');

    $this->withSession(adminSession())
        ->postJson('/api/admin/taxonomy/arbitrate', [
            'entity' => 'mount',
            'entries' => [7, 8],
            'category' => 'Racial',
            'source' => 'Human',
        ])
        ->assertOk()
        ->assertJsonPath('arbitrated', 2)
        ->assertJsonStructure(['snapshot' => ['path', 'entries', 'in_step']]);

    expect(taxonomyOf(7)?->category)->toBe('Racial');
});

test('arbitrating can file an entry nowhere on purpose', function (): void {
    pendingMount(7);

    $this->withSession(adminSession())
        ->postJson('/api/admin/taxonomy/arbitrate', [
            'entity' => 'mount',
            'entries' => [7],
            'category' => null,
            'source' => null,
        ])
        ->assertOk();

    expect(taxonomyOf(7))->not->toBeNull()
        ->and(taxonomyOf(7)?->category)->toBeNull();
});

test('a correction of an entry that is already curated goes through the same door', function (): void {
    pendingMount(7);
    WowCollectionTaxonomy::query()->insert([
        'entity' => 'mount', 'entry_id' => 7, 'category' => 'Racial', 'source' => 'Human', 'obtainable' => true,
    ]);

    $this->withSession(adminSession())
        ->postJson('/api/admin/taxonomy/arbitrate', [
            'entity' => 'mount', 'entries' => [7], 'category' => 'Professions', 'source' => 'Fishing',
        ])
        ->assertOk();

    expect(taxonomyOf(7)?->category)->toBe('Professions');
});

test('an entry that is not in this collection catalogue is refused', function (): void {
    $this->withSession(adminSession())
        ->postJson('/api/admin/taxonomy/arbitrate', [
            'entity' => 'mount', 'entries' => [999], 'category' => 'Racial', 'source' => 'Human',
        ])
        ->assertStatus(422)
        ->assertJsonPath('entries', [999]);

    expect(WowCollectionTaxonomy::query()->count())->toBe(0);
});

test('an entry curated in the taxonomy but gone from the catalogue is refused with its reason', function (): void {
    WowCollectionTaxonomy::query()->insert([
        'entity' => 'mount', 'entry_id' => 999, 'category' => 'Other', 'source' => null, 'obtainable' => true,
    ]);

    $this->withSession(adminSession())
        ->postJson('/api/admin/taxonomy/arbitrate', [
            'entity' => 'mount', 'entries' => [999], 'category' => 'Legion', 'source' => 'Drop',
        ])
        ->assertStatus(422)
        ->assertJsonPath('entries', [999])
        ->assertJsonPath('message', 'Certaines entrées ne sont pas au catalogue de cette collection (mount) : 999.');

    expect(taxonomyOf(999)?->category)->toBe('Other');
});

test('a collection the server does not know is refused', function (): void {
    $this->withSession(adminSession())
        ->postJson('/api/admin/taxonomy/arbitrate', [
            'entity' => 'wow_mounts; DROP TABLE', 'entries' => [7], 'category' => 'Racial', 'source' => 'Human',
        ])
        ->assertStatus(422);
});

test('an arbitration with no entry named is refused', function (): void {
    $this->withSession(adminSession())
        ->postJson('/api/admin/taxonomy/arbitrate', ['entity' => 'mount', 'entries' => []])
        ->assertStatus(422);
});

test('an arbitration records the administrator who made it', function (): void {
    pendingMount(7);

    $this->withSession(adminSession())
        ->postJson('/api/admin/taxonomy/arbitrate', [
            'entity' => 'mount', 'entries' => [7], 'category' => 'Racial', 'source' => 'Human',
        ])
        ->assertOk();

    expect(auditTrail()[0]['message'] ?? null)->toBe('Collection taxonomy arbitrated from the admin panel')
        ->and(auditTrail()[0]['context']['actor'] ?? null)->toBe('12345');
});

test('arbitrating the taxonomy is closed to anyone but an administrator', function (): void {
    $this->postJson('/api/admin/taxonomy/arbitrate', ['entity' => 'mount', 'entries' => [7]])->assertForbidden();
});

test('retrying a failed job puts it back on its queue', function (): void {
    useRedisQueue();
    $uuid = recordFailedJob();

    $this->withSession(adminSession())
        ->postJson('/api/admin/failed-jobs/'.$uuid.'/retry')
        ->assertOk()
        ->assertJson(['uuid' => $uuid]);

    expect(Queue::connection('redis')->size('imports'))->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('retrying a failed job while an import runs is refused, and says since when', function (): void {
    useRedisQueue();
    $uuid = recordFailedJob();
    resolve(CurrentImport::class)->mark('job-running', now()->subMinutes(3)->getTimestamp());

    $this->withSession(adminSession())
        ->postJson('/api/admin/failed-jobs/'.$uuid.'/retry')
        ->assertStatus(409)
        ->assertJson(['jobId' => 'job-running']);

    expect(DB::table('failed_jobs')->count())->toBe(1);
});

test('forgetting a failed job removes it', function (): void {
    $uuid = recordFailedJob();

    $this->withSession(adminSession())
        ->deleteJson('/api/admin/failed-jobs/'.$uuid)
        ->assertOk()
        ->assertJson(['uuid' => $uuid]);

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

test('an unknown failed job answers as not found', function (string $method, string $uri): void {
    $this->withSession(adminSession())
        ->json($method, $uri)
        ->assertNotFound()
        ->assertJson(['message' => 'Le job échoué 00000000-0000-0000-0000-000000000000 est introuvable.']);
})->with([
    'retry' => ['POST', '/api/admin/failed-jobs/00000000-0000-0000-0000-000000000000/retry'],
    'forget' => ['DELETE', '/api/admin/failed-jobs/00000000-0000-0000-0000-000000000000'],
]);

test('who retried or forgot a failed job is written to the application log', function (): void {
    $uuid = recordFailedJob();

    $this->withSession(adminSession())->deleteJson('/api/admin/failed-jobs/'.$uuid)->assertOk();

    expect(auditTrail())->toBe([['message' => 'Failed job forgotten from the admin panel', 'context' => ['uuid' => $uuid, 'actor' => '12345']]]);
});

test('failed jobs are closed to anyone but an administrator', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertForbidden();
})->with([
    'retry' => ['POST', '/api/admin/failed-jobs/any/retry'],
    'forget' => ['DELETE', '/api/admin/failed-jobs/any'],
]);

test('a failed job that can no longer be read is refused with the reason', function (): void {
    useRedisQueue();
    $uuid = recordFailedJob();
    DB::table('failed_jobs')->where('uuid', $uuid)->update(['payload' => json_encode([
        'uuid' => $uuid,
        'displayName' => 'App\\Jobs\\RenamedJob',
        'data' => ['command' => 'neither serialized nor encrypted'],
    ])]);

    $this->withSession(adminSession())
        ->postJson('/api/admin/failed-jobs/'.$uuid.'/retry')
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'Le job échoué '.$uuid.' ne peut pas être relancé'));
});

test('reloading the taxonomy snapshot merges it into the base and says how much it brought', function (): void {
    writeTestTaxonomySnapshot(['mount' => [6 => new App\Infrastructure\Taxonomy\TaxonomyEntry('Racial', 'Human')]]);

    $this->withSession(adminSession())
        ->postJson('/api/admin/taxonomy/load')
        ->assertOk()
        ->assertJson(['inserted' => 1]);

    expect(WowCollectionTaxonomy::query()->count())->toBe(1);
});

test('the taxonomy snapshot rendered from the base is downloaded as a file to commit', function (): void {
    WowCollectionTaxonomy::query()->create(['entity' => 'mount', 'entry_id' => 6, 'category' => 'Racial', 'source' => 'Human', 'obtainable' => true]);

    $response = $this->withSession(adminSession())->get('/api/admin/taxonomy/snapshot');

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertDownload('collection_taxonomy.csv');

    expect($response->getContent())->toContain('mount,6,Racial,Human,true');
});

test('an empty taxonomy is not downloaded', function (): void {
    $this->withSession(adminSession())
        ->getJson('/api/admin/taxonomy/snapshot')
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'Taxonomie vide en base'));
});

test('reloading and downloading the snapshot are closed to anyone but an administrator', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertForbidden();
})->with([
    'reload' => ['POST', '/api/admin/taxonomy/load'],
    'download' => ['GET', '/api/admin/taxonomy/snapshot'],
]);
