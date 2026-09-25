<?php

declare(strict_types=1);

namespace App\Infrastructure\Mappings;

use Illuminate\Support\Facades\File;

/**
 * Map zone→extension figée dans le dépôt (database/data/area_expansion_map.json).
 *
 * Générée une fois depuis les DB2 du build live (AreaTable + Map + ContentTuning,
 * overrides manuels inclus) : l'API Blizzard n'expose pas l'extension des zones,
 * ce référentiel remplace la dépendance aux CSV wago.tools.
 */
final class FrozenAreaExpansionMap
{
    /**
     * @return array<int, int> [area_id => expansion_id]
     */
    public static function load(): array
    {
        $path = database_path('data/area_expansion_map.json');
        if (! File::exists($path)) {
            return [];
        }

        /** @var array<int|string, int> $decoded */
        $decoded = json_decode(File::get($path), true) ?: [];

        $map = [];
        foreach ($decoded as $areaId => $expansionId) {
            $map[(int) $areaId] = (int) $expansionId;
        }

        return $map;
    }
}
