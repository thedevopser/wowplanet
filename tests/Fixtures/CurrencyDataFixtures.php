<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

final class CurrencyDataFixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function validJsonData(): array
    {
        return [
            'characters' => [
                [
                    'name' => 'Prøtactinium',
                    'faction' => 'Alliance',
                    'realm' => 'Dalaran',
                    'class' => 'Mage',
                    'level' => 80,
                    'lastUpdate' => 1734567890,
                    'currencies' => [
                        [
                            'name' => 'Pierre de vol',
                            'quantity' => 1500,
                            'id' => 2245,
                            'maxQuantity' => 2000,
                            'isAccountTransferable' => true,
                            'transferPercentage' => 100,
                            'isAccountWide' => false,
                        ],
                        [
                            'name' => 'Deniers',
                            'quantity' => 2625,
                            'id' => 2003,
                            'maxQuantity' => 5000,
                            'isAccountTransferable' => false,
                            'transferPercentage' => 0,
                            'isAccountWide' => false,
                        ],
                    ],
                ],
                [
                    'name' => 'Testchar',
                    'faction' => 'Horde',
                    'realm' => 'Dalaran',
                    'class' => 'Warrior',
                    'level' => 70,
                    'lastUpdate' => 1734567891,
                    'currencies' => [
                        [
                            'name' => 'Pierre de vol',
                            'quantity' => 500,
                            'id' => 2245,
                            'maxQuantity' => 2000,
                            'isAccountTransferable' => true,
                            'transferPercentage' => 100,
                            'isAccountWide' => false,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function validJsonDataFlat(): array
    {
        return [
            [
                'name' => 'Character1',
                'faction' => 'Alliance',
                'currencies' => [
                    ['name' => 'Gold', 'quantity' => 1000],
                ],
            ],
        ];
    }

    public static function validBase64WithHeader(): string
    {
        $jsonData = self::validJsonData();
        $jsonString = json_encode($jsonData);

        if ($jsonString === false) {
            throw new \RuntimeException('Failed to encode JSON');
        }

        return 'CT_BASE64_V1:' . base64_encode($jsonString);
    }

    public static function invalidHeaderBase64(): string
    {
        $jsonData = self::validJsonData();
        $jsonString = json_encode($jsonData);

        if ($jsonString === false) {
            throw new \RuntimeException('Failed to encode JSON');
        }

        return 'CT_BASE64_V2:' . base64_encode($jsonString);
    }

    public static function plainBase64NoHeader(): string
    {
        $jsonData = self::validJsonData();
        $jsonString = json_encode($jsonData);

        if ($jsonString === false) {
            throw new \RuntimeException('Failed to encode JSON');
        }

        return base64_encode($jsonString);
    }

    public static function largeDataExceeding5MB(): string
    {
        $largeString = str_repeat('A', 6000000);

        return 'CT_BASE64_V1:' . base64_encode($largeString);
    }

    public static function invalidJsonBase64(): string
    {
        return 'CT_BASE64_V1:' . base64_encode('{"invalid json syntax');
    }

    public static function corruptedBase64(): string
    {
        return 'CT_BASE64_V1:INVALID!!!BASE64===';
    }

    public static function jsonWithControlCharacters(): string
    {
        $jsonWithControl = '{"characters":[{"name":"Test","description":"Line1\nLine2\rLine3","currencies":[]}]}';

        return 'CT_BASE64_V1:' . base64_encode($jsonWithControl);
    }
}
