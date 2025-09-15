# SSO Client SDK for PHP

A comprehensive PHP SDK for integrating with the TodayIsLife SSO Server API. This SDK provides easy-to-use methods for authentication, user management, OAuth2/OIDC flows, two-factor authentication, and more.

## Features

- 🔐 **Complete Authentication Support** - Login, registration, magic links, password reset
- 🛡️ **Two-Factor Authentication** - TOTP setup, verification, and management
- 🎯 **OAuth2/OIDC Integration** - Full authorization code flow with PKCE support
- 👥 **User Management** - Profile updates, password changes, user info
- 🔗 **Social Login** - Support for Google, GitHub, Facebook, and more
- 🚀 **Production Ready** - Retry logic, caching, rate limiting, logging
- 📊 **PSR Compliance** - PSR-3 logging, PSR-6 caching, PSR-18 HTTP client
- 🧪 **Well Tested** - Comprehensive unit tests and examples

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client
- PSR-compatible logger (optional)
- PSR-compatible cache (optional)

## Installation

Install via Composer:

```bash
composer require todayislife/sso-client
```

## Quick Start

### Basic Setup

```php
<?php

use TodayIsLife\SsoClient\Client\SsoClient;

// Initialize the client
$ssoClient = new SsoClient(
    baseUrl: 'https://sso.yourdomain.com',
    accessToken: null, // Will be set after login
    options: [
        'timeout' => 30,
        'verify_ssl' => true,
        'debug' => false,
    ]
);

// Login user
try {
    $result = $ssoClient->login('user@example.com', 'password123');

    if ($result->isSuccess()) {
        echo "Login successful!\n";
        echo "Access Token: " . $result->getAccessToken() . "\n";
        echo "User: " . $result->getUser()->getName() . "\n";
    }
} catch (\Exception $e) {
    echo "Login failed: " . $e->getMessage() . "\n";
}
```

### With Logging and Caching

```php
<?php

use TodayIsLife\SsoClient\Client\SsoClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

// Setup logger
$logger = new Logger('sso');
$logger->pushHandler(new StreamHandler('sso.log', Logger::DEBUG));

// Setup cache
$cache = new FilesystemAdapter('sso', 0, '/tmp/sso-cache');

// Initialize client with logger and cache
$ssoClient = new SsoClient(
    baseUrl: 'https://sso.yourdomain.com',
    accessToken: null,
    options: [
        'debug' => true,
        'retry_attempts' => 3,
        'cache_ttl' => 300,
    ],
    logger: $logger,
    cache: $cache
);
```

## Authentication Examples

### User Registration

```php
try {
    $result = $ssoClient->register(
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        password: 'SecurePassword123!',
        passwordConfirmation: 'SecurePassword123!'
    );

    echo "Registration successful! Please check email for verification.\n";
    echo "User ID: " . $result->getUser()->getId() . "\n";
} catch (\TodayIsLife\SsoClient\Exceptions\ValidationException $e) {
    echo "Validation errors:\n";
    foreach ($e->getAllErrorMessages() as $error) {
        echo "- $error\n";
    }
}
```

### Magic Link Authentication

```php
// Request magic link
try {
    $result = $ssoClient->requestMagicLink('user@example.com');
    echo $result->getMessage() . "\n"; // "Magic link sent to your email"
} catch (\Exception $e) {
    echo "Failed to send magic link: " . $e->getMessage() . "\n";
}

// Verify magic link (typically called from email link)
try {
    $result = $ssoClient->verifyMagicLink($tokenFromEmail);

    if ($result->isSuccess()) {
        echo "Magic link login successful!\n";
        echo "Access Token: " . $result->getAccessToken() . "\n";
    }
} catch (\Exception $e) {
    echo "Magic link verification failed: " . $e->getMessage() . "\n";
}
```

### Two-Factor Authentication

```php
// Enable 2FA for current user
try {
    $result = $ssoClient->enable2FA();

    echo "2FA Secret: " . $result->getSecret() . "\n";
    echo "QR Code: " . $result->getQrCodeDataUri() . "\n";
    echo "Recovery Codes:\n";

    foreach ($result->getRecoveryCodes() as $code) {
        echo "- $code\n";
    }
} catch (\Exception $e) {
    echo "Failed to enable 2FA: " . $e->getMessage() . "\n";
}

// Confirm 2FA setup
try {
    $ssoClient->confirm2FA('123456'); // TOTP token from authenticator app
    echo "2FA enabled successfully!\n";
} catch (\Exception $e) {
    echo "2FA confirmation failed: " . $e->getMessage() . "\n";
}

// Login with 2FA
try {
    $result = $ssoClient->verify2FA('user@example.com', '123456');

    if ($result->isSuccess()) {
        echo "2FA login successful!\n";
    }
} catch (\Exception $e) {
    echo "2FA verification failed: " . $e->getMessage() . "\n";
}
```

## OAuth2/OIDC Integration

### Authorization Code Flow with PKCE

```php
// Generate PKCE parameters
$pkce = SsoClient::generatePkce();
$state = SsoClient::generateState();

// Get authorization URL
$authUrl = $ssoClient->getAuthorizationUrl(
    clientId: 'your-client-id',
    redirectUri: 'https://yourapp.com/callback',
    responseType: 'code',
    scopes: ['openid', 'email', 'profile'],
    state: $state,
    codeChallenge: $pkce['code_challenge'],
    codeChallengeMethod: 'S256'
);

echo "Redirect user to: $authUrl\n";

// In your callback handler
try {
    $tokens = $ssoClient->exchangeCodeForToken(
        code: $_GET['code'],
        clientId: 'your-client-id',
        clientSecret: 'your-client-secret',
        redirectUri: 'https://yourapp.com/callback',
        codeVerifier: $pkce['code_verifier']
    );

    echo "Access Token: " . $tokens->getAccessToken() . "\n";
    echo "Refresh Token: " . $tokens->getRefreshToken() . "\n";
    echo "Expires In: " . $tokens->getExpiresIn() . " seconds\n";

    // Get user info
    $user = $ssoClient->getUserInfo($tokens->getAccessToken());
    echo "User: " . $user->getName() . " (" . $user->getEmail() . ")\n";

} catch (\Exception $e) {
    echo "Token exchange failed: " . $e->getMessage() . "\n";
}
```

