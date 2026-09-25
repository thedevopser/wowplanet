<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

/**
 * Lecture de la faction dans un masque de race DB2.
 *
 * Blizzard a scindé ces masques en deux moitiés de 32 bits le jour où les identifiants
 * de race ont dépassé la largeur d'origine : `FiltRaceMasks_0` / `_1`, `RaceMasks_0` / `_1`,
 * `ReputationRaceMasks0_0` / `_1`. La première moitié porte les bits de poids faible, la
 * seconde ceux de poids fort, et leur recomposition rend exactement les masques complets
 * d'avant la scission — vérifié sur les trois tables du socle.
 *
 * Une moitié lue seule ne veut rien dire : c'est un entier quelconque, souvent négatif,
 * dont l'analyse par bits produit une faction plausible et fausse.
 */
final class RaceMask
{
    private const ALLIANCE = 6130900294268439629;

    private const HORDE = -6184943489809468494;

    /**
     * Positions de bit des races de l'Alliance, décalées de un : humain = 1 donc bit 0.
     *
     * @var list<int>
     */
    private const ALLIANCE_RACE_IDS = [1, 3, 4, 7, 11, 22, 25, 29, 30, 34, 36, 37];

    /**
     * @var list<int>
     */
    private const HORDE_RACE_IDS = [2, 5, 6, 8, 9, 10, 26, 27, 28, 31, 33, 35];

    public static function combine(?int $low, ?int $high): ?int
    {
        if ($low === null || $high === null) {
            return null;
        }

        // La moitié haute est décalée sans être masquée d'abord : les bits que le masque
        // aurait retirés sortent du mot de 64 bits de toute façon, et PHPStan tient un
        // décalage de 32 sur une valeur déjà bornée à 32 bits pour un débordement impossible.
        return ($high << 32) | ($low & 0xFFFFFFFF);
    }

    public static function faction(?int $low, ?int $high): ?string
    {
        $mask = self::combine($low, $high);

        return match (true) {
            $mask === null => null,
            $mask === self::ALLIANCE => 'Alliance',
            $mask === self::HORDE => 'Horde',
            $mask <= 0 => null,
            default => self::factionFromRaceBits($mask),
        };
    }

    /**
     * Faction d'un masque partiel : elle n'est tranchée que si toutes les races autorisées
     * sont du même camp. Un masque mixte, ou dont aucune race n'est placée, ne dit rien.
     */
    private static function factionFromRaceBits(int $mask): ?string
    {
        $hasAlliance = self::matchesAnyRace($mask, self::ALLIANCE_RACE_IDS);
        $hasHorde = self::matchesAnyRace($mask, self::HORDE_RACE_IDS);

        if ($hasAlliance === $hasHorde) {
            return null;
        }

        return $hasAlliance ? 'Alliance' : 'Horde';
    }

    /**
     * @param  list<int>  $raceIds
     */
    private static function matchesAnyRace(int $mask, array $raceIds): bool
    {
        foreach ($raceIds as $raceId) {
            if (($mask & (1 << ($raceId - 1))) !== 0) {
                return true;
            }
        }

        return false;
    }
}
