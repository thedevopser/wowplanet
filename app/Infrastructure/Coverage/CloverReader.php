<?php

declare(strict_types=1);

namespace App\Infrastructure\Coverage;

use App\Infrastructure\Coverage\Exceptions\UnreadableCloverException;
use SimpleXMLElement;

/**
 * Lecture des méthodes d'un rapport clover de PHPUnit.
 *
 * Fonction pure : le contenu XML entre, les méthodes sortent. Le CRAP est celui que PHPUnit
 * a calculé, il n'est pas recalculé ici.
 */
final class CloverReader
{
    private const string METHOD = 'method';

    private const string STATEMENT = 'stmt';

    /**
     * Le clover range les lignes au niveau du fichier, pas de la classe : les instructions
     * d'une méthode sont celles qui la suivent jusqu'à la méthode suivante.
     *
     * @return list<MethodRisk>
     */
    public static function methods(string $xml): array
    {
        $document = self::parse($xml);

        $methods = [];

        foreach ($document->xpath('//file') ?: [] as $file) {
            array_push($methods, ...self::methodsOf($file));
        }

        return $methods;
    }

    private static function parse(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            throw UnreadableCloverException::notXml();
        }

        if ($document->getName() !== 'coverage') {
            throw UnreadableCloverException::notClover($document->getName());
        }

        return $document;
    }

    /**
     * @return list<MethodRisk>
     */
    private static function methodsOf(SimpleXMLElement $file): array
    {
        $fileName = (string) $file['name'];
        $className = property_exists($file, 'class') && $file->class !== null ? (string) $file->class['name'] : $fileName;

        $methods = [];
        $current = null;
        $statements = 0;
        $covered = 0;

        foreach ($file->line as $line) {
            $type = (string) $line['type'];

            if ($type === self::METHOD) {
                if ($current instanceof SimpleXMLElement) {
                    $methods[] = self::methodRisk($className, $fileName, $current, $statements, $covered);
                }

                $current = $line;
                $statements = 0;
                $covered = 0;

                continue;
            }

            if ($type === self::STATEMENT && $current instanceof SimpleXMLElement) {
                $statements++;
                $covered += (int) $line['count'] > 0 ? 1 : 0;
            }
        }

        if ($current instanceof SimpleXMLElement) {
            $methods[] = self::methodRisk($className, $fileName, $current, $statements, $covered);
        }

        return $methods;
    }

    private static function methodRisk(
        string $className,
        string $fileName,
        SimpleXMLElement $method,
        int $statements,
        int $covered,
    ): MethodRisk {
        return new MethodRisk(
            $className,
            (string) $method['name'],
            (int) self::requiredAttribute($method, 'complexity', $fileName),
            self::coverage($method, $statements, $covered),
            (float) self::requiredAttribute($method, 'crap', $fileName),
        );
    }

    /**
     * Une méthode sans instruction — un constructeur à promotion de propriétés — est couverte
     * dès qu'elle a été appelée.
     */
    private static function coverage(SimpleXMLElement $method, int $statements, int $covered): float
    {
        if ($statements === 0) {
            return (int) $method['count'] > 0 ? 100.0 : 0.0;
        }

        return round($covered * 100 / $statements, 1);
    }

    private static function requiredAttribute(SimpleXMLElement $method, string $attribute, string $fileName): string
    {
        if (! isset($method[$attribute])) {
            throw UnreadableCloverException::missingAttribute($fileName, $attribute);
        }

        return (string) $method[$attribute];
    }
}
