<?php

/**
 * SSO Client SDK - Authentication Result Model
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Models;

/**
 * Authentication result model
 */
class AuthResult
{
    private bool $success;
    private ?string $message;
    private ?User $user;
    private ?string $accessToken;
    private ?string $tokenType;
    private ?int $expiresIn;

    public function __construct(
        bool $success,
        ?string $message = null,
        ?User $user = null,
        ?string $accessToken = null,
        ?string $tokenType = null,
        ?int $expiresIn = null
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->user = $user;
        $this->accessToken = $accessToken;
        $this->tokenType = $tokenType;
        $this->expiresIn = $expiresIn;
    }

    /**
     * Create AuthResult from API response array
     */
    public static function fromArray(array $data): self
    {
        $user = null;
        if (isset($data['user'])) {
            $user = User::fromArray($data['user']);
        }

        return new self(
            success: $data['success'] ?? true,
            message: $data['message'] ?? null,
            user: $user,
            accessToken: $data['access_token'] ?? null,
            tokenType: $data['token_type'] ?? 'Bearer',
            expiresIn: $data['expires_in'] ?? null
        );
    }

    // Getters
    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getTokenType(): ?string
    {
        return $this->tokenType;
    }

    public function getExpiresIn(): ?int
    {
        return $this->expiresIn;
    }

    /**
     * Get full authorization header value
     */
    public function getAuthorizationHeader(): ?string
    {
        if (!$this->accessToken) {
            return null;
        }

        return ($this->tokenType ?? 'Bearer') . ' ' . $this->accessToken;
    }

    /**
     * Check if authentication includes access token
     */
    public function hasAccessToken(): bool
    {
        return !empty($this->accessToken);
    }

    /**
     * Calculate token expiration timestamp
     */
    public function getExpiresAt(): ?\DateTimeImmutable
    {
        if (!$this->expiresIn) {
            return null;
        }

        return new \DateTimeImmutable('+' . $this->expiresIn . ' seconds');
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'user' => $this->user?->toArray(),
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
        ];
    }
}