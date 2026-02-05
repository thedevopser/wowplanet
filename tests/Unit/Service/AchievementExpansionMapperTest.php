<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\ExpansionAchievementGroup;
use App\Service\AchievementExpansionMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AchievementExpansionMapperTest extends TestCase
{
    private AchievementExpansionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AchievementExpansionMapper(new NullLogger());
    }

    #[Test]
    public function itReturnsTwelveExpansionGroups(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        self::assertCount(12, $groups);
    }

    #[Test]
    public function itSortsGroupsByOrder(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        self::assertSame('Classic', $groups[0]->expansionName);
        self::assertSame(1, $groups[0]->order);
        self::assertSame('The Burning Crusade', $groups[1]->expansionName);
        self::assertSame('Wrath of the Lich King', $groups[2]->expansionName);
        self::assertSame('Midnight', $groups[11]->expansionName);
        self::assertSame(12, $groups[11]->order);
    }

    #[Test]
    public function itCountsTotalAchievementsPerExpansion(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        $dfGroup = $this->findGroupByName($groups, 'Dragonflight');
        self::assertNotNull($dfGroup);
        self::assertGreaterThan(0, $dfGroup->totalAchievements);
    }

    #[Test]
    public function itReturnsZeroCompletedWhenNoAchievementsProvided(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        foreach ($groups as $group) {
            self::assertSame(
                0,
                $group->completedAchievements,
                sprintf(
                    'Expected 0 completed achievements for %s',
                    $group->expansionName
                )
            );
        }
    }

    #[Test]
    public function itIncludesCategoriesForExpansions(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        $dfGroup = $this->findGroupByName($groups, 'Dragonflight');
        self::assertNotNull($dfGroup);
        self::assertNotEmpty($dfGroup->categories);

        $categoryNames = array_column($dfGroup->categories, 'name');
        self::assertNotEmpty($categoryNames);
    }

    #[Test]
    public function itHasAchievementsForAllExpansions(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        foreach ($groups as $group) {
            self::assertTrue(
                $group->hasAchievements(),
                sprintf('Expected achievements for %s', $group->expansionName)
            );
        }
    }

    #[Test]
    public function itMapsCompletedAchievementToCorrectExpansion(): void
    {
        $completedAchievementIds = [
            15325 => true,
        ];

        $groups = $this->mapper->buildExpansionProgress($completedAchievementIds);

        $totalCompleted = 0;
        foreach ($groups as $group) {
            $totalCompleted += $group->completedAchievements;
        }

        self::assertSame(1, $totalCompleted);
    }

    #[Test]
    public function itBuildsCategoryProgressForExpansion(): void
    {
        $categories = $this->mapper->buildCategoryProgress(10, []);

        self::assertNotEmpty($categories);

        $firstCategory = $categories[0];
        self::assertNotEmpty($firstCategory->categoryName);
        self::assertGreaterThan(0, $firstCategory->krowiCategoryId);
        self::assertSame(0, $firstCategory->completedAchievements);
    }

    #[Test]
    public function itIncludesAchievementDetailsInCategoryProgress(): void
    {
        $categories = $this->mapper->buildCategoryProgress(10, []);
        $categoryWithAchievements = array_find(
            $categories,
            static fn ($category) => $category->totalAchievements > 0
        );

        self::assertNotNull($categoryWithAchievements);
        self::assertNotEmpty($categoryWithAchievements->achievements);
        self::assertArrayHasKey('id', $categoryWithAchievements->achievements[0]);
        self::assertArrayHasKey('name', $categoryWithAchievements->achievements[0]);
        self::assertArrayHasKey('completed', $categoryWithAchievements->achievements[0]);
        self::assertFalse($categoryWithAchievements->achievements[0]['completed']);
    }

    #[Test]
    public function itCountsCompletedAchievementsInCategoryProgress(): void
    {
        $categories = $this->mapper->buildCategoryProgress(10, []);
        $categoryWithAchievements = array_find(
            $categories,
            static fn ($category) => $category->totalAchievements > 0
        );

        self::assertNotNull($categoryWithAchievements);

        $firstAchievementId = $categoryWithAchievements->achievements[0]['id'];
        $completedIds = [$firstAchievementId => true];

        $categoriesWithCompleted = $this->mapper->buildCategoryProgress(10, $completedIds);
        $updatedCategory = array_find(
            $categoriesWithCompleted,
            static fn ($c) => $c->krowiCategoryId === $categoryWithAchievements->krowiCategoryId
        );

        self::assertNotNull($updatedCategory);
        self::assertSame(1, $updatedCategory->completedAchievements);
    }

    /**
     * @param list<ExpansionAchievementGroup> $groups
     */
    private function findGroupByName(
        array $groups,
        string $name
    ): ?ExpansionAchievementGroup {
        foreach ($groups as $group) {
            if ($group->expansionName === $name) {
                return $group;
            }
        }

        return null;
    }
}
