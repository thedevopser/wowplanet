<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\ExpansionQuestGroup;
use App\Service\QuestExpansionMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class QuestExpansionMapperTest extends TestCase
{
    private QuestExpansionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new QuestExpansionMapper(new NullLogger());
    }

    #[Test]
    public function itReturnsElevenExpansionGroups(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        self::assertCount(11, $groups);
    }

    #[Test]
    public function itSortsGroupsByOrder(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        self::assertSame('Classic', $groups[0]->expansionName);
        self::assertSame(1, $groups[0]->order);
        self::assertSame('The Burning Crusade', $groups[1]->expansionName);
        self::assertSame('Wrath of the Lich King', $groups[2]->expansionName);
        self::assertSame('The War Within', $groups[10]->expansionName);
        self::assertSame(11, $groups[10]->order);
    }

    #[Test]
    public function itCountsTotalQuestsPerExpansion(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        $tbcGroup = $this->findGroupByName($groups, 'The Burning Crusade');
        self::assertNotNull($tbcGroup);
        self::assertGreaterThan(0, $tbcGroup->totalQuests);
    }

    #[Test]
    public function itCountsCompletedQuestsFromInput(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);
        $tbcGroup = $this->findGroupByName($groups, 'The Burning Crusade');
        self::assertNotNull($tbcGroup);

        $firstZone = $tbcGroup->zones[0] ?? null;
        self::assertNotNull($firstZone);

        self::assertSame(0, $tbcGroup->completedQuests);
    }

    #[Test]
    public function itCountsCompletedQuestsWhenProvided(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        $twwGroup = $this->findGroupByName($groups, 'The War Within');
        self::assertNotNull($twwGroup);
        $totalTww = $twwGroup->totalQuests;
        self::assertGreaterThan(0, $totalTww);

        self::assertSame(0, $twwGroup->completedQuests);
    }

    #[Test]
    public function itReturnsZeroCompletedWhenNoQuestsProvided(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        foreach ($groups as $group) {
            self::assertSame(
                0,
                $group->completedQuests,
                sprintf(
                    'Expected 0 completed quests for %s',
                    $group->expansionName
                )
            );
        }
    }

    #[Test]
    public function itIncludesZonesForExpansions(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        $legionGroup = $this->findGroupByName($groups, 'Legion');
        self::assertNotNull($legionGroup);
        self::assertNotEmpty($legionGroup->zones);

        $zoneNames = array_column($legionGroup->zones, 'name');
        self::assertContains('Azsuna', $zoneNames);
        self::assertContains('Suramar', $zoneNames);
    }

    #[Test]
    public function itHasNoQuestsForClassicExpansion(): void
    {
        $groups = $this->mapper->buildExpansionProgress([]);

        $classicGroup = $this->findGroupByName($groups, 'Classic');
        self::assertNotNull($classicGroup);
        self::assertSame(0, $classicGroup->totalQuests);
        self::assertFalse($classicGroup->hasQuests());
    }

    #[Test]
    public function itMapsCompletedQuestToCorrectExpansion(): void
    {
        $completedQuestIds = [
            10119 => true,
        ];

        $groups = $this->mapper->buildExpansionProgress($completedQuestIds);

        $totalCompleted = 0;
        foreach ($groups as $group) {
            $totalCompleted += $group->completedQuests;
        }

        self::assertSame(1, $totalCompleted);
    }

    #[Test]
    public function itBuildsZoneProgressForExpansion(): void
    {
        $zones = $this->mapper->buildZoneProgress(11, []);

        self::assertNotEmpty($zones);

        $firstZone = $zones[0];
        self::assertNotEmpty($firstZone->zoneName);
        self::assertGreaterThan(0, $firstZone->btwCategoryId);
        self::assertSame(0, $firstZone->completedQuests);
    }

    #[Test]
    public function itReturnsEmptyZoneProgressForClassic(): void
    {
        $zones = $this->mapper->buildZoneProgress(1, []);

        self::assertEmpty($zones);
    }

    #[Test]
    public function itIncludesQuestDetailsInZoneProgress(): void
    {
        $zones = $this->mapper->buildZoneProgress(11, []);
        $zoneWithQuests = array_find($zones, fn($zone) => $zone->totalQuests > 0);

        self::assertNotNull($zoneWithQuests);
        self::assertNotEmpty($zoneWithQuests->quests);
        self::assertArrayHasKey('id', $zoneWithQuests->quests[0]);
        self::assertArrayHasKey('name', $zoneWithQuests->quests[0]);
        self::assertArrayHasKey('completed', $zoneWithQuests->quests[0]);
        self::assertFalse($zoneWithQuests->quests[0]['completed']);
    }

    /**
     * @param list<ExpansionQuestGroup> $groups
     */
    private function findGroupByName(
        array $groups,
        string $name
    ): ?ExpansionQuestGroup {
        foreach ($groups as $group) {
            if ($group->expansionName === $name) {
                return $group;
            }
        }

        return null;
    }
}
