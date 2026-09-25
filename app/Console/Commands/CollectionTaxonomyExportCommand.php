<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Taxonomy\TaxonomySnapshotExporter;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Console\Command;

/**
 * Écrit l'instantané versionné de la taxonomie depuis la base.
 *
 * C'est le pendant de `app:collection-taxonomy-sync` : celle-ci charge l'instantané en base,
 * celle-là le regénère. On l'exécute après un arbitrage manuel ou un tirage amont, et on commite
 * le fichier produit — sans quoi la curation ne vit que dans une base et se perd au prochain
 * environnement.
 *
 * Elle refuse d'écrire un instantané vide : une table vide au moment de l'export écraserait
 * silencieusement le fichier curé du dépôt, ce qui est exactement le geste qu'on ne veut pas.
 */
class CollectionTaxonomyExportCommand extends Command
{
    protected $signature = 'app:collection-taxonomy-export
        {--path= : Écrire ailleurs que dans l\'instantané du dépôt}';

    protected $description = 'Exporte la taxonomie des collections vers son instantané versionné';

    public function handle(CollectionTaxonomySnapshot $collectionTaxonomySnapshot): int
    {
        /** @var string|null $requestedPath */
        $requestedPath = $this->option('path');

        $snapshot = $requestedPath === null ? $collectionTaxonomySnapshot : new CollectionTaxonomySnapshot($requestedPath);

        try {
            $report = (new TaxonomySnapshotExporter($snapshot))->export();
        } catch (\RuntimeException $runtimeException) {
            $this->error($runtimeException->getMessage());

            return self::FAILURE;
        }

        $this->report($report['written'], $report['path']);

        return self::SUCCESS;
    }

    private function report(int $written, string $path): void
    {
        $this->info('Instantané de la taxonomie');
        $this->newLine();

        foreach (CollectionEntity::cases() as $collectionEntity) {
            $this->line(sprintf(
                '  %-8s %6d entrée(s)',
                $collectionEntity->value,
                WowCollectionTaxonomy::query()->where('entity', $collectionEntity->value)->count(),
            ));
        }

        $this->newLine();
        $this->info(sprintf('%s écrit : %d entrée(s).', $path, $written));
    }
}
