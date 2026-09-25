<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

use Illuminate\Support\Facades\DB;

/**
 * Tout ce que le socle sait des réputations : extension, nom, renom maximal, portée
 * compte, et camp des réputations exclusives à une faction.
 *
 * Servie à l'import comme à l'exécution : l'agrégateur de progression des réputations
 * l'interroge à chaque profil de personnage. Les lectures sont donc mémorisées pour la
 * durée de l'instance, la version précédente reparsant `faction.csv` quatre fois par
 * requête.
 */
class FactionReference
{
    /**
     * Factions parentes servant d'en-tête d'extension dans la hiérarchie.
     */
    private const EXPANSION_HEADERS = [
        1118 => 0,  // Classique
        980 => 1,   // The Burning Crusade
        1097 => 2,  // Wrath of the Lich King
        1162 => 3,  // Cataclysm
        1245 => 4,  // Mists of Pandaria
        1444 => 5,  // Warlords of Draenor
        1834 => 6,  // Legion
        2104 => 7,  // Battle for Azeroth
        2414 => 8,  // Shadowlands
        2506 => 9,  // Dragonflight
        2569 => 10, // The War Within
        2698 => 11, // Midnight
    ];

    /** Extension à partir de laquelle toute réputation vaut pour le compte entier. */
    private const ACCOUNT_WIDE_FROM_EXPANSION = 9;

    /** Profondeur maximale de remontée dans la hiérarchie des factions parentes. */
    private const MAX_PARENT_DEPTH = 10;

    /** Hurlevent, dont le masque de race sert de référence Alliance. */
    private const STORMWIND_FACTION_ID = 72;

    /**
     * @var list<array{id: int, name: string, reputation_index: int, parent_faction_id: int, renown_currency_id: int, friendship_rep_id: int, reputation_max_0: int, reputation_max_1: int, race_mask_low: int|null, race_mask_high: int|null}>|null
     */
    private ?array $rows = null;

    /**
     * @return array<int, int> [faction_id => expansion_id]
     */
    public function expansions(): array
    {
        $parents = [];
        foreach ($this->rows() as $row) {
            $parents[$row['id']] = $row['parent_faction_id'];
        }

        $expansions = [];
        foreach ($this->reputations() as $row) {
            $expansion = $this->resolveExpansion($row['id'], $parents);
            if ($expansion !== null) {
                $expansions[$row['id']] = $expansion;
            }
        }

        return $expansions;
    }

    /**
     * @return array<int, string> [faction_id => nom localisé]
     */
    public function names(): array
    {
        $names = [];
        foreach ($this->reputations() as $row) {
            $names[$row['id']] = $row['name'];
        }

        return $names;
    }

    /**
     * @return array<int, int> [faction_id => niveau de renom maximal]
     */
    public function maxRenownLevels(): array
    {
        $currencyRows = DB::table('wow_ref_currency_types')->where('max_qty', '>', 0)->get(['id', 'max_qty']);

        $maxQuantities = [];
        foreach ($currencyRows as $currencyRow) {
            $maxQuantities[ReferenceValue::int($currencyRow->id)] = ReferenceValue::int($currencyRow->max_qty);
        }

        $levels = [];
        foreach ($this->rows() as $row) {
            $currencyId = $row['renown_currency_id'];
            if ($currencyId > 0 && isset($maxQuantities[$currencyId])) {
                $levels[$row['id']] = $maxQuantities[$currencyId];
            }
        }

        return $levels;
    }

    /**
     * @return array<int, true>
     */
    public function accountWideIds(): array
    {
        $accountWide = [];
        foreach ($this->rows() as $row) {
            if ($row['renown_currency_id'] > 0 || $row['friendship_rep_id'] > 0) {
                $accountWide[$row['id']] = true;
            }
        }

        foreach ($this->expansions() as $factionId => $expansionId) {
            if ($expansionId >= self::ACCOUNT_WIDE_FROM_EXPANSION) {
                $accountWide[$factionId] = true;
            }
        }

        return $accountWide;
    }

