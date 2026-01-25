<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\BlizzardApiServiceInterface;
use App\Service\CharacterCountService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CharacterCountServiceTest extends TestCase
{
    private MockObject&BlizzardApiServiceInterface $apiService;
    private LoggerInterface $logger;
    private CharacterCountService $service;

    protected function setUp(): void
    {
        $this->apiService = $this->createMock(BlizzardApiServiceInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->service = new CharacterCountService($this->apiService, $this->logger);
    }

    public function testCountCharactersByClassWithNoWowAccounts(): void
    {
        $this->apiService->method('fetchUserProfile')
            ->willReturn([]);

        $result = $this->service->countCharactersByClass('fake_token');

        $this->assertTrue($result->hasError());
        $this->assertSame('Aucun compte WoW trouvé. Vérifiez vos autorisations.', $result->error);
        $this->assertSame([], $result->classes);
        $this->assertSame(0, $result->totalCharacters);
    }

    public function testCountCharactersByClassWithEmptyWowAccounts(): void
    {
        $this->apiService->method('fetchUserProfile')
            ->willReturn(['wow_accounts' => []]);

        $result = $this->service->countCharactersByClass('fake_token');

        $this->assertFalse($result->hasError());
        $this->assertSame([], $result->classes);
        $this->assertSame(0, $result->totalCharacters);
    }

    public function testCountCharactersByClassWithSingleCharacter(): void
    {
        $profile = [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Arthas',
                            'realm' => ['name' => 'Archimonde', 'slug' => 'archimonde'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->method('fetchUserProfile')
            ->willReturn($profile);

        $result = $this->service->countCharactersByClass('fake_token');

        $this->assertFalse($result->hasError());
        $this->assertCount(1, $result->classes);
        $this->assertSame('Paladin', $result->classes[0]->name);
        $this->assertSame(1, $result->classes[0]->count);
        $this->assertSame(1, $result->totalCharacters);
    }

    public function testCountCharactersByClassWithMultipleCharactersSameClass(): void
    {
        $profile = [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Arthas',
                            'realm' => ['name' => 'Archimonde', 'slug' => 'archimonde'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                        [
                            'name' => 'Uther',
                            'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                            'level' => 70,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->method('fetchUserProfile')
            ->willReturn($profile);

        $result = $this->service->countCharactersByClass('fake_token');

        $this->assertFalse($result->hasError());
        $this->assertCount(1, $result->classes);
        $this->assertSame('Paladin', $result->classes[0]->name);
        $this->assertSame(2, $result->classes[0]->count);
        $this->assertSame(2, $result->totalCharacters);
    }

    public function testCountCharactersByClassWithMultipleDifferentClasses(): void
    {
        $profile = [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Arthas',
                            'realm' => ['name' => 'Archimonde', 'slug' => 'archimonde'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                        [
                            'name' => 'Jaina',
                            'realm' => ['name' => 'Dalaran', 'slug' => 'dalaran'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 8, 'name' => 'Mage'],
                        ],
                        [
                            'name' => 'Thrall',
                            'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                            'level' => 70,
                            'faction' => ['name' => 'Horde'],
                            'playable_class' => ['id' => 7, 'name' => 'Shaman'],
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->method('fetchUserProfile')
            ->willReturn($profile);

        $result = $this->service->countCharactersByClass('fake_token');

        $this->assertFalse($result->hasError());
        $this->assertCount(3, $result->classes);
        $this->assertSame(3, $result->totalCharacters);
    }

    public function testCountCharactersByClassSortsByCountDescending(): void
    {
        $profile = [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Mage1',
                            'realm' => ['name' => 'Realm', 'slug' => 'realm'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 8, 'name' => 'Mage'],
                        ],
                        [
                            'name' => 'Paladin1',
                            'realm' => ['name' => 'Realm', 'slug' => 'realm'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                        [
                            'name' => 'Paladin2',
                            'realm' => ['name' => 'Realm', 'slug' => 'realm'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                        [
                            'name' => 'Paladin3',
                            'realm' => ['name' => 'Realm', 'slug' => 'realm'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                        [
                            'name' => 'Warrior1',
                            'realm' => ['name' => 'Realm', 'slug' => 'realm'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 1, 'name' => 'Warrior'],
                        ],
                        [
                            'name' => 'Warrior2',
                            'realm' => ['name' => 'Realm', 'slug' => 'realm'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 1, 'name' => 'Warrior'],
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->method('fetchUserProfile')
            ->willReturn($profile);

        $result = $this->service->countCharactersByClass('fake_token');

        $this->assertSame('Paladin', $result->classes[0]->name);
        $this->assertSame(3, $result->classes[0]->count);
        $this->assertSame('Warrior', $result->classes[1]->name);
        $this->assertSame(2, $result->classes[1]->count);
        $this->assertSame('Mage', $result->classes[2]->name);
        $this->assertSame(1, $result->classes[2]->count);
    }

    public function testCountCharactersByClassWithMultipleWowAccounts(): void
    {
        $profile = [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Arthas',
                            'realm' => ['name' => 'Archimonde', 'slug' => 'archimonde'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                    ],
                ],
                [
                    'characters' => [
                        [
                            'name' => 'Thrall',
                            'realm' => ['name' => 'Hyjal', 'slug' => 'hyjal'],
                            'level' => 70,
                            'faction' => ['name' => 'Horde'],
                            'playable_class' => ['id' => 7, 'name' => 'Shaman'],
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->method('fetchUserProfile')
            ->willReturn($profile);

        $result = $this->service->countCharactersByClass('fake_token');

        $this->assertCount(2, $result->classes);
        $this->assertSame(2, $result->totalCharacters);
    }

    public function testCountCharactersByClassSkipsCharactersWithoutPlayableClass(): void
    {
        $profile = [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Arthas',
                            'realm' => ['name' => 'Archimonde', 'slug' => 'archimonde'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                        [
                            'name' => 'NoClass',
                            'realm' => ['name' => 'Realm', 'slug' => 'realm'],
                            'level' => 10,
                            'faction' => ['name' => 'Alliance'],
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->method('fetchUserProfile')
            ->willReturn($profile);

        $result = $this->service->countCharactersByClass('fake_token');

        $this->assertCount(1, $result->classes);
        $this->assertSame('Paladin', $result->classes[0]->name);
    }

    public function testCountCharactersByClassSkipsCharactersWithInvalidPlayableClass(): void
    {
        $profile = [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Arthas',
                            'realm' => ['name' => 'Archimonde', 'slug' => 'archimonde'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                        [
                            'name' => 'InvalidClass',
                            'realm' => ['name' => 'Realm', 'slug' => 'realm'],
                            'level' => 10,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => 'not_an_array',
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->method('fetchUserProfile')
            ->willReturn($profile);

        $result = $this->service->countCharactersByClass('fake_token');

        $this->assertCount(1, $result->classes);
    }

    public function testCharacterDetailsAreStoredCorrectly(): void
    {
        $profile = [
            'wow_accounts' => [
                [
                    'characters' => [
                        [
                            'name' => 'Arthas',
                            'realm' => ['name' => 'Archimonde', 'slug' => 'archimonde'],
                            'level' => 80,
                            'faction' => ['name' => 'Alliance'],
                            'playable_class' => ['id' => 2, 'name' => 'Paladin'],
                        ],
                    ],
                ],
            ],
        ];

        $this->apiService->method('fetchUserProfile')
            ->willReturn($profile);

        $result = $this->service->countCharactersByClass('fake_token');

        $characters = $result->classes[0]->characters;
        $this->assertCount(1, $characters);
        $this->assertSame('Arthas', $characters[0]->name);
        $this->assertSame('Archimonde', $characters[0]->realm);
        $this->assertSame(80, $characters[0]->level);
        $this->assertSame('Alliance', $characters[0]->faction);
    }
}
