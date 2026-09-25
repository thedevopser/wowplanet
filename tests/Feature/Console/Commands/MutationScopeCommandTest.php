<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->scopeRoot = sys_get_temp_dir().'/pest-mutation-scope-'.uniqid();
    mkdir($this->scopeRoot, 0755, true);
    $this->app->setBasePath($this->scopeRoot);

    scopeFile($this->scopeRoot, 'app/Domain/Services/ScoreCalculator.php');
    scopeFile($this->scopeRoot, 'app/Domain/ValueObjects/ScoreInput.php');
    scopeFile($this->scopeRoot, 'app/Application/Import/VolumeShrink.php');
    scopeFile($this->scopeRoot, 'tests/Unit/Domain/Services/ScoreCalculatorTest.php');
    scopeFile($this->scopeRoot, 'tests/Unit/Domain/Services/ScoreCalculatorEdgeCasesTest.php');
    scopeFile($this->scopeRoot, 'tests/Unit/Application/Import/VolumeShrinkTest.php');
    scopeFile($this->scopeRoot, 'mutation-perimeter.txt', "# Perimeter\napp/Domain\napp/Application/Import/VolumeShrink.php\n");
});

afterEach(function (): void {
    removeDirectory($this->scopeRoot);
});

function scopeFile(string $root, string $relativePath, string $contents = "<?php\n"): void
{
    $fullPath = $root.'/'.$relativePath;
    @mkdir(dirname($fullPath), 0755, true);
    file_put_contents($fullPath, $contents);
}

function fakeGit(string $changed, string $untracked = '', ?string $basePerimeter = "app/Domain\napp/Application/Import/VolumeShrink.php\n"): void
{
    Process::fake([
        "'git' 'merge-base' *" => Process::result("abc123\n"),
        "'git' 'diff' *" => Process::result($changed),
        "'git' 'ls-files' *" => Process::result($untracked),
        "'git' 'show' *" => $basePerimeter === null
            ? Process::result('', "fatal: path 'mutation-perimeter.txt' does not exist in 'abc123'", 128)
            : Process::result($basePerimeter),
    ]);
}

test('without a base it plans every class of the perimeter with its dedicated tests', function (): void {
    $this->artisan('mutation:scope')
        ->expectsOutput('app/Application/Import/VolumeShrink.php tests/Unit/Application/Import/VolumeShrinkTest.php')
        ->expectsOutput('app/Domain/Services/ScoreCalculator.php tests/Unit/Domain/Services/ScoreCalculatorEdgeCasesTest.php tests/Unit/Domain/Services/ScoreCalculatorTest.php')
        ->expectsOutput('app/Domain/ValueObjects/ScoreInput.php')
        ->assertExitCode(0);
});

test('an entry that no longer exists fails and is named', function (): void {
    scopeFile($this->scopeRoot, 'mutation-perimeter.txt', "app/Domain\napp/Gone.php\n");

    $this->artisan('mutation:scope')
        ->expectsOutputToContain('app/Gone.php')
        ->assertExitCode(1);
});

test('a missing perimeter file fails with a message', function (): void {
    unlink($this->scopeRoot.'/mutation-perimeter.txt');

    $this->artisan('mutation:scope')
        ->expectsOutputToContain('No mutation perimeter at')
        ->assertExitCode(1);
});

test('with a base it lists the perimeter files the branch touches', function (): void {
    fakeGit("app/Domain/Services/ScoreCalculator.php\nREADME.md\n");

    $this->artisan('mutation:scope', ['--base' => 'origin/main'])
        ->expectsOutput('app/Domain/Services/ScoreCalculator.php tests/Unit/Domain/Services/ScoreCalculatorEdgeCasesTest.php tests/Unit/Domain/Services/ScoreCalculatorTest.php')
        ->assertExitCode(0);
});

test('a directory entry is expanded to the files it holds', function (): void {
    fakeGit("app/Domain/ValueObjects/ScoreInput.php\n");

    $this->artisan('mutation:scope', ['--base' => 'origin/main'])
        ->expectsOutput('app/Domain/ValueObjects/ScoreInput.php')
        ->assertExitCode(0);
});

test('a changed test selects the class it is named after', function (): void {
    fakeGit("tests/Unit/Application/Import/VolumeShrinkTest.php\n");

    $this->artisan('mutation:scope', ['--base' => 'origin/main'])
        ->expectsOutput('app/Application/Import/VolumeShrink.php tests/Unit/Application/Import/VolumeShrinkTest.php')
        ->assertExitCode(0);
});

test('an untracked test counts as changed', function (): void {
    fakeGit('', "tests/Unit/Domain/Services/ScoreCalculatorTest.php\n");

    $this->artisan('mutation:scope', ['--base' => 'origin/main'])
        ->expectsOutput('app/Domain/Services/ScoreCalculator.php tests/Unit/Domain/Services/ScoreCalculatorEdgeCasesTest.php tests/Unit/Domain/Services/ScoreCalculatorTest.php')
        ->assertExitCode(0);
});

test('an entry added to the perimeter on the branch is selected', function (): void {
    fakeGit("mutation-perimeter.txt\n", '', "app/Domain\n");

    $this->artisan('mutation:scope', ['--base' => 'origin/main'])
        ->expectsOutput('app/Application/Import/VolumeShrink.php tests/Unit/Application/Import/VolumeShrinkTest.php')
        ->assertExitCode(0);
});

test('a perimeter absent from the base makes every entry new', function (): void {
    fakeGit("mutation-perimeter.txt\n", '', null);

    $this->artisan('mutation:scope', ['--base' => 'origin/main'])
        ->expectsOutput('app/Application/Import/VolumeShrink.php tests/Unit/Application/Import/VolumeShrinkTest.php')
        ->expectsOutput('app/Domain/Services/ScoreCalculator.php tests/Unit/Domain/Services/ScoreCalculatorEdgeCasesTest.php tests/Unit/Domain/Services/ScoreCalculatorTest.php')
        ->expectsOutput('app/Domain/ValueObjects/ScoreInput.php')
        ->assertExitCode(0);
});

test('a branch that touches nothing of the perimeter prints nothing and succeeds', function (): void {
    fakeGit("README.md\n");

    $this->artisan('mutation:scope', ['--base' => 'origin/main'])
        ->doesntExpectOutputToContain('app/')
        ->assertExitCode(0);
});

test('an unknown base fails with the git message', function (): void {
    Process::fake([
        "'git' 'merge-base' *" => Process::result('', 'fatal: Not a valid object name origin/nope', 128),
    ]);

    $this->artisan('mutation:scope', ['--base' => 'origin/nope'])
        ->expectsOutputToContain('Not a valid object name origin/nope')
        ->assertExitCode(1);
});

test('git runs from the project root and the diff starts from the merge base', function (): void {
    fakeGit('');

    $this->artisan('mutation:scope', ['--base' => 'origin/main'])->assertExitCode(0);

    Process::assertRan(fn ($process): bool => $process->command === ['git', 'merge-base', 'origin/main', 'HEAD']
        && $process->path === $this->scopeRoot);
    Process::assertRan(fn ($process): bool => $process->command === ['git', 'diff', '--name-only', 'abc123']);
    Process::assertRan(fn ($process): bool => $process->command === ['git', 'show', 'abc123:mutation-perimeter.txt']);
});
