<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceStore;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ReferenceStore::DISK);
});

test('it lists what the store actually holds', function (): void {
    Storage::disk(ReferenceStore::DISK)->put('faction-12.1.0.69875.csv', 'a');
    Storage::disk(ReferenceStore::DISK)->put('mounts.json', 'bb');

    expect((new ReferenceStore)->sizes())->toBe([
        'faction-12.1.0.69875.csv' => 1,
        'mounts.json' => 2,
    ]);
});

/**
 * C'est l'état d'une installation neuve : la page de purge doit s'y rendre, pas échouer.
 * Le disque `reference` lève au lieu de rendre `false`, donc ce cas se vérifie.
 */
test('an absent store directory lists nothing rather than failing', function (): void {
    removeDirectory(Storage::disk(ReferenceStore::DISK)->path(''));

    expect((new ReferenceStore)->sizes())->toBe([]);
});

test('it reads a size from the filesystem, never from the contents', function (): void {
    Storage::disk(ReferenceStore::DISK)->put('spell_misc-12.1.0.69875.csv', str_repeat('x', 4_096));

    expect((new ReferenceStore)->sizes()['spell_misc-12.1.0.69875.csv'])->toBe(4_096);
});

test('it deletes a file it is given', function (): void {
    Storage::disk(ReferenceStore::DISK)->put('faction-12.1.0.69587.csv', 'a');

    (new ReferenceStore)->delete('faction-12.1.0.69587.csv');

    Storage::disk(ReferenceStore::DISK)->assertMissing('faction-12.1.0.69587.csv');
});

/**
 * Deux onglets qui purgent la même sélection, ou un fichier effacé à la main entre
 * l'affichage et la confirmation : la seconde suppression ne doit pas faire échouer la
 * purge, le résultat voulu étant déjà acquis.
 */
test('deleting a file that has already gone is not an error', function (): void {
    expect(fn (): null => (new ReferenceStore)->delete('never-there.csv'))->not->toThrow(Throwable::class);
});

test('it ignores anything nested below the store root', function (): void {
    Storage::disk(ReferenceStore::DISK)->put('archive/faction-12.1.0.69587.csv', 'a');
    Storage::disk(ReferenceStore::DISK)->put('faction-12.1.0.69875.csv', 'b');

    expect((new ReferenceStore)->sizes())->toBe(['faction-12.1.0.69875.csv' => 1]);
});
