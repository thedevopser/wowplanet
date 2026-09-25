<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Infrastructure\Blizzard\Responses\Profile\CharacterProfessionsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\CharacterReputationsResponse;
use App\Infrastructure\Blizzard\Responses\Profile\ProfessionTier;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @phpstan-import-type ExpansionCollection from CharacterProfileDTO
 * @phpstan-import-type ProfessionProgress from \App\Application\Services\Progress\ProfessionProgressAggregator
 *
 * @phpstan-type FactionStanding array{character_name: string, tier: int, raw: int, renown_level: int, standing_name: string, completed: bool}
 * @phpstan-type SkillPointOwner array{character_name: string, skill_points: int, max_skill_points: int}
 * @phpstan-type CrossCharacterResult array{completedQuestIds: list<int>, completedAchievementIds: list<int>, completedRecipeIds: list<int>, questOwners: array<int, string>, achievementOwners: array<int, string>, bestFactionStandings: array<int, FactionStanding>, recipeOwners: array<int, string>, skillPointOwners: array<int, array<int, SkillPointOwner>>}
 */
class CrossCharacterProgress
{
    // @pest-mutate-ignore
    private const int EXALTED_TIER = 7;

    /**
     * Un maximum absent ne doit pas passer pour un renom au cap, qui se lit `max === 0`.
     *
     * @pest-mutate-ignore
     */
    private const int UNKNOWN_MAX_STANDING = 1;

    /** @var array<int, string> quest_id => character_name */
    public array $completedQuestIds = [];

    /** @var array<int, string> achievement_id => character_name */
    public array $completedAchievementIds = [];

    /** @var array<int, true> */
    public array $completedRecipeIds = [];

    /** @var array<int, FactionStanding> */
    public array $bestFactionStandings = [];

    /** @var array<int, string> recipe_id => character_name */
    public array $recipeOwners = [];

    /** @var array<int, array<int, SkillPointOwner>> [profId][expId] */
    public array $skillPointOwners = [];

    /**
     * Reprend des données déjà calculées, telles que la colonne `cross_character_data.data` les rend.
     * L'ancien format, sans propriétaires, ne portait que les listes d'identifiants.
     */
    public static function fromStored(ResponsePayload $responsePayload): self
    {
        $crossCharacterProgress = new self;

        $crossCharacterProgress->completedQuestIds = self::ownersOrAnonymous($responsePayload, 'questOwners', 'completedQuestIds');
        $crossCharacterProgress->completedAchievementIds = self::ownersOrAnonymous($responsePayload, 'achievementOwners', 'completedAchievementIds');
        $crossCharacterProgress->completedRecipeIds = array_fill_keys($responsePayload->intList('completedRecipeIds'), true);
        $crossCharacterProgress->recipeOwners = $responsePayload->stringMap('recipeOwners');

        foreach ($responsePayload->objectMap('bestFactionStandings') as $factionId => $standing) {
            $crossCharacterProgress->bestFactionStandings[$factionId] = [
                'character_name' => $standing->requiredString('character_name'),
                'tier' => $standing->requiredInt('tier'),
                'raw' => $standing->requiredInt('raw'),
                'renown_level' => $standing->requiredInt('renown_level'),
                'standing_name' => $standing->requiredString('standing_name'),
                'completed' => $standing->optionalBool('completed') === true,
            ];
        }

        foreach ($responsePayload->objectMap('skillPointOwners') as $professionId => $expansions) {
            foreach ($expansions->entries() as $expansionId => $owner) {
                $crossCharacterProgress->skillPointOwners[$professionId][$expansionId] = [
                    'character_name' => $owner->requiredString('character_name'),
                    'skill_points' => $owner->requiredInt('skill_points'),
                    'max_skill_points' => $owner->requiredInt('max_skill_points'),
                ];
            }
        }

        return $crossCharacterProgress;
    }

    public function mergeCharacter(string $characterName, FetchedCharacterProgress $fetchedCharacterProgress): void
    {
        $this->mergeQuestIds($characterName, $fetchedCharacterProgress->questIds);
        $this->mergeAchievementIds($characterName, $fetchedCharacterProgress->achievementIds);
        $this->mergeReputations($characterName, $fetchedCharacterProgress->reputations);
        $this->mergeProfessions($characterName, $fetchedCharacterProgress->professions);
    }

    /**
     * @return CrossCharacterResult
     */
    public function buildResult(): array
    {
        return [
            'completedQuestIds' => array_keys($this->completedQuestIds),
            'completedAchievementIds' => array_keys($this->completedAchievementIds),
            'completedRecipeIds' => array_keys($this->completedRecipeIds),
            'questOwners' => $this->completedQuestIds,
            'achievementOwners' => $this->completedAchievementIds,
            'bestFactionStandings' => $this->bestFactionStandings,
            'recipeOwners' => $this->recipeOwners,
            'skillPointOwners' => $this->skillPointOwners,
        ];
    }

    /**
     * Merge from an existing CharacterProfileDTO (for piggyback).
     */
    public function mergeFromProfile(string $characterName, CharacterProfileDTO $characterProfileDTO): void
    {
        foreach ($characterProfileDTO->completedQuestIds as $id) {
            $this->completedQuestIds[$id] ??= $characterName;
        }

        foreach ($characterProfileDTO->completedAchievementIds as $id) {
            $this->completedAchievementIds[$id] ??= $characterName;
        }

        $this->mergeReputationsFromCollections($characterName, $characterProfileDTO->collections);
        $this->mergeProfessionsFromProfile($characterName, $characterProfileDTO->professions);
    }

