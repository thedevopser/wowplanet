<?php

declare(strict_types=1);

namespace App\Application\Taxonomy;

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les entrées de catalogue que la taxonomie range déjà, avec leur rangement actuel : c'est
 * là que l'écran d'arbitrage retrouve une entrée mal rangée pour la réaffecter.
 *
 * Le rangement affiché est celui de la taxonomie, qui fait foi, et non la copie qu'en porte
 * le catalogue. Une ligne de taxonomie dont l'entrée a quitté le catalogue n'est pas listée :
 * le site ne la montre plus, et l'arbitrage la refuserait.
 *
 * @phpstan-type CuratedEntry array{id: int, name: string, category: string|null, source: string|null}
 * @phpstan-type CategoryFilter array{value: string, category: string|null, entries: int}
 */
final readonly class CuratedTaxonomyEntries
{
    /**
     * Valeur du filtre qui vise les entrées rangées nulle part : une catégorie nulle ne
     * s'écrit pas dans une URL.
     */
    public const string UNCATEGORISED = '__none__';

    /**
     * @return list<CuratedEntry>
     */
    public function forEntity(CollectionEntity $collectionEntity, ?string $search = null, ?string $category = null): array
    {
        $builder = $collectionEntity->catalogue()
            ->whereIn('id', $this->rankings($collectionEntity, $category)->select('entry_id'))
            ->orderBy('name_fr')
            ->orderBy('id');

        CatalogueSearch::apply($builder, $search);

        $entries = $builder->get(['id', 'name_fr']);

        $rankings = $this->rankings($collectionEntity, $category)
            ->whereIn('entry_id', $entries->modelKeys())
            ->get(['entry_id', 'category', 'source'])
            ->keyBy('entry_id');

        return array_values($entries
            ->map(static function (WowMount|WowPet|WowDecor $model) use ($rankings): array {
                $ranking = $rankings->get($model->id);

                return [
                    'id' => $model->id,
                    'name' => $model->name_fr,
                    'category' => $ranking?->category,
                    'source' => $ranking?->source,
                ];
            })
            ->all());
    }

    /**
     * Les catégories que la collection emploie, avec leur effectif, les entrées rangées
     * nulle part en dernier : c'est la liste du filtre, qui permet d'ouvrir « Other » pour
     * voir tout ce qui y est rangé à tort.
     *
     * @return list<CategoryFilter>
     */
    public function categories(CollectionEntity $collectionEntity): array
    {
        $sizes = [];
        $uncategorised = 0;

        foreach ($this->rankings($collectionEntity)->get(['category']) as $wowCollectionTaxonomy) {
            if ($wowCollectionTaxonomy->category === null) {
                $uncategorised++;

                continue;
            }

            $sizes[$wowCollectionTaxonomy->category] = ($sizes[$wowCollectionTaxonomy->category] ?? 0) + 1;
        }

        ksort($sizes, SORT_STRING);

        $categories = [];
        foreach ($sizes as $category => $entries) {
            $category = (string) $category;
            $categories[] = ['value' => $category, 'category' => $category, 'entries' => $entries];
        }

        if ($uncategorised > 0) {
            $categories[] = ['value' => self::UNCATEGORISED, 'category' => null, 'entries' => $uncategorised];
        }

        return $categories;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (CollectionEntity::cases() as $collectionEntity) {
            $counts[$collectionEntity->value] = $this->rankings($collectionEntity)->count();
        }

        return $counts;
    }

    /**
     * Les lignes de taxonomie de la collection dont l'entrée est au catalogue, restreintes
     * à une catégorie quand le filtre en nomme une.
     *
     * @return Builder<WowCollectionTaxonomy>
     */
    private function rankings(CollectionEntity $collectionEntity, ?string $category = null): Builder
    {
        $builder = WowCollectionTaxonomy::query()
            ->where('entity', $collectionEntity->value)
            ->whereIn('entry_id', $collectionEntity->catalogue()->select('id'));

        return match ($category) {
            null => $builder,
            self::UNCATEGORISED => $builder->whereNull('category'),
            default => $builder->where('category', $category),
        };
    }
}
