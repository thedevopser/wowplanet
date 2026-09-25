<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Reference\Db2CsvProjector;
use App\Infrastructure\Reference\Exceptions\ReferenceSyncException;
use App\Infrastructure\Reference\Exceptions\TruncatedSourceException;
use App\Infrastructure\Reference\Exceptions\UnknownTableException;
use App\Infrastructure\Reference\ReferenceCatalog;
use App\Infrastructure\Reference\ReferenceLoader;
use App\Infrastructure\Reference\ReferenceStore;
use App\Infrastructure\Reference\ReferenceTable;
use App\Infrastructure\Reference\WagoClient;
use App\Models\WowReferenceDownload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Synchronise les correspondances que l'API Blizzard n'expose pas — extension d'une
 * quête, faction d'une zone, renom d'une réputation — depuis les tables DB2 de wago.
 *
 * Rien n'est écrit en base avant que tous les téléchargements ne soient acquis, et le
 * chargement lui-même tient dans une transaction : une source qui casse en cours de
 * route laisse le socle précédent intact, un socle à moitié chargé étant pire qu'un
 * socle périmé.
 */
class WowReferenceSyncCommand extends Command
{
    protected $signature = 'app:wow-reference-sync {--table= : Synchroniser une seule table DB2}';

    protected $description = 'Charge les tables de référence DB2 depuis wago.tools';

    /**
     * En deçà de cette part du dernier chargement, une source est tenue pour tronquée.
     */
    private const MIN_RETAINED_RATIO = 0.5;

    public function handle(
        WagoClient $wagoClient,
        ReferenceCatalog $referenceCatalog,
        ReferenceStore $referenceStore,
        Db2CsvProjector $db2CsvProjector,
        ReferenceLoader $referenceLoader,
    ): int {
        try {
            $tables = $this->tablesToSync($referenceCatalog);
            $build = $wagoClient->liveBuild();

            $this->info(sprintf('Socle de référence — build %s', $build));
            $this->newLine();

            $sizes = $this->downloadAll($wagoClient, $referenceStore, $tables, $build);

            /** @var list<array{source: string, loaded: int, previous: int|null}> $results */
            $results = DB::transaction(fn (): array => $this->loadAll(
                $referenceStore,
                $db2CsvProjector,
                $referenceLoader,
                $tables,
                $build,
                $sizes,
            ));
        } catch (ReferenceSyncException $referenceSyncException) {
            $this->error($referenceSyncException->getMessage());

            return self::FAILURE;
        }

        $this->report($results);

        return self::SUCCESS;
    }

    /**
     * @return list<ReferenceTable>
     */
    private function tablesToSync(ReferenceCatalog $referenceCatalog): array
    {
        /** @var string|null $requested */
        $requested = $this->option('table');

        if ($requested === null) {
            return $referenceCatalog->tables();
        }

        $table = $referenceCatalog->find($requested);

        if (! $table instanceof ReferenceTable) {
            throw UnknownTableException::named($requested, $referenceCatalog->sources());
        }

        return [$table];
    }

    /**
     * @param  list<ReferenceTable>  $tables
     * @return array<string, int>
     */
    private function downloadAll(WagoClient $wagoClient, ReferenceStore $referenceStore, array $tables, string $build): array
    {
        $sizes = [];

        foreach ($tables as $table) {
            $sizes[$table->source] = $referenceStore->put($table, $build, $wagoClient->fetch($table));
        }

        return $sizes;
    }

    /**
     * @param  list<ReferenceTable>  $tables
     * @param  array<string, int>  $sizes
     * @return list<array{source: string, loaded: int, previous: int|null}>
     */
    private function loadAll(
        ReferenceStore $referenceStore,
        Db2CsvProjector $db2CsvProjector,
        ReferenceLoader $referenceLoader,
        array $tables,
        string $build,
        array $sizes,
    ): array {
        $results = [];

        foreach ($tables as $table) {
            $previous = $this->previousRowCount($table);

            $loaded = $referenceLoader->replace(
                $table,
                $db2CsvProjector->project($table, $referenceStore->read($table, $build)),
            );

            $this->guardAgainstCollapse($table, $loaded, $previous);

            WowReferenceDownload::query()->updateOrCreate(
                ['filename' => $table->filename($build)],
                [
                    'source_table' => $table->source,
                    'build' => $build,
                    'bytes' => $sizes[$table->source],
                    'row_count' => $loaded,
                    'downloaded_at' => Date::now(),
                ],
            );

            $results[] = ['source' => $table->source, 'loaded' => $loaded, 'previous' => $previous];
        }

        return $results;
    }

    private function previousRowCount(ReferenceTable $referenceTable): ?int
    {
        $download = WowReferenceDownload::query()
            ->where('source_table', $referenceTable->source)
            ->latest('downloaded_at')
            ->first();

        return $download?->row_count;
    }

    private function guardAgainstCollapse(ReferenceTable $referenceTable, int $loaded, ?int $previous): void
    {
        if ($previous === null || $previous === 0) {
            return;
        }

        if ($loaded >= (int) ceil($previous * self::MIN_RETAINED_RATIO)) {
            return;
        }

        throw TruncatedSourceException::collapsed($referenceTable->source, $loaded, $previous);
    }

    /**
     * @param  list<array{source: string, loaded: int, previous: int|null}>  $results
     */
    private function report(array $results): void
    {
        $total = 0;

        foreach ($results as $result) {
            $total += $result['loaded'];

            $this->line(sprintf(
                '  %-20s %8d lignes   %s',
                $result['source'],
                $result['loaded'],
                $this->formatDelta($result['loaded'], $result['previous']),
            ));
        }

        $this->newLine();
        $this->info(sprintf('Socle chargé : %d table(s), %d lignes.', count($results), $total));
    }

    private function formatDelta(int $loaded, ?int $previous): string
    {
        if ($previous === null) {
            return '(nouveau)';
        }

        $delta = $loaded - $previous;

        if ($delta === 0) {
            return '(=)';
        }

        return sprintf('(%+d)', $delta);
    }
}
