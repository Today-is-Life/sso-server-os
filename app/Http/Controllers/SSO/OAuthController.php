<?php

namespace App\Http\Controllers\SSO;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OAuthController extends Controller
{
    /**
     * OAuth2 Authorization Endpoint
     */
    public function authorize(Request $request): JsonResponse|RedirectResponse|View
    {
        $validated = $request->validate([
            'client_id' => 'required|string',
            'redirect_uri' => 'required|url',
            'response_type' => 'required|in:code,token',
            'scope' => 'nullable|string',
            'state' => 'nullable|string',
            'code_challenge' => 'nullable|string', // PKCE
            'code_challenge_method' => 'nullable|in:S256,plain',
        ]);
        
        // Find domain by client_id
        $domain = Domain::where('client_id', $validated['client_id'])->first();
        
        if (!$domain || !$domain->is_active) {
            return response()->json(['error' => 'invalid_client'], 400);
        }
        
        // Validate redirect URI
        if (!$domain->isRedirectUriAllowed($validated['redirect_uri'])) {
            return response()->json(['error' => 'invalid_redirect_uri'], 400);
        }
        
        // Check if user is logged in
        if (!Auth::check()) {
            // Store authorization request in session
            session(['oauth_request' => $validated]);
            return redirect()->route('sso.login', ['return_to' => route('oauth.authorize', $validated)]);
        }
        
        // Check if authorization already granted
        $existingAuth = DB::table('oauth_authorizations')
            ->where('user_id', Auth::id())
            ->where('domain_id', $domain->id)
            ->where('scope', $validated['scope'] ?? 'openid profile email')
            ->first();
        
        if ($existingAuth) {
            return $this->issueAuthorizationCode($domain, $validated);
        }
        
        // Show authorization consent screen
        return view('sso.authorize', [
            'domain' => $domain,
            'scope' => $validated['scope'] ?? 'openid profile email',
            'state' => $validated['state'] ?? null,
        ]);
    }
    
