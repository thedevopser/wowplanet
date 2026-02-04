<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CharacterDetailProfile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CharacterDetailProfileTest extends TestCase
{
    #[Test]
    public function itCreatesFromCompleteApiData(): void
    {
        $apiData = $this->completeApiData();

        $profile = CharacterDetailProfile::fromApiData($apiData);

        self::assertSame('Thrall', $profile->name);
        self::assertSame(80, $profile->level);
        self::assertSame('Hyjal', $profile->realmName);
        self::assertSame('hyjal', $profile->realmSlug);
        self::assertSame('Orc', $profile->raceName);
        self::assertSame('Shaman', $profile->className);
        self::assertSame('Horde', $profile->factionName);
        self::assertSame('HORDE', $profile->factionType);
        self::assertSame('Enhancement', $profile->activeSpecializationName);
        self::assertSame(18500, $profile->achievementPoints);
        self::assertSame(620, $profile->equippedItemLevel);
        self::assertSame('Warsong Clan', $profile->guildName);
        self::assertSame(7, $profile->classId);
    }

    #[Test]
    public function itCreatesFromApiDataWithoutGuild(): void
    {
        $apiData = $this->completeApiData();
        unset($apiData['guild']);

        $profile = CharacterDetailProfile::fromApiData($apiData);

        self::assertNull($profile->guildName);
    }

    #[Test]
    public function itCreatesFromApiDataWithMissingOptionalFields(): void
    {
        $apiData = [
            'name' => 'Arthas',
            'level' => 70,
            'realm' => ['name' => 'Archimonde', 'slug' => 'archimonde'],
            'character_class' => ['name' => 'Death Knight', 'id' => 6],
            'faction' => ['name' => 'Alliance', 'type' => 'ALLIANCE'],
        ];

        $profile = CharacterDetailProfile::fromApiData($apiData);

        self::assertSame('Arthas', $profile->name);
        self::assertSame(70, $profile->level);
        self::assertSame('Archimonde', $profile->realmName);
        self::assertSame('archimonde', $profile->realmSlug);
        self::assertSame('Inconnu', $profile->raceName);
        self::assertSame('Death Knight', $profile->className);
        self::assertSame('Alliance', $profile->factionName);
        self::assertSame('', $profile->activeSpecializationName);
        self::assertSame(0, $profile->achievementPoints);
        self::assertSame(0, $profile->equippedItemLevel);
        self::assertNull($profile->guildName);
        self::assertSame(6, $profile->classId);
    }

    #[Test]
    public function itCreatesFromEmptyApiData(): void
    {
        $profile = CharacterDetailProfile::fromApiData([]);

        self::assertSame('Inconnu', $profile->name);
        self::assertSame(0, $profile->level);
        self::assertSame('Inconnu', $profile->realmName);
        self::assertSame('', $profile->realmSlug);
        self::assertSame('Inconnu', $profile->raceName);
        self::assertSame('Inconnu', $profile->className);
        self::assertSame('Inconnu', $profile->factionName);
        self::assertSame('', $profile->factionType);
        self::assertSame('', $profile->activeSpecializationName);
        self::assertSame(0, $profile->achievementPoints);
        self::assertSame(0, $profile->equippedItemLevel);
        self::assertNull($profile->guildName);
        self::assertSame(0, $profile->classId);
    }

    /**
     * @return array<string, mixed>
     */
    private function completeApiData(): array
    {
        return [
            'name' => 'Thrall',
            'level' => 80,
            'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
            'race' => ['name' => 'Orc'],
            'character_class' => ['name' => 'Shaman', 'id' => 7],
            'faction' => ['name' => 'Horde', 'type' => 'HORDE'],
            'active_spec' => ['name' => 'Enhancement'],
            'achievement_points' => 18500,
            'equipped_item_level' => 620,
            'guild' => ['name' => 'Warsong Clan'],
        ];
    }
}
