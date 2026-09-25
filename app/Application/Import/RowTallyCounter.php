<?php

declare(strict_types=1);

namespace App\Application\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lit sur les tables du catalogue ce qu'une étape d'import y a écrit.
 *
 * Aucune étape n'écrit dans la table d'une autre, ce qui permet de dater les écritures
 * sans ambiguïté malgré des horodatages à la seconde : deux étapes qui se suivent dans
 * la même seconde ne se disputent jamais une ligne.
 */
final readonly class RowTallyCounter
{
    /**
     * @param  list<class-string<Model>>  $tables
     */
    public function count(array $tables): int
    {
        $total = 0;

        foreach ($tables as $table) {
            $total += $table::query()->count();
        }

        return $total;
    }

    /**
     * @param  list<class-string<Model>>  $tables
     * @param  int  $rowsBefore  Cardinalité relevée avant le début de l'étape
     */
    public function tally(array $tables, Carbon $since, int $rowsBefore): RowTally
    {
        $touched = 0;
        $created = 0;

        foreach ($tables as $table) {
            $touched += $table::query()->where('updated_at', '>=', $since)->count();
            $created += $table::query()->where('created_at', '>=', $since)->count();
        }

        return RowTally::fromCounts($touched, $created, $rowsBefore, $this->count($tables));
    }
}
