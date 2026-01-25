<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CharacterSummary;
use App\DTO\ClassCount;
use PHPUnit\Framework\TestCase;

final class ClassCountTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $classCount = new ClassCount(
            name: 'Paladin',
            id: 2,
            count: 5,
            characters: []
        );

        $this->assertSame('Paladin', $classCount->name);
        $this->assertSame(2, $classCount->id);
        $this->assertSame(5, $classCount->count);
        $this->assertSame([], $classCount->characters);
    }

    public function testCanBeInstantiatedWithCharacters(): void
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

        $this->assertCount(1, $classCount->characters);
        $this->assertSame('Arthas', $classCount->characters[0]->name);
    }

    public function testAddCharacterReturnsNewInstance(): void
    {
        $classCount = new ClassCount(
            name: 'Paladin',
            id: 2,
            count: 0,
            characters: []
        );

        $character = new CharacterSummary(
            name: 'Uther',
            realm: 'Hyjal',
            level: 70,
            faction: 'Alliance'
        );

        $newClassCount = $classCount->addCharacter($character);

        $this->assertNotSame($classCount, $newClassCount);
        $this->assertSame(0, $classCount->count);
        $this->assertSame(1, $newClassCount->count);
        $this->assertCount(0, $classCount->characters);
        $this->assertCount(1, $newClassCount->characters);
    }

    public function testAddCharacterIncrementsCount(): void
    {
        $classCount = new ClassCount(
            name: 'Mage',
            id: 8,
            count: 2,
            characters: []
        );

        $character = new CharacterSummary(
            name: 'Jaina',
            realm: 'Dalaran',
            level: 80,
            faction: 'Alliance'
        );

        $newClassCount = $classCount->addCharacter($character);

        $this->assertSame(3, $newClassCount->count);
    }

    public function testAddCharacterPreservesExistingCharacters(): void
    {
        $existingCharacter = new CharacterSummary(
            name: 'Khadgar',
            realm: 'Dalaran',
            level: 80,
            faction: 'Alliance'
        );

        $classCount = new ClassCount(
            name: 'Mage',
            id: 8,
            count: 1,
            characters: [$existingCharacter]
        );

        $newCharacter = new CharacterSummary(
            name: 'Jaina',
            realm: 'Dalaran',
            level: 80,
            faction: 'Alliance'
        );

        $newClassCount = $classCount->addCharacter($newCharacter);

        $this->assertCount(2, $newClassCount->characters);
        $this->assertSame('Khadgar', $newClassCount->characters[0]->name);
        $this->assertSame('Jaina', $newClassCount->characters[1]->name);
    }

    public function testToArray(): void
    {
        $character = new CharacterSummary(
            name: 'Thrall',
            realm: 'Hyjal',
            level: 70,
            faction: 'Horde'
        );

        $classCount = new ClassCount(
            name: 'Shaman',
            id: 7,
            count: 1,
            characters: [$character]
        );

        $array = $classCount->toArray();

        $this->assertSame('Shaman', $array['name']);
        $this->assertSame(7, $array['id']);
        $this->assertSame(1, $array['count']);

        $characters = $array['characters'];
        $this->assertIsArray($characters);
        $this->assertCount(1, $characters);

        $firstCharacter = $characters[0];
        $this->assertIsArray($firstCharacter);
        $this->assertSame('Thrall', $firstCharacter['name']);
    }

    public function testToArrayWithEmptyCharacters(): void
    {
        $classCount = new ClassCount(
            name: 'Warrior',
            id: 1,
            count: 0,
            characters: []
        );

        $array = $classCount->toArray();

        $this->assertSame([
            'name' => 'Warrior',
            'id' => 1,
            'count' => 0,
            'characters' => [],
        ], $array);
    }
}
