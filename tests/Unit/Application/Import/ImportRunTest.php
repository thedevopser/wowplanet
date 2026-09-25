<?php

declare(strict_types=1);

use App\Application\Import\ImportRun;
use App\Application\Import\ImportRunState;
use App\Application\Import\ImportStage;
use App\Application\Import\ImportStep;
use App\Application\Import\ImportStepStatus;
use App\Application\Import\ImportWait;
use App\Application\Import\RowTally;

function runOf(ImportStage ...$stages): ImportRun
{
    return ImportRun::start('job-1', array_values($stages), startedAt: 1_000);
}

test('a run starts with one pending step per requested stage', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts);

    expect($importRun->steps)->toHaveCount(2)
        ->and($importRun->status())->toBe(ImportRunState::Pending)
        ->and($importRun->currentStage())->toBeNull();
});

test('a step replaces the one of the same stage and nothing else', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->finished(new RowTally(2, 0, 0), apiCalls: 5, durationMs: 100));

    expect($importRun->step(ImportStage::Mounts)->status)->toBe(ImportStepStatus::Completed)
        ->and($importRun->step(ImportStage::Quests)->status)->toBe(ImportStepStatus::Pending);
});

test('the running stage is the one the import is on', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 4));

    expect($importRun->currentStage())->toBe(ImportStage::Quests)
        ->and($importRun->status())->toBe(ImportRunState::Running);
});

test('a run is complete when every stage is, and failed when one failed', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 10))
        ->withStep(ImportStep::pending(ImportStage::Mounts)->skipped());

    expect($importRun->status())->toBe(ImportRunState::Completed);

    $broken = $importRun->withStep(ImportStep::pending(ImportStage::Mounts)->failed('mount index unavailable', 10));

    expect($broken->status())->toBe(ImportRunState::Failed);
});

test('a failed stage does not end the run while stages remain', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->failed('boom', 10));

    expect($importRun->status())->toBe(ImportRunState::Running);
});

test('the fraction done spreads evenly over the stages', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 10))
        ->withStep(ImportStep::pending(ImportStage::Mounts)->advanced(RowTally::none(), 1, 10, offset: 1, total: 2));

    expect($importRun->fraction())->toBe(0.75);
});

test('elapsed time counts from the start of the run', function (): void {
    expect(runOf(ImportStage::Quests)->elapsedSeconds(now: 1_252))->toBe(252);
});

test('the estimate extrapolates the time left from how long the done stages took', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts, ImportStage::Pets, ImportStage::Decor)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 20_000));

    expect($importRun->etaSeconds())->toBe(60);
});

test('a stage skipped by the build gate does not drag the estimate down', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts, ImportStage::Pets)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 20_000))
        ->withStep(ImportStep::pending(ImportStage::Mounts)->skipped());

    expect($importRun->etaSeconds())->toBe(20);
});

test('nothing done yet leaves the estimate unsaid rather than invented', function (): void {
    expect(runOf(ImportStage::Quests)->etaSeconds())->toBeNull();
});

test('a finished run has no time left', function (): void {
    $importRun = runOf(ImportStage::Quests)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 10));

    expect($importRun->etaSeconds())->toBe(0);
});

test('the run carries what it is waiting on, and drops it when it stops waiting', function (): void {
    $importRun = runOf(ImportStage::Quests)->waitingOn(ImportWait::hourlyBudget(240));

    expect($importRun->wait?->seconds)->toBe(240)
        ->and($importRun->waitingOn(null)->wait)->toBeNull();
});

test('the summary names the stage in progress and what the run is waiting on', function (): void {
    $summary = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 4))
        ->waitingOn(ImportWait::hourlyBudget(240))
        ->withBudgetUsed(12_340)
        ->summary(now: 1_060);

    expect($summary)->toContain('Quêtes')
        ->and($summary)->toContain('plafond horaire')
        ->and($summary)->toContain('12 340');
});

test('the summary reports each finished stage with its rows, calls and duration', function (): void {
    $summary = runOf(ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->finished(new RowTally(43, 2, 1), apiCalls: 5, durationMs: 1_100))
        ->summary(now: 1_060);

    expect($summary)->toContain('Montures')
        ->and($summary)->toContain('43 créées')
        ->and($summary)->toContain('2 mises à jour')
        ->and($summary)->toContain('1 supprimée')
        ->and($summary)->toContain('5 appels');
});

