<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\QuestCategoryIndex;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QuestCategoryIndexTest extends TestCase
{
    #[Test]
    public function itCreatesFromCompleteApiData(): void
    {
        $apiData = [
            'categories' => [
                ['id' => 1, 'name' => 'Classic'],
                ['id' => 2, 'name' => 'The Burning Crusade'],
                ['id' => 3, 'name' => 'Wrath of the Lich King'],
            ],
        ];

        $index = QuestCategoryIndex::fromApiData($apiData);

        self::assertCount(3, $index->categories);
        self::assertSame(1, $index->categories[0]['id']);
        self::assertSame('Classic', $index->categories[0]['name']);
        self::assertSame(3, $index->categories[2]['id']);
    }

    #[Test]
    public function itCreatesFromEmptyApiData(): void
    {
        $index = QuestCategoryIndex::fromApiData([]);

        self::assertCount(0, $index->categories);
    }

    #[Test]
    public function itFiltersInvalidEntries(): void
    {
        $apiData = [
            'categories' => [
                ['id' => 1, 'name' => 'Classic'],
                'invalid',
                ['id' => 3, 'name' => 'Wrath of the Lich King'],
            ],
        ];

        $index = QuestCategoryIndex::fromApiData($apiData);

        self::assertCount(2, $index->categories);
    }

    #[Test]
    public function itSeparatesExpansionFromOtherCategories(): void
    {
        $apiData = [
            'categories' => [
                ['id' => 1, 'name' => 'Classique'],
                ['id' => 2, 'name' => 'The Burning Crusade'],
                ['id' => 3, 'name' => 'Saisonnier'],
                ['id' => 4, 'name' => 'Dragonflight'],
                ['id' => 5, 'name' => 'JcJ'],
                ['id' => 6, 'name' => 'Shadowlands'],
            ],
        ];

        $index = QuestCategoryIndex::fromApiData($apiData);

        $expansions = $index->expansionCategories();
        $others = $index->otherCategories();

        self::assertCount(4, $expansions);
        self::assertSame('Classique', $expansions[0]['name']);
        self::assertSame('The Burning Crusade', $expansions[1]['name']);
        self::assertSame('Dragonflight', $expansions[2]['name']);
        self::assertSame('Shadowlands', $expansions[3]['name']);

        self::assertCount(2, $others);
        self::assertSame('Saisonnier', $others[0]['name']);
        self::assertSame('JcJ', $others[1]['name']);
    }

    #[Test]
    public function itRecognizesFrenchExpansionNames(): void
    {
        $apiData = [
            'categories' => [
                ['id' => 1, 'name' => 'Cataclysme'],
                ['id' => 2, 'name' => "La Col\u{00e8}re du Roi-liche"],
                ['id' => 3, 'name' => "L\u{00e9}gion"],
                ['id' => 4, 'name' => 'Bataille pour Azeroth'],
                ['id' => 5, 'name' => 'Vol des Dragons'],
            ],
        ];

        $index = QuestCategoryIndex::fromApiData($apiData);

        self::assertCount(5, $index->expansionCategories());
        self::assertCount(0, $index->otherCategories());
    }
}
