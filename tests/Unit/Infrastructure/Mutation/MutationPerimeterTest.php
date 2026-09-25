<?php

declare(strict_types=1);

use App\Infrastructure\Mutation\MutationPerimeter;

test('it keeps one entry per line and drops comments and blank lines', function (): void {
    $contents = <<<'TXT'
# Header comment.

# Pure domain.
app/Domain

app/Application/Import/VolumeShrink.php
TXT;

    expect(MutationPerimeter::entries($contents))->toBe(['app/Domain', 'app/Application/Import/VolumeShrink.php']);
});

test('surrounding whitespace is not part of an entry', function (): void {
    expect(MutationPerimeter::entries("  app/Domain  \r\n\tapp/Jobs/ComputeCrossCharacterJob.php\n"))
        ->toBe(['app/Domain', 'app/Jobs/ComputeCrossCharacterJob.php']);
});

test('an entry declared twice is kept once', function (): void {
    expect(MutationPerimeter::entries("app/Domain\napp/Domain\n"))->toBe(['app/Domain']);
});

test('an empty perimeter has no entry', function (): void {
    expect(MutationPerimeter::entries("# nothing yet\n"))->toBe([]);
});
