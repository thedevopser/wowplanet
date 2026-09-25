<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * État d'une étape d'import, et par agrégation celui de l'import entier.
 *
 * `Skipped` est un état terminal qui n'est pas un échec : c'est la porte de build de
 * l'US-02 qui a constaté qu'il n'y avait rien à refaire pour ce build.
 */
enum ImportStepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Skipped], true);
    }
}
