<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\CurrencyImportRateLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CurrencyImportRateLimiterTest extends TestCase
{
    private LoggerInterface $logger;
    private CurrencyImportRateLimiter $rateLimiter;
    private Session $session;

    protected function setUp(): void
    {
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->rateLimiter = new CurrencyImportRateLimiter($this->logger);
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testAllowFirstAttempt(): void
    {
        $canAttempt = $this->rateLimiter->canAttemptImport($this->session);

        $this->assertTrue($canAttempt);
    }

    public function testAllowWithinLimit(): void
    {
        for ($i = 0; $i < 9; $i++) {
            $this->rateLimiter->recordImportAttempt($this->session);
        }

        $canAttempt = $this->rateLimiter->canAttemptImport($this->session);

        $this->assertTrue($canAttempt);
    }

    public function testBlockAfterLimit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->rateLimiter->recordImportAttempt($this->session);
        }

        $canAttempt = $this->rateLimiter->canAttemptImport($this->session);

        $this->assertFalse($canAttempt);
    }

    public function testResetAfterWindow(): void
    {
        $customRateLimiter = new CurrencyImportRateLimiter($this->logger, 10, 1);

        for ($i = 0; $i < 10; $i++) {
            $customRateLimiter->recordImportAttempt($this->session);
        }

        $this->assertFalse($customRateLimiter->canAttemptImport($this->session));

        sleep(2);

        $this->assertTrue($customRateLimiter->canAttemptImport($this->session));
    }

    public function testGetRemainingAttempts(): void
    {
        $this->rateLimiter->recordImportAttempt($this->session);
        $this->rateLimiter->recordImportAttempt($this->session);
        $this->rateLimiter->recordImportAttempt($this->session);

        $remaining = $this->rateLimiter->getRemainingAttempts($this->session);

        $this->assertEquals(7, $remaining);
    }

    public function testGetTimeUntilReset(): void
    {
        $this->rateLimiter->recordImportAttempt($this->session);

        $timeUntilReset = $this->rateLimiter->getTimeUntilReset($this->session);

        $this->assertGreaterThan(0, $timeUntilReset);
        $this->assertLessThanOrEqual(3600, $timeUntilReset);
    }

    public function testConcurrentRequests(): void
    {
        for ($i = 0; $i < 15; $i++) {
            if ($this->rateLimiter->canAttemptImport($this->session)) {
                $this->rateLimiter->recordImportAttempt($this->session);
            }
        }

        $remaining = $this->rateLimiter->getRemainingAttempts($this->session);

        $this->assertEquals(0, $remaining);
    }

    public function testDifferentSessions(): void
    {
        $session1 = new Session(new MockArraySessionStorage());
        $session2 = new Session(new MockArraySessionStorage());

        for ($i = 0; $i < 10; $i++) {
            $this->rateLimiter->recordImportAttempt($session1);
        }

        $this->assertFalse($this->rateLimiter->canAttemptImport($session1));
        $this->assertTrue($this->rateLimiter->canAttemptImport($session2));
    }
}
