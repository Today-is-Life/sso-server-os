<?php

/**
 * SSO Client SDK - User Model
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Models;

/**
 * User model representing SSO user data
 */
class User
{
    private string $id;
    private string $name;
    private string $email;
    private bool $emailVerified;
    private bool $mfaEnabled;
    private ?\DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $emailVerifiedAt;
    private ?string $firstName;
    private ?string $lastName;
    private ?string $timezone;
    private ?string $locale;
    private ?string $picture;

    public function __construct(
        string $id,
        string $name,
        string $email,
        bool $emailVerified = false,
        bool $mfaEnabled = false,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $emailVerifiedAt = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $timezone = null,
        ?string $locale = null,
        ?string $picture = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->emailVerified = $emailVerified;
        $this->mfaEnabled = $mfaEnabled;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->timezone = $timezone;
        $this->locale = $locale;
        $this->picture = $picture;
    }

    /**
     * Create User from API response array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            email: $data['email'],
            emailVerified: $data['email_verified'] ?? false,
            mfaEnabled: $data['mfa_enabled'] ?? false,
            createdAt: isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
            emailVerifiedAt: isset($data['email_verified_at']) ? new \DateTimeImmutable($data['email_verified_at']) : null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            timezone: $data['timezone'] ?? null,
            locale: $data['locale'] ?? null,
            picture: $data['picture'] ?? null
        );
    }

    /**
     * Create User from OAuth userinfo response
     */
    public static function fromOAuthUserInfo(array $data): self
    {
        return new self(
            id: $data['sub'],
            name: $data['name'],
            email: $data['email'],
            emailVerified: $data['email_verified'] ?? false,
            mfaEnabled: false, // Not provided in OAuth userinfo
            createdAt: null,
            updatedAt: isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
            emailVerifiedAt: null,
            firstName: $data['given_name'] ?? null,
            lastName: $data['family_name'] ?? null,
            timezone: null,
            locale: $data['locale'] ?? null,
            picture: $data['picture'] ?? null
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function isMfaEnabled(): bool
    {
        return $this->mfaEnabled;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
            'mfa_enabled' => $this->mfaEnabled,
            'created_at' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
            'email_verified_at' => $this->emailVerifiedAt?->format(\DateTimeInterface::ATOM),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'picture' => $this->picture,
        ];
    }
}