<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyLoader;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Charge en base la taxonomie des collections depuis l'instantané versionné du dépôt.
 *
 * Reconstruire la taxonomie de zéro ne demande ainsi ni réseau ni tiers : la curation est
 * notre donnée, enrichie par les arbitrages du panneau puis réexportée dans l'instantané.
 *
 * Le chargement est additif : aucune valeur déjà en base n'est réécrite et un arbitrage
 * manuel survit.
 *
 * Les collections demandées sont chargées dans une seule transaction : une source manquante
 * laisse la taxonomie exactement dans l'état où elle était, plutôt qu'à moitié chargée.
 */
#[Description('Charge la taxonomie curée des montures, mascottes et décorations')]
#[Signature('app:collection-taxonomy-sync
        {--entity= : Synchroniser une seule collection}')]
class CollectionTaxonomySyncCommand extends Command
{
    public function handle(CollectionTaxonomyLoader $collectionTaxonomyLoader): int
    {
        try {
            $entities = $this->entitiesToSync();

            /** @var list<array{entity: CollectionEntity, read: int, inserted: int, skipped: int}> $results */
            $results = DB::transaction(fn (): array => $this->loadAll($collectionTaxonomyLoader, $entities));
        } catch (InvalidArgumentException|TaxonomySourceUnavailableException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($results);

        return self::SUCCESS;
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
     * @return list<array{entity: CollectionEntity, read: int, inserted: int, skipped: int}>
     */
    private function loadAll(CollectionTaxonomyLoader $collectionTaxonomyLoader, array $entities): array
    {
        $results = [];

        foreach ($entities as $entity) {
            $results[] = ['entity' => $entity, ...$collectionTaxonomyLoader->load($entity)];
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
