<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Magasin des fichiers téléchargés, sur le disque `reference`.
 *
 * Le nom de fichier d'une table porte le build : deux synchronisations d'un même build
 * écrivent le même fichier, deux builds différents en laissent deux, ce qui donne son
 * inventaire à la purge du panneau d'administration.
 */
final class ReferenceStore
{
    public const DISK = 'reference';

    public function put(ReferenceTable $referenceTable, string $build, string $contents): int
    {
        $this->disk()->put($referenceTable->filename($build), $contents);

        return strlen($contents);
    }

    /**
     * @return resource
     */
    public function read(ReferenceTable $referenceTable, string $build)
    {
        $stream = $this->disk()->readStream($referenceTable->filename($build));

        if (! is_resource($stream)) {
            throw new \RuntimeException(sprintf('Fichier de référence %s illisible.', $referenceTable->filename($build)));
        }

        return $stream;
    }

    /**
     * Ce que le magasin tient à sa racine, avec le poids de chaque fichier.
     *
     * Les tailles viennent du système de fichiers et jamais du contenu : `spell_misc`
     * pèse 45 Mo, et l'inventaire d'un écran n'a aucune raison de le charger en mémoire.
     *
     * Le magasin est plat — `ReferenceTable::filename()` ne fabrique pas de
     * sous-répertoire — donc ce qui serait rangé plus bas n'est pas à lui et ne regarde
     * pas la purge.
     *
     * Un répertoire absent, qui est l'état d'une installation neuve, rend une liste vide :
     * vérifié sur le disque réel, que son option `throw` ne fait pas lever dans ce cas.
     *
     * @return array<string, int>
     */
    public function sizes(): array
    {
        $disk = $this->disk();
        $sizes = [];

        foreach ($disk->files() as $filename) {
            $sizes[$filename] = $disk->size($filename);
        }

        return $sizes;
    }

    /**
     * Supprime un fichier déjà résolu par le serveur.
     *
     * Un fichier déjà parti n'est pas une erreur : le résultat voulu est acquis, et deux
     * onglets qui purgent la même sélection ne doivent pas s'échouer l'un l'autre.
     */
    public function delete(string $filename): void
    {
        $this->disk()->delete($filename);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
