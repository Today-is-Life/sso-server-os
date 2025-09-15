<?php

/**
 * SSO Client SDK - OAuth Tokens Model
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Models;

/**
 * OAuth tokens model
 */
class OAuthTokens
{
    private string $accessToken;
    private string $tokenType;
    private int $expiresIn;
    private ?string $refreshToken;
    private ?string $scope;

    public function __construct(
        string $accessToken,
        string $tokenType,
        int $expiresIn,
        ?string $refreshToken = null,
        ?string $scope = null
    ) {
        $this->accessToken = $accessToken;
        $this->tokenType = $tokenType;
        $this->expiresIn = $expiresIn;
        $this->refreshToken = $refreshToken;
        $this->scope = $scope;
    }

    /**
     * Create OAuthTokens from API response array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            tokenType: $data['token_type'] ?? 'Bearer',
            expiresIn: $data['expires_in'],
            refreshToken: $data['refresh_token'] ?? null,
            scope: $data['scope'] ?? null
        );
    }

    // Getters
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    /**
     * Get scopes as array
     */
    public function getScopeArray(): array
    {
        if (!$this->scope) {
            return [];
        }

        return explode(' ', $this->scope);
    }

    /**
     * Get full authorization header value
     */
    public function getAuthorizationHeader(): string
    {
        return $this->tokenType . ' ' . $this->accessToken;
    }

    /**
     * Calculate token expiration timestamp
     */
    public function getExpiresAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('+' . $this->expiresIn . ' seconds');
    }

    /**
     * Check if token has expired
     */
    public function hasExpired(\DateTimeInterface $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();
        return $now >= $this->getExpiresAt();
    }

    /**
     * Check if tokens include refresh token
     */
    public function hasRefreshToken(): bool
    {
        return !empty($this->refreshToken);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
            'refresh_token' => $this->refreshToken,
            'scope' => $this->scope,
        ];
    }
}