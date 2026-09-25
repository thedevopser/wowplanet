<?php

declare(strict_types=1);

use App\Infrastructure\Reference\ReferenceStore;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomyUpstreamUnreachableException;
use App\Infrastructure\Taxonomy\SimpleArmoryClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(ReferenceStore::DISK);
    Http::preventStrayRequests();
});

test('it fetches a curated dump and stores it on the reference disk', function (): void {
    Http::fake(['simplearmory.com/data/mounts.json' => Http::response('[{"name":"Classic"}]')]);

    $bytes = (new SimpleArmoryClient)->fetch(CollectionEntity::Mount);

    expect($bytes)->toBe(20)
        ->and(Storage::disk(ReferenceStore::DISK)->get('mounts.json'))->toBe('[{"name":"Classic"}]');
});

test('it fetches each collection from its own upstream document', function (): void {
    Http::fake([
        'simplearmory.com/data/pets.json' => Http::response('["pets"]'),
        'simplearmory.com/data/decors.json' => Http::response('["decors"]'),
    ]);

    $simpleArmoryClient = new SimpleArmoryClient;
    $simpleArmoryClient->fetch(CollectionEntity::Pet);
    $simpleArmoryClient->fetch(CollectionEntity::Decor);

    expect(Storage::disk(ReferenceStore::DISK)->get('pets.json'))->toBe('["pets"]')
        ->and(Storage::disk(ReferenceStore::DISK)->get('decors.json'))->toBe('["decors"]');
});

test('a refused download is reported and leaves nothing behind', function (): void {
    Http::fake(['simplearmory.com/*' => Http::response('Not found', 404)]);

    expect(fn (): int => (new SimpleArmoryClient)->fetch(CollectionEntity::Mount))
        ->toThrow(TaxonomyUpstreamUnreachableException::class, '404')
        ->and(Storage::disk(ReferenceStore::DISK)->exists('mounts.json'))->toBeFalse();
});

test('an empty download is reported rather than stored', function (): void {
    Http::fake(['simplearmory.com/*' => Http::response("  \n")]);

    expect(fn (): int => (new SimpleArmoryClient)->fetch(CollectionEntity::Pet))
        ->toThrow(TaxonomyUpstreamUnreachableException::class, 'pets.json')
        ->and(Storage::disk(ReferenceStore::DISK)->exists('pets.json'))->toBeFalse();
});

test('the upstream base url is configurable', function (): void {
    config(['services.simplearmory.base_url' => 'https://mirror.test/curated']);
    Http::fake(['mirror.test/curated/mounts.json' => Http::response('["ok"]')]);

    (new SimpleArmoryClient)->fetch(CollectionEntity::Mount);

    expect(Storage::disk(ReferenceStore::DISK)->get('mounts.json'))->toBe('["ok"]');
});
