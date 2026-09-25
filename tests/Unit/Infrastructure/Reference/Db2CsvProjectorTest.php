<?php

declare(strict_types=1);

use App\Infrastructure\Reference\Db2CsvProjector;
use App\Infrastructure\Reference\Exceptions\MalformedSourceException;
use App\Infrastructure\Reference\Exceptions\MissingColumnException;
use App\Infrastructure\Reference\ReferenceColumn;
use App\Infrastructure\Reference\ReferenceColumnType;
use App\Infrastructure\Reference\ReferenceTable;

function factionReferenceTable(): ReferenceTable
{
    return new ReferenceTable(
        source: 'Faction',
        slug: 'faction',
        locale: 'frFR',
        columns: [
            new ReferenceColumn('ID', 'id', ReferenceColumnType::Integer),
            new ReferenceColumn('Name_lang', 'name_lang', ReferenceColumnType::Text),
            new ReferenceColumn('ParentFactionID', 'parent_faction_id', ReferenceColumnType::Integer),
        ],
    );
}

/**
 * @param  list<string>  $lines
 * @return resource
 */
function referenceCsvStream(array $lines)
{
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, implode("\n", $lines)."\n");
    rewind($handle);

    return $handle;
}

/**
 * @param  list<string>  $lines
 * @return list<string>
 */
function projectReferenceCsv(array $lines): array
{
    return iterator_to_array((new Db2CsvProjector)->project(factionReferenceTable(), referenceCsvStream($lines)));
}

test('it keeps only the declared columns, in the declared order', function (): void {
    $rows = projectReferenceCsv([
        'Description_lang,ParentFactionID,ID,Name_lang',
        'Une description,1118,72,Hurlevent',
    ]);

    expect($rows)->toBe(["72\tHurlevent\t1118"]);
});

test('it reads columns by name, so a reordered source changes nothing', function (): void {
    $rows = projectReferenceCsv([
        'ID,Name_lang,ParentFactionID',
        '72,Hurlevent,1118',
    ]);

    expect($rows)->toBe(["72\tHurlevent\t1118"]);
});

test('it projects every data row', function (): void {
    $rows = projectReferenceCsv([
        'ID,Name_lang,ParentFactionID',
        '72,Hurlevent,1118',
        '76,Orgrimmar,1118',
        '47,Fer-de-Lance,0',
    ]);

    expect($rows)->toHaveCount(3);
});

test('it refuses a source that no longer carries a declared column', function (): void {
    expect(fn (): array => projectReferenceCsv(['ID,Name_lang', '72,Hurlevent']))
        ->toThrow(MissingColumnException::class, 'ParentFactionID');
});

test('the missing column failure names the source table so the patch is traceable', function (): void {
    expect(fn (): array => projectReferenceCsv(['ID,Name_lang', '72,Hurlevent']))
        ->toThrow(MissingColumnException::class, 'Faction');
});

test('it refuses a source without a header row', function (): void {
    $handle = fopen('php://memory', 'r+');

    expect(fn (): array => iterator_to_array((new Db2CsvProjector)->project(factionReferenceTable(), $handle)))
        ->toThrow(MalformedSourceException::class);
});

test('an empty numeric cell becomes a null rather than an empty string', function (): void {
    $rows = projectReferenceCsv([
        'ID,Name_lang,ParentFactionID',
        '72,Hurlevent,',
    ]);

    expect($rows)->toBe(["72\tHurlevent\t\\N"]);
});

test('an empty text cell stays an empty string', function (): void {
    $rows = projectReferenceCsv([
        'ID,Name_lang,ParentFactionID',
        '72,,1118',
    ]);

    expect($rows)->toBe(["72\t\t1118"]);
});

test('it escapes a value carrying the character that separates the columns', function (): void {
    $rows = projectReferenceCsv([
        'ID,Name_lang,ParentFactionID',
        "72,\"Hurlevent\tla belle\",1118",
    ]);

    expect($rows)->toBe(["72\tHurlevent\\tla belle\t1118"]);
});

test('it yields rows one by one instead of building the whole set in memory', function (): void {
    $generator = (new Db2CsvProjector)->project(factionReferenceTable(), referenceCsvStream([
        'ID,Name_lang,ParentFactionID',
        '72,Hurlevent,1118',
    ]));

    expect($generator)->toBeInstanceOf(Generator::class);
});
