<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig\Components;

use App\Twig\Components\Card;
use PHPUnit\Framework\TestCase;

final class CardTest extends TestCase
{
    public function testCardCanBeInstantiated(): void
    {
        $card = new Card();

        $this->assertInstanceOf(Card::class, $card);
    }

    public function testCardHasDefaultValues(): void
    {
        $card = new Card();

        $this->assertSame('', $card->icon);
        $this->assertSame('', $card->title);
        $this->assertSame('', $card->description);
        $this->assertNull($card->buttonText);
        $this->assertNull($card->buttonUrl);
        $this->assertFalse($card->disabled);
        $this->assertTrue($card->hover);
    }

    public function testCardPropertiesCanBeSet(): void
    {
        $card = new Card();
        $card->icon = '⚔️';
        $card->title = 'Test Title';
        $card->description = 'Test Description';
        $card->buttonText = 'Click me';
        $card->buttonUrl = '/test';
        $card->disabled = true;
        $card->hover = false;

        $this->assertSame('⚔️', $card->icon);
        $this->assertSame('Test Title', $card->title);
        $this->assertSame('Test Description', $card->description);
        $this->assertSame('Click me', $card->buttonText);
        $this->assertSame('/test', $card->buttonUrl);
        $this->assertTrue($card->disabled);
        $this->assertFalse($card->hover);
    }

    public function testCardCanBeDisabled(): void
    {
        $card = new Card();
        $card->disabled = true;

        $this->assertTrue($card->disabled);
    }

    public function testCardCanHaveHoverDisabled(): void
    {
        $card = new Card();
        $card->hover = false;

        $this->assertFalse($card->hover);
    }

    public function testCardCanHaveButtonWithoutUrl(): void
    {
        $card = new Card();
        $card->buttonText = 'Click me';

        $this->assertSame('Click me', $card->buttonText);
        $this->assertNull($card->buttonUrl);
    }
}
