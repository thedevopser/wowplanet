<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class CharacterCountResult
{
    /**
     * @param array<int, ClassCount> $classes
     */
    public function __construct(
        public array $classes,
        public int $totalCharacters,
        public ?string $error = null
    ) {
    }

    public static function withError(string $error): self
    {
        return new self(
            classes: [],
            totalCharacters: 0,
            error: $error
        );
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'classes' => array_map(
                fn (ClassCount $classCount): array => $classCount->toArray(),
                $this->classes
            ),
            'total_characters' => $this->totalCharacters,
        ];

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        return $result;
    }
}
