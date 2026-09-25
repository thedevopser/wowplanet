<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

/**
 * Rétrécissement des valeurs qui sortent du socle.
 *
 * Les tables `wow_ref_*` sont interrogées par le constructeur de requêtes, qui rend des
 * objets aux propriétés non typées : c'est une frontière, et le `mixed` qui en vient est
 * converti ici, dès la ligne qui le reçoit, plutôt que de traverser le code.
 */
final class ReferenceValue
{
    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
