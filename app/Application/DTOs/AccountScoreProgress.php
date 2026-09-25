<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Application\Services\Progress\AchievementProgressAggregator;
use App\Application\Services\Progress\CollectionProgressAggregator;
use App\Application\Services\Progress\ProfessionProgressAggregator;
use App\Application\Services\Progress\QuestProgressAggregator;
use App\Application\Services\Progress\RaidProgressAggregator;
use App\Domain\ValueObjects\CompletionScore;

/**
 * Progression d'un compte, accumulée personnage par personnage puis figée par `buildResult()`.
 * Le score n'y figure pas : `AccountScoreService` le calcule sur le résultat et l'y ajoute.
 *
 * L'objet est mis en cache entre deux lots : ses propriétés restent des tableaux simples.
 *
 * @phpstan-import-type QuestZone from QuestProgressAggregator
 * @phpstan-import-type AchievementCategory from AchievementProgressAggregator
 * @phpstan-import-type ProfessionProgress from ProfessionProgressAggregator
 * @phpstan-import-type ProfessionExpansionProgress from ProfessionProgressAggregator
 * @phpstan-import-type RecipeCategory from ProfessionProgressAggregator
 * @phpstan-import-type CollectibleProgress from CollectionProgressAggregator
 * @phpstan-import-type DecorProgress from CollectionProgressAggregator
 * @phpstan-import-type AppearanceProgress from CollectionProgressAggregator
 * @phpstan-import-type RaidProgress from RaidProgressAggregator
 * @phpstan-import-type RaidModeView from RaidProgressAggregator
 * @phpstan-import-type RaidEncounterView from RaidProgressAggregator
 * @phpstan-import-type ExpansionCollection from CharacterProfileDTO
 *
 * @phpstan-type ExpansionTotals array{quests: array{total: int, zones: list<QuestZone>}, achievements: array{total: int, categories: list<AchievementCategory>}, reputations: array{total: int}}
 * @phpstan-type AccountRaidMode array{difficulty_type: string, difficulty_label: string, completed_count: int, total_count: int, encounters: array<int, RaidEncounterView>}
 * @phpstan-type AccountRaid array{instance_id: int, instance_name: string, modes: array<string, AccountRaidMode>}
 * @phpstan-type AccountExpansionCollection array{quests: array{total: int, completed: int, zones: list<QuestZone>}, achievements: array{total: int, completed: int, categories: list<AchievementCategory>}, reputations: array{completed: int, total: int}}
 * @phpstan-type CompletionStats array{completed: int, total: int}
 * @phpstan-type AccountScoreResult array{collections: array<int, AccountExpansionCollection>, mounts: list<CollectibleProgress>, pets: list<CollectibleProgress>, decor: list<DecorProgress>, professions: list<ProfessionProgress>, mountsCount: int, petsCount: int, decorCount: int, bestProfessionStats: CompletionStats|null, appearances: list<AppearanceProgress>, raids: list<RaidProgress>|null, characterCount: int, errors: list<string>, cachedAt: string|null, score?: CompletionScore}
 */
class AccountScoreProgress
{
    // @pest-mutate-ignore
    public int $processed = 0;

    /** @var list<string> */
    public array $errors = [];

    /** @var array<int, true> */
    public array $completedQuestIds = [];

    /** @var array<int, true> */
    public array $completedAchievementIds = [];

    /** @var array<int, CompletionStats> */
    public array $bestReputations = [];

    /** @var array<int, ExpansionTotals>|null */
    public ?array $collectionsTotals = null;

    /** @var list<CollectibleProgress>|null */
    public ?array $accountMounts = null;

    /** @var list<CollectibleProgress>|null */
    public ?array $accountPets = null;

    /** @var list<DecorProgress>|null */
    public ?array $accountDecor = null;

    /** @var array<int, ProfessionProgress> */
    public array $professionMap = [];

    /** @var array<int, true> */
    public array $completedRecipeIds = [];

    /** @var array<int, array<int, array{skill_points: int, max_skill_points: int}>> */
    public array $bestSkillPoints = [];

    /** @var CompletionStats|null */
    public ?array $bestProfessionStats = null;

    /** @var array<string, AppearanceProgress> */
    public array $bestAppearances = [];

    /** @var array<int, AccountRaid> */
    public array $accountRaids = [];

    /**
     * @param  list<array{realmSlug: string, name: string}>  $characters
     */
    public function __construct(
        public readonly array $characters,
    ) {}

