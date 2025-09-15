<?php

/**
 * SSO Client SDK - Unit Tests for SsoClient
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Tests\Unit\Client;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use TodayIsLife\SsoClient\Client\SsoClient;
use TodayIsLife\SsoClient\Exceptions\AuthenticationException;
use TodayIsLife\SsoClient\Exceptions\RateLimitException;
use TodayIsLife\SsoClient\Exceptions\ValidationException;
use TodayIsLife\SsoClient\Models\AuthResult;
use TodayIsLife\SsoClient\Models\TwoFactorResult;
use TodayIsLife\SsoClient\Models\User;

/**
 * Test suite for SsoClient
 */
class SsoClientTest extends TestCase
{
    private SsoClient $ssoClient;
    private MockHandler $mockHandler;
    private TestLogger $logger;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $this->logger = new TestLogger();

        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new HttpClient(['handler' => $handlerStack]);

        // Use reflection to inject our mock HTTP client
        $this->ssoClient = new SsoClient(
            baseUrl: 'https://sso.test.com',
            accessToken: null,
            options: ['debug' => true],
            logger: $this->logger
        );

        $reflection = new \ReflectionClass($this->ssoClient);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->ssoClient, $httpClient);
    }

    public function testSuccessfulLogin(): void
    {
        $responseData = [
            'success' => true,
            'user' => [
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'email_verified' => true,
                'mfa_enabled' => false,
            ],
            'access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ssoClient->login('john@example.com', 'password123');

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(AuthResult::class, $result);
        $this->assertInstanceOf(User::class, $result->getUser());
        $this->assertEquals('John Doe', $result->getUser()->getName());
        $this->assertEquals('eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...', $result->getAccessToken());
        $this->assertEquals(3600, $result->getExpiresIn());
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $errorResponse = [
            'error' => 'Unauthorized',
            'message' => 'Invalid credentials',
        ];

        $this->mockHandler->append(
            new ClientException(
                'Unauthorized',
                new Request('POST', '/auth/login'),
                new Response(401, ['Content-Type' => 'application/json'], json_encode($errorResponse))
            )
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->ssoClient->login('john@example.com', 'wrong-password');
    }

    public function testRegistrationValidationError(): void
    {
        $errorResponse = [
            'error' => 'Validation failed',
            'message' => 'The given data was invalid',
            'errors' => [
                'email' => ['The email field is required'],
                'password' => ['The password must be at least 8 characters'],
            ],
        ];

        $this->mockHandler->append(
            new ClientException(
                'Bad Request',
                new Request('POST', '/auth/register'),
                new Response(400, ['Content-Type' => 'application/json'], json_encode($errorResponse))
            )
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The given data was invalid');

        $this->ssoClient->register('John', 'Doe', '', 'short', 'short');
    }

    public function testRateLimitHandling(): void
    {
        $errorResponse = [
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests from your IP address',
            'retry_after' => 60,
        ];

        $this->mockHandler->append(
            new ClientException(
                'Too Many Requests',
                new Request('POST', '/auth/login'),
                new Response(429, [
                    'Content-Type' => 'application/json',
                    'Retry-After' => '60',
                ], json_encode($errorResponse))
            )
        );

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Too many requests from your IP address');

        try {
            $this->ssoClient->login('john@example.com', 'password123');
        } catch (RateLimitException $e) {
            $this->assertEquals(60, $e->getRetryAfter());
            $this->assertInstanceOf(\DateTimeImmutable::class, $e->getRetryAfterDateTime());
            throw $e;
        }
    }

    public function testEnable2FA(): void
    {
        $responseData = [
            'success' => true,
            'secret' => 'JBSWY3DPEHPK3PXP',
            'qr_code' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...',
            'recovery_codes' => [
                'AAAA-BBBB-CCCC',
                'DDDD-EEEE-FFFF',
                'GGGG-HHHH-IIII',
            ],
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        // Set access token for authenticated request
        $this->ssoClient->setAccessToken('test-token');

        $result = $this->ssoClient->enable2FA();

        $this->assertInstanceOf(TwoFactorResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('JBSWY3DPEHPK3PXP', $result->getSecret());
        $this->assertTrue($result->hasQrCode());
        $this->assertTrue($result->hasRecoveryCodes());
        $this->assertCount(3, $result->getRecoveryCodes());
        $this->assertStringStartsWith('data:image/png;base64,', $result->getQrCodeDataUri());
    }

    public function testGetCurrentUser(): void
    {
        $responseData = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => '2024-01-01T00:00:00Z',
            'mfa_enabled' => true,
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-15T00:00:00Z',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'timezone' => 'America/New_York',
            'locale' => 'en_US',
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        // Set access token for authenticated request
        $this->ssoClient->setAccessToken('test-token');

        $user = $this->ssoClient->getCurrentUser();

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertTrue($user->isEmailVerified());
        $this->assertTrue($user->isMfaEnabled());
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertEquals('America/New_York', $user->getTimezone());
        $this->assertEquals('en_US', $user->getLocale());
    }

    public function testGeneratePkce(): void
    {
        $pkce = SsoClient::generatePkce();

        $this->assertIsArray($pkce);
        $this->assertArrayHasKey('code_verifier', $pkce);
        $this->assertArrayHasKey('code_challenge', $pkce);
        $this->assertIsString($pkce['code_verifier']);
        $this->assertIsString($pkce['code_challenge']);
        $this->assertNotEmpty($pkce['code_verifier']);
        $this->assertNotEmpty($pkce['code_challenge']);

        // Verify code_verifier is base64url encoded
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $pkce['code_verifier']);

        // Verify code_challenge is base64url encoded
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $pkce['code_challenge']);
    }

    public function testGenerateState(): void
    {
        $state = SsoClient::generateState();

        $this->assertIsString($state);
        $this->assertEquals(32, strlen($state)); // 16 bytes * 2 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $state);
    }

    public function testGetAuthorizationUrl(): void
    {
        $pkce = SsoClient::generatePkce();
        $state = SsoClient::generateState();

        $url = $this->ssoClient->getAuthorizationUrl(
            clientId: 'test-client-id',
            redirectUri: 'https://app.test.com/callback',
            responseType: 'code',
            scopes: ['openid', 'email', 'profile'],
            state: $state,
            codeChallenge: $pkce['code_challenge'],
            codeChallengeMethod: 'S256'
        );

        $this->assertIsString($url);
        $this->assertStringStartsWith('https://sso.test.com/api/v1/oauth/authorize?', $url);

        // Parse URL to check query parameters
        $parsedUrl = parse_url($url);
        parse_str($parsedUrl['query'], $queryParams);

        $this->assertEquals('test-client-id', $queryParams['client_id']);
        $this->assertEquals('https://app.test.com/callback', $queryParams['redirect_uri']);
        $this->assertEquals('code', $queryParams['response_type']);
        $this->assertEquals('openid email profile', $queryParams['scope']);
        $this->assertEquals($state, $queryParams['state']);
        $this->assertEquals($pkce['code_challenge'], $queryParams['code_challenge']);
        $this->assertEquals('S256', $queryParams['code_challenge_method']);
    }

    public function testLogout(): void
    {
        $responseData = [
            'success' => true,
            'message' => 'Successfully logged out',
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        // Set access token for authenticated request
        $this->ssoClient->setAccessToken('test-token');

        $result = $this->ssoClient->logout();

        $this->assertTrue($result);
        $this->assertNull($this->ssoClient->getAccessToken());
    }

    public function testRequestPasswordReset(): void
    {
        $responseData = [
            'success' => true,
            'message' => 'Password reset link sent to your email',
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ssoClient->requestPasswordReset('john@example.com');

        $this->assertTrue($result);
    }

    public function testRequestMagicLink(): void
    {
        $responseData = [
            'success' => true,
            'message' => 'Magic link sent to your email',
        ];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $result = $this->ssoClient->requestMagicLink('john@example.com');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('Magic link sent to your email', $result->getMessage());
    }

    public function testAccessTokenManagement(): void
    {
        $this->assertFalse($this->ssoClient->hasAccessToken());
        $this->assertNull($this->ssoClient->getAccessToken());

        $token = 'test-access-token';
        $this->ssoClient->setAccessToken($token);

        $this->assertTrue($this->ssoClient->hasAccessToken());
        $this->assertEquals($token, $this->ssoClient->getAccessToken());
    }

    public function testGetBaseUrl(): void
    {
        $this->assertEquals('https://sso.test.com/api/v1', $this->ssoClient->getBaseUrl());
    }

    public function testLoggingIntegration(): void
    {
        $responseData = ['success' => true];

        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );

        $this->ssoClient->requestPasswordReset('test@example.com');

        // Check that debug logs were written
        $this->assertTrue($this->logger->hasDebugRecords());

        $records = $this->logger->records;
        $requestLog = array_filter($records, fn($record) => str_contains($record['message'], 'SSO API Request'));

        $this->assertNotEmpty($requestLog);
    }
}