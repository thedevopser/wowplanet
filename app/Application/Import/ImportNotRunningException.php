<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * L'import qu'on cherche à piloter n'est pas celui qui tourne.
 *
 * Seul l'import désigné par le pointeur se pilote : un identifiant venu de la requête ne
 * doit jamais pouvoir poser un ordre sur autre chose. Le cas courant est l'onglet resté
 * ouvert sur un import terminé depuis, d'où un refus qui le dit plutôt qu'un 404 nu.
 */
final class ImportNotRunningException extends \RuntimeException
{
    public function __construct(
        public readonly string $jobId,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forJob(string $jobId, ?string $running): self
    {
        return new self($jobId, $running === null
            ? 'Aucun import ne tourne actuellement.'
            : "Cet import n'est plus celui qui tourne.");
    }
}
