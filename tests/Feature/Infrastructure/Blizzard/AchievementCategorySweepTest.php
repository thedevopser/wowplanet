<?php

declare(strict_types=1);

use App\Domain\ValueObjects\ExpansionId;
use App\Infrastructure\Blizzard\AchievementCategorySweep;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
});

/**
 * @param  list<int>  $categoryIds
 * @param  list<int>  $guildCategoryIds
 */
function mockCategoryIndex(\Mockery\MockInterface $mock, array $categoryIds, array $guildCategoryIds = []): void
{
    $mock->shouldReceive('get')
        ->with('data/wow/achievement-category/index', \Mockery::any())
        ->andReturn([
            'categories' => array_map(static fn (int $id): array => ['id' => $id, 'name' => 'Catégorie '.$id], $categoryIds),
            'guild_categories' => array_map(static fn (int $id): array => ['id' => $id, 'name' => 'Guilde '.$id], $guildCategoryIds),
        ]);
}

/**
 * @param  array<int, string>  $achievements
 */
function mockCategoryDetail(\Mockery\MockInterface $mock, int $id, string $name, ?int $parentId, array $achievements): void
{
    $payload = ['id' => $id, 'name' => $name, 'achievements' => []];
    foreach ($achievements as $achievementId => $achievementName) {
        $payload['achievements'][] = ['id' => $achievementId, 'name' => $achievementName];
    }

    if ($parentId !== null) {
        $payload['parent_category'] = ['id' => $parentId, 'name' => 'Parent'];
    }

    $mock->shouldReceive('getAsync')
        ->withArgs(fn (string $endpoint): bool => $endpoint === 'data/wow/achievement-category/'.$id)
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(200, [], (string) json_encode($payload))));
}

test('the taxonomy is built from the index and the detail of every category', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockCategoryIndex($client, [96, 15547]);
    mockCategoryDetail($client, 96, 'Quêtes', null, [504 => '100 quêtes achevées']);
    mockCategoryDetail($client, 15547, 'Midnight', 96, [41802 => 'Reprise des Chants éternels']);

    $achievementTaxonomy = resolve(AchievementCategorySweep::class)->fetchTaxonomy();

    expect($achievementTaxonomy)->not->toBeNull()
        ->and($achievementTaxonomy->placements())->toHaveCount(2)
        ->and($achievementTaxonomy->placements()[0]->id)->toBe(504)
        ->and($achievementTaxonomy->placements()[1]->expansionId)->toBe(ExpansionId::MIDNIGHT);
});

test('the guild categories listed apart are never fetched', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockCategoryIndex($client, [96], guildCategoryIds: [15076]);
    mockCategoryDetail($client, 96, 'Quêtes', null, [504 => '100 quêtes achevées']);
    $client->shouldNotReceive('getAsync')->with('data/wow/achievement-category/15076', \Mockery::any());

    expect(resolve(AchievementCategorySweep::class)->fetchTaxonomy()?->placements())->toHaveCount(1);
});

test('an unreachable category index carries no taxonomy at all', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    $client->shouldReceive('get')->andThrow(new \Exception('API error: 500 Internal Server Error'));
    $client->shouldNotReceive('getAsync');

    expect(resolve(AchievementCategorySweep::class)->fetchTaxonomy())->toBeNull();
});

test('an index holding no category carries no taxonomy at all', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockCategoryIndex($client, []);
    $client->shouldNotReceive('getAsync');

    expect(resolve(AchievementCategorySweep::class)->fetchTaxonomy())->toBeNull();
});

test('a single missing category detail abandons the whole taxonomy', function (): void {
    /** @var BlizzardApiClient|\Mockery\MockInterface $client */
    $client = $this->mock(BlizzardApiClient::class);

    mockCategoryIndex($client, [96, 15547]);
    mockCategoryDetail($client, 96, 'Quêtes', null, [504 => '100 quêtes achevées']);
    $client->shouldReceive('getAsync')
        ->withArgs(fn (string $endpoint): bool => $endpoint === 'data/wow/achievement-category/15547')
        ->andReturnUsing(fn (): PromiseInterface => Create::promiseFor(new Response(500, [], '')));

    expect(resolve(AchievementCategorySweep::class)->fetchTaxonomy())->toBeNull();
});
