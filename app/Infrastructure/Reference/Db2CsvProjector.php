<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

use App\Infrastructure\Reference\Exceptions\MalformedSourceException;
use App\Infrastructure\Reference\Exceptions\MissingColumnException;
use Generator;

/**
 * Réduit un CSV DB2 aux seules colonnes déclarées, dans l'ordre de la table cible.
 */
final class Db2CsvProjector
{
    /**
     * @param  resource  $handle
     * @return Generator<int, string>
     */
    public function project(ReferenceTable $referenceTable, $handle): Generator
    {
        $headers = fgetcsv($handle, 0, ',', '"', '');

        if (! is_array($headers)) {
            throw MalformedSourceException::withoutHeader($referenceTable->source);
        }

        $indexes = $this->resolveIndexes($referenceTable, $headers);

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            yield CopyText::line($this->projectRow($referenceTable, $indexes, $row));
        }

        fclose($handle);
    }

    /**
     * @param  list<string|null>  $headers
     * @return list<int>
     */
    private function resolveIndexes(ReferenceTable $referenceTable, array $headers): array
    {
        $indexes = [];

        foreach ($referenceTable->columns as $column) {
            $index = array_search($column->source, $headers, true);

            if ($index === false) {
                throw MissingColumnException::for($referenceTable->source, $column->source);
            }

            $indexes[] = $index;
        }

        return $indexes;
    }

    /**
     * @param  list<int>  $indexes
     * @param  list<string|null>  $row
     * @return list<string|null>
     */
    private function projectRow(ReferenceTable $referenceTable, array $indexes, array $row): array
    {
        $values = [];

        foreach ($referenceTable->columns as $position => $column) {
            $value = $row[$indexes[$position]] ?? null;

            $values[] = $value === '' && $column->type->isNumeric() ? null : $value;
        }

        return $values;
    }
}
