<?php

declare(strict_types=1);

use App\Models\ImportHistoryEntry;
use App\Models\ImportHistoryStep;
use Inertia\Testing\AssertableInertia as Assert;

function historyEntry(string $jobId, string $startedAt, int $mounts): void
{
    ImportHistoryEntry::factory()->create(['job_id' => $jobId, 'started_at' => $startedAt]);
    ImportHistoryStep::factory()->create(['job_id' => $jobId, 'stage' => 'mounts', 'rows_after' => $mounts]);
}

test('the history page lists the imports, most recent first', function (): void {
    historyEntry('older', '2026-09-01 10:00:00', 100);
    historyEntry('newer', '2026-09-10 10:00:00', 50);

    $this->withSession(['is_admin' => true])
        ->get('/admin/history')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('AdminHistoryPage')
            ->where('history.entries.0.job_id', 'newer')
            ->where('history.entries.0.shrunk.0.stage', 'mounts')
            ->where('history.page', 1));
});

test('the history page serves the page it is asked for', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/history?page=3')
        ->assertInertia(fn (Assert $assert): Assert => $assert->where('history.page', 3));
});

test('a page that is not a positive number is refused', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin/history?page=0')
        ->assertSessionHasErrors('page');
});

test('the detail of an import carries its report against the previous one', function (): void {
    historyEntry('older', '2026-09-01 10:00:00', 100);
    historyEntry('newer', '2026-09-10 10:00:00', 50);

    $this->withSession(['is_admin' => true])
        ->get('/admin/history/newer')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('AdminHistoryEntryPage')
            ->where('entry.job_id', 'newer')
            ->where('entry.steps.0.previous_rows', 100)
            ->where('entry.steps.0.shrunk', true)
            ->where('entry.journal', null));
});

test('an unknown import answers as not found', function (): void {
    $this->withSession(['is_admin' => true])->get('/admin/history/nobody')->assertNotFound();
});

test('two imports are compared from the older to the newer', function (): void {
    historyEntry('older', '2026-09-01 10:00:00', 100);
    historyEntry('newer', '2026-09-10 10:00:00', 50);

    $this->withSession(['is_admin' => true])
        ->get('/admin/history/compare?first=newer&second=older')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component('AdminHistoryComparePage')
            ->where('comparison.older.job_id', 'older')
            ->where('comparison.stages.0.delta', -50)
            ->where('comparison.stages.0.shrunk', true));
});

test('a comparison needs two different imports', function (string $query): void {
    historyEntry('older', '2026-09-01 10:00:00', 100);

    $this->withSession(['is_admin' => true])
        ->get('/admin/history/compare?'.$query)
        ->assertSessionHasErrors();
})->with([
    'nothing named' => [''],
    'one import' => ['first=older'],
    'the same twice' => ['first=older&second=older'],
]);

test('a comparison with an unknown import answers as not found', function (): void {
    historyEntry('older', '2026-09-01 10:00:00', 100);

    $this->withSession(['is_admin' => true])->get('/admin/history/compare?first=older&second=nobody')->assertNotFound();
});
