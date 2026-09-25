<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\Progress\AchievementProgressAggregator;
use App\Application\Services\Progress\CollectionProgressAggregator;
use App\Application\Services\Progress\EquipmentAggregator;
use App\Application\Services\Progress\ProfessionProgressAggregator;
use App\Application\Services\Progress\QuestProgressAggregator;
use App\Application\Services\Progress\RaidProgressAggregator;
use App\Application\Services\Progress\ReputationProgressAggregator;
use App\Domain\Services\ScoreCalculator;
use App\Domain\ValueObjects\ExpansionId;
use App\Domain\ValueObjects\ScoreInput;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\FetchesProfileEndpoints;
use App\Infrastructure\Blizzard\Responses\MediaIconResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterAchievementsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterDecorResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterEquipmentResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterMediaResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterMountsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterPetsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterRaidsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterSummaryResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterTransmogsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CompletedQuestsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\JournalInstanceResponse;
use App\Infrastructure\Blizzard\Responses\Profile\MythicKeystoneSeasonResponse;
use App\Infrastructure\Blizzard\Responses\Profile\MythicRun;
use App\Infrastructure\Blizzard\Responses\Profile\MythicRunMember;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-import-type QuestProgress from QuestProgressAggregator
 * @phpstan-import-type AchievementProgress from AchievementProgressAggregator
 * @phpstan-import-type ReputationProgress from ReputationProgressAggregator
 * @phpstan-import-type RaidProgress from RaidProgressAggregator
 * @phpstan-import-type ExpansionCollection from CharacterProfileDTO
 * @phpstan-import-type MythicKeystoneArray from CharacterProfileDTO
 * @phpstan-import-type MythicRunArray from CharacterProfileDTO
 */
class CharacterProfileService
{
    use FetchesProfileEndpoints;

