<?php

declare(strict_types=1);

namespace App\Application\Taxonomy;

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;

/**
 * Les catégories et les sources qu'une collection emploie réellement.
 *
 * Lues en base et non figées dans le code : ce vocabulaire est de la curation, il grossit
 * d'un patch à l'autre, et une liste codée en dur aurait divergé dès le premier arbitrage.
 * Elle sert à proposer l'existant à la saisie sans l'imposer — une valeur neuve reste
 * saisissable, c'est ainsi qu'une catégorie entre.
 */
final readonly class TaxonomyVocabulary
{
    /**
     * @return array{categories: list<string>, sources: list<string>}
     */
    public function forEntity(CollectionEntity $collectionEntity): array
    {
        return [
            'categories' => $this->distinct($collectionEntity, 'category'),
            'sources' => $this->distinct($collectionEntity, 'source'),
        ];
    }

    /**
     * @return list<string>
     */
    private function distinct(CollectionEntity $collectionEntity, string $column): array
    {
        /** @var list<string> $values */
        $values = WowCollectionTaxonomy::query()
            ->where('entity', $collectionEntity->value)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();

        return $values;
    }
}
