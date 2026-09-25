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
 * Les entrées de catalogue que la taxonomie ne range pas encore.
 *
 * Rien n'est stocké : l'absence de ligne de taxonomie pour une ligne de catalogue *est* le
 * rapport. Une entrée rangée nulle part en connaissance de cause porte, elle, une ligne aux
 * deux libellés nuls — elle est curée, donc hors de cette liste. Confondre les deux ferait
 * revenir indéfiniment une entrée qu'on a déjà tranchée.
 *
 * La règle vit ici et non dans la commande de rapport, parce que trois appelants la
 * partagent : cette commande, l'écran d'arbitrage et le compteur du tableau de bord.
 */
final readonly class PendingTaxonomyEntries
{
    /**
     * @return list<array{id: int, name: string, pending_source: string|null}>
     */
    public function forEntity(CollectionEntity $collectionEntity, ?string $search = null): array
    {
        $builder = $this->catalogue($collectionEntity)
            ->whereNotIn('id', $this->curatedIds($collectionEntity))
            ->orderBy('id');

        $this->applySearch($builder, $search);

        return array_values($builder->get(['id', 'name_fr', 'source'])
            ->map(static fn (WowMount|WowPet|WowDecor $model): array => [
                'id' => $model->id,
                'name' => $model->name_fr,
                // La valeur d'attente que l'importer a posée depuis le type de source de
                // l'API : elle oriente l'arbitrage sans le décider.
                'pending_source' => $model->source,
            ])
            ->all());
    }

    /**
     * Ce qui reste à arbitrer et ce que le catalogue porte, collection par collection.
     *
     * @return array<string, array{pending: int, catalogue: int}>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (CollectionEntity::cases() as $collectionEntity) {
            $counts[$collectionEntity->value] = [
                'pending' => $this->catalogue($collectionEntity)
                    ->whereNotIn('id', $this->curatedIds($collectionEntity))
                    ->count(),
                'catalogue' => $this->catalogue($collectionEntity)->count(),
            ];
        }

        return $counts;
    }

    public function total(): int
    {
        return array_sum(array_column($this->counts(), 'pending'));
    }

    /**
     * @param  Builder<WowMount>|Builder<WowPet>|Builder<WowDecor>  $query
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        $term = trim((string) $search);

        if ($term === '') {
            return;
        }

        $query->where(static function (Builder $builder) use ($term): void {
            $builder->where('name_fr', 'ilike', '%'.$term.'%');

            if (ctype_digit($term)) {
                $builder->orWhere('id', (int) $term);
            }
        });
    }

    /**
     * @return Builder<WowCollectionTaxonomy>
     */
    private function curatedIds(CollectionEntity $collectionEntity): Builder
    {
        return WowCollectionTaxonomy::query()
            ->where('entity', $collectionEntity->value)
            ->select('entry_id');
    }

    /**
     * @return Builder<WowMount>|Builder<WowPet>|Builder<WowDecor>
     */
    private function catalogue(CollectionEntity $collectionEntity): Builder
    {
        return match ($collectionEntity) {
            CollectionEntity::Mount => WowMount::query(),
            CollectionEntity::Pet => WowPet::query(),
            CollectionEntity::Decor => WowDecor::query(),
        };
    }
}
