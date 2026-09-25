<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\ImportHistoryEntry;
use App\Models\ImportHistoryStep;
use Illuminate\Support\Facades\DB;

/**
 * Archive chaque import de la chaîne : une entrée à son lancement, son rapport à sa
 * clôture.
 *
 * L'entrée est ouverte dès le lancement, et non à la fin : un import dont le worker meurt
 * en route n'atteint jamais sa clôture, et il doit quand même figurer dans l'historique,
 * avec l'état réel où il a été laissé.
 */
final readonly class ImportHistory
{
    /** Un cycle complet de patchs : assez pour retrouver quand une anomalie est apparue. */
    public const RETENTION_MONTHS = 12;

    public const string CONSOLE_TRIGGER = 'console';

    private const string FORCED = 'forced';

    private const string INCREMENTAL = 'incremental';

    public function __construct(private ImportInventory $importInventory) {}

    /**
     * Faute de planificateur dans le projet, la purge des rapports trop anciens se fait
     * ici, à l'archivage d'un nouvel import : l'historique ne grossit que par cette porte.
     */
    public function open(ImportRun $importRun, bool $force, string $trigger): void
    {
        DB::transaction(function () use ($importRun, $force, $trigger): void {
            ImportHistoryEntry::query()->where('started_at', '<', now()->subMonths(self::RETENTION_MONTHS))->delete();

            ImportHistoryEntry::query()->create([
                'job_id' => $importRun->jobId,
                'trigger' => $trigger,
                'mode' => $force ? self::FORCED : self::INCREMENTAL,
                'status' => $importRun->status() === ImportRunState::Pending ? ImportRunState::Running->value : $importRun->status()->value,
                'started_at' => \Illuminate\Support\Facades\Date::createFromTimestamp($importRun->startedAt),
            ]);

            foreach ($importRun->steps as $importStep) {
                ImportHistoryStep::query()->create([
                    'job_id' => $importRun->jobId,
                    'stage' => $importStep->stage->value,
                    'status' => $importStep->status->value,
                ]);
            }
        });
    }

    /**
     * Les volumes sont relevés à la clôture, sur les tables elles-mêmes : c'est ce que le
     * catalogue pèse réellement après l'import, que l'étape ait tourné, été sautée ou
     * échoué, et c'est ce qu'on compare d'un import au suivant.
     */
    public function close(ImportRun $importRun, int $now): void
    {
        DB::transaction(function () use ($importRun, $now): void {
            $updated = ImportHistoryEntry::query()->whereKey($importRun->jobId)->update([
                'status' => $importRun->status()->value,
                'finished_at' => \Illuminate\Support\Facades\Date::createFromTimestamp($now),
                'budget_used' => $importRun->budgetUsed,
            ]);

            if ($updated === 0) {
                return;
            }

            foreach ($importRun->steps as $importStep) {
                ImportHistoryStep::query()
                    ->where('job_id', $importRun->jobId)
                    ->where('stage', $importStep->stage->value)
                    ->update([
                        'status' => $importStep->status->value,
                        'created' => $importStep->rows->created,
                        'updated' => $importStep->rows->updated,
                        'deleted' => $importStep->rows->deleted,
                        'api_calls' => $importStep->apiCalls,
                        'duration_ms' => $importStep->durationMs,
                        'rows_after' => $importStep->stage->tables() === [] ? null : $this->importInventory->rowsOf($importStep->stage),
                        'error' => $importStep->error,
                    ]);
            }
        });
    }
}
