<?php

/**
 * SSO Client SDK - Basic Usage Example
 *
 * (c) 2024-2025 Today is Life GmbH
 * Author: Guido Mitschke
 * License: MIT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use TodayIsLife\SsoClient\Client\SsoClient;
use TodayIsLife\SsoClient\Exceptions\AuthenticationException;
use TodayIsLife\SsoClient\Exceptions\ValidationException;
use TodayIsLife\SsoClient\Exceptions\RateLimitException;

// Initialize the SSO client
$ssoClient = new SsoClient(
    baseUrl: 'https://sso.yourdomain.com',
    accessToken: null,
    options: [
        'timeout' => 30,
        'verify_ssl' => true,
        'debug' => false,
    ]
);

echo "=== SSO Client SDK - Basic Usage Example ===\n\n";

try {
    // Example 1: User Login
    echo "1. User Login\n";
    echo "Attempting to login with email and password...\n";

    $loginResult = $ssoClient->login('demo@example.com', 'demo-password');

    if ($loginResult->isSuccess()) {
        echo "✅ Login successful!\n";
        echo "   User: " . $loginResult->getUser()->getName() . "\n";
        echo "   Email: " . $loginResult->getUser()->getEmail() . "\n";
        echo "   Access Token: " . substr($loginResult->getAccessToken(), 0, 20) . "...\n";
        echo "   Token Type: " . $loginResult->getTokenType() . "\n";
        echo "   Expires In: " . $loginResult->getExpiresIn() . " seconds\n";
    } else {
        echo "❌ Login failed: " . $loginResult->getMessage() . "\n";
    }

    echo "\n";

    // Example 2: Get Current User (requires authentication)
    if ($loginResult->isSuccess()) {
        echo "2. Get Current User\n";
        echo "Fetching current user information...\n";

        $user = $ssoClient->getCurrentUser();

        echo "✅ User information retrieved:\n";
        echo "   ID: " . $user->getId() . "\n";
        echo "   Name: " . $user->getName() . "\n";
        echo "   Email: " . $user->getEmail() . "\n";
        echo "   Email Verified: " . ($user->isEmailVerified() ? 'Yes' : 'No') . "\n";
        echo "   MFA Enabled: " . ($user->isMfaEnabled() ? 'Yes' : 'No') . "\n";

        if ($user->getTimezone()) {
            echo "   Timezone: " . $user->getTimezone() . "\n";
        }

        if ($user->getLocale()) {
            echo "   Locale: " . $user->getLocale() . "\n";
        }

        echo "\n";
    }

    // Example 3: Request Password Reset
    echo "3. Request Password Reset\n";
    echo "Requesting password reset for demo@example.com...\n";

    $resetResult = $ssoClient->requestPasswordReset('demo@example.com');

    if ($resetResult) {
        echo "✅ Password reset email sent successfully!\n";
    }

    echo "\n";

    // Example 4: Request Magic Link
    echo "4. Request Magic Link\n";
    echo "Requesting magic link for demo@example.com...\n";

    $magicLinkResult = $ssoClient->requestMagicLink('demo@example.com');

    if ($magicLinkResult->isSuccess()) {
        echo "✅ Magic link sent: " . $magicLinkResult->getMessage() . "\n";
    }

    echo "\n";

    // Example 5: OAuth2 Authorization URL
    echo "5. OAuth2 Authorization URL\n";
    echo "Generating OAuth2 authorization URL...\n";

    $pkce = SsoClient::generatePkce();
    $state = SsoClient::generateState();

    $authUrl = $ssoClient->getAuthorizationUrl(
        clientId: 'demo-client-id',
        redirectUri: 'https://yourapp.com/callback',
        responseType: 'code',
        scopes: ['openid', 'email', 'profile'],
        state: $state,
        codeChallenge: $pkce['code_challenge'],
        codeChallengeMethod: 'S256'
    );

    echo "✅ Authorization URL generated:\n";
    echo "   URL: " . $authUrl . "\n";
    echo "   State: " . $state . "\n";
    echo "   PKCE Verifier: " . substr($pkce['code_verifier'], 0, 20) . "...\n";

    echo "\n";

    // Example 6: Social Login URLs
    echo "6. Social Login URLs\n";
    echo "Available social login providers:\n";

    $providers = ['google', 'github', 'facebook', 'linkedin', 'twitter'];
    foreach ($providers as $provider) {
        $socialUrl = $ssoClient->getSocialRedirectUrl($provider);
        echo "   " . ucfirst($provider) . ": " . $socialUrl . "\n";
    }

    echo "\n";

    // Example 7: Logout (if logged in)
    if ($loginResult->isSuccess()) {
        echo "7. User Logout\n";
        echo "Logging out current user...\n";

        $logoutResult = $ssoClient->logout();

        if ($logoutResult) {
            echo "✅ Logout successful!\n";
            echo "   Access token cleared from client\n";
        }
    }

} catch (AuthenticationException $e) {
    echo "❌ Authentication Error: " . $e->getMessage() . "\n";
    echo "   This usually means invalid credentials or expired token.\n";

} catch (ValidationException $e) {
    echo "❌ Validation Error: " . $e->getMessage() . "\n";
    echo "   Field errors:\n";

    foreach ($e->getAllErrorMessages() as $error) {
        echo "   - " . $error . "\n";
    }

} catch (RateLimitException $e) {
    echo "❌ Rate Limit Exceeded: " . $e->getMessage() . "\n";

    if ($e->getRetryAfter()) {
        echo "   Retry after: " . $e->getRetryAfter() . " seconds\n";
        echo "   Can retry now: " . ($e->canRetryNow() ? 'Yes' : 'No') . "\n";
    }

} catch (\Exception $e) {
    echo "❌ Unexpected Error: " . $e->getMessage() . "\n";
    echo "   Error Type: " . get_class($e) . "\n";
}

echo "\n=== Example completed ===\n";