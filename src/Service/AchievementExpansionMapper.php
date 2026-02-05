<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CategoryAchievementProgress;
use App\DTO\ExpansionAchievementGroup;
use Psr\Log\LoggerInterface;

final readonly class AchievementExpansionMapper
{
    /** @var list<array{name: string, order: int}> */
    private array $expansionDefinitions;

    /** @var array<int, int> achievement_id => expansion_order */
    private array $achievementExpansionMap;

    /** @var array<int, list<array{name: string, krowi_category_id: int}>> */
    private array $expansionCategories;

    /** @var array<int, int> expansion_order => total achievement count */
    private array $achievementCountPerExpansion;

    /** @var array<int, list<int>> krowi_category_id => achievement_ids */
    private array $categoryAchievements;

    /** @var array<int, string> achievement_id => achievement_name */
    private array $achievementNames;

    /** @var array<string, string> english => french translations */
    private array $zoneTranslations;

    public function __construct(
        private LoggerInterface $logger
    ) {
        /** @var list<array{name: string, order: int}> $definitions */
        $definitions = require __DIR__ . '/../Data/wow_expansions.php';

        $definitions[] = ['name' => 'Midnight', 'order' => 12];
        $this->expansionDefinitions = $definitions;

        /** @var array<int, int> $achievementMap */
        $achievementMap = require __DIR__ . '/../Data/achievement_expansion_map.php';
        $this->achievementExpansionMap = $achievementMap;

        /** @var array<int, list<array{name: string, krowi_category_id: int}>> $categories */
        $categories = require __DIR__ . '/../Data/expansion_categories.php';
        $this->expansionCategories = $categories;

        /** @var array<int, list<int>> $categoryAchievementsData */
        $categoryAchievementsData = require __DIR__ . '/../Data/category_achievements.php';
        $this->categoryAchievements = $categoryAchievementsData;

        /** @var array<int, string> $names */
        $names = require __DIR__ . '/../Data/achievement_names.php';
        $this->achievementNames = $names;

        /** @var array<string, string> $translations */
        $translations = require __DIR__ . '/../Data/zone_translations_fr.php';
        $this->zoneTranslations = $translations;

        $this->achievementCountPerExpansion = $this->computeAchievementCounts();
    }

    /**
     * @param array<int, bool> $completedAchievementIds
     * @return list<ExpansionAchievementGroup>
     */
    public function buildExpansionProgress(array $completedAchievementIds): array
    {
        $completedPerExpansion = $this->countCompletedPerExpansion($completedAchievementIds);

        $groups = [];
        foreach ($this->expansionDefinitions as $expansion) {
            $order = $expansion['order'];

            $groups[] = new ExpansionAchievementGroup(
                expansionName: $expansion['name'],
                order: $order,
                totalAchievements: $this->achievementCountPerExpansion[$order] ?? 0,
                completedAchievements: $completedPerExpansion[$order] ?? 0,
                categories: $this->expansionCategories[$order] ?? [],
            );
        }

        usort(
            $groups,
            static fn (ExpansionAchievementGroup $a, ExpansionAchievementGroup $b): int => $a->order <=> $b->order
        );

        $this->logger->info('Expansion achievement progress computed', [
            'total_completed' => count($completedAchievementIds),
            'mapped_completed' => array_sum($completedPerExpansion),
        ]);

        return $groups;
    }

    /**
     * @param array<int, bool> $completedAchievementIds
     * @return list<CategoryAchievementProgress>
     */
    public function buildCategoryProgress(
        int $expansionOrder,
        array $completedAchievementIds
    ): array {
        $categories = $this->expansionCategories[$expansionOrder] ?? [];
        $categoryProgressList = [];

        foreach ($categories as $category) {
            $categoryId = $category['krowi_category_id'];
            $achievementIds = $this->categoryAchievements[$categoryId] ?? [];

            $achievements = [];
            $completedCount = 0;

            foreach ($achievementIds as $achievementId) {
                if (!isset($this->achievementNames[$achievementId])) {
                    continue;
                }

                $isCompleted = $completedAchievementIds[$achievementId] ?? false;

                if ($isCompleted) {
                    $completedCount++;
                }

                $achievements[] = [
                    'id' => $achievementId,
                    'name' => $this->achievementNames[$achievementId],
                    'completed' => $isCompleted,
                ];
            }

            $categoryProgressList[] = new CategoryAchievementProgress(
                categoryName: $this->translateCategoryName($category['name']),
                krowiCategoryId: $categoryId,
                totalAchievements: count($achievements),
                completedAchievements: $completedCount,
                achievements: $achievements,
            );
        }

        usort(
            $categoryProgressList,
            static fn (CategoryAchievementProgress $a, CategoryAchievementProgress $b): int
                => $a->categoryName <=> $b->categoryName
        );

        return $categoryProgressList;
    }

    /**
     * @param array<int, bool> $completedAchievementIds
     * @return array<int, int> expansion_order => completed count
     */
    private function countCompletedPerExpansion(array $completedAchievementIds): array
    {
        $counts = [];

        foreach ($completedAchievementIds as $achievementId => $completed) {
            if (!$completed) {
                continue;
            }

            if (!isset($this->achievementNames[$achievementId])) {
                continue;
            }

            $expansionOrder = $this->achievementExpansionMap[$achievementId] ?? null;

            if ($expansionOrder === null) {
                continue;
            }

            $counts[$expansionOrder] = ($counts[$expansionOrder] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<int, int> expansion_order => total achievement count
     */
    private function computeAchievementCounts(): array
    {
        $counts = [];

        foreach ($this->achievementExpansionMap as $achievementId => $expansionOrder) {
            if (!isset($this->achievementNames[$achievementId])) {
                continue;
            }

            $counts[$expansionOrder] = ($counts[$expansionOrder] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Translates category name from English to French.
     * Format: "Zone Name - Type" => "Nom de Zone - Type"
     */
    private function translateCategoryName(string $categoryName): string
    {
        if (!str_contains($categoryName, ' - ')) {
            return $this->zoneTranslations[$categoryName] ?? $categoryName;
        }

        $parts = explode(' - ', $categoryName, 2);
        $zoneName = $parts[0];
        $typeName = $parts[1];

        $translatedZone = $this->zoneTranslations[$zoneName] ?? $zoneName;
        $translatedType = $this->zoneTranslations[$typeName] ?? $typeName;

        return $translatedZone . ' - ' . $translatedType;
    }
}
