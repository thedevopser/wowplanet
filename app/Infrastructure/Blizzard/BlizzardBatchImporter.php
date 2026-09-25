<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Application\DTOs\AppearanceImportProgress;
use App\Infrastructure\Blizzard\Importers\AchievementImporter;
use App\Infrastructure\Blizzard\Importers\AppearanceImporter;
use App\Infrastructure\Blizzard\Importers\DecorImporter;
use App\Infrastructure\Blizzard\Importers\MountImporter;
use App\Infrastructure\Blizzard\Importers\PetImporter;
use App\Infrastructure\Blizzard\Importers\ProfessionImporter;
use App\Infrastructure\Blizzard\Importers\QuestImporter;

class BlizzardBatchImporter
{
    public function __construct(
        private readonly AchievementImporter $achievementImporter,
        private readonly QuestImporter $questImporter,
        private readonly MountImporter $mountImporter,
        private readonly PetImporter $petImporter,
        private readonly DecorImporter $decorImporter,
        private readonly ProfessionImporter $professionImporter,
        private readonly AppearanceImporter $appearanceImporter,
    ) {}

    public function importAchievements(): void
    {
        $this->achievementImporter->import();
    }

    /**
     * @param  array<int, int>  $areaExpansionMap
     * @param  array<int, int>  $questExpansionMap
     * @param  array<int, string>  $questFactionMap
     * @param  array<int, string>  $zoneFactionMap
     */
    public function importQuests(
        array $areaExpansionMap,
        array $questExpansionMap = [],
        array $questFactionMap = [],
        array $zoneFactionMap = [],
    ): void {
        $this->questImporter->import($areaExpansionMap, $questExpansionMap, $questFactionMap, $zoneFactionMap);
    }

    public function importMounts(): void
    {
        $this->mountImporter->import();
    }

    public function importPets(): void
    {
        $this->petImporter->import();
    }

    public function importDecor(): void
    {
        $this->decorImporter->import();
    }

    /**
     * Une passe bornée du balayage de la garde-robe, reprenable à l'offset qu'elle rend.
     *
     * @param  (\Closure(): bool)|null  $stopRequested  Rend la main à la fenêtre suivante quand il dit oui
     */
    public function importAppearanceChunk(bool $full, int $offset, int $timeBoxSeconds, ?int $limit = null, ?\Closure $stopRequested = null): AppearanceImportProgress
    {
        return $this->appearanceImporter->importChunk($full, $offset, $timeBoxSeconds, $limit, $stopRequested);
    }

    /**
     * @param  array<int, string>  $reputationFactionMap
     */
    public function tagMirrorQuestFactions(array $reputationFactionMap): void
    {
        $this->questImporter->tagMirrorFactions($reputationFactionMap);
    }

    public function tagMirrorRecipeFactions(): void
    {
        $this->professionImporter->tagMirrorRecipeFactions();
    }

    /**
     * @param  array<int, string>  $recipeFactionMap
     */
    public function importProfessions(array $recipeFactionMap = []): void
    {
        $this->professionImporter->import($recipeFactionMap);
    }
}