    /**
     * @return array<int, string>
     */
    private static function ownersOrAnonymous(ResponsePayload $responsePayload, string $ownersKey, string $idsKey): array
    {
        $owners = $responsePayload->stringMap($ownersKey);

        if ($owners !== []) {
            return $owners;
        }

        return array_fill_keys($responsePayload->intList($idsKey), '');
    }

    /**
     * @param  list<int>  $questIds
     */
    private function mergeQuestIds(string $characterName, array $questIds): void
    {
        foreach ($questIds as $questId) {
            $this->completedQuestIds[$questId] ??= $characterName;
        }
    }

    /**
     * @param  list<int>  $achievementIds
     */
    private function mergeAchievementIds(string $characterName, array $achievementIds): void
    {
        foreach ($achievementIds as $achievementId) {
            $this->completedAchievementIds[$achievementId] ??= $characterName;
        }
    }

    /**
     * @param  FactionStanding  $current
     */
    private function isBetterStanding(int $renownLevel, int $raw, array $current): bool
    {
        // If either has renown, compare by renown_level (renown is account-wide)
        if ($renownLevel > 0 || $current['renown_level'] > 0) {
            return $renownLevel > $current['renown_level'];
        }

        return $raw > $current['raw'];
    }

    /**
     * @param  FactionStanding  $standing
     */
    private function keepBestStanding(int $factionId, array $standing): void
    {
        if (! isset($this->bestFactionStandings[$factionId]) || $this->isBetterStanding($standing['renown_level'], $standing['raw'], $this->bestFactionStandings[$factionId])) {
            $this->bestFactionStandings[$factionId] = $standing;
        }
    }

    private function mergeReputations(string $characterName, CharacterReputationsResponse $characterReputationsResponse): void
    {
        foreach ($characterReputationsResponse->standings as $reputationStanding) {
            $factionId = $reputationStanding->factionId ?? 0;
            if ($factionId === 0) {
                continue;
            }

            $tier = $reputationStanding->tier ?? 0;
            $renownLevel = $reputationStanding->renownLevel ?? 0;
            $maxStanding = $reputationStanding->max ?? self::UNKNOWN_MAX_STANDING;

            $this->keepBestStanding($factionId, [
                'character_name' => $characterName,
                'tier' => $tier,
                'raw' => $reputationStanding->raw ?? 0,
                'renown_level' => $renownLevel,
                'standing_name' => $reputationStanding->standingName ?? '',
                'completed' => $tier >= self::EXALTED_TIER || ($renownLevel > 0 && $maxStanding === 0),
            ]);
        }
    }

    private function mergeProfessions(string $characterName, CharacterProfessionsResponse $characterProfessionsResponse): void
    {
        foreach ($characterProfessionsResponse->professions as $characterProfession) {
            if ($characterProfession->professionId === 0) {
                continue;
            }

            foreach ($characterProfession->tiers as $tier) {
                $this->mergeProfessionTier($characterName, $characterProfession->professionId, $tier);
            }
        }
    }

    private function mergeProfessionTier(string $characterName, int $professionId, ProfessionTier $professionTier): void
    {
        $this->keepBestSkillPoints(
            $characterName,
            $professionId,
            $professionTier->id ?? 0,
            $professionTier->skillPoints ?? 0,
            $professionTier->maxSkillPoints ?? 0,
        );

        foreach ($professionTier->knownRecipeIds as $recipeId) {
            if ($recipeId === 0) {
                continue;
            }

            $this->completedRecipeIds[$recipeId] = true;
            $this->recipeOwners[$recipeId] ??= $characterName;
        }
    }

    private function keepBestSkillPoints(string $characterName, int $professionId, int $expansionId, int $skillPoints, int $maxSkillPoints): void
    {
        $current = $this->skillPointOwners[$professionId][$expansionId] ?? null;

        if ($skillPoints > 0 && ($current === null || $skillPoints > $current['skill_points'])) {
            $this->skillPointOwners[$professionId][$expansionId] = [
                'character_name' => $characterName,
                'skill_points' => $skillPoints,
                'max_skill_points' => max($maxSkillPoints, $current['max_skill_points'] ?? 0),
            ];
        }
    }

    /**
     * @param  array<int, ExpansionCollection>  $collections
     */
    private function mergeReputationsFromCollections(string $characterName, array $collections): void
    {
        foreach ($collections as $collection) {
            foreach ($collection['reputations']['factions'] as $faction) {
                // Skip unstarted factions — merging raw: 0 data would pollute best standings
                if ($faction['started'] === false) {
                    continue;
                }

                if ($faction['id'] === 0) {
                    continue;
                }

                $this->keepBestStanding($faction['id'], [
                    'character_name' => $characterName,
                    'tier' => $faction['tier'],
                    'raw' => $faction['raw'],
                    'renown_level' => $faction['renown_level'],
                    'standing_name' => $faction['standing_name'],
                    'completed' => $faction['completed'],
                ]);
            }
        }
    }

    /**
     * @param  list<ProfessionProgress>  $professions
     */
    private function mergeProfessionsFromProfile(string $characterName, array $professions): void
    {
        foreach ($professions as $profession) {
            $professionId = $profession['profession_id'];
            if ($professionId === 0) {
                continue;
            }

            foreach ($profession['expansions'] as $expansionId => $expansion) {
                $this->keepBestSkillPoints($characterName, $professionId, $expansionId, $expansion['skill_points'], $expansion['max_skill_points']);

                foreach ($expansion['categories'] as $category) {
                    foreach ($category['items'] as $item) {
                        if ($item['is_completed']) {
                            $this->completedRecipeIds[$item['id']] = true;
                            $this->recipeOwners[$item['id']] ??= $characterName;
                        }
                    }
                }
            }
        }
    }
}
