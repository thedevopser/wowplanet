<?php

declare(strict_types=1);

use App\Application\Taxonomy\TaxonomyArbitration;
use App\Application\Taxonomy\TaxonomySnapshotExporter;
use App\Infrastructure\Logging\AdminAudit;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

beforeEach(function (): void {
    $this->snapshotPath = testTempPath('taxonomy-arbitration').'/'.CollectionTaxonomySnapshot::FILENAME;
});

afterEach(function (): void {
    removeDirectory(dirname((string) $this->snapshotPath));
});

function arbitration(string $path): TaxonomyArbitration
{
    return new TaxonomyArbitration(new TaxonomySnapshotExporter(new CollectionTaxonomySnapshot($path)), resolve(AdminAudit::class));
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

test('it records who filed what, and where', function (): void {
    arbitrate($this->snapshotPath, [7, 8], 'Racial', 'Human', '4242');

    expect(auditTrail())->toBe([[
        'message' => 'Collection taxonomy arbitrated from the admin panel',
        'context' => ['entity' => 'mount', 'entries' => [7, 8], 'category' => 'Racial', 'source' => 'Human', 'actor' => '4242'],
    ]]);
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
