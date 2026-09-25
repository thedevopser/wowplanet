<?php

declare(strict_types=1);

use App\Infrastructure\Documentation\DocumentationCoverage;

test('a class named between backticks counts as documented', function (): void {
    $report = DocumentationCoverage::report(
        [\App\Domain\Services\ScoreCalculator::class],
        'La formule vit dans `ScoreCalculator`, et nulle part ailleurs.',
        [],
    );

    expect($report->missing)->toBe([])
        ->and($report->documented)->toBe(1)
        ->and($report->total)->toBe(1);
});

test('a class absent from the pages is reported missing', function (): void {
    $report = DocumentationCoverage::report(
        [\App\Domain\Services\ScoreCalculator::class],
        'Cette page ne parle de rien.',
        [],
    );

    expect($report->missing)->toBe([\App\Domain\Services\ScoreCalculator::class])
        ->and($report->documented)->toBe(0);
});

test('a longer class name does not satisfy a shorter one', function (): void {
    $report = DocumentationCoverage::report(
        [\App\Application\Import\ImportStage::class],
        'Le lanceur `ImportStageRunner` exécute une passe par étape.',
        [],
    );

    expect($report->missing)->toBe([\App\Application\Import\ImportStage::class]);
});

test('a class name outside backticks does not count', function (): void {
    $report = DocumentationCoverage::report(
        [\App\Domain\Services\ScoreCalculator::class],
        'Le ScoreCalculator applique une moyenne pondérée.',
        [],
    );

    expect($report->missing)->toBe([\App\Domain\Services\ScoreCalculator::class]);
});

test('a backticked call on the class counts as documented', function (): void {
    $report = DocumentationCoverage::report(
        [\App\Domain\Services\ScoreCalculator::class],
        'On appelle `ScoreCalculator::calculate()` une fois par profil.',
        [],
    );

    expect($report->missing)->toBe([]);
});

test('an excluded class leaves the perimeter entirely', function (): void {
    $report = DocumentationCoverage::report(
        [\App\Providers\AppServiceProvider::class, \App\Domain\Services\ScoreCalculator::class],
        'Rien ici.',
        [\App\Providers\AppServiceProvider::class],
    );

    expect($report->missing)->toBe([\App\Domain\Services\ScoreCalculator::class])
        ->and($report->total)->toBe(1)
        ->and($report->excluded)->toBe(1);
});

test('missing classes are grouped by layer', function (): void {
    $report = DocumentationCoverage::report(
        [
            \App\Domain\Services\ScoreCalculator::class,
            \App\Http\Controllers\PvpController::class,
            \App\Domain\ValueObjects\ScoreWeights::class,
        ],
        'Rien ici.',
        [],
    );

    expect($report->missingByLayer())->toBe([
        'Domain' => [\App\Domain\Services\ScoreCalculator::class, \App\Domain\ValueObjects\ScoreWeights::class],
        'Http' => [\App\Http\Controllers\PvpController::class],
    ]);
});

test('an empty perimeter is fully documented rather than a division by zero', function (): void {
    $report = DocumentationCoverage::report([], '', []);

    expect($report->missing)->toBe([])
        ->and($report->total)->toBe(0)
        ->and($report->percentage())->toBe(100.0);
});

test('the percentage reflects the documented share of the perimeter', function (): void {
    $report = DocumentationCoverage::report(
        ['App\Domain\A', 'App\Domain\B', 'App\Domain\C', 'App\Domain\D'],
        'Seuls `A` et `B` sont décrits.',
        [],
    );

    expect($report->percentage())->toBe(50.0);
});
