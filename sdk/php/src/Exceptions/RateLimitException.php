<?php

/**
 * SSO Client SDK - Rate Limit Exception
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Exceptions;

/**
 * Exception thrown when API rate limit is exceeded
 */
class RateLimitException extends SsoClientException
{
    private ?int $retryAfter;

    public function __construct(
        string $message = 'Rate limit exceeded',
        ?int $retryAfter = null,
        int $code = 429,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->retryAfter = $retryAfter;
    }

    /**
     * Get retry after seconds
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Get retry after as DateTimeImmutable
     */
    public function getRetryAfterDateTime(): ?\DateTimeImmutable
    {
        if ($this->retryAfter === null) {
            return null;
        }

        return new \DateTimeImmutable('+' . $this->retryAfter . ' seconds');
    }

    /**
     * Check if retry time has passed
     */
    public function canRetryNow(?\DateTimeInterface $now = null): bool
    {
        if ($this->retryAfter === null) {
            return true;
        }

        $retryAt = $this->getRetryAfterDateTime();
        if ($retryAt === null) {
            return true;
        }

        $now = $now ?? new \DateTimeImmutable();
        return $now >= $retryAt;
    }

    /**
     * Get seconds until retry is allowed
     */
    public function getSecondsUntilRetry(?\DateTimeInterface $now = null): int
    {
        if ($this->retryAfter === null) {
            return 0;
        }

        $retryAt = $this->getRetryAfterDateTime();
        if ($retryAt === null) {
            return 0;
        }

        $now = $now ?? new \DateTimeImmutable();
        $diff = $retryAt->getTimestamp() - $now->getTimestamp();

        return max(0, $diff);
    }
}