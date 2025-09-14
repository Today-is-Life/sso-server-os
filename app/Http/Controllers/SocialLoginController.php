<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SocialAccount;
use App\Services\SiemService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialLoginController extends Controller
{
    protected SiemService $siemService;

    public function __construct(SiemService $siemService)
    {
        $this->siemService = $siemService;
    }
    /**
     * Supported social providers
     */
    private const SUPPORTED_PROVIDERS = [
        'google',
        'github',
        'facebook',
        'instagram',
        'linkedin',
        'twitter',
        'microsoft',
        'apple'
    ];

    /**
     * Redirect to social provider
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS)) {
            return redirect()->route('sso.login')
                ->withErrors(['provider' => 'Unbekannter Social Login Anbieter.']);
        }

        // Check if provider is configured
        if (!$this->isProviderConfigured($provider)) {
            return redirect()->route('sso.login')
                ->withErrors(['provider' => ucfirst($provider) . ' Login ist nicht konfiguriert.']);
        }

        try {
            return Socialite::driver($provider)->redirect();
        } catch (\Exception $e) {
            Log::error("Social login redirect failed for {$provider}: " . $e->getMessage());

            return redirect()->route('sso.login')
                ->withErrors(['provider' => 'Fehler beim Weiterleiten zu ' . ucfirst($provider) . '.']);
        }
    }

    /**
     * Handle social provider callback
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        if (!in_array($provider, self::SUPPORTED_PROVIDERS)) {
            return redirect()->route('sso.login')
                ->withErrors(['provider' => 'Unbekannter Social Login Anbieter.']);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            Log::error("Social login callback failed for {$provider}: " . $e->getMessage());

            return redirect()->route('sso.login')
                ->withErrors(['provider' => 'Fehler beim ' . ucfirst($provider) . ' Login. Bitte versuchen Sie es erneut.']);
        }

        // Find or create user
        $user = $this->findOrCreateUser($socialUser, $provider);

        if (!$user) {
            return redirect()->route('sso.login')
                ->withErrors(['provider' => 'Benutzer konnte nicht erstellt werden.']);
        }

        // Login user
        Auth::login($user);
        $user->recordSuccessfulLogin($request->ip());

        // Log social login
        $this->siemService->logEvent(
            SiemService::EVENT_SOCIAL_LOGIN,
            SiemService::LEVEL_INFO,
            $user->id,
            $request,
            [
                'provider' => $provider,
                'social_id' => $socialUser->getId(),
                'social_email' => $socialUser->getEmail()
            ]
        );

        Log::info("User logged in via {$provider}", [
            'user_id' => $user->id,
            'provider' => $provider,
            'social_id' => $socialUser->getId()
        ]);

        // Handle OAuth redirect if present
        if (session()->has('oauth_request')) {
            $oauthRequest = session()->pull('oauth_request');
            return $this->handleOAuthRedirect($user, $oauthRequest);
        }

        return redirect()->intended('/admin');
    }

    /**
     * Find or create user from social login
     */
    private function findOrCreateUser($socialUser, string $provider): ?User
    {
        // First, check if social account exists
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            return $socialAccount->user;
        }

        // Check if user exists by email
        $email = $socialUser->getEmail();
        if (!$email) {
            Log::warning("Social login without email for provider {$provider}");
            return null;
        }

        $emailHash = hash('sha256', strtolower($email));
        $user = User::where('email_hash', $emailHash)->first();

        if (!$user) {
            // Create new user
            $user = new User();
            $user->id = (string) Str::uuid();
            $user->name = $socialUser->getName() ?: $socialUser->getNickname() ?: 'Social User';
            $user->email = $email;
            $user->email_verified_at = now(); // Social accounts are considered verified
            $user->setAttribute('avatar_url', $socialUser->getAvatar());
            $user->setAttribute('locale', 'de');
            $user->setAttribute('timezone', 'Europe/Berlin');
            $user->password = bcrypt(Str::random(32)); // Random password, user can reset if needed
            $user->save();

            // Log account creation
            $this->siemService->logEvent(
                SiemService::EVENT_ACCOUNT_CREATED,
                SiemService::LEVEL_INFO,
                $user->id,
                request(),
                [
                    'provider' => $provider,
                    'social_id' => $socialUser->getId(),
                    'creation_method' => 'social_login'
                ]
            );

            Log::info("New user created via social login", [
                'user_id' => $user->id,
                'provider' => $provider,
                'email' => $email
            ]);
        } else {
            // Update user avatar if available
            if ($socialUser->getAvatar()) {
                $user->setAttribute('avatar_url', $socialUser->getAvatar());
                $user->save();
            }
        }

        // Create social account link
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_email' => $socialUser->getEmail(),
            'provider_name' => $socialUser->getName(),
            'provider_nickname' => $socialUser->getNickname(),
            'provider_avatar' => $socialUser->getAvatar(),
            'access_token' => $socialUser->token ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'expires_at' => $socialUser->expiresIn ? now()->addSeconds($socialUser->expiresIn) : null,
        ]);

        return $user;
    }

    /**
     * Check if social provider is configured
     */
    private function isProviderConfigured(string $provider): bool
    {
        $config = config("services.{$provider}");

        return $config &&
               !empty($config['client_id']) &&
               !empty($config['client_secret']);
    }

    /**
     * Get available social providers
     */
    public static function getAvailableProviders(): array
    {
        $providers = [];

        foreach (self::SUPPORTED_PROVIDERS as $provider) {
            $config = config("services.{$provider}");

            if ($config && !empty($config['client_id']) && !empty($config['client_secret'])) {
                $providers[] = [
                    'name' => $provider,
                    'display_name' => ucfirst($provider),
                    'icon' => self::getProviderIcon($provider),
                    'color' => self::getProviderColor($provider),
                ];
            }
        }

        return $providers;
    }

    /**
     * Get provider icon class
     */
    private static function getProviderIcon(string $provider): string
    {
        return match($provider) {
            'google' => 'fab fa-google',
            'github' => 'fab fa-github',
            'facebook' => 'fab fa-facebook-f',
            'instagram' => 'fab fa-instagram',
            'linkedin' => 'fab fa-linkedin-in',
            'twitter' => 'fab fa-twitter',
            'microsoft' => 'fab fa-microsoft',
            'apple' => 'fab fa-apple',
            default => 'fas fa-sign-in-alt'
        };
    }

    /**
     * Get provider brand color
     */
    private static function getProviderColor(string $provider): string
    {
        return match($provider) {
            'google' => 'bg-red-500 hover:bg-red-600',
            'github' => 'bg-gray-800 hover:bg-gray-900',
            'facebook' => 'bg-blue-600 hover:bg-blue-700',
            'instagram' => 'bg-pink-500 hover:bg-pink-600',
            'linkedin' => 'bg-blue-700 hover:bg-blue-800',
            'twitter' => 'bg-blue-400 hover:bg-blue-500',
            'microsoft' => 'bg-blue-600 hover:bg-blue-700',
            'apple' => 'bg-black hover:bg-gray-800',
            default => 'bg-gray-500 hover:bg-gray-600'
        };
    }

    /**
     * Handle OAuth redirect after social login
     */
    private function handleOAuthRedirect(User $user, array $oauthRequest): RedirectResponse
    {
        // Generate authorization code
        $code = Str::random(40);

        DB::table('oauth_authorization_codes')->insert([
            'id' => Str::uuid(),
            'client_id' => $oauthRequest['client_id'],
            'user_id' => $user->id,
            'code' => $code,
            'redirect_uri' => $oauthRequest['redirect_uri'],
            'expires_at' => now()->addMinutes(10),
            'code_challenge' => $oauthRequest['code_challenge'] ?? null,
            'code_challenge_method' => $oauthRequest['code_challenge_method'] ?? null,
            'created_at' => now(),
        ]);

        $params = [
            'code' => $code,
            'state' => $oauthRequest['state'] ?? null,
        ];

        return redirect($oauthRequest['redirect_uri'] . '?' . http_build_query(array_filter($params)));
    }
}
