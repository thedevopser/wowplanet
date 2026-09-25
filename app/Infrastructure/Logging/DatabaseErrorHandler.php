<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Illuminate\Database\ConnectionResolverInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Tient le journal des erreurs que la page de santé consulte.
 *
 * Branché sur la pile de journalisation, il reçoit tout ce qui atteint le niveau erreur :
 * les `Log::error` des contrôleurs et des jobs, qui rattrapent l'essentiel des échecs,
 * comme les exceptions que rien n'a rattrapées. Le journal est borné à ses dernières
 * entrées, élagué à chaque écriture.
 */
final class DatabaseErrorHandler extends AbstractProcessingHandler
{
    /** Au-delà, un diagnostic n'a plus besoin de l'entrée : c'est la cause récente qui compte. */
    public const RETAINED_ENTRIES = 200;

    public const MESSAGE_LENGTH = 2000;

    private const string TABLE = 'application_errors';

    public function __construct(
        private readonly ConnectionResolverInterface $connectionResolver,
        int|string|Level $level = Level::Error,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    /**
     * Un échec d'écriture est avalé, sans rien journaliser à son tour : la journalisation
     * ne doit jamais faire tomber la requête qui journalisait, ni se rappeler elle-même
     * en boucle quand la base est justement ce qui est en panne.
     */
    protected function write(LogRecord $record): void
    {
        $exception = $record->context['exception'] ?? null;
        $exception = $exception instanceof \Throwable ? $exception : null;

        try {
            $connection = $this->connectionResolver->connection();

            $id = $connection->table(self::TABLE)->insertGetId([
                'level' => $record->level->getName(),
                'message' => mb_substr($record->message, 0, self::MESSAGE_LENGTH),
                'exception_class' => $exception instanceof \Throwable ? $exception::class : null,
                'location' => $exception instanceof \Throwable ? $exception->getFile().':'.$exception->getLine() : null,
                'occurred_at' => $record->datetime,
            ]);

            $connection->table(self::TABLE)->where('id', '<=', (int) $id - self::RETAINED_ENTRIES)->delete();
        } catch (\Throwable) {
            return;
        }
    }
}
