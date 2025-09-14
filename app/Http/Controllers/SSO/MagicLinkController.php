<?php

namespace App\Http\Controllers\SSO;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\MagicLinkEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    /**
     * Show magic link request form
     */
    public function showRequestForm(Request $request): View
    {
        $client_id = $request->get('client_id');
        $redirect_uri = $request->get('redirect_uri');
        $state = $request->get('state');

        return view('sso.magic-link', compact('client_id', 'redirect_uri', 'state'));
    }

    /**
     * Send magic link
     */
    public function sendMagicLink(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
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
            // For security, don't reveal if user exists
            return back()->with('success', 'Falls ein Konto mit dieser E-Mail-Adresse existiert, wurde ein Magic Link versendet.');
        }

        // Generate magic link token
        $token = Str::random(64);
        $hashedToken = hash('sha256', $token);

        // Store magic link in database
        DB::table('magic_links')->insert([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'token' => $hashedToken,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'redirect_url' => $request->redirect_uri,
            'metadata' => json_encode([
                'client_id' => $request->client_id,
                'state' => $request->state,
            ]),
            'expires_at' => now()->addMinutes(10), // 10 minutes expiry
            'created_at' => now(),
        ]);

        // Generate magic URL
        $magicUrl = route('sso.magic.verify', ['token' => $token]) .
            '?' . http_build_query(array_filter([
                'client_id' => $request->client_id,
                'redirect_uri' => $request->redirect_uri,
                'state' => $request->state,
            ]));

        // Send magic link email
        try {
            Mail::to($user->email)->send(
                new MagicLinkEmail($user, $magicUrl, $request->ip(), $request->userAgent())
            );

            Log::info('Magic link sent to user: ' . $user->id);
        } catch (\Exception $e) {
            Log::error('Failed to send magic link email: ' . $e->getMessage());
            return back()->withErrors(['email' => 'Fehler beim Versenden der E-Mail. Bitte versuchen Sie es später erneut.']);
        }

        return back()->with('success', 'Magic Link wurde an Ihre E-Mail-Adresse gesendet!');
    }

    /**
     * Verify magic link and authenticate user
     */
    public function verify(Request $request, string $token): RedirectResponse
    {
        $hashedToken = hash('sha256', $token);

        // Find and validate magic link
        $magicLink = DB::table('magic_links')
            ->where('token', $hashedToken)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if (!$magicLink) {
            return redirect()->route('sso.login')
                ->withErrors(['email' => 'Der Magic Link ist ungültig oder abgelaufen.']);
        }

        // Verify IP address for additional security
        if ($magicLink->ip_address !== $request->ip()) {
            Log::warning('Magic link used from different IP', [
                'original_ip' => $magicLink->ip_address,
                'current_ip' => $request->ip(),
                'user_id' => $magicLink->user_id
            ]);
        }

        // Find user
        $user = User::find($magicLink->user_id);

        if (!$user) {
            return redirect()->route('sso.login')
                ->withErrors(['email' => 'Benutzer nicht gefunden.']);
        }

        // Mark magic link as used
        DB::table('magic_links')
            ->where('id', $magicLink->id)
            ->update(['used_at' => now()]);

        // Login user
        Auth::login($user);

        // Update user login information
        $user->recordSuccessfulLogin($request->ip());

        // Parse metadata for OAuth redirect
        $metadata = json_decode($magicLink->metadata, true);

        Log::info('User authenticated via magic link', ['user_id' => $user->id]);

        // Handle OAuth redirect
        if (!empty($metadata['client_id'])) {
            return $this->handleOAuthRedirect($user, $request, $metadata);
        }

        // Handle direct redirect
        if ($magicLink->redirect_url) {
            return redirect($magicLink->redirect_url);
        }

        // Default redirect
        return redirect()->intended('/admin');
    }

    /**
     * Handle OAuth redirect after magic link authentication
     */
    protected function handleOAuthRedirect(User $user, Request $request, array $metadata): RedirectResponse
    {
        // Generate authorization code
        $code = Str::random(40);

        DB::table('oauth_authorization_codes')->insert([
            'id' => Str::uuid(),
            'client_id' => $metadata['client_id'],
            'user_id' => $user->id,
            'code' => $code,
            'redirect_uri' => $request->get('redirect_uri') ?: $metadata['redirect_uri'] ?? '',
            'expires_at' => now()->addMinutes(10),
            'code_challenge' => $request->code_challenge ?? null,
            'code_challenge_method' => $request->code_challenge_method ?? null,
            'created_at' => now(),
        ]);

        $redirectUri = $request->get('redirect_uri') ?: $metadata['redirect_uri'] ?? '';
        $params = [
            'code' => $code,
            'state' => $request->get('state') ?: $metadata['state'] ?? '',
        ];

        return redirect($redirectUri . '?' . http_build_query(array_filter($params)));
    }
}
