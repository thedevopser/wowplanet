<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\WowImportState;
use Illuminate\Database\Eloquent\Model;

/**
 * L'état des entités importables, tel que le panneau d'administration le montre :
 * ce que chacune pèse en base, quand elle a été importée pour la dernière fois et
 * sous quel build, et l'ordre de grandeur de ce qu'un import forcé coûterait.
 *
 * Le socle de référence n'y figure pas : ce n'est pas une entité de catalogue mais une
 * dépendance des autres, pilotée depuis sa propre page.
 *
 * Aucun appel à l'API Blizzard ici. Comparer au build courant demanderait une requête
 * sortante à chaque ouverture de page, et c'est le sujet d'une autre story, avec son
 * propre cache.
 */
final readonly class ImportInventory
{
    /**
     * @return list<array{stage: string, label: string, rows: int, imported_at: string|null, build: string|null, estimated_api_calls: int}>
     */
    public function entries(): array
    {
        $states = WowImportState::query()->get()->keyBy('entity');

        return array_map(function (ImportStage $importStage) use ($states): array {
            /** @var WowImportState|null $state */
            $state = $states->get($importStage->value);

            return [
                'stage' => $importStage->value,
                'label' => $importStage->label(),
                'rows' => $this->rowsOf($importStage),
                'imported_at' => $state?->imported_at->toIso8601String(),
                'build' => $state?->build,
                'estimated_api_calls' => $importStage->estimatedApiCalls(),
            ];
        }, ImportStage::catalogue());
    }

    /**
     * Les métiers pèsent sur deux tables : leurs lignes s'additionnent, l'entité étant
     * ce que l'exploitant réimporte, pas la table.
     */
    public function rowsOf(ImportStage $importStage): int
    {
        $rows = 0;

        foreach ($importStage->tables() as $model) {
            /** @var Model $instance */
            $instance = new $model;
            $rows += $instance->newQuery()->count();
        }

        return $rows;
    }
}
