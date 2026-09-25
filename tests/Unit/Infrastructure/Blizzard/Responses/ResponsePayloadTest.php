<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\Exceptions\UnexpectedFieldTypeException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function blizzardPayload(array $decoded): ResponsePayload
{
    return ResponsePayload::forEndpoint('data/wow/pvp-season/index', $decoded);
}

// ─── strings ────────────────────────────────────────────────

test('requiredString returns the value', function (): void {
    expect(blizzardPayload(['name' => 'Thrall'])->requiredString('name'))->toBe('Thrall');
});

test('requiredString rejects a missing field', function (): void {
    blizzardPayload([])->requiredString('name');
})->throws(MissingFieldException::class);

test('requiredString rejects a null field', function (): void {
    blizzardPayload(['name' => null])->requiredString('name');
})->throws(MissingFieldException::class);

test('requiredString rejects a field that is not a string', function (): void {
    blizzardPayload(['name' => 42])->requiredString('name');
})->throws(UnexpectedFieldTypeException::class);

test('optionalString returns the value', function (): void {
    expect(blizzardPayload(['name' => 'Thrall'])->optionalString('name'))->toBe('Thrall');
});

test('optionalString returns null on a missing field', function (): void {
    expect(blizzardPayload([])->optionalString('name'))->toBeNull();
});

test('optionalString returns null on a null field', function (): void {
    expect(blizzardPayload(['name' => null])->optionalString('name'))->toBeNull();
});

test('optionalString rejects a field that is not a string', function (): void {
    blizzardPayload(['name' => 42])->optionalString('name');
})->throws(UnexpectedFieldTypeException::class);

// ─── integers ───────────────────────────────────────────────

test('requiredInt returns the value', function (): void {
    expect(blizzardPayload(['id' => 14])->requiredInt('id'))->toBe(14);
});

test('requiredInt rejects a missing field', function (): void {
    blizzardPayload([])->requiredInt('id');
})->throws(MissingFieldException::class);

test('requiredInt rejects a numeric string', function (): void {
    blizzardPayload(['id' => '14'])->requiredInt('id');
})->throws(UnexpectedFieldTypeException::class);

test('requiredInt rejects a boolean', function (): void {
    blizzardPayload(['id' => true])->requiredInt('id');
})->throws(UnexpectedFieldTypeException::class);

test('optionalInt returns the value', function (): void {
    expect(blizzardPayload(['id' => 14])->optionalInt('id'))->toBe(14);
});

test('optionalInt returns null on a missing field', function (): void {
    expect(blizzardPayload([])->optionalInt('id'))->toBeNull();
});

test('optionalInt returns null on a null field', function (): void {
    expect(blizzardPayload(['id' => null])->optionalInt('id'))->toBeNull();
});

test('optionalInt rejects a field that is not an integer', function (): void {
    blizzardPayload(['id' => 1.5])->optionalInt('id');
})->throws(UnexpectedFieldTypeException::class);

test('lenientInt reads an integer', function (): void {
    expect(blizzardPayload(['id' => 9001])->lenientInt('id'))->toBe(9001);
});

test('lenientInt reads a numeric string', function (): void {
    expect(blizzardPayload(['id' => '9002'])->lenientInt('id'))->toBe(9002);
});

test('lenientInt truncates a decimal number', function (): void {
    expect(blizzardPayload(['id' => 12.9])->lenientInt('id'))->toBe(12);
});

test('lenientInt gives null for anything else', function (): void {
    expect(blizzardPayload(['id' => 'abc'])->lenientInt('id'))->toBeNull()
        ->and(blizzardPayload(['id' => [1]])->lenientInt('id'))->toBeNull()
        ->and(blizzardPayload(['id' => true])->lenientInt('id'))->toBeNull()
        ->and(blizzardPayload([])->lenientInt('id'))->toBeNull();
});

test('lenientString reads a string', function (): void {
    expect(blizzardPayload(['name' => 'Classic'])->lenientString('name'))->toBe('Classic');
});

test('lenientString gives null for anything that is not a string', function (): void {
    expect(blizzardPayload(['name' => 42])->lenientString('name'))->toBeNull()
        ->and(blizzardPayload(['name' => ['Classic']])->lenientString('name'))->toBeNull()
        ->and(blizzardPayload([])->lenientString('name'))->toBeNull();
});

// ─── nested objects ─────────────────────────────────────────

test('requiredObject returns a reader over the nested payload', function (): void {
    $responsePayload = blizzardPayload(['current_season' => ['id' => 40]])->requiredObject('current_season');

    expect($responsePayload->requiredInt('id'))->toBe(40);
});

test('requiredObject rejects a missing field', function (): void {
    blizzardPayload([])->requiredObject('current_season');
})->throws(MissingFieldException::class);

