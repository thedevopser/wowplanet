<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Exception\InvalidCurrencyDataException;
use App\Service\CurrencyDataValidator;
use App\Tests\Fixtures\CurrencyDataFixtures;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CurrencyDataValidatorTest extends TestCase
{
    private LoggerInterface $logger;
    private CurrencyDataValidator $validator;

    protected function setUp(): void
    {
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->validator = new CurrencyDataValidator($this->logger);
    }

    public function testValidCurrencyDataArray(): void
    {
        $data = CurrencyDataFixtures::validJsonData();

        $result = $this->validator->validateAndExtractCharacters($data);

        $this->assertCount(2, $result);
        $this->assertEquals('Prøtactinium', $result[0]['name']);
    }

    public function testValidCurrencyDataWithCharactersKey(): void
    {
        $data = CurrencyDataFixtures::validJsonData();

        $result = $this->validator->validateAndExtractCharacters($data);

        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('currencies', $result[0]);
    }

    public function testRejectNonArrayData(): void
    {
        $this->expectException(InvalidCurrencyDataException::class);
        $this->expectExceptionMessage('pas un tableau');

        $this->validator->validateAndExtractCharacters('not an array');
    }

    public function testRejectEmptyArray(): void
    {
        $this->expectException(InvalidCurrencyDataException::class);
        $this->expectExceptionMessage('vide');

        $this->validator->validateAndExtractCharacters([]);
    }

    public function testRejectInvalidCharacterStructure(): void
    {
        $this->expectException(InvalidCurrencyDataException::class);

        $invalidData = ['characters' => [['invalid' => 'structure']]];

        $this->validator->validateAndExtractCharacters($invalidData);
    }

    public function testExtractCharactersFromTopLevel(): void
    {
        $data = CurrencyDataFixtures::validJsonDataFlat();

        $result = $this->validator->validateAndExtractCharacters($data);

        $this->assertCount(1, $result);
        $this->assertEquals('Character1', $result[0]['name']);
    }

    public function testExtractCharactersFromNestedKey(): void
    {
        $data = CurrencyDataFixtures::validJsonData();

        $result = $this->validator->validateAndExtractCharacters($data);

        $this->assertCount(2, $result);
    }
}
