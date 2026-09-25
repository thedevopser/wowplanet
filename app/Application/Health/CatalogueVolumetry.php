<?php

declare(strict_types=1);

namespace App\Application\Health;

use App\Application\Import\ImportStage;
use App\Models\CharacterFavorite;
use App\Models\CharacterTask;
use App\Models\CharacterVisit;
use App\Models\CrossCharacterData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Ce que pèse chaque table, lu à la demande.
 *
 * Un `COUNT` direct, sans cache ni statistiques tenues à part : la plus grosse table
 * dépasse à peine 20 000 lignes, et un diagnostic doit lire l'état réel, pas celui
 * d'il y a une heure.
 */
final readonly class CatalogueVolumetry
{
    private const string ICON_COLUMN = 'icon_url';

    /**
     * @var list<class-string<Model>>
     */
    private const array APPLICATION_TABLES = [
        User::class,
        CharacterTask::class,
        CharacterFavorite::class,
        CharacterVisit::class,
        CrossCharacterData::class,
    ];

    /**
     * @return list<TableVolume>
     */
    public function volumes(): array
    {
        return [
            ...array_map($this->catalogueVolume(...), $this->catalogueModels()),
            ...array_map($this->applicationVolume(...), self::APPLICATION_TABLES),
        ];
    }

    /**
     * La liste des tables du catalogue suit celle des imports : une entité ajoutée à la
     * chaîne apparaît ici sans qu'on ait à y penser.
     *
     * @return list<class-string<Model>>
     */
    private function catalogueModels(): array
    {
        return array_merge(...array_map(
            static fn (ImportStage $importStage): array => $importStage->tables(),
            ImportStage::catalogue(),
        ));
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function catalogueVolume(string $model): TableVolume
    {
        $table = (new $model)->getTable();
        $hasIcon = Schema::hasColumn($table, self::ICON_COLUMN);

        $withoutIcon = $hasIcon
            ? sprintf(', count(*) filter (where %1$s is null or %1$s = \'\') as without_icon', self::ICON_COLUMN)
            : '';

        $row = (array) (new $model)->newQuery()
            ->toBase()
            ->selectRaw('count(*) as total, count(*) filter (where is_active) as active'.$withoutIcon)
            ->first();

        // PDO rend un agrégat PostgreSQL en entier ou en chaîne selon le pilote : rien
        // d'autre qu'un entier ne sort d'ici.
        $counts = [];
        foreach (['total', 'active', 'without_icon'] as $key) {
            $value = $row[$key] ?? 0;
            $counts[$key] = is_numeric($value)
                ? (int) $value
                : throw new \UnexpectedValueException(sprintf('The %s count of %s is not a number.', $key, $table));
        }

        return TableVolume::catalogue(
            $table,
            rows: $counts['total'],
            active: $counts['active'],
            withoutIcon: $hasIcon ? $counts['without_icon'] : null,
        );
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function applicationVolume(string $model): TableVolume
    {
        $instance = new $model;

        return TableVolume::application($instance->getTable(), $instance->newQuery()->count());
    }
}
