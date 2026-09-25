<?php

declare(strict_types=1);

use App\Application\Services\PlayableNameService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use Illuminate\Support\Facades\Cache;

/**
 * @param  array<string, array<string, mixed>>  $responses  "endpoint|locale" => corps
 */
function mockPlayableIndexes(array $responses, array &$calls = []): \Mockery\MockInterface
{
    $mock = test()->mock(BlizzardApiClient::class);

    /** @var \Mockery\Expectation $regionExp */
    $regionExp = $mock->shouldReceive('getRegion');
    $regionExp->andReturn('eu');

    /** @var \Mockery\Expectation $getExp */
    $getExp = $mock->shouldReceive('get');
    $getExp->andReturnUsing(function (string $endpoint, array $query = []) use ($responses, &$calls): array {
        $locale = is_string($query['locale'] ?? null) ? $query['locale'] : 'fr_FR';
        $calls[] = $endpoint.'|'.$locale;

        foreach ($responses as $key => $data) {
            [$pattern, $wantedLocale] = explode('|', (string) $key);
            if (str_contains($endpoint, $pattern) && $locale === $wantedLocale) {
                throw_if($data === ['__throw__'], RuntimeException::class, 'Blizzard is down');

                return $data;
            }
        }

        return [];
    });

    return $mock;
}

function playableNameService(): PlayableNameService
{
    return new PlayableNameService(resolve(BlizzardApiClient::class));
}

function playableIndexes(): array
{
    return [
        'playable-class/index|en_US' => ['classes' => [
            ['id' => 6, 'name' => 'Death Knight'],
            ['id' => 3, 'name' => 'Hunter'],
        ]],
        'playable-class/index|fr_FR' => ['classes' => [
            ['id' => 6, 'name' => 'Chevalier de la mort'],
            ['id' => 3, 'name' => 'Chasseur'],
        ]],
        'playable-specialization/index|en_US' => ['character_specializations' => [
            ['id' => 250, 'name' => 'Blood'],
            ['id' => 253, 'name' => 'Beast Mastery'],
        ]],
        'playable-specialization/index|fr_FR' => ['character_specializations' => [
            ['id' => 250, 'name' => 'Sang'],
            ['id' => 253, 'name' => 'Maîtrise des bêtes'],
        ]],
    ];
}

beforeEach(function (): void {
    Cache::flush();
});

test('it resolves a french class and specialization label', function (): void {
    mockPlayableIndexes(playableIndexes());

    expect(playableNameService()->labelFor('deathknight', 'blood'))
        ->toBe('Chevalier de la mort · Sang');
});

test('it matches multi-word english names stripped of their spaces', function (): void {
    mockPlayableIndexes(playableIndexes());

    expect(playableNameService()->labelFor('hunter', 'beastmastery'))
        ->toBe('Chasseur · Maîtrise des bêtes');
});

test('it returns null when the class or the specialization is unknown', function (): void {
    mockPlayableIndexes(playableIndexes());

    expect(playableNameService()->labelFor('deathknight', 'inconnue'))->toBeNull()
        ->and(playableNameService()->labelFor('inconnue', 'blood'))->toBeNull();
});

test('it resolves the four indexes once and serves them from cache', function (): void {
    $calls = [];
    mockPlayableIndexes(playableIndexes(), $calls);

    playableNameService()->labelFor('deathknight', 'blood');
    playableNameService()->labelFor('hunter', 'beastmastery');

    expect($calls)->toHaveCount(4)
        ->and(Cache::has('wow_playable_names:eu'))->toBeTrue();
});

test('it degrades to null when the api fails', function (): void {
    mockPlayableIndexes([
        'playable-class/index|en_US' => ['__throw__'],
        'playable-class/index|fr_FR' => ['__throw__'],
        'playable-specialization/index|en_US' => ['__throw__'],
        'playable-specialization/index|fr_FR' => ['__throw__'],
    ]);

    expect(playableNameService()->labelFor('deathknight', 'blood'))->toBeNull();
});

test('it ignores entries whose french counterpart is missing', function (): void {
    mockPlayableIndexes([
        'playable-class/index|en_US' => ['classes' => [['id' => 6, 'name' => 'Death Knight']]],
        'playable-class/index|fr_FR' => ['classes' => []],
        'playable-specialization/index|en_US' => ['character_specializations' => [['id' => 250, 'name' => 'Blood']]],
        'playable-specialization/index|fr_FR' => ['character_specializations' => [['id' => 250, 'name' => 'Sang']]],
    ]);

    expect(playableNameService()->labelFor('deathknight', 'blood'))->toBeNull();
});