    private const int RAID_NAMES_TTL_S = 604800;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly QuestProgressAggregator $questProgressAggregator,
        private readonly AchievementProgressAggregator $achievementProgressAggregator,
        private readonly CollectionProgressAggregator $collectionProgressAggregator,
        private readonly ProfessionProgressAggregator $professionProgressAggregator,
        private readonly ReputationProgressAggregator $reputationProgressAggregator,
        private readonly RaidProgressAggregator $raidProgressAggregator,
        private readonly EquipmentAggregator $equipmentAggregator,
        private readonly UserCharacterService $userCharacterService,
        private readonly ScoreCalculator $scoreCalculator,
    ) {}

    public function getProfile(string $realm, string $name): CharacterProfileDTO
    {
        $base = sprintf('profile/wow/character/%s/%s', mb_strtolower($realm), mb_strtolower($name));

        $characterSummaryResponse = CharacterSummaryResponse::fromPayload($this->blizzardApiClient->getResponse($base));
        $payloads = $this->fetchPayloadsAsync($this->profileEndpoints($base));
        $mythicSeason = $this->fetchCurrentMythicSeason($base);
        $characterEquipmentResponse = CharacterEquipmentResponse::fromPayload($payloads['equipment']);
        $equipmentIconMap = $this->fetchEquipmentIcons($characterEquipmentResponse);
        $characterRaidsResponse = CharacterRaidsResponse::fromPayload($payloads['raids']);
        $raidNames = $this->fetchRaidNames($characterRaidsResponse);

        $faction = $characterSummaryResponse->factionName ?? '';
        $completedQuestsResponse = CompletedQuestsResponse::fromPayload($payloads['quests']);
        $characterAchievementsResponse = CharacterAchievementsResponse::fromPayload($payloads['achievements']);
        $mountIds = CharacterMountsResponse::fromPayload($payloads['mounts'])->mountIds;
        $petIds = CharacterPetsResponse::fromPayload($payloads['pets'])->speciesIds;
        $decorIds = CharacterDecorResponse::fromPayload($payloads['decor'])->decorIds;

        $collections = $this->mergeCollections(
            $this->questProgressAggregator->aggregate($completedQuestsResponse->questIds, $faction),
            $this->achievementProgressAggregator->aggregate($characterAchievementsResponse->completedAchievementIds),
            $this->reputationProgressAggregator->aggregate(CharacterReputationsResponse::fromPayload($payloads['reputations']), $faction),
        );
        $mounts = $this->collectionProgressAggregator->aggregateMounts($mountIds);
        $pets = $this->collectionProgressAggregator->aggregatePets($petIds);
        $decor = $this->collectionProgressAggregator->aggregateDecor($decorIds);
        $appearances = $this->collectionProgressAggregator->aggregateAppearances(
            CharacterTransmogsResponse::fromPayload($payloads['transmogs'])->appearanceIds,
        );
        $professions = $this->professionProgressAggregator->aggregate(CharacterProfessionsResponse::fromPayload($payloads['professions']), $faction);
        $equipment = $this->equipmentAggregator->aggregate($characterEquipmentResponse, $equipmentIconMap);
        $raids = $this->raidProgressAggregator->aggregate($characterRaidsResponse, $raidNames);

        $classId = $characterSummaryResponse->classId ?? 0;

        return new CharacterProfileDTO(
            name: $characterSummaryResponse->name ?? '',
            realm: $characterSummaryResponse->realmName ?? '',
            race: $characterSummaryResponse->raceName ?? '',
            class: $characterSummaryResponse->className ?? '',
            classId: $classId,
            level: $characterSummaryResponse->level ?? 0,
            ilvl: $characterSummaryResponse->equippedItemLevel ?? 0,
            faction: $faction,
            avatarUrl: CharacterMediaResponse::fromPayload($payloads['media'])->avatarUrl ?? '',
            classIconUrl: $this->userCharacterService->getClassIcons()[$classId] ?? '',
            collections: $collections,
            mountsCount: count($mountIds),
            petsCount: count($petIds),
            achievementPoints: $characterAchievementsResponse->totalPoints ?? 0,
            guild: $characterSummaryResponse->guildName ?? '',
            mounts: $mounts,
            pets: $pets,
            professions: $professions,
            decorCount: count($decorIds),
            decor: $decor,
            exaltedCount: $this->countExalted($collections),
            mythicKeystone: $this->buildMythicKeystoneData($payloads['mythicKeystone'], $mythicSeason),
            completedQuestIds: $completedQuestsResponse->questIds,
            completedAchievementIds: $characterAchievementsResponse->completedAchievementIds,
            equipment: $equipment,
            appearances: $appearances,
            appearancesCount: array_sum(array_column($appearances, 'completed')),
            raids: $raids,
            raidsCount: $this->countRaidBosses($raids),
            score: $this->scoreCalculator->compute(new ScoreInput(
                collections: $collections,
                mounts: $mounts,
                pets: $pets,
                decor: $decor,
                professions: $professions,
                appearances: $appearances,
                raids: $raids,
            )),
        );
    }

    /**
     * @return array<string, array{endpoint: string, query: array{}}>
     */
    private function profileEndpoints(string $base): array
    {
        $paths = [
            'media' => '/character-media',
            'quests' => '/quests/completed',
            'achievements' => '/achievements',
            'mounts' => '/collections/mounts',
            'pets' => '/collections/pets',
            'professions' => '/professions',
            'reputations' => '/reputations',
            'decor' => '/collections/decor',
            'transmogs' => '/collections/transmogs',
            'mythicKeystone' => '/mythic-keystone-profile',
            'raids' => '/encounters/raids',
            'equipment' => '/equipment',
        ];

        return array_map(static fn (string $path): array => ['endpoint' => $base.$path, 'query' => []], $paths);
    }

    /**
     * @param  array<int, QuestProgress>  $questProgress
     * @param  array<int, AchievementProgress>  $achievementProgress
     * @param  array<int, ReputationProgress>  $reputationProgress
     * @return array<int, ExpansionCollection>
     */
    private function mergeCollections(array $questProgress, array $achievementProgress, array $reputationProgress): array
    {
        $collections = [];

        foreach (array_keys(ExpansionId::allSlugs()) as $i) {
            $collections[$i] = [
                'quests' => $questProgress[$i] ?? ['total' => 0, 'completed' => 0, 'zones' => []],
                'achievements' => $achievementProgress[$i] ?? ['total' => 0, 'completed' => 0, 'categories' => []],
                'reputations' => $reputationProgress[$i] ?? ['total' => 0, 'completed' => 0, 'factions' => []],
            ];
        }

        return $collections;
    }

    /**
     * @param  array<int, ExpansionCollection>  $collections
     */
    private function countExalted(array $collections): int
    {
        return array_sum(array_map(
            static fn (array $collection): int => $collection['reputations']['completed'],
            $collections,
        ));
    }

    /**
     * @return array<int, string> Map of itemId => iconUrl
     */
    private function fetchEquipmentIcons(CharacterEquipmentResponse $characterEquipmentResponse): array
    {
        $equippedItemIds = $characterEquipmentResponse->equippedItemIds();
        if ($equippedItemIds === []) {
            return [];
        }

        $endpoints = [];
        foreach ($equippedItemIds as $itemId) {
            $endpoints['item_'.$itemId] = [
                'endpoint' => sprintf('data/wow/media/item/%d', $itemId),
                'query' => ['namespace' => 'static-'.$this->blizzardApiClient->getRegion()],
            ];
        }

        $payloads = $this->fetchPayloadsAsync($endpoints);

        $iconMap = [];
        foreach ($equippedItemIds as $equippedItemId) {
            $iconUrl = MediaIconResponse::fromPayload($payloads['item_'.$equippedItemId])->iconUrl;
            if ($iconUrl !== null) {
                $iconMap[$equippedItemId] = $iconUrl;
            }
        }

        return $iconMap;
    }

    /**
     * Résout les noms FR des raids/boss du tier courant via les données statiques
     * journal-instance (l'endpoint encounters/raids du profil ne les localise pas).
     *
     * @return array<int, array{name: string, encounters: array<int, string>}>
     */
    private function fetchRaidNames(CharacterRaidsResponse $characterRaidsResponse): array
    {
        $instanceIds = $characterRaidsResponse->instanceIdsOf(RaidProgressAggregator::CURRENT_SEASON_EXPANSION_ID);
        if ($instanceIds === []) {
            return [];
        }

        $region = $this->blizzardApiClient->getRegion();
        $nameMap = [];

        foreach ($instanceIds as $instanceId) {
            try {
                $journalInstance = $this->fetchJournalInstance($instanceId, $region);
            } catch (\Throwable $throwable) {
                Log::debug('Raid journal-instance fetch failed: '.$throwable->getMessage());

                continue;
            }

            $nameMap[$instanceId] = [
                'name' => $journalInstance->name ?? '',
                'encounters' => $journalInstance->encounterNames,
            ];
        }

        return $nameMap;
    }

    /**
     * Le cache garde la réponse décodée, pas l'objet : son contenu survit ainsi à un changement de classe.
     */
    private function fetchJournalInstance(int $instanceId, string $region): JournalInstanceResponse
    {
        $endpoint = 'data/wow/journal-instance/'.$instanceId;

        $decoded = Cache::remember(
            'raid_journal_instance:'.$instanceId,
            self::RAID_NAMES_TTL_S,
            fn (): array => $this->blizzardApiClient->get($endpoint, ['namespace' => 'static-'.$region]),
        );

        return JournalInstanceResponse::fromPayload(ResponsePayload::forEndpoint($endpoint, $decoded));
    }

    private function fetchCurrentMythicSeason(string $base): ?MythicKeystoneSeasonResponse
    {
        $currentSeasonId = $this->blizzardApiClient->getCurrentMythicSeasonId();

        if ($currentSeasonId === 0) {
            return null;
        }

        try {
            $payload = $this->blizzardApiClient->getResponse($base.'/mythic-keystone-profile/season/'.$currentSeasonId);
        } catch (\Throwable $throwable) {
            Log::debug('M+ season fetch failed: '.$throwable->getMessage());

            return null;
        }

        return $payload->isEmpty() ? null : MythicKeystoneSeasonResponse::fromPayload($payload);
    }

    /**
     * @return MythicKeystoneArray|null
     */
    private function buildMythicKeystoneData(ResponsePayload $responsePayload, ?MythicKeystoneSeasonResponse $mythicKeystoneSeasonResponse): ?array
    {
        if ($responsePayload->isEmpty() || ! $mythicKeystoneSeasonResponse instanceof MythicKeystoneSeasonResponse) {
            return null;
        }

        $runs = array_map($this->mythicRunData(...), $mythicKeystoneSeasonResponse->bestRuns);
        usort($runs, static fn (array $a, array $b): int => $b['map_score'] <=> $a['map_score']);

        return [
            'rating' => $mythicKeystoneSeasonResponse->rating === null ? null : round($mythicKeystoneSeasonResponse->rating, 1),
            'rating_color' => $mythicKeystoneSeasonResponse->ratingColor?->toArray(),
            'season_id' => $mythicKeystoneSeasonResponse->seasonId ?? 0,
            'best_runs' => $runs,
        ];
    }

    /**
     * @return MythicRunArray
     */
    private function mythicRunData(MythicRun $mythicRun): array
    {
        return [
            'dungeon_name' => $mythicRun->dungeonName ?? '',
            'dungeon_id' => $mythicRun->dungeonId ?? 0,
            'level' => $mythicRun->keystoneLevel ?? 0,
            'duration_ms' => $mythicRun->durationMs ?? 0,
            'completed_at' => $mythicRun->completedTimestamp ?? 0,
            'is_timed' => $mythicRun->completedWithinTime ?? false,
            'score' => round($mythicRun->rating ?? 0.0, 1),
            'score_color' => $mythicRun->ratingColor?->toArray(),
            'map_score' => round($mythicRun->mapRating ?? 0.0, 1),
            'map_score_color' => $mythicRun->mapRatingColor?->toArray(),
            'members' => array_map(static fn (MythicRunMember $mythicRunMember): array => [
                'name' => $mythicRunMember->name ?? '',
                'realm' => $mythicRunMember->realmName ?? '',
                'spec' => $mythicRunMember->specializationName ?? '',
                'ilvl' => $mythicRunMember->equippedItemLevel ?? 0,
            ], $mythicRun->members),
        ];
    }

    /**
     * Compte les boss vaincus à la difficulté la plus haute atteinte,
     * cumulé sur les raids du tier courant (badge de l'onglet).
     *
     * @param  list<RaidProgress>|null  $raids
     */
    private function countRaidBosses(?array $raids): int
    {
        if ($raids === null) {
            return 0;
        }

        $total = 0;
        foreach ($raids as $raid) {
            $modes = $raid['modes'];
            // Les modes sont triés par difficulté croissante : le dernier est le plus élevé.
            $highestMode = end($modes);
            if ($highestMode !== false) {
                $total += $highestMode['completed_count'];
            }
        }

        return $total;
    }
}
