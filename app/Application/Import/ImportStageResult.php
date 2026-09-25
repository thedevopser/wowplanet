<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Ce que rend une passe d'étape : l'étape mise à jour, et ce sur quoi elle bute si elle
 * n'a pas fini. L'attente est rendue à l'appelant plutôt que subie ici, pour qu'un job
 * puisse relâcher le worker là où une commande, elle, préfère dormir.
 */
final readonly class ImportStageResult
{
    public function __construct(
        public ImportStep $step,
        public ?ImportWait $wait,
    ) {}
}
