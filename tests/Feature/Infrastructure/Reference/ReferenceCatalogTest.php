<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Reference\ReferenceTable;
use Illuminate\Support\Facades\Schema;

test('every declared table exists in the database', function (): void {
    foreach ((new ReferenceCatalog)->tables() as $referenceTable) {
        expect(Schema::hasTable($referenceTable->table()))->toBeTrue($referenceTable->table());
    }
});

test('every declared column exists in its table', function (): void {
    foreach ((new ReferenceCatalog)->tables() as $referenceTable) {
        expect(Schema::hasColumns($referenceTable->table(), $referenceTable->targetColumns()))
            ->toBeTrue($referenceTable->table().': '.implode(', ', $referenceTable->targetColumns()));
    }
});

test('a table is identified by its source name and by its slug alone', function (): void {
    $tables = (new ReferenceCatalog)->tables();

    $sources = array_map(static fn (ReferenceTable $referenceTable): string => $referenceTable->source, $tables);
    $slugs = array_map(static fn (ReferenceTable $referenceTable): string => $referenceTable->slug, $tables);

    expect(array_unique($sources))->toHaveCount(count($tables))
        ->and(array_unique($slugs))->toHaveCount(count($tables));
});

test('every table carries the DB2 identifier as its first column', function (): void {
    foreach ((new ReferenceCatalog)->tables() as $referenceTable) {
        expect($referenceTable->columns[0]->source)->toBe('ID')
            ->and($referenceTable->columns[0]->target)->toBe('id');
    }
});

test('an unknown source resolves to nothing rather than to a wrong table', function (): void {
    expect((new ReferenceCatalog)->find('Inconnue'))->toBeNull();
});

test('the mount table carries the source spell that links a mount to its icon', function (): void {
    $mount = (new ReferenceCatalog)->find('Mount');

    expect($mount)->not->toBeNull()
        ->and($mount->table())->toBe('wow_ref_mount')
        ->and($mount->targetColumns())->toBe(['id', 'source_spell_id']);
});

test('the spell misc table carries the icon file of a spell', function (): void {
    $spellMisc = (new ReferenceCatalog)->find('SpellMisc');

    expect($spellMisc)->not->toBeNull()
        ->and($spellMisc->table())->toBe('wow_ref_spell_misc')
        ->and($spellMisc->targetColumns())->toBe(['id', 'spell_id', 'spell_icon_file_data_id']);
});

test('the decor tag tables link a decor to its tags, each tag to its group and its label', function (string $source, string $table, array $columns): void {
    $referenceTable = (new ReferenceCatalog)->find($source);

    expect($referenceTable)->not->toBeNull()
        ->and($referenceTable->table())->toBe($table)
        ->and($referenceTable->targetColumns())->toBe($columns);
})->with([
    'decor to tag' => ['DataTagXHouseDecorRecord', 'wow_ref_data_tag_x_house_decor_record', ['id', 'data_tag_id', 'house_decor_id']],
    'tag to group' => ['DataTagGroupXDataTag', 'wow_ref_data_tag_group_x_data_tag', ['id', 'data_tag_group_id', 'data_tag']],
    'tag label' => ['DataTagInfo', 'wow_ref_data_tag_info', ['id', 'data_tag', 'description_lang']],
]);

test('the tag labels are read in English, the language of the curated taxonomy', function (): void {
    $dataTagInfo = (new ReferenceCatalog)->find('DataTagInfo');

    expect($dataTagInfo)->not->toBeNull()
        ->and($dataTagInfo->locale)->toBeNull();
});
