<?php

declare(strict_types=1);

use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Importers\AchievementImporter;
use App\Models\WowAchievement;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

/**
 * Mocke la hiérarchie complète : l'index des catégories puis le détail de chacune.
 *
 * @param  list<array{id: int, name: string, parent?: int, achievements?: array<int, string>}>  $categories
 */
function mockAchievementHierarchy(\Mockery\MockInterface $mock, array $categories): void
{
    mockAchievementCategoryIndex($mock, $categories);

    foreach ($categories as $category) {
        mockAchievementCategory($mock, $category);
    }
}

/**
 * @param  list<array{id: int, name: string, parent?: int, achievements?: array<int, string>}>  $categories
 */
function mockAchievementCategoryIndex(\Mockery\MockInterface $mock, array $categories): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/index', \Mockery::any())
        ->andReturn([
            'categories' => array_map(static fn (array $category): array => ['id' => $category['id'], 'name' => $category['name']], $categories),
        ]);
}

/**
 * @param  array{id: int, name: string, parent?: int, achievements?: array<int, string>}  $category
 */
function mockAchievementCategory(\Mockery\MockInterface $mock, array $category): void
{
    $payload = ['id' => $category['id'], 'name' => $category['name'], 'achievements' => []];
    foreach (($category['achievements'] ?? []) as $achievementId => $achievementName) {
        $payload['achievements'][] = ['id' => $achievementId, 'name' => $achievementName];
    }

    if (isset($category['parent'])) {
        $payload['parent_category'] = ['id' => $category['parent'], 'name' => 'Parent'];
    }

    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $endpoint): bool => $endpoint === 'data/wow/achievement-category/'.$category['id'])
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode($payload))));
}

/**
 * @param  array<int, array{points: int, faction?: string}>  $details
 */
function mockAchievementDetails(\Mockery\MockInterface $mock, array $details): void
{
    foreach ($details as $id => $detail) {
        $payload = ['id' => $id, 'points' => $detail['points']];
        if (isset($detail['faction'])) {
            $payload['requirements'] = ['faction' => ['type' => $detail['faction'], 'name' => ucfirst(strtolower($detail['faction']))]];
        }

        $mock->shouldReceive('getAsync')
            ->withArgs(fn (string $endpoint): bool => $endpoint === 'data/wow/achievement/'.$id)
            ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode($payload))));
    }
}

/**
 * @param  array<int, array<int, string|null>>  $windows  index de fenêtre → identifiant → icône
 */
function mockAchievementMedia(\Mockery\MockInterface $mock, array $windows): void
{
    foreach ($windows as $window => $icons) {
        $documents = [];
        foreach ($icons as $id => $iconUrl) {
            $documents[] = ['data' => [
                'id' => $id,
                'assets' => $iconUrl === null ? [] : [['key' => 'icon', 'value' => $iconUrl, 'file_data_id' => 1]],
            ]];
        }

        $range = sprintf('id=[%d,%d]', $window * 1000, $window * 1000 + 999);
        $mock->shouldReceive('getAsync')
            ->withArgs(fn (string $endpoint): bool => str_starts_with($endpoint, 'data/wow/search/media')
                && str_contains($endpoint, $range)
                && str_contains($endpoint, 'tags=achievement'))
            ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode(['results' => $documents]))));
    }
}

/**
 * Fait échouer un endpoint précis, pour distinguer un appel dégradé d'un appel absent.
 */
function failAchievementEndpoint(\Mockery\MockInterface $mock, string $endpoint): void
{
    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $requested): bool => str_starts_with($requested, $endpoint))
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(500, [], '')));
}

