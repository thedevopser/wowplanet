<?php

declare(strict_types=1);

use App\Infrastructure\Mutation\MutationScope;

const SCOPE_PERIMETER = [
    'app/Application/Import/ImportStage.php',
    'app/Application/Services/CrossCharacterService.php',
    'app/Domain/Services/ScoreCalculator.php',
];

test('a perimeter file changed by the branch is selected', function (): void {
    expect(MutationScope::select(SCOPE_PERIMETER, [], ['app/Domain/Services/ScoreCalculator.php']))
        ->toBe(['app/Domain/Services/ScoreCalculator.php']);
});

test('a changed file outside the perimeter selects nothing', function (): void {
    expect(MutationScope::select(SCOPE_PERIMETER, [], ['app/Http/Controllers/AuthController.php', 'README.md']))
        ->toBe([]);
});

test('a changed test named after a perimeter class selects that class', function (): void {
    expect(MutationScope::select(SCOPE_PERIMETER, [], ['tests/Feature/Application/Services/CrossCharacterServiceTest.php']))
        ->toBe(['app/Application/Services/CrossCharacterService.php']);
});

test('a test whose name extends the class name still selects it', function (): void {
    expect(MutationScope::select(SCOPE_PERIMETER, [], ['tests/Unit/Domain/Services/ScoreCalculatorEdgeCasesTest.php']))
        ->toBe(['app/Domain/Services/ScoreCalculator.php']);
});

test('a file outside tests is never read as a test, whatever its name', function (): void {
    expect(MutationScope::select(SCOPE_PERIMETER, [], ['app/Support/ScoreCalculatorTest.php']))
        ->toBe([]);
});

test('an entry newly declared in the perimeter is selected', function (): void {
    expect(MutationScope::select(SCOPE_PERIMETER, ['app/Application/Import/ImportStage.php'], []))
        ->toBe(['app/Application/Import/ImportStage.php']);
});

test('the selection is sorted and holds each file once', function (): void {
    expect(MutationScope::select(
        SCOPE_PERIMETER,
        ['app/Domain/Services/ScoreCalculator.php'],
        [
            'app/Domain/Services/ScoreCalculator.php',
            'tests/Unit/Domain/Services/ScoreCalculatorTest.php',
            'app/Application/Import/ImportStage.php',
        ],
    ))->toBe([
        'app/Application/Import/ImportStage.php',
        'app/Domain/Services/ScoreCalculator.php',
    ]);
});

test('the dedicated tests of a class are the tests named after it, sorted', function (): void {
    expect(MutationScope::dedicatedTests('app/Application/Import/ImportRun.php', [
        'tests/Unit/Application/Import/ImportRunTest.php',
        'tests/Feature/Admin/ImportControllerTest.php',
        'tests/Unit/Application/Import/ImportRunStateTest.php',
        'tests/Unit/Application/Import/ImportRequestTest.php',
    ]))->toBe([
        'tests/Unit/Application/Import/ImportRunStateTest.php',
        'tests/Unit/Application/Import/ImportRunTest.php',
    ]);
});

test('a class without a test named after it has no dedicated test', function (): void {
    expect(MutationScope::dedicatedTests('app/Domain/Services/PvpBracketClassifier.php', [
        'tests/Feature/Application/Services/PvpProfileServiceTest.php',
    ]))->toBe([]);
});

test('only php files ending in Test under tests count as dedicated tests', function (): void {
    expect(MutationScope::dedicatedTests('app/Domain/Services/ScoreCalculator.php', [
        'tests/Unit/Domain/Services/ScoreCalculatorHelpers.php',
        'app/Support/ScoreCalculatorTest.php',
    ]))->toBe([]);
});
