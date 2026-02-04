<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\QuestCategoryDetail;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QuestCategoryDetailTest extends TestCase
{
    #[Test]
    public function itCreatesFromCompleteApiData(): void
    {
        $apiData = [
            'id' => 15,
            'category' => ['name' => 'Dragonflight'],
            'quests' => [
                ['id' => 100, 'name' => 'The Dragon Isles Await'],
                ['id' => 101, 'name' => 'To the Dragon Isles!'],
            ],
            'sub_categories' => [
                [
                    'id' => 151,
                    'name' => "L'Envol des dragons",
                    'quests' => [
                        ['id' => 200, 'name' => 'Ruby Life Pools'],
                        ['id' => 201, 'name' => 'Flashfrost Assault'],
                    ],
                ],
                [
                    'id' => 152,
                    'name' => 'Les Plaines de Ohn\'ahra',
                    'quests' => [
                        ['id' => 300, 'name' => 'The Wind Calls'],
                    ],
                ],
            ],
        ];

        $detail = QuestCategoryDetail::fromApiData($apiData);

        self::assertSame(15, $detail->categoryId);
        self::assertSame('Dragonflight', $detail->categoryName);
        self::assertCount(2, $detail->quests);
        self::assertSame(100, $detail->quests[0]['id']);
        self::assertSame('The Dragon Isles Await', $detail->quests[0]['name']);
        self::assertCount(2, $detail->subCategories);
        self::assertSame(151, $detail->subCategories[0]['id']);
        self::assertSame("L'Envol des dragons", $detail->subCategories[0]['name']);
        self::assertCount(2, $detail->subCategories[0]['quests']);
        self::assertCount(1, $detail->subCategories[1]['quests']);
    }

    #[Test]
    public function itCreatesFromApiDataWithoutSubCategories(): void
    {
        $apiData = [
            'id' => 5,
            'category' => ['name' => 'Classic'],
            'quests' => [
                ['id' => 10, 'name' => 'A Simple Request'],
            ],
        ];

        $detail = QuestCategoryDetail::fromApiData($apiData);

        self::assertSame(5, $detail->categoryId);
        self::assertSame('Classic', $detail->categoryName);
        self::assertCount(1, $detail->quests);
        self::assertCount(0, $detail->subCategories);
    }

    #[Test]
    public function itCreatesFromEmptyApiData(): void
    {
        $detail = QuestCategoryDetail::fromApiData([]);

        self::assertSame(0, $detail->categoryId);
        self::assertSame('Inconnu', $detail->categoryName);
        self::assertCount(0, $detail->quests);
        self::assertCount(0, $detail->subCategories);
    }

    #[Test]
    public function itCountsTotalQuests(): void
    {
        $apiData = [
            'id' => 15,
            'category' => ['name' => 'Dragonflight'],
            'quests' => [
                ['id' => 100, 'name' => 'Quest 1'],
            ],
            'sub_categories' => [
                [
                    'id' => 151,
                    'name' => 'Zone 1',
                    'quests' => [
                        ['id' => 200, 'name' => 'Quest 2'],
                        ['id' => 201, 'name' => 'Quest 3'],
                    ],
                ],
            ],
        ];

        $detail = QuestCategoryDetail::fromApiData($apiData);

        self::assertSame(3, $detail->totalQuestCount());
    }
}
