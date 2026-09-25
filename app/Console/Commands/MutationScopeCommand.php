<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Mutation\Exceptions\GitCommandFailedException;
use App\Infrastructure\Mutation\MutationPerimeter;
use App\Infrastructure\Mutation\MutationScope;
use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MutationScopeCommand extends Command
{
    private const string PERIMETER_FILE = 'mutation-perimeter.txt';

    private const string TESTS_DIRECTORY = 'tests';

    protected $signature = 'mutation:scope
        {--base= : Git reference the branch is compared to; without it, the whole perimeter is listed}';

    protected $description = 'Plan the mutation run, one class per line followed by its dedicated tests: the whole perimeter, or the part a branch touches';

    public function handle(): int
    {
        $perimeterPath = base_path(self::PERIMETER_FILE);
        if (! is_file($perimeterPath)) {
            $this->error(sprintf('No mutation perimeter at %s.', $perimeterPath));

            return self::FAILURE;
        }

        $entries = MutationPerimeter::entries((string) file_get_contents($perimeterPath));

        $missing = array_values(array_filter($entries, fn (string $entry): bool => ! file_exists(base_path($entry))));
        if ($missing !== []) {
            $this->error(sprintf(
                'Declared in %s but missing, Pest would skip them silently: %s',
                self::PERIMETER_FILE,
                implode(', ', $missing),
            ));

            return self::FAILURE;
        }

        $base = $this->option('base');

        try {
            $files = is_string($base) && $base !== '' ? $this->touchedBy($base, $entries) : $this->phpFilesOf($entries);
        } catch (GitCommandFailedException $gitCommandFailedException) {
            $this->error($gitCommandFailedException->getMessage());

            return self::FAILURE;
        }

        sort($files);
        $testFiles = $this->phpFilesOf([self::TESTS_DIRECTORY]);

        foreach ($files as $file) {
            $this->line(implode(' ', [$file, ...MutationScope::dedicatedTests($file, $testFiles)]));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $entries
     * @return list<string>
     */
    private function touchedBy(string $base, array $entries): array
    {
        $mergeBase = trim($this->git(['git', 'merge-base', $base, 'HEAD']));

        $changedPaths = [
            ...$this->lines($this->git(['git', 'diff', '--name-only', $mergeBase])),
            ...$this->lines($this->git(['git', 'ls-files', '--others', '--exclude-standard'])),
        ];

        $baseEntries = MutationPerimeter::entries($this->perimeterAt($mergeBase));
        $newEntries = array_values(array_diff($entries, $baseEntries));

        return MutationScope::select(
            $this->phpFilesOf($entries),
            $this->phpFilesOf($newEntries),
            $changedPaths,
        );
    }

    /**
     * The perimeter did not exist before the branch introduced it: every entry is then new.
     */
    private function perimeterAt(string $revision): string
    {
        $processResult = Process::path(base_path())->run(['git', 'show', $revision.':'.self::PERIMETER_FILE]);

        return $processResult->successful() ? $processResult->output() : '';
    }

    /**
     * @param  list<string>  $command
     */
    private function git(array $command): string
    {
        $processResult = Process::path(base_path())->run($command);

        if ($processResult->failed()) {
            throw GitCommandFailedException::for($command, $processResult->errorOutput());
        }

        return $processResult->output();
    }

    /**
     * @return list<string>
     */
    private function lines(string $output): array
    {
        return array_values(array_filter(array_map(trim(...), explode("\n", $output)), fn (string $line): bool => $line !== ''));
    }

    /**
     * @param  list<string>  $entries
     * @return list<string>
     */
    private function phpFilesOf(array $entries): array
    {
        $files = [];

        foreach ($entries as $entry) {
            $absolutePath = base_path($entry);

            if (is_file($absolutePath)) {
                $files[] = $entry;

                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = substr($file->getPathname(), strlen(base_path()) + 1);
                }
            }
        }

        return $files;
    }
}
