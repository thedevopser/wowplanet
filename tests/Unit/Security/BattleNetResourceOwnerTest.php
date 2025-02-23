<?php

declare(strict_types = 1);

namespace App\Tests\Unit\Security;

use App\Security\BattleNetResourceOwner;
use PHPUnit\Framework\TestCase;

class BattleNetResourceOwnerTest extends TestCase
{
    public function testGetters(): void
    {
        $response = [
            'sub'       => '12345',
            'battletag' => 'Player#1234',
        ];

        $owner = new BattleNetResourceOwner($response);

        $this->assertEquals('12345', $owner->getId());
        $this->assertEquals('Player#1234', $owner->getBattletag());
        $this->assertEquals('Player', $owner->getBattletagName());
        $this->assertEquals('1234', $owner->getBattletagDiscriminator());
    }

    public function testEmptyResponse(): void
    {
        $owner = new BattleNetResourceOwner([]);

        $this->assertEquals('', $owner->getId());
        $this->assertNull($owner->getBattletag());
        $this->assertNull($owner->getBattletagName());
        $this->assertNull($owner->getBattletagDiscriminator());
    }
}
