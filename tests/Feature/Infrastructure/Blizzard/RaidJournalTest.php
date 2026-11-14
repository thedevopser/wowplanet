<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\RaidJournal;

/**
 * @param  array<int, array{name: string, encounters: list<array{id: int, name: string}>}|Throwable>  $instances
 */
function journalClient(array $instances): void
{
    $client = Mockery::mock(BlizzardApiClient::class);
    $client->allows('getRegion')->andReturn('eu');
    $client->allows('get')->andReturnUsing(function (string $endpoint) use ($instances): array {
        $instanceId = (int) substr($endpoint, strrpos($endpoint, '/') + 1);
        $instance = $instances[$instanceId];

        throw_if($instance instanceof Throwable, $instance);

        return $instance;
    });
    app()->instance(BlizzardApiClient::class, $client);
}

test('it names each raid and every one of its bosses, in French', function (): void {
    journalClient([
        1307 => ['name' => 'La Flèche du Vide', 'encounters' => [['id' => 11, 'name' => 'Imperator Averzian'], ['id' => 12, 'name' => 'Vorasius']]],
        1314 => ['name' => 'La Faille du Rêve', 'encounters' => [['id' => 21, 'name' => 'Chimaerus']]],
    ]);

    expect(resolve(RaidJournal::class)->forInstances([1307, 1314]))->toBe([
        1307 => ['name' => 'La Flèche du Vide', 'encounters' => [11 => 'Imperator Averzian', 12 => 'Vorasius']],
        1314 => ['name' => 'La Faille du Rêve', 'encounters' => [21 => 'Chimaerus']],
    ]);
});

test('it reads the static data of a raid from Blizzard once, then from the cache', function (): void {
    journalClient([1307 => ['name' => 'La Flèche du Vide', 'encounters' => []]]);

    resolve(RaidJournal::class)->forInstances([1307]);
    resolve(RaidJournal::class)->forInstances([1307]);

    resolve(BlizzardApiClient::class)->shouldHaveReceived('get')->once()->with('data/wow/journal-instance/1307', ['namespace' => 'static-eu']);
});

test('a raid whose static data cannot be read is left out, the others stay', function (): void {
    journalClient([
        1307 => new RuntimeException('static data unavailable'),
        1314 => ['name' => 'La Faille du Rêve', 'encounters' => []],
    ]);

    expect(array_keys(resolve(RaidJournal::class)->forInstances([1307, 1314])))->toBe([1314]);
});
