<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class QuestCategoryIndex
{
    private const array EXPANSION_PATTERNS = [
        'classic',
        'classique',
        'burning crusade',
        'wrath of the lich king',
        'colère du roi-liche',
        'cataclysm',
        'cataclysme',
        'mists of pandaria',
        'brumes de pandarie',
        'warlords of draenor',
        'seigneurs de guerre',
        'legion',
        'légion',
        'battle for azeroth',
        'bataille pour azeroth',
        'shadowlands',
        'dragonflight',
        'vol des dragons',
        'the war within',
        'la guerre intérieure',
    ];

    /**
     * @param list<array{id: int, name: string}> $categories
     */
    public function __construct(
        public array $categories
    ) {
    }

    /**
     * @param array<string, mixed> $indexData
     */
    public static function fromApiData(array $indexData): self
    {
        $rawCategories = is_array($indexData['categories'] ?? null) ? $indexData['categories'] : [];
        $categories = [];

        foreach ($rawCategories as $category) {
            if (!is_array($category)) {
                continue;
            }

            $id = $category['id'] ?? null;
            $name = $category['name'] ?? null;

            if (!is_int($id) || !is_string($name)) {
                continue;
            }

            $categories[] = ['id' => $id, 'name' => $name];
        }

        return new self($categories);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function expansionCategories(): array
    {
        return array_values(
            array_filter(
                $this->categories,
                static fn (array $category): bool => self::isExpansion($category['name'])
            )
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function otherCategories(): array
    {
        return array_values(
            array_filter(
                $this->categories,
                static fn (array $category): bool => !self::isExpansion($category['name'])
            )
        );
    }

    private static function isExpansion(string $categoryName): bool
    {
        $lowered = mb_strtolower($categoryName);

        return array_any(
            self::EXPANSION_PATTERNS,
            static fn (string $pattern): bool => str_contains($lowered, $pattern)
        );
    }
}
