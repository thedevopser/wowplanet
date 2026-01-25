<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class CharacterSummary
{
    public function __construct(
        public string $name,
        public string $realm,
        public int $level,
        public string $faction
    ) {
    }

    /**
     * @param array<string, mixed> $characterData
     */
    public static function fromApiData(array $characterData): self
    {
        $realm = $characterData['realm'] ?? null;
        $realmName = is_array($realm) ? (is_string($realm['name'] ?? null) ? $realm['name'] : 'Inconnu') : 'Inconnu';

        $faction = $characterData['faction'] ?? null;
        $factionName = is_array($faction) ? (is_string($faction['name'] ?? null) ? $faction['name'] : 'Inconnu') : 'Inconnu';

        $name = $characterData['name'] ?? null;
        $level = $characterData['level'] ?? null;

        return new self(
            name: is_string($name) ? $name : 'Inconnu',
            realm: $realmName,
            level: is_int($level) ? $level : 0,
            faction: $factionName
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'realm' => $this->realm,
            'level' => $this->level,
            'faction' => $this->faction,
        ];
    }
}
