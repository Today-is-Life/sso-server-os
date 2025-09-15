<?php

/**
 * SSO Client SDK - Main Client Class
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

declare(strict_types=1);

namespace TodayIsLife\SsoClient\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TodayIsLife\SsoClient\Exceptions\AuthenticationException;
use TodayIsLife\SsoClient\Exceptions\RateLimitException;
use TodayIsLife\SsoClient\Exceptions\SsoClientException;
use TodayIsLife\SsoClient\Exceptions\ValidationException;
use TodayIsLife\SsoClient\Http\RequestBuilder;
use TodayIsLife\SsoClient\Http\ResponseHandler;
use TodayIsLife\SsoClient\Models\AuthResult;
use TodayIsLife\SsoClient\Models\MagicLinkResult;
use TodayIsLife\SsoClient\Models\OAuthTokens;
use TodayIsLife\SsoClient\Models\TwoFactorResult;
use TodayIsLife\SsoClient\Models\User;

/**
 * Main SSO Client for interacting with SSO Server API
 */
class SsoClient
{
    private ClientInterface $httpClient;
    private RequestBuilder $requestBuilder;
    private ResponseHandler $responseHandler;
    private LoggerInterface $logger;
    private ?CacheItemPoolInterface $cache;
    private array $config;

    public function __construct(
        string $baseUrl,
        ?string $accessToken = null,
        array $options = [],
        ?LoggerInterface $logger = null,
        ?CacheItemPoolInterface $cache = null
    ) {
        $this->config = array_merge([
            'base_url' => rtrim($baseUrl, '/') . '/api/v1',
            'access_token' => $accessToken,
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_attempts' => 3,
            'retry_delay' => 1000, // milliseconds
            'user_agent' => 'TodayIsLife-SSO-Client/1.0.0 PHP/' . PHP_VERSION,
            'verify_ssl' => true,
            'debug' => false,
        ], $options);

        $this->logger = $logger ?? new NullLogger();
        $this->cache = $cache;
        $this->httpClient = $this->createHttpClient();
        $this->requestBuilder = new RequestBuilder($this->config);
        $this->responseHandler = new ResponseHandler($this->logger);
    }

