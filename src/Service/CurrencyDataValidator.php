<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InvalidCurrencyDataException;
use Psr\Log\LoggerInterface;

final readonly class CurrencyDataValidator
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param mixed $decodedData
     * @return array<int, array<string, mixed>>
     */
    public function validateAndExtractCharacters(mixed $decodedData): array
    {
        if (!is_array($decodedData)) {
            throw InvalidCurrencyDataException::notAnArray();
        }

        if (empty($decodedData)) {
            throw InvalidCurrencyDataException::emptyCharacterData();
        }

        $characters = $this->extractCharactersArray($decodedData);

        $this->validateCharacterStructure($characters);

        $this->logger->info('Currency data validated successfully', [
            'characters_count' => count($characters),
        ]);

        return $characters;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function extractCharactersArray(array $data): array
    {
        if (isset($data['characters']) && is_array($data['characters'])) {
            /** @var array<int, array<string, mixed>> */
            return $data['characters'];
        }

        if ($this->isValidCharactersArray($data)) {
            /** @var array<int, array<string, mixed>> */
            return $data;
        }

        throw InvalidCurrencyDataException::noCharacterData();
    }

    /**
     * @param array<int, array<string, mixed>> $characters
     */
    private function validateCharacterStructure(array $characters): void
    {
        if (empty($characters)) {
            throw InvalidCurrencyDataException::emptyCharacterData();
        }

        foreach ($characters as $character) {
            if (!isset($character['name'])) {
                throw InvalidCurrencyDataException::invalidJsonStructure('Le champ "name" est requis');
            }

            if (!isset($character['currencies'])) {
                throw InvalidCurrencyDataException::invalidJsonStructure('Le champ "currencies" est requis');
            }
        }
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function isValidCharactersArray(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $firstKey = array_key_first($data);

        if (!is_int($firstKey)) {
            return false;
        }

        $firstElement = $data[$firstKey];

        if (!is_array($firstElement)) {
            return false;
        }

        return isset($firstElement['name']) && isset($firstElement['currencies']);
    }
}
