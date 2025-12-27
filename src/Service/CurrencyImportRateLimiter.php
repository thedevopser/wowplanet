<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class CurrencyImportRateLimiter
{
    private const string SESSION_KEY = 'currency_import_rate_limit';
    private const int DEFAULT_MAX_ATTEMPTS = 10;
    private const int DEFAULT_WINDOW_SECONDS = 3600;

    public function __construct(
        private LoggerInterface $logger,
        private int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private int $windowSeconds = self::DEFAULT_WINDOW_SECONDS
    ) {
    }

    public function canAttemptImport(SessionInterface $session): bool
    {
        $timestamps = $this->getAttemptTimestamps($session);

        $validAttempts = $this->filterValidAttempts($timestamps, $this->windowSeconds);

        $canAttempt = count($validAttempts) < $this->maxAttempts;

        if (!$canAttempt) {
            $this->logger->warning('Currency import rate limit check: limit reached', [
                'attempts_count' => count($validAttempts),
                'max_attempts' => $this->maxAttempts,
            ]);
        }

        return $canAttempt;
    }

    public function recordImportAttempt(SessionInterface $session): void
    {
        $timestamps = $this->getAttemptTimestamps($session);

        $validAttempts = $this->filterValidAttempts($timestamps, $this->windowSeconds);

        $validAttempts[] = time();

        $this->saveAttemptTimestamps($session, $validAttempts);

        $this->logger->info('Currency import attempt recorded', [
            'total_attempts' => count($validAttempts),
            'remaining_attempts' => $this->maxAttempts - count($validAttempts),
        ]);
    }

    public function getRemainingAttempts(SessionInterface $session): int
    {
        $timestamps = $this->getAttemptTimestamps($session);

        $validAttempts = $this->filterValidAttempts($timestamps, $this->windowSeconds);

        $remaining = $this->maxAttempts - count($validAttempts);

        return max(0, $remaining);
    }

    public function getTimeUntilReset(SessionInterface $session): int
    {
        $timestamps = $this->getAttemptTimestamps($session);

        if (empty($timestamps)) {
            return 0;
        }

        $oldestTimestamp = min($timestamps);

        $resetTime = $oldestTimestamp + $this->windowSeconds;

        $timeUntilReset = $resetTime - time();

        return max(0, $timeUntilReset);
    }

    /**
     * @return array<int, int>
     */
    private function getAttemptTimestamps(SessionInterface $session): array
    {
        $data = $session->get(self::SESSION_KEY);

        if (!is_array($data)) {
            return [];
        }

        if (!isset($data['timestamps']) || !is_array($data['timestamps'])) {
            return [];
        }

        /** @var array<int, int> */
        return array_filter($data['timestamps'], is_int(...));
    }

    /**
     * @param array<int, int> $timestamps
     * @return array<int, int>
     */
    private function filterValidAttempts(array $timestamps, int $windowSeconds): array
    {
        $currentTime = time();

        $cutoffTime = $currentTime - $windowSeconds;

        return array_filter(
            $timestamps,
            fn (int $timestamp): bool => $timestamp > $cutoffTime
        );
    }

    /**
     * @param array<int, int> $timestamps
     */
    private function saveAttemptTimestamps(SessionInterface $session, array $timestamps): void
    {
        $session->set(self::SESSION_KEY, [
            'timestamps' => array_values($timestamps),
            'window_seconds' => $this->windowSeconds,
            'max_attempts' => $this->maxAttempts,
        ]);
    }
}
