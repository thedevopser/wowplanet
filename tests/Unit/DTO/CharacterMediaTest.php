<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CharacterMedia;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CharacterMediaTest extends TestCase
{
    #[Test]
    public function itCreatesFromCompleteApiData(): void
    {
        $apiData = [
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/avatar.jpg'],
                ['key' => 'inset', 'value' => 'https://render.worldofwarcraft.com/inset.jpg'],
                ['key' => 'main-raw', 'value' => 'https://render.worldofwarcraft.com/main-raw.png'],
            ],
        ];

        $media = CharacterMedia::fromApiData($apiData);

        self::assertSame('https://render.worldofwarcraft.com/avatar.jpg', $media->avatarUrl);
        self::assertSame('https://render.worldofwarcraft.com/inset.jpg', $media->renderUrl);
        self::assertSame('https://render.worldofwarcraft.com/main-raw.png', $media->mainRawUrl);
    }

    #[Test]
    public function itCreatesFromEmptyAssets(): void
    {
        $media = CharacterMedia::fromApiData(['assets' => []]);

        self::assertNull($media->avatarUrl);
        self::assertNull($media->renderUrl);
        self::assertNull($media->mainRawUrl);
    }

    #[Test]
    public function itCreatesFromEmptyApiData(): void
    {
        $media = CharacterMedia::fromApiData([]);

        self::assertNull($media->avatarUrl);
        self::assertNull($media->renderUrl);
        self::assertNull($media->mainRawUrl);
    }

    #[Test]
    public function itCreatesFromPartialAssets(): void
    {
        $apiData = [
            'assets' => [
                ['key' => 'avatar', 'value' => 'https://render.worldofwarcraft.com/avatar.jpg'],
            ],
        ];

        $media = CharacterMedia::fromApiData($apiData);

        self::assertSame('https://render.worldofwarcraft.com/avatar.jpg', $media->avatarUrl);
        self::assertNull($media->renderUrl);
        self::assertNull($media->mainRawUrl);
    }
}