## User Management

### Get Current User

```php
try {
    $user = $ssoClient->getCurrentUser();

    echo "User ID: " . $user->getId() . "\n";
    echo "Name: " . $user->getName() . "\n";
    echo "Email: " . $user->getEmail() . "\n";
    echo "Email Verified: " . ($user->isEmailVerified() ? 'Yes' : 'No') . "\n";
    echo "2FA Enabled: " . ($user->isMfaEnabled() ? 'Yes' : 'No') . "\n";
} catch (\Exception $e) {
    echo "Failed to get user: " . $e->getMessage() . "\n";
}
```

### Update Profile

```php
try {
    $user = $ssoClient->updateProfile([
        'first_name' => 'John',
        'last_name' => 'Smith',
        'timezone' => 'America/New_York',
        'locale' => 'en_US'
    ]);

    echo "Profile updated successfully!\n";
    echo "New name: " . $user->getName() . "\n";
} catch (\Exception $e) {
    echo "Failed to update profile: " . $e->getMessage() . "\n";
}
```

### Change Password

```php
try {
    $ssoClient->changePassword(
        currentPassword: 'OldPassword123!',
        newPassword: 'NewPassword456!',
        newPasswordConfirmation: 'NewPassword456!'
    );

    echo "Password changed successfully!\n";
} catch (\Exception $e) {
    echo "Failed to change password: " . $e->getMessage() . "\n";
}
```

## Password Reset

```php
// Request password reset
try {
    $ssoClient->requestPasswordReset('user@example.com');
    echo "Password reset email sent!\n";
} catch (\Exception $e) {
    echo "Failed to send reset email: " . $e->getMessage() . "\n";
}

// Reset password with token
try {
    $ssoClient->resetPassword(
        token: $tokenFromEmail,
        email: 'user@example.com',
        password: 'NewPassword123!',
        passwordConfirmation: 'NewPassword123!'
    );

    echo "Password reset successfully!\n";
} catch (\Exception $e) {
    echo "Failed to reset password: " . $e->getMessage() . "\n";
}
```

## Social Login

```php
// Get redirect URL for social provider
$providers = ['google', 'github', 'facebook', 'instagram', 'linkedin', 'twitter', 'microsoft', 'apple'];

foreach ($providers as $provider) {
    $redirectUrl = $ssoClient->getSocialRedirectUrl($provider);
    echo "$provider: $redirectUrl\n";
}
```

## Error Handling

The SDK provides specific exception types for different error scenarios:

```php
use TodayIsLife\SsoClient\Exceptions\AuthenticationException;
use TodayIsLife\SsoClient\Exceptions\ValidationException;
use TodayIsLife\SsoClient\Exceptions\RateLimitException;
use TodayIsLife\SsoClient\Exceptions\SsoClientException;

try {
    $result = $ssoClient->login('user@example.com', 'wrong-password');
} catch (AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage() . "\n";
} catch (ValidationException $e) {
    echo "Validation errors:\n";
    foreach ($e->getAllErrorMessages() as $error) {
        echo "- $error\n";
    }
} catch (RateLimitException $e) {
    echo "Rate limit exceeded. Retry after: " . $e->getRetryAfter() . " seconds\n";
} catch (SsoClientException $e) {
    echo "SSO error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}
```

## Configuration Options

```php
$options = [
    'timeout' => 30,                    // Request timeout in seconds
    'connect_timeout' => 10,            // Connection timeout in seconds
    'retry_attempts' => 3,              // Number of retry attempts
    'retry_delay' => 1000,              // Delay between retries in milliseconds
    'verify_ssl' => true,               // Verify SSL certificates
    'debug' => false,                   // Enable debug logging
    'user_agent' => 'Custom-Agent/1.0', // Custom user agent
];

$ssoClient = new SsoClient(
    baseUrl: 'https://sso.yourdomain.com',
    accessToken: $token,
    options: $options,
    logger: $logger,
    cache: $cache
);
```

## Rate Limiting

The SDK automatically handles rate limiting with exponential backoff. When rate limits are exceeded, it throws a `RateLimitException` with retry information:

```php
try {
    $result = $ssoClient->login('user@example.com', 'password');
} catch (RateLimitException $e) {
    $retryAfter = $e->getRetryAfter();
    $canRetryNow = $e->canRetryNow();
    $secondsUntilRetry = $e->getSecondsUntilRetry();

    if (!$canRetryNow) {
        echo "Rate limited. Retry in $secondsUntilRetry seconds.\n";
        sleep($secondsUntilRetry);
        // Retry the operation
    }
}
```

## Caching

The SDK supports PSR-6 caching for improved performance. Responses are automatically cached based on the operation type.

## Testing

Run the test suite:

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run the test suite
6. Submit a pull request

## License

This SDK is licensed under the MIT License. See the LICENSE file for details.

## Support

For support, please contact:
- Email: info@todayislife.de
- Website: https://www.todayislife.de

## Changelog

### 1.0.0 (2024-09-15)
- Initial release
- Complete SSO API coverage
- OAuth2/OIDC support
- Two-factor authentication
- Magic link authentication
- Social login integration
- Comprehensive error handling
- PSR compliance
- Production-ready features