<?php

declare(strict_types=1);

namespace App\Service;

interface BlizzardApiServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function fetchUserProfile(string $accessToken): array;

    /**
     * @return array<string, mixed>
     */
    public function fetchCharacterReputations(
        string $accessToken,
        string $realmSlug,
        string $characterName
    ): array;

    /**
     * @param array<string, mixed> $reputations
     * @return array<string, mixed>|null
     */
    public function findSpecificReputation(array $reputations, string $targetFaction): ?array;

    /**
     * @param array<string, mixed> $reputation
     */
    public function calculateReputationValue(array $reputation): int;

    /**
     * @return array<string, mixed>
     */
    public function fetchCharacterCurrencies(
        string $accessToken,
        string $realmSlug,
        string $characterName
    ): array;

    /**
     * @param array<string, mixed> $currencies
     * @return array<string, mixed>|null
     */
    public function findSpecificCurrency(array $currencies, string $targetCurrencyName): ?array;

    /**
     * @param array<string, mixed> $currencyEntry
     */
    public function extractCurrencyQuantity(array $currencyEntry): int;
}
