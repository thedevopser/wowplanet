<?php

declare(strict_types=1);

use App\Application\Import\CurrentImport;
use App\Application\Import\ImportHistoryReader;
use App\Application\Import\ImportLog;
use App\Models\ImportHistoryEntry;
use App\Models\ImportHistoryStep;

function historyReader(): ImportHistoryReader
{
    return resolve(ImportHistoryReader::class);
}

/**
 * @param  array<string, int|null>  $volumes  Étape => lignes à la clôture
 * @param  array<string, string|int|null>  $attributes
 */
function archivedImport(string $jobId, string $startedAt, array $volumes, array $attributes = []): ImportHistoryEntry
{
    $importHistoryEntry = ImportHistoryEntry::factory()->create([
        'job_id' => $jobId,
        'started_at' => $startedAt,
        'finished_at' => $startedAt,
        ...$attributes,
    ]);

    foreach ($volumes as $stage => $rows) {
        ImportHistoryStep::query()->create([
            'job_id' => $jobId,
            'stage' => $stage,
            'status' => 'completed',
            'created' => 1,
            'api_calls' => 10,
            'duration_ms' => 2_000,
            'rows_after' => $rows,
        ]);
    }

    return $importHistoryEntry;
}

test('the history lists imports from the most recent, with their stages', function (): void {
    archivedImport('older', '2026-09-01 10:00:00', ['mounts' => 100]);
    archivedImport('newer', '2026-09-10 10:00:00', ['mounts' => 100, 'pets' => 50], ['trigger' => '12345', 'mode' => 'forced']);

    $page = historyReader()->page(1);

    expect(array_column($page['entries'], 'job_id'))->toBe(['newer', 'older'])
        ->and($page['entries'][0])->toMatchArray([
            'trigger' => '12345',
            'mode' => 'forced',
            'status' => 'completed',
            'stages' => [['stage' => 'mounts', 'label' => 'Montures'], ['stage' => 'pets', 'label' => 'Mascottes']],
            'shrunk' => [],
        ])
        ->and($page['entries'][0]['started_at'])->toStartWith('2026-09-10T10:00:00')
        ->and($page['page'])->toBe(1)
        ->and($page['pages'])->toBe(1);
});

test('an entry whose volume fell against the previous import of the same stage is flagged', function (): void {
    archivedImport('before', '2026-09-01 10:00:00', ['mounts' => 1_000, 'pets' => 500]);
    archivedImport('between', '2026-09-05 10:00:00', ['pets' => 500]);
    archivedImport('after', '2026-09-10 10:00:00', ['mounts' => 600, 'pets' => 495]);

    $entries = collect(historyReader()->page(1)['entries'])->keyBy('job_id');

    expect($entries['after']['shrunk'])->toBe([['stage' => 'mounts', 'label' => 'Montures']])
        ->and($entries['between']['shrunk'])->toBe([])
        ->and($entries['before']['shrunk'])->toBe([]);
});

test('an import left running while another one holds the panel is shown as abandoned', function (): void {
    archivedImport('dead', '2026-09-01 10:00:00', ['mounts' => null], ['status' => 'running', 'finished_at' => null]);
    archivedImport('alive', '2026-09-02 10:00:00', ['mounts' => null], ['status' => 'running', 'finished_at' => null]);
    resolve(CurrentImport::class)->mark('alive', now()->getTimestamp());

    $entries = collect(historyReader()->page(1)['entries'])->keyBy('job_id');

    expect($entries['dead']['status'])->toBe('abandoned')
        ->and($entries['alive']['status'])->toBe('running');
});

test('the history is served one page at a time', function (): void {
    foreach (range(1, ImportHistoryReader::PER_PAGE + 1) as $index) {
        archivedImport('job-'.$index, now()->subMinutes($index)->toDateTimeString(), ['mounts' => 10]);
    }

    $second = historyReader()->page(2);

    expect($second['entries'])->toHaveCount(1)
        ->and($second['entries'][0]['job_id'])->toBe('job-'.(ImportHistoryReader::PER_PAGE + 1))
        ->and($second['pages'])->toBe(2);
});

test('a page below one cannot be asked for', function (): void {
    historyReader()->page(0);
})->throws(InvalidArgumentException::class);

test('the detail of an import sets each stage against the previous import of that stage', function (): void {
    archivedImport('before', '2026-09-01 10:00:00', ['mounts' => 1_000]);
    archivedImport('after', '2026-09-10 10:00:00', ['mounts' => 600, 'pets' => 50]);

    $detail = historyReader()->entry('after');

    expect($detail['job_id'])->toBe('after')
        ->and($detail['steps'][0])->toMatchArray([
            'stage' => 'mounts',
            'label' => 'Montures',
            'rows_after' => 600,
            'previous_rows' => 1_000,
            'previous_job_id' => 'before',
            'delta' => -400,
            'shrunk' => true,
            'api_calls' => 10,
        ])
        ->and($detail['steps'][1])->toMatchArray(['stage' => 'pets', 'previous_rows' => null, 'delta' => null, 'shrunk' => false]);
});

test('the detail carries the journal while it lives, and says when it has expired', function (): void {
    archivedImport('fresh', '2026-09-10 10:00:00', ['mounts' => 10]);
    archivedImport('stale', '2026-09-01 10:00:00', ['mounts' => 10]);
    resolve(ImportLog::class)->note('fresh', 'Import démarré — 1 étape : Montures.');

    expect(historyReader()->entry('fresh')['journal'])->toHaveCount(1)
        ->and(historyReader()->entry('stale')['journal'])->toBeNull();
});

test('an unknown import has no detail', function (): void {
    historyReader()->entry('nobody');
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('two imports compare stage by stage on what they share, from the older to the newer', function (): void {
    archivedImport('before', '2026-09-01 10:00:00', ['mounts' => 1_000, 'quests' => 200]);
    archivedImport('after', '2026-09-10 10:00:00', ['mounts' => 1_100, 'pets' => 50]);

    $comparison = historyReader()->compare('after', 'before');

    expect($comparison['older']['job_id'])->toBe('before')
        ->and($comparison['newer']['job_id'])->toBe('after')
        ->and($comparison['stages'])->toHaveCount(1)
        ->and($comparison['stages'][0])->toMatchArray([
            'stage' => 'mounts',
            'older_rows' => 1_000,
            'newer_rows' => 1_100,
            'delta' => 100,
            'shrunk' => false,
        ]);
});

test('a comparison flags a stage whose volume fell', function (): void {
    archivedImport('before', '2026-09-01 10:00:00', ['mounts' => 1_000]);
    archivedImport('after', '2026-09-10 10:00:00', ['mounts' => 10]);

    expect(historyReader()->compare('before', 'after')['stages'][0]['shrunk'])->toBeTrue();
});

test('an import cannot be compared with itself', function (): void {
    archivedImport('same', '2026-09-01 10:00:00', ['mounts' => 10]);

    historyReader()->compare('same', 'same');
})->throws(InvalidArgumentException::class);
