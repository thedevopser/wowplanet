<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CategoryAchievementProgress;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CategoryAchievementProgressTest extends TestCase
{
    #[Test]
    public function itCanBeInstantiated(): void
    {
        $achievements = [
            ['id' => 15325, 'name' => 'Dracthyr, Awaken', 'completed' => true],
            ['id' => 15394, 'name' => "Ohn'a'Roll", 'completed' => false],
        ];

        $category = new CategoryAchievementProgress(
            categoryName: 'Dragon Isles - Quests',
            krowiCategoryId: 1342,
            totalAchievements: 2,
            completedAchievements: 1,
            achievements: $achievements,
        );

        self::assertSame('Dragon Isles - Quests', $category->categoryName);
        self::assertSame(1342, $category->krowiCategoryId);
        self::assertSame(2, $category->totalAchievements);
        self::assertSame(1, $category->completedAchievements);
        self::assertCount(2, $category->achievements);
    }

    #[Test]
    public function itComputesCompletionPercent(): void
    {
        $category = new CategoryAchievementProgress(
            categoryName: 'The Waking Shores - Exploration',
            krowiCategoryId: 1242,
            totalAchievements: 87,
            completedAchievements: 44,
            achievements: [],
        );

        self::assertSame(51, $category->completionPercent());
    }

    #[Test]
    public function itReturnsZeroPercentWhenNoAchievements(): void
    {
        $category = new CategoryAchievementProgress(
            categoryName: 'Empty Category',
            krowiCategoryId: 999,
            totalAchievements: 0,
            completedAchievements: 0,
            achievements: [],
        );

        self::assertSame(0, $category->completionPercent());
    }

    #[Test]
    public function itReturnsHundredPercentWhenAllCompleted(): void
    {
        $category = new CategoryAchievementProgress(
            categoryName: 'Dragon Isles - PvP',
            krowiCategoryId: 1390,
            totalAchievements: 14,
            completedAchievements: 14,
            achievements: [],
        );

        self::assertSame(100, $category->completionPercent());
    }

    #[Test]
    public function itRoundsCompletionPercent(): void
    {
        $category = new CategoryAchievementProgress(
            categoryName: 'Valdrakken',
            krowiCategoryId: 1362,
            totalAchievements: 3,
            completedAchievements: 1,
            achievements: [],
        );

        self::assertSame(33, $category->completionPercent());
    }
}
