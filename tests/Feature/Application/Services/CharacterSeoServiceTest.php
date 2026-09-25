<?php

declare(strict_types=1);

use App\Application\DTOs\CharacterProfileDTO;
use App\Application\Services\CharacterSeoService;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use Illuminate\Support\Facades\Cache;

function makeProfileDTO(array $overrides = []): CharacterProfileDTO
{
    return new CharacterProfileDTO(
        name: $overrides['name'] ?? 'Thrall',
        realm: $overrides['realm'] ?? 'Hyjal',
        race: $overrides['race'] ?? 'Orc',
        class: $overrides['class'] ?? 'Chaman',
        classId: 7,
        level: $overrides['level'] ?? 80,
        ilvl: $overrides['ilvl'] ?? 620,
        faction: $overrides['faction'] ?? 'Horde',
        avatarUrl: $overrides['avatarUrl'] ?? 'https://example.com/avatar.jpg',
        classIconUrl: 'https://example.com/class.jpg',
        collections: [],
        mountsCount: 0,
        petsCount: 0,
    );
}

test('get home meta returns correct structure', function (): void {
    $this->mock(BlizzardApiClient::class);

    $characterSeoService = resolve(CharacterSeoService::class);
    $meta = $characterSeoService->getHomeMeta();

    expect($meta)->toHaveKey('title')
        ->toHaveKey('description')
        ->toHaveKey('ogTitle')
        ->toHaveKey('ogImage')
        ->toHaveKey('canonicalUrl')
        ->toHaveKey('jsonLd')
        ->and((string) $meta['title'])->toContain('WowPlanet');
});

test('build character meta returns structure from profile', function (): void {
    $characterSeoService = resolve(CharacterSeoService::class);

    $meta = $characterSeoService->buildCharacterMeta(makeProfileDTO(), 'hyjal', 'thrall');

    expect((string) $meta['title'])->toContain('Thrall')
        ->and($meta['ogType'])->toBe('profile')
        ->and($meta['jsonLd'])->not->toBeNull()
        ->and((string) $meta['ogImage'])->toContain('avatar.jpg')
        ->and((string) $meta['canonicalUrl'])->toContain('/character/hyjal/thrall');
});

test('build not found character meta returns null jsonLd', function (): void {
    $characterSeoService = resolve(CharacterSeoService::class);

    $meta = $characterSeoService->buildNotFoundCharacterMeta('hyjal', 'unknown');

    expect((string) $meta['title'])->toContain('Unknown')
        ->and($meta['ogType'])->toBe('profile')
        ->and($meta['jsonLd'])->toBeNull();
});

test('generate sitemap index returns valid xml', function (): void {
    $this->mock(BlizzardApiClient::class);

    Cache::flush();
    $characterSeoService = resolve(CharacterSeoService::class);
    $xml = $characterSeoService->generateSitemapIndex();

    expect($xml)->toStartWith('<?xml')
        ->toContain('sitemapindex')
        ->toContain('sitemap-pages.xml')
        ->not->toContain('sitemap-characters.xml');

    $parsed = simplexml_load_string($xml);
    expect($parsed)->not->toBeFalse('Sitemap index XML should be parseable');
});

test('build character meta tracks visit', function (): void {
    $characterSeoService = resolve(CharacterSeoService::class);

    $characterSeoService->buildCharacterMeta(makeProfileDTO(), 'hyjal', 'thrall');

    $this->assertDatabaseHas('character_visits', [
        'realm_slug' => 'hyjal',
        'character_name' => 'thrall',
    ]);
});