    public function mergeProfile(CharacterProfileDTO $characterProfileDTO): void
    {
        $this->processed++;

        $this->accountMounts ??= $characterProfileDTO->mounts;
        $this->accountPets ??= $characterProfileDTO->pets;
        $this->accountDecor ??= $characterProfileDTO->decor;

        $this->mergeCollections($characterProfileDTO);
        $this->mergeProfessions($characterProfileDTO);
        $this->trackBestProfessionStats($characterProfileDTO);
        $this->mergeAppearances($characterProfileDTO);
        $this->mergeRaids($characterProfileDTO);
    }

    /**
     * @return AccountScoreResult
     */
    public function buildResult(): array
    {
        $mounts = $this->accountMounts ?? [];
        $pets = $this->accountPets ?? [];
        $decor = $this->accountDecor ?? [];

        return [
            'collections' => $this->rebuildCollections(),
            'mounts' => $mounts,
            'pets' => $pets,
            'decor' => $decor,
            'professions' => $this->rebuildProfessions(),
            'mountsCount' => $this->countCompleted($mounts),
            'petsCount' => $this->countCompleted($pets),
            'decorCount' => $this->countCompleted($decor),
            'bestProfessionStats' => $this->bestProfessionStats,
            'appearances' => array_values($this->bestAppearances),
            'raids' => $this->rebuildRaids(),
            'characterCount' => $this->processed,
            'errors' => $this->errors,
            'cachedAt' => now()->toISOString(),
        ];
    }

    /**
     * @param  list<array{is_completed: bool}>  $items
     */
    private function countCompleted(array $items): int
    {
        return count(array_filter($items, static fn (array $item): bool => $item['is_completed']));
    }

    /** La garde-robe est partagée par le compte : on retient le mieux servi par slot. */
    private function mergeAppearances(CharacterProfileDTO $characterProfileDTO): void
    {
        foreach ($characterProfileDTO->appearances as $slot) {
            $key = $slot['slot'];
            $known = $this->bestAppearances[$key] ?? null;

            if ($known === null || $slot['completed'] > $known['completed']) {
                $this->bestAppearances[$key] = $slot;
            }
        }
    }

    /**
     * Un compte a tué un boss dès qu'un de ses personnages l'a tué : on réunit les
     * boss par raid et par difficulté.
     */
    private function mergeRaids(CharacterProfileDTO $characterProfileDTO): void
    {
        foreach ($characterProfileDTO->raids ?? [] as $raid) {
            $instanceId = $raid['instance_id'];

            $this->accountRaids[$instanceId] ??= [
                'instance_id' => $instanceId,
                'instance_name' => $raid['instance_name'],
                'modes' => [],
            ];

            foreach ($raid['modes'] as $mode) {
                $this->mergeRaidMode($instanceId, $mode);
            }
        }
    }

    /**
     * @param  RaidModeView  $mode
     */
    private function mergeRaidMode(int $instanceId, array $mode): void
    {
        $difficulty = $mode['difficulty_type'];

        $known = $this->accountRaids[$instanceId]['modes'][$difficulty] ?? [
            'difficulty_type' => $mode['difficulty_type'],
            'difficulty_label' => $mode['difficulty_label'],
            'completed_count' => $mode['completed_count'],
            'total_count' => $mode['total_count'],
            'encounters' => [],
        ];

        foreach ($mode['encounters'] as $encounter) {
            $known['encounters'][$encounter['id']] ??= $encounter;
        }

        $known['total_count'] = max($known['total_count'], $mode['total_count']);

        $this->accountRaids[$instanceId]['modes'][$difficulty] = $known;
    }

    /**
     * @return list<RaidProgress>|null
     */
    private function rebuildRaids(): ?array
    {
        if ($this->accountRaids === []) {
            return null;
        }

        $raids = [];

        foreach ($this->accountRaids as $accountRaid) {
            $modes = [];

            foreach ($accountRaid['modes'] as $mode) {
                $encounters = $mode['encounters'];
                ksort($encounters);

                $modes[] = [
                    'difficulty_type' => $mode['difficulty_type'],
                    'difficulty_label' => $mode['difficulty_label'],
                    'completed_count' => count($encounters),
                    'total_count' => $mode['total_count'],
                    'encounters' => array_values($encounters),
                ];
            }

            $raids[] = [
                'instance_id' => $accountRaid['instance_id'],
                'instance_name' => $accountRaid['instance_name'],
                'modes' => $modes,
            ];
        }

        return $raids;
    }

