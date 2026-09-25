<?php

declare(strict_types=1);

namespace App\Application\Reference;

/**
 * Range le contenu du magasin de référence en quatre états, à partir de ce qu'on lui
 * donne et de rien d'autre.
 *
 * Aucune lecture de disque ni de base ici : le classement est la seule règle de l'écran
 * de purge, et c'est celle qu'il faut pouvoir vérifier sans rien monter. Les effets
 * vivent dans {@see ReferenceFileInventory}.
 */
final readonly class ReferenceFileClassifier
{
    /**
     * @param  array<string, int>  $sizes  Taille en octets, par nom de fichier réellement présent
     * @param  list<array{filename: string, source_table: string, build: string, loaded_at: int}>  $inventory
     * @param  list<string>  $taxonomyFilenames  Instantanés déposés par le tirage amont de la taxonomie
     * @return array{files: list<array{filename: string, state: string, bytes: int, source_table: string|null, build: string|null, loaded_at: int|null}>, missing: list<array{filename: string, source_table: string, build: string, loaded_at: int}>}
     */
    public function classify(array $sizes, array $inventory, array $taxonomyFilenames): array
    {
        $loads = $this->indexByFilename($inventory);
        $inService = $this->inServiceFilenames($inventory);

        arsort($sizes);

        $files = [];

        foreach ($sizes as $filename => $bytes) {
            $load = $loads[$filename] ?? null;

            $files[] = [
                'filename' => $filename,
                'state' => $this->state($filename, $load, $inService, $taxonomyFilenames)->value,
                'bytes' => $bytes,
                'source_table' => $load['source_table'] ?? null,
                'build' => $load['build'] ?? null,
                'loaded_at' => $load['loaded_at'] ?? null,
            ];
        }

        return ['files' => $files, 'missing' => $this->missing($inventory, $sizes)];
    }

    /**
     * @param  array{filename: string, source_table: string, build: string, loaded_at: int}|null  $load
     * @param  array<string, true>  $inService
     * @param  list<string>  $taxonomyFilenames
     */
    private function state(string $filename, ?array $load, array $inService, array $taxonomyFilenames): ReferenceFileState
    {
        if (in_array($filename, $taxonomyFilenames, true)) {
            return ReferenceFileState::Taxonomy;
        }

        if ($load === null) {
            return ReferenceFileState::Orphan;
        }

        return isset($inService[$filename]) ? ReferenceFileState::Live : ReferenceFileState::Obsolete;
    }

    /**
     * Le dernier chargement de chaque table, qu'il ait laissé un fichier ou non.
     *
     * Un fichier effacé à la main ne rend pas son prédécesseur au service : la table
     * porte toujours ce que ce chargement-là y a écrit, et son fichier reste l'obsolète
     * qu'il était.
     *
     * @param  list<array{filename: string, source_table: string, build: string, loaded_at: int}>  $inventory
     * @return array<string, true>
     */
    private function inServiceFilenames(array $inventory): array
    {
        $newest = [];

        foreach ($inventory as $load) {
            $incumbent = $newest[$load['source_table']] ?? null;

            // Le build départage deux chargements enregistrés dans la même seconde, que
            // l'horodatage seul laisserait dans l'ordre de lecture de la base.
            if ($incumbent === null
                || $load['loaded_at'] > $incumbent['loaded_at']
                || ($load['loaded_at'] === $incumbent['loaded_at'] && $load['build'] > $incumbent['build'])) {
                $newest[$load['source_table']] = $load;
            }
        }

        return array_fill_keys(array_column($newest, 'filename'), true);
    }

    /**
     * @param  list<array{filename: string, source_table: string, build: string, loaded_at: int}>  $inventory
     * @return array<string, array{filename: string, source_table: string, build: string, loaded_at: int}>
     */
    private function indexByFilename(array $inventory): array
    {
        return array_column($inventory, null, 'filename');
    }

    /**
     * @param  list<array{filename: string, source_table: string, build: string, loaded_at: int}>  $inventory
     * @param  array<string, int>  $sizes
     * @return list<array{filename: string, source_table: string, build: string, loaded_at: int}>
     */
    private function missing(array $inventory, array $sizes): array
    {
        return array_values(array_filter(
            $inventory,
            static fn (array $load): bool => ! array_key_exists($load['filename'], $sizes),
        ));
    }
}
