<?php

declare(strict_types=1);

use App\Application\Services\DatabaseQueryService;
use App\Application\Taxonomy\TaxonomyArbitration;
use App\Application\Taxonomy\TaxonomySnapshotExporter;
use App\Application\Taxonomy\UnknownCollectionEntryException;
use App\Infrastructure\Logging\AdminAudit;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

beforeEach(function (): void {
    $this->snapshotPath = testTempPath('taxonomy-arbitration').'/'.CollectionTaxonomySnapshot::FILENAME;

    foreach ([7, 8, 12] as $mountId) {
        WowMount::factory()->create(['id' => $mountId, 'category' => null, 'source' => 'Drop']);
    }
});

afterEach(function (): void {
    removeDirectory(dirname((string) $this->snapshotPath));
});

function arbitration(string $path): TaxonomyArbitration
{
    return new TaxonomyArbitration(
        new TaxonomySnapshotExporter(new CollectionTaxonomySnapshot($path)),
        resolve(AdminAudit::class),
        resolve(DatabaseQueryService::class),
    );
}

/**
 * @param  list<int>  $entryIds
 * @return array{arbitrated: int, snapshot: array{path: string, entries: int, in_step: bool}}
 */
function arbitrate(string $path, array $entryIds, ?string $category, ?string $source, string $actor = '12345'): array
{
    return arbitration($path)->arbitrate(CollectionEntity::Mount, $entryIds, $category, $source, $actor);
}

function taxonomyRow(int $entryId): ?WowCollectionTaxonomy
{
    return WowCollectionTaxonomy::query()
        ->where('entity', CollectionEntity::Mount->value)
        ->where('entry_id', $entryId)
        ->first();
}

test('it files a pending entry under the category and source it is given', function (): void {
    arbitrate($this->snapshotPath, [7], 'Racial', 'Human');

    expect(taxonomyRow(7)?->category)->toBe('Racial')
        ->and(taxonomyRow(7)?->source)->toBe('Human');
});

test('it files a whole selection in one go, which is how eleven hundred entries get ranked', function (): void {
    arbitrate($this->snapshotPath, [7, 8, 12], 'Racial', 'Human');

    expect(WowCollectionTaxonomy::query()->count())->toBe(3);
});

test('it reports how many entries it filed', function (): void {
    expect(arbitrate($this->snapshotPath, [7, 8], 'Racial', 'Human')['arbitrated'])->toBe(2);
});

/**
 * C'est le troisième état du modèle, et sans lui une entrée qui n'a vraiment aucune
 * catégorie resterait à arbitrer pour toujours.
 */
test('it can file an entry nowhere on purpose, which takes it off the pending list', function (): void {
    arbitrate($this->snapshotPath, [7], null, null);

    expect(taxonomyRow(7))->not->toBeNull()
        ->and(taxonomyRow(7)?->category)->toBeNull()
        ->and(taxonomyRow(7)?->source)->toBeNull();
});

test('a correction overwrites an entry that was already curated', function (): void {
    arbitrate($this->snapshotPath, [7], 'Racial', 'Human');
    arbitrate($this->snapshotPath, [7], 'Professions', 'Fishing');

    expect(taxonomyRow(7)?->category)->toBe('Professions')
        ->and(WowCollectionTaxonomy::query()->count())->toBe(1);
});

test('it files an entry as obtainable, since the panel ranks and never rules on existence', function (): void {
    arbitrate($this->snapshotPath, [7], 'Racial', 'Human');

    expect(taxonomyRow(7)?->obtainable)->toBeTrue();
});

test('a correction leaves the obtainable flag it found in place', function (): void {
    WowCollectionTaxonomy::query()->insert([
        'entity' => CollectionEntity::Mount->value,
        'entry_id' => 7,
        'category' => 'Racial',
        'source' => 'Human',
        'obtainable' => false,
    ]);

    arbitrate($this->snapshotPath, [7], 'Professions', 'Fishing');

    expect(taxonomyRow(7)?->obtainable)->toBeFalse();
});

test('blank labels are stored as filed nowhere, not as empty strings', function (): void {
    arbitrate($this->snapshotPath, [7], '  ', '');

    expect(taxonomyRow(7)?->category)->toBeNull()
        ->and(taxonomyRow(7)?->source)->toBeNull();
});

test('labels are trimmed, so a stray space never forks the vocabulary', function (): void {
    arbitrate($this->snapshotPath, [7], ' Racial ', ' Human ');

    expect(taxonomyRow(7)?->category)->toBe('Racial')
        ->and(taxonomyRow(7)?->source)->toBe('Human');
});

test('it exports the snapshot in the same breath, so the repository never falls behind', function (): void {
    arbitrate($this->snapshotPath, [7], 'Racial', 'Human');

    expect((new CollectionTaxonomySnapshot($this->snapshotPath))->entriesFor(CollectionEntity::Mount))
        ->toHaveKey(7);
});

test('it hands back the state of the snapshot, so the screen can ask for a commit', function (): void {
    $report = arbitrate($this->snapshotPath, [7], 'Racial', 'Human');

    expect($report['snapshot'])->toMatchArray([
        'path' => $this->snapshotPath,
        'entries' => 1,
        'in_step' => true,
    ]);
});

test('where the snapshot cannot be committed, an arbitration leaves the shipped file alone and shows what is left to download', function (): void {
    config(['services.taxonomy.export_after_arbitration' => false]);

    $report = arbitrate($this->snapshotPath, [7], 'Racial', 'Human');

    expect(is_file((string) $this->snapshotPath))->toBeFalse()
        ->and(taxonomyRow(7)?->category)->toBe('Racial')
        ->and($report['snapshot'])->toMatchArray(['in_step' => false, 'missing_in_file' => 1]);
});

