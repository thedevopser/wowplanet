<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Domain\ValueObjects\ExpansionId;
use App\Models\WowAchievement;
use Illuminate\Support\Collection;

/**
 * @phpstan-type AchievementItem array{id: int, name: string, icon_url: string|null, is_completed: bool}
 * @phpstan-type AchievementCategory array{name: string, total: int, completed: int, items: list<AchievementItem>}
 * @phpstan-type AchievementProgress array{total: int, completed: int, categories: list<AchievementCategory>}
 */
class AchievementProgressAggregator
{
    /**
     * @param  list<int>  $completedAchievementIds
     * @return array<int, AchievementProgress>
     */
    public function aggregate(array $completedAchievementIds): array
    {
        $allAchievements = WowAchievement::query()
            ->where('is_active', true)
            ->get()
            ->groupBy('expansion_id');

        $results = [];

        // Les identifiants viennent du value object : le seau « Non classé » n'est pas
        // dans la suite des extensions et une borne en dur le ferait disparaître.
        foreach (array_keys(ExpansionId::allSlugs()) as $expansionIndex) {
            /** @var Collection<int, WowAchievement> $expansionAchievements */
            $expansionAchievements = $allAchievements->get($expansionIndex, new Collection);
            $categoryProgress = $this->buildCategoryProgress($expansionAchievements, $completedAchievementIds);

            $results[$expansionIndex] = [
                'total' => $expansionAchievements->count(),
                'completed' => $expansionAchievements->whereIn('id', $completedAchievementIds)->count(),
                'categories' => $categoryProgress,
            ];
        }

        return $results;
    }

    /**
     * @param  Collection<int, WowAchievement>  $expansionAchievements
     * @param  list<int>  $completedAchievementIds
     * @return list<AchievementCategory>
     */
    private function buildCategoryProgress(Collection $expansionAchievements, array $completedAchievementIds): array
    {
        $categoryProgress = [];
        /** @var Collection<string, Collection<int, WowAchievement>> $achievementsByCategory */
        $achievementsByCategory = $expansionAchievements->groupBy('category_name');

        foreach ($achievementsByCategory as $categoryName => $categoryAchievements) {
            if (empty($categoryName)) {
                continue;
            }

            $categoryProgress[] = $this->buildSingleCategoryProgress((string) $categoryName, $categoryAchievements, $completedAchievementIds);
        }

        return $categoryProgress;
    }

    /**
     * @param  Collection<int, WowAchievement>  $categoryAchievements
     * @param  list<int>  $completedAchievementIds
     * @return AchievementCategory
     */
    private function buildSingleCategoryProgress(string $categoryName, Collection $categoryAchievements, array $completedAchievementIds): array
    {
        $items = [];
        $completedCount = 0;

        foreach ($categoryAchievements as $categoryAchievement) {
            $isCompleted = in_array($categoryAchievement->id, $completedAchievementIds);
            $items[] = [
                'id' => $categoryAchievement->id,
                'name' => $categoryAchievement->name_fr,
                'icon_url' => $categoryAchievement->icon_url,
                'is_completed' => $isCompleted,
            ];
            if ($isCompleted) {
                $completedCount++;
            }
        }

        return [
            'name' => $categoryName,
            'total' => count($items),
            'completed' => $completedCount,
            'items' => $items,
        ];
    }
}
