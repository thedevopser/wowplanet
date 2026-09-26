<?php

declare(strict_types=1);

use App\Application\Taxonomy\TaxonomyArbitration;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowMount;

/**
 * L'instantané de la taxonomie est un fichier versionné, et l'arbitrage du panneau le
 * réexporte à chaque écriture. Une suite de tests qui le réécrirait remplacerait des
 * milliers de lignes de curation par ses quelques lignes de doublure — c'est arrivé.
 *
 * La redirection vit dans `tests/Pest.php`, et ce test est ce qui la tient.
 */
test('the versioned snapshot is out of reach of the test suite', function (): void {
    expect(resolve(CollectionTaxonomySnapshot::class)->path())
        ->not->toBe(database_path('data/'.CollectionTaxonomySnapshot::FILENAME));
});

test('an arbitration leaves the snapshot versioned with the repository untouched', function (): void {
    $versioned = database_path('data/'.CollectionTaxonomySnapshot::FILENAME);
    $before = md5_file($versioned);

    WowCollectionTaxonomy::factory()->create();
    WowMount::factory()->create(['id' => 7]);
    resolve(TaxonomyArbitration::class)->arbitrate(CollectionEntity::Mount, [7], 'Racial', 'Human', 'test');

    expect(md5_file($versioned))->toBe($before);
});
