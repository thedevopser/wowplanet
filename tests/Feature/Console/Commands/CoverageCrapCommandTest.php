<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->cloverPath = testTempPath('coverage-crap').'.xml';
});

afterEach(function (): void {
    @unlink($this->cloverPath);
});

function writeClover(string $path, string $methodLines): void
{
    file_put_contents($path, <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1">
  <project timestamp="1">
    <file name="/app/app/Http/Middleware/SecurityHeaders.php">
      <class name="App\Http\Middleware\SecurityHeaders" namespace="App\Http\Middleware"/>
{$methodLines}
    </file>
  </project>
</coverage>
XML);
}

test('it succeeds and says so when no method exceeds the threshold', function (): void {
    writeClover($this->cloverPath, '<line num="1" type="method" name="handle" complexity="4" crap="4" count="1"/>');

    $this->artisan('coverage:crap', ['--clover' => $this->cloverPath])
        ->expectsOutputToContain('No method above a CRAP of 30 among 1 measured.')
        ->assertExitCode(0);
});

test('it fails and names each method above the threshold, worst first', function (): void {
    writeClover($this->cloverPath, <<<'XML'
      <line num="1" type="method" name="handle" complexity="12" crap="56" count="0"/>
      <line num="2" type="stmt" count="0"/>
      <line num="10" type="method" name="policy" complexity="14" crap="210" count="0"/>
      <line num="11" type="stmt" count="0"/>
XML);

    $this->artisan('coverage:crap', ['--clover' => $this->cloverPath])
        ->expectsTable(
            ['CRAP', 'Complexity', 'Coverage', 'Method'],
            [
                ['210', '14', '0 %', \App\Http\Middleware\SecurityHeaders::class.'::policy'],
                ['56', '12', '0 %', \App\Http\Middleware\SecurityHeaders::class.'::handle'],
            ],
        )
        ->expectsOutputToContain('2 methods above a CRAP of 30 among 2 measured.')
        ->assertExitCode(1);
});

test('it names a single method above the threshold in the singular', function (): void {
    writeClover($this->cloverPath, '<line num="1" type="method" name="handle" complexity="12" crap="56" count="0"/>');

    $this->artisan('coverage:crap', ['--clover' => $this->cloverPath])
        ->expectsOutputToContain('1 method above a CRAP of 30 among 1 measured.')
        ->assertExitCode(1);
});

test('the threshold is an option of the command', function (): void {
    writeClover($this->cloverPath, '<line num="1" type="method" name="handle" complexity="12" crap="56" count="0"/>');

    $this->artisan('coverage:crap', ['--clover' => $this->cloverPath, '--threshold' => '60'])
        ->expectsOutputToContain('No method above a CRAP of 60 among 1 measured.')
        ->assertExitCode(0);
});

test('a threshold that is not a positive integer is refused with a message', function (string $threshold): void {
    writeClover($this->cloverPath, '<line num="1" type="method" name="handle" complexity="1" crap="1" count="1"/>');

    $this->artisan('coverage:crap', ['--clover' => $this->cloverPath, '--threshold' => $threshold])
        ->expectsOutputToContain('The threshold must be a positive integer')
        ->assertExitCode(1);
})->with(['abc', '0', '-5', '12.5']);

test('a missing clover fails with a message pointing to the coverage target', function (): void {
    $this->artisan('coverage:crap', ['--clover' => $this->cloverPath])
        ->expectsOutputToContain('No clover report at '.$this->cloverPath.'. Run make coverage first.')
        ->assertExitCode(1);
});

test('an unreadable clover fails with a message rather than a stack trace', function (): void {
    file_put_contents($this->cloverPath, 'not a clover');

    $this->artisan('coverage:crap', ['--clover' => $this->cloverPath])
        ->expectsOutputToContain("n'est pas un document XML lisible")
        ->assertExitCode(1);
});

test('the clover is read from the coverage directory by default', function (): void {
    $basePath = testTempPath('coverage-crap-base');
    mkdir($basePath.'/coverage', 0755, true);
    writeClover($basePath.'/coverage/clover.xml', '<line num="1" type="method" name="handle" complexity="1" crap="1" count="1"/>');
    $this->app->setBasePath($basePath);

    try {
        $this->artisan('coverage:crap')
            ->expectsOutputToContain('No method above a CRAP of 30 among 1 measured.')
            ->assertExitCode(0);
    } finally {
        removeDirectory($basePath);
    }
});
