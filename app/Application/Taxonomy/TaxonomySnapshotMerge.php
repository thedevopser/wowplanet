<?php

declare(strict_types=1);

namespace App\Application\Taxonomy;

use App\Application\Services\DatabaseQueryService;
use App\Infrastructure\Logging\AdminAudit;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyLoader;
use App\Infrastructure\Taxonomy\CollectionTaxonomyReader;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use Illuminate\Support\Facades\DB;

/**
 * Recharge en base, depuis le panneau, la curation versionnée des trois collections.
 *
 * C'est le remède d'une base en retard sur le fichier : une base neuve, ou une curation
 * commitée depuis le dernier import. La fusion est additive, comme au début des étapes
 * d'import : un arbitrage déjà en base n'est jamais réécrit.
 *
 * Les entrées ajoutées rangent aussitôt le catalogue, comme un arbitrage : un fichier
 * déployé prend effet au clic, sans import forcé. Celles déjà en base n'y sont pas recopiées,
 * la fusion n'ayant rien changé pour elles.
 */
final readonly class TaxonomySnapshotMerge
{
    public function __construct(
        private CollectionTaxonomyLoader $collectionTaxonomyLoader,
        private CollectionTaxonomyReader $collectionTaxonomyReader,
        private CatalogueRanking $catalogueRanking,
        private DatabaseQueryService $databaseQueryService,
        private AdminAudit $adminAudit,
    ) {}

    /**
     * @return array{inserted: int}
     */
    public function merge(string $actor): array
    {
        $inserted = DB::transaction(function (): int {
            $inserted = 0;

            foreach (CollectionEntity::cases() as $collectionEntity) {
                $known = $this->collectionTaxonomyReader->for($collectionEntity);

                try {
                    $inserted += $this->collectionTaxonomyLoader->load($collectionEntity)['inserted'];
                } catch (TaxonomySourceUnavailableException) {
                    continue;
                }

                $this->catalogueRanking->apply(
                    $collectionEntity,
                    array_diff_key($this->collectionTaxonomyReader->for($collectionEntity), $known),
                );
            }

            return $inserted;
        });

        $this->databaseQueryService->forgetSidebar();
        $this->adminAudit->record('Collection taxonomy loaded from the versioned snapshot', $actor, ['inserted' => $inserted]);

        return ['inserted' => $inserted];
    }
}
