<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Illuminate\Log\LogManager;

/**
 * La piste « qui a déclenché quoi » des actions du panneau d'administration.
 *
 * Elle a son propre canal, au niveau information et toujours écrit : la production
 * journalise en `warning`, et ces traces, passées par le journal applicatif, y
 * disparaissaient. Une action destructive doit rester attribuable.
 */
final readonly class AdminAudit
{
    public const string CHANNEL = 'audit';

    public function __construct(
        private LogManager $logManager,
    ) {}

    /**
     * @param  array<string, int|string|list<int>|list<string>|list<array<string, int|string|bool|null>>|null>  $context
     */
    public function record(string $action, string $actor, array $context = []): void
    {
        $trace = [...$context, 'actor' => $actor];

        try {
            $this->logManager->channel(self::CHANNEL)->info($action, $trace);
        } catch (\Throwable $throwable) {
            // La trace suit une action déjà faite : l'échec de son écriture ne doit pas la
            // faire passer pour ratée. Il part dans le journal des erreurs, que la page de
            // santé affiche, avec de quoi reconstituer la trace perdue.
            $this->logManager->error('Admin audit trail could not be written', [
                'action' => $action,
                'trace' => $trace,
                'exception' => $throwable,
            ]);
        }
    }
}
