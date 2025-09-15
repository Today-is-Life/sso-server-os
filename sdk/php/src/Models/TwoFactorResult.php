<?php

/**
 * SSO Client SDK - Two-Factor Authentication Result Model
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Models;

/**
 * Two-factor authentication result model
 */
class TwoFactorResult
{
    private bool $success;
    private ?string $message;
    private ?string $secret;
    private ?string $qrCode;
    private array $recoveryCodes;

    public function __construct(
        bool $success,
        ?string $message = null,
        ?string $secret = null,
        ?string $qrCode = null,
        array $recoveryCodes = []
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->secret = $secret;
        $this->qrCode = $qrCode;
        $this->recoveryCodes = $recoveryCodes;
    }

    /**
     * Create TwoFactorResult from API response array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'] ?? true,
            message: $data['message'] ?? null,
            secret: $data['secret'] ?? null,
            qrCode: $data['qr_code'] ?? null,
            recoveryCodes: $data['recovery_codes'] ?? []
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

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function getRecoveryCodes(): array
    {
        return $this->recoveryCodes;
    }

    /**
     * Check if result includes QR code
     */
    public function hasQrCode(): bool
    {
        return !empty($this->qrCode);
    }

    /**
     * Check if result includes recovery codes
     */
    public function hasRecoveryCodes(): bool
    {
        return !empty($this->recoveryCodes);
    }

    /**
     * Get QR code as data URI for display
     */
    public function getQrCodeDataUri(): ?string
    {
        if (!$this->qrCode) {
            return null;
        }

        // If already a data URI, return as-is
        if (str_starts_with($this->qrCode, 'data:')) {
            return $this->qrCode;
        }

        // Otherwise, assume it's base64 PNG data
        return 'data:image/png;base64,' . $this->qrCode;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'secret' => $this->secret,
            'qr_code' => $this->qrCode,
            'recovery_codes' => $this->recoveryCodes,
        ];
    }
}