<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\Responses\AchievementCategoryDocument;

/**
 * Récupération de la hiérarchie complète des catégories de hauts faits.
 *
 * Cent soixante-dix catégories, donc cent soixante-dix appels, et c'est tout ou rien :
 * une catégorie manquante, c'est un pan entier du catalogue absent du lot, que le
 * balayage des lignes périmées supprimerait ensuite. Un échec rend donc `null` et
 * l'appelant abandonne l'import sans toucher au catalogue.
 *
 * Les catégories de guilde vivent dans une autre liste de l'index et ne sont pas
 * demandées : elles ne sont pas au catalogue, et y entrer serait une décision produit.
 */
final readonly class AchievementCategorySweep
{
    use ImportsFromBlizzardApi;

    private const INDEX_ENDPOINT = 'data/wow/achievement-category/index';

    private const CATEGORY_ENDPOINT = 'data/wow/achievement-category/';

    public function __construct(BlizzardApiClient $blizzardApiClient)
    {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    public function fetchTaxonomy(): ?AchievementTaxonomy
    {
        $categoryIds = $this->fetchCategoryIds();
        if ($categoryIds === []) {
            return null;
        }

        $endpoints = [];
        foreach ($categoryIds as $categoryId) {
            $endpoints[$categoryId] = self::CATEGORY_ENDPOINT.$categoryId;
        }

        $responses = $this->fetchBatchAsync($endpoints);

        $categories = [];
        foreach ($categoryIds as $categoryId) {
            $decoded = $responses[$categoryId] ?? null;
            if ($decoded === null) {
                $this->info(sprintf('  ERROR: category %d unavailable, aborting import (catalog left untouched).', $categoryId));

                return null;
            }

            $categories[] = AchievementCategoryDocument::fromPayload($decoded);
        }

        $achievementTaxonomy = AchievementTaxonomy::fromCategories($categories);

        $this->info(sprintf(
            '  %d categories read, %d achievements placed.',
            count($categories),
            count($achievementTaxonomy->placements()),
        ));

        return $achievementTaxonomy;
    }

    /**
     * @return list<int>
     */
    private function fetchCategoryIds(): array
    {
        $this->info('Fetching achievement category hierarchy from Blizzard API...');

        $decoded = $this->fetchWithRetry(self::INDEX_ENDPOINT);
        if (! $decoded instanceof \App\Infrastructure\Blizzard\Responses\ResponsePayload) {
            $this->info('  ERROR: category index unavailable, aborting import (catalog left untouched).');

            return [];
        }

        $categoryIds = [];
        foreach ($decoded->objectList('categories') as $responsePayload) {
            $categoryIds[] = $responsePayload->requiredInt('id');
        }

        if ($categoryIds === []) {
            $this->info('  ERROR: category index holds no category, aborting import (catalog left untouched).');
        }

        return $categoryIds;
    }
}
