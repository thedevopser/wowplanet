<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Import\ImportHistory;
use App\Application\Import\ImportPipeline;
use App\Application\Import\ImportRun;
use App\Application\Import\ImportRunState;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportWaitReason;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Lance un import complet et le déroule jusqu'au bout, étape par étape.
 *
 * Même chaîne et même suivi que l'import lancé depuis le panneau d'administration :
 * seule la façon d'attendre diffère, la commande dormant là où le job relâche le worker.
 */
class WowDataImportCommand extends Command
{
    protected $signature = 'app:wow-data-import {--type=all} {--force : Reimport even when the WoW build has not changed} {--full : Re-fetch every appearance icon instead of only the missing ones} {--limit= : Cap the number of id windows swept per pass (smoke-test)}';

    protected $description = 'Import WoW data from the Blizzard API and the reference tables';

    public function handle(ImportPipeline $importPipeline): int
    {
        /** @var string $type */
        $type = $this->option('type');

        $stages = ImportStage::requested($type);

        if ($stages === []) {
            $this->error(sprintf('Unknown import type "%s".', $type));

            return self::FAILURE;
        }

        $importRun = $importPipeline->begin((string) Str::uuid(), $stages, (bool) $this->option('force'), ImportHistory::CONSOLE_TRIGGER);

        while (! $importPipeline->isDone($importRun)) {
            $importRun = $importPipeline->advance($importRun, (bool) $this->option('full'), $this->limit());
            $this->pauseIfNeeded($importRun);
        }

        $this->newLine();

        foreach (explode(PHP_EOL, $importRun->summary(now()->getTimestamp())) as $line) {
            $this->line($line);
        }

        return $importRun->status() === ImportRunState::Failed ? self::FAILURE : self::SUCCESS;
    }

    private function limit(): ?int
    {
        /** @var string|null $limit */
        $limit = $this->option('limit');

        return $limit === null ? null : max(1, (int) $limit);
    }

    /**
     * Le plafond horaire est la seule attente que la commande subit : les autres sont
     * déjà passées quand la passe rend la main.
     */
    private function pauseIfNeeded(ImportRun $importRun): void
    {
        if ($importRun->wait?->reason !== ImportWaitReason::HourlyBudget) {
            return;
        }

        $this->warn(sprintf('  %s', $importRun->wait->describe()));

        Sleep::sleep($importRun->wait->seconds);
    }
}
