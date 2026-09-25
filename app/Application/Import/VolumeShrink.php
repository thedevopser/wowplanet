<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Le seuil unique qui dit qu'une table a fondu d'un chargement au suivant.
 *
 * Un patch qui casse un format ne lève pas d'erreur : il vide une table. Le socle de
 * référence et l'historique des imports lisent tous deux ce symptôme, et doivent le
 * lire au même seuil.
 */
final class VolumeShrink
{
    /**
     * Volontairement bien au-dessus du refus d'écriture de la synchronisation du socle,
     * qui rejette une source tombée sous la moitié de son prédécesseur. Entre les deux,
     * le chargement aboutit sans que rien ne le signale : c'est l'angle mort que ce
     * seuil comble. Perdre un dixième de ses lignes d'un build à l'autre est anormal et
     * mérite un regard, même accepté.
     *
     * @pest-mutate-ignore
     */
    public const ALERT_RATIO = 0.9;

    public static function between(int $previous, int $current): bool
    {
        throw_if($previous < 0 || $current < 0, \InvalidArgumentException::class, 'A row count cannot be negative.');

        return $previous > 0 && $current < $previous * self::ALERT_RATIO;
    }
}
