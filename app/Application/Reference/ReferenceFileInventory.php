<?php

declare(strict_types=1);

namespace App\Application\Reference;

use App\Infrastructure\Reference\ReferenceStore;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowReferenceDownload;

/**
 * Ce que le magasin de référence contient réellement, confronté à ce que l'inventaire en
 * dit.
 *
 * C'est la moitié à effets du classement : elle lit le disque et la base, et délègue la
 * règle à {@see ReferenceFileClassifier}. `storage/app/blizzard/` avait accumulé 66 Mo de
 * reliquats parce que rien ne les affichait ; cette lecture est ce qui empêche la même
 * dérive sur `storage/app/wow-reference/`.
 */
final readonly class ReferenceFileInventory
{
    public function __construct(
        private ReferenceStore $referenceStore,
        private ReferenceFileClassifier $referenceFileClassifier,
    ) {}

    /**
     * @return array{files: list<array{filename: string, state: string, bytes: int, source_table: string|null, build: string|null, loaded_at: int|null}>, missing: list<array{filename: string, source_table: string, build: string, loaded_at: int}>, totals: array{files: int, bytes: int, sweepable_files: int, sweepable_bytes: int}}
     */
    public function contents(): array
    {
        $classified = $this->referenceFileClassifier->classify(
            $this->referenceStore->sizes(),
            $this->loads(),
            $this->taxonomyFilenames(),
        );

        return [...$classified, 'totals' => $this->totals($classified['files'])];
    }

    /**
     * Ce que le nettoyage en lot emporterait, calculé par la même règle que l'écran.
     *
     * @return list<string>
     */
    public function sweepableFilenames(): array
    {
        return array_values(array_map(
            static fn (array $file): string => $file['filename'],
            array_filter(
                $this->contents()['files'],
                static fn (array $file): bool => ReferenceFileState::from($file['state'])->isSweepable(),
            ),
        ));
    }

    /**
     * Les seuls noms qu'une requête a le droit de désigner.
     *
     * @return list<string>
     */
    public function filenames(): array
    {
        return array_keys($this->referenceStore->sizes());
    }

    /**
     * @param  list<array{filename: string, state: string, bytes: int, source_table: string|null, build: string|null, loaded_at: int|null}>  $files
     * @return array{files: int, bytes: int, sweepable_files: int, sweepable_bytes: int}
     */
    private function totals(array $files): array
    {
        $sweepable = array_filter(
            $files,
            static fn (array $file): bool => ReferenceFileState::from($file['state'])->isSweepable(),
        );

        return [
            'files' => count($files),
            'bytes' => array_sum(array_column($files, 'bytes')),
            'sweepable_files' => count($sweepable),
            'sweepable_bytes' => array_sum(array_column($sweepable, 'bytes')),
        ];
    }

    /**
     * @return list<array{filename: string, source_table: string, build: string, loaded_at: int}>
     */
    private function loads(): array
    {
        return array_values(WowReferenceDownload::query()
            ->get()
            ->map(static fn (WowReferenceDownload $wowReferenceDownload): array => [
                'filename' => $wowReferenceDownload->filename,
                'source_table' => $wowReferenceDownload->source_table,
                'build' => $wowReferenceDownload->build,
                'loaded_at' => $wowReferenceDownload->downloaded_at->getTimestamp(),
            ])
            ->all());
    }

    /**
     * Les instantanés du tirage amont de la taxonomie, qui partagent ce disque sans
     * passer par l'inventaire : le client amont écrit directement dessus.
     *
     * @return list<string>
     */
    private function taxonomyFilenames(): array
    {
        return array_map(
            static fn (CollectionEntity $collectionEntity): string => $collectionEntity->simpleArmoryFile(),
            CollectionEntity::cases(),
        );
    }
}
