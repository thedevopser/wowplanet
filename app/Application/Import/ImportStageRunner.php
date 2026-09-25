<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\DTOs\AppearanceImportProgress;
use App\Infrastructure\Blizzard\BlizzardBatchImporter;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Infrastructure\Mappings\FrozenAreaExpansionMap;
use App\Infrastructure\Reference\FactionReference;
use App\Infrastructure\Reference\ReferenceMaps;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyLoader;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Support\Facades\Artisan;

/**
 * Exécute une passe d'une étape d'import et rend ce qu'elle a fait.
 *
 * Une étape qui échoue est rapportée, jamais propagée : le rôle de ce lanceur est que
 * l'échec d'une entité n'emporte pas les suivantes. Ce qu'elle a écrit se mesure sur la
 * base, ce qu'elle a consommé sur le compteur de budget, sans second mécanisme.
 *
 * Seule la garde-robe rend la main avant d'avoir fini : son offset désigne la fenêtre
 * où reprendre, et la passe suivante repart de là.
 */
final readonly class ImportStageRunner
{
    private const REFERENCE_SYNC_COMMAND = 'app:wow-reference-sync';

    public function __construct(
        private BlizzardBatchImporter $blizzardBatchImporter,
        private ReferenceMaps $referenceMaps,
        private FactionReference $factionReference,
        private RowTallyCounter $rowTallyCounter,
        private HourlyBudgetGuard $hourlyBudgetGuard,
        private ImportControl $importControl,
        private CollectionTaxonomyLoader $collectionTaxonomyLoader,
        private ImportLog $importLog,
    ) {}

    public function run(string $jobId, ImportStep $importStep, bool $full, ?int $limit): ImportStageResult
    {
        $tables = $importStep->stage->tables();
        $startedAt = now();
        $rowsBefore = $this->rowTallyCounter->count($tables);
        $callsBefore = $this->hourlyBudgetGuard->totalConsumed();
        $startedMs = (int) (microtime(true) * 1000);

        try {
            $progress = $this->execute($jobId, $importStep, $full, $limit);
        } catch (\Throwable $throwable) {
            return new ImportStageResult(
                $importStep->failed($throwable->getMessage(), $this->elapsedMs($startedMs)),
                null,
            );
        }

        $tally = $this->rowTallyCounter->tally($tables, $startedAt, $rowsBefore);
        $calls = $this->hourlyBudgetGuard->totalConsumed() - $callsBefore;
        $durationMs = $this->elapsedMs($startedMs);

        if ($progress instanceof AppearanceImportProgress && ! $progress->done) {
            return new ImportStageResult(
                $importStep->advanced($tally, $calls, $durationMs, $progress->offset, $progress->total),
                $progress->secondsUntilBudget > 0 ? ImportWait::hourlyBudget($progress->secondsUntilBudget) : null,
            );
        }

        return new ImportStageResult($importStep->finished($tally, $calls, $durationMs), null);
    }

    /**
     * Rend l'avancement de la passe pour une étape reprenable, `null` pour les autres.
     */
    private function execute(string $jobId, ImportStep $importStep, bool $full, ?int $limit): ?AppearanceImportProgress
    {
        return match ($importStep->stage) {
            ImportStage::Reference => $this->syncReference(),
            ImportStage::Achievements => $this->nothingToResume($this->blizzardBatchImporter->importAchievements(...)),
            ImportStage::Quests => $this->importQuests(),
            ImportStage::Professions => $this->importProfessions(),
            ImportStage::Mounts => $this->importCollection($jobId, ImportStage::Mounts, CollectionEntity::Mount, $this->blizzardBatchImporter->importMounts(...)),
            ImportStage::Pets => $this->importCollection($jobId, ImportStage::Pets, CollectionEntity::Pet, $this->blizzardBatchImporter->importPets(...)),
            ImportStage::Decor => $this->importCollection($jobId, ImportStage::Decor, CollectionEntity::Decor, $this->blizzardBatchImporter->importDecor(...)),
            ImportStage::Appearances => $this->sweepAppearances($jobId, $importStep, $full, $limit),
        };
    }

    /**
     * L'importer range chaque entrée d'après la taxonomie en base : vide, elle ferait passer
     * tout le catalogue « en attente d'arbitrage », sans que rien ne le signale. La curation
     * versionnée y est donc fusionnée d'abord — additivement, un arbitrage fait en base
     * n'étant jamais réécrit —, et l'import est refusé si la collection reste sans taxonomie.
     *
     * @param  callable(): void  $import
     */
    private function importCollection(string $jobId, ImportStage $importStage, CollectionEntity $collectionEntity, callable $import): null
    {
        try {
            $inserted = $this->collectionTaxonomyLoader->load($collectionEntity)['inserted'];
        } catch (TaxonomySourceUnavailableException) {
            $inserted = 0;
        }

        if ($inserted > 0) {
            $this->importLog->note($jobId, sprintf(
                '%s — %d %s depuis l\'instantané versionné.',
                $importStage->label(),
                $inserted,
                $inserted > 1 ? 'entrées de taxonomie chargées' : 'entrée de taxonomie chargée',
            ));
        }

        throw_unless(
            WowCollectionTaxonomy::query()->where('entity', $collectionEntity)->exists(),
            \RuntimeException::class,
            sprintf(
                'Taxonomie des %s vide : import refusé pour ne pas effacer le rangement du catalogue. Rechargez l\'instantané depuis /admin/taxonomy.',
                mb_strtolower($importStage->label()),
            ),
        );

        return $this->nothingToResume($import);
    }

    private function syncReference(): null
    {
        $exitCode = Artisan::call(self::REFERENCE_SYNC_COMMAND, []);

        throw_if($exitCode !== 0, \RuntimeException::class, trim(Artisan::output()));

        return null;
    }

    private function importQuests(): null
    {
        $this->blizzardBatchImporter->importQuests(
            FrozenAreaExpansionMap::load(),
            $this->referenceMaps->questExpansions(),
            $this->referenceMaps->questFactions(),
            $this->referenceMaps->zoneFactions(),
        );

        $this->blizzardBatchImporter->tagMirrorQuestFactions($this->factionReference->factions());

        return null;
    }

    private function importProfessions(): null
    {
        $this->blizzardBatchImporter->importProfessions($this->referenceMaps->recipeFactions());
        $this->blizzardBatchImporter->tagMirrorRecipeFactions();

        return null;
    }

    /**
     * La garde-robe est la seule étape assez longue pour qu'attendre sa fin rende une
     * pause inutile : elle reçoit donc de quoi rendre la main à la fenêtre suivante, au
     * même titre que son time-box. Les autres s'arrêtent à leur propre fin, où leur
     * balayage de suppression a déjà eu lieu sur un traitement complet.
     */
    private function sweepAppearances(string $jobId, ImportStep $importStep, bool $full, ?int $limit): AppearanceImportProgress
    {
        /** @var int $timeBox */
        $timeBox = config('services.blizzard.import_chunk_timebox', 600);

        return $this->blizzardBatchImporter->importAppearanceChunk(
            $full,
            $importStep->offset,
            $timeBox,
            $limit,
            fn (): bool => $this->importControl->pending($jobId) instanceof ImportRequest,
        );
    }

    /**
     * @param  callable(): void  $import
     */
    private function nothingToResume(callable $import): null
    {
        $import();

        return null;
    }

    private function elapsedMs(int $startedMs): int
    {
        return max(0, (int) (microtime(true) * 1000) - $startedMs);
    }
}
