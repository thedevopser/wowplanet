<?php

declare(strict_types=1);

namespace App\Application\Reference;

use App\Application\Import\VolumeShrink;
use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Reference\ReferenceTable;
use App\Models\WowReferenceDownload;
use Illuminate\Support\Facades\DB;

/**
 * L'état du socle de référence, tel que le panneau d'administration le montre : ce que
 * chaque table pèse réellement, quand elle a été chargée et sous quel build, et l'écart
 * avec le chargement précédent.
 *
 * Les lignes sont comptées sur la table, pas relues de l'inventaire : ce dernier dit ce
 * qu'un chargement a écrit, la table dit ce qui reste. Les deux divergent dès qu'on
 * touche à la base autrement que par la commande de synchronisation, et c'est alors la
 * table qui a raison.
 *
 * Le build courant est passé en paramètre plutôt que relu ici : le lire coûte une requête
 * sortante vers wago, qui n'a pas sa place dans un service que le rendu d'une page
 * appelle.
 */
final readonly class ReferenceInventory
{
    public function __construct(private ReferenceCatalog $referenceCatalog) {}

    /**
     * @param  string|null  $liveBuild  Build servi par wago, `null` s'il n'a pas pu être lu
     * @return list<array{source: string, table: string, rows: int, build: string|null, loaded_at: string|null, previous_rows: int|null, delta: int|null, is_empty: bool, has_shrunk: bool, is_stale: bool}>
     */
    public function entries(?string $liveBuild): array
    {
        return array_map(
            fn (ReferenceTable $referenceTable): array => $this->entry($referenceTable, $liveBuild),
            $this->referenceCatalog->tables(),
        );
    }

    /**
     * @return array{source: string, table: string, rows: int, build: string|null, loaded_at: string|null, previous_rows: int|null, delta: int|null, is_empty: bool, has_shrunk: bool, is_stale: bool}
     */
    private function entry(ReferenceTable $referenceTable, ?string $liveBuild): array
    {
        $loads = $this->lastTwoLoads($referenceTable);
        $last = $loads[0] ?? null;
        $previous = $loads[1] ?? null;

        $rows = DB::table($referenceTable->table())->count();

        return [
            'source' => $referenceTable->source,
            'table' => $referenceTable->table(),
            'rows' => $rows,
            'build' => $last?->build,
            'loaded_at' => $last?->downloaded_at->toIso8601String(),
            'previous_rows' => $previous?->row_count,
            'delta' => $last instanceof WowReferenceDownload && $previous instanceof WowReferenceDownload
                ? $last->row_count - $previous->row_count
                : null,
            'is_empty' => $rows === 0,
            'has_shrunk' => $this->hasShrunk($last, $previous),
            // Un build courant illisible ne doit pas faire passer tout le socle pour
            // périmé : sans point de comparaison, on ne signale rien.
            'is_stale' => $liveBuild !== null && $last instanceof WowReferenceDownload && $last->build !== $liveBuild,
        ];
    }

    private function hasShrunk(?WowReferenceDownload $last, ?WowReferenceDownload $previous): bool
    {
        if (! $last instanceof WowReferenceDownload || ! $previous instanceof WowReferenceDownload) {
            return false;
        }

        return VolumeShrink::between($previous->row_count, $last->row_count);
    }

    /**
     * @return list<WowReferenceDownload>
     */
    private function lastTwoLoads(ReferenceTable $referenceTable): array
    {
        return array_values(
            WowReferenceDownload::query()
                ->where('source_table', $referenceTable->source)
                ->latest('downloaded_at')
                ->limit(2)
                ->get()
                ->all()
        );
    }
}
