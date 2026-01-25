<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CharacterCountResult;
use App\DTO\CharacterSummary;
use App\DTO\ClassCount;
use Psr\Log\LoggerInterface;

final readonly class CharacterCountService
{
    public function __construct(
        private BlizzardApiServiceInterface $apiService,
        private LoggerInterface $logger
    ) {
    }

    public function countCharactersByClass(string $accessToken): CharacterCountResult
    {
        $profile = $this->apiService->fetchUserProfile($accessToken);

        if (!isset($profile['wow_accounts']) || !is_array($profile['wow_accounts'])) {
            $this->logger->error('No WoW accounts found in profile');
            return CharacterCountResult::withError('Aucun compte WoW trouvé. Vérifiez vos autorisations.');
        }

        $characters = $this->extractCharactersFromProfile($profile);

        $this->logger->info('Found characters in profile', ['count' => count($characters)]);

        $classCounts = $this->groupCharactersByClass($characters);

        $this->logger->info('Character count by class completed', [
            'total_characters' => count($characters),
            'classes_found' => count($classCounts),
        ]);

        return new CharacterCountResult(
            classes: $classCounts,
            totalCharacters: count($characters)
        );
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<int, array<string, mixed>>
     */
    private function extractCharactersFromProfile(array $profile): array
    {
        /** @var array<int, array<string, mixed>> $characters */
        $characters = [];

        $wowAccounts = $profile['wow_accounts'] ?? [];
        if (!is_array($wowAccounts)) {
            return [];
        }

        foreach ($wowAccounts as $account) {
            if (!is_array($account)) {
                continue;
            }

            $accountCharacters = $account['characters'] ?? [];
            if (!is_array($accountCharacters)) {
                continue;
            }

            foreach ($accountCharacters as $character) {
                if (is_array($character)) {
                    /** @var array<string, mixed> $character */
                    $characters[] = $character;
                }
            }
        }

        return $characters;
    }

    /**
     * @param array<int, array<string, mixed>> $characters
     * @return array<int, ClassCount>
     */
    private function groupCharactersByClass(array $characters): array
    {
        /** @var array<string, ClassCount> $classCounts */
        $classCounts = [];

        foreach ($characters as $character) {
            $playableClass = $character['playable_class'] ?? null;

            if (!is_array($playableClass)) {
                continue;
            }

            $className = $playableClass['name'] ?? null;
            $classId = $playableClass['id'] ?? null;

            if (!is_string($className) || !is_int($classId)) {
                continue;
            }

            $characterSummary = CharacterSummary::fromApiData($character);

            if (!isset($classCounts[$className])) {
                $classCounts[$className] = new ClassCount(
                    name: $className,
                    id: $classId,
                    count: 0,
                    characters: []
                );
            }

            $classCounts[$className] = $classCounts[$className]->addCharacter($characterSummary);
        }

        $classCountsArray = array_values($classCounts);

        usort($classCountsArray, fn (ClassCount $a, ClassCount $b): int => $b->count <=> $a->count);

        return $classCountsArray;
    }
}
