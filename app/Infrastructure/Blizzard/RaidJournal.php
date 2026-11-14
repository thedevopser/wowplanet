<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Infrastructure\Blizzard\Responses\Profile\JournalInstanceResponse;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Le journal des raids, lu dans les données statiques `journal-instance` : le nom français d'un
 * raid et de tous ses boss, que les réponses de profil ne localisent pas et ne listent qu'en
 * partie.
 *
 * @phpstan-type RaidJournalEntry array{name: string, encounters: array<int, string>}
 */
class RaidJournal
{
    private const string CACHE_PREFIX = 'raid_journal_instance:'; // @pest-mutate-ignore

    private const int CACHE_TTL_S = 604800; // @pest-mutate-ignore

    public function __construct(private readonly BlizzardApiClient $blizzardApiClient) {}

    /**
     * Un raid dont les données statiques ne se lisent pas est laissé de côté.
     *
     * @param  list<int>  $instanceIds
     * @return array<int, RaidJournalEntry>
     */
    public function forInstances(array $instanceIds): array
    {
        $journal = [];

        foreach ($instanceIds as $instanceId) {
            try {
                $journalInstanceResponse = $this->journalInstance($instanceId);
            } catch (Throwable $throwable) {
                Log::debug('Raid journal-instance fetch failed: '.$throwable->getMessage());

                continue;
            }

            $journal[$instanceId] = ['name' => $journalInstanceResponse->name ?? '', 'encounters' => $journalInstanceResponse->encounterNames];
        }

        return $journal;
    }

    /**
     * Le cache garde la réponse décodée, pas l'objet : son contenu survit ainsi à un changement de classe.
     */
    private function journalInstance(int $instanceId): JournalInstanceResponse
    {
        $endpoint = 'data/wow/journal-instance/'.$instanceId;

        $decoded = Cache::remember(
            self::CACHE_PREFIX.$instanceId,
            self::CACHE_TTL_S,
            fn (): array => $this->blizzardApiClient->get($endpoint, ['namespace' => 'static-'.$this->blizzardApiClient->getRegion()]),
        );

        return JournalInstanceResponse::fromPayload(ResponsePayload::forEndpoint($endpoint, $decoded));
    }
}
