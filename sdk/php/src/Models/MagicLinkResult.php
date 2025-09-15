<?php

/**
 * SSO Client SDK - Magic Link Result Model
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Models;

/**
 * Magic link authentication result model
 */
class MagicLinkResult
{
    private bool $success;
    private string $message;

    public function __construct(bool $success, string $message)
    {
        $this->success = $success;
        $this->message = $message;
    }

    /**
     * Create MagicLinkResult from API response array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'] ?? true,
            message: $data['message'] ?? 'Magic link sent'
        );
    }

    // Getters
    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
        ];
    }
}