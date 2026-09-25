<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Infrastructure\Blizzard\HourlyBudgetGuard;

/**
 * Fait remonter au suivi les attentes que seul le client API connaît : un lot en vol,
 * un recul après un 429.
 *
 * C'est un service partagé, tenu en unique exemplaire par le conteneur, parce que le
 * client API est traversé par sept importers dont aucun ne connaît le job en cours.
 * Hors import suivi — appel en ligne de commande, trafic du site — il ne suit rien et
 * ne publie rien.
 */
final class ImportWaitReporter
{
    private ?string $jobId = null;

    public function __construct(
        private readonly ImportProgressStore $importProgressStore,
        private readonly HourlyBudgetGuard $hourlyBudgetGuard,
    ) {}

    public function follow(string $jobId): void
    {
        $this->jobId = $jobId;
    }

    public function release(): void
    {
        $this->jobId = null;
    }

    public function waiting(ImportWait $importWait): void
    {
        $this->publish($importWait);
    }

    public function working(): void
    {
        $this->publish(null);
    }

    private function publish(?ImportWait $importWait): void
    {
        if ($this->jobId === null) {
            return;
        }

        $importRun = $this->importProgressStore->find($this->jobId);

        if (! $importRun instanceof ImportRun) {
            return;
        }

        // Le budget est rafraîchi ici et pas seulement en fin d'étape : une étape de
        // deux minutes afficherait sinon un quota figé pendant tout ce temps.
        $this->importProgressStore->save(
            $importRun->waitingOn($importWait)->withBudgetUsed($this->hourlyBudgetGuard->usedInWindow()),
        );
    }
}
