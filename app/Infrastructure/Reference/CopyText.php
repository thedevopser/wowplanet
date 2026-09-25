<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

/**
 * Encodage d'une ligne au format texte de `COPY`, celui que `copyFromArray()` attend.
 *
 * Ce format n'a rien de CSV : il sépare par tabulation, marque le nul par `\N` et
 * n'accorde aucun sens aux guillemets ni aux virgules. Seuls la barre oblique inverse
 * et les caractères de mise en page doivent être neutralisés.
 */
final class CopyText
{
    public const NULL_MARKER = '\N';

    /**
     * Le même marqueur, tel qu'il doit être passé à `copyFromArray()`.
     *
     * L'extension recopie l'argument dans la clause `NULL AS '…'` sans l'échapper : une
     * barre oblique inverse simple y est consommée par l'analyseur SQL, et PostgreSQL
     * finit par chercher un nul écrit `N`.
     */
    public const NULL_MARKER_SQL = '\\\\N';

    public const SEPARATOR = "\t";

    /**
     * @param  list<string|null>  $values
     */
    public static function line(array $values): string
    {
        return implode(self::SEPARATOR, array_map(self::encode(...), $values));
    }

    private static function encode(?string $value): string
    {
        if ($value === null) {
            return self::NULL_MARKER;
        }

        // La barre oblique inverse en premier, sinon les échappements produits par les
        // remplacements suivants seraient échappés à leur tour.
        return str_replace(['\\', "\t", "\n", "\r"], ['\\\\', '\\t', '\\n', '\\r'], $value);
    }
}
