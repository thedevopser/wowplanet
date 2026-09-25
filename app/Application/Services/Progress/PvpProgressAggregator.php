<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Domain\Services\PvpBracketClassifier;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpBracketResponse;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpMatchStatistics;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpSummaryResponse;

/**
 * Normalise le PvP d'un personnage : résumé (`pvp-summary`) et détail par bracket
 * (`pvp-bracket/{slug}`), le tout regroupé par mode de jeu.
 *
 * Service pur : aucun appel API, aucune dépendance. L'orchestration des requêtes
 * appartient à PvpProfileService.
 *
 * @phpstan-type PvpStatistics array{played: int, won: int, lost: int, win_rate: float}
 * @phpstan-type PvpBracket array{slug: string, group: string, label: string, spec: string|null,
 *     rating: int, season_id: int, tier_name: string|null, tier_icon_url: string|null,
 *     played: int, won: int, lost: int, win_rate: float,
 *     weekly: array{played: int, won: int, lost: int}}
 * @phpstan-type PvpGroup array{key: string, label: string, brackets: list<PvpBracket>}
 * @phpstan-type PvpProfile array{season_id: int, honor_level: int, honorable_kills: int, best_rating: int, battlegrounds: PvpStatistics, groups: list<PvpGroup>}
 * @phpstan-type PvpTierMap array<int, array{name?: string, icon_url?: string}>
 */
class PvpProgressAggregator
{
    /**
     * Groupes dont les brackets se classent par rating décroissant (un par spécialisation).
     *
     * @pest-mutate-ignore
     */
    private const RATING_SORTED_GROUPS = ['shuffle', 'blitz'];

    public function __construct(
        private readonly PvpBracketClassifier $pvpBracketClassifier = new PvpBracketClassifier,
    ) {}

    /**
     * @param  array<string, PvpBracketResponse>  $bracketResponses  Indexées par slug
     * @param  PvpTierMap  $tiers  Paliers résolus, indexés par id
     * @param  int  $currentSeasonId  0 si la saison courante n'a pas pu être résolue
     * @param  array<string, string>  $specNames  Spécialisations FR par slug, en repli si l'API n'en fournit pas
     * @return PvpProfile|null
     */
    public function aggregate(PvpSummaryResponse $pvpSummaryResponse, array $bracketResponses, array $tiers, int $currentSeasonId, array $specNames = []): ?array
    {
        if ($pvpSummaryResponse->isEmpty) {
            return null;
        }

        $brackets = [];
        $seasonId = $currentSeasonId;
        $bestRating = 0;

        foreach ($bracketResponses as $slug => $pvpBracketResponse) {
            $bracket = $this->buildBracket((string) $slug, $pvpBracketResponse, $tiers, $currentSeasonId, $specNames[$slug] ?? null);

            if ($bracket === null) {
                continue;
            }

            if ($seasonId === 0) {
                $seasonId = $bracket['season_id'];
            }

            $bestRating = max($bestRating, $bracket['rating']);
            $brackets[] = $bracket;
        }

        $battlegrounds = $this->buildBattlegrounds($pvpSummaryResponse->battlegroundStatistics);

        if ($brackets === [] && $pvpSummaryResponse->honorLevel === 0 && $pvpSummaryResponse->honorableKills === 0 && $battlegrounds['played'] === 0) {
            return null;
        }

        return [
            'season_id' => $seasonId,
            'honor_level' => $pvpSummaryResponse->honorLevel,
            'honorable_kills' => $pvpSummaryResponse->honorableKills,
            'best_rating' => $bestRating,
            'battlegrounds' => $battlegrounds,
            'groups' => $this->buildGroups($brackets),
        ];
    }