test('the summary reports a failed stage with its reason', function (): void {
    $summary = runOf(ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->failed('mount index unavailable', 10))
        ->summary(now: 1_060);

    expect($summary)->toContain('mount index unavailable');
});

test('the summary says nothing of rows for a stage that counts none', function (): void {
    $summary = runOf(ImportStage::Reference)
        ->withStep(ImportStep::pending(ImportStage::Reference)->finished(RowTally::none(), apiCalls: 0, durationMs: 7_900))
        ->summary(now: 1_060);

    expect($summary)->toContain('Socle de référence')
        ->and($summary)->not->toContain('0 créées');
});

test('a run survives a round trip through the tracking payload', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Appearances)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(new RowTally(1, 2, 3), 4, 5))
        ->waitingOn(ImportWait::batch(286))
        ->withBudgetUsed(12_340);

    expect(ImportRun::fromArray($importRun->toArray()))->toEqual($importRun);
});

test('asking a run for a stage it does not carry is refused', function (): void {
    runOf(ImportStage::Quests)->step(ImportStage::Mounts);
})->throws(InvalidArgumentException::class);

test('a run with no stage at all has nothing left to do', function (): void {
    expect(ImportRun::start('job-1', [], startedAt: 1_000)->fraction())->toBe(1.0);
});

test('durations are reported in seconds, minutes or hours as they grow', function (int $elapsed, string $expected): void {
    $importRun = runOf(ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->finished(RowTally::none(), 1, 10));

    expect($importRun->summary(now: 1_000 + $elapsed))->toContain($expected);
})->with([
    [30, '30 s'],
    [95, '1 min 35 s'],
    [3_661, '1 h 01 min'],
]);

test('a run held back by the quota before its first stage is running, not pending', function (): void {
    expect(runOf(ImportStage::Quests)->waitingOn(ImportWait::hourlyBudget(240))->status())
        ->toBe(ImportRunState::Running);
});

test('a paused run says so, whatever its stages were doing', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 4))
        ->paused(at: 1_200);

    expect($importRun->status())->toBe(ImportRunState::Paused)
        ->and($importRun->interruptedAt)->toBe(1_200);
});

test('a resumed run goes back to being driven by its stages', function (): void {
    $importRun = runOf(ImportStage::Quests)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 4))
        ->paused(at: 1_200)
        ->resumed();

    expect($importRun->status())->toBe(ImportRunState::Running)
        ->and($importRun->interruptedAt)->toBeNull();
});

test('a cancelled run is over even though stages remain to be done', function (): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 10))
        ->cancelled(at: 1_200);

    expect($importRun->status())->toBe(ImportRunState::Cancelled)
        ->and($importRun->status()->isTerminal())->toBeTrue();
});

test('a cancellation drops a pending pause, the run being over either way', function (): void {
    $importRun = runOf(ImportStage::Quests)->paused(at: 1_200)->cancelled(at: 1_260);

    expect($importRun->status())->toBe(ImportRunState::Cancelled)
        ->and($importRun->interruptedAt)->toBe(1_260);
});

test('the summary of a paused run says since when it has been waiting', function (): void {
    $summary = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 4))
        ->paused(at: 1_200)
        ->summary(now: 1_260);

    expect($summary)->toContain('Import en pause')
        ->and($summary)->toContain('1 min 00 s');
});

test('the summary of a cancelled run says interrupted, never finished', function (): void {
    $summary = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 10))
        ->cancelled(at: 1_200)
        ->summary(now: 1_260);

    expect($summary)->toContain('Import interrompu')
        ->and($summary)->toContain('1 étape sur 2')
        ->and($summary)->not->toContain('Import terminé');
});

test('the stage a cancellation caught mid-flight is reported as abandoned, not as done', function (): void {
    $summary = runOf(ImportStage::Appearances)
        ->withStep(ImportStep::pending(ImportStage::Appearances)->advanced(new RowTally(12, 0, 0), 30, 900, 4, 10))
        ->cancelled(at: 1_200)
        ->summary(now: 1_260);

    expect($summary)->toContain('abandonnée en cours');
});

test('an interrupted run survives a round trip through the tracking payload', function (): void {
    $importRun = runOf(ImportStage::Quests)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 4))
        ->paused(at: 1_200);

    expect(ImportRun::fromArray($importRun->toArray()))->toEqual($importRun);
});

test('a run cannot be built in a state no administrator could have asked for', function (): void {
    new ImportRun('job-1', 1_000, [], 0, null, ImportRunState::Completed, 1_200);
})->throws(InvalidArgumentException::class);

