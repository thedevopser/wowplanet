<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\Progress\TalentAggregator;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Responses\MediaIconResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use App\Infrastructure\Blizzard\Responses\Talent\CharacterSpecializationsResponse;
use App\Infrastructure\Blizzard\Responses\Talent\PlayableSpecializationResponse;
use App\Infrastructure\Blizzard\Responses\Talent\TalentTreeResponse;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TalentController extends Controller
{
    private const int STATIC_DATA_TTL_S = 604800;

    private const int SPELL_ICON_TTL_S = 2592000;

    private const int SPELL_ICON_BATCH_SIZE = 50;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly TalentAggregator $talentAggregator,
    ) {}

    public function show(string $realm, string $name): JsonResponse
    {
        $realm = mb_strtolower($realm);
        $name = mb_strtolower($name);

        try {
            $base = sprintf('profile/wow/character/%s/%s', $realm, $name);

            $characterSpecializationsResponse = CharacterSpecializationsResponse::fromPayload(
                $this->blizzardApiClient->getResponse($base.'/specializations'),
            );
            $specId = $characterSpecializationsResponse->activeSpecializationId ?? 0;

            if ($specId === 0) {
                return response()->json(['error' => 'No active specialization'], 404);
            }

            $talentTreeId = $this->resolveTalentTreeId($specId);

            if ($talentTreeId === 0) {
                return response()->json(['error' => 'Talent tree not found'], 404);
            }

            $talentTree = $this->fetchTalentTree($talentTreeId, $specId);

            return response()->json($this->talentAggregator->aggregate(
                $characterSpecializationsResponse,
                $talentTree['tree'],
                $talentTree['icons'],
            ));
        } catch (\Exception $exception) {
            Log::error('Failed to fetch talents', [
                'realm' => $realm,
                'name' => $name,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to fetch talent data'], 500);
        }
    }

    private function resolveTalentTreeId(int $specId): int
    {
        // The Redis store keeps numbers unserialized: the id comes back as a string.
        return (int) Cache::remember(
            sprintf('playable_spec:%d', $specId),
            self::STATIC_DATA_TTL_S,
            fn (): int => PlayableSpecializationResponse::fromPayload($this->blizzardApiClient->getResponse(
                sprintf('data/wow/playable-specialization/%d', $specId),
                ['namespace' => 'static-'.$this->blizzardApiClient->getRegion()],
            ))->talentTreeId ?? 0,
        );
    }

    /**
     * Le cache garde la réponse décodée et les icônes de ses sorts, pas des objets : son contenu
     * se relit à l'identique sur le store `array` comme sur Redis, et survit à un changement de classe.
     *
     * @return array{tree: TalentTreeResponse, icons: array<int, string>}
     */
    private function fetchTalentTree(int $talentTreeId, int $specId): array
    {
        $endpoint = sprintf('data/wow/talent-tree/%d/playable-specialization/%d', $talentTreeId, $specId);
        $region = $this->blizzardApiClient->getRegion();

        $cached = Cache::remember(
            sprintf('talent_tree:v2:%d:%d', $talentTreeId, $specId),
            self::STATIC_DATA_TTL_S,
            function () use ($endpoint, $region): array {
                $tree = $this->blizzardApiClient->get($endpoint, ['namespace' => 'static-'.$region]);
                $spellIds = TalentTreeResponse::fromPayload(ResponsePayload::forEndpoint($endpoint, $tree))->spellIds();

                return ['tree' => $tree, 'icons' => $this->fetchSpellIcons($spellIds, $region)];
            },
        );

        return [
            'tree' => TalentTreeResponse::fromPayload(ResponsePayload::forEndpoint($endpoint, $cached['tree'])),
            'icons' => $cached['icons'],
        ];
    }

    /**
     * @param  list<int>  $spellIds
     * @return array<int, string>
     */
    private function fetchSpellIcons(array $spellIds, string $region): array
    {
        $iconMap = [];

        foreach (array_chunk($spellIds, self::SPELL_ICON_BATCH_SIZE) as $chunk) {
            $promises = [];
            foreach ($chunk as $spellId) {
                $cached = Cache::get(sprintf('spell_icon:%d', $spellId));
                if (is_string($cached)) {
                    $iconMap[$spellId] = $cached;

                    continue;
                }

                $promises[$spellId] = $this->blizzardApiClient->getAsync(
                    sprintf('data/wow/media/spell/%d', $spellId),
                    ['namespace' => 'static-'.$region],
                );
            }

            if ($promises === []) {
                continue;
            }

            /** @var array<int, array{state: string, value?: \Psr\Http\Message\ResponseInterface}> $settled */
            $settled = Utils::settle($promises)->wait();

            foreach ($settled as $spellId => $result) {
                if ($result['state'] !== 'fulfilled') {
                    continue;
                }

                if (! isset($result['value'])) {
                    continue;
                }

                try {
                    $iconUrl = $this->readSpellIcon($spellId, $result['value']);
                } catch (\Throwable $throwable) {
                    Log::debug('Spell icon fetch failed', ['spell_id' => $spellId, 'exception' => $throwable->getMessage()]);

                    continue;
                }

                if ($iconUrl !== null) {
                    $iconMap[$spellId] = $iconUrl;
                    Cache::put(sprintf('spell_icon:%d', $spellId), $iconUrl, self::SPELL_ICON_TTL_S);
                }
            }
        }

        return $iconMap;
    }

    private function readSpellIcon(int $spellId, \Psr\Http\Message\ResponseInterface $response): ?string
    {
        $decoded = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        $response->getBody()->close();

        return MediaIconResponse::fromPayload(ResponsePayload::forEndpoint(
            sprintf('data/wow/media/spell/%d', $spellId),
            is_array($decoded) ? $decoded : [],
        ))->iconUrl;
    }
}
