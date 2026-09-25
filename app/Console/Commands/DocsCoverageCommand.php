<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Documentation\CoverageReport;
use App\Infrastructure\Documentation\DocumentationCoverage;
use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class DocsCoverageCommand extends Command
{
    protected $signature = 'docs:coverage';

    protected $description = 'Report the share of the code named in the documentation pages';

    public function handle(): int
    {
        /** @var list<string> $sourcePaths */
        $sourcePaths = config('documentation.source_paths', []);
        /** @var string $pagesPath */
        $pagesPath = config('documentation.pages_path', '');
        /** @var array<string, string> $exclusions */
        $exclusions = config('documentation.exclude', []);
        /** @var int $ceiling */
        $ceiling = config('documentation.max_undocumented', 0);

        $report = DocumentationCoverage::report(
            $this->classNamesIn($sourcePaths),
            $this->pagesIn($pagesPath),
            array_keys($exclusions),
        );

        $this->render($report, $ceiling);

        return count($report->missing) > $ceiling ? self::FAILURE : self::SUCCESS;
    }

    private function render(CoverageReport $coverageReport, int $ceiling): void
    {
        $undocumented = count($coverageReport->missing);

        $this->line(sprintf(
            'Documentation coverage: %s %% — %d/%d classes, %d excluded.',
            $coverageReport->percentage(),
            $coverageReport->documented,
            $coverageReport->total,
            $coverageReport->excluded,
        ));

        if ($undocumented === 0) {
            $this->info('Every class of the perimeter is documented.');

            return;
        }

        $this->newLine();
        foreach ($coverageReport->missingByLayer() as $layer => $classNames) {
            $this->line(sprintf('  %s (%d)', $layer, count($classNames)));
            foreach ($classNames as $className) {
                $this->line('    '.$className);
            }
        }

        $this->newLine();

        if ($undocumented > $ceiling) {
            $this->error(sprintf(
                '%d classes documented nowhere, ceiling is %d. Document them, or lower nothing.',
                $undocumented,
                $ceiling,
            ));

            return;
        }

        $this->comment(sprintf('%d classes documented nowhere, under the ceiling of %d.', $undocumented, $ceiling));
    }

    /**
     * @param  list<string>  $sourcePaths
     * @return list<string>
     */
    private function classNamesIn(array $sourcePaths): array
    {
        $classNames = [];

        foreach ($sourcePaths as $sourcePath) {
            foreach ($this->phpFilesIn($sourcePath) as $file) {
                $relativePath = substr($file->getPathname(), strlen($sourcePath) + 1, -4);
                $classNames[] = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
            }
        }

        sort($classNames);

        return $classNames;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function pagesIn(string $directory): string
    {
        if (! is_dir($directory)) {
            return '';
        }

        $contents = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'md') {
                $contents[] = (string) file_get_contents($file->getPathname());
            }
        }

        return implode("\n", $contents);
    }
}