test('the summary of a running import reads line by line', function (): void {
    $summary = runOf(ImportStage::Reference, ImportStage::Quests, ImportStage::Mounts, ImportStage::Pets)
        ->withStep(ImportStep::pending(ImportStage::Reference)->finished(RowTally::none(), 0, 2_949))
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(new RowTally(1, 2, 0), 1_234, 59_950, 2, 3))
        ->waitingOn(ImportWait::hourlyBudget(120))
        ->withBudgetUsed(1)
        ->summary(now: 1_125);

    expect(explode(PHP_EOL, $summary))->toBe([
        'Import en cours — Quêtes (42 %).',
        'En attente : plafond horaire atteint, reprise dans 120 s.',
        'Budget horaire : 1 appel consommés · écoulé 2 min 05 s · reste ~9,0 s',
        '',
        '✓  Socle de référence — 0 appel · 2,9 s',
        '⏳ Quêtes — 67 % · 1 créée, 2 mises à jour, 0 supprimée · 1 234 appels · 59 s',
        '·  Montures — à faire',
        '·  Mascottes — à faire',
    ]);
});

test('the summary of a cancelled import reads line by line', function (): void {
    $summary = runOf(ImportStage::Quests, ImportStage::Mounts, ImportStage::Pets)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(new RowTally(1_500, 0, 1), 2, 9_999))
        ->withStep(ImportStep::pending(ImportStage::Mounts)->advanced(RowTally::none(), 1, 1_050, 1, 3))
        ->cancelled(at: 2_000)
        ->summary(now: 2_060);

    expect(explode(PHP_EOL, $summary))->toBe([
        'Import interrompu après 16 min 40 s — 1 étape sur 3 traitée.',
        'Budget horaire : 0 appel consommés · écoulé 17 min 40 s · reste ~20 s',
        '',
        '✓  Quêtes — 1 500 créées, 0 mise à jour, 1 supprimée · 2 appels · 10,0 s',
        '⊘  Montures — abandonnée en cours, à 33 % · 0 créée, 0 mise à jour, 0 supprimée · 1 appel · 1,1 s',
        '·  Mascottes — à faire',
    ]);
});

test('a finished import announces no time left, and an import with nothing measured none either', function (): void {
    $finished = runOf(ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->finished(RowTally::none(), 1, 10))
        ->summary(now: 1_060);
    $untouched = runOf(ImportStage::Mounts)->summary(now: 1_000);

    expect(explode(PHP_EOL, $finished)[1])->toBe('Budget horaire : 0 appel consommés · écoulé 1 min 00 s')
        ->and(explode(PHP_EOL, $untouched)[1])->toBe('Budget horaire : 0 appel consommés · écoulé 0,0 s');
});

test('the headline of a running import rounds its progress to the nearest percent', function (ImportRun $importRun, string $expected): void {
    expect(explode(PHP_EOL, $importRun->summary(now: 1_000))[0])->toBe($expected);
})->with([
    'three quarters' => [fn (): ImportRun => runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 10))
        ->withStep(ImportStep::pending(ImportStage::Mounts)->advanced(RowTally::none(), 1, 10, 1, 2)), 'Import en cours — Montures (75 %).'],
    'a third' => [fn (): ImportRun => runOf(ImportStage::Quests)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 3)), 'Import en cours — Quêtes (33 %).'],
    'held back before its first stage' => [fn (): ImportRun => runOf(ImportStage::Quests)
        ->waitingOn(ImportWait::hourlyBudget(240)), 'Import en cours — démarrage (0 %).'],
]);

test('the headline of a finished import gives its duration in the unit that fits', function (int $elapsed, string $expected): void {
    $summary = runOf(ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->finished(RowTally::none(), 1, 10))
        ->summary(now: 1_000 + $elapsed);

    expect(explode(PHP_EOL, $summary)[0])->toBe('Import terminé en '.$expected.'.');
})->with([
    [9, '9,0 s'],
    [10, '10 s'],
    [59, '59 s'],
    [60, '1 min 00 s'],
    [1_000, '16 min 40 s'],
    [3_599, '59 min 59 s'],
    [3_600, '1 h 00 min'],
    [7_140, '1 h 59 min'],
    [10_799, '2 h 59 min'],
]);

