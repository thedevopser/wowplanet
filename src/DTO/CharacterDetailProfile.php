<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class CharacterDetailProfile
{
    public function __construct(
        public string $name,
        public int $level,
        public string $realmName,
        public string $realmSlug,
        public string $raceName,
        public string $className,
        public string $factionName,
        public string $factionType,
        public string $activeSpecializationName,
        public int $achievementPoints,
        public int $equippedItemLevel,
        public ?string $guildName,
        public int $classId
    ) {
    }

    /**
     * @param array<string, mixed> $profileData
     */
    public static function fromApiData(array $profileData): self
    {
        $realm = is_array($profileData['realm'] ?? null) ? $profileData['realm'] : [];
        $race = is_array($profileData['race'] ?? null) ? $profileData['race'] : [];
        $characterClass = is_array($profileData['character_class'] ?? null) ? $profileData['character_class'] : [];
        $faction = is_array($profileData['faction'] ?? null) ? $profileData['faction'] : [];
        $activeSpec = is_array($profileData['active_spec'] ?? null) ? $profileData['active_spec'] : [];
        $guild = is_array($profileData['guild'] ?? null) ? $profileData['guild'] : null;

        return new self(
            name: is_string($profileData['name'] ?? null) ? $profileData['name'] : 'Inconnu',
            level: is_int($profileData['level'] ?? null) ? $profileData['level'] : 0,
            realmName: is_string($realm['name'] ?? null) ? $realm['name'] : 'Inconnu',
            realmSlug: is_string($realm['slug'] ?? null) ? $realm['slug'] : '',
            raceName: is_string($race['name'] ?? null) ? $race['name'] : 'Inconnu',
            className: is_string($characterClass['name'] ?? null) ? $characterClass['name'] : 'Inconnu',
            factionName: is_string($faction['name'] ?? null) ? $faction['name'] : 'Inconnu',
            factionType: is_string($faction['type'] ?? null) ? $faction['type'] : '',
            activeSpecializationName: is_string($activeSpec['name'] ?? null) ? $activeSpec['name'] : '',
            achievementPoints: self::extractInt($profileData, 'achievement_points'),
            equippedItemLevel: self::extractInt($profileData, 'equipped_item_level'),
            guildName: $guild !== null && is_string($guild['name'] ?? null) ? $guild['name'] : null,
            classId: is_int($characterClass['id'] ?? null) ? $characterClass['id'] : 0,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : 0;
    }
}