test('the catalog is built from the hierarchy, the details and the media', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockAchievementHierarchy($client, [
        ['id' => 96, 'name' => 'Quêtes', 'achievements' => [504 => '100 quêtes achevées']],
        ['id' => 15547, 'name' => 'Midnight', 'parent' => 96, 'achievements' => [41802 => 'Reprise des Chants éternels']],
    ]);
    mockAchievementDetails($client, [
        504 => ['points' => 10],
        41802 => ['points' => 25, 'faction' => 'HORDE'],
    ]);
    mockAchievementMedia($client, [
        0 => [504 => 'https://render.worldofwarcraft.com/eu/icons/56/504.jpg'],
        41 => [41802 => 'https://render.worldofwarcraft.com/eu/icons/56/41802.jpg'],
    ]);

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(2);

    $wowAchievement = WowAchievement::query()->findOrFail(504);
    expect($wowAchievement->name_fr)->toBe('100 quêtes achevées')
        ->and($wowAchievement->category_name)->toBe('Quêtes')
        ->and($wowAchievement->expansion_id)->toBe(ExpansionId::UNCLASSIFIED)
        ->and($wowAchievement->points)->toBe(10)
        ->and($wowAchievement->faction)->toBeNull()
        ->and($wowAchievement->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/504.jpg')
        ->and($wowAchievement->is_active)->toBeTrue();

    $midnight = WowAchievement::query()->findOrFail(41802);
    expect($midnight->category_name)->toBe('Quêtes')
        ->and($midnight->expansion_id)->toBe(ExpansionId::MIDNIGHT)
        ->and($midnight->points)->toBe(25)
        ->and($midnight->faction)->toBe('Horde')
        ->and($midnight->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/41802.jpg');
});

test('an achievement no ancestor dates lands in the unclassified bucket, not in Classic', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockAchievementHierarchy($client, [
        ['id' => 155, 'name' => 'Évènements mondiaux'],
        ['id' => 156, 'name' => "Voile d'hiver", 'parent' => 155, 'achievements' => [1685 => 'Joyeuses fêtes !']],
    ]);
    mockAchievementDetails($client, [1685 => ['points' => 10]]);
    mockAchievementMedia($client, [1 => [1685 => 'https://render.worldofwarcraft.com/eu/icons/56/1685.jpg']]);

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->findOrFail(1685)->expansion_id)->toBe(ExpansionId::UNCLASSIFIED);
});