    /**
     * Approve authorization
     */
    public function approveAuthorization(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|string',
            'approved' => 'required|boolean',
        ]);
        
        $domain = Domain::where('client_id', $validated['client_id'])->first();
        
        if (!$domain) {
            return response()->json(['error' => 'invalid_client'], 400);
        }
        
        $oauthRequest = session('oauth_request');
        
        if (!$validated['approved']) {
            $redirectUri = $oauthRequest['redirect_uri'];
            $params = ['error' => 'access_denied'];
            if (isset($oauthRequest['state'])) {
                $params['state'] = $oauthRequest['state'];
            }
            return redirect($redirectUri . '?' . http_build_query($params));
        }
        
        // Store authorization
        DB::table('oauth_authorizations')->insert([
            'id' => Str::uuid(),
            'user_id' => Auth::id(),
            'domain_id' => $domain->id,
            'scope' => $oauthRequest['scope'] ?? 'openid profile email',
            'created_at' => now(),
        ]);
        
        return $this->issueAuthorizationCode($domain, $oauthRequest);
    }
    
    /**
     * Issue authorization code
     */
    private function issueAuthorizationCode(Domain $domain, array $request): RedirectResponse
    {
        $code = Str::random(64);
        
        // Store authorization code (expires in 10 minutes)
        DB::table('oauth_authorization_codes')->insert([
            'id' => Str::uuid(),
            'code' => hash('sha256', $code),
            'user_id' => Auth::id(),
            'domain_id' => $domain->id,
            'redirect_uri' => $request['redirect_uri'],
            'scope' => $request['scope'] ?? 'openid profile email',
            'code_challenge' => $request['code_challenge'] ?? null,
            'code_challenge_method' => $request['code_challenge_method'] ?? null,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);
        
        $params = ['code' => $code];
        if (isset($request['state'])) {
            $params['state'] = $request['state'];
        }
        
        return redirect($request['redirect_uri'] . '?' . http_build_query($params));
    }
    
    /**
     * OAuth2 Token Endpoint
     */
    public function token(Request $request): JsonResponse
    {
        $grantType = $request->input('grant_type');
        
        switch ($grantType) {
            case 'authorization_code':
                return $this->handleAuthorizationCodeGrant($request);
            case 'refresh_token':
                return $this->handleRefreshTokenGrant($request);
            case 'client_credentials':
                return $this->handleClientCredentialsGrant($request);
            default:
                return response()->json(['error' => 'unsupported_grant_type'], 400);
        }
    }
    
    /**
     * Handle authorization code grant
     */
    private function handleAuthorizationCodeGrant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'redirect_uri' => 'required|url',
            'code_verifier' => 'nullable|string', // PKCE
        ]);
        
        // Verify client
        $domain = Domain::where('client_id', $validated['client_id'])->first();
        
        if (!$domain || $domain->client_secret !== $validated['client_secret']) {
            return response()->json(['error' => 'invalid_client'], 401);
        }
        
        // Find and validate authorization code
        $authCode = DB::table('oauth_authorization_codes')
            ->where('code', hash('sha256', $validated['code']))
            ->where('domain_id', $domain->id)
            ->where('redirect_uri', $validated['redirect_uri'])
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();
        
        if (!$authCode) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }
        
        // Verify PKCE if present
        if ($authCode->code_challenge) {
            if (!isset($validated['code_verifier'])) {
                return response()->json(['error' => 'invalid_request', 'error_description' => 'code_verifier required'], 400);
            }
            
            $verifier = $authCode->code_challenge_method === 'S256' 
                ? base64_encode(hash('sha256', $validated['code_verifier'], true))
                : $validated['code_verifier'];
            
            if ($verifier !== $authCode->code_challenge) {
                return response()->json(['error' => 'invalid_grant'], 400);
            }
        }
        
        // Mark code as used
        DB::table('oauth_authorization_codes')
            ->where('id', $authCode->id)
            ->update(['used_at' => now()]);
        
        // Generate tokens
        $user = User::find($authCode->user_id);
        return $this->issueTokens($user, $domain, $authCode->scope);
    }
    
    /**
     * Issue access and refresh tokens
     */
    private function issueTokens(User $user, Domain $domain, string $scope): JsonResponse
    {
        $tokenId = Str::uuid();
        $accessToken = $this->generateJWT($user, $domain, $scope, $tokenId);
        $refreshToken = Str::random(64);
        
        // Store tokens
        DB::table('oauth_tokens')->insert([
            'id' => $tokenId,
            'user_id' => $user->id,
            'client_id' => $domain->id,
            'token' => hash('sha256', $accessToken),
            'scopes' => json_encode(explode(' ', $scope)),
            'expires_at' => now()->addSeconds($domain->token_lifetime ?? 3600),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('oauth_refresh_tokens')->insert([
            'id' => Str::uuid(),
            'access_token_id' => $tokenId,
            'token' => hash('sha256', $refreshToken),
            'expires_at' => now()->addSeconds($domain->refresh_token_lifetime ?? 86400),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $domain->token_lifetime ?? 3600,
            'refresh_token' => $refreshToken,
            'scope' => $scope,
            'id_token' => $this->generateIDToken($user, $domain),
        ]);
    }
    
    /**
     * Generate JWT access token
     */
    private function generateJWT(User $user, Domain $domain, string $scope, string $tokenId): string
    {
        $privateKey = $this->getPrivateKey();
        
        $payload = [
            'iss' => url('/'),
            'sub' => $user->id,
            'aud' => $domain->client_id,
            'exp' => time() + ($domain->token_lifetime ?? 3600),
            'iat' => time(),
            'jti' => $tokenId,
            'scope' => $scope,
            'email' => $user->email,
            'name' => $user->name,
        ];
        
        return JWT::encode($payload, $privateKey, 'RS256', 'sso-key-1');
    }
    
    /**
     * Generate OpenID Connect ID Token
     */
    private function generateIDToken(User $user, Domain $domain): string
    {
        $privateKey = $this->getPrivateKey();
        
        $payload = [
            'iss' => url('/'),
            'sub' => $user->id,
            'aud' => $domain->client_id,
            'exp' => time() + 3600,
            'iat' => time(),
            'auth_time' => session('auth_time', time()),
            'nonce' => session('oidc_nonce'),
            'email' => $user->email,
            'email_verified' => $user->getAttribute('email_verified_at') !== null,
            'name' => $user->name,
            'preferred_username' => $user->email,
            'locale' => $user->getAttribute('locale'),
        ];
        
        return JWT::encode($payload, $privateKey, 'RS256', 'sso-key-1');
    }
    
    /**
     * Get user info endpoint
     */
    public function userinfo(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'sub' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->getAttribute('email_verified_at') !== null,
            'locale' => $user->getAttribute('locale'),
            'preferred_username' => $user->email,
            'updated_at' => $user->updated_at->timestamp,
        ]);
    }
    
    /**
     * OpenID Connect Discovery
     */
    public function discovery(): JsonResponse
    {
        return response()->json([
            'issuer' => url('/'),
            'authorization_endpoint' => route('oauth.authorize'),
            'token_endpoint' => route('oauth.token'),
            'userinfo_endpoint' => route('oauth.userinfo'),
            'jwks_uri' => route('oidc.jwks'),
            'response_types_supported' => ['code', 'token', 'id_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'claims_supported' => ['sub', 'name', 'email', 'email_verified', 'locale'],
            'code_challenge_methods_supported' => ['plain', 'S256'],
        ]);
    }
    
    /**
     * JWKS endpoint
     */
    public function jwks(): JsonResponse
    {
        $publicKey = $this->getPublicKey();
        
        // Extract key components for JWK
        $keyResource = openssl_pkey_get_public($publicKey);
        $keyDetails = openssl_pkey_get_details($keyResource);
        
        return response()->json([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'kid' => 'sso-key-1',
                    'alg' => 'RS256',
                    'n' => base64_encode($keyDetails['rsa']['n']),
                    'e' => base64_encode($keyDetails['rsa']['e']),
                ]
            ]
        ]);
    }
    
    /**
     * Get private key for JWT signing
     */
    private function getPrivateKey(): string
    {
        $keyPath = storage_path('keys/oauth-private.key');
        
        if (!file_exists($keyPath)) {
            // Generate key pair if not exists
            $this->generateKeyPair();
        }
        
        $content = file_get_contents($keyPath);
        if ($content === false) {
            throw new \Exception('Failed to read private key');
        }
        return $content;
    }
    
    /**
     * Get public key for JWT verification
     */
    private function getPublicKey(): string
    {
        $keyPath = storage_path('keys/oauth-public.key');
        
        if (!file_exists($keyPath)) {
            $this->generateKeyPair();
        }
        
        $content = file_get_contents($keyPath);
        if ($content === false) {
            throw new \Exception('Failed to read public key');
        }
        return $content;
    }
    
    /**
     * Generate RSA key pair
     */
    private function generateKeyPair(): void
    {
        $config = [
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        
        $res = openssl_pkey_new($config);
        
        openssl_pkey_export($res, $privateKey);
        $publicKey = openssl_pkey_get_details($res)['key'];
        
        // Create keys directory if not exists
        $keyDir = storage_path('keys');
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0755, true);
        }
        
        file_put_contents($keyDir . '/oauth-private.key', $privateKey);
        file_put_contents($keyDir . '/oauth-public.key', $publicKey);
        
        chmod($keyDir . '/oauth-private.key', 0600);
    }
}