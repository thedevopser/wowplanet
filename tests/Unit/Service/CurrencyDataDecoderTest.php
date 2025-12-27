<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Exception\CurrencyDataTooLargeException;
use App\Exception\InvalidCurrencyDataException;
use App\Exception\InvalidCurrencyDataHeaderException;
use App\Service\CurrencyDataDecoder;
use App\Tests\Fixtures\CurrencyDataFixtures;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CurrencyDataDecoderTest extends TestCase
{
    private LoggerInterface $logger;
    private CurrencyDataDecoder $decoder;

    protected function setUp(): void
    {
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->decoder = new CurrencyDataDecoder($this->logger);
    }

    public function testDecodeValidBase64WithHeader(): void
    {
        $input = CurrencyDataFixtures::validBase64WithHeader();

        $result = $this->decoder->decodeAndParse($input);

        $this->assertArrayHasKey('characters', $result);
        $this->assertIsArray($result['characters']);
        $this->assertCount(2, $result['characters']);
        $this->assertIsArray($result['characters'][0]);
        $this->assertArrayHasKey('name', $result['characters'][0]);
        $this->assertEquals('Prøtactinium', $result['characters'][0]['name']);
    }

    public function testRejectMissingHeader(): void
    {
        $this->expectException(InvalidCurrencyDataHeaderException::class);
        $this->expectExceptionMessage('Format invalide: le header CT_BASE64_V1: est manquant');

        $input = CurrencyDataFixtures::plainBase64NoHeader();

        $this->decoder->decodeAndParse($input);
    }

    public function testRejectInvalidHeader(): void
    {
        $this->expectException(InvalidCurrencyDataHeaderException::class);
        $this->expectExceptionMessage('header "CT_BASE64_V2:" non supporté');

        $input = CurrencyDataFixtures::invalidHeaderBase64();

        $this->decoder->decodeAndParse($input);
    }

    public function testRejectTooLargeData(): void
    {
        $this->expectException(CurrencyDataTooLargeException::class);
        $this->expectExceptionMessage('Données trop volumineuses');

        $input = CurrencyDataFixtures::largeDataExceeding5MB();

        $this->decoder->decodeAndParse($input);
    }

    public function testDecodeAndCleanControlCharacters(): void
    {
        $input = CurrencyDataFixtures::jsonWithControlCharacters();

        $result = $this->decoder->decodeAndParse($input);

        $this->assertArrayHasKey('characters', $result);
    }

    public function testInvalidBase64Encoding(): void
    {
        $this->expectException(InvalidCurrencyDataException::class);
        $this->expectExceptionMessage('Erreur de décodage base64');

        $input = CurrencyDataFixtures::corruptedBase64();

        $this->decoder->decodeAndParse($input);
    }

    public function testInvalidJsonAfterDecode(): void
    {
        $this->expectException(InvalidCurrencyDataException::class);
        $this->expectExceptionMessage('JSON invalide');

        $input = CurrencyDataFixtures::invalidJsonBase64();

        $this->decoder->decodeAndParse($input);
    }

    public function testLoggingOnSuccess(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
            ->method('info')
            ->with(
                'Currency data decoded successfully',
                $this->callback(fn(array $context): bool => isset($context['decoded_size_bytes']))
            );

        $decoder = new CurrencyDataDecoder($loggerMock);
        $input = CurrencyDataFixtures::validBase64WithHeader();

        $decoder->decodeAndParse($input);
    }

    public function testLoggingOnFailure(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Currency data decode failed'),
                $this->callback(fn(array $context): bool => isset($context['error_type']))
            );

        $decoder = new CurrencyDataDecoder($loggerMock);
        $input = CurrencyDataFixtures::plainBase64NoHeader();

        try {
            $decoder->decodeAndParse($input);
        } catch (InvalidCurrencyDataHeaderException) {
            // Expected
        }
    }
}
