<?php

declare(strict_types=1);

namespace App\Application\Reference;

use App\Infrastructure\Logging\AdminAudit;
use App\Infrastructure\Reference\ReferenceStore;
use App\Models\WowReferenceDownload;

/**
 * Retire du magasin de référence les fichiers qu'on lui désigne, et l'inventaire avec.
 *
 * Les noms reçus sont déjà résolus par le serveur : rien ici ne confronte une requête au
 * disque, c'est la validation du contrôleur qui le fait, contre la liste que
 * {@see ReferenceFileInventory} calcule.
 */
final readonly class ReferencePurge
{
    public function __construct(
        private ReferenceStore $referenceStore,
        private AdminAudit $adminAudit,
    ) {}

    /**
     * @param  list<string>  $filenames
     * @return array{files: int, bytes: int} Ce qui a réellement été retiré
     */
    public function purge(array $filenames, string $actor): array
    {
        $sizes = $this->referenceStore->sizes();
        $removed = [];
        $bytes = 0;

        foreach ($filenames as $filename) {
            // Un fichier déjà parti ne compte pas dans l'espace libéré : le panneau
            // annonce un chiffre, et il doit être celui qu'on retrouve sur le disque.
            if (! array_key_exists($filename, $sizes)) {
                continue;
            }

            $this->referenceStore->delete($filename);

            $removed[] = $filename;
            $bytes += $sizes[$filename];
        }

        $this->forget($removed);
        $this->audit($removed, $bytes, $actor);

        return ['files' => count($removed), 'bytes' => $bytes];
    }

    /**
     * Une ligne d'inventaire sans fichier n'est pas une entrée valable : elle survivrait à
     * la purge en prétendant décrire un chargement qu'on ne peut plus rouvrir.
     *
     * @param  list<string>  $filenames
     */
    private function forget(array $filenames): void
    {
        if ($filenames === []) {
            return;
        }

        WowReferenceDownload::query()->whereIn('filename', $filenames)->delete();
    }

    /**
     * La piste d'audit part dans le journal applicatif, comme celle des ordres donnés à un
     * import : le journal d'import vit une journée, et qui a purgé quoi doit survivre plus
     * longtemps.
     *
     * @param  list<string>  $filenames
     */
    private function audit(array $filenames, int $bytes, string $actor): void
    {
        if ($filenames === []) {
            return;
        }

        $this->adminAudit->record('Reference store purged from the admin panel', $actor, [
            'files' => $filenames,
            'bytes' => $bytes,
        ]);
    }
}
