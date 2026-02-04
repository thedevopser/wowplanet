<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\ZoneQuestProgress;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ZoneQuestProgressTest extends TestCase
{
    #[Test]
    public function itCanBeInstantiated(): void
    {
        $quests = [
            ['id' => 78530, 'name' => 'Breaking Point', 'completed' => true],
            ['id' => 78531, 'name' => 'Earthen Fissures', 'completed' => false],
        ];

        $zone = new ZoneQuestProgress(
            zoneName: 'Isle of Dorn',
            btwCategoryId: 1101,
            totalQuests: 2,
            completedQuests: 1,
            quests: $quests,
        );

        self::assertSame('Isle of Dorn', $zone->zoneName);
        self::assertSame(1101, $zone->btwCategoryId);
        self::assertSame(2, $zone->totalQuests);
        self::assertSame(1, $zone->completedQuests);
        self::assertCount(2, $zone->quests);
    }

    #[Test]
    public function itComputesCompletionPercent(): void
    {
        $zone = new ZoneQuestProgress(
            zoneName: 'Azsuna',
            btwCategoryId: 703,
            totalQuests: 87,
            completedQuests: 44,
            quests: [],
        );

        self::assertSame(51, $zone->completionPercent());
    }

    #[Test]
    public function itReturnsZeroPercentWhenNoQuests(): void
    {
        $zone = new ZoneQuestProgress(
            zoneName: 'Empty Zone',
            btwCategoryId: 999,
            totalQuests: 0,
            completedQuests: 0,
            quests: [],
        );

        self::assertSame(0, $zone->completionPercent());
    }

    #[Test]
    public function itReturnsHundredPercentWhenAllCompleted(): void
    {
        $zone = new ZoneQuestProgress(
            zoneName: 'Suramar',
            btwCategoryId: 707,
            totalQuests: 253,
            completedQuests: 253,
            quests: [],
        );

        self::assertSame(100, $zone->completionPercent());
    }
}
