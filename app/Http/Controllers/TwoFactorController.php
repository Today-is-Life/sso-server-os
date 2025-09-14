<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TwoFactorController extends Controller
{
    /**
     * Show 2FA setup page
     */
    public function setup(): View
    {
        $user = Auth::user();

        if ($user->mfa_enabled) {
            return redirect()->route('2fa.manage')
                ->with('info', '2FA ist bereits aktiviert.');
        }

        // Generate secret if not exists
        if (!$user->mfa_secret) {
            $user->generate2FASecret();
        }

        return view('2fa.setup', [
            'qrCodeUrl' => $user->get2FAQRCodeUrl(),
            'provisioningUri' => $user->get2FAProvisioningUri(),
            'secret' => $user->mfa_secret,
        ]);
    }

    /**
     * Enable 2FA
     */
    public function enable(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|size:6',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator);
        }

        $user = Auth::user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            $error = ['password' => 'Passwort ist nicht korrekt.'];
            if ($request->expectsJson()) {
                return response()->json(['errors' => $error], 422);
            }
            return back()->withErrors($error);
        }

        // Enable 2FA
        if (!$user->enable2FA($request->token)) {
            $error = ['token' => 'Ungültiger 2FA-Code. Bitte versuchen Sie es erneut.'];
            if ($request->expectsJson()) {
                return response()->json(['errors' => $error], 422);
            }
            return back()->withErrors($error);
        }

        $recoveryCodes = $user->mfa_recovery_codes
            ? array_map(fn($code) => decrypt($code), $user->mfa_recovery_codes)
            : [];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => '2FA wurde erfolgreich aktiviert!',
                'recovery_codes' => $recoveryCodes,
            ]);
        }

        return redirect()->route('2fa.manage')
            ->with('success', '2FA wurde erfolgreich aktiviert!')
            ->with('recovery_codes', $recoveryCodes);
    }

    /**
     * Show 2FA management page
     */
    public function manage(): View
    {
        $user = Auth::user();

        return view('2fa.manage', [
            'mfaEnabled' => $user->mfa_enabled,
            'recoveryCodes' => $user->mfa_recovery_codes ? count($user->mfa_recovery_codes) : 0,
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator);
        }

        $user = Auth::user();

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            $error = ['password' => 'Passwort ist nicht korrekt.'];
            if ($request->expectsJson()) {
                return response()->json(['errors' => $error], 422);
            }
            return back()->withErrors($error);
        }

        $user->disable2FA();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => '2FA wurde deaktiviert.',
            ]);
        }

        return redirect()->route('2fa.setup')
            ->with('success', '2FA wurde deaktiviert. Sie können es jederzeit wieder aktivieren.');
    }

    /**
     * Generate new recovery codes
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return back()->withErrors($validator);
        }

        $user = Auth::user();

        if (!$user->mfa_enabled) {
            if ($request->expectsJson()) {
                return response()->json(['error' => '2FA ist nicht aktiviert.'], 400);
            }
            return back()->withErrors(['error' => '2FA ist nicht aktiviert.']);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            $error = ['password' => 'Passwort ist nicht korrekt.'];
            if ($request->expectsJson()) {
                return response()->json(['errors' => $error], 422);
            }
            return back()->withErrors($error);
        }

        // Generate new recovery codes
        $recoveryCodes = $user->generateRecoveryCodes();

        Log::info('Recovery codes regenerated for user: ' . $user->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Neue Recovery-Codes wurden generiert.',
                'recovery_codes' => $recoveryCodes,
            ]);
        }

        return redirect()->route('2fa.manage')
            ->with('success', 'Neue Recovery-Codes wurden generiert.')
            ->with('recovery_codes', $recoveryCodes);
    }

    /**
     * Show recovery codes
     */
    public function showRecoveryCodes(Request $request): JsonResponse|View
    {
        $user = Auth::user();

        if (!$user->mfa_enabled || !$user->mfa_recovery_codes) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Keine Recovery-Codes verfügbar.'], 404);
            }
            return redirect()->route('2fa.manage');
        }

        // For security, we'll only show codes immediately after generation
        $recoveryCodes = session('recovery_codes', []);

        if (empty($recoveryCodes)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Recovery-Codes sind nur direkt nach der Generierung sichtbar.'], 403);
            }
            return redirect()->route('2fa.manage')
                ->with('warning', 'Recovery-Codes sind nur direkt nach der Generierung sichtbar.');
        }

        if ($request->expectsJson()) {
            return response()->json(['recovery_codes' => $recoveryCodes]);
        }

        return view('2fa.recovery-codes', ['recoveryCodes' => $recoveryCodes]);
    }
}
