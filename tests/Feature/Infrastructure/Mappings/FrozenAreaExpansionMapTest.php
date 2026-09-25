<?php

declare(strict_types=1);

use App\Infrastructure\Mappings\FrozenAreaExpansionMap;

test('it loads the frozen area expansion map bundled with the repository', function (): void {
    $map = FrozenAreaExpansionMap::load();

    expect(count($map))->toBeGreaterThan(9000)
        ->and($map[1])->toBe(0)      // Dun Morogh → Classic
        ->and($map[2037])->toBe(1);  // Quel'thalas → TBC (override manuel préservé)
});

test('it returns integer keys and values', function (): void {
    $map = FrozenAreaExpansionMap::load();

    $areaId = array_key_first($map);
    expect($areaId)->toBeInt()
        ->and($map[$areaId])->toBeInt();
});
