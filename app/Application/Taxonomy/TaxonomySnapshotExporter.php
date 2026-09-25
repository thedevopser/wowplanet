<?php

declare(strict_types=1);

namespace App\Application\Taxonomy;

use App\Infrastructure\Taxonomy\CollectionTaxonomySnapshot;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowCollectionTaxonomy;

/**
 * Regénère l'instantané versionné depuis la base, et dit s'il en est encore le reflet.
 *
 * Un arbitrage écrit en base, et la curation ne vit alors que là : sans export, un
 * environnement reconstruit depuis le dépôt la perdrait. L'écran d'arbitrage exporte donc
 * dans la foulée de chaque écriture, et `state()` reste pour attraper le cas où la base a
 * bougé autrement — un export raté, une écriture faite ailleurs.
 *
 * La comparaison porte sur le contenu et non sur un nombre de lignes : une correction de
 * libellé ne change aucun décompte et ferait passer pour en phase un fichier périmé.
 */
final readonly class TaxonomySnapshotExporter
{
    public function __construct(private CollectionTaxonomySnapshot $collectionTaxonomySnapshot) {}

    /**
     * @return array{written: int, path: string}
     *
     * @throws \RuntimeException quand la base est vide
     */
    public function export(): array
    {
        $entries = $this->nonEmptyEntries();

        return [
            'written' => $this->collectionTaxonomySnapshot->write($entries),
            'path' => $this->collectionTaxonomySnapshot->path(),
        ];
    }

    /**
     * L'instantané régénéré depuis la base, à verser au dépôt, sans toucher au fichier
     * en place : en production, ce fichier est la version commitée qui sert de référence.
     *
     * @return array{filename: string, contents: string}
     *
     * @throws \RuntimeException quand la base est vide
     */
    public function download(): array
    {
        return [
            'filename' => CollectionTaxonomySnapshot::FILENAME,
            'contents' => $this->collectionTaxonomySnapshot->render($this->nonEmptyEntries()),
        ];
    }

    /**
     * La dérive est orientée, parce que le remède en dépend : des entrées du fichier
     * absentes de la base se rechargent, des entrées de la base absentes du fichier ou
     * différentes se téléchargent pour être commitées.
     *
     * @return array{path: string, entries: int, in_step: bool, missing_in_base: int, missing_in_file: int, differing: int}
     */
    public function state(): array
    {
        $onFile = $this->comparable($this->onFile());
        $inBase = $this->comparable($this->entries());

        return [
            'path' => $this->collectionTaxonomySnapshot->path(),
            'entries' => array_sum(array_map(count(...), $onFile)),
            'in_step' => $onFile === $inBase,
            'missing_in_base' => $this->countMissing($onFile, $inBase),
            'missing_in_file' => $this->countMissing($inBase, $onFile),
            'differing' => $this->countDiffering($onFile, $inBase),
        ];
    }

    /**
     * Une table vide écraserait silencieusement le fichier curé du dépôt, ou ferait
     * télécharger un instantané vide : c'est exactement le geste qu'on ne veut pas.
     *
     * @return array<string, array<int, TaxonomyEntry>>
     */
    private function nonEmptyEntries(): array
    {
        $entries = $this->entries();

        throw_if($entries === [], \RuntimeException::class, sprintf(
            'Taxonomie vide en base : rien à exporter, %s laissé en place.',
            $this->collectionTaxonomySnapshot->path(),
        ));

        return $entries;
    }

    /**
     * @param  array<string, array<int, array{string|null, string|null, bool}>>  $from
     * @param  array<string, array<int, array{string|null, string|null, bool}>>  $in
     */
    private function countMissing(array $from, array $in): int
    {
        $missing = 0;

        foreach ($from as $entity => $entries) {
            $missing += count(array_diff_key($entries, $in[$entity] ?? []));
        }

        return $missing;
    }

    /**
     * @param  array<string, array<int, array{string|null, string|null, bool}>>  $onFile
     * @param  array<string, array<int, array{string|null, string|null, bool}>>  $inBase
     */
    private function countDiffering(array $onFile, array $inBase): int
    {
        $differing = 0;

        foreach ($onFile as $entity => $entries) {
            foreach ($entries as $entryId => $values) {
                if (isset($inBase[$entity][$entryId]) && $inBase[$entity][$entryId] !== $values) {
                    $differing++;
                }
            }
        }

        return $differing;
    }

    /**
     * Un instantané absent ou illisible se lit comme vide : l'écran doit pouvoir dire qu'il
     * a dérivé, pas tomber en erreur sur un fichier qu'on n'a pas encore écrit.
     *
     * @return array<string, array<int, TaxonomyEntry>>
     */
    private function onFile(): array
    {
        try {
            return $this->collectionTaxonomySnapshot->read();
        } catch (TaxonomySourceUnavailableException) {
            return [];
        }
    }

    /**
     * @param  array<string, array<int, TaxonomyEntry>>  $entries
     * @return array<string, array<int, array{string|null, string|null, bool}>>
     */
    private function comparable(array $entries): array
    {
        $comparable = [];

        foreach ($entries as $entity => $entityEntries) {
            ksort($entityEntries);

            foreach ($entityEntries as $entryId => $taxonomyEntry) {
                $comparable[$entity][$entryId] = [
                    $taxonomyEntry->category,
                    $taxonomyEntry->source,
                    $taxonomyEntry->obtainable,
                ];
            }
        }

        ksort($comparable);

        return $comparable;
    }

    /**
     * @return array<string, array<int, TaxonomyEntry>>
     */
    private function entries(): array
    {
        $entries = [];

        WowCollectionTaxonomy::query()
            ->orderBy('entity')
            ->orderBy('entry_id')
            ->each(static function (WowCollectionTaxonomy $wowCollectionTaxonomy) use (&$entries): void {
                $entries[$wowCollectionTaxonomy->entity->value][$wowCollectionTaxonomy->entry_id] = new TaxonomyEntry(
                    $wowCollectionTaxonomy->category,
                    $wowCollectionTaxonomy->source,
                    $wowCollectionTaxonomy->obtainable,
                );
            });

        /** @var array<string, array<int, TaxonomyEntry>> $entries */
        return $entries;
    }
}
