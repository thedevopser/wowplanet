<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CharacterSummary;
use PHPUnit\Framework\TestCase;

final class CharacterSummaryTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $summary = new CharacterSummary(
            name: 'Arthas',
            realm: 'Archimonde',
            level: 80,
            faction: 'Alliance'
        );

        $this->assertSame('Arthas', $summary->name);
        $this->assertSame('Archimonde', $summary->realm);
        $this->assertSame(80, $summary->level);
        $this->assertSame('Alliance', $summary->faction);
    }

    public function testFromApiDataWithCompleteData(): void
    {
        $apiData = [
            'name' => 'Thrall',
            'realm' => ['name' => 'Hyjal'],
            'level' => 70,
            'faction' => ['name' => 'Horde'],
        ];

        $summary = CharacterSummary::fromApiData($apiData);

        $this->assertSame('Thrall', $summary->name);
        $this->assertSame('Hyjal', $summary->realm);
        $this->assertSame(70, $summary->level);
        $this->assertSame('Horde', $summary->faction);
    }

    public function testFromApiDataWithMissingName(): void
    {
        $apiData = [
            'realm' => ['name' => 'Hyjal'],
            'level' => 70,
            'faction' => ['name' => 'Horde'],
        ];

        $summary = CharacterSummary::fromApiData($apiData);

        $this->assertSame('Inconnu', $summary->name);
    }

    public function testFromApiDataWithMissingRealm(): void
    {
        $apiData = [
            'name' => 'Thrall',
            'level' => 70,
            'faction' => ['name' => 'Horde'],
        ];

        $summary = CharacterSummary::fromApiData($apiData);

        $this->assertSame('Inconnu', $summary->realm);
    }

    public function testFromApiDataWithRealmNotArray(): void
    {
        $apiData = [
            'name' => 'Thrall',
            'realm' => 'invalid',
            'level' => 70,
            'faction' => ['name' => 'Horde'],
        ];

        $summary = CharacterSummary::fromApiData($apiData);

        $this->assertSame('Inconnu', $summary->realm);
    }

    public function testFromApiDataWithMissingLevel(): void
    {
        $apiData = [
            'name' => 'Thrall',
            'realm' => ['name' => 'Hyjal'],
            'faction' => ['name' => 'Horde'],
        ];

        $summary = CharacterSummary::fromApiData($apiData);

        $this->assertSame(0, $summary->level);
    }

    public function testFromApiDataWithMissingFaction(): void
    {
        $apiData = [
            'name' => 'Thrall',
            'realm' => ['name' => 'Hyjal'],
            'level' => 70,
        ];

        $summary = CharacterSummary::fromApiData($apiData);

        $this->assertSame('Inconnu', $summary->faction);
    }

    public function testFromApiDataWithFactionNotArray(): void
    {
        $apiData = [
            'name' => 'Thrall',
            'realm' => ['name' => 'Hyjal'],
            'level' => 70,
            'faction' => 'invalid',
        ];

        $summary = CharacterSummary::fromApiData($apiData);

        $this->assertSame('Inconnu', $summary->faction);
    }

    public function testToArray(): void
    {
        $summary = new CharacterSummary(
            name: 'Jaina',
            realm: 'Dalaran',
            level: 80,
            faction: 'Alliance'
        );

        $array = $summary->toArray();

        $this->assertSame([
            'name' => 'Jaina',
            'realm' => 'Dalaran',
            'level' => 80,
            'faction' => 'Alliance',
        ], $array);
    }
}
