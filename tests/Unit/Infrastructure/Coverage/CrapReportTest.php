<?php

declare(strict_types=1);

use App\Infrastructure\Coverage\CrapReport;
use App\Infrastructure\Coverage\MethodRisk;

function methodRisk(string $methodName, float $crap): MethodRisk
{
    return new MethodRisk(\App\Domain\Services\ScoreCalculator::class, $methodName, 10, 0.0, $crap);
}

test('only the methods above the threshold are reported, worst first', function (): void {
    $crapReport = CrapReport::of([
        methodRisk('mild', 31.0),
        methodRisk('safe', 12.0),
        methodRisk('worst', 110.0),
        methodRisk('bad', 56.0),
    ], 30);

    expect(array_map(fn (MethodRisk $methodRisk): string => $methodRisk->methodName, $crapReport->risky))
        ->toBe(['worst', 'bad', 'mild'])
        ->and($crapReport->measured)->toBe(4)
        ->and($crapReport->threshold)->toBe(30);
});

test('a method exactly at the threshold is not reported', function (): void {
    $crapReport = CrapReport::of([methodRisk('borderline', 30.0)], 30);

    expect($crapReport->risky)->toBe([])
        ->and($crapReport->exceedsThreshold())->toBeFalse();
});

test('a single method above the threshold makes the report exceed it', function (): void {
    expect(CrapReport::of([methodRisk('risky', 30.01)], 30)->exceedsThreshold())->toBeTrue();
});

test('a threshold below one is refused', function (): void {
    CrapReport::of([], 0);
})->throws(InvalidArgumentException::class);
