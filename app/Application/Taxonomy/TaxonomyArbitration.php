<?php

declare(strict_types=1);

namespace App\Application\Taxonomy;

use App\Application\Services\DatabaseQueryService;
use App\Infrastructure\Logging\AdminAudit;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Range depuis le panneau les entrées que la taxonomie ne connaissait pas, et remet
 * l'instantané versionné en phase dans la foulée.
 *
 * L'export immédiat est la réponse au seul vrai piège de cet écran : un arbitrage n'existe
 * qu'en base, et un environnement reconstruit depuis le dépôt le perdrait. En exportant
 * dans la même transaction de pensée que l'écriture, la dérive est nulle par construction
 * et il ne reste qu'un geste humain, le commit du fichier.
 *
 * Le rangement est recopié dans la même transaction sur la ligne du catalogue que le site
 * lit, et le cache de la barre latérale est vidé : la correction se voit tout de suite, au
 * lieu d'attendre le prochain import. Cet import la conserve, puisqu'il relit la taxonomie.
 *
 * L'écriture passe par le constructeur de requêtes : la clé primaire de
 * {@see WowCollectionTaxonomy} est composite, et un `save()` sur une instance chargée ne
 * saurait pas la retrouver.
 *
 * @phpstan-type PreviousRanking array{entry_id: int, pending: bool, category: string|null, source: string|null}
 */
final readonly class TaxonomyArbitration
{
    public function __construct(
        private TaxonomySnapshotExporter $taxonomySnapshotExporter,
        private AdminAudit $adminAudit,
        private DatabaseQueryService $databaseQueryService,
    ) {}

    /**
     * @param  list<int>  $entryIds
     * @return array{arbitrated: int, snapshot: array{path: string, entries: int, in_step: bool, missing_in_base: int, missing_in_file: int, differing: int}}
     */
    public function arbitrate(
        CollectionEntity $collectionEntity,
        array $entryIds,
        ?string $category,
        ?string $source,
        string $actor,
    ): array {
        if ($entryIds === []) {
            return ['arbitrated' => 0, 'snapshot' => $this->taxonomySnapshotExporter->state()];
        }

        $category = $this->label($category);
        $source = $this->label($source);

        $previous = DB::transaction(function () use ($collectionEntity, $entryIds, $category, $source): array {
            $this->guardCatalogue($collectionEntity, $entryIds);
            $previous = $this->previousRankings($collectionEntity, $entryIds);

            foreach ($entryIds as $entryId) {
                $this->file($collectionEntity, $entryId, $category, $source);
            }

            $collectionEntity->catalogue()
                ->whereIn('id', $entryIds)
                ->update(['category' => $category, 'source' => $source]);

            return $previous;
        });

        $this->databaseQueryService->forgetSidebar();
        $this->audit($collectionEntity, $entryIds, $category, $source, $previous, $actor);

        return [
            'arbitrated' => count($entryIds),
            'snapshot' => $this->export(),
        ];
    }

    /**
     * @param  list<int>  $entryIds
     */
    private function guardCatalogue(CollectionEntity $collectionEntity, array $entryIds): void
    {
        /** @var list<int> $known */
        $known = $collectionEntity->catalogue()->whereIn('id', $entryIds)->pluck('id')->all();
        $unknown = array_values(array_diff($entryIds, $known));

        if ($unknown !== []) {
            sort($unknown);

            throw UnknownCollectionEntryException::in($collectionEntity, $unknown);
        }
    }

    /**
     * Le rangement d'avant, pour que l'audit dise ce qu'une correction a défait. Une entrée
     * en attente n'en avait aucun, ce qui la distingue d'une entrée rangée nulle part.
     *
     * @param  list<int>  $entryIds
     * @return list<PreviousRanking>
     */
    private function previousRankings(CollectionEntity $collectionEntity, array $entryIds): array
    {
        $curated = WowCollectionTaxonomy::query()
            ->where('entity', $collectionEntity->value)
            ->whereIn('entry_id', $entryIds)
            ->get(['entry_id', 'category', 'source'])
            ->keyBy('entry_id');

        return array_map(static function (int $entryId) use ($curated): array {
            $row = $curated->get($entryId);

            return [
                'entry_id' => $entryId,
                'pending' => ! $row instanceof WowCollectionTaxonomy,
                'category' => $row?->category,
                'source' => $row?->source,
            ];
        }, $entryIds);
    }

    /**
     * Une entrée déjà curée est corrigée, une entrée inconnue est créée, et le drapeau
     * d'obtention trouvé en place n'est jamais touché : le panneau range, il ne statue pas
     * sur l'existence d'une monture.
     */
    private function file(CollectionEntity $collectionEntity, int $entryId, ?string $category, ?string $source): void
    {
        $key = ['entity' => $collectionEntity->value, 'entry_id' => $entryId];

        $updated = WowCollectionTaxonomy::query()
            ->where($key)
            ->update(['category' => $category, 'source' => $source]);

        if ($updated === 0) {
            WowCollectionTaxonomy::query()->insert([
                ...$key,
                'category' => $category,
                'source' => $source,
                'obtainable' => true,
            ]);
        }
    }

    /**
     * Un export qui échoue ne doit pas défaire un arbitrage acquis, ni faire tomber la
     * requête : l'écran dira que l'instantané a dérivé, ce qui est l'information utile et
     * ce qui reste vrai. Le cas se produit dès que le fichier n'est pas inscriptible, un
     * dépôt monté en lecture seule par exemple.
     *
     * Là où le fichier ne peut pas être commité — en production, où il vit dans l'image —,
     * l'export n'a pas lieu : le fichier embarqué reste la version commitée, et la dérive
     * dit exactement ce qu'il reste à télécharger pour le verser au dépôt.
     *
     * @return array{path: string, entries: int, in_step: bool, missing_in_base: int, missing_in_file: int, differing: int}
     */
    private function export(): array
    {
        if (config('services.taxonomy.export_after_arbitration') !== true) {
            return $this->taxonomySnapshotExporter->state();
        }

        try {
            $this->taxonomySnapshotExporter->export();
        } catch (\Throwable $throwable) {
            Log::warning('Collection taxonomy snapshot could not be exported', [
                'path' => $this->taxonomySnapshotExporter->state()['path'],
                'reason' => $throwable->getMessage(),
            ]);
        }

        return $this->taxonomySnapshotExporter->state();
    }

    /**
     * Un libellé vide est un rangement nul, pas une chaîne vide : c'est la distinction que
     * l'instantané et les importers lisent.
     */
    private function label(?string $raw): ?string
    {
        $trimmed = trim((string) $raw);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  list<int>  $entryIds
     * @param  list<PreviousRanking>  $previous
     */
    private function audit(CollectionEntity $collectionEntity, array $entryIds, ?string $category, ?string $source, array $previous, string $actor): void
    {
        $this->adminAudit->record('Collection taxonomy arbitrated from the admin panel', $actor, [
            'entity' => $collectionEntity->value,
            'entries' => $entryIds,
            'category' => $category,
            'source' => $source,
            'previous' => $previous,
        ]);
    }
}
