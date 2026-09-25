<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Traduit en FR les classes et spécialisations désignées par leur nom anglais.
 *
 * Les classements PvP de Blizzard nomment leurs brackets par slug anglais
 * (« shuffle-deathknight-blood ») et n'exposent aucun identifiant : impossible
 * de les localiser directement. On rapproche donc l'index anglais et l'index
 * français par id, puis on indexe le résultat sur le nom anglais slugifié —
 * quatre appels, mis en cache 30 jours, et aucune table de correspondance à
 * maintenir dans le dépôt.
 */
class PlayableNameService
{
    private const TTL_S = 2592000;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    /**
     * @return string|null « Chevalier de la mort · Sang », ou null si non résolu
     */
    public function labelFor(string $classSlug, string $specSlug): ?string
    {
        $names = $this->names();

        $class = $names['classes'][$classSlug] ?? null;
        $spec = $names['specs'][$specSlug] ?? null;

        if ($class === null || $spec === null) {
            return null;
        }

        return $class.' · '.$spec;
    }

    /**
     * @return array{classes: array<string, string>, specs: array<string, string>}
     */
    private function names(): array
    {
        /** @var array{classes: array<string, string>, specs: array<string, string>} $names */
        $names = Cache::remember(
            'wow_playable_names:'.$this->blizzardApiClient->getRegion(),
            self::TTL_S,
            fn (): array => [
                'classes' => $this->buildMap('data/wow/playable-class/index', 'classes'),
                'specs' => $this->buildMap('data/wow/playable-specialization/index', 'character_specializations'),
            ],
        );

        return $names;
    }

    /**
     * @return array<string, string> Nom anglais slugifié => nom FR
     */
    private function buildMap(string $endpoint, string $key): array
    {
        $english = $this->fetchNames($endpoint, $key, 'en_US');
        $french = $this->fetchNames($endpoint, $key, 'fr_FR');

        $map = [];
        foreach ($english as $id => $name) {
            $slug = $this->slugify($name);
            if ($slug === '') {
                continue;
            }

            if (! isset($french[$id])) {
                continue;
            }

            $map[$slug] = $french[$id];
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function fetchNames(string $endpoint, string $key, string $locale): array
    {
        try {
            $response = $this->blizzardApiClient->get($endpoint, [
                'namespace' => 'static-'.$this->blizzardApiClient->getRegion(),
                'locale' => $locale,
            ]);
        } catch (\Throwable $throwable) {
            Log::debug(sprintf('Playable names fetch failed [%s/%s]: ', $endpoint, $locale).$throwable->getMessage());

            return [];
        }

        $names = [];
        foreach (ResponsePayload::forEndpoint($endpoint, $response)->objectList($key) as $responsePayload) {
            $id = $responsePayload->optionalInt('id') ?? 0;
            $name = $responsePayload->lenientString('name');

            if ($id > 0 && $name !== null && $name !== '') {
                $names[$id] = $name;
            }
        }

        return $names;
    }

    /**
     * « Death Knight » → « deathknight », « Beast Mastery » → « beastmastery » :
     * la forme exacte utilisée par Blizzard dans ses slugs de bracket.
     */
    private function slugify(string $name): string
    {
        return mb_strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $name));
    }
}
