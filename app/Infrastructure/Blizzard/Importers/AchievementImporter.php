<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Blizzard\AchievementCategorySweep;
use App\Infrastructure\Blizzard\AchievementPlacement;
use App\Infrastructure\Blizzard\AchievementTaxonomy;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\MediaSearchSweep;
use App\Infrastructure\Blizzard\MediaSearchTag;
use App\Infrastructure\Blizzard\Responses\AchievementDocument;
use App\Infrastructure\Blizzard\SearchIdWindow;
use App\Models\WowAchievement;

/**
 * Catalogue des hauts faits, entièrement tiré de l'API officielle.
 *
 * Trois sources, chacune pour ce qu'elle seule porte : la hiérarchie des catégories tranche
 * l'existence, le nom, la catégorie et l'extension ; le détail d'un haut fait donne ses
 * points et sa faction ; le balayage des media donne son icône.
 *
 * La hiérarchie est obligatoire, le reste ne l'est pas. Une catégorie manquante ferait
 * disparaître du lot des hauts faits que le balayage des lignes périmées supprimerait
 * ensuite : l'import s'interrompt. Un détail ou un media manquant, en revanche, laisse la
 * ligne existante avec les points, la faction et l'icône qu'elle avait.
 */
final readonly class AchievementImporter
{
    use ImportsFromBlizzardApi;

    private const DETAIL_ENDPOINT = 'data/wow/achievement/';

    /** Les détails sont petits, mais huit mille corps décodés gardés ensemble ne le sont pas. */
    private const DETAIL_BATCH = 1000;

    private const MEDIA_WINDOW_BATCH = 10;

    private const SAVE_CHUNK = 500;

    /** Au-delà, le rapport devient illisible : le compte total dit le reste. */
    private const REPORTED_CATEGORIES = 15;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
        private AchievementCategorySweep $achievementCategorySweep,
        private MediaSearchSweep $mediaSearchSweep,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    public function import(): void
    {
        $achievementTaxonomy = $this->achievementCategorySweep->fetchTaxonomy();
        if (! $achievementTaxonomy instanceof AchievementTaxonomy) {
            return;
        }

        $placements = $achievementTaxonomy->placements();
        if ($placements === []) {
            $this->info('  ERROR: the category hierarchy holds no achievement, aborting import (catalog left untouched).');

            return;
        }

        $rows = $this->buildRows(
            $placements,
            $this->fetchDetails($placements),
            $this->fetchIcons($placements),
            $this->existingRows(),
        );

        $this->saveRows($rows, array_map(static fn (AchievementPlacement $achievementPlacement): int => $achievementPlacement->id, $placements));
        $this->report($achievementTaxonomy);
    }

    /**
     * Points et faction, les deux seuls champs que la hiérarchie ne porte pas.
     *
     * @param  list<AchievementPlacement>  $placements
     * @return array<int, AchievementDocument>
     */
    private function fetchDetails(array $placements): array
    {
        $this->info(sprintf('Fetching the detail of %d achievements...', count($placements)));

        $documents = [];
        $missing = 0;

        foreach (array_chunk($placements, self::DETAIL_BATCH) as $chunk) {
            $endpoints = [];
            foreach ($chunk as $placement) {
                $endpoints[$placement->id] = self::DETAIL_ENDPOINT.$placement->id;
            }

            foreach ($this->fetchBatchAsync($endpoints) as $id => $decoded) {
                if ($decoded === null) {
                    $missing++;

                    continue;
                }

                $documents[(int) $id] = AchievementDocument::fromPayload($decoded);
            }
        }

        if ($missing > 0) {
            $this->info(sprintf('  %d details unavailable: those rows keep the points and faction they had.', $missing));
        }

        return $documents;
    }

    /**
     * Icônes par fenêtres d'identifiants : un media de haut fait porte l'identifiant du
     * haut fait, ce qui évite un appel unitaire par ligne.
     *
     * @param  list<AchievementPlacement>  $placements
     * @return array<int, string>
     */
    private function fetchIcons(array $placements): array
    {
        $windows = [];
        foreach ($placements as $placement) {
            $windows[SearchIdWindow::holding($placement->id)] = true;
        }

        $windows = array_keys($windows);
        sort($windows);

        $this->info(sprintf('Sweeping %d media windows for achievement icons...', count($windows)));

        $icons = [];
        foreach (array_chunk($windows, self::MEDIA_WINDOW_BATCH) as $chunk) {
            foreach ($this->mediaSearchSweep->sweep($chunk, MediaSearchTag::Achievement) as $id => $mediaSearchDocument) {
                if ($mediaSearchDocument->iconUrl !== null) {
                    $icons[$id] = $mediaSearchDocument->iconUrl;
                }
            }
        }

        return $icons;
    }

    /**
     * @return array<int, array{name_fr: string, expansion_id: int, category_name: string, icon_url: string|null, points: int, faction: string|null, is_active: bool}>
     */
    private function existingRows(): array
    {
        $rows = [];

        foreach (WowAchievement::query()->get(['id', 'name_fr', 'expansion_id', 'category_name', 'icon_url', 'points', 'faction', 'is_active']) as $achievement) {
            $rows[$achievement->id] = [
                'name_fr' => $achievement->name_fr,
                'expansion_id' => $achievement->expansion_id,
                'category_name' => $achievement->category_name,
                'icon_url' => $achievement->icon_url,
                'points' => $achievement->points,
                'faction' => $achievement->faction,
                'is_active' => $achievement->is_active,
            ];
        }

        return $rows;
    }

    /**
     * Une ligne identique à celle déjà en base n'est pas réécrite : sans cela, chaque
     * passe toucherait les huit mille lignes et le mode incrémental ne voudrait plus rien
     * dire.
     *
     * @param  list<AchievementPlacement>  $placements
     * @param  array<int, AchievementDocument>  $details
     * @param  array<int, string>  $icons
     * @param  array<int, array{name_fr: string, expansion_id: int, category_name: string, icon_url: string|null, points: int, faction: string|null, is_active: bool}>  $existing
     * @return list<array{id: int, name_fr: string, expansion_id: int, category_name: string, icon_url: string|null, points: int, faction: string|null, is_active: bool}>
     */
    private function buildRows(array $placements, array $details, array $icons, array $existing): array
    {
        $rows = [];
        $unchanged = 0;

        foreach ($placements as $placement) {
            $previous = $existing[$placement->id] ?? null;
            $achievementDocument = $details[$placement->id] ?? null;

            $row = [
                'name_fr' => $placement->name,
                'expansion_id' => $placement->expansionId,
                'category_name' => $placement->categoryName,
                'icon_url' => $icons[$placement->id] ?? $previous['icon_url'] ?? null,
                'points' => $achievementDocument instanceof AchievementDocument ? $achievementDocument->points : ($previous['points'] ?? 0),
                'faction' => $achievementDocument instanceof AchievementDocument ? $achievementDocument->faction : ($previous['faction'] ?? null),
                'is_active' => true,
            ];

            if ($row === $previous) {
                $unchanged++;

                continue;
            }

            $rows[] = ['id' => $placement->id] + $row;
        }

        if ($unchanged > 0) {
            $this->info(sprintf('  %d rows already up to date.', $unchanged));
        }

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, expansion_id: int, category_name: string, icon_url: string|null, points: int, faction: string|null, is_active: bool}>  $rows
     * @param  list<int>  $catalogIds
     */
    private function saveRows(array $rows, array $catalogIds): void
    {
        $this->info(sprintf('Saving %d achievements...', count($rows)));

        foreach (array_chunk($rows, self::SAVE_CHUNK) as $chunk) {
            WowAchievement::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'expansion_id', 'category_name', 'icon_url', 'points', 'faction', 'is_active'],
            );
        }

        $this->deleteRowsOutsideCatalog(WowAchievement::class, $catalogIds, 'achievements');
    }

    /**
     * Ce qui n'a pu être daté se lit dans le rapport, jamais dans un silence.
     */
    private function report(AchievementTaxonomy $achievementTaxonomy): void
    {
        $total = count($achievementTaxonomy->placements());
        $unclassified = count(array_filter(
            $achievementTaxonomy->placements(),
            static fn (AchievementPlacement $achievementPlacement): bool => $achievementPlacement->expansionId === ExpansionId::UNCLASSIFIED,
        ));

        $this->info(sprintf(
            'Achievement import complete: %d in catalog, %d dated by their category, %d unclassified.',
            $total,
            $total - $unclassified,
            $unclassified,
        ));

        $unrankedCategories = $achievementTaxonomy->unrankedCategories();
        if ($unrankedCategories !== []) {
            arsort($unrankedCategories);
            $this->info(sprintf('  %d categories nothing dates, largest first:', count($unrankedCategories)));

            foreach (array_slice($unrankedCategories, 0, self::REPORTED_CATEGORIES, true) as $label => $count) {
                $this->info(sprintf('    %s (%d)', $label, $count));
            }
        }

        foreach ($achievementTaxonomy->staleDatingSignals() as $signal) {
            $this->info(sprintf('  WARNING: a place-named category is going stale, dating by category no longer holds — %s', $signal));
        }
    }
}
