<?php

declare(strict_types=1);

use App\Application\Import\ImportLog;
use Illuminate\Support\Facades\Redis;

beforeEach(function (): void {
    $this->importLog = new ImportLog;
});

test('a fresh import has an empty journal and a cursor at the start', function (): void {
    $slice = $this->importLog->since('job-1', 0);

    expect($slice->lines)->toBe([])
        ->and($slice->cursor)->toBe(0);
});

test('it hands back the lines appended since the given cursor', function (): void {
    $this->importLog->append('job-1', 'Étape Quêtes démarrée.');
    $this->importLog->append('job-1', 'Étape Quêtes terminée.');

    $slice = $this->importLog->since('job-1', 0);

    expect($slice->lines)->toBe(['Étape Quêtes démarrée.', 'Étape Quêtes terminée.'])
        ->and($slice->cursor)->toBe(2);
});

test('it hands back nothing when the caller is already up to date', function (): void {
    $this->importLog->append('job-1', 'Étape Quêtes démarrée.');

    $slice = $this->importLog->since('job-1', 1);

    expect($slice->lines)->toBe([])
        ->and($slice->cursor)->toBe(1);
});

test('it hands back only what appeared since the last read', function (): void {
    $this->importLog->append('job-1', 'première');
    $this->importLog->append('job-1', 'deuxième');
    $this->importLog->append('job-1', 'troisième');

    $slice = $this->importLog->since('job-1', 2);

    expect($slice->lines)->toBe(['troisième'])
        ->and($slice->cursor)->toBe(3);
});

test('a cursor beyond the journal is treated as up to date rather than as an error', function (): void {
    $this->importLog->append('job-1', 'première');

    $slice = $this->importLog->since('job-1', 12);

    expect($slice->lines)->toBe([])
        ->and($slice->cursor)->toBe(1);
});

test('a negative cursor reads the journal from its first line', function (): void {
    $this->importLog->append('job-1', 'première');

    $slice = $this->importLog->since('job-1', -5);

    expect($slice->lines)->toBe(['première'])
        ->and($slice->cursor)->toBe(1);
});

test('two imports keep their journals apart', function (): void {
    $this->importLog->append('job-1', 'celle du premier');
    $this->importLog->append('job-2', 'celle du second');

    expect($this->importLog->since('job-1', 0)->lines)->toBe(['celle du premier'])
        ->and($this->importLog->since('job-2', 0)->lines)->toBe(['celle du second']);
});

test('a journal expires on its own rather than living forever', function (): void {
    $this->importLog->append('job-1', 'première');

    $ttl = Redis::connection('imports')->ttl('import_log:job-1');

    expect($ttl)->toBeGreaterThan(0)
        ->and($ttl)->toBeLessThanOrEqual(ImportLog::TTL_S);
});

test('appending again does not push the expiry further away', function (): void {
    $this->importLog->append('job-1', 'première');
    Redis::connection('imports')->expire('import_log:job-1', 60);

    $this->importLog->append('job-1', 'deuxième');

    expect(Redis::connection('imports')->ttl('import_log:job-1'))->toBeLessThanOrEqual(60);
});

test('it forgets a journal on demand', function (): void {
    $this->importLog->append('job-1', 'première');

    $this->importLog->forget('job-1');

    expect($this->importLog->since('job-1', 0)->lines)->toBe([]);
});

test('the journal survives a cache flush, which the import panel can trigger mid-run', function (): void {
    $this->importLog->append('job-1', 'première');

    Illuminate\Support\Facades\Cache::flush();

    expect($this->importLog->since('job-1', 0)->lines)->toBe(['première']);
});