test('a hierarchy holding no achievement at all touches nothing', function (): void {
    WowAchievement::query()->create(['id' => 400, 'name_fr' => 'Haut-fait existant', 'expansion_id' => 0, 'category_name' => 'Quêtes', 'points' => 10, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementHierarchy($client, [['id' => 96, 'name' => 'Quêtes']]);

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(1)
        ->and(WowAchievement::query()->findOrFail(400)->name_fr)->toBe('Haut-fait existant');
});

test('it aborts without touching the catalog when the category index fails', function (): void {
    WowAchievement::query()->create(['id' => 400, 'name_fr' => 'Haut-fait existant', 'expansion_id' => 0, 'category_name' => 'Quêtes', 'points' => 10, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    $client->shouldReceive('get')->andThrow(new \Exception('API error: 500 Internal Server Error'));
    $client->shouldNotReceive('getAsync');

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(1)
        ->and(WowAchievement::query()->findOrFail(400)->name_fr)->toBe('Haut-fait existant');
});

test('it aborts without touching the catalog when a single category detail fails', function (): void {
    WowAchievement::query()->create(['id' => 400, 'name_fr' => 'Haut-fait existant', 'expansion_id' => 0, 'category_name' => 'Quêtes', 'points' => 10, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementCategoryIndex($client, [
        ['id' => 96, 'name' => 'Quêtes'],
        ['id' => 15547, 'name' => 'Midnight'],
    ]);
    mockAchievementCategory($client, ['id' => 96, 'name' => 'Quêtes', 'achievements' => [504 => '100 quêtes achevées']]);
    failAchievementEndpoint($client, 'data/wow/achievement-category/15547');

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(1)
        ->and(WowAchievement::query()->findOrFail(400)->name_fr)->toBe('Haut-fait existant');
});

test('an achievement that left the hierarchy leaves the catalog', function (): void {
    WowAchievement::query()->create(['id' => 900, 'name_fr' => 'Retiré du jeu', 'expansion_id' => 0, 'category_name' => 'Tours de force', 'points' => 0, 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementHierarchy($client, [['id' => 96, 'name' => 'Quêtes', 'achievements' => [504 => '100 quêtes achevées']]]);
    mockAchievementDetails($client, [504 => ['points' => 10]]);
    mockAchievementMedia($client, [0 => [504 => null]]);

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->count())->toBe(1)
        ->and(WowAchievement::query()->find(900))->toBeNull();
});

test('an unreachable detail leaves the points and the faction already in base alone', function (): void {
    WowAchievement::query()->create(['id' => 504, 'name_fr' => 'Ancien nom', 'expansion_id' => 0, 'category_name' => 'Quêtes', 'points' => 42, 'faction' => 'Alliance', 'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/old.jpg', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementHierarchy($client, [['id' => 96, 'name' => 'Quêtes', 'achievements' => [504 => '100 quêtes achevées']]]);
    failAchievementEndpoint($client, 'data/wow/achievement/504');
    mockAchievementMedia($client, [0 => [504 => 'https://render.worldofwarcraft.com/eu/icons/56/504.jpg']]);

    resolve(AchievementImporter::class)->import();

    $wowAchievement = WowAchievement::query()->findOrFail(504);
    expect($wowAchievement->points)->toBe(42)
        ->and($wowAchievement->faction)->toBe('Alliance')
        ->and($wowAchievement->name_fr)->toBe('100 quêtes achevées')
        ->and($wowAchievement->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/504.jpg');
});

test('a detail that carries no faction clears the faction the catalog held', function (): void {
    WowAchievement::query()->create(['id' => 504, 'name_fr' => '100 quêtes achevées', 'expansion_id' => 0, 'category_name' => 'Quêtes', 'points' => 10, 'faction' => 'Alliance', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementHierarchy($client, [['id' => 96, 'name' => 'Quêtes', 'achievements' => [504 => '100 quêtes achevées']]]);
    mockAchievementDetails($client, [504 => ['points' => 10]]);
    mockAchievementMedia($client, [0 => [504 => null]]);

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->findOrFail(504)->faction)->toBeNull();
});

test('a media window the API did not answer leaves the icon already in base alone', function (): void {
    WowAchievement::query()->create(['id' => 504, 'name_fr' => '100 quêtes achevées', 'expansion_id' => ExpansionId::UNCLASSIFIED, 'category_name' => 'Quêtes', 'points' => 10, 'icon_url' => 'https://render.worldofwarcraft.com/eu/icons/56/kept.jpg', 'is_active' => true]);

    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementHierarchy($client, [['id' => 96, 'name' => 'Quêtes', 'achievements' => [504 => '100 quêtes achevées']]]);
    mockAchievementDetails($client, [504 => ['points' => 10]]);
    failAchievementEndpoint($client, 'data/wow/search/media');

    resolve(AchievementImporter::class)->import();

    expect(WowAchievement::query()->findOrFail(504)->icon_url)->toBe('https://render.worldofwarcraft.com/eu/icons/56/kept.jpg');
});

test('a second pass over an unchanged catalog writes nothing', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementHierarchy($client, [
        ['id' => 96, 'name' => 'Quêtes', 'achievements' => [504 => '100 quêtes achevées']],
        ['id' => 15547, 'name' => 'Midnight', 'parent' => 96, 'achievements' => [41802 => 'Reprise des Chants éternels']],
    ]);
    mockAchievementDetails($client, [504 => ['points' => 10], 41802 => ['points' => 25, 'faction' => 'HORDE']]);
    mockAchievementMedia($client, [
        0 => [504 => 'https://render.worldofwarcraft.com/eu/icons/56/504.jpg'],
        41 => [41802 => 'https://render.worldofwarcraft.com/eu/icons/56/41802.jpg'],
    ]);

    resolve(AchievementImporter::class)->import();

    DB::enableQueryLog();
    resolve(AchievementImporter::class)->import();
    $writes = array_filter(
        DB::getQueryLog(),
        static fn (array $query): bool => str_contains((string) $query['query'], 'insert into "wow_achievements"'),
    );
    DB::disableQueryLog();

    expect($writes)->toBe([])
        ->and(WowAchievement::query()->count())->toBe(2);
});

test('the report names the categories nothing dates and the dating signals going stale', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);
    mockAchievementHierarchy($client, [
        ['id' => 96, 'name' => 'Quêtes'],
        ['id' => 14863, 'name' => 'Norfendre', 'parent' => 96, 'achievements' => [41999 => 'Bien trop récent']],
        ['id' => 156, 'name' => "Voile d'hiver", 'parent' => 96, 'achievements' => [1685 => 'Joyeuses fêtes !']],
    ]);
    mockAchievementDetails($client, [41999 => ['points' => 10], 1685 => ['points' => 10]]);
    mockAchievementMedia($client, [41 => [41999 => null], 1 => [1685 => null]]);

    $report = [];
    \Illuminate\Support\Facades\Log::shouldReceive('info')->andReturnUsing(function (string $message) use (&$report): void {
        $report[] = $message;
    });

    resolve(AchievementImporter::class)->import();

    $reported = implode("\n", $report);
    expect($reported)->toContain("Quêtes > Voile d'hiver")
        ->and($reported)->toContain('Quêtes > Norfendre: 41999 Bien trop récent')
        ->and($reported)->toContain('going stale');
});
