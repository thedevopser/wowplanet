<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Models\WowImportState;

/**
 * Décide s'il y a lieu de réimporter une entité, en comparant le build servi par
 * l'API à celui du dernier import réussi.
 */
final class ImportBuildGate
{
    public function isUpToDate(string $entity, ?string $currentBuild): bool
    {
        if ($currentBuild === null) {
            return false;
        }

        return WowImportState::query()
            ->where('entity', $entity)
            ->where('build', $currentBuild)
            ->exists();
    }

    public function remember(string $entity, string $build, ?string $lastModified = null): void
    {
        WowImportState::query()->updateOrCreate(['entity' => $entity], ['build' => $build, 'last_modified' => $lastModified, 'imported_at' => \Illuminate\Support\Facades\Date::now()]);
    }

    public function lastModifiedFor(string $entity): ?string
    {
        $state = WowImportState::query()->where('entity', $entity)->first();

        return $state?->last_modified;
    }
}
