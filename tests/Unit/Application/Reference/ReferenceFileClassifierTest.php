<?php

declare(strict_types=1);

use App\Application\Reference\ReferenceFileClassifier;
use App\Application\Reference\ReferenceFileState;

/**
 * @param  array<string, int>  $sizes
 * @param  list<array{filename: string, source_table: string, build: string, loaded_at: int}>  $inventory
 * @return array{files: list<array{filename: string, state: string, bytes: int, source_table: string|null, build: string|null, loaded_at: int|null}>, missing: list<array{filename: string, source_table: string, build: string, loaded_at: int}>}
 */
function classify(array $sizes, array $inventory = [], array $taxonomy = ['mounts.json']): array
{
    return (new ReferenceFileClassifier)->classify($sizes, $inventory, $taxonomy);
}

/**
 * @return array{filename: string, source_table: string, build: string, loaded_at: int}
 */
function load(string $filename, string $source, string $build, int $at): array
{
    return ['filename' => $filename, 'source_table' => $source, 'build' => $build, 'loaded_at' => $at];
}

/**
 * Trié par nom, pour que ces assertions portent sur le classement et non sur l'ordre
 * d'affichage, qui a son propre test.
 *
 * @param  array{files: list<array{filename: string, state: string, bytes: int, source_table: string|null, build: string|null, loaded_at: int|null}>, missing: list<array{filename: string, source_table: string, build: string, loaded_at: int}>}  $contents
 * @return array<string, string>
 */
function statesByFilename(array $contents): array
{
    $states = [];

    foreach ($contents['files'] as $file) {
        $states[$file['filename']] = $file['state'];
    }

    ksort($states);

    return $states;
}

/**
 * @param  array{files: list<array{filename: string, state: string, bytes: int, source_table: string|null, build: string|null, loaded_at: int|null}>, missing: list<array{filename: string, source_table: string, build: string, loaded_at: int}>}  $contents
 * @return list<string>
 */
function filenamesInOrder(array $contents): array
{
    return array_column($contents['files'], 'filename');
}

test('the newest load of a table is the file in service', function (): void {
    $contents = classify(
        ['faction-69875.csv' => 10, 'faction-69814.csv' => 20],
        [
            load('faction-69814.csv', 'Faction', '69814', 100),
            load('faction-69875.csv', 'Faction', '69875', 200),
        ],
    );

    expect(statesByFilename($contents))->toBe([
        'faction-69814.csv' => ReferenceFileState::Obsolete->value,
        'faction-69875.csv' => ReferenceFileState::Live->value,
    ]);
});

test('each table keeps its own file in service, they are not compared across tables', function (): void {
    $contents = classify(
        ['faction-69875.csv' => 10, 'mount-69814.csv' => 20],
        [
            load('mount-69814.csv', 'Mount', '69814', 100),
            load('faction-69875.csv', 'Faction', '69875', 200),
        ],
    );

    expect(statesByFilename($contents))->toBe([
        'faction-69875.csv' => ReferenceFileState::Live->value,
        'mount-69814.csv' => ReferenceFileState::Live->value,
    ]);
});

test('a single load of a table is that table file in service', function (): void {
    $contents = classify(
        ['faction-69875.csv' => 10],
        [load('faction-69875.csv', 'Faction', '69875', 200)],
    );

    expect(statesByFilename($contents))->toBe(['faction-69875.csv' => ReferenceFileState::Live->value]);
});

test('a file the inventory does not know is an orphan', function (): void {
    $contents = classify(['spell_name.csv' => 4_096]);

    expect(statesByFilename($contents))->toBe(['spell_name.csv' => ReferenceFileState::Orphan->value]);
});

test('an upstream taxonomy snapshot is recognised, never taken for an orphan', function (): void {
    $contents = classify(
        ['mounts.json' => 390_055, 'pets.json' => 393_280],
        [],
        ['mounts.json', 'pets.json', 'decors.json'],
    );

    expect(statesByFilename($contents))->toBe([
        'mounts.json' => ReferenceFileState::Taxonomy->value,
        'pets.json' => ReferenceFileState::Taxonomy->value,
    ]);
});

test('a taxonomy snapshot that has not been pulled yet is simply absent', function (): void {
    $contents = classify([], [], ['mounts.json', 'pets.json', 'decors.json']);

    expect($contents['files'])->toBe([])
        ->and($contents['missing'])->toBe([]);
});

test('an inventory row whose file has vanished is reported apart, not as a file', function (): void {
    $vanished = load('faction-69587.csv', 'Faction', '69587', 50);

    $contents = classify(
        ['faction-69875.csv' => 10],
        [$vanished, load('faction-69875.csv', 'Faction', '69875', 200)],
    );

    expect($contents['missing'])->toBe([$vanished])
        ->and(statesByFilename($contents))->toBe(['faction-69875.csv' => ReferenceFileState::Live->value]);
});

test('a vanished file still counts as a load, so it can hold the service slot', function (): void {
    $contents = classify(
        ['faction-69814.csv' => 20],
        [
            load('faction-69814.csv', 'Faction', '69814', 100),
            load('faction-69875.csv', 'Faction', '69875', 200),
        ],
    );

    expect(statesByFilename($contents))->toBe(['faction-69814.csv' => ReferenceFileState::Obsolete->value]);
});

test('two loads of one table recorded in the same second are departed by build, deterministically', function (): void {
    $contents = classify(
        ['faction-69814.csv' => 20, 'faction-69875.csv' => 10],
        [
            load('faction-69814.csv', 'Faction', '69814', 100),
            load('faction-69875.csv', 'Faction', '69875', 100),
        ],
    );

    expect(statesByFilename($contents))->toBe([
        'faction-69814.csv' => ReferenceFileState::Obsolete->value,
        'faction-69875.csv' => ReferenceFileState::Live->value,
    ]);
});

test('a file carries its size and, when it has one, its load', function (): void {
    $contents = classify(
        ['faction-69875.csv' => 157_683],
        [load('faction-69875.csv', 'Faction', '69875', 200)],
    );

    expect($contents['files'][0])->toBe([
        'filename' => 'faction-69875.csv',
        'state' => ReferenceFileState::Live->value,
        'bytes' => 157_683,
        'source_table' => 'Faction',
        'build' => '69875',
        'loaded_at' => 200,
    ]);
});

test('a file with no load carries no table and no build', function (): void {
    $contents = classify(['spell_name.csv' => 4_096]);

    expect($contents['files'][0])->toBe([
        'filename' => 'spell_name.csv',
        'state' => ReferenceFileState::Orphan->value,
        'bytes' => 4_096,
        'source_table' => null,
        'build' => null,
        'loaded_at' => null,
    ]);
});

test('an empty store classifies nothing rather than failing', function (): void {
    expect(classify([]))->toBe(['files' => [], 'missing' => []]);
});

test('files come out in a stable order, heaviest first', function (): void {
    $contents = classify([
        'small.csv' => 10,
        'huge.csv' => 45_000_000,
        'medium.csv' => 1_000,
    ]);

    expect(filenamesInOrder($contents))->toBe(['huge.csv', 'medium.csv', 'small.csv']);
});
