<?php

declare(strict_types=1);

namespace App\Application\Taxonomy;

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
 * L'écriture passe par le constructeur de requêtes : la clé primaire de
 * {@see WowCollectionTaxonomy} est composite, et un `save()` sur une instance chargée ne
 * saurait pas la retrouver.
 */
final readonly class TaxonomyArbitration
{
    public function __construct(
        private TaxonomySnapshotExporter $taxonomySnapshotExporter,
        private AdminAudit $adminAudit,
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

        DB::transaction(function () use ($collectionEntity, $entryIds, $category, $source): void {
            foreach ($entryIds as $entryId) {
                $this->file($collectionEntity, $entryId, $category, $source);
            }
        });

        $this->audit($collectionEntity, $entryIds, $category, $source, $actor);

        return [
            'arbitrated' => count($entryIds),
            'snapshot' => $this->export(),
        ];
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
     */
    private function audit(CollectionEntity $collectionEntity, array $entryIds, ?string $category, ?string $source, string $actor): void
    {
        $this->adminAudit->record('Collection taxonomy arbitrated from the admin panel', $actor, [
            'entity' => $collectionEntity->value,
            'entries' => $entryIds,
            'category' => $category,
            'source' => $source,
        ]);
    }
}
