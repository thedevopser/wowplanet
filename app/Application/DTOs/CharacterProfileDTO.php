<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Domain\ValueObjects\CompletionScore;

/**
 * @phpstan-import-type QuestProgress from \App\Application\Services\Progress\QuestProgressAggregator
 * @phpstan-import-type AchievementProgress from \App\Application\Services\Progress\AchievementProgressAggregator
 * @phpstan-import-type ReputationProgress from \App\Application\Services\Progress\ReputationProgressAggregator
 * @phpstan-import-type CollectibleProgress from \App\Application\Services\Progress\CollectionProgressAggregator
 * @phpstan-import-type DecorProgress from \App\Application\Services\Progress\CollectionProgressAggregator
 * @phpstan-import-type AppearanceProgress from \App\Application\Services\Progress\CollectionProgressAggregator
 * @phpstan-import-type ProfessionProgress from \App\Application\Services\Progress\ProfessionProgressAggregator
 * @phpstan-import-type RaidProgress from \App\Application\Services\Progress\RaidProgressAggregator
 * @phpstan-import-type EquippedItem from \App\Application\Services\Progress\EquipmentAggregator
 *
 * @phpstan-type ExpansionCollection array{quests: QuestProgress, achievements: AchievementProgress, reputations: ReputationProgress}
 * @phpstan-type RatingColorArray array{r: int, g: int, b: int, a: float}
 * @phpstan-type MythicRunMemberArray array{name: string, realm: string, spec: string, ilvl: int}
 * @phpstan-type MythicRunArray array{dungeon_name: string, dungeon_id: int, level: int, duration_ms: int, completed_at: int, is_timed: bool, score: float, score_color: RatingColorArray|null, map_score: float, map_score_color: RatingColorArray|null, members: list<MythicRunMemberArray>}
 * @phpstan-type MythicKeystoneArray array{rating: float|null, rating_color: RatingColorArray|null, season_id: int, best_runs: list<MythicRunArray>}
 */
readonly class CharacterProfileDTO
{
    /**
     * @param  array<int, ExpansionCollection>  $collections
     * @param  list<CollectibleProgress>  $mounts
     * @param  list<CollectibleProgress>  $pets
     * @param  list<ProfessionProgress>  $professions
     * @param  list<DecorProgress>  $decor
     * @param  MythicKeystoneArray|null  $mythicKeystone
     * @param  list<RaidProgress>|null  $raids
     * @param  list<int>  $completedQuestIds
     * @param  list<int>  $completedAchievementIds
     * @param  list<EquippedItem>  $equipment
     * @param  list<AppearanceProgress>  $appearances
     */
    public function __construct(
        public string $name,
        public string $realm,
        public string $race,
        public string $class,
        public int $classId,
        public int $level,
        public int $ilvl,
        public string $faction,
        public string $avatarUrl,
        public string $classIconUrl,
        public array $collections,
        public int $mountsCount,
        public int $petsCount,
        public int $achievementPoints = 0,
        public string $guild = '',
        public array $mounts = [],
        public array $pets = [],
        public array $professions = [],
        public int $decorCount = 0,
        public array $decor = [],
        public int $exaltedCount = 0,
        public ?array $mythicKeystone = null,
        public array $completedQuestIds = [],
        public array $completedAchievementIds = [],
        public array $equipment = [],
        public array $appearances = [],
        public int $appearancesCount = 0,
        public ?array $raids = null,
        public int $raidsCount = 0,
        public ?CompletionScore $score = null,
    ) {}
}
