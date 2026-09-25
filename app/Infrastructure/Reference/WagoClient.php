<?php

declare(strict_types=1);

namespace App\Infrastructure\Reference;

use App\Infrastructure\Reference\Exceptions\BuildUnavailableException;
use App\Infrastructure\Reference\Exceptions\DownloadFailedException;
use Illuminate\Support\Facades\Http;

/**
 * Frontière wago.tools, seule source des tables DB2.
 *
 * Le produit est épinglé sur le build LIVE : sans lui, wago sert son dernier build tous
 * produits confondus, souvent un PTR dont la localisation française est incomplète.
 */
final class WagoClient
{
    public function liveBuild(): string
    {
        $response = Http::timeout($this->timeout())->get($this->baseUrl().'/api/builds');

        if (! $response->successful()) {
            throw BuildUnavailableException::unreadable();
        }

        $version = $response->json($this->product().'.0.version');

        if (! is_string($version) || $version === '') {
            throw BuildUnavailableException::unreadable();
        }

        return $version;
    }

    public function fetch(ReferenceTable $referenceTable): string
    {
        $query = ['product' => $this->product()];

        if ($referenceTable->locale !== null) {
            $query['locale'] = $referenceTable->locale;
        }

        $response = Http::timeout($this->timeout())
            ->get(sprintf('%s/db2/%s/csv', $this->baseUrl(), $referenceTable->source), $query);

        if (! $response->successful()) {
            throw DownloadFailedException::status($referenceTable->source, $response->status());
        }

        $body = $response->body();

        if (trim($body) === '') {
            throw DownloadFailedException::empty($referenceTable->source);
        }

        return $body;
    }

    private function baseUrl(): string
    {
        /** @var string $baseUrl */
        $baseUrl = config('services.wago.base_url', 'https://wago.tools');

        return rtrim($baseUrl, '/');
    }

    private function product(): string
    {
        /** @var string $product */
        $product = config('services.wago.product', 'wow');

        return $product;
    }

    private function timeout(): int
    {
        /** @var int $timeout */
        $timeout = config('services.wago.timeout', 120);

        return $timeout;
    }
}
