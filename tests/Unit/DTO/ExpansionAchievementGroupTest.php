<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\ExpansionAchievementGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExpansionAchievementGroupTest extends TestCase
{
    #[Test]
    public function itCanBeInstantiated(): void
    {
        $categories = [
            ['name' => 'Dragon Isles - Quests', 'krowi_category_id' => 1342],
            ['name' => 'Dragon Isles - Exploration', 'krowi_category_id' => 1343],
        ];

        $group = new ExpansionAchievementGroup(
            expansionName: 'Dragonflight',
            order: 10,
            totalAchievements: 847,
            completedAchievements: 150,
            categories: $categories,
        );

        self::assertSame('Dragonflight', $group->expansionName);
        self::assertSame(10, $group->order);
        self::assertSame(847, $group->totalAchievements);
        self::assertSame(150, $group->completedAchievements);
        self::assertCount(2, $group->categories);
    }

    #[Test]
    public function itReturnsTrueWhenHasAchievements(): void
    {
        $group = new ExpansionAchievementGroup(
            expansionName: 'Dragonflight',
            order: 10,
            totalAchievements: 847,
            completedAchievements: 0,
            categories: [],
        );

        self::assertTrue($group->hasAchievements());
    }

    #[Test]
    public function itReturnsFalseWhenNoAchievements(): void
    {
        $group = new ExpansionAchievementGroup(
            expansionName: 'Classic',
            order: 1,
            totalAchievements: 0,
            completedAchievements: 0,
            categories: [],
        );

        self::assertFalse($group->hasAchievements());
    }

    #[Test]
    public function itComputesCompletionPercent(): void
    {
        $group = new ExpansionAchievementGroup(
            expansionName: 'Legion',
            order: 7,
            totalAchievements: 200,
            completedAchievements: 50,
            categories: [],
        );

        self::assertSame(25, $group->completionPercent());
    }

    #[Test]
    public function itReturnsZeroPercentWhenNoAchievements(): void
    {
        $group = new ExpansionAchievementGroup(
            expansionName: 'Classic',
            order: 1,
            totalAchievements: 0,
            completedAchievements: 0,
            categories: [],
        );

        self::assertSame(0, $group->completionPercent());
    }

    #[Test]
    public function itRoundsCompletionPercent(): void
    {
        $group = new ExpansionAchievementGroup(
            expansionName: 'Shadowlands',
            order: 9,
            totalAchievements: 3,
            completedAchievements: 1,
            categories: [],
        );

        self::assertSame(33, $group->completionPercent());
    }

    #[Test]
    public function itReturnsHundredPercentWhenAllCompleted(): void
    {
        $group = new ExpansionAchievementGroup(
            expansionName: 'The War Within',
            order: 11,
            totalAchievements: 536,
            completedAchievements: 536,
            categories: [],
        );

        self::assertSame(100, $group->completionPercent());
    }
}
