<?php

declare(strict_types=1);

use App\Application\Import\ImportLog;
use App\Application\Import\ImportLogOutput;

function journalOf(string $jobId): string
{
    return implode(PHP_EOL, resolve(ImportLog::class)->since($jobId, 0)->lines);
}

test('each line a command writes lands in the journal, timestamped', function (): void {
    $output = new ImportLogOutput(resolve(ImportLog::class), 'job-1');

    $output->writeln('Socle de référence — build 12.1.0.69875');
    $output->writeln('  Faction   868 lignes');

    expect(resolve(ImportLog::class)->since('job-1', 0)->lines)->toHaveCount(2)
        ->and(journalOf('job-1'))->toContain('Faction   868 lignes')
        ->and(journalOf('job-1'))->toMatch('/\[\d{2}:\d{2}:\d{2}\]/');
});

test('a blank line the command uses to breathe is not journalled', function (): void {
    $output = new ImportLogOutput(resolve(ImportLog::class), 'job-1');

    $output->writeln('une ligne');
    $output->writeln('');
    $output->writeln('   ');

    expect(resolve(ImportLog::class)->since('job-1', 0)->lines)->toHaveCount(1);
});

test('a line written in several pieces is journalled once, when it ends', function (): void {
    $output = new ImportLogOutput(resolve(ImportLog::class), 'job-1');

    $output->write('Faction');
    $output->write(' — 868 lignes');

    expect(resolve(ImportLog::class)->since('job-1', 0)->lines)->toBeEmpty();

    $output->writeln('');

    expect(journalOf('job-1'))->toContain('Faction — 868 lignes');
});

test('console formatting is stripped, the journal being read as plain text', function (): void {
    $output = new ImportLogOutput(resolve(ImportLog::class), 'job-1');

    $output->writeln('<info>Socle chargé</info>');

    expect(journalOf('job-1'))->toContain('Socle chargé')
        ->and(journalOf('job-1'))->not->toContain('<info>');
});

test('two commands followed at once do not mix their journals', function (): void {
    (new ImportLogOutput(resolve(ImportLog::class), 'job-1'))->writeln('celle de job-1');
    (new ImportLogOutput(resolve(ImportLog::class), 'job-2'))->writeln('celle de job-2');

    expect(journalOf('job-1'))->toContain('celle de job-1')
        ->and(journalOf('job-1'))->not->toContain('celle de job-2');
});
