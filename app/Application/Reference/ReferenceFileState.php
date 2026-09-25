<?php

declare(strict_types=1);

namespace App\Application\Reference;

/**
 * Ce qu'un fichier du magasin de référence vaut encore, et donc ce qu'on a le droit d'en
 * faire depuis le panneau.
 *
 * « En service » veut dire chargé, pas publié par wago : c'est le fichier dont le
 * chargement a produit ce qui est dans les tables. Le classement ne dépend donc d'aucun
 * appel sortant et reste juste quand wago est injoignable.
 */
enum ReferenceFileState: string
{
    case Live = 'live';
    case Obsolete = 'obsolete';
    case Taxonomy = 'taxonomy';
    case Orphan = 'orphan';

    /**
     * Ce que le nettoyage en lot emporte.
     *
     * Un fichier en service est la preuve de ce qui a été chargé, et un instantané de
     * taxonomie vient d'un tirage manuel : ni l'un ni l'autre ne doit partir dans un
     * balayage, seulement sur une désignation explicite.
     */
    public function isSweepable(): bool
    {
        return $this === self::Obsolete || $this === self::Orphan;
    }
}
