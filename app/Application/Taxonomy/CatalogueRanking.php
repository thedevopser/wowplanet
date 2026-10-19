<?php

declare(strict_types=1);

namespace App\Application\Taxonomy;

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\TaxonomyEntry;

/**
 * Recopie au catalogue un rangement que la taxonomie vient de recevoir, pour qu'il se voie
 * sans attendre que l'import de la collection le relise.
 *
 * Une décoration rangée perd sa suggestion : la garder la ferait reparaître si l'entrée
 * revenait un jour en attente, sous une proposition que personne n'a relue.
 */
final readonly class CatalogueRanking
{
    /**
     * Le rangement d'un arbitrage : catégorie et source, l'état obtenable restant celui que
     * la curation porte déjà.
     *
     * @param  list<int>  $entryIds
     */
    public function rank(CollectionEntity $collectionEntity, array $entryIds, ?string $category, ?string $source): void
    {
        $collectionEntity->catalogue()
            ->whereIn('id', $entryIds)
            ->update($this->columns($collectionEntity, ['category' => $category, 'source' => $source]));
    }

    /**
     * Le rangement complet d'entrées curées, état obtenable compris, regroupé par valeur
     * pour une mise à jour par rangement distinct plutôt qu'une par entrée.
     *
     * @param  array<int, TaxonomyEntry>  $entries
     */
    public function apply(CollectionEntity $collectionEntity, array $entries): void
    {
        $groups = [];

        foreach ($entries as $entryId => $taxonomyEntry) {
            $key = serialize([$taxonomyEntry->category, $taxonomyEntry->source, $taxonomyEntry->obtainable]);
            $groups[$key]['entry'] = $taxonomyEntry;
            $groups[$key]['ids'][] = $entryId;
        }

        foreach ($groups as $group) {
            $collectionEntity->catalogue()
                ->whereIn('id', $group['ids'])
                ->update($this->columns($collectionEntity, [
                    'category' => $group['entry']->category,
                    'source' => $group['entry']->source,
                    'is_active' => $group['entry']->obtainable,
                ]));
        }
    }

    /**
     * @param  array<string, string|bool|null>  $ranking
     * @return array<string, string|bool|null>
     */
    private function columns(CollectionEntity $collectionEntity, array $ranking): array
    {
        return $collectionEntity === CollectionEntity::Decor
            ? [...$ranking, 'suggested_category' => null]
            : $ranking;
    }
}
