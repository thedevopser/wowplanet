<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

/**
 * @phpstan-type AccountCharacter array{name: string, realm: string, realmSlug: string, level: int, classId: int, className: string, raceId: int, raceName: string, faction: string, avatarUrl: string}
 */
class UserCharacterService
{
    public const string OWNED_CHARACTERS_SESSION_KEY = 'owned_characters';

    private readonly string $region;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {
        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $this->region = $region;
    }

    public function isAuthenticated(): bool
    {
        return Session::has('blizzard_user_token');
    }

    public function logout(): void
    {
        Session::forget(['blizzard_user_token', 'bnet_user_id', 'bnet_battletag', 'is_admin', self::OWNED_CHARACTERS_SESSION_KEY]);
    }

    /**
     * The account list is read once per session: a character page must not cost a
     * Blizzard call per visit to know whether it can offer the owner's actions.
     */
    public function ownsCharacter(string $realmSlug, string $name): bool
    {
        if (! $this->isAuthenticated()) {
            return false;
        }

        return in_array($this->characterKey($realmSlug, $name), $this->ownedCharacterKeys(), true);
    }

    /**
     * @return list<string>
     */
    private function ownedCharacterKeys(): array
    {
        /** @var list<string>|null $cached */
        $cached = Session::get(self::OWNED_CHARACTERS_SESSION_KEY);

        if ($cached !== null) {
            return $cached;
        }

        /** @var string $token */
        $token = Session::get('blizzard_user_token', '');

        try {
            $characters = $this->parseCharacters($this->blizzardApiClient->getWithUserToken('profile/user/wow', $token));
        } catch (\Throwable) {
            return [];
        }

        $keys = array_map(fn (array $character): string => $this->characterKey($character['realmSlug'], $character['name']), $characters);
        Session::put(self::OWNED_CHARACTERS_SESSION_KEY, $keys);

        return $keys;
    }

    private function characterKey(string $realmSlug, string $name): string
    {
        return mb_strtolower($realmSlug).'/'.mb_strtolower($name);
    }

    /**
     * @return list<AccountCharacter>
     */
    public function getUserCharacters(): array
    {
        /** @var string $token */
        $token = Session::get('blizzard_user_token', '');

        if ($token === '') {
            return [];
        }

        $responsePayload = $this->blizzardApiClient->getWithUserToken('profile/user/wow', $token);
        $characters = $this->parseCharacters($responsePayload);

        return $this->fetchAvatars($characters, $token);
    }

    /**
     * @return array<int, string>
     */
    public function getClassIcons(): array
    {
        /** @var array<int, string> $icons */
        $icons = Cache::remember('wow_class_icons', 86400 * 30, function (): array {
            $classIds = range(1, 13);
            $token = $this->blizzardApiClient->getAccessToken();
            $baseUrl = sprintf('https://%s.api.blizzard.com', $this->region);
            $namespace = 'static-'.$this->region;

            /** @var array<string, \Illuminate\Http\Client\Response> $responses */
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($classIds, $baseUrl, $namespace, $token): void {
                foreach ($classIds as $classId) {
                    $pool->as((string) $classId)
                        ->withToken($token)
                        ->withHeaders(['Battlenet-Namespace' => $namespace])
                        ->get(sprintf('%s/data/wow/media/playable-class/%s', $baseUrl, $classId), [
                            'locale' => 'fr_FR',
                            'namespace' => $namespace,
                        ]);
                }
            });

            $icons = [];
            foreach ($responses as $key => $response) {
                if (! $response->ok()) {
                    continue;
                }

                /** @var list<array{key: string, value: string}> $assets */
                $assets = $response->json('assets') ?? [];
                foreach ($assets as $asset) {
                    if ($asset['key'] === 'icon') {
                        $icons[(int) $key] = $asset['value'];
                        break;
                    }
                }
            }

            return $icons;
        });

        return $icons;
    }

    /**
     * @return list<AccountCharacter>
     */
    private function parseCharacters(ResponsePayload $responsePayload): array
    {
        $characters = [];

        foreach ($responsePayload->objectList('wow_accounts') as $account) {
            foreach ($account->objectList('characters') as $char) {
                $charRealm = $char->optionalObject('realm');
                $charClass = $char->optionalObject('playable_class');
                $charRace = $char->optionalObject('playable_race');

                $characters[] = [
                    'name' => $char->lenientString('name') ?? '',
                    'realm' => $charRealm?->optionalString('name') ?? '',
                    'realmSlug' => $charRealm?->optionalString('slug') ?? '',
                    'level' => $char->optionalInt('level') ?? 0,
                    'classId' => $charClass?->optionalInt('id') ?? 0,
                    'className' => $charClass?->optionalString('name') ?? '',
                    'raceId' => $charRace?->optionalInt('id') ?? 0,
                    'raceName' => $charRace?->optionalString('name') ?? '',
                    'faction' => $char->optionalObject('faction')?->optionalString('name') ?? '',
                    'avatarUrl' => '',
                ];
            }
        }

        usort($characters, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $characters;
    }

    /**
     * @param  list<AccountCharacter>  $characters
     * @return list<AccountCharacter>
     */
    private function fetchAvatars(array $characters, string $token): array
    {
        $baseUrl = sprintf('https://%s.api.blizzard.com', $this->region);
        $namespace = 'profile-'.$this->region;

        /** @var array<string, \Illuminate\Http\Client\Response> $responses */
        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($characters, $baseUrl, $namespace, $token): void {
            foreach ($characters as $i => $char) {
                $realm = mb_strtolower($char['realmSlug']);
                $name = mb_strtolower($char['name']);

                $pool->as((string) $i)
                    ->withToken($token)
                    ->withHeaders(['Battlenet-Namespace' => $namespace])
                    ->get(sprintf('%s/profile/wow/character/%s/%s/character-media', $baseUrl, $realm, $name), [
                        'locale' => 'fr_FR',
                    ]);
            }
        });

        foreach ($characters as $i => &$char) {
            $key = (string) $i;
            if (! isset($responses[$key])) {
                continue;
            }

            $response = $responses[$key];
            if ($response->ok()) {
                $decoded = $response->json();
                $media = ResponsePayload::forEndpoint('character-media', is_array($decoded) ? $decoded : []);
                foreach ($media->objectList('assets') as $asset) {
                    if ($asset->optionalString('key') === 'avatar') {
                        $char['avatarUrl'] = $asset->optionalString('value') ?? '';
                        break;
                    }
                }
            }
        }

        return $characters;
    }
}
