<?php

declare(strict_types=1);

namespace App\Application\Taxonomy;

use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use Illuminate\Database\Eloquent\Builder;

/**
 * La recherche de l'écran d'arbitrage, la même sur les entrées en attente et sur les
 * entrées déjà rangées : le nom français, sans égard à la casse, et l'identifiant exact
 * quand le terme est un nombre.
 */
final class CatalogueSearch
{
    /**
     * @param  Builder<WowMount>|Builder<WowPet>|Builder<WowDecor>  $query
     */
    public static function apply(Builder $query, ?string $search): void
    {
        $term = trim((string) $search);

        if ($term === '') {
            return;
        }

        $table = $query->getModel()->getTable();

        $query->where(static function (Builder $builder) use ($table, $term): void {
            $builder->where($table.'.name_fr', 'ilike', '%'.$term.'%');

            if (ctype_digit($term)) {
                $builder->orWhere($table.'.id', (int) $term);
            }
        });
    }
}
