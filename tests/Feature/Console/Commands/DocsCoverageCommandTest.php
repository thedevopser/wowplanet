<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->docsTmpDir = testTempPath('docs-coverage');
    mkdir($this->docsTmpDir.'/app', 0755, true);
    mkdir($this->docsTmpDir.'/documentation', 0755, true);

    config([
        'documentation.source_paths' => [$this->docsTmpDir.'/app'],
        'documentation.pages_path' => $this->docsTmpDir.'/documentation',
        'documentation.exclude' => [],
        'documentation.max_undocumented' => 0,
    ]);
});

afterEach(function (): void {
    removeDirectory($this->docsTmpDir);
});

function docsCoverageSource(string $root, string $relativePath): void
{
    $fullPath = $root.'/app/'.$relativePath;
    @mkdir(dirname($fullPath), 0755, true);
    file_put_contents($fullPath, "<?php\n");
}

function docsCoveragePage(string $root, string $relativePath, string $contents): void
{
    $fullPath = $root.'/documentation/'.$relativePath;
    @mkdir(dirname($fullPath), 0755, true);
    file_put_contents($fullPath, $contents);
}

test('it succeeds when every class of the perimeter is documented', function (): void {
    docsCoverageSource($this->docsTmpDir, 'Domain/Services/ScoreCalculator.php');
    docsCoveragePage($this->docsTmpDir, 'index.md', 'La formule vit dans `ScoreCalculator`.');

    $this->artisan('docs:coverage')->assertExitCode(0);
});

test('it fails when a class is documented nowhere', function (): void {
    docsCoverageSource($this->docsTmpDir, 'Domain/Services/ScoreCalculator.php');
    docsCoveragePage($this->docsTmpDir, 'index.md', 'Cette page ne parle de rien.');

    $this->artisan('docs:coverage')->assertExitCode(1);
});

test('it reads the pages nested in the documentation tree, not only its root', function (): void {
    docsCoverageSource($this->docsTmpDir, 'Domain/Services/ScoreCalculator.php');
    docsCoveragePage($this->docsTmpDir, 'index.md', 'Rien ici.');
    docsCoveragePage($this->docsTmpDir, 'backend/01-domain.md', 'La formule vit dans `ScoreCalculator`.');

    $this->artisan('docs:coverage')->assertExitCode(0);
});

test('it names the undocumented classes, grouped by layer', function (): void {
    docsCoverageSource($this->docsTmpDir, 'Domain/Services/ScoreCalculator.php');
    docsCoverageSource($this->docsTmpDir, 'Http/Controllers/PvpController.php');
    docsCoveragePage($this->docsTmpDir, 'index.md', 'Rien ici.');

    // Chaque attente est vérifiée ligne à ligne, et Mockery retient la première qui
    // correspond : des chaînes qui se recouvrent se voleraient mutuellement leur ligne.
    $this->artisan('docs:coverage')
        ->expectsOutputToContain('Domain (1)')
        ->expectsOutputToContain(\App\Domain\Services\ScoreCalculator::class)
        ->expectsOutputToContain('Http (1)')
        ->expectsOutputToContain(\App\Http\Controllers\PvpController::class)
        ->assertExitCode(1);
});

test('it tolerates undocumented classes up to the declared ceiling', function (): void {
    docsCoverageSource($this->docsTmpDir, 'Domain/Services/ScoreCalculator.php');
    docsCoveragePage($this->docsTmpDir, 'index.md', 'Rien ici.');
    config(['documentation.max_undocumented' => 1]);

    $this->artisan('docs:coverage')->assertExitCode(0);
});

test('it fails as soon as one class more than the ceiling is undocumented', function (): void {
    docsCoverageSource($this->docsTmpDir, 'Domain/Services/ScoreCalculator.php');
    docsCoverageSource($this->docsTmpDir, 'Domain/Services/PvpBracketClassifier.php');
    docsCoveragePage($this->docsTmpDir, 'index.md', 'Rien ici.');
    config(['documentation.max_undocumented' => 1]);

    $this->artisan('docs:coverage')->assertExitCode(1);
});

test('an excluded class is not required to be documented', function (): void {
    docsCoverageSource($this->docsTmpDir, 'Providers/AppServiceProvider.php');
    docsCoveragePage($this->docsTmpDir, 'index.md', 'Rien ici.');
    config(['documentation.exclude' => [\App\Providers\AppServiceProvider::class => 'câblage de conteneur']]);

    $this->artisan('docs:coverage')->assertExitCode(0);
});

test('it leaves the documentation and the sources untouched', function (): void {
    docsCoverageSource($this->docsTmpDir, 'Domain/Services/ScoreCalculator.php');
    docsCoveragePage($this->docsTmpDir, 'index.md', 'La formule vit dans `ScoreCalculator`.');

    $before = [
        md5_file($this->docsTmpDir.'/app/Domain/Services/ScoreCalculator.php'),
        md5_file($this->docsTmpDir.'/documentation/index.md'),
    ];

    $this->artisan('docs:coverage');

    expect([
        md5_file($this->docsTmpDir.'/app/Domain/Services/ScoreCalculator.php'),
        md5_file($this->docsTmpDir.'/documentation/index.md'),
    ])->toBe($before);
});

test('the real project sits at or below its own declared ceiling', function (): void {
    /** @var array{source_paths: list<string>, pages_path: string, exclude: array<string, string>, max_undocumented: int} $real */
    $real = require config_path('documentation.php');

    config(['documentation' => $real]);

    $this->artisan('docs:coverage')->assertExitCode(0);
});

test('a class added to the real project without documentation fails the command', function (): void {
    /** @var array{source_paths: list<string>, pages_path: string, exclude: array<string, string>, max_undocumented: int} $real */
    $real = require config_path('documentation.php');
    docsCoverageSource($this->docsTmpDir, 'Domain/Services/UndocumentedNewcomer.php');

    config(['documentation' => [...$real, 'source_paths' => [...$real['source_paths'], $this->docsTmpDir.'/app']]]);

    $this->artisan('docs:coverage')
        ->expectsOutputToContain('App\Domain\Services\UndocumentedNewcomer')
        ->assertExitCode(1);
});
