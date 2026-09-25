<?php

declare(strict_types=1);

namespace App\Domain\Services;

use App\Domain\ValueObjects\CompletionScore;
use App\Domain\ValueObjects\ScoreDimension;
use App\Domain\ValueObjects\ScoreInput;
use App\Domain\ValueObjects\ScoreWeights;

/**
 * Calcule le score de complétion. Service pur, seule implémentation de la formule.
 *
 * Le global est une moyenne pondérée renormalisée sur les seules dimensions applicables :
 * une dimension sans données sort du calcul au lieu de valoir 0.
 *
 * @phpstan-import-type ScoreCollection from ScoreInput
 * @phpstan-import-type ScoreItem from ScoreInput
 * @phpstan-import-type ScoreRaid from ScoreInput
 */
class ScoreCalculator
{
    /**
     * Valeur d'un boss selon son meilleur palier ; il ne compte qu'une fois.
     *
     * @pest-mutate-ignore
     */
    private const RAID_DIFFICULTY_VALUES = [
        'LFR' => 0.25,
        'NORMAL' => 0.50,
        'HEROIC' => 0.75,
        'MYTHIC' => 1.00,
    ];

    public function compute(ScoreInput $scoreInput): CompletionScore
    {
        $stats = [
            'quests' => $this->sumCollections($scoreInput->collections, 'quests'),
            'achievements' => $this->sumCollections($scoreInput->collections, 'achievements'),
            'reputations' => $this->sumCollections($scoreInput->collections, 'reputations'),
            'raids' => $this->sumRaids($scoreInput->raids),
            'mounts' => $this->countItems($scoreInput->mounts),
            'transmog' => $this->sumAppearances($scoreInput->appearances),
            'pets' => $this->countItems($scoreInput->pets),
            'decor' => $this->countItems($scoreInput->decor),
            'professions' => $this->sumProfessions($scoreInput),
        ];

        $dimensions = [];
        $weightedSum = 0.0;
        $applicableWeight = 0.0;

        foreach (ScoreWeights::WEIGHTS as $key => $weight) {
            ['completed' => $completed, 'total' => $total] = $stats[$key];

            $applicable = $total > 0;
            $score = $applicable ? $completed / $total * 100 : 0.0;

            if ($applicable) {
                $weightedSum += $score * $weight;
                $applicableWeight += $weight;
            }

            $dimensions[] = new ScoreDimension(
                key: $key,
                label: ScoreWeights::LABELS[$key],
                weight: $weight,
                completed: $completed,
                total: $total,
                score: $score,
                applicable: $applicable,
            );
        }

        $global = $applicableWeight > 0.0 ? round($weightedSum / $applicableWeight, 1) : 0.0;

        return new CompletionScore(
            version: ScoreWeights::VERSION,
            global: $global,
            rank: $this->rank($global),
            dimensions: $dimensions,
        );
    }

    private function rank(float $global): string
    {
        return match (true) {
            $global >= 90 => 'Légendaire',
            $global >= 75 => 'Épique',
            $global >= 50 => 'Rare',
            $global >= 25 => 'Commun',
            default => 'Débutant',
        };
    }

    /**
     * @param  array<int, ScoreCollection>  $collections
     * @param  'quests'|'achievements'|'reputations'  $type
     * @return array{completed: float, total: int}
     */
    private function sumCollections(array $collections, string $type): array
    {
        $completed = 0;
        $total = 0;

        foreach ($collections as $collection) {
            $stats = $collection[$type] ?? [];
            $completed += $stats['completed'] ?? 0;
            $total += $stats['total'] ?? 0;
        }

        return ['completed' => (float) $completed, 'total' => $total];
    }

    /**
     * @param  list<ScoreItem>  $items
     * @return array{completed: float, total: int}
     */
    private function countItems(array $items): array
    {
        $completed = 0;

        foreach ($items as $item) {
            if ($item['is_completed'] ?? false) {
                $completed++;
            }
        }

        return ['completed' => (float) $completed, 'total' => count($items)];
    }

    /**
     * @param  list<array{slot?: string, total?: int, completed?: int}>  $appearances
     * @return array{completed: float, total: int}
     */
    private function sumAppearances(array $appearances): array
    {
        $completed = 0;
        $total = 0;

        foreach ($appearances as $appearance) {
            $completed += $appearance['completed'] ?? 0;
            $total += $appearance['total'] ?? 0;
        }

        return ['completed' => (float) $completed, 'total' => $total];
    }

    /**
     * `completed` est un équivalent-mythique : 8 boss tués en normal valent 4.
     *
     * @param  list<ScoreRaid>|null  $raids
     * @return array{completed: float, total: int}
     */
    private function sumRaids(?array $raids): array
    {
        if ($raids === null) {
            return ['completed' => 0.0, 'total' => 0];
        }

        $points = 0.0;
        $total = 0;

        foreach ($raids as $raid) {
            /** @var array<int, float> $bestByBoss */
            $bestByBoss = [];
            $bossCount = 0;

            foreach ($raid['modes'] ?? [] as $mode) {
                $bossCount = max($bossCount, $mode['total_count'] ?? 0);

                $value = self::RAID_DIFFICULTY_VALUES[$mode['difficulty_type'] ?? ''] ?? 0.0;
                if ($value === 0.0) {
                    continue;
                }

                foreach ($mode['encounters'] ?? [] as $encounter) {
                    $bossId = $encounter['id'] ?? null;
                    if ($bossId === null) {
                        continue;
                    }

                    $bestByBoss[$bossId] = max($bestByBoss[$bossId] ?? 0.0, $value);
                }
            }

            $points += array_sum($bestByBoss);
            $total += $bossCount;
        }

        // Les paliers valent des quarts : deux décimales restent exactes.
        return ['completed' => round($points, 2), 'total' => $total];
    }

    /**
     * Le meilleur ratio métier du compte prime sur le cumul du personnage courant.
     *
     * @return array{completed: float, total: int}
     */
    private function sumProfessions(ScoreInput $scoreInput): array
    {
        $best = $scoreInput->bestProfessionStats;
        if ($best !== null && $best['total'] > 0) {
            return ['completed' => (float) $best['completed'], 'total' => $best['total']];
        }

        $recipeCompleted = 0;
        $recipeTotal = 0;
        $skillPoints = 0;
        $skillMax = 0;

        foreach ($scoreInput->professions as $profession) {
            foreach ($profession['expansions'] ?? [] as $expansion) {
                $recipeCompleted += $expansion['completed'] ?? 0;
                $recipeTotal += $expansion['total'] ?? 0;
                $skillPoints += $expansion['skill_points'] ?? 0;
                $skillMax += $expansion['max_skill_points'] ?? 0;
            }
        }

        // Sans référentiel de recettes, les points de compétence sont la seule mesure.
        if ($recipeTotal > 0) {
            return ['completed' => (float) $recipeCompleted, 'total' => $recipeTotal];
        }

        return ['completed' => (float) $skillPoints, 'total' => $skillMax];
    }
}