test('requiredObject rejects a field that is not an object', function (): void {
    blizzardPayload(['current_season' => 40])->requiredObject('current_season');
})->throws(UnexpectedFieldTypeException::class);

test('requiredObject rejects a list', function (): void {
    blizzardPayload(['seasons' => [['id' => 40]]])->requiredObject('seasons');
})->throws(UnexpectedFieldTypeException::class);

test('optionalObject returns a reader over the nested payload', function (): void {
    $season = blizzardPayload(['current_season' => ['id' => 40]])->optionalObject('current_season');

    expect($season?->requiredInt('id'))->toBe(40);
});

test('optionalObject returns null on a missing field', function (): void {
    expect(blizzardPayload([])->optionalObject('current_season'))->toBeNull();
});

test('optionalObject returns null on a null field', function (): void {
    expect(blizzardPayload(['current_season' => null])->optionalObject('current_season'))->toBeNull();
});

test('optionalObject rejects a field that is not an object', function (): void {
    blizzardPayload(['current_season' => 'now'])->optionalObject('current_season');
})->throws(UnexpectedFieldTypeException::class);

test('an empty object is a valid object', function (): void {
    expect(blizzardPayload(['current_season' => []])->requiredObject('current_season')->optionalInt('id'))->toBeNull();
});

// ─── object lists ───────────────────────────────────────────

test('objectList returns one reader per entry', function (): void {
    $seasons = blizzardPayload(['seasons' => [['id' => 39], ['id' => 40]]])->objectList('seasons');

    expect($seasons)->toHaveCount(2)
        ->and($seasons[0]->requiredInt('id'))->toBe(39)
        ->and($seasons[1]->requiredInt('id'))->toBe(40);
});

test('objectList returns an empty list on a missing field', function (): void {
    expect(blizzardPayload([])->objectList('seasons'))->toBe([]);
});

test('objectList returns an empty list on a null field', function (): void {
    expect(blizzardPayload(['seasons' => null])->objectList('seasons'))->toBe([]);
});

test('objectList rejects a field that is not a list', function (): void {
    blizzardPayload(['seasons' => ['id' => 40]])->objectList('seasons');
})->throws(UnexpectedFieldTypeException::class);

test('objectList rejects an entry that is not an object', function (): void {
    blizzardPayload(['seasons' => [40]])->objectList('seasons');
})->throws(UnexpectedFieldTypeException::class);

// ─── floats ─────────────────────────────────────────────────

test('requiredFloat widens an integer value', function (): void {
    expect(blizzardPayload(['a' => 1])->requiredFloat('a'))->toBe(1.0);
});

test('requiredFloat rejects a missing field', function (): void {
    blizzardPayload([])->requiredFloat('a');
})->throws(MissingFieldException::class);

test('requiredFloat rejects a field that is not a number', function (): void {
    blizzardPayload(['a' => true])->requiredFloat('a');
})->throws(UnexpectedFieldTypeException::class, 'should be of type float, bool given');

test('optionalFloat returns a decimal value', function (): void {
    expect(blizzardPayload(['rating' => 2456.78])->optionalFloat('rating'))->toBe(2456.78);
});

test('optionalFloat widens an integer value', function (): void {
    expect(blizzardPayload(['rating' => 280])->optionalFloat('rating'))->toBe(280.0);
});

test('optionalFloat returns null on a missing field', function (): void {
    expect(blizzardPayload([])->optionalFloat('rating'))->toBeNull();
});

test('optionalFloat rejects a field that is not a number', function (): void {
    blizzardPayload(['rating' => '280'])->optionalFloat('rating');
})->throws(UnexpectedFieldTypeException::class, 'should be of type float, string given');

// ─── booleans ───────────────────────────────────────────────

test('optionalBool returns the value', function (): void {
    expect(blizzardPayload(['is_completed_within_time' => false])->optionalBool('is_completed_within_time'))->toBeFalse();
});

test('optionalBool returns null on a missing field', function (): void {
    expect(blizzardPayload([])->optionalBool('is_completed_within_time'))->toBeNull();
});

test('optionalBool rejects a field that is not a boolean', function (): void {
    blizzardPayload(['is_completed_within_time' => 1])->optionalBool('is_completed_within_time');
})->throws(UnexpectedFieldTypeException::class, 'should be of type bool, int given');

// ─── integer lists ──────────────────────────────────────────

test('intList returns the integers in order', function (): void {
    expect(blizzardPayload(['unlocks' => [3, 1, 2]])->intList('unlocks'))->toBe([3, 1, 2]);
});

test('intList returns an empty list on a missing field', function (): void {
    expect(blizzardPayload([])->intList('unlocks'))->toBe([]);
});

