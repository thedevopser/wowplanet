<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyLoader;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomyUpstreamUnreachableException;
use App\Infrastructure\Taxonomy\SimpleArmoryClient;
use App\Infrastructure\Taxonomy\SimpleArmoryTaxonomyReader;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Charge en base la taxonomie des collections, et l'enrichit de la curation d'un nouveau patch.
 *
 * Deux chemins, parce que les deux besoins sont distincts. Par défaut la commande lit
 * l'instantané versionné du dépôt : reconstruire la taxonomie de zéro ne demande alors ni
 * réseau ni tiers. Avec `--upstream` elle va chercher la curation fraîche chez SimpleArmory,
 * seul amont à ranger une monture ou une décoration, puis réexporte l'instantané pour que le
 * dépôt garde la trace de ce que le patch a apporté.
 *
 * Le chargement étant additif dans les deux cas, aucune valeur déjà en base n'est réécrite et
 * un arbitrage manuel survit. `--upstream` refuse une taxonomie vide : c'est un chemin
 * d'enrichissement, et l'appliquer à une base nue ferait entrer une curation que l'instantané
 * contredit délibérément sur plusieurs centaines d'entrées.
 *
 * Les collections demandées sont chargées dans une seule transaction : une source manquante
 * laisse la taxonomie exactement dans l'état où elle était, plutôt qu'à moitié chargée.
 */
class CollectionTaxonomySyncCommand extends Command
{
    protected $signature = 'app:collection-taxonomy-sync
        {--entity= : Synchroniser une seule collection}
        {--upstream : Tirer la curation fraîche de SimpleArmory au lieu de lire l\'instantané}';

    protected $description = 'Charge la taxonomie curée des montures, mascottes et décorations';

    public function handle(
        CollectionTaxonomyLoader $collectionTaxonomyLoader,
        SimpleArmoryClient $simpleArmoryClient,
        SimpleArmoryTaxonomyReader $simpleArmoryTaxonomyReader,
    ): int {
        $upstream = (bool) $this->option('upstream');

        try {
            $entities = $this->entitiesToSync();

            if ($upstream && ! $this->assertSeededTaxonomy()) {
                return self::FAILURE;
            }

            /** @var list<array{entity: CollectionEntity, read: int, inserted: int, skipped: int}> $results */
            $results = DB::transaction(fn (): array => $this->loadAll(
                $collectionTaxonomyLoader,
                $entities,
                $upstream ? fn (CollectionEntity $collectionEntity): array => $this->pullUpstream(
                    $simpleArmoryClient,
                    $simpleArmoryTaxonomyReader,
                    $collectionEntity,
                ) : null,
            ));
        } catch (InvalidArgumentException|TaxonomySourceUnavailableException|TaxonomyUpstreamUnreachableException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($results);

        if ($upstream) {
            $this->newLine();
            $this->call('app:collection-taxonomy-export');
        }

        return self::SUCCESS;
    }

    private function assertSeededTaxonomy(): bool
    {
        if (WowCollectionTaxonomy::query()->exists()) {
            return true;
        }

        $this->error(
            'Taxonomie vide : amorcez-la depuis l\'instantané avant de tirer un patch de l\'amont curé.'
        );

        return false;
    }

    /**
     * @return array<int, \App\Infrastructure\Taxonomy\TaxonomyEntry>
     */
    private function pullUpstream(
        SimpleArmoryClient $simpleArmoryClient,
        SimpleArmoryTaxonomyReader $simpleArmoryTaxonomyReader,
        CollectionEntity $collectionEntity,
    ): array {
        $simpleArmoryClient->fetch($collectionEntity);

        return $simpleArmoryTaxonomyReader->entriesFor($collectionEntity);
    }

    /**
     * @return list<CollectionEntity>
     */
    private function entitiesToSync(): array
    {
        /** @var string|null $requested */
        $requested = $this->option('entity');

        if ($requested === null) {
            return CollectionEntity::cases();
        }

        return [CollectionEntity::fromOption($requested)];
    }

    /**
     * @param  list<CollectionEntity>  $entities
     * @param  (callable(CollectionEntity): array<int, \App\Infrastructure\Taxonomy\TaxonomyEntry>)|null  $upstream
     * @return list<array{entity: CollectionEntity, read: int, inserted: int, skipped: int}>
     */
    private function loadAll(CollectionTaxonomyLoader $collectionTaxonomyLoader, array $entities, ?callable $upstream): array
    {
        $results = [];

        foreach ($entities as $entity) {
            $result = $upstream === null
                ? $collectionTaxonomyLoader->load($entity)
                : $collectionTaxonomyLoader->loadEntries($entity, $upstream($entity));

            $results[] = ['entity' => $entity, ...$result];
        }

        return $results;
    }

    /**
     * @param  list<array{entity: CollectionEntity, read: int, inserted: int, skipped: int}>  $results
     */
    private function report(array $results): void
    {
        $this->info('Taxonomie des collections');
        $this->newLine();

        foreach ($results as $result) {
            $total = WowCollectionTaxonomy::query()->where('entity', $result['entity'])->count();

            $this->line(sprintf(
                '  %-8s %6d curées   %6d en base   %s',
                $result['entity']->value,
                $result['read'],
                $total,
                $result['inserted'] === 0 ? '(=)' : sprintf('(%+d)', $result['inserted']),
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Taxonomie chargée : %d collection(s), %d entrée(s) ajoutée(s).',
            count($results),
            array_sum(array_column($results, 'inserted')),
        ));
    }
}