    /**
     * @param  PvpTierMap  $tiers
     * @return PvpBracket|null
     */
    private function buildBracket(string $slug, PvpBracketResponse $pvpBracketResponse, array $tiers, int $currentSeasonId, ?string $fallbackSpec = null): ?array
    {
        if ($pvpBracketResponse->isEmpty) {
            return null;
        }

        $seasonId = $pvpBracketResponse->seasonId;

        // Un bracket d'une saison antérieure porte un rating périmé : on l'écarte.
        // Saison courante inconnue (index API indisponible) → on n'écarte rien.
        if ($currentSeasonId > 0 && $seasonId !== $currentSeasonId) {
            return null;
        }

        $rating = $pvpBracketResponse->rating;
        $statistics = $this->buildStatistics($pvpBracketResponse->seasonStatistics);

        // Ni rating ni match joué : rien à montrer.
        if ($rating === 0 && $statistics['played'] === 0) {
            return null;
        }

        $tierData = $tiers[$pvpBracketResponse->tierId ?? 0] ?? [];
        $specializationName = $pvpBracketResponse->specializationName;
        $spec = $specializationName !== null && $specializationName !== '' ? $specializationName : $fallbackSpec;
        $weekly = $pvpBracketResponse->weeklyStatistics;

        return [
            'slug' => $slug,
            'group' => $this->pvpBracketClassifier->groupFor($slug),
            'label' => $this->pvpBracketClassifier->labelFor($slug, $spec),
            'spec' => $spec,
            'rating' => $rating,
            'season_id' => $seasonId,
            'tier_name' => $this->nonEmpty($tierData['name'] ?? null),
            'tier_icon_url' => $this->nonEmpty($tierData['icon_url'] ?? null),
            'played' => $statistics['played'],
            'won' => $statistics['won'],
            'lost' => $statistics['lost'],
            'win_rate' => $statistics['win_rate'],
            'weekly' => [
                'played' => $weekly->played,
                'won' => $weekly->won,
                'lost' => $weekly->lost,
            ],
        ];
    }

    private function nonEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @return PvpStatistics
     */
    private function buildStatistics(PvpMatchStatistics $pvpMatchStatistics): array
    {
        $played = $pvpMatchStatistics->played;
        $won = $pvpMatchStatistics->won;

        return [
            'played' => $played,
            'won' => $won,
            'lost' => $pvpMatchStatistics->lost,
            'win_rate' => $played > 0 ? round($won / $played * 100, 1) : 0.0,
        ];
    }

    /**
     * Cumule les statistiques de champs de bataille non cotés, seule progression
     * PvP visible pour les personnages qui ne jouent aucun mode coté.
     *
     * @param  list<PvpMatchStatistics>  $mapStatistics
     * @return PvpStatistics
     */
    private function buildBattlegrounds(array $mapStatistics): array
    {
        return $this->buildStatistics(new PvpMatchStatistics(
            played: array_sum(array_map(static fn (PvpMatchStatistics $pvpMatchStatistics): int => $pvpMatchStatistics->played, $mapStatistics)),
            won: array_sum(array_map(static fn (PvpMatchStatistics $pvpMatchStatistics): int => $pvpMatchStatistics->won, $mapStatistics)),
            lost: array_sum(array_map(static fn (PvpMatchStatistics $pvpMatchStatistics): int => $pvpMatchStatistics->lost, $mapStatistics)),
        ));
    }

    /**
     * @param  list<PvpBracket>  $brackets
     * @return list<PvpGroup>
     */
    private function buildGroups(array $brackets): array
    {
        $groups = [];

        foreach (PvpBracketClassifier::GROUPS as $key => $label) {
            $groupBrackets = array_values(array_filter($brackets, fn (array $bracket): bool => $bracket['group'] === $key));

            if ($groupBrackets === []) {
                continue;
            }

            if (in_array($key, self::RATING_SORTED_GROUPS, true)) {
                usort($groupBrackets, fn (array $a, array $b): int => $b['rating'] <=> $a['rating']);
            } else {
                usort($groupBrackets, fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));
            }

            $groups[] = ['key' => $key, 'label' => $label, 'brackets' => $groupBrackets];
        }

        return $groups;
    }
}
