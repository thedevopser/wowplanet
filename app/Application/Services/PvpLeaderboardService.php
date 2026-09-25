<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Services\PvpBracketClassifier;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpLeaderboardEntry;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpLeaderboardIndexResponse;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpLeaderboardResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Classements PvP officiels, servis en direct depuis l'API Blizzard.
 *
 * Aucune table, aucun classement reconstitué à partir des personnages déjà vus :
 * Blizzard publie lui-même le classement complet de la saison par bracket. Seules
 * les colonnes affichées sont mises en cache, le reste de la réponse (plusieurs
 * milliers d'entrées très bavardes) est jeté à la lecture.
 *
 * @phpstan-type LeaderboardRow array{rank: int, name: string, realm: string, realm_slug: string, faction: string, rating: int, won: int, lost: int}
 * @phpstan-type LeaderboardPage array{bracket: string, label: string, seasonId: int, entries: list<LeaderboardRow>, total: int, currentPage: int, lastPage: int, unavailable: bool}
 */
class PvpLeaderboardService
{
    public const DEFAULT_BRACKET = '3v3';

    private const PER_PAGE = 50;

    private const INDEX_TTL_S = 86400;

    private const LEADERBOARD_TTL_S = 3600;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly PvpBracketClassifier $pvpBracketClassifier,
        private readonly PlayableNameService $playableNameService,
    ) {}

    /**
     * Brackets classés de la saison, regroupés par mode.
     *
     * @return list<array{key: string, label: string, brackets: list<array{slug: string, label: string, short: string}>}>
     */
    public function availableBrackets(): array
    {
        $slugs = $this->bracketSlugs();

        if ($slugs === []) {
            return [];
        }

        $groups = [];
        foreach (PvpBracketClassifier::GROUPS as $key => $label) {
            $brackets = [];
            foreach ($slugs as $slug) {
                if ($this->pvpBracketClassifier->groupFor($slug) === $key) {
                    $spec = $this->frenchSpecFor($slug);

                    $brackets[] = [
                        'slug' => $slug,
                        'label' => $this->pvpBracketClassifier->labelFor($slug, $spec),
                        'short' => $this->pvpBracketClassifier->shortLabelFor($slug, $spec),
                    ];
                }
            }

            if ($brackets === []) {
                continue;
            }

            // « Toutes spés » d'abord : c'est le classement par défaut du mode.
            usort($brackets, fn (array $a, array $b): int => [$this->isOverall($a['slug']) ? 0 : 1, $a['slug']]
                <=> [$this->isOverall($b['slug']) ? 0 : 1, $b['slug']]);
            $groups[] = ['key' => $key, 'label' => $label, 'brackets' => $brackets];
        }

        return $groups;
    }

    /**
     * @return LeaderboardPage
     */
    public function leaderboard(string $bracket, int $page = 1, ?string $search = null): array
    {
        $bracket = $this->resolveBracket($bracket);
        $seasonId = $this->currentSeasonId();

        $entries = $seasonId > 0 ? $this->entriesFor($seasonId, $bracket) : null;

        if ($entries === null) {
            return $this->emptyResult($bracket, $seasonId, true);
        }

        $entries = $this->filter($entries, $search);
        $total = count($entries);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $currentPage = min(max($page, 1), $lastPage);

        return [
            'bracket' => $bracket,
            'label' => $this->pvpBracketClassifier->labelFor($bracket, $this->frenchSpecFor($bracket)),
            'seasonId' => $seasonId,
            'entries' => array_slice($entries, ($currentPage - 1) * self::PER_PAGE, self::PER_PAGE),
            'total' => $total,
            'currentPage' => $currentPage,
            'lastPage' => $lastPage,
            'unavailable' => false,
        ];
    }

    /**
     * L'index des classements ne nomme ses brackets qu'en anglais : on retraduit
     * « deathknight-blood » via les index de classes et spécialisations.
     */
    private function frenchSpecFor(string $slug): ?string
    {
        $specSlugs = $this->pvpBracketClassifier->specSlugsFor($slug);

        if ($specSlugs === null) {
            return null;
        }

        return $this->playableNameService->labelFor($specSlugs[0], $specSlugs[1]);
    }

    private function isOverall(string $slug): bool
    {
        return str_ends_with($slug, '-overall');
    }

    /**
     * Un slug inconnu de l'index retombe sur le bracket par défaut. Quand l'index
     * est indisponible, on se contente de rejeter ce qui ne ressemble pas à un slug.
     */
    private function resolveBracket(string $bracket): string
    {
        $slugs = $this->bracketSlugs();

        if ($slugs !== []) {
            return in_array($bracket, $slugs, true) ? $bracket : self::DEFAULT_BRACKET;
        }

        return preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $bracket) === 1 ? $bracket : self::DEFAULT_BRACKET;
    }

    /**
     * @return list<string>
     */
    private function bracketSlugs(): array
    {
        $seasonId = $this->currentSeasonId();

        if ($seasonId === 0) {
            return [];
        }

        return Cache::remember(
            'pvp_leaderboard_index:'.$seasonId,
            self::INDEX_TTL_S,
            function () use ($seasonId): array {
                $endpoint = sprintf('data/wow/pvp-season/%d/pvp-leaderboard/index', $seasonId);

                try {
                    $response = $this->blizzardApiClient->get($endpoint, ['namespace' => 'dynamic-'.$this->blizzardApiClient->getRegion()]);
                } catch (\Throwable $throwable) {
                    Log::debug('PvP leaderboard index fetch failed: '.$throwable->getMessage());

                    return [];
                }

                return PvpLeaderboardIndexResponse::fromPayload(ResponsePayload::forEndpoint($endpoint, $response))->slugs;
            },
        );
    }

    /**
     * @return list<LeaderboardRow>|null null quand le classement est indisponible
     */
    private function entriesFor(int $seasonId, string $bracket): ?array
    {
        $cacheKey = sprintf('pvp_leaderboard:%d:%s', $seasonId, $bracket);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            /** @var list<LeaderboardRow> $cached written only by this method, below */
            return $cached;
        }

        $endpoint = sprintf('data/wow/pvp-season/%d/pvp-leaderboard/%s', $seasonId, $bracket);

        try {
            $response = $this->blizzardApiClient->get($endpoint, ['namespace' => 'dynamic-'.$this->blizzardApiClient->getRegion()]);
        } catch (\Throwable $throwable) {
            Log::warning('PvP leaderboard fetch failed', ['bracket' => $bracket, 'exception' => $throwable->getMessage()]);

            return null;
        }

        $entries = array_map(
            $this->buildEntry(...),
            PvpLeaderboardResponse::fromPayload(ResponsePayload::forEndpoint($endpoint, $response))->entries,
        );

        Cache::put($cacheKey, $entries, self::LEADERBOARD_TTL_S);

        return $entries;
    }

    /**
     * @return LeaderboardRow
     */
    private function buildEntry(PvpLeaderboardEntry $pvpLeaderboardEntry): array
    {
        $realmSlug = $pvpLeaderboardEntry->realmSlug ?? '';

        return [
            'rank' => $pvpLeaderboardEntry->rank,
            'name' => $pvpLeaderboardEntry->characterName ?? '',
            'realm' => ucwords(str_replace('-', ' ', $realmSlug)),
            'realm_slug' => $realmSlug,
            'faction' => $pvpLeaderboardEntry->factionType ?? '',
            'rating' => $pvpLeaderboardEntry->rating,
            'won' => $pvpLeaderboardEntry->statistics->won,
            'lost' => $pvpLeaderboardEntry->statistics->lost,
        ];
    }

    /**
     * @param  list<LeaderboardRow>  $entries
     * @return list<LeaderboardRow>
     */
    private function filter(array $entries, ?string $search): array
    {
        $needle = mb_strtolower(trim((string) $search));

        if ($needle === '') {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            static fn (array $entry): bool => str_contains(mb_strtolower((string) $entry['name']), $needle) || str_contains(mb_strtolower((string) $entry['realm']), $needle),
        ));
    }

    /**
     * @return LeaderboardPage
     */
    private function emptyResult(string $bracket, int $seasonId, bool $unavailable): array
    {
        return [
            'bracket' => $bracket,
            'label' => $this->pvpBracketClassifier->labelFor($bracket, $this->frenchSpecFor($bracket)),
            'seasonId' => $seasonId,
            'entries' => [],
            'total' => 0,
            'currentPage' => 1,
            'lastPage' => 1,
            'unavailable' => $unavailable,
        ];
    }

    private function currentSeasonId(): int
    {
        try {
            return $this->blizzardApiClient->getCurrentPvpSeasonId();
        } catch (\Throwable $throwable) {
            Log::debug('PvP season index fetch failed: '.$throwable->getMessage());

            return 0;
        }
    }
}
