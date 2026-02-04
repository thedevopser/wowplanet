<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class QuestCategoryDetail
{
    /**
     * @param list<array{id: int, name: string}> $quests
     * @param list<array{id: int, name: string, quests: list<array{id: int, name: string}>}> $subCategories
     */
    public function __construct(
        public int $categoryId,
        public string $categoryName,
        public array $quests,
        public array $subCategories
    ) {
    }

    /**
     * @param array<string, mixed> $categoryData
     */
    /**
     * @param array<string, mixed> $categoryData
     */
    public static function fromApiData(
        array $categoryData,
        string $fallbackName = 'Inconnu'
    ): self {
        return new self(
            categoryId: is_int($categoryData['id'] ?? null) ? $categoryData['id'] : 0,
            categoryName: self::extractCategoryName($categoryData, $fallbackName),
            quests: self::extractQuests($categoryData['quests'] ?? []),
            subCategories: self::extractSubCategories(
                $categoryData['sub_categories'] ?? $categoryData['categories'] ?? []
            ),
        );
    }

    public function totalQuestCount(): int
    {
        $count = count($this->quests);

        foreach ($this->subCategories as $subCategory) {
            $count += count($subCategory['quests']);
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractCategoryName(
        array $data,
        string $fallbackName
    ): string {
        $category = is_array($data['category'] ?? null) ? $data['category'] : [];

        if (is_string($category['name'] ?? null)) {
            return $category['name'];
        }

        if (is_string($data['title'] ?? null)) {
            return $data['title'];
        }

        if (is_string($data['name'] ?? null)) {
            return $data['name'];
        }

        return $fallbackName;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private static function extractQuests(mixed $rawQuests): array
    {
        if (!is_array($rawQuests)) {
            return [];
        }

        $quests = [];

        foreach ($rawQuests as $quest) {
            if (!is_array($quest)) {
                continue;
            }

            $id = $quest['id'] ?? null;
            $name = $quest['name'] ?? $quest['title'] ?? null;

            if (!is_int($id) || !is_string($name)) {
                continue;
            }

            $quests[] = ['id' => $id, 'name' => $name];
        }

        return $quests;
    }

    /**
     * @return list<array{id: int, name: string, quests: list<array{id: int, name: string}>}>
     */
    private static function extractSubCategories(mixed $rawSubCategories): array
    {
        if (!is_array($rawSubCategories)) {
            return [];
        }

        $subCategories = [];

        foreach ($rawSubCategories as $subCategory) {
            if (!is_array($subCategory)) {
                continue;
            }

            $id = $subCategory['id'] ?? null;
            $name = $subCategory['name'] ?? $subCategory['title'] ?? null;

            if (!is_int($id) || !is_string($name)) {
                continue;
            }

            $subCategories[] = [
                'id' => $id,
                'name' => $name,
                'quests' => self::extractQuests($subCategory['quests'] ?? []),
            ];
        }

        return $subCategories;
    }
}
