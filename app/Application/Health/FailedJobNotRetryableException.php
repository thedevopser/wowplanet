<?php

declare(strict_types=1);

namespace App\Application\Health;

/**
 * Le job échoué ne peut pas être remis en queue : sa charge utile ne se relit plus,
 * typiquement parce que la classe du job a été renommée ou supprimée depuis l'échec.
 * Il reste dans la liste, où seule la suppression peut encore le traiter.
 */
final class FailedJobNotRetryableException extends \RuntimeException
{
    public static function because(string $uuid, \Throwable $throwable): self
    {
        return new self(sprintf('Le job échoué %s ne peut pas être relancé : %s', $uuid, $throwable->getMessage()), 0, $throwable);
    }
}
