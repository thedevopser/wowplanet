<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy;

use App\Infrastructure\Reference\ReferenceStore;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomyUpstreamUnreachableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Frontière simplearmory.com, amont de la curation des collections.
 *
 * Elle n'est traversée que par un tirage manuel, une fois par patch : l'amont indisponible
 * n'empêche donc aucun import, qui lit l'instantané versionné. Le fichier tiré atterrit sur le
 * disque `reference`, celui du socle, inventorié.
 */
final readonly class SimpleArmoryClient
{
    /**
     * @return int Taille du document stocké, en octets.
     */
    public function fetch(CollectionEntity $collectionEntity): int
    {
        $filename = $collectionEntity->simpleArmoryFile();
        $response = Http::timeout($this->timeout())->get($this->baseUrl().'/'.$filename);

        if (! $response->successful()) {
            throw TaxonomyUpstreamUnreachableException::status($filename, $response->status());
        }

        $body = $response->body();

        if (trim($body) === '') {
            throw TaxonomyUpstreamUnreachableException::empty($filename);
        }

        Storage::disk(ReferenceStore::DISK)->put($filename, $body);

        return strlen($body);
    }

    private function baseUrl(): string
    {
        /** @var string $baseUrl */
        $baseUrl = config('services.simplearmory.base_url', 'https://simplearmory.com/data');

        return rtrim($baseUrl, '/');
    }

    private function timeout(): int
    {
        /** @var int $timeout */
        $timeout = config('services.simplearmory.timeout', 120);

        return $timeout;
    }
}
