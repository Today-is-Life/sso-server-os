<?php

namespace App\Http\Controllers\SSO;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Domain;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected $syncService;

    public function __construct(SyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Show login form
     */
    public function showLoginForm(Request $request)
    {
        $client_id = $request->get('client_id');
        $redirect_uri = $request->get('redirect_uri');
        $state = $request->get('state');
        
        // Validate OAuth parameters if present
        if ($client_id) {
            $domain = Domain::on('sso')->where('client_id', $client_id)->first();
            if (!$domain || !$domain->is_active) {
                return view('sso.error', ['message' => 'Invalid client']);
            }
        }
        
        return view('sso.login', compact('client_id', 'redirect_uri', 'state'));
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
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
        $user = User::on('sso')->where('email_hash', $emailHash)->first();

        if (!$user) {
            return back()->withErrors(['email' => 'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.'])->withInput();
        }

        // Check if account is locked
        if ($user->locked_until && $user->locked_until->isFuture()) {
            $minutes = $user->locked_until->diffInMinutes(now());
            return back()->withErrors(['email' => "Konto gesperrt. Bitte versuchen Sie es in {$minutes} Minuten erneut."])->withInput();
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            // Increment failed login attempts
            $user->failed_login_attempts++;
            
            // Lock account after 5 failed attempts
            if ($user->failed_login_attempts >= 5) {
                $user->locked_until = now()->addMinutes(15);
                $user->save();
                return back()->withErrors(['email' => 'Zu viele fehlgeschlagene Anmeldeversuche. Konto für 15 Minuten gesperrt.'])->withInput();
            }
            
            $user->save();
            return back()->withErrors(['email' => 'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen überein.'])->withInput();
        }

        // Check if email is verified
        if (!$user->email_verified_at) {
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
        $this->completeLogin($user, $request->remember);

        // Handle OAuth redirect
        if ($request->client_id) {
            return $this->handleOAuthRedirect($user, $request);
        }

        return redirect()->intended('/dashboard');
    }

    /**
     * Complete login process
     */
    protected function completeLogin($user, $remember = false)
    {
        // Reset failed login attempts
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->last_login_ip = request()->ip();
        $user->save();

        // Login user
        Auth::guard('sso')->login($user, $remember);

        // Create session
        DB::connection('sso')->table('user_sessions')->insert([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'token' => Str::random(60),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addMinutes(config('session.lifetime', 120)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Handle OAuth redirect after successful login
     */
    protected function handleOAuthRedirect($user, Request $request)
    {
        // Generate authorization code
        $code = Str::random(40);
        
        DB::connection('sso')->table('oauth_authorization_codes')->insert([
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
    public function showRegistrationForm(Request $request)
    {
        $client_id = $request->get('client_id');
        $redirect_uri = $request->get('redirect_uri');
        $state = $request->get('state');
        
        // Check if registration is allowed
        if ($client_id) {
            $domain = Domain::on('sso')->where('client_id', $client_id)->first();
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
    public function register(Request $request)
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
        if (User::on('sso')->where('email_hash', $emailHash)->exists()) {
            return back()->withErrors(['email' => 'Diese E-Mail-Adresse ist bereits registriert.'])->withInput();
        }

        // Get domain and default group
        $domainId = null;
        $defaultGroupId = null;
        
        if ($request->client_id) {
            $domain = Domain::on('sso')->where('client_id', $request->client_id)->first();
            if ($domain) {
                $domainId = $domain->id;
                // Find default user group for this domain
                $defaultGroup = DB::connection('sso')->table('groups')
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
        $user->setConnection('sso');
        $user->id = Str::uuid();
        $user->name = $request->name;
        $user->email = $request->email; // Will be encrypted by mutator
        $user->email_hash = $emailHash;
        $user->password = Hash::make($request->password);
        $user->locale = 'de';
        $user->timezone = 'Europe/Berlin';
        $user->save();

        // Assign to default group if available
        if ($defaultGroupId) {
            DB::connection('sso')->table('user_groups')->insert([
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
    protected function sendVerificationEmail($user)
    {
        $token = Str::random(60);
        
        DB::connection('sso')->table('password_reset_tokens')->insert([
            'email' => $user->email_hash,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // TODO: Send actual email
        Log::info('Verification email would be sent to: ' . $user->email);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $user = Auth::guard('sso')->user();
        
        if ($user) {
            // Delete user sessions
            DB::connection('sso')->table('user_sessions')
                ->where('user_id', $user->id)
                ->delete();
        }

        Auth::guard('sso')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Handle OAuth logout redirect
        if ($request->has('post_logout_redirect_uri')) {
            return redirect($request->post_logout_redirect_uri);
        }

        return redirect('/');
    }
}
