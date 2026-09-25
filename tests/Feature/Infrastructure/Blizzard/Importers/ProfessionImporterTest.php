<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\ProfessionImporter;
use App\Models\WowProfession;
use App\Models\WowRecipe;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

/**
 * Mocke l'index des métiers.
 *
 * @param  list<array{id: int, name: string}>  $professions
 */
function mockProfessionIndex(\Mockery\MockInterface $mock, array $professions): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/profession/index', \Mockery::any())
        ->andReturn([
            'professions' => array_map(fn (array $profession): array => ['id' => $profession['id'], 'name' => $profession['name']], $professions),
        ]);
}

/**
 * Mocke le détail d'un métier (nom, type, skill tiers).
 *
 * @param  list<array{id: int, name: string}>  $tiers
 */
function mockProfessionDetail(\Mockery\MockInterface $mock, int $id, string $name, string $type, array $tiers): void
{
    $mock->shouldReceive('getAsync')
        ->with('data/wow/profession/'.$id, \Mockery::any())
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'id' => $id,
            'name' => $name,
            'type' => ['type' => $type, 'name' => 'x'],
            'skill_tiers' => array_map(fn (array $tier): array => ['id' => $tier['id'], 'name' => $tier['name']], $tiers),
        ]))));
}

/**
 * Mocke le détail d'un skill tier (catégories → recettes).
 *
 * @param  list<array{name: string, recipes: list<array{id: int, name: string}>}>  $categories
 */
function mockSkillTierDetail(\Mockery\MockInterface $mock, int $professionId, int $tierId, array $categories): void
{
    $mock->shouldReceive('getAsync')
        ->with(sprintf('data/wow/profession/%d/skill-tier/%d', $professionId, $tierId), \Mockery::any())
        ->andReturnUsing(fn (): \GuzzleHttp\Promise\PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode([
            'id' => $tierId,
            'categories' => array_map(fn (array $category): array => [
                'name' => $category['name'],
                'recipes' => array_map(fn (array $recipe): array => ['id' => $recipe['id'], 'name' => $recipe['name']], $category['recipes']),
            ], $categories),
        ]))));
}

test('it imports professions and recipes from the API', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockProfessionIndex($client, [
        ['id' => 164, 'name' => 'Forge'],
    ]);
    mockProfessionDetail($client, 164, 'Forge', 'PRIMARY', [
        ['id' => 2820, 'name' => 'Forge de Khaz Algar'],
    ]);
    mockSkillTierDetail($client, 164, 2820, [
        ['name' => 'Armures', 'recipes' => [
            ['id' => 5001, 'name' => 'Plastron en acier'],
            ['id' => 5002, 'name' => 'Jambiere en fer'],
        ]],
    ]);

    resolve(ProfessionImporter::class)->import();

    expect(WowProfession::query()->count())->toBe(1);
    expect(WowProfession::query()->find(164)->name_fr)->toBe('Forge');
    expect(WowProfession::query()->find(164)->type)->toBe('primary');

    expect(WowRecipe::query()->count())->toBe(2);
    expect(WowRecipe::query()->find(5001)->name_fr)->toBe('Plastron en acier');
    expect(WowRecipe::query()->find(5001)->profession_id)->toBe(164);
    expect(WowRecipe::query()->find(5001)->expansion_id)->toBe(10); // Khaz Algar = TWW
    expect(WowRecipe::query()->find(5001)->category_name)->toBe('Armures');
    expect(WowRecipe::query()->find(5001)->is_active)->toBeTrue();
    expect(WowRecipe::query()->find(5002)->name_fr)->toBe('Jambiere en fer');
});

test('it preserves existing wowhead_spell_id values on re-import', function (): void {
    WowProfession::factory()->create(['id' => 164, 'name_fr' => 'Forge', 'type' => 'primary']);
    WowRecipe::factory()->create([
        'id' => 5001,
        'name_fr' => 'Ancien nom',
        'profession_id' => 164,
        'expansion_id' => 10,
        'wowhead_spell_id' => 80001,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockProfessionIndex($client, [['id' => 164, 'name' => 'Forge']]);
    mockProfessionDetail($client, 164, 'Forge', 'PRIMARY', [['id' => 2820, 'name' => 'Forge de Khaz Algar']]);
    mockSkillTierDetail($client, 164, 2820, [
        ['name' => 'Armures', 'recipes' => [['id' => 5001, 'name' => 'Plastron en acier']]],
    ]);

    resolve(ProfessionImporter::class)->import();

    $recipe = WowRecipe::query()->find(5001);
    expect($recipe->name_fr)->toBe('Plastron en acier')
        ->and($recipe->wowhead_spell_id)->toBe(80001);
});

test('it skips professions that are neither primary nor secondary', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockProfessionIndex($client, [
        ['id' => 164, 'name' => 'Forge'],
        ['id' => 2777, 'name' => 'Chiffrage des âmes'],
    ]);
    mockProfessionDetail($client, 164, 'Forge', 'PRIMARY', []);
    mockProfessionDetail($client, 2777, 'Chiffrage des âmes', 'OTHER', []);

    resolve(ProfessionImporter::class)->import();

    expect(WowProfession::query()->count())->toBe(1)
        ->and(WowProfession::query()->find(164))->not->toBeNull();
});

test('it assigns recipe factions from faction map', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockProfessionIndex($client, [['id' => 164, 'name' => 'Forge']]);
    mockProfessionDetail($client, 164, 'Forge', 'PRIMARY', [['id' => 2820, 'name' => 'Forge de Khaz Algar']]);
    mockSkillTierDetail($client, 164, 2820, [
        ['name' => 'Armures', 'recipes' => [['id' => 5001, 'name' => 'Plastron allianceux']]],
    ]);

    resolve(ProfessionImporter::class)->import([5001 => 'Alliance']);

    expect(WowRecipe::query()->find(5001)->faction)->toBe('Alliance');
});