test('it records who filed what, and how each entry was ranked before', function (): void {
    WowCollectionTaxonomy::query()->insert([
        'entity' => CollectionEntity::Mount->value, 'entry_id' => 8, 'category' => 'Other', 'source' => 'Drop', 'obtainable' => true,
    ]);

    arbitrate($this->snapshotPath, [7, 8], 'Racial', 'Human', '4242');

    expect(auditTrail())->toBe([[
        'message' => 'Collection taxonomy arbitrated from the admin panel',
        'context' => [
            'entity' => 'mount',
            'entries' => [7, 8],
            'category' => 'Racial',
            'source' => 'Human',
            'previous' => [
                ['entry_id' => 7, 'pending' => true, 'category' => null, 'source' => null],
                ['entry_id' => 8, 'pending' => false, 'category' => 'Other', 'source' => 'Drop'],
            ],
            'actor' => '4242',
        ],
    ]]);
});

test('it writes the new ranking on the catalogue row, so the site shows it without waiting for an import', function (): void {
    arbitrate($this->snapshotPath, [7, 8], 'Legion', 'Class Hall');

    expect(WowMount::query()->findOrFail(7)->only(['category', 'source']))->toBe(['category' => 'Legion', 'source' => 'Class Hall'])
        ->and(WowMount::query()->findOrFail(8)->category)->toBe('Legion')
        ->and(WowMount::query()->findOrFail(12)->category)->toBeNull();
});

test('it writes on the catalogue of the collection it arbitrates', function (CollectionEntity $collectionEntity, string $model): void {
    $model::factory()->create(['id' => 500, 'category' => null, 'source' => null]);

    arbitration($this->snapshotPath)->arbitrate($collectionEntity, [500], 'Legion', 'Drop', '12345');

    expect($model::query()->findOrFail(500)->only(['category', 'source']))->toBe(['category' => 'Legion', 'source' => 'Drop']);
})->with([
    'pets' => [CollectionEntity::Pet, WowPet::class],
    'decors' => [CollectionEntity::Decor, WowDecor::class],
]);

test('an entry missing from the collection catalogue is refused, and nothing is written', function (): void {
    try {
        arbitrate($this->snapshotPath, [7, 999, 998], 'Legion', 'Drop');
        $this->fail('The arbitration of an unknown entry went through.');
    } catch (UnknownCollectionEntryException $unknownCollectionEntryException) {
        expect($unknownCollectionEntryException->entryIds)->toBe([998, 999]);
    }

    expect(WowCollectionTaxonomy::query()->count())->toBe(0)
        ->and(WowMount::query()->findOrFail(7)->category)->toBeNull()
        ->and(auditTrail())->toBe([]);
});

test('an entry curated in the taxonomy but gone from the catalogue is refused too', function (): void {
    WowCollectionTaxonomy::query()->insert([
        'entity' => CollectionEntity::Mount->value, 'entry_id' => 999, 'category' => 'Other', 'source' => null, 'obtainable' => true,
    ]);

    expect(fn (): array => arbitrate($this->snapshotPath, [999], 'Legion', 'Drop'))
        ->toThrow(UnknownCollectionEntryException::class);

    expect(WowCollectionTaxonomy::query()->sole()->category)->toBe('Other');
});

test('an entry of another collection is refused, whatever the mounts catalogue holds', function (): void {
    expect(fn (): array => arbitration($this->snapshotPath)->arbitrate(CollectionEntity::Pet, [7], 'Legion', 'Drop', '12345'))
        ->toThrow(UnknownCollectionEntryException::class);
});

test('the database sidebar shows the new ranking right after the arbitration', function (): void {
    $databaseQueryService = resolve(DatabaseQueryService::class);
    expect($databaseQueryService->cachedSubcategories()['mounts'])->toBe([]);

    arbitrate($this->snapshotPath, [7, 8], 'Legion', 'Class Hall');

    expect($databaseQueryService->cachedSubcategories()['mounts'])->toBe([['name' => 'Legion', 'slug' => 'legion', 'count' => 2]]);
});

test('an empty selection changes nothing and is not worth a line in the audit trail', function (): void {
    expect(arbitrate($this->snapshotPath, [], 'Racial', 'Human')['arbitrated'])->toBe(0);

    expect(WowCollectionTaxonomy::query()->count())->toBe(0)
        ->and(auditTrail())->toBe([]);
});

/**
 * Le dépôt peut être monté en lecture seule. L'arbitrage est acquis en base, et c'est
 * l'écran qui doit porter l'information : l'instantané a dérivé.
 */
test('an export that cannot write keeps the arbitration and says the snapshot drifted', function (): void {
    config(['logging.default' => 'captured', 'logging.channels.captured' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);

    // Le chemin est rendu impossible et non interdit : un `chmod` ne barre pas la route à
    // root, sous lequel la mesure de couverture exécute la suite dans le conteneur.
    $directory = dirname((string) $this->snapshotPath);
    file_put_contents($directory, 'ceci est un fichier, pas un répertoire');

    $report = arbitrate($this->snapshotPath, [7], 'Racial', 'Human');

    expect(taxonomyRow(7)?->category)->toBe('Racial')
        ->and($report['snapshot']['in_step'])->toBeFalse();

    $handler = Log::channel('captured')->getLogger()->getHandlers()[0];
    expect($handler instanceof TestHandler && $handler->hasWarningThatContains('Collection taxonomy snapshot could not be exported'))->toBeTrue();

    unlink($directory);
});
