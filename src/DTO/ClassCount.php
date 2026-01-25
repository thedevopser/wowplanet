<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ClassCount
{
    /**
     * @param array<int, CharacterSummary> $characters
     */
    public function __construct(
        public string $name,
        public int $id,
        public int $count,
        public array $characters
    ) {
    }

    public function addCharacter(CharacterSummary $character): self
    {
        $characters = $this->characters;
        $characters[] = $character;

        return new self(
            name: $this->name,
            id: $this->id,
            count: $this->count + 1,
            characters: $characters
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'id' => $this->id,
            'count' => $this->count,
            'characters' => array_map(
                fn (CharacterSummary $char): array => $char->toArray(),
                $this->characters
            ),
        ];
    }
}