test('it returns early when no primary or secondary profession is found', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockProfessionIndex($client, [['id' => 2777, 'name' => 'Chiffrage des âmes']]);
    mockProfessionDetail($client, 2777, 'Chiffrage des âmes', 'OTHER', []);

    resolve(ProfessionImporter::class)->import();

    expect(WowProfession::query()->count())->toBe(0);
});

test('it returns early when the profession index fails', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')
        ->with('data/wow/profession/index', \Mockery::any())
        ->andThrow(new \Exception('API error: 500 Internal Server Error'));

    resolve(ProfessionImporter::class)->import();

    expect(WowProfession::query()->count())->toBe(0);
});

test('it tags mirror recipe pairs', function (): void {
    WowProfession::factory()->create(['id' => 164]);
    WowRecipe::factory()->create([
        'id' => 9001,
        'name_fr' => 'Tabard de faction',
        'profession_id' => 164,
        'expansion_id' => 5,
        'faction' => 'Alliance',
        'is_active' => true,
    ]);
    WowRecipe::factory()->create([
        'id' => 9002,
        'name_fr' => 'Tabard de faction',
        'profession_id' => 164,
        'expansion_id' => 5,
        'faction' => null,
        'is_active' => true,
    ]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    resolve(ProfessionImporter::class)->tagMirrorRecipeFactions();

    expect(WowRecipe::query()->find(9002)->faction)->toBe('Horde');
});

// ─── Réécriture des lignes inchangées ───────────────────────

/**
 * @param  list<array{id: int, name: string}>  $recipes
 */
function professionApiReturning(array $recipes, string $professionName = 'Forge'): void
{
    /** @var BlizzardApiClient&\Mockery\MockInterface $mock */
    $mock = \Mockery::mock(BlizzardApiClient::class);
    app()->instance(BlizzardApiClient::class, $mock);

    mockProfessionIndex($mock, [['id' => 164, 'name' => $professionName]]);
    mockProfessionDetail($mock, 164, $professionName, 'PRIMARY', [['id' => 2820, 'name' => 'Forge de Khaz Algar']]);
    mockSkillTierDetail($mock, 164, 2820, [['name' => 'Armures', 'recipes' => $recipes]]);
}

/**
 * `updated_at` n'a pas de précision sous la seconde : deux passes de test tombent
 * dans la même. On compte donc les écritures réellement émises sur la table.
 */
function writesOnTableDuring(string $table, callable $callback): int
{
    $writes = 0;

    DB::listen(function (Illuminate\Database\Events\QueryExecuted $queryExecuted) use ($table, &$writes): void {
        if (str_contains($queryExecuted->sql, $table) && ! str_starts_with($queryExecuted->sql, 'select')) {
            $writes++;
        }
    });

    $callback();

    return $writes;
}

test('a second pass over unchanged recipes writes nothing', function (): void {
    professionApiReturning([['id' => 5001, 'name' => 'Plastron en acier']]);
    resolve(ProfessionImporter::class)->import();

    professionApiReturning([['id' => 5001, 'name' => 'Plastron en acier']]);
    $writes = writesOnTableDuring('wow_recipes', fn () => resolve(ProfessionImporter::class)->import());

    expect($writes)->toBe(0);
});

test('a second pass over unchanged professions writes nothing', function (): void {
    professionApiReturning([['id' => 5001, 'name' => 'Plastron en acier']]);
    resolve(ProfessionImporter::class)->import();

    professionApiReturning([['id' => 5001, 'name' => 'Plastron en acier']]);
    $writes = writesOnTableDuring('wow_professions', fn () => resolve(ProfessionImporter::class)->import());

    expect($writes)->toBe(0);
});

test('a recipe whose name changed upstream is rewritten', function (): void {
    professionApiReturning([['id' => 5001, 'name' => 'Ancien nom']]);
    resolve(ProfessionImporter::class)->import();

    professionApiReturning([['id' => 5001, 'name' => 'Nouveau nom']]);
    resolve(ProfessionImporter::class)->import();

    expect(WowRecipe::query()->find(5001)->name_fr)->toBe('Nouveau nom');
});

test('a profession whose name changed upstream is rewritten', function (): void {
    professionApiReturning([['id' => 5001, 'name' => 'Plastron en acier']]);
    resolve(ProfessionImporter::class)->import();

    professionApiReturning([['id' => 5001, 'name' => 'Plastron en acier']], professionName: 'Forgeage');
    resolve(ProfessionImporter::class)->import();

    expect(WowProfession::query()->find(164)->name_fr)->toBe('Forgeage');
});

test('a faction resolved by the mirror tagger survives the next import', function (): void {
    professionApiReturning([['id' => 5001, 'name' => 'Plastron en acier']]);
    resolve(ProfessionImporter::class)->import();

    WowRecipe::query()->where('id', 5001)->update(['faction' => 'Alliance']);

    professionApiReturning([['id' => 5001, 'name' => 'Plastron en acier']]);
    resolve(ProfessionImporter::class)->import();

    expect(WowRecipe::query()->find(5001)->faction)->toBe('Alliance');
});
