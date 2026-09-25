<?php

declare(strict_types=1);

namespace App\Http\Character;

/**
 * Les trois sections de la fiche qui ont une adresse propre, avec leurs sous-onglets
 * dans l'ordre de la fiche. L'Aperçu n'en a pas : c'est l'URL de base.
 */
enum CharacterSheetSection: string
{
    case Progression = 'progression';
    case Endgame = 'endgame';
    case Collections = 'collections';

    /**
     * @return list<string>
     */
    public function subTabs(): array
    {
        return match ($this) {
            self::Progression => ['quetes', 'hauts-faits', 'reputations', 'metiers'],
            self::Endgame => ['mythique-plus', 'raids', 'pvp', 'equipement'],
            self::Collections => ['montures', 'mascottes', 'decorations', 'garde-robe'],
        };
    }
}
