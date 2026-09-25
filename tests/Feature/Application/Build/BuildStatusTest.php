<?php

declare(strict_types=1);

use App\Application\Build\BuildStatus;
use App\Application\Import\ImportStage;
use App\Models\WowImportState;
use App\Models\WowReferenceDownload;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config([
        'services.blizzard.client_id' => 'test-client-id',
        'services.blizzard.client_secret' => 'test-client-secret',
        'services.blizzard.region' => 'eu',
    ]);
    Cache::flush();
});

function catalogueImportedOn(string $build): void
{
    foreach (ImportStage::catalogue() as $importStage) {
        WowImportState::query()->create([
            'entity' => $importStage->value,
            'build' => $build,
            'imported_at' => now(),
        ]);
    }
}

/**
 * @return array<string, array{stage: string, label: string, upstream: string, build: string|null, upstream_build: string|null, imported_at: string|null, state: string}>
 */
function entriesByStage(): array
{
    $entries = [];

    foreach (resolve(BuildStatus::class)->snapshot()['entries'] as $entry) {
        $entries[$entry['stage']] = $entry;
    }

    return $entries;
}

test('it carries one entry per stage of the chain, the socle opening the list', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');

    $entries = resolve(BuildStatus::class)->snapshot()['entries'];

    expect($entries)->toHaveCount(count(ImportStage::chain()))
        ->and($entries[0]['stage'])->toBe('reference')
        ->and($entries[0]['label'])->toBe('Socle de référence');
});

test('each entry is compared to its own upstream, never across families', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');
    catalogueImportedOn('12.1.0_68914');
    referenceLoadedOn('12.1.0.69875');

    $entries = entriesByStage();

    expect($entries['reference']['upstream'])->toBe('wago')
        ->and($entries['reference']['upstream_build'])->toBe('12.1.0.69875')
        ->and($entries['reference']['state'])->toBe('current')
        ->and($entries['mounts']['upstream'])->toBe('blizzard')
        ->and($entries['mounts']['upstream_build'])->toBe('12.1.0_68914')
        ->and($entries['mounts']['state'])->toBe('current');
});

test('everything on the current build is announced up to date explicitly', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');
    catalogueImportedOn('12.1.0_68914');
    referenceLoadedOn('12.1.0.69875');

    $snapshot = resolve(BuildStatus::class)->snapshot();

    expect($snapshot['is_up_to_date'])->toBeTrue()
        ->and($snapshot['is_conclusive'])->toBeTrue()
        ->and($snapshot['behind'])->toBe([]);
});

test('an entity left on an older build is flagged stale with both builds on its row', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');
    catalogueImportedOn('12.1.0_68914');
    referenceLoadedOn('12.1.0.69875');
    WowImportState::query()->where('entity', 'mounts')->update(['build' => '12.1.0_68000']);

    $entry = entriesByStage()['mounts'];

    expect($entry['state'])->toBe('stale')
        ->and($entry['build'])->toBe('12.1.0_68000')
        ->and($entry['upstream_build'])->toBe('12.1.0_68914');
});

test('an entity never imported is flagged never, not up to date', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');

    $entry = entriesByStage()['mounts'];

    expect($entry['state'])->toBe('never')
        ->and($entry['build'])->toBeNull()
        ->and($entry['imported_at'])->toBeNull();
});

test('behind lists the stages to relaunch, in chain order', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');
    catalogueImportedOn('12.1.0_68914');
    referenceLoadedOn('12.1.0.69587');
    WowImportState::query()->where('entity', 'mounts')->update(['build' => '12.1.0_68000']);

    $snapshot = resolve(BuildStatus::class)->snapshot();

    expect($snapshot['behind'])->toBe(['reference', 'mounts'])
        ->and($snapshot['is_up_to_date'])->toBeFalse();
});

test('an unreachable upstream leaves its entities unknown rather than current', function (): void {
    stubBlizzardBuild(null);
    stubWagoBuild('12.1.0.69875');
    catalogueImportedOn('12.1.0_68914');
    referenceLoadedOn('12.1.0.69875');

    $entries = entriesByStage();

    expect($entries['mounts']['state'])->toBe('unknown')
        ->and($entries['reference']['state'])->toBe('current');
});

test('an unreachable upstream is never up to date, even with nothing behind', function (): void {
    stubBlizzardBuild(null);
    stubWagoBuild('12.1.0.69875');
    referenceLoadedOn('12.1.0.69875');

    $snapshot = resolve(BuildStatus::class)->snapshot();

    expect($snapshot['is_conclusive'])->toBeFalse()
        ->and($snapshot['is_up_to_date'])->toBeFalse();
});

test('an entity whose upstream is unreadable is not listed as behind', function (): void {
    // On ne propose pas de réimporter ce qu'on n'a pas pu situer : le bouton relancerait
    // un import sur une supposition.
    stubBlizzardBuild(null);
    stubWagoBuild('12.1.0.69875');
    referenceLoadedOn('12.1.0.69875');

    expect(resolve(BuildStatus::class)->snapshot()['behind'])->toBe([]);
});

test('the last known build and its date survive an unreachable upstream', function (): void {
    stubWagoBuild('12.1.0.69875');
    stubBlizzardBuild('12.1.0_68914');
    resolve(BuildStatus::class)->snapshot();

    Cache::flush();
    stubBlizzardBuild(null);
    stubWagoBuild(null);

    $upstreams = resolve(BuildStatus::class)->snapshot()['upstreams'];

    expect($upstreams['blizzard']['build'])->toBe('12.1.0_68914')
        ->and($upstreams['blizzard']['reachable'])->toBeFalse()
        ->and($upstreams['blizzard']['checked_at'])->not->toBeNull()
        ->and($upstreams['blizzard']['label'])->toBe('API Blizzard')
        ->and($upstreams['wago']['build'])->toBe('12.1.0.69875');
});

test('a forced snapshot re-reads the upstream the cache would have served', function (): void {
    Cache::put('upstream_build:wago', '12.1.0.69587', 3600);
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');

    $snapshot = resolve(BuildStatus::class)->snapshot(force: true);

    expect($snapshot['upstreams']['wago']['build'])->toBe('12.1.0.69875');
});

test('the socle says how many of its tables are behind, its build alone not telling', function (): void {
    // Un socle partiellement rechargé n'est sur aucun build : le dernier chargement peut
    // être au build servi alors qu'une table est restée derrière. La ligne afficherait
    // deux builds identiques sous une alerte de retard sans cette précision.
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');
    referenceLoadedOn('12.1.0.69875', '2026-09-21 10:00:00');
    WowReferenceDownload::factory()->create([
        'source_table' => 'Faction',
        'build' => '12.1.0.69587',
        'downloaded_at' => '2026-09-21 11:00:00',
    ]);

    $entry = entriesByStage()['reference'];

    expect($entry['state'])->toBe('stale')
        ->and($entry['note'])->toBe('1 table sur 8 en retard');
});

test('a socle fully loaded carries no note to explain', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');
    referenceLoadedOn('12.1.0.69875');

    expect(entriesByStage()['reference']['note'])->toBeNull();
});

test('a catalogue entity has nothing to add to its two builds', function (): void {
    stubBlizzardBuild('12.1.0_68914');
    stubWagoBuild('12.1.0.69875');

    expect(entriesByStage()['mounts']['note'])->toBeNull();
});
