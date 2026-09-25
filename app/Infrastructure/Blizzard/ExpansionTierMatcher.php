<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

class ExpansionTierMatcher
{
    /**
     * Match a skill tier name (French or English) to an expansion ID.
     * Most specific keywords first to avoid partial matches.
     */
    public static function match(string $name): ?int
    {
        // Normalize non-breaking spaces (U+00A0) from wago.tools DB2 data
        $name = str_replace("\u{00A0}", ' ', $name);

        $keywords = [
            // Multi-word (most specific first)
            "Royaumes de l'Est" => 0,
            'Wrath of the Lich King' => 2,
            'Battle for Azeroth' => 7,
            'Mists of Pandaria' => 4,
            'Burning Crusade' => 1,
            'Lich King' => 2,
            'War Within' => 10,
            'Kul Tir' => 7,
            'Zandalari' => 7,
            'Khaz Algar' => 10,
            'Tol Barad' => 3,
            'Dragon Isles' => 9,
            'les aux Dragons' => 9,
            'Faë nocturnes' => 8,
            'Nérub-ar' => 10,
            'Château Nathria' => 8,
            'Sépulcre des Premiers' => 8,
            "Caveau de l'Incarnation" => 9,
            "Ny'alotha" => 7,
            "Cœur d'Azeroth" => 7,
            'Île de Mécagone' => 7,

            // Single-word identifiers (French + English variants)
            'Kalimdor' => 0,
            'Classique' => 0,
            'Classic' => 0,
            'Outreterre' => 1,
            'Outland' => 1,
            'Norfendre' => 2,
            'Northrend' => 2,
            'Naxxramas' => 2,
            'Ulduar' => 2,
            'Cataclysm' => 3,
            'Cataclysme' => 3,
            'Worgen' => 3,
            'Pandarie' => 4,
            'Pandaria' => 4,
            'Pandaren' => 4,
            'pandarène' => 4,
            'Mogu' => 4,
            'Mantide' => 4,
            'Draenor' => 5,
            'Warlords' => 5,
            'Legion' => 6,
            'Légion' => 6,
            'Argus' => 6,
            'Uldir' => 7,
            'Zandalar' => 7,
            'Vulpérin' => 7,
            'Mécagnome' => 7,
            'Nécrolord' => 8,
            'Venthyr' => 8,
            'Kyrian' => 8,
            'Torghast' => 8,
            'Ombreterre' => 8,
            'Shadowlands' => 8,
            'Dragonflight' => 9,
            'Dracthyr' => 9,
            'Aberrus' => 9,
            'Amirdrassil' => 9,
            'Gouffre' => 10,
            'Midnight' => 11,
        ];

        foreach ($keywords as $keyword => $expansionId) {
            if (mb_stripos($name, $keyword) !== false) {
                return $expansionId;
            }
        }

        return null;
    }
}
