<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class InvalidCurrencyDataException extends RuntimeException
{
    public static function invalidBase64Encoding(): self
    {
        return new self('Erreur de décodage base64: données corrompues');
    }

    public static function invalidJsonStructure(string $reason): self
    {
        return new self(sprintf('JSON invalide: %s', $reason));
    }

    public static function notAnArray(): self
    {
        return new self('Le fichier JSON est invalide (pas un tableau)');
    }

    public static function noCharacterData(): self
    {
        return new self('Le fichier JSON ne contient pas de données de personnages');
    }

    public static function emptyCharacterData(): self
    {
        return new self('Le fichier JSON contient un tableau de personnages vide');
    }
}