    private function mergeCollections(CharacterProfileDTO $characterProfileDTO): void
    {
        $this->collectionsTotals ??= [];

        foreach ($characterProfileDTO->collections as $expId => $exp) {
            $this->initExpansionTotals($expId, $exp);
            $this->collectCompletedIds($exp);
            $this->mergeBestReputations($expId, $exp);
        }
    }

    /**
     * @param  ExpansionCollection  $exp
     */
    private function initExpansionTotals(int $expId, array $exp): void
    {
        if (isset($this->collectionsTotals[$expId])) {
            return;
        }

        $this->collectionsTotals[$expId] = [
            'quests' => [
                'total' => $exp['quests']['total'],
                'zones' => $exp['quests']['zones'],
            ],
            'achievements' => [
                'total' => $exp['achievements']['total'],
                'categories' => $exp['achievements']['categories'],
            ],
            'reputations' => [
                'total' => $exp['reputations']['total'],
            ],
        ];
    }

    /**
     * @param  ExpansionCollection  $exp
     */
    private function collectCompletedIds(array $exp): void
    {
        $this->extractCompletedFromGroups($exp['quests']['zones'], $this->completedQuestIds);
        $this->extractCompletedFromGroups($exp['achievements']['categories'], $this->completedAchievementIds);
    }