    /**
     * Camp des réputations réservées à une faction.
     *
     * Une réputation est exclusive quand elle porte un plafond pour un camp et pas pour
     * l'autre. Son camp se lit alors par recoupement avec le masque de race de Hurlevent,
     * pris comme référence Alliance : Blizzard ne nomme les camps nulle part.
     *
     * @return array<int, string> [faction_id => 'Alliance'|'Horde']
     */
    public function factions(): array
    {
        $allianceReference = null;
        foreach ($this->rows() as $row) {
            if ($row['id'] === self::STORMWIND_FACTION_ID) {
                $allianceReference = RaceMask::combine($row['race_mask_low'], $row['race_mask_high']);
            }
        }

        if ($allianceReference === null || $allianceReference === 0) {
            return [];
        }

        $factions = [];
        foreach ($this->rows() as $row) {
            if ($row['reputation_max_1'] >= 0) {
                continue;
            }

            if ($row['reputation_max_0'] <= 0) {
                continue;
            }

            $mask = RaceMask::combine($row['race_mask_low'], $row['race_mask_high']);
            if ($mask === null) {
                continue;
            }

            $factions[$row['id']] = ($allianceReference & $mask) !== 0 ? 'Alliance' : 'Horde';
        }

        return $factions;
    }

    /**
     * Factions qui sont de vraies réputations suivies : indexées, rattachées à un parent,
     * et que l'endpoint de réputations de l'API retourne réellement.
     *
     * @return list<array{id: int, name: string, reputation_index: int, parent_faction_id: int, renown_currency_id: int, friendship_rep_id: int, reputation_max_0: int, reputation_max_1: int, race_mask_low: int|null, race_mask_high: int|null}>
     */
    private function reputations(): array
    {
        return array_values(array_filter(
            $this->rows(),
            fn (array $row): bool => $row['reputation_index'] >= 0
                && $row['parent_faction_id'] !== 0
                && ! $this->isExcluded($row['name']),
        ));
    }

    /**
     * @param  array<int, int>  $parents
     */
    private function resolveExpansion(int $factionId, array $parents): ?int
    {
        $current = $factionId;

        for ($depth = 0; $depth < self::MAX_PARENT_DEPTH; $depth++) {
            $parentId = $parents[$current] ?? 0;

            if ($parentId === 0) {
                return null;
            }

            if (isset(self::EXPANSION_HEADERS[$parentId])) {
                return self::EXPANSION_HEADERS[$parentId];
            }

            $current = $parentId;
        }

        return null;
    }

    /**
     * Réputations que l'endpoint `/reputations` de l'API ne retourne jamais : les compter
     * au dénominateur rendrait le 100 % inatteignable.
     */
    private function isExcluded(string $name): bool
    {
        if (str_contains($name, '(parangon)')) {
            return true;
        }

        if (mb_stripos($name, 'gouffre') !== false) {
            return true;
        }

        // \h couvre l'espace insécable de la typographie française de Blizzard.
        if (preg_match('/^Traque\h+saison\h+\d+$/u', $name) === 1) {
            return true;
        }

        return str_contains($name, 'DEPRECATED') || str_contains($name, '[DNT]') || str_contains($name, 'JOUEUR');
    }

    /**
     * @return list<array{id: int, name: string, reputation_index: int, parent_faction_id: int, renown_currency_id: int, friendship_rep_id: int, reputation_max_0: int, reputation_max_1: int, race_mask_low: int|null, race_mask_high: int|null}>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $rows = [];
        foreach (DB::table('wow_ref_faction')->get() as $row) {
            $rows[] = [
                'id' => ReferenceValue::int($row->id),
                'name' => ReferenceValue::string($row->name_lang),
                'reputation_index' => ReferenceValue::int($row->reputation_index),
                'parent_faction_id' => ReferenceValue::int($row->parent_faction_id),
                'renown_currency_id' => ReferenceValue::int($row->renown_currency_id),
                'friendship_rep_id' => ReferenceValue::int($row->friendship_rep_id),
                'reputation_max_0' => ReferenceValue::int($row->reputation_max_0),
                'reputation_max_1' => ReferenceValue::int($row->reputation_max_1),
                'race_mask_low' => ReferenceValue::nullableInt($row->reputation_race_masks0_0),
                'race_mask_high' => ReferenceValue::nullableInt($row->reputation_race_masks0_1),
            ];
        }

        return $this->rows = $rows;
    }
}
