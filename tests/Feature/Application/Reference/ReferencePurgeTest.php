<?php

declare(strict_types=1);

use App\Application\Reference\ReferencePurge;
use App\Infrastructure\Reference\ReferenceStore;
use App\Models\WowReferenceDownload;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ReferenceStore::DISK);
});

function stored(string $filename, int $bytes): void
{
    Storage::disk(ReferenceStore::DISK)->put($filename, str_repeat('x', $bytes));
}

function inventoried(string $source, string $build, string $at): WowReferenceDownload
{
    return WowReferenceDownload::factory()->create([
        'source_table' => $source,
        'build' => $build,
        'downloaded_at' => $at,
    ]);
}

/**
 * @param  list<string>  $filenames
 * @return array{files: int, bytes: int}
 */
function purge(array $filenames, string $actor = '12345'): array
{
    return resolve(ReferencePurge::class)->purge($filenames, $actor);
}

test('it removes the files it is given from the store', function (): void {
    stored('spell_name.csv', 10);
    stored('item_sparse.csv', 20);

    purge(['spell_name.csv']);

    Storage::disk(ReferenceStore::DISK)->assertMissing('spell_name.csv');
    Storage::disk(ReferenceStore::DISK)->assertExists('item_sparse.csv');
});

test('it drops the inventory row along with the file, so nothing claims a file that has gone', function (): void {
    $wowReferenceDownload = inventoried('Faction', '12.1.0.69587', '2026-09-12 16:17:46');
    stored($wowReferenceDownload->filename, 10);

    purge([$wowReferenceDownload->filename]);

    expect(WowReferenceDownload::query()->find($wowReferenceDownload->filename))->toBeNull();
});

test('it leaves the inventory of the files it did not touch alone', function (): void {
    $wowReferenceDownload = inventoried('Faction', '12.1.0.69587', '2026-09-12 16:17:46');
    $kept = inventoried('Faction', '12.1.0.69875', '2026-09-20 08:55:02');
    stored($wowReferenceDownload->filename, 10);
    stored($kept->filename, 10);

    purge([$wowReferenceDownload->filename]);

    expect(WowReferenceDownload::query()->find($kept->filename))->not->toBeNull();
});

test('it reports how much it freed, so the panel can be held to its figure', function (): void {
    stored('spell_name.csv', 4_096);
    stored('item_sparse.csv', 2_048);

    expect(purge(['spell_name.csv', 'item_sparse.csv']))->toBe(['files' => 2, 'bytes' => 6_144]);
});

test('it measures a file before removing it, never after', function (): void {
    stored('spell_name.csv', 4_096);

    purge(['spell_name.csv']);

    expect(Storage::disk(ReferenceStore::DISK)->exists('spell_name.csv'))->toBeFalse();
});

test('a file already gone is skipped rather than counted as freed space', function (): void {
    expect(purge(['never-there.csv']))->toBe(['files' => 0, 'bytes' => 0]);
});

test('an empty selection does nothing', function (): void {
    stored('spell_name.csv', 10);

    expect(purge([]))->toBe(['files' => 0, 'bytes' => 0]);

    Storage::disk(ReferenceStore::DISK)->assertExists('spell_name.csv');
});

test('it records who purged what and how much it freed', function (): void {
    stored('spell_name.csv', 4_096);

    purge(['spell_name.csv'], '4242');

    expect(auditTrail())->toBe([[
        'message' => 'Reference store purged from the admin panel',
        'context' => ['files' => ['spell_name.csv'], 'bytes' => 4_096, 'actor' => '4242'],
    ]]);
});

test('a purge that freed nothing is not worth a line in the audit trail', function (): void {
    purge([]);

    expect(auditTrail())->toBe([]);
});
