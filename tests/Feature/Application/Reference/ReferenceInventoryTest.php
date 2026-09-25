<?php

declare(strict_types=1);

use App\Application\Import\VolumeShrink;
use App\Application\Reference\ReferenceInventory;
use App\Models\WowReferenceDownload;
use Illuminate\Support\Facades\DB;

function downloadOf(string $source, string $build, int $rows, string $at): void
{
    WowReferenceDownload::query()->create([
        'filename' => sprintf('%s-%s.csv', mb_strtolower($source), $build),
        'source_table' => $source,
        'build' => $build,
        'bytes' => 1_000,
        'row_count' => $rows,
        'downloaded_at' => $at,
    ]);
}

/**
 * @return array{source: string, table: string, rows: int, build: string|null, loaded_at: string|null, previous_rows: int|null, delta: int|null, is_empty: bool, has_shrunk: bool, is_stale: bool}
 */
function entryOf(string $source, ?string $liveBuild = null): array
{
    foreach (resolve(ReferenceInventory::class)->entries($liveBuild) as $entry) {
        if ($entry['source'] === $source) {
            return $entry;
        }
    }

    throw new RuntimeException('Table outside the catalogue: '.$source);
}

test('it lists every table of the reference catalogue', function (): void {
    $entries = resolve(ReferenceInventory::class)->entries(null);

    expect($entries)->toHaveCount(8)
        ->and(array_column($entries, 'source'))->toContain('Faction', 'AreaTable', 'SpellMisc');
});

test('a table nobody has ever loaded says so rather than showing a zero', function (): void {
    $entry = entryOf('Faction');

    expect($entry['build'])->toBeNull()
        ->and($entry['loaded_at'])->toBeNull()
        ->and($entry['previous_rows'])->toBeNull()
        ->and($entry['delta'])->toBeNull();
});

test('it reports the build and the date of the last successful load', function (): void {
    downloadOf('Faction', '12.1.0.69875', 868, '2026-09-19 19:38:50');

    $entry = entryOf('Faction');

    expect($entry['build'])->toBe('12.1.0.69875')
        ->and($entry['loaded_at'])->toStartWith('2026-09-19');
});

test('it counts the rows the socle really holds, not the ones the last load claimed', function (): void {
    downloadOf('Faction', '12.1.0.69875', 868, '2026-09-19 19:38:50');
    DB::table('wow_ref_faction')->insert([
        ['id' => 1, 'name_lang' => 'Alliance'],
        ['id' => 2, 'name_lang' => 'Horde'],
    ]);

    expect(entryOf('Faction')['rows'])->toBe(2);
});

test('it compares the last load with the one before it', function (): void {
    downloadOf('Mount', '12.1.0.69814', 1_689, '2026-09-13 19:29:15');
    downloadOf('Mount', '12.1.0.69875', 1_694, '2026-09-19 19:38:51');

    $entry = entryOf('Mount');

    expect($entry['previous_rows'])->toBe(1_689)
        ->and($entry['delta'])->toBe(5);
});

test('a table left behind on an older build is flagged as stale', function (): void {
    downloadOf('AreaTable', '12.1.0.69587', 10_002, '2026-09-12 16:17:46');

    expect(entryOf('AreaTable', '12.1.0.69875')['is_stale'])->toBeTrue();
});

test('a table loaded on the live build is not stale', function (): void {
    downloadOf('AreaTable', '12.1.0.69875', 10_002, '2026-09-19 19:38:50');

    expect(entryOf('AreaTable', '12.1.0.69875')['is_stale'])->toBeFalse();
});

test('nothing is stale when the live build cannot be read, rather than everything', function (): void {
    downloadOf('AreaTable', '12.1.0.69587', 10_002, '2026-09-12 16:17:46');

    expect(entryOf('AreaTable')['is_stale'])->toBeFalse();
});

test('an empty table is flagged, a loaded one is not', function (): void {
    downloadOf('Faction', '12.1.0.69875', 868, '2026-09-19 19:38:50');

    expect(entryOf('Faction')['is_empty'])->toBeTrue();

    DB::table('wow_ref_faction')->insert(['id' => 1, 'name_lang' => 'Alliance']);

    expect(entryOf('Faction')['is_empty'])->toBeFalse();
});

test('a load that lost more rows than the alert threshold allows is flagged', function (): void {
    downloadOf('Faction', '12.1.0.69814', 1_000, '2026-09-13 19:29:15');
    downloadOf('Faction', '12.1.0.69875', 800, '2026-09-19 19:38:50');

    expect(entryOf('Faction')['has_shrunk'])->toBeTrue();
});

test('a load that lost a handful of rows is business as usual, not an alert', function (): void {
    downloadOf('Faction', '12.1.0.69814', 1_000, '2026-09-13 19:29:15');
    downloadOf('Faction', '12.1.0.69875', 995, '2026-09-19 19:38:50');

    expect(entryOf('Faction')['has_shrunk'])->toBeFalse();
});

test('a load that grew is never flagged as having shrunk', function (): void {
    downloadOf('Faction', '12.1.0.69814', 800, '2026-09-13 19:29:15');
    downloadOf('Faction', '12.1.0.69875', 1_000, '2026-09-19 19:38:50');

    expect(entryOf('Faction')['has_shrunk'])->toBeFalse();
});

test('a first load has nothing to have shrunk from', function (): void {
    downloadOf('Faction', '12.1.0.69875', 800, '2026-09-19 19:38:50');

    expect(entryOf('Faction')['has_shrunk'])->toBeFalse();
});

test('the alert threshold sits well above the refusal the sync command already enforces', function (): void {
    // La commande refuse d'écrire sous 50 % du chargement précédent. Entre ce refus et
    // le seuil d'alerte, le chargement passe sans que personne soit prévenu : c'est
    // exactement l'angle mort que cette page comble.
    expect(VolumeShrink::ALERT_RATIO)->toBeGreaterThan(0.5)
        ->and(VolumeShrink::ALERT_RATIO)->toBeLessThan(1.0);
});

test('a table that grew out of an empty previous load is not read as having shrunk', function (): void {
    downloadOf('Faction', '12.1.0.69814', 0, '2026-09-13 19:29:15');
    downloadOf('Faction', '12.1.0.69875', 868, '2026-09-19 19:38:50');

    expect(entryOf('Faction')['has_shrunk'])->toBeFalse();
});
