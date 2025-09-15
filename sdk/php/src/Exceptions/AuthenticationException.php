<?php

/**
 * SSO Client SDK - Authentication Exception
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Exceptions;

/**
 * Exception thrown when authentication fails
 */
class AuthenticationException extends SsoClientException
{
    /**
     * Create exception for invalid credentials
     */
    public static function invalidCredentials(string $message = 'Invalid credentials'): self
    {
        return new self($message, 401);
    }

    /**
     * Create exception for missing access token
     */
    public static function missingAccessToken(string $message = 'Access token is required'): self
    {
        return new self($message, 401);
    }

    /**
     * Create exception for expired token
     */
    public static function expiredToken(string $message = 'Access token has expired'): self
    {
        return new self($message, 401);
    }

    /**
     * Create exception for invalid token
     */
    public static function invalidToken(string $message = 'Invalid access token'): self
    {
        return new self($message, 401);
    }
}