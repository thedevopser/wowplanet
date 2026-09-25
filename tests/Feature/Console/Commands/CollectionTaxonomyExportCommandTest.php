<?php

declare(strict_types=1);

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->exportPath = sys_get_temp_dir().'/pest-taxonomy-export-'.uniqid().'/collection_taxonomy.csv';
});

afterEach(function (): void {
    if (file_exists($this->exportPath)) {
        unlink($this->exportPath);
        rmdir(dirname($this->exportPath));
    }
});

function exportTaxonomy(string $path): string
{
    Artisan::call('app:collection-taxonomy-export', ['--path' => $path]);

    return Artisan::output();
}

test('it exports every taxonomy row to the snapshot', function (): void {
    WowCollectionTaxonomy::query()->insert([
        ['entity' => CollectionEntity::Mount->value, 'entry_id' => 6, 'category' => 'Classic', 'source' => 'Vendeur', 'obtainable' => true],
        ['entity' => CollectionEntity::Pet->value, 'entry_id' => 39, 'category' => null, 'source' => null, 'obtainable' => true],
        ['entity' => CollectionEntity::Decor->value, 'entry_id' => 2113, 'category' => 'Logis', 'source' => 'Artisanat', 'obtainable' => false],
    ]);

    exportTaxonomy($this->exportPath);

    expect(file_get_contents($this->exportPath))->toBe(<<<'CSV'
        entity,entry_id,category,source,obtainable
        mount,6,Classic,Vendeur,true
        pet,39,,,true
        decor,2113,Logis,Artisanat,false

        CSV);
});

test('it reports the written count per entity', function (): void {
    WowCollectionTaxonomy::factory()->count(3)->create(['entity' => CollectionEntity::Mount]);
    WowCollectionTaxonomy::factory()->count(2)->create(['entity' => CollectionEntity::Decor]);

    $output = exportTaxonomy($this->exportPath);

    expect($output)->toContain('mount')
        ->and($output)->toContain('3')
        ->and($output)->toContain('decor')
        ->and($output)->toContain('5 entrée(s)');
});

test('it names the file it wrote', function (): void {
    WowCollectionTaxonomy::factory()->create();

    expect(exportTaxonomy($this->exportPath))->toContain($this->exportPath);
});

test('it refuses to write an empty snapshot over a curated one', function (): void {
    $exitCode = Artisan::call('app:collection-taxonomy-export', ['--path' => $this->exportPath]);

    expect($exitCode)->toBe(Symfony\Component\Console\Command\Command::FAILURE)
        ->and(file_exists($this->exportPath))->toBeFalse()
        ->and(Artisan::output())->toContain('vide');
});

/**
 * Vérifié sur le chemin et non en lançant la commande : sans `--path`, elle écrirait dans
 * le fichier versionné du dépôt, et une suite de tests ne salit jamais le répertoire de
 * travail. C'est précisément ce que faisait ce test, avec la seule ligne de sa doublure.
 */
test('without an explicit path it targets the snapshot versioned with the repository', function (): void {
    expect((new CollectionTaxonomySnapshot)->path())->toBe(database_path('data/collection_taxonomy.csv'));
});
