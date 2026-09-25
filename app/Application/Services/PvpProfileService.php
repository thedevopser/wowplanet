<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Services\Progress\PvpProgressAggregator;
use App\Domain\Services\PvpBracketClassifier;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\FetchesProfileEndpoints;
use App\Infrastructure\Blizzard\Responses\MediaIconResponse;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpBracketResponse;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpSummaryResponse;
use App\Infrastructure\Blizzard\Responses\Pvp\PvpTierResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Récupère le PvP d'un personnage à la volée : aucune table, aucun import.
 *
 * Le PvP est chargé paresseusement depuis l'onglet du profil, et non avec le reste
 * du profil : il concerne une minorité de personnages et ne doit rien coûter aux autres.
 *
 * @phpstan-import-type PvpProfile from PvpProgressAggregator
 *
 * @phpstan-type PvpTier array{name: string, icon_url: string}
 */
class PvpProfileService
{
    use FetchesProfileEndpoints;

    /** Ratings volatils : TTL bien plus court que les collections. */
    private const PROFILE_TTL_S = 900;

    /** Les paliers d'une saison ne bougent pas. */
    private const TIER_TTL_S = 2592000;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly PvpProgressAggregator $pvpProgressAggregator,
        private readonly PvpBracketClassifier $pvpBracketClassifier,
        private readonly PlayableNameService $playableNameService,
    ) {}

    /**
     * @return PvpProfile|null
     */
    public function getForCharacter(string $realm, string $name): ?array
    {
        $realm = mb_strtolower($realm);
        $name = mb_strtolower($name);

        // L'absence de PvP est le cas majoritaire : on l'enveloppe pour la mettre
        // en cache aussi (Cache::remember ne mémorise pas un null).
        $cached = Cache::remember(
            sprintf('pvp_profile:%s:%s', $realm, $name),
            self::PROFILE_TTL_S,
            fn (): array => ['pvp' => $this->build($realm, $name)],
        );

        return $cached['pvp'];
    }

    /**
     * @return PvpProfile|null
     */
    private function build(string $realm, string $name): ?array
    {
        $base = sprintf('profile/wow/character/%s/%s', $realm, $name);

        try {
            $pvpSummaryResponse = PvpSummaryResponse::fromPayload(
                ResponsePayload::forEndpoint($base.'/pvp-summary', $this->blizzardApiClient->get($base.'/pvp-summary')),
            );
        } catch (\Throwable $throwable) {
            Log::debug('PvP summary fetch failed: '.$throwable->getMessage());

            return null;
        }

        $bracketResponses = $this->fetchBrackets($base, $pvpSummaryResponse->bracketSlugs);

        return $this->pvpProgressAggregator->aggregate(
            $pvpSummaryResponse,
            $bracketResponses,
            $this->fetchTiers($bracketResponses),
            $this->currentSeasonId(),
            $this->frenchSpecNames(array_keys($bracketResponses)),
        );
    }

    /**
     * Repli FR pour les brackets par spécialisation dont l'API de profil ne
     * renvoie pas `specialization` : sans lui, le libellé retomberait sur le
     * slug anglais (« Priest Shadow »).
     *
     * @param  list<array-key>  $slugs
     * @return array<string, string>
     */
    private function frenchSpecNames(array $slugs): array
    {
        $names = [];

        foreach ($slugs as $slug) {
            $specSlugs = $this->pvpBracketClassifier->specSlugsFor((string) $slug);

            if ($specSlugs === null) {
                continue;
            }

            $label = $this->playableNameService->labelFor($specSlugs[0], $specSlugs[1]);

            if ($label !== null) {
                $names[(string) $slug] = $label;
            }
        }

        return $names;
    }

    /**
     * @param  list<string>  $slugs
     * @return array<string, PvpBracketResponse>
     */
    private function fetchBrackets(string $base, array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $endpoints = [];
        foreach ($slugs as $slug) {
            $endpoints[$slug] = ['endpoint' => $base.'/pvp-bracket/'.$slug, 'query' => []];
        }

        return array_map(PvpBracketResponse::fromPayload(...), $this->fetchPayloadsAsync($endpoints));
    }

    /**
     * Résout nom FR et icône des paliers rencontrés. Chaque palier est caché
     * séparément : deux personnages du même rang ne coûtent qu'un seul appel.
     *
     * @param  array<string, PvpBracketResponse>  $bracketResponses
     * @return array<int, PvpTier>
     */
    private function fetchTiers(array $bracketResponses): array
    {
        $tiers = [];
        $missing = [];

        foreach ($bracketResponses as $bracketResponse) {
            $tierId = $bracketResponse->tierId ?? 0;
            if ($tierId === 0) {
                continue;
            }

            if (isset($tiers[$tierId])) {
                continue;
            }

            if (isset($missing[$tierId])) {
                continue;
            }

            $cached = $this->cachedTier($tierId);

            if ($cached !== null) {
                $tiers[$tierId] = $cached;

                continue;
            }

            $missing[$tierId] = true;
        }

        if ($missing === []) {
            return $tiers;
        }

        $namespace = 'static-'.$this->blizzardApiClient->getRegion();
        $endpoints = [];
        foreach (array_keys($missing) as $tierId) {
            $endpoints['tier_'.$tierId] = ['endpoint' => 'data/wow/pvp-tier/'.$tierId, 'query' => ['namespace' => $namespace]];
            $endpoints['media_'.$tierId] = ['endpoint' => 'data/wow/media/pvp-tier/'.$tierId, 'query' => ['namespace' => $namespace]];
        }

        $payloads = $this->fetchPayloadsAsync($endpoints);

        foreach (array_keys($missing) as $tierId) {
            $tier = [
                'name' => PvpTierResponse::fromPayload($payloads['tier_'.$tierId])->name ?? '',
                'icon_url' => MediaIconResponse::fromPayload($payloads['media_'.$tierId])->iconUrl ?? '',
            ];

            if ($tier['name'] === '' && $tier['icon_url'] === '') {
                continue;
            }

            Cache::put('pvp_tier:'.$tierId, $tier, self::TIER_TTL_S);
            $tiers[$tierId] = $tier;
        }

        return $tiers;
    }

    /**
     * @return PvpTier|null
     */
    private function cachedTier(int $tierId): ?array
    {
        $cached = Cache::get('pvp_tier:'.$tierId);

        if (! is_array($cached) || ! is_string($cached['name'] ?? null) || ! is_string($cached['icon_url'] ?? null)) {
            return null;
        }

        return ['name' => $cached['name'], 'icon_url' => $cached['icon_url']];
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
