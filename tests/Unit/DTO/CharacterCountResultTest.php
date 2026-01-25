<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CharacterCountResult;
use App\DTO\CharacterSummary;
use App\DTO\ClassCount;
use PHPUnit\Framework\TestCase;

final class CharacterCountResultTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $result = new CharacterCountResult(
            classes: [],
            totalCharacters: 0
        );

        $this->assertSame([], $result->classes);
        $this->assertSame(0, $result->totalCharacters);
        $this->assertNull($result->error);
    }

    public function testCanBeInstantiatedWithClasses(): void
    {
        $classCount = new ClassCount(
            name: 'Paladin',
            id: 2,
            count: 5,
            characters: []
        );

        $result = new CharacterCountResult(
            classes: [$classCount],
            totalCharacters: 5
        );

        $this->assertCount(1, $result->classes);
        $this->assertSame(5, $result->totalCharacters);
    }

    public function testCanBeInstantiatedWithError(): void
    {
        $result = new CharacterCountResult(
            classes: [],
            totalCharacters: 0,
            error: 'Something went wrong'
        );

        $this->assertSame('Something went wrong', $result->error);
    }

    public function testWithErrorFactoryMethod(): void
    {
        $result = CharacterCountResult::withError('Aucun compte WoW trouve');

        $this->assertSame([], $result->classes);
        $this->assertSame(0, $result->totalCharacters);
        $this->assertSame('Aucun compte WoW trouve', $result->error);
    }

    public function testHasErrorReturnsTrueWhenErrorExists(): void
    {
        $result = CharacterCountResult::withError('Error message');

        $this->assertTrue($result->hasError());
    }

    public function testHasErrorReturnsFalseWhenNoError(): void
    {
        $result = new CharacterCountResult(
            classes: [],
            totalCharacters: 0
        );

        $this->assertFalse($result->hasError());
    }

    public function testToArrayWithoutError(): void
    {
        $character = new CharacterSummary(
            name: 'Arthas',
            realm: 'Archimonde',
            level: 80,
            faction: 'Alliance'
        );

        $classCount = new ClassCount(
            name: 'Paladin',
            id: 2,
            count: 1,
            characters: [$character]
        );

        $result = new CharacterCountResult(
            classes: [$classCount],
            totalCharacters: 1
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('classes', $array);
        $this->assertArrayHasKey('total_characters', $array);
        $this->assertArrayNotHasKey('error', $array);

        $classes = $array['classes'];
        $this->assertIsArray($classes);
        $this->assertCount(1, $classes);
        $this->assertSame(1, $array['total_characters']);
    }

    public function testToArrayWithError(): void
    {
        $result = CharacterCountResult::withError('API error');

        $array = $result->toArray();

        $this->assertArrayHasKey('classes', $array);
        $this->assertArrayHasKey('total_characters', $array);
        $this->assertArrayHasKey('error', $array);
        $this->assertSame('API error', $array['error']);
    }

    public function testToArrayWithMultipleClasses(): void
    {
        $paladinClass = new ClassCount(
            name: 'Paladin',
            id: 2,
            count: 3,
            characters: []
        );

        $mageClass = new ClassCount(
            name: 'Mage',
            id: 8,
            count: 2,
            characters: []
        );

        $result = new CharacterCountResult(
            classes: [$paladinClass, $mageClass],
            totalCharacters: 5
        );

        $array = $result->toArray();

        $classes = $array['classes'];
        $this->assertIsArray($classes);
        $this->assertCount(2, $classes);

        $firstClass = $classes[0];
        $secondClass = $classes[1];
        $this->assertIsArray($firstClass);
        $this->assertIsArray($secondClass);

        $this->assertSame('Paladin', $firstClass['name']);
        $this->assertSame('Mage', $secondClass['name']);
        $this->assertSame(5, $array['total_characters']);
    }
}
