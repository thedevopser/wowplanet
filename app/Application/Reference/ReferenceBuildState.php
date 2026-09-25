<?php

declare(strict_types=1);

namespace App\Application\Reference;

use App\Infrastructure\Reference\ReferenceCatalog;
use App\Models\WowReferenceDownload;

/**
 * Sur quel build le socle se trouve, et combien de ses tables sont restées en arrière.
 *
 * L'état se lit sur l'inventaire des téléchargements et non sur `wow_import_states` :
 * une synchronisation lancée depuis la page du socle ne passe pas par le pipeline
 * d'import et n'y écrit donc rien. Cette table-là ignorerait un socle pourtant à jour.
 *
 * Le build courant est passé en paramètre, comme pour `ReferenceInventory` : le lire
 * coûte une requête sortante, qui n'a pas sa place dans un service que le rendu d'une
 * page appelle.
 */
final readonly class ReferenceBuildState
{
    public function __construct(private ReferenceCatalog $referenceCatalog) {}

    /**
     * @param  string|null  $liveBuild  Build servi par wago, `null` s'il n'a pas pu être lu
     * @return array{build: string|null, loaded_at: string|null, tables_behind: int, tables_total: int}
     */
    public function current(?string $liveBuild): array
    {
        $sources = $this->referenceCatalog->sources();
        $lastLoads = $this->lastLoadPerSource();
        $latest = $this->mostRecent($lastLoads);

        return [
            'build' => $latest?->build,
            'loaded_at' => $latest?->downloaded_at->toIso8601String(),
            'tables_behind' => $this->countBehind($sources, $lastLoads, $liveBuild),
            'tables_total' => count($sources),
        ];
    }

    /**
     * @param  list<string>  $sources
     * @param  array<string, WowReferenceDownload>  $lastLoads
     */
    private function countBehind(array $sources, array $lastLoads, ?string $liveBuild): int
    {
        // Sans point de comparaison, on ne signale rien : un wago muet ne doit pas faire
        // passer tout le socle pour périmé.
        if ($liveBuild === null) {
            return 0;
        }

        $behind = 0;

        foreach ($sources as $source) {
            $last = $lastLoads[$source] ?? null;

            // Une table jamais chargée est en retard au même titre qu'une table restée
            // sur un build antérieur : dans les deux cas, le socle n'est pas à jour.
            if (! $last instanceof WowReferenceDownload || $last->build !== $liveBuild) {
                $behind++;
            }
        }

        return $behind;
    }

    /**
     * @return array<string, WowReferenceDownload>
     */
    private function lastLoadPerSource(): array
    {
        $lastLoads = [];

        foreach (WowReferenceDownload::query()->oldest('downloaded_at')->get() as $download) {
            $lastLoads[$download->source_table] = $download;
        }

        return $lastLoads;
    }

    /**
     * @param  array<string, WowReferenceDownload>  $lastLoads
     */
    private function mostRecent(array $lastLoads): ?WowReferenceDownload
    {
        $latest = null;

        foreach ($lastLoads as $lastLoad) {
            if (! $latest instanceof WowReferenceDownload || $lastLoad->downloaded_at->greaterThan($latest->downloaded_at)) {
                $latest = $lastLoad;
            }
        }

        return $latest;
    }
}