    /**
     * @param  list<array{items: list<array{id: int, is_completed: bool}>}>  $groups
     * @param  array<int, true>  $target
     */
    private function extractCompletedFromGroups(array $groups, array &$target): void
    {
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                if ($item['is_completed']) {
                    $target[$item['id']] = true;
                }
            }
        }
    }

    /**
     * @param  ExpansionCollection  $exp
     */
    private function mergeBestReputations(int $expId, array $exp): void
    {
        $repCompleted = $exp['reputations']['completed'];
        $repTotal = $exp['reputations']['total'];

        if (! isset($this->bestReputations[$expId]) || $repCompleted > $this->bestReputations[$expId]['completed']) {
            $this->bestReputations[$expId] = ['completed' => $repCompleted, 'total' => $repTotal];
        }
    }

    private function trackBestProfessionStats(CharacterProfileDTO $characterProfileDTO): void
    {
        $recipeCompleted = 0;
        $recipeTotal = 0;
        $skillPoints = 0;
        $skillMax = 0;

        foreach ($characterProfileDTO->professions as $prof) {
            foreach ($prof['expansions'] as $expansion) {
                $recipeCompleted += $expansion['completed'];
                $recipeTotal += $expansion['total'];
                $skillPoints += $expansion['skill_points'];
                $skillMax += $expansion['max_skill_points'];
            }
        }

        $completed = $recipeTotal > 0 ? $recipeCompleted : $skillPoints;
        $total = $recipeTotal > 0 ? $recipeTotal : $skillMax;

        if ($total > 0 && ($this->bestProfessionStats === null || ($completed / $total) > ($this->bestProfessionStats['completed'] / max(1, $this->bestProfessionStats['total'])))) {
            $this->bestProfessionStats = ['completed' => $completed, 'total' => $total];
        }
    }

    private function mergeProfessions(CharacterProfileDTO $characterProfileDTO): void
    {
        foreach ($characterProfileDTO->professions as $prof) {
            $pid = $prof['profession_id'];
            $this->professionMap[$pid] ??= $prof;

            foreach ($prof['expansions'] as $expId => $expData) {
                $this->mergeProfessionExpansion($pid, $expId, $expData);
            }
        }
    }

    /**
     * @param  ProfessionExpansionProgress  $expData
     */
    private function mergeProfessionExpansion(int $pid, int $expId, array $expData): void
    {
        $this->extractCompletedFromGroups($expData['categories'], $this->completedRecipeIds);

        $sp = $expData['skill_points'];
        $msp = $expData['max_skill_points'];

        if (! isset($this->bestSkillPoints[$pid][$expId])
            || $sp > $this->bestSkillPoints[$pid][$expId]['skill_points']) {
            $this->bestSkillPoints[$pid][$expId] = [
                'skill_points' => $sp,
                'max_skill_points' => max($msp, $this->bestSkillPoints[$pid][$expId]['max_skill_points'] ?? 0),
            ];
        } else {
            $this->bestSkillPoints[$pid][$expId]['max_skill_points'] = max(
                $msp,
                $this->bestSkillPoints[$pid][$expId]['max_skill_points'],
            );
        }
    }

    /**
     * @return list<ProfessionProgress>
     */
    private function rebuildProfessions(): array
    {
        $result = [];

        foreach ($this->professionMap as $pid => $prof) {
            $rebuiltExpansions = [];

            foreach ($prof['expansions'] as $expId => $expData) {
                $rebuiltExpansions[$expId] = $this->rebuildProfessionExpansion($pid, $expId, $expData);
            }

            $prof['expansions'] = $rebuiltExpansions;
            $result[] = $prof;
        }

        return $result;
    }

    /**
     * @param  ProfessionExpansionProgress  $expData
     * @return ProfessionExpansionProgress
     */
    private function rebuildProfessionExpansion(int $pid, int $expId, array $expData): array
    {
        $rebuiltCategories = array_map($this->rebuildRecipeCategory(...), $expData['categories']);

        $bestSp = $this->bestSkillPoints[$pid][$expId] ?? null;

        $expData['total'] = array_sum(array_column($rebuiltCategories, 'total'));
        $expData['completed'] = array_sum(array_column($rebuiltCategories, 'completed'));
        $expData['categories'] = $rebuiltCategories;
        $expData['skill_points'] = $bestSp !== null ? $bestSp['skill_points'] : 0;
        $expData['max_skill_points'] = $bestSp !== null ? $bestSp['max_skill_points'] : 0;

        return $expData;
    }

    /**
     * @return array<int, AccountExpansionCollection>
     */
    private function rebuildCollections(): array
    {
        if ($this->collectionsTotals === null) {
            return [];
        }

        $collections = [];

        foreach ($this->collectionsTotals as $expId => $expTotals) {
            $collections[$expId] = $this->rebuildExpansionCollections($expId, $expTotals);
        }

        return $collections;
    }

    /**
     * @param  ExpansionTotals  $expTotals
     * @return AccountExpansionCollection
     */
    private function rebuildExpansionCollections(int $expId, array $expTotals): array
    {
        $zones = array_map($this->rebuildQuestZone(...), $expTotals['quests']['zones']);
        $categories = array_map($this->rebuildAchievementCategory(...), $expTotals['achievements']['categories']);

        $rep = $this->bestReputations[$expId] ?? ['completed' => 0, 'total' => $expTotals['reputations']['total']];

        return [
            'quests' => ['total' => $expTotals['quests']['total'], 'completed' => array_sum(array_column($zones, 'completed')), 'zones' => $zones],
            'achievements' => ['total' => $expTotals['achievements']['total'], 'completed' => array_sum(array_column($categories, 'completed')), 'categories' => $categories],
            'reputations' => ['completed' => $rep['completed'], 'total' => $rep['total']],
        ];
    }

    /**
     * @param  QuestZone  $zone
     * @return QuestZone
     */
    private function rebuildQuestZone(array $zone): array
    {
        $items = array_map(fn (array $item): array => [
            'id' => $item['id'],
            'name' => $item['name'],
            'is_completed' => isset($this->completedQuestIds[$item['id']]),
        ], $zone['items']);

        return ['name' => $zone['name'], 'total' => count($items), 'completed' => $this->countCompleted($items), 'items' => $items];
    }

    /**
     * @param  AchievementCategory  $category
     * @return AchievementCategory
     */
    private function rebuildAchievementCategory(array $category): array
    {
        $items = array_map(fn (array $item): array => [
            'id' => $item['id'],
            'name' => $item['name'],
            'icon_url' => $item['icon_url'],
            'is_completed' => isset($this->completedAchievementIds[$item['id']]),
        ], $category['items']);

        return ['name' => $category['name'], 'total' => count($items), 'completed' => $this->countCompleted($items), 'items' => $items];
    }

    /**
     * @param  RecipeCategory  $category
     * @return RecipeCategory
     */
    private function rebuildRecipeCategory(array $category): array
    {
        $items = array_map(fn (array $item): array => [
            'id' => $item['id'],
            'name' => $item['name'],
            'is_completed' => isset($this->completedRecipeIds[$item['id']]),
            'wowhead_spell_id' => $item['wowhead_spell_id'],
        ], $category['items']);

        return ['name' => $category['name'], 'total' => count($items), 'completed' => $this->countCompleted($items), 'items' => $items];
    }
}
