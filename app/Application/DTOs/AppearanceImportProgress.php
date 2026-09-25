<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * État d'avancement d'une passe d'import d'apparences (importChunk).
 *
 * - $done : plus rien à traiter (toutes les fenêtres balayées).
 * - $offset : fenêtre de reprise, portée d'une passe à l'autre. Les fenêtres d'items
 *   viennent d'abord, celles des media ensuite, sur la même grille d'identifiants.
 * - $total : nombre total de fenêtres à balayer, les deux passes confondues.
 * - $secondsUntilBudget : secondes à attendre avant la prochaine passe (0 si budget dispo).
 */
final readonly class AppearanceImportProgress
{
    public function __construct(
        public bool $done,
        public int $offset,
        public int $total,
        public int $secondsUntilBudget,
    ) {}
}
