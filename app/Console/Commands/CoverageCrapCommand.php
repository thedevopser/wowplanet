<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Coverage\CloverReader;
use App\Infrastructure\Coverage\CrapReport;
use App\Infrastructure\Coverage\Exceptions\UnreadableCloverException;
use App\Infrastructure\Coverage\MethodRisk;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CoverageCrapCommand extends Command
{
    private const string DEFAULT_CLOVER = 'coverage/clover.xml';

    private const int DEFAULT_THRESHOLD = 30;

    protected $signature = 'coverage:crap
        {--clover= : Clover report to read, coverage/clover.xml by default}
        {--threshold=30 : CRAP above which a method is reported}';

    protected $description = 'List the methods whose CRAP exceeds a threshold, read from a PHPUnit clover report';

    public function handle(): int
    {
        try {
            $threshold = $this->threshold();
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        $cloverPath = $this->cloverPath();
        if (! is_file($cloverPath)) {
            $this->error(sprintf('No clover report at %s. Run make coverage first.', $cloverPath));

            return self::FAILURE;
        }

        try {
            $methods = CloverReader::methods((string) file_get_contents($cloverPath));
        } catch (UnreadableCloverException $unreadableCloverException) {
            $this->error($unreadableCloverException->getMessage());

            return self::FAILURE;
        }

        $crapReport = CrapReport::of($methods, $threshold);
        $this->render($crapReport);

        return $crapReport->exceedsThreshold() ? self::FAILURE : self::SUCCESS;
    }

    private function threshold(): int
    {
        $option = $this->option('threshold');
        $threshold = filter_var(
            is_string($option) ? $option : self::DEFAULT_THRESHOLD,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if (! is_int($threshold)) {
            throw new InvalidArgumentException(sprintf('The threshold must be a positive integer, got %s.', var_export($option, true)));
        }

        return $threshold;
    }

    private function cloverPath(): string
    {
        $option = $this->option('clover');

        return is_string($option) && $option !== '' ? $option : base_path(self::DEFAULT_CLOVER);
    }

    private function render(CrapReport $crapReport): void
    {
        if (! $crapReport->exceedsThreshold()) {
            $this->info(sprintf(
                'No method above a CRAP of %d among %d measured.',
                $crapReport->threshold,
                $crapReport->measured,
            ));

            return;
        }

        $this->table(
            ['CRAP', 'Complexity', 'Coverage', 'Method'],
            array_map(fn (MethodRisk $methodRisk): array => [
                (string) $methodRisk->crap,
                (string) $methodRisk->complexity,
                $methodRisk->coverage.' %',
                $methodRisk->className.'::'.$methodRisk->methodName,
            ], $crapReport->risky),
        );

        $riskyCount = count($crapReport->risky);

        $this->error(sprintf(
            '%d %s above a CRAP of %d among %d measured.',
            $riskyCount,
            $riskyCount === 1 ? 'method' : 'methods',
            $crapReport->threshold,
            $crapReport->measured,
        ));
    }
}