test('the headline of a failed or paused import gives its duration too', function (): void {
    $failed = runOf(ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Mounts)->failed('mount index unavailable', 10))
        ->summary(now: 2_000);
    $paused = runOf(ImportStage::Mounts)->paused(at: 1_000)->summary(now: 2_000);

    expect(explode(PHP_EOL, $failed)[0])->toBe('Import terminé en 16 min 40 s, avec des échecs.')
        ->and(explode(PHP_EOL, $paused)[0])->toBe('Import en pause depuis 16 min 40 s.');
});

test('the estimate averages the measured stages and rounds to the nearest second', function (array $durations, int $expected): void {
    $importRun = runOf(ImportStage::Quests, ImportStage::Mounts, ImportStage::Pets);
    $stages = [ImportStage::Quests, ImportStage::Mounts];
    foreach ($durations as $index => $duration) {
        $importRun = $importRun->withStep(ImportStep::pending($stages[$index])->finished(RowTally::none(), 1, $duration));
    }

    expect($importRun->etaSeconds())->toBe($expected);
})->with([
    'mean of two stages' => [[2_000, 4_000], 3],
    'a half rounds up' => [[2_500], 5],
    'under a half rounds down' => [[2_400, 2_400], 2],
    'just under one and a half' => [[1_499, 1_499], 1],
    'exactly a half second' => [[500, 500], 1],
    'a stage of a single millisecond still counts' => [[1, 1], 0],
]);

test('time since an interruption never goes negative, and is none without interruption', function (): void {
    $paused = runOf(ImportStage::Quests)->paused(at: 1_500);

    expect($paused->interruptedForSeconds(2_000))->toBe(500)
        ->and($paused->interruptedForSeconds(1_500))->toBe(0)
        ->and($paused->interruptedForSeconds(1_400))->toBe(0)
        ->and(runOf(ImportStage::Quests)->interruptedForSeconds(2_000))->toBeNull();
});

test('elapsed time never goes negative', function (): void {
    expect(runOf(ImportStage::Quests)->elapsedSeconds(1_000))->toBe(0)
        ->and(runOf(ImportStage::Quests)->elapsedSeconds(900))->toBe(0);
});

test('a run starts with no budget used', function (): void {
    expect(runOf(ImportStage::Quests)->budgetUsed)->toBe(0);
});

test('an interruption state that is not one is refused and named', function (): void {
    new ImportRun('job-1', 1_000, [], 0, null, ImportRunState::Completed, 1_200);
})->throws(InvalidArgumentException::class, 'An import run can only be interrupted as paused or cancelled: completed');

test('an interruption without its timestamp, or a timestamp without interruption, is refused', function (?ImportRunState $importRunState, ?int $interruptedAt): void {
    new ImportRun('job-1', 1_000, [], 0, null, $importRunState, $interruptedAt);
})->throws(InvalidArgumentException::class, 'An interruption and its timestamp go together.')->with([
    'paused without timestamp' => [ImportRunState::Paused, null],
    'timestamp without interruption' => [null, 1_200],
]);

test('asking for a stage outside the run names it', function (): void {
    runOf(ImportStage::Quests)->step(ImportStage::Mounts);
})->throws(InvalidArgumentException::class, 'Stage outside this run: mounts');

test('an interruption read back without its timestamp is dated at zero', function (): void {
    $payload = runOf(ImportStage::Quests)->toArray();
    $payload['interruption'] = 'paused';
    unset($payload['interrupted_at']);

    $importRun = ImportRun::fromArray($payload);

    expect($importRun->status())->toBe(ImportRunState::Paused)
        ->and($importRun->interruptedAt)->toBe(0);
});

test('a stage line rounds its own progress to the nearest percent', function (): void {
    $running = runOf(ImportStage::Quests)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 1, 3))
        ->summary(now: 1_000);
    $abandoned = runOf(ImportStage::Quests)
        ->withStep(ImportStep::pending(ImportStage::Quests)->advanced(RowTally::none(), 1, 10, 2, 3))
        ->cancelled(at: 1_000)
        ->summary(now: 1_000);

    expect(explode(PHP_EOL, $running)[3])->toStartWith('⏳ Quêtes — 33 %')
        ->and(explode(PHP_EOL, $abandoned)[3])->toStartWith('⊘  Quêtes — abandonnée en cours, à 67 %');
});

test('a long estimate is given in minutes', function (): void {
    $summary = runOf(ImportStage::Quests, ImportStage::Mounts)
        ->withStep(ImportStep::pending(ImportStage::Quests)->finished(RowTally::none(), 1, 1_000_000))
        ->summary(now: 1_000);

    expect(explode(PHP_EOL, $summary)[1])->toEndWith(' · reste ~16 min 40 s');
});
