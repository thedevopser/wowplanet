<?php

declare(strict_types=1);

namespace App\Infrastructure\Coverage\Exceptions;

use RuntimeException;

final class UnreadableCloverException extends RuntimeException
{
    public static function notXml(): self
    {
        return new self("Le rapport clover n'est pas un document XML lisible.");
    }

    public static function notClover(string $rootName): self
    {
        return new self(sprintf("Le document XML a pour racine <%s>, pas <coverage> : ce n'est pas un rapport clover.", $rootName));
    }

    public static function missingAttribute(string $fileName, string $attribute): self
    {
        return new self(sprintf('Une méthode de %s n\'a pas d\'attribut %s.', $fileName, $attribute));
    }
}
