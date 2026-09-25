<?php

declare(strict_types=1);

namespace App\Application\Import;

use Symfony\Component\Console\Output\Output;

/**
 * Détourne la sortie d'une commande Artisan vers le journal d'un import.
 *
 * C'est ce qui donne au chemin générique de `RunImportJob` le même suivi en direct que
 * l'import orchestré : la commande écrit comme elle l'a toujours fait, et le panneau relit
 * ses lignes au fil de l'eau par le même contrat « curseur plus delta », au lieu d'attendre
 * un bloc de sortie publié à la fin.
 */
final class ImportLogOutput extends Output
{
    /** Une commande peut composer une ligne en plusieurs écritures : on attend sa fin. */
    private string $pending = '';

    public function __construct(
        private readonly ImportLog $importLog,
        private readonly string $jobId,
    ) {
        parent::__construct(decorated: false);
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->pending .= $message;

        if (! $newline) {
            return;
        }

        $line = trim($this->pending);
        $this->pending = '';

        // Les commandes aèrent leur sortie de lignes vides, qui n'ont pas de sens
        // horodatées dans un journal.
        if ($line !== '') {
            $this->importLog->note($this->jobId, $line);
        }
    }
}
