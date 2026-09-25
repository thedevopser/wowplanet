<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Un import tourne déjà, un second ne démarrera pas.
 *
 * Le refus porte de quoi le rendre lisible — lequel tourne et depuis quand — parce que
 * « import déjà en cours » sans plus de précision laisse le choix entre attendre et
 * redémarrer un worker qu'on croit coincé.
 */
final class ImportAlreadyRunningException extends \RuntimeException
{
    public function __construct(
        public readonly string $jobId,
        public readonly int $startedAt,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function since(string $jobId, int $startedAt, int $now): self
    {
        return new self($jobId, $startedAt, sprintf(
            'Un import est déjà en cours depuis %s.',
            self::elapsed(max(0, $now - $startedAt)),
        ));
    }

    private static function elapsed(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' s';
        }

        if ($seconds < 3600) {
            return sprintf('%d min %02d s', intdiv($seconds, 60), $seconds % 60);
        }

        return sprintf('%d h %02d min', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
