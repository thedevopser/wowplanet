<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\ExpansionQuestGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExpansionQuestGroupTest extends TestCase
{
    #[Test]
    public function itCanBeInstantiated(): void
    {
        $zones = [
            ['name' => 'Mont Hyjal', 'btw_category_id' => 401],
            ['name' => 'Uldum', 'btw_category_id' => 404],
        ];

        $group = new ExpansionQuestGroup(
            expansionName: 'Cataclysm',
            order: 4,
            totalQuests: 3427,
            completedQuests: 150,
            zones: $zones,
        );

        self::assertSame('Cataclysm', $group->expansionName);
        self::assertSame(4, $group->order);
        self::assertSame(3427, $group->totalQuests);
        self::assertSame(150, $group->completedQuests);
        self::assertCount(2, $group->zones);
    }

    #[Test]
    public function itReturnsTrueWhenHasQuests(): void
    {
        $group = new ExpansionQuestGroup(
            expansionName: 'Dragonflight',
            order: 10,
            totalQuests: 1525,
            completedQuests: 0,
            zones: [],
        );

        self::assertTrue($group->hasQuests());
    }

    #[Test]
    public function itReturnsFalseWhenNoQuests(): void
    {
        $group = new ExpansionQuestGroup(
            expansionName: 'Classic',
            order: 1,
            totalQuests: 0,
            completedQuests: 0,
            zones: [],
        );

        self::assertFalse($group->hasQuests());
    }

    #[Test]
    public function itComputesCompletionPercent(): void
    {
        $group = new ExpansionQuestGroup(
            expansionName: 'Legion',
            order: 7,
            totalQuests: 200,
            completedQuests: 50,
            zones: [],
        );

        self::assertSame(25, $group->completionPercent());
    }

    #[Test]
    public function itReturnsZeroPercentWhenNoQuests(): void
    {
        $group = new ExpansionQuestGroup(
            expansionName: 'Classic',
            order: 1,
            totalQuests: 0,
            completedQuests: 0,
            zones: [],
        );

        self::assertSame(0, $group->completionPercent());
    }

    #[Test]
    public function itRoundsCompletionPercent(): void
    {
        $group = new ExpansionQuestGroup(
            expansionName: 'Shadowlands',
            order: 9,
            totalQuests: 3,
            completedQuests: 1,
            zones: [],
        );

        self::assertSame(33, $group->completionPercent());
    }

    #[Test]
    public function itReturnsHundredPercentWhenAllCompleted(): void
    {
        $group = new ExpansionQuestGroup(
            expansionName: 'The War Within',
            order: 11,
            totalQuests: 1536,
            completedQuests: 1536,
            zones: [],
        );

        self::assertSame(100, $group->completionPercent());
    }
}
