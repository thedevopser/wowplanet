<?php

declare(strict_types=1);

use App\Application\Reference\ReferenceFileInventory;
use App\Application\Reference\ReferenceFileState;
use App\Infrastructure\Reference\ReferenceStore;
use App\Models\WowReferenceDownload;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ReferenceStore::DISK);
});

function onDisk(string $filename, int $bytes): void
{
    Storage::disk(ReferenceStore::DISK)->put($filename, str_repeat('x', $bytes));
}

function recorded(string $source, string $build, int $bytes, string $at): WowReferenceDownload
{
    return WowReferenceDownload::factory()->create([
        'source_table' => $source,
        'build' => $build,
        'bytes' => $bytes,
        'downloaded_at' => $at,
    ]);
}

/**
 * @return array{files: list<array{filename: string, state: string, bytes: int, source_table: string|null, build: string|null, loaded_at: int|null}>, missing: list<array{filename: string, source_table: string, build: string, loaded_at: int}>, totals: array{files: int, bytes: int, sweepable_files: int, sweepable_bytes: int}}
 */
function contents(): array
{
    return resolve(ReferenceFileInventory::class)->contents();
}

test('it reads what is on disk and what the inventory says about it', function (): void {
    $wowReferenceDownload = recorded('Faction', '12.1.0.69875', 100, '2026-09-20 08:55:02');
    onDisk($wowReferenceDownload->filename, 100);

    $contents = contents();

    expect($contents['files'])->toHaveCount(1)
        ->and($contents['files'][0]['filename'])->toBe($wowReferenceDownload->filename)
        ->and($contents['files'][0]['state'])->toBe(ReferenceFileState::Live->value)
        ->and($contents['files'][0]['source_table'])->toBe('Faction')
        ->and($contents['files'][0]['build'])->toBe('12.1.0.69875');
});

test('an older load of the same table comes out obsolete', function (): void {
    $wowReferenceDownload = recorded('Faction', '12.1.0.69587', 90, '2026-09-12 16:17:46');
    $new = recorded('Faction', '12.1.0.69875', 100, '2026-09-20 08:55:02');
    onDisk($wowReferenceDownload->filename, 90);
    onDisk($new->filename, 100);

    $states = [];
    foreach (contents()['files'] as $file) {
        $states[$file['filename']] = $file['state'];
    }

    expect($states[$wowReferenceDownload->filename])->toBe(ReferenceFileState::Obsolete->value)
        ->and($states[$new->filename])->toBe(ReferenceFileState::Live->value);
});

test('the three upstream taxonomy snapshots are recognised, not taken for orphans', function (): void {
    onDisk('mounts.json', 10);
    onDisk('pets.json', 10);
    onDisk('decors.json', 10);

    expect(array_column(contents()['files'], 'state'))
        ->toBe(array_fill(0, 3, ReferenceFileState::Taxonomy->value));
});

test('a file the inventory never heard of comes out orphan', function (): void {
    onDisk('spell_name.csv', 10);

    expect(contents()['files'][0]['state'])->toBe(ReferenceFileState::Orphan->value);
});

test('an inventory row whose file has gone is reported apart', function (): void {
    $wowReferenceDownload = recorded('Faction', '12.1.0.69587', 90, '2026-09-12 16:17:46');

    $contents = contents();

    expect($contents['files'])->toBe([])
        ->and($contents['missing'])->toHaveCount(1)
        ->and($contents['missing'][0]['filename'])->toBe($wowReferenceDownload->filename);
});

test('the totals count the store and what a sweep would take from it', function (): void {
    $wowReferenceDownload = recorded('Faction', '12.1.0.69875', 100, '2026-09-20 08:55:02');
    $obsolete = recorded('Faction', '12.1.0.69587', 90, '2026-09-12 16:17:46');
    onDisk($wowReferenceDownload->filename, 100);
    onDisk($obsolete->filename, 90);
    onDisk('spell_name.csv', 10);
    onDisk('mounts.json', 5);

    expect(contents()['totals'])->toBe([
        'files' => 4,
        'bytes' => 205,
        'sweepable_files' => 2,
        'sweepable_bytes' => 100,
    ]);
});

test('an empty store reports zeroes rather than failing', function (): void {
    expect(contents())->toBe([
        'files' => [],
        'missing' => [],
        'totals' => ['files' => 0, 'bytes' => 0, 'sweepable_files' => 0, 'sweepable_bytes' => 0],
    ]);
});

test('a store directory that was never created reports zeroes too', function (): void {
    removeDirectory(Storage::disk(ReferenceStore::DISK)->path(''));

    expect(contents()['totals']['files'])->toBe(0);
});

test('a sweep targets the obsolete and the orphans, and nothing else', function (): void {
    $wowReferenceDownload = recorded('Faction', '12.1.0.69875', 100, '2026-09-20 08:55:02');
    $obsolete = recorded('Faction', '12.1.0.69587', 90, '2026-09-12 16:17:46');
    onDisk($wowReferenceDownload->filename, 100);
    onDisk($obsolete->filename, 90);
    onDisk('spell_name.csv', 10);
    onDisk('mounts.json', 5);

    expect(resolve(ReferenceFileInventory::class)->sweepableFilenames())
        ->toEqualCanonicalizing([$obsolete->filename, 'spell_name.csv']);
});

test('the filenames it hands out are exactly what sits on disk', function (): void {
    $wowReferenceDownload = recorded('Faction', '12.1.0.69875', 100, '2026-09-20 08:55:02');
    onDisk($wowReferenceDownload->filename, 100);
    onDisk('mounts.json', 5);

    expect(resolve(ReferenceFileInventory::class)->filenames())
        ->toEqualCanonicalizing([$wowReferenceDownload->filename, 'mounts.json']);
});
