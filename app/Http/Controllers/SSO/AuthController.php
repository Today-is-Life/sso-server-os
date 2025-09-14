<?php

namespace App\Http\Controllers\SSO;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Domain;
use App\Services\SyncService;
use App\Services\SiemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    protected SyncService $syncService;
    protected SiemService $siemService;

    public function __construct(SyncService $syncService, SiemService $siemService)
    {
        $this->syncService = $syncService;
        $this->siemService = $siemService;
    }

    /**
     * Show login form
     */
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        $client_id = $request->get('client_id');
        $redirect_uri = $request->get('redirect_uri');
        $state = $request->get('state');
        
        // Validate OAuth parameters if present
        if ($client_id) {
            $domain = Domain::where('client_id', $client_id)->first();
            if (!$domain || !$domain->is_active) {
                return view('sso.error', ['message' => 'Invalid client']);
            }
        }
        
        return view('sso.login', compact('client_id', 'redirect_uri', 'state'));
    }

    /**
     * Handle login request
     */
    public function login(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
            'client_id' => 'nullable|string',
            'redirect_uri' => 'nullable|url',
            'state' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Find user by email hash
        $emailHash = hash('sha256', strtolower($request->email));
        $user = User::where('email_hash', $emailHash)->first();

        if (!$user) {
            // Log failed login attempt
            $this->siemService->logEvent(
                SiemService::EVENT_LOGIN_FAILURE,
                SiemService::LEVEL_WARNING,
                null,
                $request,
                ['email' => $request->email, 'reason' => 'user_not_found']
            );

            return back()->withErrors(['email' => 'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.'])->withInput();
        }

        // Check if account is locked
        $lockedUntil = $user->getAttribute('locked_until');
        if ($lockedUntil && $lockedUntil->isFuture()) {
            $minutes = $lockedUntil->diffInMinutes(now());
            return back()->withErrors(['email' => "Konto gesperrt. Bitte versuchen Sie es in {$minutes} Minuten erneut."])->withInput();
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            // Increment failed login attempts
            $failedAttempts = ($user->getAttribute('failed_login_attempts') ?? 0) + 1;
            $user->setAttribute('failed_login_attempts', $failedAttempts);

            // Log failed login attempt
            $this->siemService->logEvent(
                SiemService::EVENT_LOGIN_FAILURE,
                SiemService::LEVEL_WARNING,
                $user->id,
                $request,
                ['email' => $request->email, 'reason' => 'invalid_password', 'attempt_count' => $failedAttempts]
            );

            // Lock account after 5 failed attempts
            if ($failedAttempts >= 5) {
                $user->setAttribute('locked_until', now()->addMinutes(15));
                $user->save();

                // Log account lock
                $this->siemService->logEvent(
                    SiemService::EVENT_BRUTE_FORCE,
                    SiemService::LEVEL_CRITICAL,
                    $user->id,
                    $request,
                    ['locked_until' => $user->getAttribute('locked_until')->toISOString()]
                );

                return back()->withErrors(['email' => 'Zu viele fehlgeschlagene Anmeldeversuche. Konto für 15 Minuten gesperrt.'])->withInput();
            }

            $user->save();
            return back()->withErrors(['email' => 'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.'])->withInput();
        }

        // Check if email is verified (Superadmins bypass this check)
        $isSuperadmin = $user->groups()->where('slug', 'superadmin')->exists();
        if (!$user->email_verified_at && !$isSuperadmin) {
            return back()->withErrors(['email' => 'Bitte bestätigen Sie zuerst Ihre E-Mail-Adresse.'])->withInput();
        }

        // Check if MFA is enabled
        if ($user->mfa_enabled) {
            // Store user ID in session for MFA verification
            session(['mfa_user_id' => $user->id]);
            session(['mfa_redirect' => $request->all()]);
            return redirect()->route('sso.mfa');
        }

        // Login successful
        $this->completeLogin($user, $request->boolean('remember'));

        // Log successful login
        $this->siemService->logEvent(
            SiemService::EVENT_LOGIN_SUCCESS,
            SiemService::LEVEL_INFO,
            $user->id,
            $request,
            ['login_method' => 'password']
        );

        // Check if user is superadmin
        $isSuperadmin = $user->groups()
            ->where('slug', 'superadmin')
            ->exists();

        // Handle OAuth redirect
        if ($request->client_id) {
            // If superadmin is logging in via another domain, redirect to SSO admin
            if ($isSuperadmin) {
                // Store the original client request in session for later redirect
                session(['superadmin_return_client' => [
                    'client_id' => $request->client_id,
                    'redirect_uri' => $request->redirect_uri,
                    'state' => $request->state,
                ]]);
                return redirect('https://sso.todayislife.test/admin');
            }
            return $this->handleOAuthRedirect($user, $request);
        }

        // Direct login to SSO - redirect based on user type
        if ($isSuperadmin) {
            return redirect('/admin'); // SSO admin for superadmins
        }

        // For regular users/admins, redirect to intended or their domain
        return redirect()->intended('/admin');
    }

    /**
     * Complete login process
     */
    protected function completeLogin(User $user, bool $remember = false): void
    {
        // Reset failed login attempts
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->last_login_ip = request()->ip();
        $user->save();

        // Login user
        Auth::login($user, $remember);

        // Create session
        $token = Str::random(60);
        DB::table('user_sessions')->insert([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'last_activity' => now(),
            'expires_at' => now()->addMinutes(config('session.lifetime', 120)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Handle OAuth redirect after successful login
     */
    protected function handleOAuthRedirect(User $user, Request $request): RedirectResponse
    {
        // Generate authorization code
        $code = Str::random(40);
        
        DB::table('oauth_authorization_codes')->insert([
            'id' => Str::uuid(),
            'client_id' => $request->client_id,
            'user_id' => $user->id,
            'code' => $code,
            'redirect_uri' => $request->redirect_uri,
            'expires_at' => now()->addMinutes(10),
            'code_challenge' => $request->code_challenge ?? null,
            'code_challenge_method' => $request->code_challenge_method ?? null,
            'created_at' => now(),
        ]);

        $params = [
            'code' => $code,
            'state' => $request->state,
        ];

        return redirect($request->redirect_uri . '?' . http_build_query($params));
    }

    /**
     * Show registration form
     */
    public function showRegistrationForm(Request $request): View
    {
        $client_id = $request->get('client_id');
        $redirect_uri = $request->get('redirect_uri');
        $state = $request->get('state');
        
        // Check if registration is allowed
        if ($client_id) {
            $domain = Domain::where('client_id', $client_id)->first();
            if (!$domain || !$domain->is_active) {
                return view('sso.error', ['message' => 'Invalid client']);
            }
            
            $settings = json_decode($domain->settings, true);
            if (!($settings['allow_registration'] ?? true)) {
                return view('sso.error', ['message' => 'Registration is disabled for this domain']);
            }
        }
        
        return view('sso.register', compact('client_id', 'redirect_uri', 'state'));
    }

    /**
     * Handle registration request
     */
    public function register(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'terms' => 'required|accepted',
            'client_id' => 'nullable|string',
            'redirect_uri' => 'nullable|url',
            'state' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Check if email already exists
        $emailHash = hash('sha256', strtolower($request->email));
        if (User::where('email_hash', $emailHash)->exists()) {
            return back()->withErrors(['email' => 'Diese E-Mail-Adresse ist bereits registriert.'])->withInput();
        }

        // Get domain and default group
        $domainId = null;
        $defaultGroupId = null;
        
        if ($request->client_id) {
            $domain = Domain::where('client_id', $request->client_id)->first();
            if ($domain) {
                $domainId = $domain->id;
                // Find default user group for this domain
                $defaultGroup = DB::table('groups')
                    ->where('domain_id', $domainId)
                    ->where('slug', 'user')
                    ->first();
                if ($defaultGroup) {
                    $defaultGroupId = $defaultGroup->id;
                }
            }
        }

        // Create user
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->name = $request->name;
        $user->email = $request->email; // Will be encrypted by mutator
        $user->setAttribute('email_hash', $emailHash);
        $user->password = Hash::make($request->password);
        $user->setAttribute('locale', 'de');
        $user->setAttribute('timezone', 'Europe/Berlin');
        $user->save();

        // Assign to default group if available
        if ($defaultGroupId) {
            DB::table('user_groups')->insert([
                'user_id' => $user->id,
                'group_id' => $defaultGroupId,
                'assigned_at' => now(),
                'assigned_by' => null,
                'expires_at' => null,
                'is_primary' => true,
            ]);
        }

        // Send verification email
        $this->sendVerificationEmail($user);

        return redirect()->route('sso.login')
            ->with('success', 'Registrierung erfolgreich! Bitte bestätigen Sie Ihre E-Mail-Adresse.');
    }

    /**
     * Send verification email
     */
    protected function sendVerificationEmail(User $user): void
    {
        $token = Str::random(60);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email_hash,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Generate verification URL
        $verificationUrl = url('/auth/verify-email/' . urlencode($token) . '?email=' . urlencode($user->email_hash));

        // Send actual email
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)
                ->send(new \App\Mail\VerifyEmail($user, $verificationUrl));

            Log::info('Verification email sent to user: ' . $user->id);
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
            // In production, you might want to throw this error or handle it appropriately
        }
    }

    /**
     * Verify email address
     */
    public function verifyEmail(Request $request, string $token): RedirectResponse
    {
        $emailHash = $request->get('email');

        if (!$emailHash) {
            return redirect()->route('sso.login')
                ->withErrors(['email' => 'Ungültiger Verifikationslink.']);
        }

        // Find the verification token
        $verificationToken = DB::table('password_reset_tokens')
            ->where('email', $emailHash)
            ->where('created_at', '>', now()->subHours(24)) // Token valid for 24 hours
            ->first();

        if (!$verificationToken || !Hash::check($token, $verificationToken->token)) {
            return redirect()->route('sso.login')
                ->withErrors(['email' => 'Der Verifikationslink ist ungültig oder abgelaufen.']);
        }

        // Find user and verify email
        $user = User::where('email_hash', $emailHash)->first();

        if (!$user) {
            return redirect()->route('sso.login')
                ->withErrors(['email' => 'Benutzer nicht gefunden.']);
        }

        // Mark email as verified
        $user->email_verified_at = now();
        $user->save();

        // Delete verification token
        DB::table('password_reset_tokens')
            ->where('email', $emailHash)
            ->delete();

        Log::info('Email verified for user: ' . $user->id);

        return redirect()->route('sso.login')
            ->with('success', 'E-Mail-Adresse erfolgreich bestätigt! Sie können sich jetzt anmelden.');
    }

    /**
     * Show MFA form
     */
    public function showMfaForm(): View
    {
        if (!session('mfa_user_id')) {
            return redirect()->route('sso.login');
        }

        return view('sso.mfa');
    }

    /**
     * Verify MFA token
     */
    public function verifyMfa(Request $request): RedirectResponse
    {
        $userId = session('mfa_user_id');

        if (!$userId) {
            return redirect()->route('sso.login');
        }

        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('sso.login');
        }

        // Verify MFA token
        if (!$user->verify2FAToken($request->token)) {
            return back()->withErrors(['token' => 'Ungültiger MFA-Code.']);
        }

        // Complete login process
        $originalRequest = session('mfa_redirect', []);
        $this->completeLogin($user, false);

        // Clear MFA session data
        session()->forget(['mfa_user_id', 'mfa_redirect']);

        // Handle OAuth redirect if needed
        if (isset($originalRequest['client_id'])) {
            return $this->handleOAuthRedirect($user, new Request($originalRequest));
        }

        return redirect()->intended('/admin');
    }

    /**
     * Logout
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        
        if ($user) {
            // Delete user sessions
            DB::table('user_sessions')
                ->where('user_id', $user->id)
                ->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Handle OAuth logout redirect
        if ($request->has('post_logout_redirect_uri')) {
            return redirect($request->post_logout_redirect_uri);
        }

        return redirect('/');
    }
}