test('intList rejects a field that is not a list', function (): void {
    blizzardPayload(['unlocks' => ['a' => 1]])->intList('unlocks');
})->throws(UnexpectedFieldTypeException::class, 'should be of type list');

test('intList rejects an entry that is not an integer and names its index', function (): void {
    blizzardPayload(['unlocks' => [1, '2']])->intList('unlocks');
})->throws(UnexpectedFieldTypeException::class, 'Field [unlocks.1]');

// ─── maps keyed by identifier ──────────────────────────────

test('stringMap reads text indexed by identifier', function (): void {
    expect(blizzardPayload(['owners' => [100 => 'Thrall', 7 => '']])->stringMap('owners'))->toBe([100 => 'Thrall', 7 => '']);
});

test('stringMap reads a map whose identifiers decoded into a list', function (): void {
    expect(blizzardPayload(['owners' => ['Thrall', 'Jaina']])->stringMap('owners'))->toBe([0 => 'Thrall', 1 => 'Jaina']);
});

test('stringMap returns an empty map on a missing field', function (): void {
    expect(blizzardPayload([])->stringMap('owners'))->toBe([]);
});

test('stringMap rejects a key that is not an identifier', function (): void {
    blizzardPayload(['owners' => ['thrall' => 'Thrall']])->stringMap('owners');
})->throws(UnexpectedFieldTypeException::class, 'Field [owners] in the response of [data/wow/pvp-season/index] should be of type map keyed by integer');

test('stringMap rejects a value that is not text and names its identifier', function (): void {
    blizzardPayload(['owners' => [100 => 42]])->stringMap('owners');
})->throws(UnexpectedFieldTypeException::class, 'Field [owners.100]');

test('objectMap reads objects indexed by identifier, lists included', function (): void {
    $map = blizzardPayload(['owners' => [164 => [0 => ['name' => 'Classic'], 10 => ['name' => 'Khaz Algar']]]])->objectMap('owners');

    expect(array_keys($map))->toBe([164])
        ->and(array_map(fn (ResponsePayload $responsePayload): ?string => $responsePayload->optionalString('name'), $map[164]->entries()))->toBe([0 => 'Classic', 10 => 'Khaz Algar']);
});

test('objectMap rejects an entry that is not an object', function (): void {
    blizzardPayload(['owners' => [164 => 'Thrall']])->objectMap('owners');
})->throws(UnexpectedFieldTypeException::class, 'Field [owners.164]');

test('objectMap rejects a field that is not a map', function (): void {
    blizzardPayload(['owners' => 'Thrall'])->objectMap('owners');
})->throws(UnexpectedFieldTypeException::class, 'Field [owners] in the response of [data/wow/pvp-season/index] should be of type map keyed by integer');

test('entries rejects a nested map keyed by something else than identifiers', function (): void {
    blizzardPayload(['owners' => [164 => ['classic' => ['name' => 'Classic']]]])->objectMap('owners')[164]->entries();
})->throws(UnexpectedFieldTypeException::class, 'Field [owners.164] in the response of [data/wow/pvp-season/index] should be of type map keyed by integer');

test('entries names the path of a nested entry', function (): void {
    blizzardPayload(['owners' => [164 => [10 => 'x']]])->objectMap('owners')[164]->entries();
})->throws(UnexpectedFieldTypeException::class, 'Field [owners.164.10]');

// ─── whole payload ──────────────────────────────────────────

test('isEmpty is true for an empty response', function (): void {
    expect(blizzardPayload([])->isEmpty())->toBeTrue();
});

test('isEmpty is false as soon as the response carries a field', function (): void {
    expect(blizzardPayload(['season' => null])->isEmpty())->toBeFalse();
});

// ─── error messages ─────────────────────────────────────────

test('a missing field names the field and the endpoint', function (): void {
    blizzardPayload([])->requiredInt('id');
})->throws(MissingFieldException::class, 'Missing field [id] in the response of [data/wow/pvp-season/index]');

test('a missing nested field names the full path', function (): void {
    blizzardPayload(['current_season' => []])->requiredObject('current_season')->requiredInt('id');
})->throws(MissingFieldException::class, 'Missing field [current_season.id] in the response of [data/wow/pvp-season/index]');

test('a missing field inside a list entry names its index', function (): void {
    blizzardPayload(['seasons' => [['id' => 39], []]])->objectList('seasons')[1]->requiredInt('id');
})->throws(MissingFieldException::class, 'Missing field [seasons.1.id] in the response of [data/wow/pvp-season/index]');

test('an unexpected type names the expected and the received type', function (): void {
    blizzardPayload(['current_season' => ['id' => '40']])->requiredObject('current_season')->requiredInt('id');
})->throws(UnexpectedFieldTypeException::class, 'Field [current_season.id] in the response of [data/wow/pvp-season/index] should be of type int, string given');
