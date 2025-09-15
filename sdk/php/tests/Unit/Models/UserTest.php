<?php

/**
 * SSO Client SDK - Unit Tests for User Model
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use TodayIsLife\SsoClient\Models\User;

/**
 * Test suite for User model
 */
class UserTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $data = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified' => true,
            'mfa_enabled' => false,
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-15T00:00:00Z',
            'email_verified_at' => '2024-01-01T12:00:00Z',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'timezone' => 'America/New_York',
            'locale' => 'en_US',
            'picture' => 'https://example.com/avatar.jpg',
        ];

        $user = User::fromArray($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $user->getId());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertTrue($user->isEmailVerified());
        $this->assertFalse($user->isMfaEnabled());
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertEquals('America/New_York', $user->getTimezone());
        $this->assertEquals('en_US', $user->getLocale());
        $this->assertEquals('https://example.com/avatar.jpg', $user->getPicture());

        // Test date parsing
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getUpdatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getEmailVerifiedAt());
    }

    public function testCreateFromArrayWithMinimalData(): void
    {
        $data = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $user = User::fromArray($data);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $user->getId());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertFalse($user->isEmailVerified()); // Default value
        $this->assertFalse($user->isMfaEnabled()); // Default value
        $this->assertNull($user->getCreatedAt());
        $this->assertNull($user->getFirstName());
        $this->assertNull($user->getTimezone());
    }

    public function testCreateFromOAuthUserInfo(): void
    {
        $data = [
            'sub' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified' => true,
            'given_name' => 'John',
            'family_name' => 'Doe',
            'locale' => 'en_US',
            'picture' => 'https://example.com/avatar.jpg',
            'updated_at' => '2024-01-15T00:00:00Z',
        ];

        $user = User::fromOAuthUserInfo($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $user->getId());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertTrue($user->isEmailVerified());
        $this->assertFalse($user->isMfaEnabled()); // Not provided in OAuth userinfo
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertEquals('en_US', $user->getLocale());
        $this->assertEquals('https://example.com/avatar.jpg', $user->getPicture());
        $this->assertNull($user->getTimezone()); // Not provided in OAuth userinfo
        $this->assertNull($user->getCreatedAt()); // Not provided in OAuth userinfo
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getUpdatedAt());
    }

    public function testToArray(): void
    {
        $originalData = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified' => true,
            'mfa_enabled' => true,
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-15T00:00:00Z',
            'email_verified_at' => '2024-01-01T12:00:00Z',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'timezone' => 'America/New_York',
            'locale' => 'en_US',
            'picture' => 'https://example.com/avatar.jpg',
        ];

        $user = User::fromArray($originalData);
        $arrayData = $user->toArray();

        $this->assertIsArray($arrayData);
        $this->assertEquals($originalData['id'], $arrayData['id']);
        $this->assertEquals($originalData['name'], $arrayData['name']);
        $this->assertEquals($originalData['email'], $arrayData['email']);
        $this->assertEquals($originalData['email_verified'], $arrayData['email_verified']);
        $this->assertEquals($originalData['mfa_enabled'], $arrayData['mfa_enabled']);
        $this->assertEquals($originalData['first_name'], $arrayData['first_name']);
        $this->assertEquals($originalData['last_name'], $arrayData['last_name']);
        $this->assertEquals($originalData['timezone'], $arrayData['timezone']);
        $this->assertEquals($originalData['locale'], $arrayData['locale']);
        $this->assertEquals($originalData['picture'], $arrayData['picture']);

        // Check date formatting
        $this->assertEquals('2024-01-01T00:00:00+00:00', $arrayData['created_at']);
        $this->assertEquals('2024-01-15T00:00:00+00:00', $arrayData['updated_at']);
        $this->assertEquals('2024-01-01T12:00:00+00:00', $arrayData['email_verified_at']);
    }

    public function testToArrayWithNullValues(): void
    {
        $data = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $user = User::fromArray($data);
        $arrayData = $user->toArray();

        $this->assertNull($arrayData['created_at']);
        $this->assertNull($arrayData['updated_at']);
        $this->assertNull($arrayData['email_verified_at']);
        $this->assertNull($arrayData['first_name']);
        $this->assertNull($arrayData['last_name']);
        $this->assertNull($arrayData['timezone']);
        $this->assertNull($arrayData['locale']);
        $this->assertNull($arrayData['picture']);
    }
}