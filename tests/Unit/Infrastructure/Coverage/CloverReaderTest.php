<?php

declare(strict_types=1);

use App\Infrastructure\Coverage\CloverReader;
use App\Infrastructure\Coverage\Exceptions\UnreadableCloverException;
use App\Infrastructure\Coverage\MethodRisk;

function cloverDocument(string $files): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1">
  <project timestamp="1">
    <package name="App\Domain\Services">
{$files}
    </package>
  </project>
</coverage>
XML;
}

test('it reads each method with its class, complexity and the crap given by the clover', function (): void {
    $xml = cloverDocument(<<<'XML'
      <file name="/app/app/Domain/Services/ScoreCalculator.php">
        <class name="App\Domain\Services\ScoreCalculator" namespace="App\Domain\Services"/>
        <line num="10" type="method" name="compute" visibility="public" complexity="5" crap="5" count="3"/>
        <line num="11" type="stmt" count="3"/>
        <line num="20" type="method" name="weigh" visibility="private" complexity="12" crap="42.75" count="0"/>
        <line num="21" type="stmt" count="0"/>
      </file>
XML);

    $methods = CloverReader::methods($xml);

    expect($methods)->toHaveCount(2)
        ->and($methods[0])->toEqual(new MethodRisk(\App\Domain\Services\ScoreCalculator::class, 'compute', 5, 100.0, 5.0))
        ->and($methods[1]->methodName)->toBe('weigh')
        ->and($methods[1]->complexity)->toBe(12)
        ->and($methods[1]->crap)->toBe(42.75);
});

test('a method coverage counts only the statements up to the next method', function (): void {
    $xml = cloverDocument(<<<'XML'
      <file name="/app/app/Domain/Services/ScoreCalculator.php">
        <class name="App\Domain\Services\ScoreCalculator" namespace="App\Domain\Services"/>
        <line num="10" type="method" name="halfCovered" complexity="2" crap="2.5" count="1"/>
        <line num="11" type="stmt" count="1"/>
        <line num="12" type="stmt" count="0"/>
        <line num="13" type="stmt" count="4"/>
        <line num="14" type="stmt" count="0"/>
        <line num="20" type="method" name="untouched" complexity="1" crap="2" count="0"/>
        <line num="21" type="stmt" count="0"/>
      </file>
XML);

    $methods = CloverReader::methods($xml);

    expect($methods[0]->coverage)->toBe(50.0)
        ->and($methods[1]->coverage)->toBe(0.0);
});

test('a method without statements is covered when it was called', function (): void {
    $xml = cloverDocument(<<<'XML'
      <file name="/app/app/Domain/ValueObjects/ScoreInput.php">
        <class name="App\Domain\ValueObjects\ScoreInput" namespace="App\Domain\ValueObjects"/>
        <line num="10" type="method" name="__construct" complexity="1" crap="1" count="2"/>
        <line num="20" type="method" name="neverBuilt" complexity="1" crap="2" count="0"/>
      </file>
XML);

    $methods = CloverReader::methods($xml);

    expect($methods[0]->coverage)->toBe(100.0)
        ->and($methods[1]->coverage)->toBe(0.0);
});

test('it reads the methods of every file of every package', function (): void {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1">
  <project timestamp="1">
    <package name="App\Domain">
      <file name="/app/app/Domain/A.php">
        <class name="App\Domain\A" namespace="App\Domain"/>
        <line num="1" type="method" name="first" complexity="1" crap="1" count="1"/>
      </file>
    </package>
    <file name="/app/app/B.php">
      <class name="App\B" namespace="App"/>
      <line num="1" type="method" name="second" complexity="1" crap="1" count="1"/>
    </file>
  </project>
</coverage>
XML;

    $methods = CloverReader::methods($xml);

    expect(array_map(fn (MethodRisk $methodRisk): string => $methodRisk->className.'::'.$methodRisk->methodName, $methods))
        ->toBe(['App\Domain\A::first', 'App\B::second']);
});

test('a file without class element is named after its path', function (): void {
    $xml = cloverDocument(<<<'XML'
      <file name="/app/app/helpers.php">
        <line num="1" type="method" name="helper" complexity="1" crap="1" count="1"/>
      </file>
XML);

    expect(CloverReader::methods($xml)[0]->className)->toBe('/app/app/helpers.php');
});

test('an empty project yields no method', function (): void {
    expect(CloverReader::methods(cloverDocument('')))->toBe([]);
});

test('a document that is not xml is refused', function (): void {
    CloverReader::methods('this is not a clover');
})->throws(UnreadableCloverException::class);

test('an xml document that is not a clover report is refused', function (): void {
    CloverReader::methods('<?xml version="1.0"?><phpunit/>');
})->throws(UnreadableCloverException::class);

test('a method line without crap is refused rather than read as zero', function (): void {
    CloverReader::methods(cloverDocument(<<<'XML'
      <file name="/app/app/A.php">
        <class name="App\A" namespace="App"/>
        <line num="1" type="method" name="first" complexity="1" count="1"/>
      </file>
XML));
})->throws(UnreadableCloverException::class, 'crap');