    /**
     * Create HTTP client with retry middleware
     */
    private function createHttpClient(): ClientInterface
    {
        $stack = HandlerStack::create();

        // Add retry middleware
        $stack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));

        // Add logging middleware
        if ($this->config['debug']) {
            $stack->push(Middleware::log($this->logger, new \GuzzleHttp\MessageFormatter()));
        }

        return new Client([
            'handler' => $stack,
            'timeout' => $this->config['timeout'],
            'connect_timeout' => $this->config['connect_timeout'],
            'verify' => $this->config['verify_ssl'],
            'headers' => [
                'User-Agent' => $this->config['user_agent'],
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Retry decider for failed requests
     */
    private function retryDecider(): callable
    {
        return function (
            int $retries,
            Request $request,
            ?Response $response = null,
            ?RequestException $exception = null
        ): bool {
            if ($retries >= $this->config['retry_attempts']) {
                return false;
            }

            // Retry on server errors or network issues
            if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
                return true;
            }

            if ($response && $response->getStatusCode() >= 500) {
                return true;
            }

            // Don't retry on rate limit (429) or client errors (4xx)
            return false;
        };
    }

    /**
     * Retry delay calculator
     */
    private function retryDelay(): callable
    {
        return function (int $numberOfRetries): int {
            return $this->config['retry_delay'] * $numberOfRetries;
        };
    }

    // =========================
    // Authentication Methods
    // =========================

    /**
     * Register a new user
     */
    public function register(
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        string $passwordConfirmation
    ): AuthResult {
        $data = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ];

        $response = $this->makeRequest('POST', '/auth/register', $data);
        return AuthResult::fromArray($response);
    }

    /**
     * Login with email and password
     */
    public function login(string $email, string $password, bool $remember = false): AuthResult
    {
        $data = [
            'email' => $email,
            'password' => $password,
            'remember' => $remember,
        ];

        $response = $this->makeRequest('POST', '/auth/login', $data);
        $result = AuthResult::fromArray($response);

        // Store token for future requests
        if ($result->getAccessToken()) {
            $this->config['access_token'] = $result->getAccessToken();
        }

        return $result;
    }

    /**
     * Logout current session
     */
    public function logout(): bool
    {
        $this->makeRequest('POST', '/auth/logout', [], true);
        $this->config['access_token'] = null;
        return true;
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(string $token): bool
    {
        $this->makeRequest('GET', "/auth/verify-email/{$token}");
        return true;
    }

    // =========================
    // Magic Link Authentication
    // =========================

    /**
     * Request magic link for passwordless login
     */
    public function requestMagicLink(string $email): MagicLinkResult
    {
        $data = ['email' => $email];
        $response = $this->makeRequest('POST', '/auth/magic-link', $data);
        return MagicLinkResult::fromArray($response);
    }

    /**
     * Verify magic link token
     */
    public function verifyMagicLink(string $token): AuthResult
    {
        $response = $this->makeRequest('GET', "/auth/magic/{$token}");
        $result = AuthResult::fromArray($response);

        // Store token for future requests
        if ($result->getAccessToken()) {
            $this->config['access_token'] = $result->getAccessToken();
        }

        return $result;
    }

    // =========================
    // Two-Factor Authentication
    // =========================

    /**
     * Enable 2FA for current user
     */
    public function enable2FA(): TwoFactorResult
    {
        $response = $this->makeRequest('POST', '/2fa/enable', [], true);
        return TwoFactorResult::fromArray($response);
    }

    /**
     * Confirm 2FA activation
     */
    public function confirm2FA(string $token): bool
    {
        $data = ['token' => $token];
        $this->makeRequest('POST', '/2fa/confirm', $data, true);
        return true;
    }

    /**
     * Verify 2FA token during login
     */
    public function verify2FA(string $email, string $token): AuthResult
    {
        $data = [
            'email' => $email,
            'token' => $token,
        ];

        $response = $this->makeRequest('POST', '/2fa/verify', $data);
        $result = AuthResult::fromArray($response);

        // Store token for future requests
        if ($result->getAccessToken()) {
            $this->config['access_token'] = $result->getAccessToken();
        }

        return $result;
    }

    /**
     * Disable 2FA for current user
     */
    public function disable2FA(string $password): bool
    {
        $data = ['password' => $password];
        $this->makeRequest('POST', '/2fa/disable', $data, true);
        return true;
    }

    // =========================
    // OAuth2/OIDC Methods
    // =========================

    /**
     * Get OAuth authorization URL
     */
    public function getAuthorizationUrl(
        string $clientId,
        string $redirectUri,
        string $responseType = 'code',
        array $scopes = [],
        ?string $state = null,
        ?string $codeChallenge = null,
        string $codeChallengeMethod = 'S256'
    ): string {
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => $responseType,
        ];

        if (!empty($scopes)) {
            $params['scope'] = implode(' ', $scopes);
        }

        if ($state !== null) {
            $params['state'] = $state;
        }

        if ($codeChallenge !== null) {
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = $codeChallengeMethod;
        }

        return $this->config['base_url'] . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(
        string $code,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        ?string $codeVerifier = null
    ): OAuthTokens {
        $data = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ];

        if ($codeVerifier !== null) {
            $data['code_verifier'] = $codeVerifier;
        }

        $response = $this->makeRequest('POST', '/oauth/token', $data);
        return OAuthTokens::fromArray($response);
    }

    /**
     * Get user info using OAuth token
     */
    public function getUserInfo(?string $accessToken = null): User
    {
        $token = $accessToken ?? $this->config['access_token'];
        if (!$token) {
            throw new AuthenticationException('Access token required for getUserInfo');
        }

        $response = $this->makeRequest('GET', '/oauth/userinfo', [], true, $token);
        return User::fromOAuthUserInfo($response);
    }

    // =========================
    // User Management
    // =========================

    /**
     * Get current authenticated user
     */
    public function getCurrentUser(): User
    {
        $response = $this->makeRequest('GET', '/user', [], true);
        return User::fromArray($response);
    }

    /**
     * Update user profile
     */
    public function updateProfile(array $data): User
    {
        $response = $this->makeRequest('PUT', '/user/profile', $data, true);
        return User::fromArray($response['user']);
    }

    /**
     * Change user password
     */
    public function changePassword(
        string $currentPassword,
        string $newPassword,
        string $newPasswordConfirmation
    ): bool {
        $data = [
            'current_password' => $currentPassword,
            'new_password' => $newPassword,
            'new_password_confirmation' => $newPasswordConfirmation,
        ];

        $this->makeRequest('POST', '/user/password', $data, true);
        return true;
    }

    // =========================
    // Password Reset
    // =========================

    /**
     * Request password reset email
     */
    public function requestPasswordReset(string $email): bool
    {
        $data = ['email' => $email];
        $this->makeRequest('POST', '/password/email', $data);
        return true;
    }

    /**
     * Reset password using token
     */
    public function resetPassword(
        string $token,
        string $email,
        string $password,
        string $passwordConfirmation
    ): bool {
        $data = [
            'token' => $token,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ];

        $this->makeRequest('POST', '/password/reset', $data);
        return true;
    }

    // =========================
    // Social Login
    // =========================

    /**
     * Get social provider redirect URL
     */
    public function getSocialRedirectUrl(string $provider): string
    {
        return $this->config['base_url'] . "/auth/social/{$provider}/redirect";
    }

    // =========================
    // Core HTTP Methods
    // =========================

    /**
     * Make HTTP request to SSO API
     */
    private function makeRequest(
        string $method,
        string $endpoint,
        array $data = [],
        bool $requiresAuth = false,
        ?string $customToken = null
    ): array {
        try {
            $request = $this->requestBuilder->build(
                $method,
                $endpoint,
                $data,
                $requiresAuth,
                $customToken
            );

            $this->logger->debug('SSO API Request', [
                'method' => $method,
                'endpoint' => $endpoint,
                'has_auth' => $requiresAuth,
            ]);

            $response = $this->httpClient->sendRequest($request);

            return $this->responseHandler->handle($response);

        } catch (ClientException $e) {
            $this->handleClientException($e);
        } catch (GuzzleException $e) {
            $this->logger->error('SSO API Request Failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw new SsoClientException(
                'HTTP request failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Handle client exceptions (4xx errors)
     */
    private function handleClientException(ClientException $e): void
    {
        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        try {
            $errorData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            $errorData = ['message' => $body];
        }

        $message = $errorData['message'] ?? 'Unknown error';

        match ($statusCode) {
            401 => throw new AuthenticationException($message, $statusCode, $e),
            400 => throw new ValidationException($message, $errorData['errors'] ?? [], $statusCode, $e),
            429 => throw new RateLimitException($message, $this->extractRetryAfter($response), $statusCode, $e),
            default => throw new SsoClientException($message, $statusCode, $e),
        };
    }

    /**
     * Extract retry-after header value
     */
    private function extractRetryAfter(Response $response): ?int
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        return $retryAfter ? (int) $retryAfter : null;
    }

    // =========================
    // Utility Methods
    // =========================

    /**
     * Set access token for authenticated requests
     */
    public function setAccessToken(string $token): self
    {
        $this->config['access_token'] = $token;
        return $this;
    }

    /**
     * Get current access token
     */
    public function getAccessToken(): ?string
    {
        return $this->config['access_token'];
    }

    /**
     * Check if client has valid access token
     */
    public function hasAccessToken(): bool
    {
        return !empty($this->config['access_token']);
    }

    /**
     * Get base URL
     */
    public function getBaseUrl(): string
    {
        return $this->config['base_url'];
    }

    /**
     * Generate PKCE code verifier and challenge
     */
    public static function generatePkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [
            'code_verifier' => $verifier,
            'code_challenge' => $challenge,
        ];
    }

    /**
     * Generate secure state parameter
     */
    public static function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }
}