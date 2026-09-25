<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Reference\ReferenceLoader;
use App\Infrastructure\Reference\ReferenceTable;
use Illuminate\Support\Facades\DB;

function contentTuningTable(): ReferenceTable
{
    $table = (new ReferenceCatalog)->find('ContentTuning');
    expect($table)->not->toBeNull();

    return $table;
}

function loadContentTuning(iterable $rows): int
{
    return resolve(ReferenceLoader::class)->replace(contentTuningTable(), $rows);
}

test('it loads the projected rows into the reference table', function (): void {
    loadContentTuning(["1\t9", "2\t10"]);

    expect(DB::table('wow_ref_content_tuning')->orderBy('id')->pluck('expansion_id', 'id')->all())
        ->toBe([1 => 9, 2 => 10]);
});

test('it returns the number of rows it loaded', function (): void {
    expect(loadContentTuning(["1\t9", "2\t10", "3\t11"]))->toBe(3);
});

test('it replaces the previous content instead of adding to it', function (): void {
    loadContentTuning(["1\t9", "2\t10"]);
    loadContentTuning(["3\t11"]);

    expect(DB::table('wow_ref_content_tuning')->pluck('id')->all())->toBe([3]);
});

test('it writes a null where the source had no value', function (): void {
    loadContentTuning(["1\t\\N"]);

    expect(DB::table('wow_ref_content_tuning')->where('id', 1)->value('expansion_id'))->toBeNull();
});

test('it accepts a source larger than a single copy chunk', function (): void {
    $rows = [];
    for ($id = 1; $id <= 12_000; $id++) {
        $rows[] = $id."\t".($id % 12);
    }

    expect(loadContentTuning($rows))->toBe(12_000)
        ->and(DB::table('wow_ref_content_tuning')->count())->toBe(12_000);
});

test('it consumes a generator without holding every row at once', function (): void {
    $rows = (function (): Generator {
        yield "1\t9";
        yield "2\t10";
    })();

    expect(loadContentTuning($rows))->toBe(2);
});

test('it refuses a value the target column cannot hold', function (): void {
    expect(fn (): int => loadContentTuning(["1\tpas un entier"]))->toThrow(PDOException::class);
});

test('it restores the previous content when the caller rolls back', function (): void {
    loadContentTuning(["1\t9"]);

    try {
        DB::transaction(function (): void {
            loadContentTuning(["2\t10", "3\tpas un entier"]);
        });
    } catch (PDOException) {
        // La transaction du chargement est le garde-fou : le socle précédent doit survivre.
    }

    expect(DB::table('wow_ref_content_tuning')->pluck('id')->all())->toBe([1]);
});
