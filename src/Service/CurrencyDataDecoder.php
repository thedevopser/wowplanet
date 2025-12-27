<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\CurrencyDataTooLargeException;
use App\Exception\InvalidCurrencyDataException;
use App\Exception\InvalidCurrencyDataHeaderException;
use Psr\Log\LoggerInterface;

final readonly class CurrencyDataDecoder
{
    private const string EXPECTED_HEADER = 'CT_BASE64_V1:';
    private const int DEFAULT_MAX_SIZE = 5242880; // 5MB

    public function __construct(
        private LoggerInterface $logger,
        private int $maxDecodedSizeBytes = self::DEFAULT_MAX_SIZE
    ) {
    }

    /**
     * @return array<mixed, mixed>
     */
    public function decodeAndParse(string $base64Input): array
    {
        try {
            $this->validateHeaderPrefix($base64Input);

            $base64Content = $this->stripHeaderPrefix($base64Input);

            $decodedJson = $this->decodeBase64($base64Content);

            $this->validateDecodedSize($decodedJson);

            $cleanedJson = $this->cleanJsonControlCharacters($decodedJson);

            $parsedData = $this->parseJson($cleanedJson);

            $this->logger->info('Currency data decoded successfully', [
                'decoded_size_bytes' => strlen($decodedJson),
            ]);

            return $parsedData;
        } catch (InvalidCurrencyDataHeaderException | InvalidCurrencyDataException | CurrencyDataTooLargeException $e) {
            $this->logger->error('Currency data decode failed', [
                'error_type' => $e::class,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function validateHeaderPrefix(string $input): void
    {
        if (!str_starts_with($input, self::EXPECTED_HEADER)) {
            if (str_contains($input, ':') && preg_match('/^([A-Z_0-9]+):/', $input, $matches)) {
                throw InvalidCurrencyDataHeaderException::invalidHeader($matches[1] . ':');
            }

            throw InvalidCurrencyDataHeaderException::missingHeader();
        }
    }

    private function stripHeaderPrefix(string $input): string
    {
        return substr($input, strlen(self::EXPECTED_HEADER));
    }

    private function decodeBase64(string $base64Content): string
    {
        $decoded = base64_decode($base64Content, true);

        if ($decoded === false) {
            throw InvalidCurrencyDataException::invalidBase64Encoding();
        }

        return $decoded;
    }

    private function validateDecodedSize(string $decoded): void
    {
        $size = strlen($decoded);

        if ($size > $this->maxDecodedSizeBytes) {
            throw CurrencyDataTooLargeException::fromSize($size, $this->maxDecodedSizeBytes);
        }
    }

    private function cleanJsonControlCharacters(string $json): string
    {
        $json = preg_replace('/"description":\s*"[^"]*(?:\\.[^"]*)*",?\s*/s', '', $json) ?? $json;

        $json = preg_replace('/,(\s*[}\]])/', '$1', $json) ?? $json;

        return $json;
    }

    /**
     * @return array<mixed, mixed>
     */
    private function parseJson(string $json): array
    {
        $data = json_decode($json, true, 512, JSON_INVALID_UTF8_IGNORE);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            $errorMsg = match ($jsonError) {
                JSON_ERROR_DEPTH => 'Profondeur maximale atteinte',
                JSON_ERROR_STATE_MISMATCH => 'JSON mal formé',
                JSON_ERROR_CTRL_CHAR => 'Caractère de contrôle inattendu',
                JSON_ERROR_SYNTAX => 'Erreur de syntaxe JSON',
                JSON_ERROR_UTF8 => 'Caractères UTF-8 invalides',
                default => 'Erreur JSON inconnue (code: ' . $jsonError . ')',
            };

            throw InvalidCurrencyDataException::invalidJsonStructure($errorMsg);
        }

        if (!is_array($data)) {
            throw InvalidCurrencyDataException::notAnArray();
        }

        return $data;
    }
}
