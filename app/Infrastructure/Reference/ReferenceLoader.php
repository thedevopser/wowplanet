<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

use Illuminate\Database\Connection;
use Pdo\Pgsql;
use RuntimeException;

/**
 * Remplace le contenu d'une table de référence par `COPY`.
 *
 * `TRUNCATE` est transactionnel sur PostgreSQL : un chargement qui casse en cours de
 * route laisse la table telle qu'elle était, sans passer par une table de transit.
 */
final readonly class ReferenceLoader
{
    private const CHUNK_SIZE = 5000;

    public function __construct(private Connection $connection) {}

    /**
     * @param  iterable<string>  $rows  Lignes déjà encodées au format texte de `COPY`
     */
    public function replace(ReferenceTable $referenceTable, iterable $rows): int
    {
        $this->connection->statement(sprintf('TRUNCATE TABLE %s', $referenceTable->table()));

        $pdo = $this->pdo();
        $fields = implode(',', $referenceTable->targetColumns());

        $loaded = 0;
        $chunk = [];

        foreach ($rows as $row) {
            $chunk[] = $row;

            if (count($chunk) === self::CHUNK_SIZE) {
                $this->copy($pdo, $referenceTable, $chunk, $fields);
                $loaded += count($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->copy($pdo, $referenceTable, $chunk, $fields);
            $loaded += count($chunk);
        }

        return $loaded;
    }

    /**
     * @param  list<string>  $chunk
     */
    private function copy(Pgsql $pgsql, ReferenceTable $referenceTable, array $chunk, string $fields): void
    {
        $pgsql->copyFromArray($referenceTable->table(), $chunk, CopyText::SEPARATOR, CopyText::NULL_MARKER_SQL, $fields);
    }

    private function pdo(): Pgsql
    {
        $pdo = $this->connection->getPdo();

        throw_unless($pdo instanceof Pgsql, RuntimeException::class, 'Le chargement du socle de référence exige une connexion PostgreSQL.');

        return $pdo;
    }
}
